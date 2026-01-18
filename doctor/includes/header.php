<?php
// Auth is already initialized in bootstrap.php, so we use the global instance
global $auth, $currentUser;

// If not set (shouldn't happen), initialize them
if (!isset($auth)) {
    require_once __DIR__ . '/../../src/helpers/Auth.php';
    $auth = new Auth();
}

if (!isset($currentUser)) {
    $currentUser = $auth->getCurrentUser();
}

require_once __DIR__ . '/../../src/helpers/Utils.php';
require_once __DIR__ . '/../../src/helpers/Config.php';

$flashMessage = Utils::getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Doctor Dashboard - Health Tracker'; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1f2937',
                        'primary-dark': '#111827',
                        'accent': '#34d399',
                        'accent-light': '#6ee7b7',
                        'success': '#34d399',
                        'warning': '#f59e0b',
                        'danger': '#ef4444'
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Pacifico&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            padding-top: 4rem;
        }
        
        .header-glass {
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(161, 209, 177, 0.2);
            box-shadow: 0 8px 32px rgba(28, 37, 41, 0.1);
        }
        
        .nav-link {
            position: relative;
            overflow: hidden;
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem !important;
        }
        
        .nav-link svg {
            width: 1.125rem;
            height: 1.125rem;
            margin-right: 0.375rem;
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #1f2937 0%, #34d399 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        .nav-link:hover::before,
        .nav-link.active::before {
            width: 80%;
        }
        
        .avatar-gradient {
            background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        /* Mobile menu styles */
        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .mobile-menu.active {
            max-height: 500px;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 3.5rem;
            }
            
            .nav-link {
                width: 100%;
                justify-content: flex-start;
                padding: 0.75rem 1rem !important;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .nav-link {
                font-size: 0.8125rem;
                padding: 0.4rem 0.6rem !important;
            }
            
            .nav-link svg {
                width: 1rem;
                height: 1rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Doctor Navigation Header -->
    <nav id="mainNav" class="fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-b border-white/20 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6">
            <div class="flex justify-between items-center h-14 md:h-16">
                <!-- Logo and Brand -->
                <div class="flex items-center">
                    <a href="<?php echo doctorUrl('dashboard.php'); ?>" class="flex items-center space-x-2 group">
                        <div class="w-8 h-8 md:w-10 md:h-10 bg-gradient-to-br from-gray-800 to-green-400 rounded-lg md:rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-2xl transition-all duration-500 transform group-hover:scale-110">
                            <svg class="w-4 h-4 md:w-6 md:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <span class="text-base md:text-xl bg-gradient-to-r from-gray-800 to-green-400 bg-clip-text text-transparent hidden sm:block" style="font-family: 'Pacifico', cursive; font-weight: 400;">Health Tracker</span>
                    </a>
                </div>

                <!-- Mobile Menu Button -->
                <button id="mobileMenuBtn" class="md:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-1 lg:space-x-2">
                    <a href="<?php echo doctorUrl('dashboard.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 rounded-lg transition-all duration-300 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-md' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <span class="hidden lg:inline">Dashboard</span>
                    </a>
                    <a href="<?php echo doctorUrl('assessments.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 rounded-lg transition-all duration-300 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'assessments.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-md' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        <span class="hidden lg:inline">Assessments</span>
                    </a>
                    <a href="<?php echo doctorUrl('verify_habits.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 rounded-lg transition-all duration-300 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'verify_habits.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-md' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="hidden lg:inline">Verify</span>
                    </a>
                    <a href="<?php echo doctorUrl('monitor_habits.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 rounded-lg transition-all duration-300 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'monitor_habits.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-md' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <span class="hidden lg:inline">Monitor</span>
                    </a>
                    <a href="<?php echo doctorUrl('video_calls.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 rounded-lg transition-all duration-300 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'video_calls.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-md' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span class="hidden lg:inline">Video</span>
                    </a>
                    <a href="<?php echo doctorUrl('messages.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 rounded-lg transition-all duration-300 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-md' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <span class="hidden lg:inline">Messages</span>
                    </a>
                </div>

                <!-- User Menu -->
                <div class="hidden md:flex items-center">
                    <!-- Profile Dropdown -->
                    <div class="relative" id="profileDropdownContainer" onmouseenter="showDropdown()" onmouseleave="hideDropdown()">
                        <button id="profileButton" class="flex items-center space-x-2 p-1.5 md:p-2 rounded-lg hover:bg-gradient-to-r hover:from-accent/10 hover:to-primary/10 transition-all duration-300 group">
                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center text-white text-xs md:text-sm font-bold shadow-md group-hover:shadow-lg transition-all duration-300 overflow-hidden">
                                <?php if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/../../public/' . $currentUser['avatar'])): ?>
                                    <img src="<?php echo publicUrl(htmlspecialchars($currentUser['avatar'])); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full avatar-gradient flex items-center justify-center">
                                        <?php echo strtoupper(substr($currentUser['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="text-sm font-semibold hidden lg:block">Dr. <?php echo htmlspecialchars(substr($currentUser['name'], 0, 10)); ?><?php echo strlen($currentUser['name']) > 10 ? '...' : ''; ?></span>
                            <svg id="dropdownIcon" class="w-4 h-4 text-gray-600 transition-all duration-300 group-hover:text-primary group-hover:rotate-180 hidden lg:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div id="profileDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-100 py-3 z-50">
                            <a href="<?php echo doctorUrl('profile.php'); ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gradient-to-r hover:from-green-400/10 hover:to-gray-800/10 hover:text-gray-900 transition-all duration-300 mx-2 rounded-xl transform hover:scale-105">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                My Profile
                            </a>
                            <a href="<?php echo publicUrl('about.php'); ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gradient-to-r hover:from-green-400/10 hover:to-gray-800/10 hover:text-gray-900 transition-all duration-300 mx-2 rounded-xl transform hover:scale-105">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                About Us
                            </a>
                            <hr class="my-2 border-gray-200/50">
                            <a href="<?php echo publicUrl('logout.php'); ?>" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-gradient-to-r hover:from-red-50 hover:to-pink-50 hover:text-red-700 transition-all duration-300 mx-2 rounded-xl transform hover:scale-105">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobileMenu" class="mobile-menu md:hidden bg-white/95 backdrop-blur-md border-t border-gray-200">
                <div class="py-2 space-y-1">
                    <a href="<?php echo doctorUrl('dashboard.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="<?php echo doctorUrl('assessments.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'assessments.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        Assessments
                    </a>
                    <a href="<?php echo doctorUrl('verify_habits.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'verify_habits.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Verify Habits
                    </a>
                    <a href="<?php echo doctorUrl('monitor_habits.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'monitor_habits.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Monitor Habits
                    </a>
                    <a href="<?php echo doctorUrl('video_calls.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'video_calls.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        Video Calls
                    </a>
                    <a href="<?php echo doctorUrl('messages.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        Messages
                    </a>
                    <div class="border-t border-gray-200 mt-2 pt-2">
                        <a href="<?php echo doctorUrl('profile.php'); ?>" class="nav-link flex items-center text-gray-700 hover:text-gray-900 hover:bg-green-400/10">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            My Profile
                        </a>
                        <a href="<?php echo publicUrl('logout.php'); ?>" class="nav-link flex items-center text-red-600 hover:text-red-700 hover:bg-red-50">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flashMessage): ?>
    <div id="flashMessage" class="fixed top-20 right-4 max-w-md p-4 rounded-lg shadow-lg z-50 transition-all duration-300 <?php 
        echo $flashMessage['type'] === 'success' ? 'bg-green-600 text-white' : 
            ($flashMessage['type'] === 'error' ? 'bg-red-600 text-white' : 
            ($flashMessage['type'] === 'warning' ? 'bg-yellow-500 text-white' : 'bg-blue-600 text-white')); 
    ?>" style="display: block;">
        <div class="flex items-center justify-between">
            <p class="font-medium"><?php echo htmlspecialchars($flashMessage['message']); ?></p>
            <button onclick="closeFlashMessage()" class="ml-4 text-white hover:text-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main class="flex-1">

    <script>
        // Flash message functionality
        function closeFlashMessage() {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                flashMessage.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                setTimeout(closeFlashMessage, 5000);
            }
            
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('active');
                    
                    // Toggle icon
                    const icon = mobileMenuBtn.querySelector('svg');
                    if (mobileMenu.classList.contains('active')) {
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
                    } else {
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>';
                    }
                });
                
                // Close mobile menu when clicking a link
                const mobileLinks = mobileMenu.querySelectorAll('a');
                mobileLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        mobileMenu.classList.remove('active');
                        const icon = mobileMenuBtn.querySelector('svg');
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>';
                    });
                });
            }
            
            // Sticky header effect
            const nav = document.getElementById('mainNav');
            let lastScrollY = window.scrollY;
            
            window.addEventListener('scroll', function() {
                const currentScrollY = window.scrollY;
                
                if (currentScrollY > 30) {
                    nav.classList.add('header-glass', 'shadow-2xl');
                } else {
                    nav.classList.remove('header-glass', 'shadow-2xl');
                }
                
                lastScrollY = currentScrollY;
            });
        });
        
        // Profile dropdown hover functions
        let dropdownTimeout;
        
        function showDropdown() {
            clearTimeout(dropdownTimeout);
            const dropdown = document.getElementById('profileDropdown');
            const icon = document.getElementById('dropdownIcon');
            
            dropdown.classList.remove('hidden');
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
        
        function hideDropdown() {
            dropdownTimeout = setTimeout(() => {
                const dropdown = document.getElementById('profileDropdown');
                const icon = document.getElementById('dropdownIcon');
                
                dropdown.classList.add('hidden');
                if (icon) icon.style.transform = 'rotate(0deg)';
            }, 200);
        }
    </script>
