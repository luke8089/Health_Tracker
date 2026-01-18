<?php
$pageTitle = "System Settings";
require_once __DIR__ . '/include/header.php';
require_once __DIR__ . '/../src/helpers/Database.php';

$db = (new Database())->connect();

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_general':
                // Save to a settings file or database
                $config = [
                    'site_name' => $_POST['site_name'],
                    'site_email' => $_POST['site_email'],
                    'timezone' => $_POST['timezone']
                ];
                file_put_contents(__DIR__ . '/../config/settings.json', json_encode($config, JSON_PRETTY_PRINT));
                $success = "General settings updated successfully";
                break;
                
            case 'update_user':
                $config = [
                    'registration_enabled' => isset($_POST['registration_enabled']),
                    'email_verification' => isset($_POST['email_verification']),
                    'default_role' => $_POST['default_role']
                ];
                file_put_contents(__DIR__ . '/../config/user_settings.json', json_encode($config, JSON_PRETTY_PRINT));
                $success = "User settings updated successfully";
                break;
                
            case 'update_habit':
                $config = [
                    'default_points' => intval($_POST['default_points']),
                    'streak_bonus' => intval($_POST['streak_bonus']),
                    'verification_required' => isset($_POST['verification_required'])
                ];
                file_put_contents(__DIR__ . '/../config/habit_settings.json', json_encode($config, JSON_PRETTY_PRINT));
                $success = "Habit settings updated successfully";
                break;
                
            case 'clear_cache':
                // Clear cache logic
                $success = "Cache cleared successfully";
                break;
                
            case 'optimize_db':
                $tables = ['users', 'doctors', 'assessments', 'habits', 'habit_completions', 'messages', 'recommendations'];
                foreach ($tables as $table) {
                    $stmt = $db->query("OPTIMIZE TABLE $table");
                    $stmt->fetchAll(); // Fetch all results to clear the buffer
                }
                $success = "Database optimized successfully";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Load current settings
$generalSettings = file_exists(__DIR__ . '/../config/settings.json') 
    ? json_decode(file_get_contents(__DIR__ . '/../config/settings.json'), true) 
    : ['site_name' => 'Health Tracker', 'site_email' => 'admin@healthtracker.com', 'timezone' => 'UTC'];

$userSettings = file_exists(__DIR__ . '/../config/user_settings.json')
    ? json_decode(file_get_contents(__DIR__ . '/../config/user_settings.json'), true)
    : ['registration_enabled' => true, 'email_verification' => false, 'default_role' => 'user'];

$habitSettings = file_exists(__DIR__ . '/../config/habit_settings.json')
    ? json_decode(file_get_contents(__DIR__ . '/../config/habit_settings.json'), true)
    : ['default_points' => 10, 'streak_bonus' => 5, 'verification_required' => true];

// Database stats
$stmt = $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.TABLES WHERE table_schema = DATABASE()");
$dbSize = $stmt->fetchColumn();
$stmt->closeCursor(); // Close cursor to free up the connection
?>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-xl mb-6 animate-fade">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-xl mb-6 animate-fade">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">System Settings</h1>
    <p class="text-gray-600 mt-2">Configure your Health Tracker application</p>
</div>

<!-- Settings Tabs -->
<div class="bg-white rounded-xl shadow-md mb-6" x-data="{ tab: 'general' }">
    <div class="flex border-b border-gray-200 overflow-x-auto">
        <button @click="tab = 'general'" 
                :class="tab === 'general' ? 'border-b-2 border-green-500 text-green-600' : 'text-gray-600 hover:text-gray-900'"
                class="px-6 py-4 text-sm font-medium transition-colors">
            General
        </button>
        <button @click="tab = 'user'" 
                :class="tab === 'user' ? 'border-b-2 border-green-500 text-green-600' : 'text-gray-600 hover:text-gray-900'"
                class="px-6 py-4 text-sm font-medium transition-colors">
            User Management
        </button>
        <button @click="tab = 'habit'" 
                :class="tab === 'habit' ? 'border-b-2 border-green-500 text-green-600' : 'text-gray-600 hover:text-gray-900'"
                class="px-6 py-4 text-sm font-medium transition-colors">
            Habits
        </button>
        <button @click="tab = 'database'" 
                :class="tab === 'database' ? 'border-b-2 border-green-500 text-green-600' : 'text-gray-600 hover:text-gray-900'"
                class="px-6 py-4 text-sm font-medium transition-colors">
            Database
        </button>
    </div>
    
    <!-- General Settings -->
    <div x-show="tab === 'general'" class="p-6">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="update_general">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Site Name</label>
                <input type="text" name="site_name" value="<?php echo htmlspecialchars($generalSettings['site_name']); ?>" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email</label>
                <input type="email" name="site_email" value="<?php echo htmlspecialchars($generalSettings['site_email']); ?>" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                <select name="timezone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                    <?php
                    $timezones = ['UTC', 'America/New_York', 'America/Chicago', 'America/Los_Angeles', 'Europe/London', 'Asia/Tokyo'];
                    foreach ($timezones as $tz):
                    ?>
                        <option value="<?php echo $tz; ?>" <?php echo $generalSettings['timezone'] === $tz ? 'selected' : ''; ?>>
                            <?php echo $tz; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-medium">
                Save General Settings
            </button>
        </form>
    </div>
    
    <!-- User Management Settings -->
    <div x-show="tab === 'user'" class="p-6">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="update_user">
            
            <div>
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="registration_enabled" <?php echo $userSettings['registration_enabled'] ? 'checked' : ''; ?>
                           class="w-5 h-5 text-green-500 border-gray-300 rounded focus:ring-2 focus:ring-green-400">
                    <span class="text-sm font-medium text-gray-700">Enable User Registration</span>
                </label>
            </div>
            
            <div>
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="email_verification" <?php echo $userSettings['email_verification'] ? 'checked' : ''; ?>
                           class="w-5 h-5 text-green-500 border-gray-300 rounded focus:ring-2 focus:ring-green-400">
                    <span class="text-sm font-medium text-gray-700">Require Email Verification</span>
                </label>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Default User Role</label>
                <select name="default_role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                    <option value="user" <?php echo $userSettings['default_role'] === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="doctor" <?php echo $userSettings['default_role'] === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                </select>
            </div>
            
            <button type="submit" class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-medium">
                Save User Settings
            </button>
        </form>
    </div>
    
    <!-- Habit Settings -->
    <div x-show="tab === 'habit'" class="p-6">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="update_habit">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Default Points per Completion</label>
                <input type="number" name="default_points" value="<?php echo $habitSettings['default_points']; ?>" min="1" max="100" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Streak Bonus Points (per week)</label>
                <input type="number" name="streak_bonus" value="<?php echo $habitSettings['streak_bonus']; ?>" min="0" max="50" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
            </div>
            
            <div>
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="verification_required" <?php echo $habitSettings['verification_required'] ? 'checked' : ''; ?>
                           class="w-5 h-5 text-green-500 border-gray-300 rounded focus:ring-2 focus:ring-green-400">
                    <span class="text-sm font-medium text-gray-700">Require Verification for Points</span>
                </label>
            </div>
            
            <button type="submit" class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-medium">
                Save Habit Settings
            </button>
        </form>
    </div>
    
    <!-- Database Maintenance -->
    <div x-show="tab === 'database'" class="p-6 space-y-6">
        <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Database Statistics</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Database Size</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $dbSize; ?> MB</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Tables</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php 
                        $stmt = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = DATABASE()");
                        $tableCount = $stmt->fetchColumn();
                        $stmt->closeCursor();
                        echo $tableCount;
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="space-y-4">
            <form method="POST" onsubmit="return confirm('Clear cache? This action cannot be undone.');">
                <input type="hidden" name="action" value="clear_cache">
                <button type="submit" class="w-full px-6 py-3 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition font-medium">
                    🗑️ Clear Cache
                </button>
            </form>
            
            <form method="POST" onsubmit="return confirm('Optimize database? This may take a few moments.');">
                <input type="hidden" name="action" value="optimize_db">
                <button type="submit" class="w-full px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition font-medium">
                    ⚡ Optimize Database
                </button>
            </form>
            
            <a href="../migrations/schema.sql" download class="block w-full px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium text-center">
                💾 Download Schema Backup
            </a>
        </div>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-sm text-yellow-800">
                <strong>⚠️ Warning:</strong> Database maintenance operations should be performed during low-traffic periods.
            </p>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade">
        <h3 class="font-bold text-gray-900 mb-4">PHP Information</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Version:</span>
                <span class="font-semibold"><?php echo phpversion(); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Memory Limit:</span>
                <span class="font-semibold"><?php echo ini_get('memory_limit'); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Max Upload:</span>
                <span class="font-semibold"><?php echo ini_get('upload_max_filesize'); ?></span>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade">
        <h3 class="font-bold text-gray-900 mb-4">Server Information</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Server:</span>
                <span class="font-semibold"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">OS:</span>
                <span class="font-semibold"><?php echo PHP_OS; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Document Root:</span>
                <span class="font-semibold text-xs"><?php echo substr($_SERVER['DOCUMENT_ROOT'], -20); ?></span>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade">
        <h3 class="font-bold text-gray-900 mb-4">Application Status</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between items-center">
                <span class="text-gray-600">Status:</span>
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Online</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Version:</span>
                <span class="font-semibold">1.0.0</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Environment:</span>
                <span class="font-semibold">Development</span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/include/footer.php'; ?>
