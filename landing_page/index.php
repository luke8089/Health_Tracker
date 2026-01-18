<?php
// Clear any stale session data when landing on this page
// This ensures users with cleared cache start fresh
session_start();

// If user is logged in, keep the session
// Otherwise, ensure clean state
if (!isset($_SESSION['user_id'])) {
    // Clear any partial session data
    $_SESSION = array();
}

// Determine base path dynamically (landing page doesn't load Bootstrap)
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = '';
if (strpos($scriptName, '/landing_page/') !== false) {
    $basePath = substr($scriptName, 0, strpos($scriptName, '/landing_page/'));
}

// Define as constant for consistency
if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', $basePath);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <title>Health Tracker - Your Wellness Journey</title>
  <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.0/css/all.min.css'>
  <link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/landing_page/style.css">

</head>

<body>

  <div class="mouse-effect">
    <div class="circle">
    </div>
    <div class="circle-follow">
    </div>
  </div>

  <header class="hide-text">
    <div class="header-inner">
      <a href="#" class="navbar-brand">   
        Health Tracker
      </a>
      
      <!-- Hamburger Menu Button -->
      <button class="hamburger" id="hamburger" aria-label="Toggle navigation">
        <span></span>
        <span></span>
        <span></span>
      </button>
      
      <nav id="nav-menu">
        <ul>
         
          <li><a href="#" onclick="checkLoginStatus('features'); return false;">Features</a></li>
          <li><a href="#" onclick="checkLoginStatus('about'); return false;">About</a></li>
          <li><a href="#" onclick="checkLoginStatus('contact'); return false;">Contact</a></li>
          <li><a href="<?php echo APP_BASE_PATH; ?>/public/login.php">Login</a></li>
          <li><a href="<?php echo APP_BASE_PATH; ?>/public/register.php">Sign Up</a></li>
        </ul>
      </nav>
     
    </div>
  </header>

  <h1 class="main-txt">Health Tracker</h1>

  <section class="banner hide-text">
    <div class="banner-inner">
      <div class="top-desc">
        <h5>Personal Health Management</h5>
        <h6>Track & Monitor Your Wellness</h6>
        <span></span>
      </div>
      <div class="bottom-desc">
        <div class="left-desc">
          <h1><i class="fa-solid fa-chart-line"></i></h1>
          <div class="desc-inner">
            <h5>Smart Analytics</h5>
            <h6>AI-Powered Health Insights</h6>
          </div>
        </div>
        <div class="middle-desc">
          <h2>Your Health Journey</h2>
        </div>
        <div class="right-desc">
          <h1><i class="fa-solid fa-heartbeat"></i></h1>
          <div class="desc-inner">
            <span>Health Community</span>
            <ul>
              <li>
                <a href="#">
                  <i class="fa-solid fa-stethoscope"></i>
                </a>
              </li>
              <li>
                <a href="#">
                  <i class="fa-solid fa-user-md"></i>
                </a>
              </li>
              <li>
                <a href="#">
                  <i class="fa-solid fa-pills"></i>
                </a>
              </li>
              <li>
                <a href="#">
                  <i class="fa-solid fa-heart"></i>
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <span class="rotated-text hide-text">Track your wellness journey</span>

  <canvas class="webgl" id="webgl"></canvas>
</body>
<script>
  // Make base path available to JavaScript
  window.APP_BASE_PATH = '<?php echo APP_BASE_PATH; ?>';
</script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/three@0.132.2/build/three.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/three@0.132.2/examples/js/loaders/RGBELoader.js'></script>
<script src='https://cdn.jsdelivr.net/npm/three@0.132.2/examples/js/controls/OrbitControls.js'></script>
<script src="<?php echo APP_BASE_PATH; ?>/landing_page/script.js"></script>

</body>

</html>