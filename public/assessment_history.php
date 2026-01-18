<?php
/**
 * Assessment History Page
 * View all past health assessments
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Database.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = new Database();
$conn = $db->connect();

// Pagination settings
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Get total count
$stmt = $conn->prepare("SELECT COUNT(*) FROM assessments WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$totalAssessments = $stmt->fetchColumn();
$totalPages = ceil($totalAssessments / $perPage);

// Get assessments for current page
$stmt = $conn->prepare("
    SELECT * FROM assessments 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$currentUser['id'], $perPage, $offset]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter options
$filterSeverity = isset($_GET['severity']) ? $_GET['severity'] : '';
$filterYear = isset($_GET['year']) ? $_GET['year'] : '';

// Apply filters if set
if ($filterSeverity || $filterYear) {
    $query = "SELECT * FROM assessments WHERE user_id = ?";
    $params = [$currentUser['id']];
    
    if ($filterSeverity) {
        $query .= " AND severity = ?";
        $params[] = $filterSeverity;
    }
    
    if ($filterYear) {
        $query .= " AND YEAR(created_at) = ?";
        $params[] = $filterYear;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update total count for pagination
    $countQuery = str_replace("SELECT *", "SELECT COUNT(*)", $query);
    $countQuery = str_replace(" LIMIT ? OFFSET ?", "", $countQuery);
    $stmt = $conn->prepare($countQuery);
    $stmt->execute(array_slice($params, 0, -2));
    $totalAssessments = $stmt->fetchColumn();
    $totalPages = ceil($totalAssessments / $perPage);
}

$title = "Assessment History - Health Tracker";
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-100">
    <div class="max-w-6xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <a href="assessment.php" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-4 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to Assessment
                    </a>
                    <h1 class="text-4xl font-bold" style="color: #1f2937;">Assessment History</h1>
                    <p class="text-gray-600 mt-2">View and track all your health assessments</p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold" style="color: #34d399;"><?php echo $totalAssessments; ?></div>
                    <div class="text-sm text-gray-600">Total Assessments</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <form method="GET" class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Filter by Status</label>
                    <select name="severity" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-green-400 focus:outline-none transition-colors">
                        <option value="">All Status</option>
                        <option value="excellent" <?php echo $filterSeverity === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                        <option value="good" <?php echo $filterSeverity === 'good' ? 'selected' : ''; ?>>Good</option>
                        <option value="fair" <?php echo $filterSeverity === 'fair' ? 'selected' : ''; ?>>Fair</option>
                        <option value="poor" <?php echo $filterSeverity === 'poor' ? 'selected' : ''; ?>>Poor</option>
                        <option value="critical" <?php echo $filterSeverity === 'critical' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Filter by Year</label>
                    <select name="year" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-green-400 focus:outline-none transition-colors">
                        <option value="">All Years</option>
                        <?php
                        $currentYear = date('Y');
                        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                            $selected = $filterYear == $y ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold text-white transition-all duration-300 hover:shadow-lg" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                        Apply Filters
                    </button>
                    <?php if ($filterSeverity || $filterYear): ?>
                        <a href="assessment_history.php" class="px-6 py-3 rounded-xl font-semibold border-2 transition-all duration-300 hover:shadow-lg" style="border-color: #34d399; color: #1f2937;">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Assessments List -->
        <?php if (empty($assessments)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <svg class="w-20 h-20 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Assessments Found</h3>
                <p class="text-gray-600 mb-6">
                    <?php if ($filterSeverity || $filterYear): ?>
                        No assessments match your filter criteria. Try adjusting your filters.
                    <?php else: ?>
                        You haven't taken any health assessments yet.
                    <?php endif; ?>
                </p>
                <a href="assessment.php" class="inline-flex items-center px-6 py-3 rounded-xl font-semibold text-white transition-all duration-300 hover:shadow-lg" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Take Your First Assessment
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($assessments as $assessment): 
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
                    <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-[1.02]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4 flex-1">
                                <!-- Score Badge -->
                                <div class="w-16 h-16 rounded-xl flex flex-col items-center justify-center text-white font-bold" 
                                     style="background: <?php echo $typeGradient; ?>;">
                                    <span class="text-2xl"><?php echo $assessment['score']; ?></span>
                                    <span class="text-xs">%</span>
                                </div>
                                
                                <!-- Assessment Info -->
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h3 class="font-bold text-lg text-gray-900 capitalize">
                                            <?php echo str_replace('_', ' ', $assessment['severity']); ?> Health Status
                                        </h3>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold text-white"
                                              style="background: <?php echo $typeGradient; ?>;">
                                            <span class="mr-1"><?php echo $typeIcon; ?></span>
                                            <?php echo ucfirst($assessmentType); ?>
                                        </span>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php 
                                            echo $assessment['severity'] === 'excellent' ? 'bg-green-100 text-green-700' : 
                                                ($assessment['severity'] === 'good' ? 'bg-blue-100 text-blue-700' : 
                                                ($assessment['severity'] === 'fair' ? 'bg-yellow-100 text-yellow-700' : 
                                                ($assessment['severity'] === 'poor' ? 'bg-orange-100 text-orange-700' : 
                                                'bg-red-100 text-red-700')));
                                        ?>">
                                            <?php echo strtoupper($assessment['severity']); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 text-sm text-gray-600">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <?php echo date('F j, Y', strtotime($assessment['completed_at'] ?? $assessment['created_at'])); ?>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <?php echo date('g:i A', strtotime($assessment['completed_at'] ?? $assessment['created_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Category Scores Preview -->
                                    <?php 
                                    $categoryScores = json_decode($assessment['category_scores'], true);
                                    if (is_array($categoryScores) && !empty($categoryScores)): 
                                    ?>
                                        <div class="mt-3 flex gap-2 flex-wrap">
                                            <?php foreach ($categoryScores as $category => $score): 
                                                $percentage = min(100, round($score * 20));
                                            ?>
                                                <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg text-xs">
                                                    <span class="text-gray-600 capitalize"><?php echo str_replace('_', ' ', $category); ?>:</span>
                                                    <span class="font-semibold" style="color: #34d399;"><?php echo $percentage; ?>%</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2 ml-4">
                                <a href="assessment.php?results=<?php echo $assessment['id']; ?>" 
                                   class="px-6 py-3 rounded-xl font-semibold transition-all duration-300 hover:shadow-md flex items-center gap-2"
                                   style="background-color: #34d399; color: #1f2937;">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex justify-center items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $filterSeverity ? '&severity=' . $filterSeverity : ''; ?><?php echo $filterYear ? '&year=' . $filterYear : ''; ?>" 
                           class="px-4 py-2 rounded-xl font-medium transition-all duration-300 border-2" 
                           style="border-color: #34d399; color: #1f2937;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $filterSeverity ? '&severity=' . $filterSeverity : ''; ?><?php echo $filterYear ? '&year=' . $filterYear : ''; ?>" 
                           class="px-4 py-2 rounded-xl font-semibold transition-all duration-300 <?php echo $i === $page ? 'text-white' : 'border-2'; ?>"
                           style="<?php echo $i === $page ? 'background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);' : 'border-color: #34d399; color: #1f2937;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $filterSeverity ? '&severity=' . $filterSeverity : ''; ?><?php echo $filterYear ? '&year=' . $filterYear : ''; ?>" 
                           class="px-4 py-2 rounded-xl font-medium transition-all duration-300 border-2" 
                           style="border-color: #34d399; color: #1f2937;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
                
                <p class="text-center text-gray-600 text-sm mt-4">
                    Showing <?php echo min($offset + 1, $totalAssessments); ?> - <?php echo min($offset + $perPage, $totalAssessments); ?> of <?php echo $totalAssessments; ?> assessments
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
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

.animate-fade-in {
    animation: fade-in 0.5s ease-out forwards;
}
</style>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
