<?php
require_once __DIR__ . '/../../src/helpers/Auth.php';
require_once __DIR__ . '/../../src/helpers/Database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('admin');
$currentUser = $auth->getCurrentUser();
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: true }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Admin | Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Pacifico&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade { animation: fadeIn 0.5s ease-out; }
        .sidebar-link { transition: all 0.3s ease; }
        .sidebar-link:hover { background: rgba(161, 209, 177, 0.1); padding-left: 1.25rem; }
        .sidebar-link.active { background: rgba(161, 209, 177, 0.2); color: #A1D1B1; font-weight: 600; border-left: 4px solid #A1D1B1; }
    </style>
</head>
<body class="bg-gray-50">
<div class="flex h-screen">
    <!-- Sidebar -->
    <aside x-show="sidebarOpen" x-transition class="w-64 bg-white shadow-xl flex flex-col">
        <div class="p-6 border-b">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-gray-800 to-green-400 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div><h1 class="text-gray-900" style="font-family: 'Pacifico', cursive; font-weight: 400;">Health Tracker</h1><p class="text-xs text-gray-500">Admin Panel</p></div>
            </div>
        </div>
        <nav class="flex-1 p-4 overflow-y-auto space-y-1">
            <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Users</span>
            </a>
            <a href="doctors.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'doctors.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Doctors</span>
            </a>
            <a href="assessments.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'assessments.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Assessments</span>
            </a>
            <a href="habits.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'habits.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Habits</span>
            </a>
            <a href="habit_requests.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'habit_requests.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Habit Proofs</span>
            </a>
            <a href="habit_edit_requests.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'habit_edit_requests.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span>Edit Requests</span>
            </a>
            <a href="questions.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'questions.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Questions</span>
            </a>
            <a href="reports.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Reports</span>
            </a>
            <div class="border-t my-2"></div>
            <a href="settings.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : 'text-gray-700'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Settings</span>
            </a>
        </nav>
        <div class="p-4 border-t">
            <a href="../public/profile.php" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-lg transition">
                <div class="w-10 h-10 bg-gradient-to-br from-gray-800 to-green-400 rounded-full flex items-center justify-center text-white font-bold">
                    <?php echo strtoupper(substr($currentUser['name'], 0, 2)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                    <p class="text-xs text-gray-500">Admin</p>
                </div>
            </a>
            <a href="../public/logout.php" class="mt-2 w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span class="text-sm font-medium">Logout</span>
            </a>
        </div>
    </aside>
    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm border-b px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($pageTitle); ?></h2>
                </div>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
