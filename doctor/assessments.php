<?php
/**
 * Doctor Assessments Management Page - Optimized
 * Allows doctors to manage assessment questions and view patient results
 */

// Load optimized bootstrap
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/../mail/Mailer.php';

// Handle GET request for statistics details (with caching)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_stats_detail') {
    header('Content-Type: application/json');
    $severity = sanitizeInput($_GET['severity'] ?? 'total');
    
    try {
        if ($severity === 'total') {
            $assessments = $queryOptimizer->cachedQuery("
                SELECT 
                    a.id,
                    a.score,
                    a.severity,
                    a.created_at,
                    u.name as patient_name,
                    u.email as patient_email
                FROM assessments a
                JOIN users u ON a.user_id = u.id
                INNER JOIN user_doctor_connections udc ON a.user_id = udc.user_id
                WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND udc.doctor_id = ?
                AND udc.status = 'active'
                ORDER BY a.created_at DESC
                LIMIT 100
            ", [$currentUser['id']], 300);
        } else {
            $assessments = $queryOptimizer->cachedQuery("
                SELECT 
                    a.id,
                    a.score,
                    a.severity,
                    a.created_at,
                    u.name as patient_name,
                    u.email as patient_email
                FROM assessments a
                JOIN users u ON a.user_id = u.id
                INNER JOIN user_doctor_connections udc ON a.user_id = udc.user_id
                WHERE a.severity = ? 
                AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND udc.doctor_id = ?
                AND udc.status = 'active'
                ORDER BY a.created_at DESC
                LIMIT 100
            ", [$severity, $currentUser['id']], 300);
        }
        
        jsonResponse([
            'success' => true,
            'assessments' => $assessments,
            'count' => count($assessments)
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// Handle GET request for patient assessments
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_patient_assessments') {
    // Clean any output buffer to prevent HTML from mixing with JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    header('Content-Type: application/json');
    
    $userId = intval($_GET['user_id'] ?? 0);
    
    try {
        // Verify user_id is provided
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID provided']);
            exit;
        }
        
        // Check if doctor is connected to this patient (optional - may want to allow viewing all)
        $connectionCheck = $conn->prepare("
            SELECT COUNT(*) as is_connected 
            FROM user_doctor_connections 
            WHERE user_id = ? AND doctor_id = ? AND status = 'active'
        ");
        $connectionCheck->execute([$userId, $currentUser['id']]);
        $isConnected = $connectionCheck->fetch(PDO::FETCH_ASSOC)['is_connected'];
        
        // Get all assessments for this patient
        $stmt = $conn->prepare("
            SELECT 
                a.id,
                a.user_id,
                a.type,
                a.score,
                a.max_score,
                a.severity,
                a.created_at,
                a.category_scores,
                a.responses,
                u.name as patient_name,
                u.email as patient_email,
                u.avatar,
                (SELECT COUNT(*) FROM assessment_views 
                 WHERE assessment_id = a.id AND doctor_id = ?) as is_viewed,
                (SELECT COUNT(*) FROM doctor_recommendations 
                 WHERE assessment_id = a.id AND doctor_id = ?) as has_recommendation
            FROM assessments a
            JOIN users u ON a.user_id = u.id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id'], $userId]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get patient info
        $patientStmt = $conn->prepare("SELECT id, name, email, avatar FROM users WHERE id = ?");
        $patientStmt->execute([$userId]);
        $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            echo json_encode(['success' => false, 'message' => 'Patient not found']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'patient' => $patient,
            'assessments' => $assessments,
            'count' => count($assessments),
            'is_connected' => $isConnected > 0
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Error in get_patient_assessments: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'line' => $e->getLine()]);
        exit;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'create_question') {
            $stmt = $conn->prepare("
                INSERT INTO assessment_questions 
                (question_text, question_type, category, options, weight, order_index, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            
            // Get the next order index
            $orderStmt = $conn->query("SELECT MAX(order_index) as max_order FROM assessment_questions");
            $maxOrder = $orderStmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;
            
            $options = json_encode(array_values(array_filter($_POST['options'] ?? [])));
            
            $result = $stmt->execute([
                $_POST['question_text'],
                $_POST['question_type'],
                $_POST['category'],
                $options,
                floatval($_POST['weight'] ?? 1.0),
                $maxOrder + 1
            ]);
            
            echo json_encode(['success' => $result, 'message' => 'Question created successfully']);
            exit;
        }
        
        if ($_POST['action'] === 'update_question') {
            $stmt = $conn->prepare("
                UPDATE assessment_questions 
                SET question_text = ?, question_type = ?, category = ?, options = ?, weight = ?, is_active = ?
                WHERE id = ?
            ");
            
            $options = json_encode(array_values(array_filter($_POST['options'] ?? [])));
            
            $result = $stmt->execute([
                $_POST['question_text'],
                $_POST['question_type'],
                $_POST['category'],
                $options,
                floatval($_POST['weight'] ?? 1.0),
                intval($_POST['is_active'] ?? 1),
                intval($_POST['question_id'])
            ]);
            
            echo json_encode(['success' => $result, 'message' => 'Question updated successfully']);
            exit;
        }
        
        if ($_POST['action'] === 'delete_question') {
            $stmt = $conn->prepare("UPDATE assessment_questions SET is_active = 0 WHERE id = ?");
            $result = $stmt->execute([intval($_POST['question_id'])]);
            
            echo json_encode(['success' => $result, 'message' => 'Question deactivated successfully']);
            exit;
        }
        
        if ($_POST['action'] === 'connect_with_patient') {
            // Create connection when patient fails assessment
            $stmt = $conn->prepare("
                INSERT INTO user_doctor_connections (user_id, doctor_id, assessment_id, status) 
                VALUES (?, ?, ?, 'active')
            ");
            
            $result = $stmt->execute([
                intval($_POST['patient_id']),
                $currentUser['id'],
                intval($_POST['assessment_id'])
            ]);
            
            // Send message to patient
            $messageStmt = $conn->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, body) 
                VALUES (?, ?, ?, ?)
            ");
            
            $messageStmt->execute([
                $currentUser['id'],
                intval($_POST['patient_id']),
                'Health Assessment Follow-up',
                'Hello, I noticed your recent health assessment results and would like to offer my support and guidance. Please feel free to reach out if you have any questions or need assistance.'
            ]);
            
            echo json_encode(['success' => $result, 'message' => 'Connected with patient successfully']);
            exit;
        }
        
        if ($_POST['action'] === 'get_assessment_answers') {
            // Get assessment with user answers
            $stmt = $conn->prepare("
                SELECT a.*, u.name as patient_name, u.email as patient_email,
                       a.responses as answers
                FROM assessments a
                JOIN users u ON a.user_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([intval($_POST['assessment_id'])]);
            $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$assessment) {
                echo json_encode(['success' => false, 'message' => 'Assessment not found for ID: ' . intval($_POST['assessment_id'])]);
                exit;
            }
            
            // Add debug info
            if (!$assessment['answers']) {
                echo json_encode(['success' => false, 'message' => 'No responses found in assessment']);
                exit;
            }
            
            // Parse answers - handle both old and new format
            $answersData = json_decode($assessment['answers'], true);
            
            // Check if it's the new nested format or old flat format
            if (isset($answersData['answers'])) {
                // New format: {"type":"mental","answers":{...}}
                $answers = $answersData['answers'];
            } elseif (isset($answersData['type'])) {
                // Another variation of new format
                $answers = $answersData['answers'] ?? [];
            } else {
                // Old format: {"1":"3","2":"1",...}
                $answers = $answersData;
            }
            
            // Get questions that were answered
            $questionsStmt = $conn->query("
                SELECT id, question_text, question_type, category, options 
                FROM assessment_questions 
                WHERE is_active = 1
                ORDER BY category, order_index
            ");
            $allQuestions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Match questions with answers
            $questionsWithAnswers = [];
            if (is_array($answers)) {
                foreach ($allQuestions as $question) {
                    // Check if answer exists (handle both string and integer keys)
                    $answerId = $question['id'];
                    $answerValue = $answers[$answerId] ?? $answers[(string)$answerId] ?? null;
                    
                    if ($answerValue !== null) {
                        $question['user_answer'] = $answerValue;
                        $question['options'] = json_decode($question['options'], true);
                        $questionsWithAnswers[] = $question;
                    }
                }
            }
            
            // Check if doctor has already provided recommendation
            $recStmt = $conn->prepare("
                SELECT recommendation_text, created_at 
                FROM doctor_recommendations 
                WHERE assessment_id = ? AND doctor_id = ?
                ORDER BY created_at DESC LIMIT 1
            ");
            $recStmt->execute([intval($_POST['assessment_id']), $currentUser['id']]);
            $existingRecommendation = $recStmt->fetch(PDO::FETCH_ASSOC);
            
            // Mark this assessment as viewed by this doctor
            $viewStmt = $conn->prepare("
                INSERT INTO assessment_views (assessment_id, doctor_id) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP
            ");
            $viewStmt->execute([intval($_POST['assessment_id']), $currentUser['id']]);
            
            echo json_encode([
                'success' => true,
                'assessment' => $assessment,
                'questions' => $questionsWithAnswers,
                'existing_recommendation' => $existingRecommendation
            ]);
            exit;
        }
        
        if ($_POST['action'] === 'save_doctor_recommendation') {
            // Save doctor's recommendation
            $stmt = $conn->prepare("
                INSERT INTO doctor_recommendations 
                (assessment_id, doctor_id, recommendation_text, reviewed_answers) 
                VALUES (?, ?, ?, ?)
            ");
            
            $reviewedAnswers = json_encode([
                'doctor_name' => $currentUser['name'],
                'review_date' => date('Y-m-d H:i:s'),
                'notes' => $_POST['notes'] ?? ''
            ]);
            
            $result = $stmt->execute([
                intval($_POST['assessment_id']),
                $currentUser['id'],
                $_POST['recommendation_text'],
                $reviewedAnswers
            ]);
            
            if ($result) {
                // Send notification to patient via messaging system
                $messageStmt = $conn->prepare("
                    INSERT INTO messages (sender_id, recipient_id, subject, body) 
                    VALUES (?, ?, ?, ?)
                ");
                
                $messageStmt->execute([
                    $currentUser['id'],
                    intval($_POST['patient_id']),
                    'Doctor Recommendation - Assessment Review',
                    'I have reviewed your recent health assessment and provided personalized recommendations. Please check your dashboard for details.'
                ]);
                
                // Get patient details for email
                $patientStmt = $conn->prepare("
                    SELECT u.name, u.email, a.score, a.severity 
                    FROM users u
                    JOIN assessments a ON a.id = ?
                    WHERE u.id = ?
                ");
                $patientStmt->execute([intval($_POST['assessment_id']), intval($_POST['patient_id'])]);
                $patientInfo = $patientStmt->fetch(PDO::FETCH_ASSOC);
                
                // Get doctor specialty
                $doctorStmt = $conn->prepare("
                    SELECT specialty FROM doctors WHERE id = ?
                ");
                $doctorStmt->execute([$currentUser['id']]);
                $doctorInfo = $doctorStmt->fetch(PDO::FETCH_ASSOC);
                
                // Send email notification to patient
                if ($patientInfo) {
                    try {
                        $mailer = new Mailer();
                        $emailResult = $mailer->sendDoctorRecommendationEmail(
                            [
                                'name' => $patientInfo['name'],
                                'email' => $patientInfo['email']
                            ],
                            [
                                'name' => $currentUser['name'],
                                'specialty' => $doctorInfo['specialty'] ?? 'Healthcare Provider'
                            ],
                            [
                                'recommendation_text' => $_POST['recommendation_text'],
                                'assessment_score' => $patientInfo['score'],
                                'assessment_severity' => $patientInfo['severity'],
                                'review_date' => date('F j, Y')
                            ]
                        );
                        
                        if (!$emailResult['success']) {
                            error_log("Failed to send doctor recommendation email: " . $emailResult['message']);
                        }
                    } catch (Exception $e) {
                        error_log("Doctor recommendation email error: " . $e->getMessage());
                    }
                }
            }
            
            echo json_encode(['success' => $result, 'message' => 'Recommendation saved and sent to patient successfully']);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage(), 'line' => $e->getLine(), 'file' => basename($e->getFile())]);
        exit;
    }
}

// Get all assessment questions
$questionsStmt = $conn->query("
    SELECT * FROM assessment_questions 
    ORDER BY category, order_index
");
$questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);

// SECURITY: Get unique patients with assessments requiring attention - ONLY CONNECTED USERS
$patientsStmt = $conn->prepare("
    SELECT DISTINCT
        u.id as user_id,
        u.name as patient_name,
        u.email as patient_email,
        u.avatar,
        (SELECT COUNT(*) FROM assessments 
         WHERE user_id = u.id AND severity IN ('critical', 'poor', 'fair')) as total_assessments,
        (SELECT COUNT(*) FROM assessments a2
         WHERE a2.user_id = u.id 
         AND a2.severity IN ('critical', 'poor', 'fair')
         AND NOT EXISTS (SELECT 1 FROM assessment_views av WHERE av.assessment_id = a2.id AND av.doctor_id = ?)) as unviewed_count,
        (SELECT severity FROM assessments 
         WHERE user_id = u.id AND severity IN ('critical', 'poor', 'fair')
         ORDER BY 
            CASE severity
                WHEN 'critical' THEN 1
                WHEN 'poor' THEN 2
                WHEN 'fair' THEN 3
            END
         LIMIT 1) as highest_severity,
        (SELECT created_at FROM assessments 
         WHERE user_id = u.id 
         ORDER BY created_at DESC LIMIT 1) as last_assessment_date,
        1 as is_connected
    FROM users u
    INNER JOIN user_doctor_connections udc ON u.id = udc.user_id
    WHERE udc.doctor_id = ?
    AND udc.status = 'active'
    AND EXISTS (
        SELECT 1 FROM assessments a
        WHERE a.user_id = u.id 
        AND a.severity IN ('critical', 'poor', 'fair')
    )
    ORDER BY 
        (SELECT COUNT(*) FROM assessments a2
         WHERE a2.user_id = u.id 
         AND a2.severity IN ('critical', 'poor', 'fair')
         AND NOT EXISTS (SELECT 1 FROM assessment_views av WHERE av.assessment_id = a2.id AND av.doctor_id = ?)) DESC,
        (SELECT MIN(CASE severity
            WHEN 'critical' THEN 1
            WHEN 'poor' THEN 2
            WHEN 'fair' THEN 3
        END) FROM assessments WHERE user_id = u.id AND severity IN ('critical', 'poor', 'fair')),
        last_assessment_date DESC
    LIMIT 50
");
$patientsStmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id']]);
$patients = $patientsStmt->fetchAll(PDO::FETCH_ASSOC);

// SECURITY: Get statistics - only from connected patients
$statsStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_assessments,
        COUNT(CASE WHEN a.severity = 'critical' THEN 1 END) as critical_count,
        COUNT(CASE WHEN a.severity = 'poor' THEN 1 END) as poor_count,
        COUNT(CASE WHEN a.severity = 'fair' THEN 1 END) as fair_count,
        COUNT(CASE WHEN a.severity = 'good' THEN 1 END) as good_count,
        COUNT(CASE WHEN a.severity = 'excellent' THEN 1 END) as excellent_count
    FROM assessments a
    INNER JOIN user_doctor_connections udc ON a.user_id = udc.user_id
    WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND udc.doctor_id = ?
    AND udc.status = 'active'
");
$statsStmt->execute([$currentUser['id']]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// SECURITY: Get unviewed critical/poor assessments count - only from connected patients
$unviewedStmt = $conn->prepare("
    SELECT COUNT(*) as unviewed_count
    FROM assessments a
    INNER JOIN user_doctor_connections udc ON a.user_id = udc.user_id
    WHERE a.severity IN ('critical', 'poor')
    AND udc.doctor_id = ?
    AND udc.status = 'active'
    AND NOT EXISTS (
        SELECT 1 FROM assessment_views av 
        WHERE av.assessment_id = a.id AND av.doctor_id = ?
    )
");
$unviewedStmt->execute([$currentUser['id'], $currentUser['id']]);
$unviewedCount = $unviewedStmt->fetch(PDO::FETCH_ASSOC)['unviewed_count'];

$title = "Assessment Management - Doctor Portal";
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-4xl font-bold bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent mb-2">
                        Assessment Management
                    </h1>
                    <p class="text-gray-600">Manage health assessment questions and monitor patient results</p>
                </div>
                <button onclick="openCreateQuestionModal()" 
                        class="px-6 py-3 rounded-xl font-semibold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                        style="background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Question
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <div onclick="showStatDetails('total')" class="bg-white rounded-xl p-4 shadow-lg transform transition-all hover:scale-105 cursor-pointer">
                    <div class="text-sm text-gray-600 mb-1">Total (30d)</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_assessments']; ?></div>
                </div>
                <div onclick="showStatDetails('critical')" class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-4 shadow-lg transform transition-all hover:scale-105 cursor-pointer">
                    <div class="text-sm text-red-700 mb-1">Critical</div>
                    <div class="text-2xl font-bold text-red-900"><?php echo $stats['critical_count']; ?></div>
                </div>
                <div onclick="showStatDetails('poor')" class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4 shadow-lg transform transition-all hover:scale-105 cursor-pointer">
                    <div class="text-sm text-orange-700 mb-1">Poor</div>
                    <div class="text-2xl font-bold text-orange-900"><?php echo $stats['poor_count']; ?></div>
                </div>
                <div onclick="showStatDetails('fair')" class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-4 shadow-lg transform transition-all hover:scale-105 cursor-pointer">
                    <div class="text-sm text-yellow-700 mb-1">Fair</div>
                    <div class="text-2xl font-bold text-yellow-900"><?php echo $stats['fair_count']; ?></div>
                </div>
                <div onclick="showStatDetails('good')" class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 shadow-lg transform transition-all hover:scale-105 cursor-pointer">
                    <div class="text-sm text-blue-700 mb-1">Good</div>
                    <div class="text-2xl font-bold text-blue-900"><?php echo $stats['good_count']; ?></div>
                </div>
                <div onclick="showStatDetails('excellent')" class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 shadow-lg transform transition-all hover:scale-105 cursor-pointer">
                    <div class="text-sm text-green-700 mb-1">Excellent</div>
                    <div class="text-2xl font-bold text-green-900"><?php echo $stats['excellent_count']; ?></div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6">
            <div class="bg-white rounded-2xl shadow-lg p-2 inline-flex">
                <button onclick="switchTab('questions')" id="tab-questions" 
                        class="tab-button active px-6 py-3 rounded-xl font-semibold transition-all duration-300">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Assessment Questions
                </button>
                <button onclick="switchTab('patients')" id="tab-patients" 
                        class="tab-button px-6 py-3 rounded-xl font-semibold transition-all duration-300">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Patient Results
                    <?php if ($unviewedCount > 0): ?>
                        <span class="ml-2 px-2 py-1 bg-red-500 text-white text-xs rounded-full animate-pulse">
                            <?php echo $unviewedCount; ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Questions Tab -->
        <div id="content-questions" class="tab-content">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold text-gray-900">Assessment Questions</h2>
                        <div class="flex items-center gap-4">
                            <select id="category-filter" onchange="filterQuestions()" 
                                    class="px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-accent">
                                <option value="">All Categories</option>
                                <option value="mental_health">Mental Health</option>
                                <option value="physical_health">Physical Health</option>
                                <option value="lifestyle">Lifestyle</option>
                                <option value="nutrition">Nutrition</option>
                                <option value="stress">Stress Management</option>
                            </select>
                            <span class="text-sm text-gray-600">Total: <?php echo count($questions); ?> questions</span>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-gray-200">
                    <?php 
                    $groupedQuestions = [];
                    foreach ($questions as $question) {
                        $groupedQuestions[$question['category']][] = $question;
                    }
                    
                    foreach ($groupedQuestions as $category => $categoryQuestions): 
                    ?>
                        <div class="question-category" data-category="<?php echo $category; ?>">
                            <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-4">
                                <h3 class="text-lg font-bold text-gray-900 capitalize">
                                    <?php echo str_replace('_', ' ', $category); ?>
                                    <span class="ml-2 text-sm font-normal text-gray-500">(<?php echo count($categoryQuestions); ?> questions)</span>
                                </h3>
                            </div>
                            
                            <?php foreach ($categoryQuestions as $question): ?>
                                <div class="question-item p-6 hover:bg-gray-50 transition-colors duration-200">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-3">
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?php 
                                                    echo $question['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; 
                                                ?>">
                                                    <?php echo $question['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                                <span class="px-3 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">
                                                    <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?>
                                                </span>
                                                <span class="text-sm text-gray-500">Weight: <?php echo $question['weight']; ?></span>
                                            </div>
                                            
                                            <h4 class="text-lg font-semibold text-gray-900 mb-3">
                                                <?php echo htmlspecialchars($question['question_text']); ?>
                                            </h4>
                                            
                                            <?php 
                                            $options = json_decode($question['options'], true);
                                            if (!empty($options)): 
                                            ?>
                                                <div class="grid gap-2">
                                                    <?php foreach ($options as $index => $option): ?>
                                                        <div class="flex items-center gap-2 text-sm text-gray-600">
                                                            <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs font-semibold">
                                                                <?php echo $index + 1; ?>
                                                            </div>
                                                            <span><?php echo htmlspecialchars($option); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="flex flex-col gap-2">
                                            <button onclick='editQuestion(<?php echo json_encode($question); ?>)' 
                                                    class="px-4 py-2 bg-blue-100 text-blue-700 rounded-xl font-semibold hover:bg-blue-200 transition-all">
                                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                Edit
                                            </button>
                                            <button onclick="deleteQuestion(<?php echo $question['id']; ?>)" 
                                                    class="px-4 py-2 bg-red-100 text-red-700 rounded-xl font-semibold hover:bg-red-200 transition-all">
                                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Patient Results Tab -->
        <div id="content-patients" class="tab-content hidden">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-900">Patient Assessment Results</h2>
                    <p class="text-gray-600 mt-1">Patients requiring attention (Critical, Poor, and Fair assessments)</p>
                </div>

                <div class="divide-y divide-gray-200">
                    <?php if (empty($patients)): ?>
                        <div class="p-12 text-center">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-gray-500 text-lg">No patient assessments requiring attention</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($patients as $patient): ?>
                            <div class="p-6 hover:bg-gray-50 transition-colors duration-200 cursor-pointer" onclick="viewPatientAssessments(<?php echo $patient['user_id']; ?>)">
                                <div class="flex items-start justify-between gap-6">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-3">
                                            <?php if ($patient['avatar']): ?>
                                                <img src="<?php echo publicUrl($patient['avatar']); ?>" 
                                                     alt="<?php echo htmlspecialchars($patient['patient_name']); ?>"
                                                     class="w-14 h-14 rounded-full object-cover border-2 border-accent">
                                            <?php else: ?>
                                                <div class="w-14 h-14 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-bold text-lg">
                                                    <?php echo strtoupper(substr($patient['patient_name'], 0, 2)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($patient['patient_name']); ?></h3>
                                                    <?php if ($patient['unviewed_count'] > 0): ?>
                                                        <span class="px-2 py-1 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse">
                                                            <?php echo $patient['unviewed_count']; ?> NEW
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($patient['patient_email']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="grid md:grid-cols-3 gap-4 mb-4">
                                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-3">
                                                <p class="text-xs text-blue-700 font-semibold mb-1">Total Assessments</p>
                                                <p class="text-2xl font-bold text-blue-900"><?php echo $patient['total_assessments']; ?></p>
                                            </div>
                                            
                                            <div class="bg-gradient-to-br from-<?php 
                                                echo $patient['highest_severity'] === 'critical' ? 'red' : 
                                                    ($patient['highest_severity'] === 'poor' ? 'orange' : 'yellow'); 
                                            ?>-50 to-<?php 
                                                echo $patient['highest_severity'] === 'critical' ? 'red' : 
                                                    ($patient['highest_severity'] === 'poor' ? 'orange' : 'yellow'); 
                                            ?>-100 rounded-xl p-3">
                                                <p class="text-xs text-<?php 
                                                    echo $patient['highest_severity'] === 'critical' ? 'red' : 
                                                        ($patient['highest_severity'] === 'poor' ? 'orange' : 'yellow'); 
                                                ?>-700 font-semibold mb-1">Highest Severity</p>
                                                <p class="text-xl font-bold text-<?php 
                                                    echo $patient['highest_severity'] === 'critical' ? 'red' : 
                                                        ($patient['highest_severity'] === 'poor' ? 'orange' : 'yellow'); 
                                                ?>-900 capitalize"><?php echo $patient['highest_severity']; ?></p>
                                            </div>
                                            
                                            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-3">
                                                <p class="text-xs text-gray-700 font-semibold mb-1">Last Assessment</p>
                                                <p class="text-sm font-bold text-gray-900">
                                                    <?php echo date('M j, Y', strtotime($patient['last_assessment_date'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <?php if ($patient['is_connected']): ?>
                                            <div class="inline-flex items-center gap-2 px-4 py-2 bg-green-100 text-green-800 rounded-xl text-sm font-semibold">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                Connected
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex flex-col gap-2">
                                        <button onclick="event.stopPropagation(); viewPatientAssessments(<?php echo $patient['user_id']; ?>)" 
                                                class="px-6 py-3 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:shadow-lg transition-all text-center whitespace-nowrap">
                                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                            </svg>
                                            View All Assessments
                                        </button>
                                        
                                        <a href="<?php echo doctorUrl('messages.php?user_id=' . $patient['user_id']); ?>" 
                                           onclick="event.stopPropagation();"
                                           class="px-6 py-3 bg-blue-500 text-white font-semibold rounded-xl hover:bg-blue-600 transition-all text-center whitespace-nowrap">
                                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                            </svg>
                                            Message
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Create/Edit Question Modal -->
<div id="questionModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Create Assessment Question</h2>
        </div>
        
        <form id="questionForm" class="p-6 space-y-6">
            <input type="hidden" id="question_id" name="question_id">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Question Text *</label>
                <textarea id="question_text" name="question_text" rows="3" required
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-accent focus:border-transparent"
                          placeholder="Enter your assessment question here..."></textarea>
            </div>
            
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Question Type *</label>
                    <select id="question_type" name="question_type" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-accent">
                        <option value="scale">Scale (1-5)</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="text">Text Response</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
                    <select id="category" name="category" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-accent">
                        <option value="mental_health">Mental Health</option>
                        <option value="physical_health">Physical Health</option>
                        <option value="lifestyle">Lifestyle</option>
                        <option value="nutrition">Nutrition</option>
                        <option value="stress">Stress Management</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Weight (1.0 - 2.0)</label>
                <input type="number" id="weight" name="weight" step="0.1" min="1" max="2" value="1.0"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-accent">
                <p class="text-xs text-gray-500 mt-1">Higher weight means more importance in final score</p>
            </div>
            
            <div id="optionsContainer">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Answer Options (for Scale/Multiple Choice)</label>
                <div id="optionsList" class="space-y-2"></div>
                <button type="button" onclick="addOption()" 
                        class="mt-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Option
                </button>
            </div>
            
            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_active" name="is_active" value="1" checked
                       class="w-5 h-5 text-accent border-gray-300 rounded focus:ring-accent">
                <label for="is_active" class="text-sm font-semibold text-gray-700">Active (visible to patients)</label>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeQuestionModal()" 
                        class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                    Save Question
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Assessment Details Modal -->
<div id="detailsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div id="detailsContent"></div>
    </div>
</div>

<!-- View Patient Answers Modal -->
<div id="answersModal" class="fixed inset-0 z-[60] hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-primary to-accent">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-1">Patient Assessment Review</h2>
                    <p class="text-white text-opacity-90" id="patientInfoText">Review answers and provide recommendation</p>
                </div>
                <button onclick="closeAnswersModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div id="answersContent" class="p-6">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Statistics Details Modal -->
<div id="statsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 id="statsModalTitle" class="text-2xl font-bold text-gray-900">Assessment Statistics</h2>
                    <p id="statsModalSubtitle" class="text-gray-600 mt-1">Detailed breakdown</p>
                </div>
                <button onclick="closeStatsModal()" class="text-gray-500 hover:bg-gray-100 rounded-lg p-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div id="statsContent" class="p-6">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Patient Assessments Modal -->
<div id="patientAssessmentsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-primary to-accent">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div id="patientAvatarContainer"></div>
                    <div>
                        <h2 id="patientAssessmentsTitle" class="text-2xl font-bold text-white mb-1">Patient Assessments</h2>
                        <p id="patientAssessmentsSubtitle" class="text-white text-opacity-90">All assessment history</p>
                    </div>
                </div>
                <button onclick="closePatientAssessmentsModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div id="patientAssessmentsContent" class="p-6">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<style>
.tab-button {
    color: #6b7280;
}

.tab-button.active {
    background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);
    color: white;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.tab-button:hover:not(.active) {
    background-color: #f3f4f6;
}
</style>

<script>
// Configuration variables
const BASE_URL = '<?php echo BASE_URL; ?>';
const PUBLIC_URL = '<?php echo PUBLIC_URL; ?>';
const DOCTOR_URL = '<?php echo DOCTOR_URL; ?>';

let optionCount = 0;

function switchTab(tab) {
    // Update buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById('tab-' + tab).classList.add('active');
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    document.getElementById('content-' + tab).classList.remove('hidden');
}

function filterQuestions() {
    const category = document.getElementById('category-filter').value;
    const categories = document.querySelectorAll('.question-category');
    
    categories.forEach(cat => {
        if (!category || cat.dataset.category === category) {
            cat.style.display = 'block';
        } else {
            cat.style.display = 'none';
        }
    });
}

function openCreateQuestionModal() {
    document.getElementById('modalTitle').textContent = 'Create Assessment Question';
    document.getElementById('questionForm').reset();
    document.getElementById('question_id').value = '';
    document.getElementById('optionsList').innerHTML = '';
    optionCount = 0;
    
    // Add default options
    for (let i = 0; i < 5; i++) {
        addOption();
    }
    
    document.getElementById('questionModal').classList.remove('hidden');
    document.getElementById('questionModal').classList.add('flex');
}

function closeQuestionModal() {
    document.getElementById('questionModal').classList.add('hidden');
    document.getElementById('questionModal').classList.remove('flex');
}

function addOption() {
    optionCount++;
    const optionsList = document.getElementById('optionsList');
    const optionDiv = document.createElement('div');
    optionDiv.className = 'flex items-center gap-2';
    optionDiv.innerHTML = `
        <input type="text" name="options[]" 
               class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-accent"
               placeholder="Option ${optionCount}">
        <button type="button" onclick="this.parentElement.remove()" 
                class="px-3 py-2 bg-red-100 text-red-700 rounded-xl hover:bg-red-200 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    optionsList.appendChild(optionDiv);
}

function editQuestion(question) {
    document.getElementById('modalTitle').textContent = 'Edit Assessment Question';
    document.getElementById('question_id').value = question.id;
    document.getElementById('question_text').value = question.question_text;
    document.getElementById('question_type').value = question.question_type;
    document.getElementById('category').value = question.category;
    document.getElementById('weight').value = question.weight;
    document.getElementById('is_active').checked = question.is_active == 1;
    
    // Populate options
    const optionsList = document.getElementById('optionsList');
    optionsList.innerHTML = '';
    optionCount = 0;
    
    const options = JSON.parse(question.options || '[]');
    options.forEach(option => {
        addOption();
        const inputs = optionsList.querySelectorAll('input[name="options[]"]');
        inputs[inputs.length - 1].value = option;
    });
    
    document.getElementById('questionModal').classList.remove('hidden');
    document.getElementById('questionModal').classList.add('flex');
}

async function deleteQuestion(questionId) {
    if (!confirm('Are you sure you want to deactivate this question?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_question');
    formData.append('question_id', questionId);
    
    try {
        const response = await fetch('assessments.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to delete question');
    }
}

document.getElementById('questionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const questionId = document.getElementById('question_id').value;
    const action = questionId ? 'update_question' : 'create_question';
    
    const formData = new FormData(this);
    formData.append('action', action);
    formData.set('is_active', document.getElementById('is_active').checked ? 1 : 0);
    
    try {
        const response = await fetch('assessments.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to save question');
    }
});

async function connectWithPatient(patientId, assessmentId) {
    if (!confirm('Connect with this patient and send them a message?')) return;
    
    const formData = new FormData();
    formData.append('action', 'connect_with_patient');
    formData.append('patient_id', patientId);
    formData.append('assessment_id', assessmentId);
    
    try {
        const response = await fetch('assessments.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to connect with patient');
    }
}

function viewAssessmentDetails(assessment) {
    const categoryScores = JSON.parse(assessment.category_scores || '{}');
    
    let categoryHTML = '';
    for (const [category, score] of Object.entries(categoryScores)) {
        const percent = Math.min(100, Math.round(score * 20));
        categoryHTML += `
            <div class="mb-3">
                <div class="flex justify-between mb-1">
                    <span class="text-sm font-semibold capitalize">${category.replace('_', ' ')}</span>
                    <span class="text-sm font-bold">${percent}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full ${percent >= 70 ? 'bg-green-500' : (percent >= 50 ? 'bg-yellow-500' : 'bg-red-500')}" 
                         style="width: ${percent}%"></div>
                </div>
            </div>
        `;
    }
    
    document.getElementById('detailsContent').innerHTML = `
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-gray-900">Assessment Details</h2>
                <button onclick="closeDetailsModal()" class="p-2 hover:bg-gray-100 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="p-6 space-y-6">
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Patient Information</h3>
                    <p class="text-gray-700"><strong>Name:</strong> ${assessment.patient_name}</p>
                    <p class="text-gray-700"><strong>Email:</strong> ${assessment.patient_email}</p>
                    <p class="text-gray-700"><strong>Date:</strong> ${new Date(assessment.created_at).toLocaleString()}</p>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Overall Score</h3>
                    <div class="text-4xl font-bold text-gray-900 mb-2">${assessment.score}%</div>
                    <span class="px-4 py-2 inline-block text-sm font-bold rounded-full ${
                        assessment.severity === 'critical' ? 'bg-red-100 text-red-800' :
                        (assessment.severity === 'poor' ? 'bg-orange-100 text-orange-800' :
                        'bg-yellow-100 text-yellow-800')
                    }">
                        ${assessment.severity.toUpperCase()}
                    </span>
                </div>
            </div>
            
            <div>
                <h3 class="text-lg font-bold text-gray-900 mb-4">Category Breakdown</h3>
                ${categoryHTML}
            </div>
            
            <div class="flex gap-3 pt-4">
                <a href="${DOCTOR_URL}/messages.php?user_id=${assessment.user_id}" 
                   class="flex-1 px-6 py-3 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:shadow-lg transition-all text-center">
                    Message Patient
                </a>
                <button onclick="closeDetailsModal()" 
                        class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                    Close
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('detailsModal').classList.remove('hidden');
    document.getElementById('detailsModal').classList.add('flex');
}

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.add('hidden');
    document.getElementById('detailsModal').classList.remove('flex');
}

async function viewPatientAnswers(assessmentId, patientId) {
    const formData = new FormData();
    formData.append('action', 'get_assessment_answers');
    formData.append('assessment_id', assessmentId);
    
    try {
        const response = await fetch('assessments.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('Raw response:', text);
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response text:', text);
            alert('Server returned invalid JSON. Check console for details.');
            return;
        }
        
        if (result.success) {
            displayPatientAnswers(result.assessment, result.questions, result.existing_recommendation, patientId, assessmentId);
        } else {
            console.error('Server error:', result);
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Fetch error:', error);
        alert('Failed to load patient answers: ' + error.message);
    }
}

function displayPatientAnswers(assessment, questions, existingRecommendation, patientId, assessmentId) {
    // Update patient info
    document.getElementById('patientInfoText').textContent = `${assessment.patient_name} - Assessed: ${new Date(assessment.created_at).toLocaleString()}`;
    
    // Update notification badge (assessment has been viewed)
    updateNotificationBadge(assessmentId);
    
    // Build questions HTML
    let questionsHTML = '';
    questions.forEach((question, index) => {
        let answerDisplay = '';
        
        if (question.question_type === 'scale') {
            answerDisplay = `<div class="flex items-center gap-2 mt-2">
                <span class="text-lg font-bold text-accent">${question.user_answer} / 5</span>
                <div class="flex gap-1">
                    ${[1,2,3,4,5].map(i => `
                        <div class="w-8 h-8 rounded-full flex items-center justify-center ${i <= question.user_answer ? 'bg-accent text-white' : 'bg-gray-200 text-gray-400'} font-semibold">
                            ${i}
                        </div>
                    `).join('')}
                </div>
            </div>`;
        } else if (question.question_type === 'multiple_choice') {
            const selectedOption = question.options[question.user_answer] || 'N/A';
            answerDisplay = `<div class="mt-2 p-3 bg-accent bg-opacity-10 rounded-xl border-2 border-accent">
                <p class="text-gray-800 font-semibold">${selectedOption}</p>
            </div>`;
        } else if (question.question_type === 'text') {
            answerDisplay = `<div class="mt-2 p-4 bg-gray-50 rounded-xl border border-gray-200">
                <p class="text-gray-800 whitespace-pre-wrap">${question.user_answer || 'No response provided'}</p>
            </div>`;
        }
        
        questionsHTML += `
            <div class="p-4 bg-white rounded-xl border border-gray-200 hover:shadow-md transition-all">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">
                            ${index + 1}
                        </span>
                        <span class="px-3 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full capitalize">
                            ${question.category.replace('_', ' ')}
                        </span>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full capitalize">
                        ${question.question_type.replace('_', ' ')}
                    </span>
                </div>
                <h4 class="text-base font-semibold text-gray-900 mb-2">${question.question_text}</h4>
                ${answerDisplay}
            </div>
        `;
    });
    
    // Build existing recommendation display
    let existingRecHTML = '';
    if (existingRecommendation) {
        existingRecHTML = `
            <div class="p-4 bg-green-50 border-2 border-green-200 rounded-xl mb-6">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-bold text-green-800">You already provided a recommendation on ${new Date(existingRecommendation.created_at).toLocaleString()}</span>
                </div>
                <p class="text-gray-700 italic">"${existingRecommendation.recommendation_text}"</p>
                <p class="text-sm text-gray-600 mt-2">You can add a new recommendation below to update the patient.</p>
            </div>
        `;
    }
    
    // Display content
    document.getElementById('answersContent').innerHTML = `
        <!-- Assessment Summary -->
        <div class="grid md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl">
                <p class="text-sm text-blue-700 font-semibold mb-1">Overall Score</p>
                <p class="text-3xl font-bold text-blue-900">${assessment.score}%</p>
            </div>
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-xl">
                <p class="text-sm text-purple-700 font-semibold mb-1">Severity</p>
                <p class="text-2xl font-bold text-purple-900 capitalize">${assessment.severity}</p>
            </div>
            <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-4 rounded-xl">
                <p class="text-sm text-gray-700 font-semibold mb-1">Questions Answered</p>
                <p class="text-3xl font-bold text-gray-900">${questions.length}</p>
            </div>
        </div>
        
        ${existingRecHTML}
        
        <!-- Questions and Answers -->
        <div class="mb-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Patient's Responses
            </h3>
            <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                ${questionsHTML}
            </div>
        </div>
        
        <!-- Doctor Recommendation Form -->
        <div class="border-t pt-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Your Professional Recommendation
            </h3>
            
            <form id="recommendationForm" class="space-y-4">
                <input type="hidden" id="rec_assessment_id" value="${assessment.id}">
                <input type="hidden" id="rec_patient_id" value="${patientId}">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Recommendation & Advice *</label>
                    <textarea id="recommendation_text" name="recommendation_text" rows="6" required
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-accent focus:border-accent"
                              placeholder="Based on the patient's responses, provide your professional recommendation, guidance, and any specific action items they should take..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">This will be visible to the patient on their dashboard</p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Private Notes (Optional)</label>
                    <textarea id="recommendation_notes" name="notes" rows="3"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-accent focus:border-accent"
                              placeholder="Add any private notes for your records (not visible to patient)..."></textarea>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeAnswersModal()" 
                            class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="saveRecommendationBtn"
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                        <span id="saveRecIcon">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                        <svg id="saveRecSpinner" class="hidden animate-spin h-5 w-5 inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span id="saveRecText">Save & Send to Patient</span>
                        <span id="saveRecLoadingText" class="hidden">Sending...</span>
                    </button>
                </div>
            </form>
        </div>
    `;
    
    // Show modal
    document.getElementById('answersModal').classList.remove('hidden');
    document.getElementById('answersModal').classList.add('flex');
    
    // Add form submit handler
    document.getElementById('recommendationForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Add loading effect
        const submitBtn = document.getElementById('saveRecommendationBtn');
        const submitIcon = document.getElementById('saveRecIcon');
        const submitSpinner = document.getElementById('saveRecSpinner');
        const submitText = document.getElementById('saveRecText');
        const submitLoadingText = document.getElementById('saveRecLoadingText');
        
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        submitBtn.style.cursor = 'not-allowed';
        submitIcon.classList.add('hidden');
        submitSpinner.classList.remove('hidden');
        submitText.classList.add('hidden');
        submitLoadingText.classList.remove('hidden');
        
        const formData = new FormData();
        formData.append('action', 'save_doctor_recommendation');
        formData.append('assessment_id', document.getElementById('rec_assessment_id').value);
        formData.append('patient_id', document.getElementById('rec_patient_id').value);
        formData.append('recommendation_text', document.getElementById('recommendation_text').value);
        formData.append('notes', document.getElementById('recommendation_notes').value);
        
        try {
            const response = await fetch('assessments.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Recommendation saved and sent to patient!');
                closeAnswersModal();
                location.reload();
            } else {
                alert('Error: ' + result.message);
                // Re-enable button on error
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
                submitIcon.classList.remove('hidden');
                submitSpinner.classList.add('hidden');
                submitText.classList.remove('hidden');
                submitLoadingText.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save recommendation');
            // Re-enable button on error
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
            submitIcon.classList.remove('hidden');
            submitSpinner.classList.add('hidden');
            submitText.classList.remove('hidden');
            submitLoadingText.classList.add('hidden');
        }
    });
}

function closeAnswersModal() {
    document.getElementById('answersModal').classList.add('hidden');
    document.getElementById('answersModal').classList.remove('flex');
}

function updateNotificationBadge(assessmentId) {
    // Remove "NEW" badge from the specific assessment row if it exists
    const assessmentRow = document.querySelector(`button[onclick*="viewPatientAnswers(${assessmentId}"]`);
    if (assessmentRow) {
        const parentRow = assessmentRow.closest('.hover\\:bg-gray-50');
        if (parentRow) {
            const newBadge = parentRow.querySelector('.animate-pulse');
            if (newBadge) {
                newBadge.remove();
            }
        }
    }
    
    // Get current notification badge on tab
    const badge = document.querySelector('#tab-patients .rounded-full');
    if (!badge) return;
    
    // Decrease count
    let currentCount = parseInt(badge.textContent);
    if (isNaN(currentCount)) return;
    
    currentCount--;
    
    if (currentCount <= 0) {
        // Remove badge if count reaches 0
        badge.remove();
    } else {
        // Update count
        badge.textContent = currentCount;
    }
}

async function showStatDetails(severity) {
    // Switch to patient results tab
    switchTab('patients');
    
    const titles = {
        'total': 'All Assessments (Last 30 Days)',
        'critical': 'Critical Assessments',
        'poor': 'Poor Assessments',
        'fair': 'Fair Assessments',
        'good': 'Good Assessments',
        'excellent': 'Excellent Assessments'
    };
    
    const colors = {
        'total': 'gray',
        'critical': 'red',
        'poor': 'orange',
        'fair': 'yellow',
        'good': 'blue',
        'excellent': 'green'
    };
    
    document.getElementById('statsModalTitle').textContent = titles[severity];
    
    // Fetch assessments data for this severity
    try {
        const response = await fetch(`assessments.php?action=get_stats_detail&severity=${severity}`, {
            method: 'GET'
        });
        
        if (!response.ok) {
            // If the API endpoint doesn't exist yet, use client-side filtering
            filterPatientsBySeverity(severity, titles[severity], colors[severity]);
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            displayStatsDetails(result.assessments, titles[severity], colors[severity]);
        } else {
            // Fallback to client-side filtering
            filterPatientsBySeverity(severity, titles[severity], colors[severity]);
        }
    } catch (error) {
        console.log('Using client-side filtering');
        // Fallback to client-side filtering
        filterPatientsBySeverity(severity, titles[severity], colors[severity]);
    }
}

function filterPatientsBySeverity(severity, title, color) {
    // Simply scroll to patient results and show a summary message
    document.getElementById('statsModalSubtitle').textContent = `Showing ${title}`;
    
    const colorClasses = {
        'gray': { bg: 'bg-gray-100', text: 'text-gray-600', icon: 'text-gray-600' },
        'red': { bg: 'bg-red-100', text: 'text-red-600', icon: 'text-red-600' },
        'orange': { bg: 'bg-orange-100', text: 'text-orange-600', icon: 'text-orange-600' },
        'yellow': { bg: 'bg-yellow-100', text: 'text-yellow-600', icon: 'text-yellow-600' },
        'blue': { bg: 'bg-blue-100', text: 'text-blue-600', icon: 'text-blue-600' },
        'green': { bg: 'bg-green-100', text: 'text-green-600', icon: 'text-green-600' }
    };
    
    const colors = colorClasses[color] || colorClasses['gray'];
    
    const statsHTML = `
        <div class="text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full ${colors.bg} mb-4">
                <svg class="w-8 h-8 ${colors.icon}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">${title}</h3>
            <p class="text-gray-600 mb-6">View the "Patient Results" tab below to see ${severity === 'total' ? 'all' : severity} assessments</p>
            <button onclick="closeStatsModal()" class="px-6 py-3 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                View Patient Results
            </button>
        </div>
    `;
    
    document.getElementById('statsContent').innerHTML = statsHTML;
    document.getElementById('statsModal').classList.remove('hidden');
    document.getElementById('statsModal').classList.add('flex');
}

function displayStatsDetails(assessments, title, color) {
    const colorClasses = {
        'gray': { score: 'text-gray-900', badge: 'bg-gray-100 text-gray-800' },
        'red': { score: 'text-red-900', badge: 'bg-red-100 text-red-800' },
        'orange': { score: 'text-orange-900', badge: 'bg-orange-100 text-orange-800' },
        'yellow': { score: 'text-yellow-900', badge: 'bg-yellow-100 text-yellow-800' },
        'blue': { score: 'text-blue-900', badge: 'bg-blue-100 text-blue-800' },
        'green': { score: 'text-green-900', badge: 'bg-green-100 text-green-800' }
    };
    
    const colors = colorClasses[color] || colorClasses['gray'];
    
    if (!assessments || assessments.length === 0) {
        document.getElementById('statsContent').innerHTML = `
            <div class="text-center py-12">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-gray-500 text-lg">No assessments found in this category</p>
            </div>
        `;
    } else {
        let assessmentsHTML = assessments.map(assessment => `
            <div class="p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors cursor-pointer" onclick="closeStatsModal(); switchTab('patients');">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <h4 class="font-bold text-gray-900">${assessment.patient_name}</h4>
                        <p class="text-sm text-gray-600">${assessment.patient_email}</p>
                        <p class="text-xs text-gray-500 mt-1">${new Date(assessment.created_at).toLocaleString()}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold ${colors.score}">${assessment.score}%</div>
                        <span class="text-xs px-2 py-1 ${colors.badge} rounded-full font-semibold">${assessment.severity.toUpperCase()}</span>
                    </div>
                </div>
            </div>
        `).join('');
        
        document.getElementById('statsContent').innerHTML = `
            <div class="mb-4">
                <p class="text-gray-600">Found <strong>${assessments.length}</strong> assessment(s) in this category</p>
            </div>
            <div class="grid gap-3 max-h-96 overflow-y-auto">
                ${assessmentsHTML}
            </div>
            <div class="mt-6 text-center">
                <button onclick="closeStatsModal(); switchTab('patients');" class="px-6 py-3 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    View All Patient Results
                </button>
            </div>
        `;
    }
    
    document.getElementById('statsModal').classList.remove('hidden');
    document.getElementById('statsModal').classList.add('flex');
}

function closeStatsModal() {
    document.getElementById('statsModal').classList.add('hidden');
    document.getElementById('statsModal').classList.remove('flex');
}

// View all assessments for a specific patient
async function viewPatientAssessments(userId) {
    if (!userId || userId <= 0) {
        alert('Invalid patient ID');
        return;
    }
    
    try {
        const response = await fetch(`assessments.php?action=get_patient_assessments&user_id=${userId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('Response text:', text);
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response was:', text);
            alert('Server returned invalid response. Check console for details.');
            return;
        }
        
        if (result.success) {
            displayPatientAssessments(result.patient, result.assessments);
        } else {
            console.error('Server error:', result);
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load patient assessments: ' + error.message);
    }
}

function displayPatientAssessments(patient, assessments) {
    // Update modal header with patient info
    document.getElementById('patientAssessmentsTitle').textContent = `${patient.name}'s Assessment History`;
    document.getElementById('patientAssessmentsSubtitle').textContent = `${patient.email} - ${assessments.length} Total Assessment${assessments.length !== 1 ? 's' : ''}`;
    
    // Display patient avatar
    let avatarHTML = '';
    if (patient.avatar) {
        avatarHTML = `<img src="${PUBLIC_URL}/${patient.avatar}" alt="${patient.name}" class="w-16 h-16 rounded-full object-cover border-2 border-white">`;
    } else {
        avatarHTML = `<div class="w-16 h-16 rounded-full bg-white bg-opacity-20 flex items-center justify-center text-white font-bold text-2xl border-2 border-white">
            ${patient.name.substring(0, 2).toUpperCase()}
        </div>`;
    }
    document.getElementById('patientAvatarContainer').innerHTML = avatarHTML;
    
    if (!assessments || assessments.length === 0) {
        document.getElementById('patientAssessmentsContent').innerHTML = `
            <div class="text-center py-12">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-gray-500 text-lg">No assessments found for this patient</p>
            </div>
        `;
    } else {
        // Group assessments by severity
        const criticalAssessments = assessments.filter(a => a.severity === 'critical');
        const poorAssessments = assessments.filter(a => a.severity === 'poor');
        const fairAssessments = assessments.filter(a => a.severity === 'fair');
        const goodAssessments = assessments.filter(a => a.severity === 'good');
        const excellentAssessments = assessments.filter(a => a.severity === 'excellent');
        
        let assessmentsHTML = `
            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-4">
                    <p class="text-sm text-red-700 font-semibold mb-1">Critical</p>
                    <p class="text-3xl font-bold text-red-900">${criticalAssessments.length}</p>
                </div>
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4">
                    <p class="text-sm text-orange-700 font-semibold mb-1">Poor</p>
                    <p class="text-3xl font-bold text-orange-900">${poorAssessments.length}</p>
                </div>
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-4">
                    <p class="text-sm text-yellow-700 font-semibold mb-1">Fair</p>
                    <p class="text-3xl font-bold text-yellow-900">${fairAssessments.length}</p>
                </div>
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4">
                    <p class="text-sm text-blue-700 font-semibold mb-1">Good</p>
                    <p class="text-3xl font-bold text-blue-900">${goodAssessments.length}</p>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4">
                    <p class="text-sm text-green-700 font-semibold mb-1">Excellent</p>
                    <p class="text-3xl font-bold text-green-900">${excellentAssessments.length}</p>
                </div>
            </div>
            
            <!-- Assessment List -->
            <div class="space-y-4">
        `;
        
        assessments.forEach(assessment => {
            const categoryScores = JSON.parse(assessment.category_scores || '{}');
            let categoryHTML = '';
            
            for (const [category, score] of Object.entries(categoryScores)) {
                const percent = Math.min(100, Math.round(score * 20));
                categoryHTML += `
                    <div class="flex items-center gap-2 text-xs mb-1">
                        <span class="w-24 text-gray-600 capitalize">${category.replace('_', ' ')}:</span>
                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full ${percent >= 70 ? 'bg-green-500' : (percent >= 50 ? 'bg-yellow-500' : 'bg-red-500')}" 
                                 style="width: ${percent}%"></div>
                        </div>
                        <span class="w-10 text-gray-700 font-semibold">${percent}%</span>
                    </div>
                `;
            }
            
            const severityColors = {
                'critical': 'bg-red-100 text-red-800 border-red-200',
                'poor': 'bg-orange-100 text-orange-800 border-orange-200',
                'fair': 'bg-yellow-100 text-yellow-800 border-yellow-200',
                'good': 'bg-blue-100 text-blue-800 border-blue-200',
                'excellent': 'bg-green-100 text-green-800 border-green-200'
            };
            
            assessmentsHTML += `
                <div class="p-5 bg-white rounded-xl border-2 ${severityColors[assessment.severity]} hover:shadow-lg transition-all">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="px-4 py-2 text-sm font-bold rounded-full ${severityColors[assessment.severity]}">
                                    ${assessment.severity.toUpperCase()}
                                </span>
                                <span class="text-3xl font-bold text-gray-900">${assessment.score}%</span>
                                ${assessment.is_viewed == 0 ? '<span class="px-2 py-1 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse">NEW</span>' : ''}
                                ${assessment.has_recommendation > 0 ? '<span class="px-2 py-1 bg-green-500 text-white text-xs font-bold rounded-full">✓ Reviewed</span>' : ''}
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-3">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <strong>Date:</strong> ${new Date(assessment.created_at).toLocaleString()}
                            </p>
                            
                            ${categoryHTML ? `
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-gray-700 mb-2">Category Breakdown:</p>
                                    ${categoryHTML}
                                </div>
                            ` : ''}
                        </div>
                        
                        <div class="flex flex-col gap-2">
                            <button onclick="viewPatientAnswers(${assessment.id}, ${assessment.user_id})" 
                                    class="px-5 py-2 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:shadow-lg transition-all whitespace-nowrap">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        assessmentsHTML += '</div>';
        
        document.getElementById('patientAssessmentsContent').innerHTML = assessmentsHTML;
    }
    
    // Show modal
    document.getElementById('patientAssessmentsModal').classList.remove('hidden');
    document.getElementById('patientAssessmentsModal').classList.add('flex');
}

function closePatientAssessmentsModal() {
    document.getElementById('patientAssessmentsModal').classList.add('hidden');
    document.getElementById('patientAssessmentsModal').classList.remove('flex');
}

// Initialize with default options when page loads
window.addEventListener('DOMContentLoaded', function() {
    // Any initialization code here
});
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
