<?php
/**
 * About Us Page
 * Modern page with smooth animations showcasing the Health Tracker platform
 */

$pageTitle = 'About Us - Health Tracker';
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1C2529',
                        'accent': '#A1D1B1'
                    }
                }
            }
        }
    </script>
    <style>
        /* Smooth Scroll */
        html {
            scroll-behavior: smooth;
        }
        
        /* Gradient Background Animation */
        @keyframes gradientShift {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #A1D1B1 0%, #1C2529 50%, #A1D1B1 100%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
        }
        
        /* Glassmorphism Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(161, 209, 177, 0.2);
            box-shadow: 0 8px 32px rgba(28, 37, 41, 0.1);
        }
        
        /* Fade In Up Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
            opacity: 0;
        }
        
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        .delay-500 { animation-delay: 0.5s; }
        .delay-600 { animation-delay: 0.6s; }
        .delay-700 { animation-delay: 0.7s; }
        .delay-800 { animation-delay: 0.8s; }
        
        /* Float Animation */
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        /* Pulse Glow Animation */
        @keyframes pulseGlow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(161, 209, 177, 0.3);
            }
            50% {
                box-shadow: 0 0 40px rgba(161, 209, 177, 0.6), 0 0 60px rgba(28, 37, 41, 0.3);
            }
        }
        
        .pulse-glow {
            animation: pulseGlow 3s ease-in-out infinite;
        }
        
        /* Scale In Animation */
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .scale-in {
            animation: scaleIn 0.6s ease-out forwards;
            opacity: 0;
        }
        
        /* Slide In From Left */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .slide-in-left {
            animation: slideInLeft 0.8s ease-out forwards;
            opacity: 0;
        }
        
        /* Slide In From Right */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .slide-in-right {
            animation: slideInRight 0.8s ease-out forwards;
            opacity: 0;
        }
        
        /* Smooth Hover Effects */
        .hover-lift {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(28, 37, 41, 0.15);
        }
        
        /* Icon Animations */
        .icon-bounce {
            transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .hover-lift:hover .icon-bounce {
            transform: translateY(-5px) scale(1.1);
        }
        
        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Rotating Border Animation */
        @keyframes rotateBorder {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        .rotating-border::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: inherit;
            background: linear-gradient(135deg, #A1D1B1, #1C2529, #A1D1B1);
            background-size: 200% 200%;
            animation: rotateBorder 4s linear infinite, gradientShift 3s ease infinite;
            z-index: -1;
        }
        
        .rotating-border {
            position: relative;
            z-index: 1;
        }
        
        /* Number Counter Animation */
        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .count-up {
            animation: countUp 1s ease-out forwards;
        }
        
        /* Smooth Line Draw */
        @keyframes drawLine {
            from {
                width: 0;
            }
            to {
                width: 100%;
            }
        }
        
        .draw-line {
            animation: drawLine 1.5s ease-out forwards;
        }
        
        /* Background Pattern */
        .pattern-bg {
            background-image: radial-gradient(circle at 2px 2px, rgba(161, 209, 177, 0.1) 1px, transparent 0);
            background-size: 40px 40px;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">

    <!-- Hero Section -->
    <section class="relative py-20 overflow-hidden gradient-bg pattern-bg">
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-white/30 to-white"></div>
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-6xl md:text-7xl font-bold text-white mb-6 fade-in-up">
                    About <span class="text-green-400">Health Tracker</span>
                </h1>
                <p class="text-xl text-white/90 mb-8 fade-in-up delay-200">
                    Empowering individuals to take control of their health journey through innovative technology and personalized care
                </p>
                <div class="flex justify-center gap-4 fade-in-up delay-400">
                    <a href="#mission" class="bg-white text-primary font-semibold px-8 py-4 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 hover:-translate-y-1">
                        Our Mission
                    </a>
                    <a href="#team" class="bg-green-400 text-white font-semibold px-8 py-4 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 hover:-translate-y-1">
                        Meet the Team
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Floating Icons -->
        <div class="absolute top-20 left-10 opacity-20 float-animation">
            <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </div>
        <div class="absolute bottom-20 right-10 opacity-20 float-animation" style="animation-delay: 1s;">
            <svg class="w-20 h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </section>

    <!-- Mission Section -->
    <section id="mission" class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-5xl font-bold gradient-text mb-4 fade-in-up">Our Mission</h2>
                    <div class="w-24 h-1 bg-gradient-to-r from-green-400 to-gray-800 mx-auto rounded-full draw-line"></div>
                </div>
                
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div class="slide-in-left delay-200">
                        <div class="glass-card rounded-3xl p-8 pulse-glow">
                            <svg class="w-16 h-16 text-green-400 mb-6 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <h3 class="text-3xl font-bold text-primary mb-4">Vision</h3>
                            <p class="text-gray-600 leading-relaxed text-lg">
                                To create a world where everyone has access to personalized health insights and professional medical guidance, making preventive healthcare accessible and engaging for all.
                            </p>
                        </div>
                    </div>
                    
                    <div class="slide-in-right delay-400">
                        <div class="glass-card rounded-3xl p-8 pulse-glow">
                            <svg class="w-16 h-16 text-green-400 mb-6 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            <h3 class="text-3xl font-bold text-primary mb-4">Values</h3>
                            <p class="text-gray-600 leading-relaxed text-lg">
                                We believe in transparency, evidence-based care, user privacy, and continuous improvement. Your health data is yours, and we're here to help you understand it better.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-20 bg-gradient-to-br from-green-400/10 to-gray-800/10">
        <div class="container mx-auto px-4">
            <div class="max-w-6xl mx-auto">
                <div class="grid md:grid-cols-4 gap-8">
                    <div class="text-center glass-card rounded-3xl p-8 hover-lift fade-in-up delay-100">
                        <div class="text-5xl font-bold gradient-text mb-2 count-up">1000+</div>
                        <p class="text-gray-600 font-semibold">Active Users</p>
                    </div>
                    <div class="text-center glass-card rounded-3xl p-8 hover-lift fade-in-up delay-200">
                        <div class="text-5xl font-bold gradient-text mb-2 count-up">50+</div>
                        <p class="text-gray-600 font-semibold">Healthcare Professionals</p>
                    </div>
                    <div class="text-center glass-card rounded-3xl p-8 hover-lift fade-in-up delay-300">
                        <div class="text-5xl font-bold gradient-text mb-2 count-up">10K+</div>
                        <p class="text-gray-600 font-semibold">Assessments Completed</p>
                    </div>
                    <div class="text-center glass-card rounded-3xl p-8 hover-lift fade-in-up delay-400">
                        <div class="text-5xl font-bold gradient-text mb-2 count-up">24/7</div>
                        <p class="text-gray-600 font-semibold">Support Available</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-5xl font-bold gradient-text mb-4 fade-in-up">What We Offer</h2>
                    <p class="text-xl text-gray-600 fade-in-up delay-200">Comprehensive health tracking tools designed for you</p>
                </div>
                
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="glass-card rounded-3xl p-8 hover-lift fade-in-up delay-100">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-gray-800 rounded-2xl flex items-center justify-center mb-6 pulse-glow">
                            <svg class="w-8 h-8 text-white icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-primary mb-4">Health Assessments</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Comprehensive physical and mental health assessments with personalized recommendations and doctor referrals.
                        </p>
                    </div>
                    
                    <!-- Feature 2 -->
                    <div class="glass-card rounded-3xl p-8 hover-lift fade-in-up delay-200">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-gray-800 rounded-2xl flex items-center justify-center mb-6 pulse-glow">
                            <svg class="w-8 h-8 text-white icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-primary mb-4">Habit Tracking</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Build and maintain healthy habits with proof-of-completion system, streaks, and gamification features.
                        </p>
                    </div>
                    
                    <!-- Feature 3 -->
                    <div class="glass-card rounded-3xl p-8 hover-lift fade-in-up delay-300">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-gray-800 rounded-2xl flex items-center justify-center mb-6 pulse-glow">
                            <svg class="w-8 h-8 text-white icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-primary mb-4">Real-Time Messaging</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Direct communication with healthcare professionals through our secure, real-time messaging platform.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section id="team" class="py-20 bg-gradient-to-br from-gray-50 to-gray-100 pattern-bg">
        <div class="container mx-auto px-4">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-5xl font-bold gradient-text mb-4 fade-in-up">Meet Our Team</h2>
                    <p class="text-xl text-gray-600 fade-in-up delay-200">Passionate professionals dedicated to your health</p>
                </div>
                
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Team Member 1 -->
                    <div class="glass-card rounded-3xl p-8 text-center hover-lift fade-in-up delay-100">
                        <div class="w-32 h-32 mx-auto mb-6 rounded-full bg-gradient-to-br from-green-400 to-gray-800 flex items-center justify-center text-white text-4xl font-bold pulse-glow rotating-border">
                            JD
                        </div>
                        <h3 class="text-2xl font-bold text-primary mb-2">Dr. Jane Doe</h3>
                        <p class="text-green-400 font-semibold mb-4">Chief Medical Officer</p>
                        <p class="text-gray-600 leading-relaxed">
                            15+ years of experience in preventive healthcare and digital health innovation.
                        </p>
                    </div>
                    
                    <!-- Team Member 2 -->
                    <div class="glass-card rounded-3xl p-8 text-center hover-lift fade-in-up delay-200">
                        <div class="w-32 h-32 mx-auto mb-6 rounded-full bg-gradient-to-br from-green-400 to-gray-800 flex items-center justify-center text-white text-4xl font-bold pulse-glow rotating-border">
                            MS
                        </div>
                        <h3 class="text-2xl font-bold text-primary mb-2">Michael Smith</h3>
                        <p class="text-green-400 font-semibold mb-4">Technology Director</p>
                        <p class="text-gray-600 leading-relaxed">
                            Expert in healthcare technology with a passion for user-centered design.
                        </p>
                    </div>
                    
                    <!-- Team Member 3 -->
                    <div class="glass-card rounded-3xl p-8 text-center hover-lift fade-in-up delay-300">
                        <div class="w-32 h-32 mx-auto mb-6 rounded-full bg-gradient-to-br from-green-400 to-gray-800 flex items-center justify-center text-white text-4xl font-bold pulse-glow rotating-border">
                            SJ
                        </div>
                        <h3 class="text-2xl font-bold text-primary mb-2">Sarah Johnson</h3>
                        <p class="text-green-400 font-semibold mb-4">Patient Experience Lead</p>
                        <p class="text-gray-600 leading-relaxed">
                            Dedicated to ensuring every user has a seamless and supportive experience.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact CTA Section -->
    <section class="py-20 bg-gradient-to-r from-primary to-accent text-white">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-5xl font-bold mb-6 fade-in-up">Ready to Start Your Health Journey?</h2>
                <p class="text-xl mb-8 text-white/90 fade-in-up delay-200">
                    Join thousands of users who are taking control of their health with our innovative platform
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4 fade-in-up delay-400">
                    <?php if (!$auth->isLoggedIn()): ?>
                        <a href="register.php" class="bg-white text-primary font-semibold px-10 py-4 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 hover:-translate-y-1">
                            Get Started Free
                        </a>
                        <a href="login.php" class="bg-green-400/20 backdrop-blur-xl text-white border-2 border-white/30 font-semibold px-10 py-4 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 hover:-translate-y-1">
                            Sign In
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="bg-white text-primary font-semibold px-10 py-4 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 hover:-translate-y-1">
                            Go to Dashboard
                        </a>
                        <a href="assessment.php" class="bg-green-400/20 backdrop-blur-xl text-white border-2 border-white/30 font-semibold px-10 py-4 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 hover:-translate-y-1">
                            Take Assessment
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Intersection Observer for scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.addEventListener('DOMContentLoaded', () => {
            const animatedElements = document.querySelectorAll('.fade-in-up, .slide-in-left, .slide-in-right, .scale-in, .count-up');
            animatedElements.forEach(el => {
                el.style.animationPlayState = 'paused';
                observer.observe(el);
            });
            
            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        // Parallax effect for floating icons
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const floatingIcons = document.querySelectorAll('.float-animation');
            floatingIcons.forEach((icon, index) => {
                const speed = 0.5 + (index * 0.2);
                icon.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    </script>

</body>
</html>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
