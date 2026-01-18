<?php
$pageTitle = "Assessment Questions";
require_once __DIR__ . '/include/header.php';
require_once __DIR__ . '/../src/helpers/Database.php';

$db = (new Database())->connect();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create':
                $stmt = $db->prepare("INSERT INTO assessment_questions (question_text, question_type, category, options, weight, order_index, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    $_POST['question_text'],
                    $_POST['question_type'],
                    $_POST['category'],
                    $_POST['options'] ?? null,
                    $_POST['weight'] ?? 1,
                    $_POST['order_index'] ?? 0
                ]);
                header('Location: questions.php?success=created');
                exit;
                
            case 'update':
                $stmt = $db->prepare("UPDATE assessment_questions SET question_text = ?, question_type = ?, category = ?, options = ?, weight = ?, order_index = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['question_text'],
                    $_POST['question_type'],
                    $_POST['category'],
                    $_POST['options'] ?? null,
                    $_POST['weight'] ?? 1,
                    $_POST['order_index'] ?? 0,
                    $_POST['id']
                ]);
                header('Location: questions.php?success=updated');
                exit;
                
            case 'toggle':
                $stmt = $db->prepare("UPDATE assessment_questions SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                header('Location: questions.php');
                exit;
                
            case 'delete':
                $stmt = $db->prepare("DELETE FROM assessment_questions WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                header('Location: questions.php?success=deleted');
                exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get filters
$category = $_GET['category'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($category) {
    $where[] = "category = ?";
    $params[] = $category;
}

$whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT * FROM assessment_questions $whereClause ORDER BY category, order_index LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$questions = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM assessment_questions $whereClause");
$stmt->execute($params);
$totalQuestions = $stmt->fetchColumn();
$totalPages = ceil($totalQuestions / $perPage);

// Get categories
$categories = ['mental_health', 'physical_health', 'lifestyle', 'nutrition', 'stress'];
$stmt = $db->query("SELECT category, COUNT(*) as count FROM assessment_questions GROUP BY category");
$categoryCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Assessment Questions</h1>
    <button @click="$dispatch('open-modal', { mode: 'create' })" 
            class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition shadow-lg hover:shadow-xl">
        + Add Question
    </button>
</div>

<!-- Category Filter -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6 animate-fade">
    <div class="flex gap-2 flex-wrap">
        <a href="?category=" 
           class="px-4 py-2 rounded-lg transition <?php echo !$category ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
            All (<?php echo array_sum($categoryCounts); ?>)
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="?category=<?php echo $cat; ?>" 
               class="px-4 py-2 rounded-lg transition <?php echo $category === $cat ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $cat)); ?> (<?php echo $categoryCounts[$cat] ?? 0; ?>)
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Questions Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade">
    <table class="w-full">
        <thead class="bg-gradient-to-r from-gray-800 to-green-400 text-white">
            <tr>
                <th class="px-6 py-4 text-left text-sm font-semibold">Order</th>
                <th class="px-6 py-4 text-left text-sm font-semibold">Question</th>
                <th class="px-6 py-4 text-left text-sm font-semibold">Type</th>
                <th class="px-6 py-4 text-left text-sm font-semibold">Category</th>
                <th class="px-6 py-4 text-left text-sm font-semibold">Weight</th>
                <th class="px-6 py-4 text-left text-sm font-semibold">Status</th>
                <th class="px-6 py-4 text-left text-sm font-semibold">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($questions as $question): ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900"><?php echo $question['order_index']; ?></td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars(substr($question['question_text'], 0, 80)); ?><?php echo strlen($question['question_text']) > 80 ? '...' : ''; ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?php echo htmlspecialchars($question['question_type']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                            <?php echo str_replace('_', ' ', $question['category']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo $question['weight']; ?></td>
                    <td class="px-6 py-4">
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $question['id']; ?>">
                            <button type="submit" class="px-3 py-1 text-xs font-semibold rounded-full transition <?php echo $question['is_active'] ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200'; ?>">
                                <?php echo $question['is_active'] ? 'Active' : 'Inactive'; ?>
                            </button>
                        </form>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex gap-2">
                            <button @click="$dispatch('open-modal', { mode: 'edit', data: <?php echo htmlspecialchars(json_encode($question)); ?> })" 
                                    class="text-blue-600 hover:text-blue-800 transition">
                                Edit
                            </button>
                            <form method="POST" onsubmit="return confirm('Delete this question?');" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $question['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800 transition">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div x-data="questionModal()" @open-modal.window="openModal($event.detail)" x-show="isOpen" x-cloak 
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="closeModal()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 animate-scale" @click.stop>
        <div class="bg-gradient-to-r from-gray-800 to-green-400 px-6 py-4 flex justify-between items-center">
            <h2 class="text-xl font-bold text-white" x-text="mode === 'create' ? 'Add Question' : 'Edit Question'"></h2>
            <button @click="closeModal()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
        </div>
        
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" x-model="mode === 'create' ? 'create' : 'update'">
            <input type="hidden" name="id" x-model="formData.id">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Question Text *</label>
                <textarea name="question_text" x-model="formData.question_text" rows="3" required 
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
                    <select name="question_type" x-model="formData.question_type" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                        <option value="scale">Scale (1-5)</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="text">Text</option>
                        <option value="yes_no">Yes/No</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                    <select name="category" x-model="formData.category" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                        <option value="mental_health">Mental Health</option>
                        <option value="physical_health">Physical Health</option>
                        <option value="lifestyle">Lifestyle</option>
                        <option value="nutrition">Nutrition</option>
                        <option value="stress">Stress</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Options (JSON - for multiple choice)</label>
                <textarea name="options" x-model="formData.options" rows="3" 
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent"
                          placeholder='{"option1": "Text", "option2": "Text"}'></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Weight</label>
                    <input type="number" name="weight" x-model="formData.weight" min="1" max="10" value="1"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                    <input type="number" name="order_index" x-model="formData.order_index" min="0" value="0"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                </div>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-medium">
                    <span x-text="mode === 'create' ? 'Create Question' : 'Update Question'"></span>
                </button>
                <button type="button" @click="closeModal()" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function questionModal() {
    return {
        isOpen: false,
        mode: 'create',
        formData: {
            id: '',
            question_text: '',
            question_type: 'scale',
            category: 'mental_health',
            options: '',
            weight: 1,
            order_index: 0
        },
        
        openModal(detail) {
            this.mode = detail.mode;
            if (detail.mode === 'edit' && detail.data) {
                this.formData = { ...detail.data };
            } else {
                this.resetForm();
            }
            this.isOpen = true;
        },
        
        closeModal() {
            this.isOpen = false;
            this.resetForm();
        },
        
        resetForm() {
            this.formData = {
                id: '',
                question_text: '',
                question_type: 'scale',
                category: 'mental_health',
                options: '',
                weight: 1,
                order_index: 0
            };
        }
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
</style>

<?php require_once __DIR__ . '/include/footer.php'; ?>
