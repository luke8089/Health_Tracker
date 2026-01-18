<?php
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

$db = (new Database())->connect();

// Handle AJAX requests for assessment details
if (isset($_GET['action']) && $_GET['action'] === 'get_assessment_details') {
    header('Content-Type: application/json');
    
    try {
        $assessmentId = $_GET['id'] ?? 0;
        
        // Get assessment details
        $stmt = $db->prepare("
            SELECT a.*, u.name as user_name, u.email as user_email
            FROM assessments a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch();
        
        if (!$assessment) {
            echo json_encode(['success' => false, 'message' => 'Assessment not found']);
            exit;
        }
        
        // Parse JSON responses from the assessment
        $responsesData = json_decode($assessment['responses'], true);
        $answers = $responsesData['answers'] ?? [];
        
        // Get questions to match with answers
        $stmt = $db->prepare("
            SELECT id, question_text as question, category, question_type as answer_type
            FROM assessment_questions
            WHERE is_active = 1
            ORDER BY order_index ASC
        ");
        $stmt->execute();
        $questions = $stmt->fetchAll();
        
        // Build responses array matching questions with answers
        $responses = [];
        foreach ($questions as $question) {
            if (isset($answers[$question['id']])) {
                $answerValue = $answers[$question['id']];
                
                // Format answer based on type
                if ($question['answer_type'] === 'scale') {
                    $responses[] = [
                        'question' => $question['question'],
                        'category' => $question['category'],
                        'answer_type' => 'scale',
                        'answer_value' => $answerValue,
                        'answer_text' => $answerValue . '/10',
                        'points' => null
                    ];
                } else if ($question['answer_type'] === 'multiple_choice') {
                    $responses[] = [
                        'question' => $question['question'],
                        'category' => $question['category'],
                        'answer_type' => 'multiple_choice',
                        'answer_value' => $answerValue,
                        'answer_text' => 'Option ' . $answerValue,
                        'points' => null
                    ];
                } else {
                    $responses[] = [
                        'question' => $question['question'],
                        'category' => $question['category'],
                        'answer_type' => 'text',
                        'answer_value' => $answerValue,
                        'answer_text' => $answerValue,
                        'points' => null
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'assessment' => $assessment,
            'responses' => $responses
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'delete') {
            $assessmentId = $_POST['assessment_id'] ?? 0;
            $stmt = $db->prepare("DELETE FROM assessments WHERE id = ?");
            $stmt->execute([$assessmentId]);
            Utils::redirect('assessments.php', 'Assessment deleted successfully', 'success');
        }
    } catch (Exception $e) {
        Utils::redirect('assessments.php', 'Error: ' . $e->getMessage(), 'error');
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$severity = $_GET['severity'] ?? '';
$type = $_GET['type'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "u.name LIKE ?";
    $params[] = "%$search%";
}

if ($severity) {
    $where[] = "a.severity = ?";
    $params[] = $severity;
}

if ($type) {
    $where[] = "a.type = ?";
    $params[] = $type;
}

$whereClause = implode(' AND ', $where);

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM assessments a LEFT JOIN users u ON a.user_id = u.id WHERE $whereClause");
$stmt->execute($params);
$totalAssessments = $stmt->fetch()['total'];
$totalPages = ceil($totalAssessments / $perPage);

// Get assessments
$stmt = $db->prepare("
    SELECT a.*, u.name as user_name, u.email as user_email,
           (SELECT COUNT(*) FROM recommendations WHERE assessment_id = a.id) as recommendation_count,
           (SELECT COUNT(*) FROM doctor_recommendations WHERE assessment_id = a.id) as doctor_review_count
    FROM assessments a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE $whereClause
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$assessments = $stmt->fetchAll();

// Get severity counts
$stmt = $db->query("SELECT severity, COUNT(*) as count FROM assessments WHERE severity IS NOT NULL AND severity != '' GROUP BY severity");
$severityCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Now include header after all processing
$pageTitle = "Assessments";
require_once __DIR__ . '/include/header.php';
?>

<script src="../public/assets/js/modal.js"></script>

<!-- Header with Search and Filters -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Health Assessments</h2>
            <p class="text-sm text-gray-600 mt-1">Total: <?php echo number_format($totalAssessments); ?> assessments</p>
        </div>
        
        <!-- Search and Filters -->
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by user..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent w-full sm:w-64">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            
            <select name="severity" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                <option value="">All Severities</option>
                <?php foreach ($severityCounts as $sev => $count): ?>
                    <option value="<?php echo htmlspecialchars($sev); ?>" <?php echo $severity === $sev ? 'selected' : ''; ?>>
                        <?php echo ucfirst(htmlspecialchars($sev)); ?> (<?php echo $count; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="px-6 py-2 bg-gradient-to-r from-gray-800 to-green-400 text-white rounded-lg hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                Filter
            </button>
            
            <?php if ($search || $severity || $type): ?>
                <a href="assessments.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Statistics Overview -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <?php 
    $severityColors = [
        'critical' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'bg-red-500'],
        'poor' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'icon' => 'bg-orange-500'],
        'fair' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'bg-yellow-500'],
        'good' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'bg-green-500'],
        'excellent' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'bg-blue-500']
    ];
    
    foreach ($severityColors as $sev => $colors): 
        $count = $severityCounts[$sev] ?? 0;
    ?>
        <div class="bg-white rounded-xl shadow-md p-4 hover:shadow-lg transition animate-fade">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full <?php echo $colors['icon']; ?>"></div>
                <div>
                    <p class="text-xs text-gray-600 capitalize"><?php echo $sev; ?></p>
                    <p class="text-xl font-bold text-gray-900"><?php echo $count; ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Assessments Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">User</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Score</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Severity</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Reviews</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($assessments)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p class="text-lg font-semibold">No assessments found</p>
                            <p class="text-sm">Try adjusting your filters</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assessments as $assessment): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($assessment['user_name'] ?? 'Unknown'); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($assessment['user_email'] ?? ''); ?></p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 capitalize">
                                    <?php echo htmlspecialchars($assessment['type'] ?? 'comprehensive'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2 max-w-[100px]">
                                        <?php 
                                        $percentage = $assessment['max_score'] > 0 ? ($assessment['score'] / $assessment['max_score']) * 100 : 0;
                                        ?>
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900"><?php echo $assessment['score']; ?>/<?php echo $assessment['max_score']; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($assessment['severity']): 
                                    $colors = $severityColors[$assessment['severity']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800'];
                                ?>
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $colors['bg'] . ' ' . $colors['text']; ?> capitalize">
                                        <?php echo htmlspecialchars($assessment['severity']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-sm text-gray-400">Not scored</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2 text-sm">
                                    <span class="text-gray-600" title="Recommendations">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <?php echo $assessment['recommendation_count']; ?>
                                    </span>
                                    <span class="text-gray-600" title="Doctor Reviews">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        <?php echo $assessment['doctor_review_count']; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo date('M j, Y', strtotime($assessment['created_at'])); ?>
                                <br>
                                <span class="text-xs text-gray-400"><?php echo date('g:i A', strtotime($assessment['created_at'])); ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="viewAssessmentDetails(<?php echo $assessment['id']; ?>)" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Details">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    <button onclick="deleteAssessment(<?php echo $assessment['id']; ?>, '<?php echo htmlspecialchars(addslashes($assessment['user_name'] ?? 'Unknown')); ?>')" 
                                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1-1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-600">
                    Showing <?php echo min($offset + 1, $totalAssessments); ?> to <?php echo min($offset + $perPage, $totalAssessments); ?> of <?php echo $totalAssessments; ?> results
                </p>
                
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&severity=<?php echo urlencode($severity); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&severity=<?php echo urlencode($severity); ?>" 
                           class="px-4 py-2 border rounded-lg transition <?php echo $i === $page ? 'bg-green-500 text-white border-green-500' : 'border-gray-300 hover:bg-gray-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&severity=<?php echo urlencode($severity); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// View assessment details in modal
async function viewAssessmentDetails(assessmentId) {
    try {
        // Fetch assessment details from the same page
        const response = await fetch(`assessments.php?action=get_assessment_details&id=${assessmentId}`);
        const data = await response.json();
        
        if (!data.success) {
            await AppModal.alert({
                title: 'Error',
                message: data.message || 'Failed to load assessment details',
                type: 'danger'
            });
            return;
        }
        
        const assessment = data.assessment;
        const responses = data.responses || [];
        
        // Build severity badge
        const severityColors = {
            'critical': 'bg-red-100 text-red-800',
            'poor': 'bg-orange-100 text-orange-800',
            'fair': 'bg-yellow-100 text-yellow-800',
            'good': 'bg-green-100 text-green-800',
            'excellent': 'bg-blue-100 text-blue-800'
        };
        const severityClass = severityColors[assessment.severity] || 'bg-gray-100 text-gray-800';
        
        // Build responses HTML
        let responsesHTML = '';
        if (responses.length > 0) {
            responsesHTML = `
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    ${responses.map((resp, index) => `
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <h4 class="font-semibold text-gray-900 flex-1">
                                    <span class="text-gray-500 mr-2">${index + 1}.</span>${escapeHtml(resp.question)}
                                </h4>
                                ${resp.points ? `<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full flex-shrink-0">${resp.points} pts</span>` : ''}
                            </div>
                            <div class="mt-2">
                                ${resp.answer_type === 'scale' ? `
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                                            <div class="bg-gradient-to-r from-green-400 to-green-600 h-2 rounded-full" style="width: ${(resp.answer_value / 10) * 100}%"></div>
                                        </div>
                                        <span class="font-bold text-gray-900">${resp.answer_value}/10</span>
                                    </div>
                                ` : resp.answer_type === 'multiple_choice' ? `
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-gray-700">${escapeHtml(resp.answer_text)}</span>
                                    </div>
                                ` : `
                                    <p class="text-gray-700 italic">"${escapeHtml(resp.answer_text)}"</p>
                                `}
                            </div>
                            ${resp.category ? `<p class="text-xs text-gray-500 mt-2">Category: ${escapeHtml(resp.category)}</p>` : ''}
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            responsesHTML = '<p class="text-center text-gray-500 py-8">No responses recorded</p>';
        }
        
        // Create modal content
        const modalContent = `
            <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" onclick="closeAssessmentModal(event)">
                <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-gray-800 to-green-400 text-white p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h2 class="text-2xl font-bold mb-2">Assessment Details</h2>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-sm font-semibold">
                                        ${escapeHtml(assessment.user_name)}
                                    </span>
                                    <span class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-sm font-semibold capitalize">
                                        ${escapeHtml(assessment.type || 'comprehensive')}
                                    </span>
                                    <span class="text-sm opacity-90">
                                        ${new Date(assessment.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                    </span>
                                </div>
                            </div>
                            <button onclick="closeAssessmentModal()" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 200px);">
                        <!-- Score and Severity -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
                                <p class="text-sm text-blue-600 font-semibold mb-1">Total Score</p>
                                <p class="text-3xl font-bold text-blue-900">${assessment.score}/${assessment.max_score}</p>
                                <div class="mt-2 bg-white rounded-full h-2">
                                    <div class="bg-gradient-to-r from-blue-400 to-blue-600 h-2 rounded-full" style="width: ${(assessment.score / assessment.max_score) * 100}%"></div>
                                </div>
                            </div>
                            
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border border-purple-200">
                                <p class="text-sm text-purple-600 font-semibold mb-1">Severity Level</p>
                                <span class="inline-block px-4 py-2 ${severityClass} text-lg font-bold rounded-full mt-1 capitalize">
                                    ${escapeHtml(assessment.severity || 'N/A')}
                                </span>
                            </div>
                            
                            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border border-green-200">
                                <p class="text-sm text-green-600 font-semibold mb-1">Total Questions</p>
                                <p class="text-3xl font-bold text-green-900">${responses.length}</p>
                                <p class="text-xs text-green-600 mt-1">Responses recorded</p>
                            </div>
                        </div>
                        
                        <!-- Responses -->
                        <div class="mb-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Assessment Responses
                            </h3>
                            ${responsesHTML}
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                        <button onclick="closeAssessmentModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        const modalDiv = document.createElement('div');
        modalDiv.id = 'assessmentDetailsModal';
        modalDiv.innerHTML = modalContent;
        document.body.appendChild(modalDiv);
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
    } catch (error) {
        console.error('Error loading assessment details:', error);
        await AppModal.alert({
            title: 'Error',
            message: 'Failed to load assessment details. Please try again.',
            type: 'danger'
        });
    }
}

function closeAssessmentModal(event) {
    // Only close if clicking backdrop or close button
    if (!event || event.target === event.currentTarget || event.currentTarget.tagName === 'BUTTON') {
        const modal = document.getElementById('assessmentDetailsModal');
        if (modal) {
            modal.remove();
            document.body.style.overflow = '';
        }
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Delete assessment with modal confirmation
async function deleteAssessment(assessmentId, userName) {
    const confirmed = await AppModal.confirm({
        title: 'Delete Assessment',
        message: `Are you sure you want to delete the assessment for <strong>${userName}</strong>? This action cannot be undone and will also delete all associated recommendations and reviews.`,
        confirmText: 'Delete',
        cancelText: 'Cancel',
        type: 'danger'
    });

    if (confirmed) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="assessment_id" value="${assessmentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/include/footer.php'; ?>
