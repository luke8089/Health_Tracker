<?php
/**
 * Health Assessment Page
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Utils.php';
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../mail/Mailer.php';

$auth = new Auth();

// Function to generate personalized recommendations
function generateRecommendations($categoryScores, $overallScore, $severity) {
    $recommendations = [];
    
    // Convert category scores to percentages
    $categoryPercentages = [];
    foreach ($categoryScores as $category => $score) {
        $categoryPercentages[$category] = min(100, round($score * 20));
    }
    
    // Mental Health Recommendations
    if ($categoryPercentages['mental_health'] < 60) {
        $recommendations[] = [
            'title' => 'Mental Health Support',
            'details' => 'Consider speaking with a mental health professional. Practice daily mindfulness meditation for 10-15 minutes. Try journaling your thoughts and feelings.',
            'urgency' => $categoryPercentages['mental_health'] < 30 ? 'high' : 'medium'
        ];
    } else if ($categoryPercentages['mental_health'] < 80) {
        $recommendations[] = [
            'title' => 'Mental Wellness Enhancement',
            'details' => 'Continue building your mental resilience through regular exercise, social connections, and stress management techniques.',
            'urgency' => 'low'
        ];
    } else {
        $recommendations[] = [
            'title' => 'Excellent Mental Health',
            'details' => 'Your mental health is in great shape! Maintain your current practices and consider sharing your wellness strategies with others.',
            'urgency' => 'low'
        ];
    }
    
    // Physical Health Recommendations
    if ($categoryPercentages['physical_health'] < 60) {
        $recommendations[] = [
            'title' => 'Physical Fitness Plan',
            'details' => 'Start with 30 minutes of moderate exercise 3-4 times per week. Consider a health check-up with your doctor. Focus on cardiovascular health.',
            'urgency' => $categoryPercentages['physical_health'] < 30 ? 'high' : 'medium'
        ];
    } else if ($categoryPercentages['physical_health'] < 80) {
        $recommendations[] = [
            'title' => 'Fitness Optimization',
            'details' => 'Increase exercise intensity and variety. Add strength training to your routine. Monitor your progress with fitness tracking.',
            'urgency' => 'low'
        ];
    } else {
        $recommendations[] = [
            'title' => 'Peak Physical Fitness',
            'details' => 'You\'re in excellent physical shape! Keep up your exercise routine and consider setting new fitness goals or trying new activities.',
            'urgency' => 'low'
        ];
    }
    
    // Lifestyle Recommendations
    if ($categoryPercentages['lifestyle'] < 60) {
        $recommendations[] = [
            'title' => 'Lifestyle Improvement',
            'details' => 'Establish a consistent sleep schedule (7-9 hours per night). Create work-life boundaries. Schedule regular recreational activities.',
            'urgency' => $categoryPercentages['lifestyle'] < 30 ? 'high' : 'medium'
        ];
    } else if ($categoryPercentages['lifestyle'] < 80) {
        $recommendations[] = [
            'title' => 'Lifestyle Balance',
            'details' => 'Your lifestyle is well-balanced. Continue prioritizing sleep, work-life balance, and recreational activities to maintain your well-being.',
            'urgency' => 'low'
        ];
    } else {
        $recommendations[] = [
            'title' => 'Exemplary Lifestyle',
            'details' => 'You have an excellent lifestyle balance! Your habits around sleep, work, and recreation are supporting optimal health.',
            'urgency' => 'low'
        ];
    }
    
    // Nutrition Recommendations
    if ($categoryPercentages['nutrition'] < 60) {
        $recommendations[] = [
            'title' => 'Nutrition Enhancement',
            'details' => 'Increase daily fruit and vegetable intake to 5-7 servings. Reduce processed foods and increase whole grains. Stay hydrated with 8+ glasses of water daily.',
            'urgency' => $categoryPercentages['nutrition'] < 30 ? 'high' : 'medium'
        ];
    } else if ($categoryPercentages['nutrition'] < 80) {
        $recommendations[] = [
            'title' => 'Advanced Nutrition',
            'details' => 'Consider consulting a nutritionist for personalized meal planning. Explore superfoods and balanced macro-nutrients.',
            'urgency' => 'low'
        ];
    } else {
        $recommendations[] = [
            'title' => 'Excellent Nutrition',
            'details' => 'Your diet is very healthy! Continue eating a balanced diet rich in fruits, vegetables, and whole foods.',
            'urgency' => 'low'
        ];
    }
    
    // Stress Management Recommendations
    if ($categoryPercentages['stress'] < 60) {
        $recommendations[] = [
            'title' => 'Stress Management Program',
            'details' => 'Learn and practice stress-reduction techniques like deep breathing, progressive muscle relaxation, or yoga. Consider time management strategies.',
            'urgency' => $categoryPercentages['stress'] < 30 ? 'high' : 'medium'
        ];
    } else if ($categoryPercentages['stress'] < 80) {
        $recommendations[] = [
            'title' => 'Stress Management Skills',
            'details' => 'You\'re managing stress well. Continue using your stress-relief techniques and consider expanding your toolkit with new relaxation methods.',
            'urgency' => 'low'
        ];
    } else {
        $recommendations[] = [
            'title' => 'Excellent Stress Management',
            'details' => 'You have exceptional stress management skills! Your coping strategies are working very well for you.',
            'urgency' => 'low'
        ];
    }
    
    // Overall health recommendations based on score
    if ($overallScore < 50) {
        $recommendations[] = [
            'title' => 'Comprehensive Health Overhaul',
            'details' => 'Your assessment indicates multiple areas needing attention. Consider scheduling a comprehensive health consultation with a healthcare provider.',
            'urgency' => 'high'
        ];
    } else if ($overallScore >= 90) {
        $recommendations[] = [
            'title' => 'Excellent Health Maintenance',
            'details' => 'You\'re doing great! Continue your current healthy practices. Consider becoming a wellness mentor for others.',
            'urgency' => 'low'
        ];
    }
    
    return $recommendations;
}

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
if (!$currentUser) {
    // If getCurrentUser fails, log out and redirect
    session_destroy();
    header('Location: login.php');
    exit;
}

$db = new Database();
$conn = $db->connect();

// Handle assessment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    $responses = [];
    $totalScore = 0;
    $categoryScores = [
        'mental_health' => 0,
        'physical_health' => 0,
        'lifestyle' => 0,
        'nutrition' => 0,
        'stress' => 0
    ];
    
    // Get all questions
    $stmt = $conn->prepare("SELECT * FROM assessment_questions WHERE is_active = 1 ORDER BY order_index");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($questions as $question) {
        $questionId = $question['id'];
        if (isset($_POST['question_' . $questionId])) {
            $response = $_POST['question_' . $questionId];
            $responses[$questionId] = $response;
            
            // Calculate score based on response (1-5 scale)
            $score = is_numeric($response) ? intval($response) : (strlen($response) > 0 ? 3 : 1);
            $weightedScore = $score * floatval($question['weight']);
            
            $categoryScores[$question['category']] += $weightedScore;
            $totalScore += $weightedScore;
        }
    }
    
    // Calculate percentage score (ensure it doesn't exceed 100)
    $maxPossibleScore = count($questions) * 5; // Assuming 5 is max per question
    $percentageScore = min(100, round(($totalScore / $maxPossibleScore) * 100));
    
    // Determine severity
    $severity = 'fair';
    if ($percentageScore >= 90) $severity = 'excellent';
    elseif ($percentageScore >= 75) $severity = 'good';
    elseif ($percentageScore >= 50) $severity = 'fair';
    elseif ($percentageScore >= 25) $severity = 'poor';
    else $severity = 'critical';
    
    // Save assessment
    try {
        $stmt = $conn->prepare("
            INSERT INTO assessments (user_id, type, responses, score, max_score, severity, category_scores, completed_at) 
            VALUES (?, 'comprehensive', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $currentUser['id'],
            json_encode($responses),
            $percentageScore,
            100,
            $severity,
            json_encode($categoryScores)
        ]);
        
        $assessmentId = $conn->lastInsertId();
        
        // Generate personalized recommendations
        $recommendations = generateRecommendations($categoryScores, $percentageScore, $severity);
        
        // Save recommendations to database
        foreach ($recommendations as $rec) {
            $stmt = $conn->prepare("
                INSERT INTO recommendations (assessment_id, title, details, urgency) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $assessmentId,
                $rec['title'],
                $rec['details'],
                $rec['urgency']
            ]);
        }
        
        // Send email with assessment results
        try {
            $mailer = new Mailer();
            $emailResult = $mailer->sendAssessmentResultsEmail(
                [
                    'name' => $currentUser['name'],
                    'email' => $currentUser['email']
                ],
                [
                    'type' => 'Comprehensive',
                    'score' => $percentageScore,
                    'severity' => $severity,
                    'recommendations' => $recommendations,
                    'date' => date('F j, Y')
                ]
            );
            
            if (!$emailResult['success']) {
                error_log("Failed to send assessment results email: " . $emailResult['message']);
            }
        } catch (Exception $e) {
            error_log("Assessment email error: " . $e->getMessage());
        }
        
        Utils::redirect('assessment.php?results=' . $assessmentId . '&completed=1', 'Assessment completed successfully! Your health score is ' . $percentageScore . '%', 'success');
        
    } catch (Exception $e) {
        $error = "Failed to save assessment. Please try again.";
    }
}

// Get recent assessments (only 3 for preview)
$stmt = $conn->prepare("
    SELECT * FROM assessments 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute([$currentUser['id']]);
$recentAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if there are more assessments
$stmt = $conn->prepare("SELECT COUNT(*) FROM assessments WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$totalAssessments = $stmt->fetchColumn();

// Get assessment questions
$stmt = $conn->prepare("SELECT * FROM assessment_questions WHERE is_active = 1 ORDER BY order_index");
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available doctors for referrals
$stmt = $conn->query("
    SELECT u.id, u.name, u.email, u.avatar, d.specialty as specialization, d.license_number as qualification 
    FROM users u 
    JOIN doctors d ON u.id = d.id 
    WHERE u.role = 'doctor' 
    ORDER BY u.name
    LIMIT 20
");
$availableDoctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Show results if requested
$showResults = false;
$assessmentResult = null;
$recommendations = [];
$doctorRecommendations = [];
if (isset($_GET['results']) && is_numeric($_GET['results'])) {
    $stmt = $conn->prepare("SELECT * FROM assessments WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['results'], $currentUser['id']]);
    $assessmentResult = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($assessmentResult) {
        $showResults = true;
        
        // Get recommendations for this assessment
        $stmt = $conn->prepare("SELECT * FROM recommendations WHERE assessment_id = ? ORDER BY urgency DESC, created_at ASC");
        $stmt->execute([$assessmentResult['id']]);
        $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate basic AI recommendations if none exist
        if (empty($recommendations)) {
            $score = $assessmentResult['score'];
            $severity = $assessmentResult['severity'];
            
            // Generate general recommendations based on score
            if ($score < 50) {
                $recommendations[] = [
                    'title' => 'Health Improvement Plan',
                    'details' => 'Your assessment indicates several areas that need attention. We recommend consulting with a healthcare professional to create a personalized health improvement plan. Focus on gradual lifestyle changes and seek professional guidance.',
                    'urgency' => 'high'
                ];
                $recommendations[] = [
                    'title' => 'Professional Consultation',
                    'details' => 'Schedule an appointment with your primary care physician for a comprehensive health evaluation. They can provide specific recommendations based on your individual needs and medical history.',
                    'urgency' => 'high'
                ];
            } elseif ($score < 75) {
                $recommendations[] = [
                    'title' => 'Health Optimization',
                    'details' => 'You\'re doing reasonably well, but there\'s room for improvement. Consider focusing on regular exercise (30 minutes daily), balanced nutrition, and stress management techniques like meditation or yoga.',
                    'urgency' => 'medium'
                ];
                $recommendations[] = [
                    'title' => 'Lifestyle Enhancement',
                    'details' => 'Establish consistent healthy habits: maintain a regular sleep schedule (7-9 hours), stay hydrated, and engage in recreational activities. Small consistent changes lead to significant improvements.',
                    'urgency' => 'medium'
                ];
            } else {
                $recommendations[] = [
                    'title' => 'Excellent Health Maintenance',
                    'details' => 'Your health status is great! Continue your current healthy practices. Maintain your exercise routine, balanced diet, and stress management techniques. Consider setting new wellness goals to challenge yourself.',
                    'urgency' => 'low'
                ];
                $recommendations[] = [
                    'title' => 'Preventive Care',
                    'details' => 'Keep up with regular health checkups and screenings. Stay informed about wellness best practices and consider becoming a wellness advocate in your community.',
                    'urgency' => 'low'
                ];
            }
            
            // Add general wellness recommendation
            $recommendations[] = [
                'title' => 'Awaiting Professional Review',
                'details' => 'These are general AI-generated recommendations. A healthcare professional will review your assessment soon and provide personalized medical advice tailored to your specific situation. Check back regularly for updates.',
                'urgency' => 'medium'
            ];
        }
        
        // Get doctor recommendations for this assessment
        $stmt = $conn->prepare("
            SELECT dr.*, u.name as doctor_name, u.avatar as doctor_avatar, 
                   d.specialty, d.license_number
            FROM doctor_recommendations dr
            JOIN users u ON dr.doctor_id = u.id
            JOIN doctors d ON dr.doctor_id = d.id
            WHERE dr.assessment_id = ?
            ORDER BY dr.created_at DESC
        ");
        $stmt->execute([$assessmentResult['id']]);
        $doctorRecommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$title = "Health Assessment - Health Tracker";
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-100">
    <!-- Success Modal (shown when assessment is just completed) -->
    <?php if (isset($_GET['results']) && isset($_GET['completed'])): ?>
        <div id="success-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-100">
            <div class="bg-white rounded-3xl shadow-2xl max-w-lg mx-4 overflow-hidden transform scale-100 transition-all duration-500">
                <div class="px-8 py-12 text-center" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                    <div class="w-20 h-20 mx-auto mb-6 rounded-full flex items-center justify-center animate-bounce" style="background: rgba(255,255,255,0.2);">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-white mb-4">Assessment Saved Successfully!</h2>
                    <p class="text-white opacity-90 text-lg">Your health assessment has been completed and saved securely.</p>
                </div>
                
                <div class="p-8 text-center">
                    <p class="text-gray-600 mb-6">What would you like to do next?</p>
                    <div class="space-y-3">
                        <button onclick="closeModalAndShowResults()" class="w-full py-4 px-6 rounded-xl font-semibold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-lg" 
                                style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"/>
                            </svg>
                            View Detailed Results
                        </button>
                        <button onclick="closeModalAndShowResults(); setTimeout(toggleRecommendations, 500);" class="w-full py-4 px-6 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 hover:shadow-lg border-2" 
                                style="border-color: #34d399; color: #1f2937; background: white;">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            See Recommendations
                        </button>
                        <button onclick="window.location.href='dashboard.php'" class="w-full py-3 px-6 text-gray-600 hover:text-gray-800 transition-colors duration-300">
                            Return to Dashboard
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="max-w-4xl mx-auto px-4 py-8">
        
        <?php if ($showResults && $assessmentResult): ?>
            <!-- Assessment Results -->
            <div class="mb-8 transform transition-all duration-700 animate-fade-in">
                <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
                    <div class="px-8 py-12" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                        <div class="text-center text-white">
                            <div class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center" style="background: rgba(255,255,255,0.2);">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h1 class="text-4xl font-bold mb-4">Assessment Complete!</h1>
                            <div class="text-6xl font-bold mb-2"><?php echo $assessmentResult['score']; ?>%</div>
                            <div class="text-xl opacity-90 capitalize"><?php echo str_replace('_', ' ', $assessmentResult['severity']); ?> Health Status</div>
                        </div>
                    </div>
                    
                    <div class="p-8">
                        <div class="grid md:grid-cols-2 gap-6 mb-8">
                            <?php 
                            $categoryScores = json_decode($assessmentResult['category_scores'], true);
                            $categoryLabels = [
                                'mental_health' => 'Mental Health',
                                'physical_health' => 'Physical Health', 
                                'lifestyle' => 'Lifestyle',
                                'nutrition' => 'Nutrition',
                                'stress' => 'Stress Management'
                            ];
                            
                            if (is_array($categoryScores) && !empty($categoryScores)):
                                foreach ($categoryScores as $category => $score): 
                                $categoryPercent = min(100, round($score * 20)); // Convert to percentage
                            ?>
                                <div class="bg-gray-50 rounded-2xl p-6 transform transition-all duration-500 hover:scale-105 hover:shadow-lg">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="font-semibold" style="color: #1f2937;"><?php echo $categoryLabels[$category] ?? ucfirst($category); ?></h3>
                                        <span class="text-2xl font-bold" style="color: #34d399;"><?php echo $categoryPercent; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="h-3 rounded-full transition-all duration-1000" style="background: linear-gradient(90deg, #1f2937 0%, #34d399 100%); width: <?php echo $categoryPercent; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-2">
                                    <?php if (!empty($doctorRecommendations)): ?>
                                        <!-- Doctor Recommendations Section -->
                                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-8 border-2 border-blue-200">
                                            <div class="flex items-center mb-6">
                                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white mr-4" 
                                                     style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h3 class="text-2xl font-bold text-gray-900">Professional Assessment Review</h3>
                                                    <p class="text-gray-600">Your assessment has been reviewed by a healthcare professional</p>
                                                </div>
                                            </div>
                                            
                                            <?php foreach ($doctorRecommendations as $docRec): ?>
                                                <div class="bg-white rounded-xl p-6 shadow-md mb-4 last:mb-0">
                                                    <!-- Doctor Info -->
                                                    <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                                                        <div class="w-14 h-14 rounded-full flex items-center justify-center text-white font-bold text-lg overflow-hidden mr-4"
                                                             style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                                                            <?php if (!empty($docRec['doctor_avatar'])): ?>
                                                                <img src="<?php echo htmlspecialchars($docRec['doctor_avatar']); ?>" 
                                                                     alt="<?php echo htmlspecialchars($docRec['doctor_name']); ?>" 
                                                                     class="w-full h-full object-cover">
                                                            <?php else: ?>
                                                                <?php echo strtoupper(substr($docRec['doctor_name'], 0, 2)); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-1">
                                                            <h4 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($docRec['doctor_name']); ?></h4>
                                                            <p class="text-sm text-gray-600 capitalize">
                                                                <span class="inline-flex items-center">
                                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                                    </svg>
                                                                    <?php echo htmlspecialchars($docRec['specialty']); ?>
                                                                </span>
                                                                <span class="mx-2">•</span>
                                                                <span class="text-xs">License: <?php echo htmlspecialchars($docRec['license_number']); ?></span>
                                                            </p>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo date('M j, Y', strtotime($docRec['created_at'])); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Recommendation Text -->
                                                    <div class="mb-4">
                                                        <h5 class="font-semibold text-gray-900 mb-2 flex items-center">
                                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                            </svg>
                                                            Doctor's Recommendation:
                                                        </h5>
                                                        <div class="bg-gray-50 rounded-lg p-4">
                                                            <p class="text-gray-700 leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($docRec['recommendation_text']); ?></p>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Status Badge -->
                                                    <div class="flex items-center justify-between">
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold <?php echo $docRec['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                            <?php echo ucfirst($docRec['status']); ?>
                                                        </span>
                                                        <a href="messages.php?doctor=<?php echo $docRec['doctor_id']; ?>" 
                                                           class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white transition-all duration-300 hover:shadow-lg"
                                                           style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                                            </svg>
                                                            Contact Doctor
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- No Category Scores and No Doctor Recommendations -->
                                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-8 text-center border-2 border-gray-200">
                                            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900 mb-2">Assessment Recorded</h3>
                                            <p class="text-gray-600 mb-6">Your assessment has been saved successfully. While detailed category scores are not available for this assessment type, you can still view your overall score and general AI recommendations below. Use the "View Recommendations" button at the bottom to see personalized health guidance.</p>
                                            <div class="flex justify-center">
                                                <button onclick="showDoctorModal()" 
                                                        class="inline-flex items-center px-8 py-4 rounded-xl font-bold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-xl shadow-lg"
                                                        style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                    </svg>
                                                    Get Professional Consultation
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-4">
                            <button onclick="window.print()" class="flex-1 py-4 px-6 rounded-xl font-semibold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-lg" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Print Results
                            </button>
                            <button onclick="toggleRecommendations()" class="flex-1 py-4 px-6 rounded-xl font-semibold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-lg" style="background: linear-gradient(135deg, #34d399 0%, #1f2937 100%);">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                                View Recommendations
                            </button>
                            <a href="assessment.php" class="flex-1 py-4 px-6 bg-white border-2 rounded-xl font-semibold text-center transition-all duration-300 transform hover:scale-105 hover:shadow-lg" style="border-color: #34d399; color: #1f2937;">
                                Take New Assessment
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recommendations Section (Initially Hidden) -->
            <div id="recommendations-section" class="mb-8 transform transition-all duration-700 opacity-0 overflow-hidden" style="max-height: 0px;">
                <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
                    <div class="px-8 py-6" style="background: linear-gradient(135deg, #34d399 0%, #1f2937 100%);">
                        <div class="flex items-center text-white">
                            <svg class="w-8 h-8 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            <h2 class="text-3xl font-bold">Health Recommendations</h2>
                        </div>
                    </div>
                        
                        <div class="p-8">
                            <div class="grid gap-6">
                                <?php foreach ($recommendations as $index => $rec): ?>
                                    <div class="bg-gradient-to-br from-gray-50 to-white rounded-2xl p-6 shadow-lg transform transition-all duration-500 hover:scale-105 hover:shadow-xl border-l-4 <?php echo $rec['urgency'] === 'high' ? 'border-red-500' : ($rec['urgency'] === 'medium' ? 'border-yellow-500' : 'border-green-500'); ?>"
                                         style="animation: slide-up 0.6s ease-out <?php echo $index * 0.1; ?>s both;">
                                        
                                        <div class="flex items-start space-x-4">
                                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 text-white" 
                                                 style="background: <?php echo $rec['urgency'] === 'high' ? 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)' : ($rec['urgency'] === 'medium' ? 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)' : 'linear-gradient(135deg, #10b981 0%, #059669 100%)'); ?>">
                                                <?php if ($rec['urgency'] === 'high'): ?>
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                                    </svg>
                                                <?php elseif ($rec['urgency'] === 'medium'): ?>
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between mb-3">
                                                    <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($rec['title']); ?></h3>
                                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold text-white <?php echo $rec['urgency'] === 'high' ? 'bg-red-500' : ($rec['urgency'] === 'medium' ? 'bg-yellow-500' : 'bg-green-500'); ?>">
                                                        <?php echo strtoupper($rec['urgency']); ?> PRIORITY
                                                    </span>
                                                </div>
                                                <p class="text-gray-700 leading-relaxed"><?php echo htmlspecialchars($rec['details']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-8 text-center">
                                <?php if ($assessmentResult['severity'] === 'poor' || $assessmentResult['severity'] === 'critical'): ?>
                                    <div class="bg-red-50 border-2 border-red-200 rounded-2xl p-6 mb-6">
                                        <div class="flex items-center justify-center mb-4">
                                            <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                            </svg>
                                        </div>
                                        <h3 class="text-xl font-bold text-red-900 mb-2">
                                            <?php echo $assessmentResult['severity'] === 'critical' ? 'Urgent Medical Attention Required' : 'Professional Consultation Recommended'; ?>
                                        </h3>
                                        <p class="text-red-700 mb-4">
                                            Your assessment results indicate that you should consult with a healthcare professional. We recommend reaching out to a doctor for personalized guidance and support.
                                        </p>
                                        <button onclick="showDoctorModal()" class="inline-flex items-center px-8 py-4 rounded-xl font-bold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-xl shadow-lg"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            Contact a Doctor Now
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <p class="text-gray-600 mb-4">Need personalized guidance? Connect with a healthcare professional.</p>
                                    <button onclick="showDoctorModal()" class="inline-flex items-center px-6 py-3 rounded-xl font-semibold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                                            style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                        </svg>
                                        Consult a Doctor
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            
            <!-- Assessment Header -->
            <div class="text-center mb-12 transform transition-all duration-700 animate-fade-in">
                <div class="w-20 h-20 mx-auto mb-6 rounded-2xl flex items-center justify-center transform transition-transform duration-500 hover:scale-110" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <h1 class="text-4xl font-bold mb-4" style="color: #1f2937;">Health Assessment</h1>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Take our comprehensive health assessment to get personalized insights into your well-being across multiple dimensions of health.
                </p>
            </div>

            <!-- Progress indicator -->
            <div class="mb-8">
                <div class="flex items-center justify-between text-sm text-gray-500 mb-2">
                    <span>Progress</span>
                    <span><span id="current-question">0</span> of <?php echo count($questions); ?> questions</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progress-bar" class="h-2 rounded-full transition-all duration-500" style="background: linear-gradient(90deg, #1f2937 0%, #34d399 100%); width: 0%"></div>
                </div>
            </div>

            <!-- Assessment Form -->
            <form method="POST" class="space-y-8" id="assessment-form">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="assessment-question bg-white rounded-3xl shadow-xl p-8 transform transition-all duration-700 opacity-0 translate-y-8 hover:shadow-2xl" 
                         data-question="<?php echo $index + 1; ?>" 
                         style="<?php echo $index === 0 ? 'opacity: 1; transform: translateY(0);' : ''; ?>">
                        
                        <div class="flex items-start space-x-4 mb-6">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white font-bold text-lg flex-shrink-0" 
                                 style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="flex-1">
                                <div class="inline-block px-3 py-1 rounded-full text-xs font-medium mb-3" 
                                     style="background-color: #34d399; color: #1f2937;">
                                    <?php echo ucfirst(str_replace('_', ' ', $question['category'])); ?>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900 leading-relaxed">
                                    <?php echo htmlspecialchars($question['question_text']); ?>
                                </h3>
                            </div>
                        </div>

                        <div class="ml-16">
                            <?php 
                            $options = json_decode($question['options'], true);
                            if ($question['question_type'] === 'scale'): ?>
                                <div class="space-y-6">
                                    <!-- Scale Labels -->
                                    <div class="flex justify-between items-center px-2">
                                        <span class="text-sm font-medium text-gray-600">Strongly Disagree</span>
                                        <span class="text-sm font-medium text-gray-600">Strongly Agree</span>
                                    </div>
                                    
                                    <!-- Scale Options (1-5) -->
                                    <div class="flex justify-center gap-4">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <label class="flex flex-col items-center cursor-pointer group scale-option">
                                                <input type="radio" 
                                                       name="question_<?php echo $question['id']; ?>" 
                                                       value="<?php echo $i; ?>" 
                                                       class="sr-only scale-radio"
                                                       required>
                                                <div class="w-14 h-14 rounded-full border-3 border-gray-300 flex items-center justify-center text-lg font-bold text-gray-400 transition-all duration-300 group-hover:border-green-400 group-hover:scale-110 scale-circle">
                                                    <?php echo $i; ?>
                                                </div>
                                                <span class="mt-2 text-xs text-gray-500 font-medium"><?php echo $i; ?></span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <p class="text-sm text-gray-500 text-center flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                        Select a number from 1 (lowest) to 5 (highest)
                                    </p>
                                </div>
                            <?php elseif ($question['question_type'] === 'multiple_choice'): ?>
                                <div class="grid gap-3">
                                    <?php foreach ($options as $optIndex => $option): ?>
                                        <label class="flex items-center p-4 rounded-2xl cursor-pointer transition-all duration-300 hover:shadow-md group border-2 border-transparent hover:border-gray-200 radio-option">
                                            <input type="radio" 
                                                   name="question_<?php echo $question['id']; ?>" 
                                                   value="<?php echo $optIndex + 1; ?>" 
                                                   class="sr-only peer"
                                                   required>
                                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 mr-4 flex-shrink-0 transition-all duration-300 relative">
                                                <div class="radio-dot w-2 h-2 rounded-full bg-white absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 opacity-0 transition-opacity duration-300"></div>
                                            </div>
                                            <span class="text-gray-700 group-hover:text-gray-900 font-medium"><?php echo htmlspecialchars($option); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($question['question_type'] === 'text'): ?>
                                <div>
                                    <textarea 
                                        name="question_<?php echo $question['id']; ?>" 
                                        rows="5" 
                                        class="w-full px-6 py-4 border-2 border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-accent focus:border-green-400 resize-none transition-all duration-300 hover:border-green-400/50"
                                        placeholder="Type your response here..."
                                        required
                                    ></textarea>
                                    <p class="text-sm text-gray-500 mt-2 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Please provide a detailed response to help us better understand your situation
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Navigation buttons -->
                <div class="flex justify-between items-center pt-8" id="navigation">
                    <button type="button" 
                            id="prev-btn" 
                            class="px-8 py-4 rounded-2xl font-semibold transition-all duration-300 transform hover:scale-105 opacity-50 cursor-not-allowed"
                            style="background-color: #f3f4f6; color: #6b7280;"
                            disabled>
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Previous
                    </button>
                    
                    <div class="text-center">
                        <span class="text-sm text-gray-500">Question <span id="question-counter">1</span> of <?php echo count($questions); ?></span>
                    </div>
                    
                    <button type="button" 
                            id="next-btn" 
                            class="px-8 py-4 rounded-2xl font-semibold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                            style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                        Next
                        <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    
                    <button type="submit" 
                            name="submit_assessment"
                            id="submit-btn" 
                            class="px-8 py-4 rounded-2xl font-semibold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-lg hidden"
                            style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                        <span id="submit-icon">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <svg id="submit-spinner" class="hidden animate-spin h-5 w-5 inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span id="submit-text">Complete Assessment</span>
                        <span id="submit-loading-text" class="hidden">Processing...</span>
                    </button>
                </div>
            </form>

        <?php endif; ?>

        <script>
        // Add loading effect to submit button
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitBtn = document.getElementById('submit-btn');
                    const submitIcon = document.getElementById('submit-icon');
                    const submitSpinner = document.getElementById('submit-spinner');
                    const submitText = document.getElementById('submit-text');
                    const submitLoadingText = document.getElementById('submit-loading-text');
                    
                    if (submitBtn && submitIcon && submitSpinner && submitText && submitLoadingText) {
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.7';
                        submitBtn.style.cursor = 'not-allowed';
                        submitBtn.style.transform = 'none';
                        
                        submitIcon.classList.add('hidden');
                        submitSpinner.classList.remove('hidden');
                        submitText.classList.add('hidden');
                        submitLoadingText.classList.remove('hidden');
                    }
                });
            }
        });
        </script>

        <!-- Recent Assessments -->
        <?php if (!empty($recentAssessments) && !$showResults): ?>
            <div class="mt-16 transform transition-all duration-700 animate-fade-in" style="animation-delay: 0.5s;">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold" style="color: #1f2937;">Recent Assessments</h2>
                    <?php if ($totalAssessments > 3): ?>
                        <a href="assessment_history.php" 
                           class="flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-all duration-300 hover:shadow-md"
                           style="background-color: #34d399; color: #1f2937;">
                            View All (<?php echo $totalAssessments; ?>)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="grid gap-4">
                    <?php foreach ($recentAssessments as $assessment): 
                        // Determine assessment type from responses
                        $responses = json_decode($assessment['responses'], true);
                        $assessmentType = 'comprehensive'; // default
                        $typeIcon = '📋';
                        $typeColor = '#1f2937';
                        $typeGradient = 'linear-gradient(135deg, #1f2937 0%, #34d399 100%)';
                        
                        if (is_array($responses) && isset($responses['type'])) {
                            $assessmentType = $responses['type'];
                            if ($assessmentType === 'physical') {
                                $typeIcon = '💪';
                                $typeColor = '#3b82f6'; // blue
                                $typeGradient = 'linear-gradient(135deg, #3b82f6 0%, #1e40af 100%)';
                            } elseif ($assessmentType === 'mental') {
                                $typeIcon = '🧠';
                                $typeColor = '#8b5cf6'; // purple
                                $typeGradient = 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)';
                            }
                        }
                    ?>
                        <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4 flex-1">
                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold" 
                                         style="background: <?php echo $typeGradient; ?>;">
                                        <?php echo $assessment['score']; ?>%
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="font-semibold text-gray-900 capitalize">
                                                <?php echo str_replace('_', ' ', $assessment['severity']); ?> Health Status
                                            </h3>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold text-white"
                                                  style="background: <?php echo $typeGradient; ?>;">
                                                <span class="mr-1"><?php echo $typeIcon; ?></span>
                                                <?php echo ucfirst($assessmentType); ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-500 text-sm">
                                            <?php echo date('M j, Y', strtotime($assessment['completed_at'] ?? $assessment['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="assessment.php?results=<?php echo $assessment['id']; ?>" 
                                   class="px-4 py-2 rounded-xl font-medium transition-all duration-300 hover:shadow-md"
                                   style="background-color: #34d399; color: #1f2937;">
                                    View Results
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalAssessments > 3): ?>
                    <div class="mt-6 text-center">
                        <a href="assessment_history.php" 
                           class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                           style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            View All Assessments
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Doctor Selection Modal -->
    <div id="doctorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4" style="display: none;">
        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden transform transition-all duration-300 scale-95 opacity-0" id="doctorModalContent">
            <div class="px-8 py-6" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                <div class="flex items-center justify-between text-white">
                    <div class="flex items-center">
                        <svg class="w-8 h-8 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <h2 class="text-2xl font-bold">Select a Doctor to Contact</h2>
                    </div>
                    <button onclick="closeDoctorModal()" class="text-white hover:text-gray-200 transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-8 overflow-y-auto" style="max-height: calc(90vh - 100px);">
                <p class="text-gray-600 mb-6">Choose a healthcare professional to discuss your assessment results and get personalized recommendations.</p>
                
                <div class="grid gap-4" id="doctorsList">
                    <?php if (!empty($availableDoctors)): ?>
                        <?php foreach ($availableDoctors as $doctor): ?>
                            <div class="bg-gradient-to-br from-gray-50 to-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-[1.02] border-2 border-transparent hover:border-green-400 cursor-pointer"
                                 onclick="selectDoctor(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['name'], ENT_QUOTES); ?>')">
                                <div class="flex items-center space-x-4">
                                    <div class="w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-xl flex-shrink-0 overflow-hidden"
                                         style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                                        <?php if (!empty($doctor['avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($doctor['avatar']); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($doctor['name'], 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($doctor['name']); ?></h3>
                                        <p class="text-gray-600 capitalize">
                                            <span class="inline-flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                                <?php echo htmlspecialchars($doctor['specialization']); ?>
                                            </span>
                                        </p>
                                        <p class="text-sm text-gray-500">License: <?php echo htmlspecialchars($doctor['qualification']); ?></p>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6" style="color: #34d399;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <p class="text-gray-600">No doctors available at the moment. Please try again later.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const questions = document.querySelectorAll('.assessment-question');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    const progressBar = document.getElementById('progress-bar');
    const questionCounter = document.getElementById('question-counter');
    const currentQuestionSpan = document.getElementById('current-question');
    
    let currentQuestion = 0;
    const totalQuestions = questions.length;
    
    function updateProgress() {
        const progress = ((currentQuestion + 1) / totalQuestions) * 100;
        progressBar.style.width = progress + '%';
        questionCounter.textContent = currentQuestion + 1;
        currentQuestionSpan.textContent = currentQuestion + 1;
        
        // Update button states
        prevBtn.disabled = currentQuestion === 0;
        prevBtn.style.opacity = currentQuestion === 0 ? '0.5' : '1';
        prevBtn.style.cursor = currentQuestion === 0 ? 'not-allowed' : 'pointer';
        
        if (currentQuestion === totalQuestions - 1) {
            nextBtn.classList.add('hidden');
            submitBtn.classList.remove('hidden');
        } else {
            nextBtn.classList.remove('hidden');
            submitBtn.classList.add('hidden');
        }
    }
    
    function showQuestion(index) {
        questions.forEach((question, i) => {
            if (i === index) {
                question.style.opacity = '1';
                question.style.transform = 'translateY(0)';
                question.style.display = 'block';
                
                // Animate in
                setTimeout(() => {
                    question.classList.add('animate-slide-in');
                }, 50);
            } else {
                question.style.opacity = '0';
                question.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    question.style.display = 'none';
                }, 300);
            }
        });
        
        // Smooth scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
        updateProgress();
    }
    
    nextBtn.addEventListener('click', function() {
        // Check if current question is answered
        const currentQuestionEl = questions[currentQuestion];
        const radioInputs = currentQuestionEl.querySelectorAll('input[type="radio"]');
        const textareaInputs = currentQuestionEl.querySelectorAll('textarea');
        
        let isAnswered = false;
        
        // Check for radio button answers
        if (radioInputs.length > 0) {
            isAnswered = Array.from(radioInputs).some(input => input.checked);
        }
        
        // Check for textarea answers
        if (textareaInputs.length > 0) {
            isAnswered = Array.from(textareaInputs).some(input => input.value.trim().length > 0);
        }
        
        if (!isAnswered) {
            // Highlight the question
            currentQuestionEl.style.borderColor = '#ef4444';
            currentQuestionEl.style.borderWidth = '2px';
            
            // Show error message
            showNotification('error', 'Please answer this question', 'You need to provide an answer before proceeding.');
            
            // Remove highlight after 3 seconds
            setTimeout(() => {
                currentQuestionEl.style.borderColor = 'transparent';
            }, 3000);
            return;
        }
        
        if (currentQuestion < totalQuestions - 1) {
            currentQuestion++;
            showQuestion(currentQuestion);
        }
    });
    
    prevBtn.addEventListener('click', function() {
        if (currentQuestion > 0) {
            currentQuestion--;
            showQuestion(currentQuestion);
        }
    });
    
    // Add smooth radio button animations
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const label = this.closest('label');
            const allLabels = this.closest('.space-y-3, .grid').querySelectorAll('label');
            
            // Remove selection from all options
            allLabels.forEach(l => {
                l.style.backgroundColor = 'transparent';
                l.style.borderColor = 'transparent';
                l.style.transform = 'scale(1)';
            });
            
            // Highlight selected option
            if (this.checked) {
                label.style.backgroundColor = 'rgba(161, 209, 177, 0.1)';
                label.style.borderColor = '#34d399';
                label.style.transform = 'scale(1.02)';
                
                // Add ripple effect
                const ripple = document.createElement('div');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(161, 209, 177, 0.3)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.left = '50%';
                ripple.style.top = '50%';
                ripple.style.width = '100px';
                ripple.style.height = '100px';
                ripple.style.marginLeft = '-50px';
                ripple.style.marginTop = '-50px';
                
                label.style.position = 'relative';
                label.style.overflow = 'hidden';
                label.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            }
        });
    });
    
    // Add scale radio button interactions
    document.querySelectorAll('.scale-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            // Add ripple effect
            const circle = this.nextElementSibling;
            if (circle) {
                circle.style.animation = 'none';
                setTimeout(() => {
                    circle.style.animation = 'scale-pulse 0.5s ease-out';
                }, 10);
            }
            
            // Reset all siblings
            const parent = this.closest('.scale-option').parentElement;
            parent.querySelectorAll('.scale-circle').forEach(c => {
                if (c !== circle) {
                    c.style.transform = 'scale(1)';
                }
            });
        });
    });
    
    // Add textarea interactions
    document.querySelectorAll('textarea').forEach(textarea => {
        // Add character counter
        const maxLength = 500;
        const counterDiv = document.createElement('div');
        counterDiv.className = 'text-xs text-gray-500 mt-2 text-right';
        counterDiv.innerHTML = `<span class="font-semibold" id="char-count-${textarea.name}">0</span> / ${maxLength} characters`;
        textarea.setAttribute('maxlength', maxLength);
        textarea.parentElement.appendChild(counterDiv);
        
        // Update character count and styling
        textarea.addEventListener('input', function() {
            const charCount = this.value.length;
            const counter = document.getElementById(`char-count-${this.name}`);
            if (counter) {
                counter.textContent = charCount;
                counter.classList.toggle('text-green-400', charCount > 0);
                counter.classList.toggle('text-orange-500', charCount > maxLength * 0.8);
                counter.classList.toggle('font-bold', charCount > maxLength * 0.9);
            }
            
            // Add visual feedback when typing
            if (charCount > 0) {
                this.style.borderColor = '#34d399';
                this.style.backgroundColor = 'rgba(161, 209, 177, 0.05)';
            } else {
                this.style.borderColor = '#e5e7eb';
                this.style.backgroundColor = 'transparent';
            }
        });
        
        // Focus effect
        textarea.addEventListener('focus', function() {
            this.style.borderColor = '#34d399';
            this.style.boxShadow = '0 0 0 3px rgba(161, 209, 177, 0.1)';
        });
        
        textarea.addEventListener('blur', function() {
            if (this.value.length === 0) {
                this.style.borderColor = '#e5e7eb';
            }
            this.style.boxShadow = 'none';
        });
    });
    
    // Initialize
    showQuestion(0);
    
    // Add notification function
    function showNotification(type, title, message) {
        // You can integrate with your existing notification system here
        alert(title + ': ' + message);
    }
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes fade-in {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slide-in {
        from {
            transform: translateX(-20px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slide-up {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes scale-pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.2);
        }
        100% {
            transform: scale(1.15);
        }
    }
    
    .animate-fade-in {
        animation: fade-in 0.7s ease-out forwards;
    }
    
    .animate-slide-in {
        animation: slide-in 0.5s ease-out forwards;
    }
    
    .assessment-question {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Radio button styles */
    .radio-option input[type="radio"]:checked + .radio-circle {
        border-color: #34d399;
        border-width: 4px;
        background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);
    }
    
    .radio-option input[type="radio"]:checked + .radio-circle .radio-dot {
        opacity: 1;
        background-color: white;
    }
    
    /* Scale option styles */
    .scale-option input[type="radio"]:checked + .scale-circle {
        border-color: #34d399;
        border-width: 4px;
        background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(161, 209, 177, 0.4);
        transform: scale(1.15);
    }
    
    .scale-option:hover .scale-circle {
        border-color: #34d399;
        box-shadow: 0 2px 8px rgba(161, 209, 177, 0.3);
    }
    
    .scale-circle {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Print styles */
    @media print {
        body { font-size: 12pt; }
        .no-print { display: none; }
        .bg-gradient-to-br { background: white !important; }
        .text-white { color: black !important; }
    }
`;
document.head.appendChild(style);

// Modal and recommendations functions
function closeModalAndShowResults() {
    const modal = document.getElementById('success-modal');
    if (modal) {
        modal.style.opacity = '0';
        modal.style.pointerEvents = 'none';
        setTimeout(() => {
            modal.style.display = 'none';
            // Update URL to remove completed parameter
            const url = new URL(window.location.href);
            url.searchParams.delete('completed');
            window.history.replaceState({}, '', url.toString());
        }, 500);
    }
}

function toggleRecommendations() {
    const section = document.getElementById('recommendations-section');
    if (section) {
        // Check if currently hidden by checking classes
        const isHidden = section.classList.contains('opacity-0');
        
        if (isHidden) {
            // Show recommendations
            section.classList.remove('opacity-0', 'max-h-0');
            section.classList.add('opacity-100');
            section.style.maxHeight = section.scrollHeight + 'px';
            
            // Smooth scroll to the section
            setTimeout(() => {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        } else {
            // Hide recommendations
            section.classList.remove('opacity-100');
            section.classList.add('opacity-0');
            section.style.maxHeight = '0px';
            
            // After transition, add max-h-0 class
            setTimeout(() => {
                section.classList.add('max-h-0');
            }, 700); // Match the duration-700 class
        }
    }
}

// Doctor Modal Functions
function showDoctorModal() {
    const modal = document.getElementById('doctorModal');
    const modalContent = document.getElementById('doctorModalContent');
    
    if (modal && modalContent) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        
        // Trigger animation
        setTimeout(() => {
            modalContent.style.transform = 'scale(1)';
            modalContent.style.opacity = '1';
        }, 10);
    }
}

function closeDoctorModal() {
    const modal = document.getElementById('doctorModal');
    const modalContent = document.getElementById('doctorModalContent');
    
    if (modal && modalContent) {
        modalContent.style.transform = 'scale(0.95)';
        modalContent.style.opacity = '0';
        
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        }, 300);
    }
}

function selectDoctor(doctorId, doctorName) {
    // Show loading state
    const modal = document.getElementById('doctorModal');
    if (modal) {
        modal.innerHTML = `
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 text-center">
                <div class="animate-spin rounded-full h-16 w-16 border-b-4 mx-auto mb-4" style="border-color: #34d399;"></div>
                <p class="text-gray-600">Redirecting to messages with ${doctorName}...</p>
            </div>
        `;
    }
    
    // Redirect to messages page with doctor parameter
    setTimeout(() => {
        window.location.href = 'messages.php?doctor=' + doctorId;
    }, 1000);
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('doctorModal');
    if (event.target === modal) {
        closeDoctorModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDoctorModal();
    }
});

// Auto-close modal after 10 seconds if not interacted with
setTimeout(() => {
    const modal = document.getElementById('success-modal');
    if (modal && modal.style.display !== 'none') {
        closeModalAndShowResults();
    }
}, 10000);
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
