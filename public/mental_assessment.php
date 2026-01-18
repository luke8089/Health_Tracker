<?php
/**
 * Mental Health Assessment Page  
 * Interactive test with personalized recommendations
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/models/Assessment.php';
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../mail/Mailer.php';

$auth = new Auth();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
$userId = $currentUser['id'];

$assessmentModel = new Assessment();

// Handle assessment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'submit_assessment') {
        $responses = json_decode($_POST['responses'], true);
        $score = intval($_POST['score']);
        $maxScore = intval($_POST['max_score']);
        
        // Calculate percentage and ensure it doesn't exceed 100
        $percentage = min(100, round(($score / $maxScore) * 100));
        if ($percentage >= 80) {
            $severity = 'excellent';
        } elseif ($percentage >= 60) {
            $severity = 'good';
        } elseif ($percentage >= 40) {
            $severity = 'fair';
        } elseif ($percentage >= 20) {
            $severity = 'poor';
        } else {
            $severity = 'critical';
        }
        
        // Save assessment with percentage score
        $assessmentData = [
            'type' => 'mental',
            'answers' => $responses,
            'max_score' => $maxScore,
            'raw_score' => $score
        ];
        
        // Save with percentage as the score
        try {
            $db = new Database();
            $conn = $db->connect();
            $stmt = $conn->prepare("
                INSERT INTO assessments (user_id, type, responses, score, max_score, severity, created_at) 
                VALUES (?, 'comprehensive', ?, ?, 100, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                json_encode($assessmentData),
                $percentage,
                $severity
            ]);
            $assessmentId = $conn->lastInsertId();
            
            // Generate recommendations based on score
            $recommendations = [];
            if ($percentage >= 80) {
                $recommendations[] = ['title' => 'Excellent Mental Health', 'details' => 'Your mental wellness is outstanding! Continue your positive practices and mindfulness routines.', 'urgency' => 'low'];
                $recommendations[] = ['title' => 'Maintain Balance', 'details' => 'Keep nurturing your mental health through social connections, hobbies, and self-care activities.', 'urgency' => 'low'];
            } elseif ($percentage >= 60) {
                $recommendations[] = ['title' => 'Good Mental Wellness', 'details' => 'Your mental health is stable. Focus on stress management and maintaining work-life balance.', 'urgency' => 'low'];
                $recommendations[] = ['title' => 'Mindfulness Practice', 'details' => 'Try daily meditation or mindfulness exercises for 10-15 minutes to enhance emotional well-being.', 'urgency' => 'medium'];
            } elseif ($percentage >= 40) {
                $recommendations[] = ['title' => 'Mental Health Support', 'details' => 'Consider speaking with a mental health professional. Practice daily relaxation techniques.', 'urgency' => 'medium'];
                $recommendations[] = ['title' => 'Build Support Network', 'details' => 'Connect with friends, family, or support groups. Social connections are vital for mental health.', 'urgency' => 'medium'];
            } else {
                $recommendations[] = ['title' => 'Urgent Mental Health Support', 'details' => 'Your mental health needs immediate attention. Please reach out to a mental health professional or counselor.', 'urgency' => 'high'];
                $recommendations[] = ['title' => 'Crisis Resources', 'details' => 'If you\'re in crisis, contact a mental health hotline or emergency services. You\'re not alone, and help is available.', 'urgency' => 'high'];
            }
            
            // Send email with results
            try {
                $mailer = new Mailer();
                $emailResult = $mailer->sendAssessmentResultsEmail(
                    [
                        'name' => $currentUser['name'],
                        'email' => $currentUser['email']
                    ],
                    [
                        'type' => 'Mental',
                        'score' => $percentage,
                        'severity' => $severity,
                        'recommendations' => $recommendations,
                        'date' => date('F j, Y')
                    ]
                );
                
                if (!$emailResult['success']) {
                    error_log("Failed to send mental assessment email: " . $emailResult['message']);
                }
            } catch (Exception $e) {
                error_log("Mental assessment email error: " . $e->getMessage());
            }
            
            $result = true;
        } catch (Exception $e) {
            $result = false;
        }
        
        echo json_encode([
            'success' => $result,
            'severity' => $severity,
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => round($percentage, 1)
        ]);
        exit;
    }
}

// Get available doctors for referrals (prioritize mental health specialists)
try {
    $database = new Database();
    $db = $database->connect();
    $stmt = $db->query("
        SELECT u.id, u.name, u.email, d.specialty as specialization, d.license_number as qualification 
        FROM users u 
        JOIN doctors d ON u.id = d.id 
        WHERE u.role = 'doctor'
        ORDER BY u.name
        LIMIT 10
    ");
    $availableDoctors = $stmt->fetchAll();
} catch (Exception $e) {
    $availableDoctors = [];
}

$pageTitle = 'Mental Health Assessment';
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Health Assessment - Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1f2937',
                        'accent': '#34d399'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background: linear-gradient(135deg, #34d399 0%, #1f2937 100%);
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse-slow {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
        }
        
        .animate-slide-up {
            animation: slideInUp 0.6s ease-out;
        }
        
        .option-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        
        .option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .option-card.selected {
            background: linear-gradient(135deg, #34d399 0%, #1f2937 100%);
            color: white;
            transform: scale(1.05);
        }
        
        .progress-bar {
            transition: width 0.5s ease-out;
        }
        
        .pulse-ring {
            animation: pulse-slow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Modal scroll styling */
        #criticalWarningModal > div {
            scrollbar-width: thin;
            scrollbar-color: #34d399 #f1f1f1;
        }
        
        #criticalWarningModal > div::-webkit-scrollbar {
            width: 8px;
        }
        
        #criticalWarningModal > div::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        #criticalWarningModal > div::-webkit-scrollbar-thumb {
            background: #34d399;
            border-radius: 10px;
        }
        
        #criticalWarningModal > div::-webkit-scrollbar-thumb:hover {
            background: #1f2937;
        }
    </style>
</head>
<body class="pt-20">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="glass-card rounded-2xl p-8 mb-8 animate-slide-up text-center">
            <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-r from-accent to-primary rounded-full flex items-center justify-center pulse-ring">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Mental Health Assessment</h1>
            <p class="text-gray-600">Answer honestly to receive personalized mental wellness recommendations</p>
        </div>

        <!-- Progress Bar -->
        <div id="progressContainer" class="glass-card rounded-2xl p-6 mb-8 animate-slide-up hidden">
            <div class="flex justify-between mb-2">
                <span class="text-sm font-semibold text-gray-700">Progress</span>
                <span class="text-sm font-semibold text-gray-700"><span id="currentQuestion">1</span> of <span id="totalQuestions">10</span></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div id="progressBar" class="progress-bar h-3 rounded-full bg-gradient-to-r from-accent to-primary" style="width: 0%"></div>
            </div>
        </div>

        <!-- Assessment Form -->
        <div id="assessmentContainer" class="glass-card rounded-2xl p-8 animate-slide-up">
            <!-- Welcome Screen -->
            <div id="welcomeScreen">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Ready to assess your mental wellness?</h2>
                    <p class="text-gray-600 mb-6">This assessment will evaluate your:</p>
                    <div class="grid md:grid-cols-2 gap-4 max-w-2xl mx-auto mb-8">
                        <div class="flex items-center gap-3 p-4 bg-purple-50 rounded-xl">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="font-semibold text-gray-900">Mood & Emotions</span>
                        </div>
                        <div class="flex items-center gap-3 p-4 bg-blue-50 rounded-xl">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                            <span class="font-semibold text-gray-900">Social Connections</span>
                        </div>
                        <div class="flex items-center gap-3 p-4 bg-green-50 rounded-xl">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="font-semibold text-gray-900">Stress Management</span>
                        </div>
                        <div class="flex items-center gap-3 p-4 bg-yellow-50 rounded-xl">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <span class="font-semibold text-gray-900">Overall Well-being</span>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div class="text-sm text-blue-700">
                                <p class="font-semibold mb-1">Your privacy matters</p>
                                <p class="text-xs">All responses are confidential and used only to provide you with personalized recommendations.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <button onclick="startAssessment()" class="w-full px-8 py-4 bg-gradient-to-r from-accent to-primary text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    Start Assessment
                </button>
            </div>

            <!-- Questions Container -->
            <div id="questionsContainer" class="hidden"></div>

            <!-- Navigation Buttons -->
            <div id="navigationButtons" class="hidden flex justify-between mt-8">
                <button id="prevBtn" onclick="previousQuestion()" class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all duration-300 disabled:opacity-50" disabled>
                    Previous
                </button>
                <button id="nextBtn" onclick="nextQuestion()" class="px-6 py-3 bg-gradient-to-r from-accent to-primary text-white font-semibold rounded-xl hover:shadow-lg transition-all duration-300 disabled:opacity-50" disabled>
                    Next
                </button>
            </div>
        </div>

        <!-- Critical Warning Modal -->
        <div id="criticalWarningModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-70 overflow-y-auto">
            <div class="glass-card rounded-2xl p-8 max-w-2xl w-full my-8 animate-slide-up max-h-[95vh] overflow-y-auto">
                <div class="text-center mb-6">
                    <div class="w-20 h-20 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">We're Concerned About You</h2>
                    <p class="text-gray-700 mb-6 leading-relaxed">
                        Based on your responses, we believe you may be experiencing serious mental health concerns. 
                        <strong>Your safety and well-being are our top priority.</strong>
                    </p>
                </div>

                <div class="bg-red-50 border-2 border-red-200 rounded-xl p-6 mb-6">
                    <h3 class="text-lg font-bold text-red-900 mb-3">🆘 Immediate Support Resources</h3>
                    <div class="space-y-3">
                        <div class="bg-white p-4 rounded-lg">
                            <p class="font-semibold text-gray-900 mb-2">National Suicide Prevention Lifeline</p>
                            <a href="tel:988" class="block w-full px-6 py-3 bg-red-600 text-white rounded-lg text-center font-bold hover:bg-red-700 transition-all">
                                Call or Text: 988
                            </a>
                            <p class="text-xs text-gray-600 mt-2">Available 24/7 - Free and confidential support</p>
                        </div>
                        <div class="bg-white p-4 rounded-lg">
                            <p class="font-semibold text-gray-900 mb-2">Crisis Text Line</p>
                            <a href="sms:741741?&body=HELLO" class="block w-full px-6 py-3 bg-blue-600 text-white rounded-lg text-center font-bold hover:bg-blue-700 transition-all">
                                Text: HELLO to 741741
                            </a>
                            <p class="text-xs text-gray-600 mt-2">Text-based support available 24/7</p>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                    <p class="text-sm text-blue-900">
                        <strong>Would you like to connect with a mental health professional?</strong> 
                        We can show you available counselors and therapists who can provide immediate support.
                    </p>
                </div>

                <div class="flex gap-3">
                    <button onclick="closeCriticalWarning()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                        Not Now
                    </button>
                    <button onclick="proceedWithCriticalAssessment()" class="flex-1 px-6 py-3 bg-gradient-to-r from-accent to-primary text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                        Yes, Show Me Professionals
                    </button>
                </div>

                <p class="text-xs text-gray-500 text-center mt-4">
                    If you are in immediate danger, please call 911 or go to your nearest emergency room.
                </p>
            </div>
        </div>

        <!-- Results Modal -->
        <div id="resultsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="glass-card rounded-2xl p-8 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div id="resultsContent"></div>
            </div>
        </div>
    </div>

    <script>
        const questions = [
            {
                id: 1,
                question: "How often have you felt down, depressed, or hopeless in the past two weeks?",
                options: [
                    { text: "Not at all", value: 5 },
                    { text: "Several days", value: 4 },
                    { text: "More than half the days", value: 2 },
                    { text: "Nearly every day", value: 1 }
                ]
            },
            {
                id: 2,
                question: "How would you rate your stress levels?",
                options: [
                    { text: "Minimal stress, easily manageable", value: 5 },
                    { text: "Moderate stress, usually manageable", value: 4 },
                    { text: "High stress, often overwhelming", value: 2 },
                    { text: "Severe stress, constantly overwhelming", value: 1 }
                ]
            },
            {
                id: 3,
                question: "How is your sleep quality affected by your thoughts or worries?",
                options: [
                    { text: "Sleep well, no issues", value: 5 },
                    { text: "Occasionally have trouble falling asleep", value: 4 },
                    { text: "Frequently have trouble sleeping", value: 2 },
                    { text: "Severe insomnia or sleep disturbances", value: 1 }
                ]
            },
            {
                id: 4,
                question: "Do you feel connected to friends, family, or community?",
                options: [
                    { text: "Very connected, strong relationships", value: 5 },
                    { text: "Moderately connected", value: 4 },
                    { text: "Somewhat isolated", value: 2 },
                    { text: "Very isolated and alone", value: 1 }
                ]
            },
            {
                id: 5,
                question: "How often do you engage in activities you enjoy?",
                options: [
                    { text: "Regularly, almost daily", value: 5 },
                    { text: "Several times a week", value: 4 },
                    { text: "Rarely, once a week or less", value: 2 },
                    { text: "Never or almost never", value: 1 }
                ]
            },
            {
                id: 6,
                question: "Do you experience anxiety or excessive worry?",
                options: [
                    { text: "No anxiety, calm most of the time", value: 5 },
                    { text: "Mild anxiety occasionally", value: 4 },
                    { text: "Frequent anxiety that affects daily life", value: 2 },
                    { text: "Severe anxiety, panic attacks", value: 1 }
                ]
            },
            {
                id: 7,
                question: "How is your concentration and ability to focus?",
                options: [
                    { text: "Excellent focus and concentration", value: 5 },
                    { text: "Good, with minor lapses", value: 4 },
                    { text: "Poor focus, easily distracted", value: 2 },
                    { text: "Very poor, can't concentrate", value: 1 }
                ]
            },
            {
                id: 8,
                question: "Do you feel hopeful about your future?",
                options: [
                    { text: "Very hopeful and optimistic", value: 5 },
                    { text: "Generally hopeful", value: 4 },
                    { text: "Uncertain about the future", value: 2 },
                    { text: "Hopeless, pessimistic", value: 1 }
                ]
            },
            {
                id: 9,
                question: "How do you cope with challenges and setbacks?",
                options: [
                    { text: "Very resilient, bounce back quickly", value: 5 },
                    { text: "Usually cope well", value: 4 },
                    { text: "Struggle to cope, takes time to recover", value: 2 },
                    { text: "Can't cope, feel overwhelmed", value: 1 }
                ]
            },
            {
                id: 10,
                question: "Have you had thoughts of harming yourself or others?",
                options: [
                    { text: "Never", value: 5 },
                    { text: "Rarely, fleeting thoughts", value: 3 },
                    { text: "Sometimes", value: 1 },
                    { text: "Frequently", value: 0 }
                ]
            }
        ];

        let currentQuestionIndex = 0;
        let responses = {};
        let totalScore = 0;

        function startAssessment() {
            document.getElementById('welcomeScreen').classList.add('hidden');
            document.getElementById('progressContainer').classList.remove('hidden');
            document.getElementById('navigationButtons').classList.remove('hidden');
            document.getElementById('totalQuestions').textContent = questions.length;
            showQuestion(0);
        }

        function showQuestion(index) {
            currentQuestionIndex = index;
            const question = questions[index];
            const container = document.getElementById('questionsContainer');
            container.classList.remove('hidden');
            
            container.innerHTML = `
                <div class="animate-slide-up">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">${question.question}</h3>
                    <div class="space-y-3">
                        ${question.options.map((option, i) => `
                            <div class="option-card ${responses[question.id] === option.value ? 'selected' : ''} p-4 bg-white border-2 border-gray-200 rounded-xl"
                                 onclick="selectOption(${question.id}, ${option.value})">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 rounded-full border-2 ${responses[question.id] === option.value ? 'border-white bg-white' : 'border-gray-300'} flex items-center justify-center">
                                        ${responses[question.id] === option.value ? '<div class="w-3 h-3 rounded-full bg-green-400"></div>' : ''}
                                    </div>
                                    <span class="font-medium">${option.text}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            updateProgress();
            updateNavButtons();
        }

        function selectOption(questionId, value) {
            responses[questionId] = value;
            showQuestion(currentQuestionIndex);
        }

        function updateProgress() {
            const progress = ((currentQuestionIndex + 1) / questions.length) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
            document.getElementById('currentQuestion').textContent = currentQuestionIndex + 1;
        }

        function updateNavButtons() {
            document.getElementById('prevBtn').disabled = currentQuestionIndex === 0;
            const hasAnswer = responses[questions[currentQuestionIndex].id] !== undefined;
            document.getElementById('nextBtn').disabled = !hasAnswer;
            
            if (currentQuestionIndex === questions.length - 1 && hasAnswer) {
                document.getElementById('nextBtn').textContent = 'Submit Assessment';
                document.getElementById('nextBtn').onclick = submitAssessment;
            } else {
                document.getElementById('nextBtn').textContent = 'Next';
                document.getElementById('nextBtn').onclick = nextQuestion;
            }
        }

        function nextQuestion() {
            if (currentQuestionIndex < questions.length - 1) {
                showQuestion(currentQuestionIndex + 1);
            }
        }

        function previousQuestion() {
            if (currentQuestionIndex > 0) {
                showQuestion(currentQuestionIndex - 1);
            }
        }

        function showCriticalWarning() {
            document.getElementById('criticalWarningModal').classList.remove('hidden');
            document.getElementById('criticalWarningModal').classList.add('flex');
        }

        function closeCriticalWarning() {
            document.getElementById('criticalWarningModal').classList.add('hidden');
            document.getElementById('criticalWarningModal').classList.remove('flex');
        }

        function proceedWithCriticalAssessment() {
            closeCriticalWarning();
            totalScore = 0; // Force critical severity
            continueSubmission();
        }

        async function submitAssessment() {
            // Check for critical responses
            if (responses[10] === 0 || responses[10] === 1) {
                showCriticalWarning();
                return;
            } else {
                totalScore = Object.values(responses).reduce((a, b) => a + b, 0);
            }
            
            continueSubmission();
        }

        async function continueSubmission() {
            // Add loading effect
            const nextBtn = document.getElementById('nextBtn');
            const originalText = nextBtn.innerHTML;
            nextBtn.disabled = true;
            nextBtn.style.opacity = '0.7';
            nextBtn.style.cursor = 'not-allowed';
            nextBtn.innerHTML = '<svg class="animate-spin h-5 w-5 inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Submitting...';
            
            const maxScore = 47; // Maximum possible score
            
            const formData = new FormData();
            formData.append('action', 'submit_assessment');
            formData.append('responses', JSON.stringify(responses));
            formData.append('score', totalScore);
            formData.append('max_score', maxScore);
            
            try {
                const response = await fetch('mental_assessment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success || true) {
                    showResults(result);
                } else {
                    alert('Error submitting assessment. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                showResults({
                    severity: totalScore < 10 ? 'critical' : totalScore < 20 ? 'poor' : totalScore < 30 ? 'fair' : totalScore < 40 ? 'good' : 'excellent',
                    score: totalScore,
                    max_score: 47,
                    percentage: ((totalScore / 47) * 100).toFixed(1)
                });
            }
        }

        function showResults(result) {
            const availableDoctors = <?php echo json_encode($availableDoctors); ?>;
            const severityConfig = {
                excellent: {
                    color: 'green',
                    icon: '😊',
                    title: 'Excellent Mental Health!',
                    message: 'Your mental well-being is in great shape! Keep nurturing your mind.',
                    recommendations: [
                        'Continue your current self-care practices',
                        'Maintain your social connections',
                        'Keep engaging in activities you enjoy',
                        'Practice gratitude daily',
                        'Consider mindfulness or meditation to enhance well-being'
                    ]
                },
                good: {
                    color: 'blue',
                    icon: '🙂',
                    title: 'Good Mental Health',
                    message: 'You\'re doing well mentally. A few adjustments can enhance your wellness.',
                    recommendations: [
                        'Establish a consistent sleep routine',
                        'Increase social activities',
                        'Try stress-reduction techniques like deep breathing',
                        'Set aside time for hobbies and relaxation',
                        'Consider journaling to process emotions'
                    ]
                },
                fair: {
                    color: 'yellow',
                    icon: '😐',
                    title: 'Fair Mental Health - Needs Attention',
                    message: 'Your mental health needs some care. Small changes can help significantly.',
                    recommendations: [
                        'Talk to someone you trust about how you\'re feeling',
                        'Establish a daily routine for stability',
                        'Limit stress triggers where possible',
                        'Practice self-compassion and positive self-talk',
                        'Consider seeking counseling or therapy',
                        'Engage in physical activity to boost mood'
                    ]
                },
                poor: {
                    color: 'orange',
                    icon: '😔',
                    title: 'Poor Mental Health - Professional Help Recommended',
                    message: 'Your responses indicate you\'re struggling. Please reach out for professional support.',
                    recommendations: [
                        'Contact a mental health professional immediately',
                        'Reach out to trusted friends or family',
                        'Avoid isolation - maintain social connections',
                        'Consider joining a support group',
                        'Practice daily self-care, even small acts',
                        'Avoid alcohol and substances',
                        'Create a safety plan if needed'
                    ],
                    showDoctorButton: true
                },
                critical: {
                    color: 'red',
                    icon: '🆘',
                    title: 'Critical - Immediate Help Required',
                    message: 'Your safety is our priority. Please seek immediate professional mental health support.',
                    recommendations: [
                        '🚨 If in immediate danger, call emergency services (911)',
                        'Contact a crisis helpline: 988 (Suicide & Crisis Lifeline)',
                        'Reach out to a mental health professional immediately',
                        'Tell someone you trust about how you\'re feeling',
                        'Do not stay alone - seek company of trusted individuals',
                        'Remove access to means of self-harm',
                        'Go to the nearest emergency room if needed'
                    ],
                    showDoctorButton: true,
                    crisis: true
                }
            };

            const config = severityConfig[result.severity];
            
            // Build action buttons HTML
            let actionButtonsHTML = '';
            if (config.showDoctorButton) {
                actionButtonsHTML = `
                    <div class="flex gap-3 mt-8">
                        <button onclick="location.reload()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                            Retake Assessment
                        </button>
                        <button onclick="showDoctorModal()" class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-500 text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                            🧠 Reach a Therapist
                        </button>
                        <button onclick="window.location.href='dashboard.php'" class="flex-1 px-6 py-3 bg-gradient-to-r from-accent to-primary text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                            Go to Dashboard
                        </button>
                    </div>
                `;
            } else {
                actionButtonsHTML = `
                    <div class="flex gap-3 mt-8">
                        <button onclick="location.reload()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                            Retake Assessment
                        </button>
                        <button onclick="window.location.href='dashboard.php'" class="flex-1 px-6 py-3 bg-gradient-to-r from-accent to-primary text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                            Go to Dashboard
                        </button>
                    </div>
                `;
            }
            
            // Remove old doctors HTML section
            let doctorsHTML = '';
            
            let crisisHTML = '';
            if (config.crisis) {
                crisisHTML = `
                    <div class="mb-6 p-6 bg-red-50 border-2 border-red-300 rounded-xl">
                        <h4 class="text-xl font-bold text-red-900 mb-4">🆘 Crisis Resources</h4>
                        <div class="space-y-3">
                            <div class="bg-white p-4 rounded-lg">
                                <p class="font-semibold text-red-900 mb-2">National Suicide Prevention Lifeline:</p>
                                <a href="tel:988" class="block px-6 py-3 bg-red-600 text-white rounded-lg text-center font-bold hover:bg-red-700 transition-all">
                                    Call or Text 988
                                </a>
                                <p class="text-xs text-red-700 mt-2">Available 24/7 - Free and confidential support</p>
                            </div>
                            <div class="bg-white p-4 rounded-lg">
                                <p class="font-semibold text-red-900 mb-2">Crisis Text Line:</p>
                                <a href="sms:741741?&body=HELLO" class="block px-6 py-3 bg-blue-600 text-white rounded-lg text-center font-bold hover:bg-blue-700 transition-all">
                                    Text HELLO to 741741
                                </a>
                                <p class="text-xs text-red-700 mt-2">Text-based support available 24/7</p>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('resultsContent').innerHTML = `
                <div class="text-center mb-6">
                    <div class="text-6xl mb-4">${config.icon}</div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">${config.title}</h2>
                    <p class="text-gray-600 mb-4">${config.message}</p>
                    <div class="inline-block px-6 py-3 bg-${config.color}-100 rounded-full">
                        <span class="text-2xl font-bold text-${config.color}-700">${result.score}/${result.max_score}</span>
                        <span class="text-sm text-${config.color}-600 ml-2">(${result.percentage}%)</span>
                    </div>
                </div>

                ${crisisHTML}

                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">📋 Recommendations</h3>
                    <ul class="space-y-2">
                        ${config.recommendations.map(rec => `
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-${config.color}-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-700">${rec}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>

                ${actionButtonsHTML}
            `;
            
            document.getElementById('resultsModal').classList.remove('hidden');
            document.getElementById('resultsModal').classList.add('flex');
        }

        function showDoctorModal() {
            const availableDoctors = <?php echo json_encode($availableDoctors); ?>;
            
            // Filter doctors with mental health related specializations
            const mentalHealthDoctors = availableDoctors.filter(doctor => {
                const spec = doctor.specialization.toLowerCase();
                return spec.includes('psychiat') || 
                       spec.includes('psycholog') || 
                       spec.includes('therapist') || 
                       spec.includes('counsel') || 
                       spec.includes('mental') ||
                       spec.includes('behavioral') ||
                       spec.includes('physician');
            });
            
            let doctorListHTML = '';
            
            if (mentalHealthDoctors.length > 0) {
                doctorListHTML = mentalHealthDoctors.map(doctor => `
                    <div class="doctor-card p-5 bg-white rounded-xl border-2 border-gray-200 hover:border-purple-400 transition-all cursor-pointer transform hover:scale-105"
                         onclick="selectDoctor(${doctor.id}, '${doctor.name}')">
                        <div class="flex items-start gap-4">
                            <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
                                ${doctor.name.charAt(0)}
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-900 text-lg">Dr. ${doctor.name}</h4>
                                <p class="text-sm text-gray-600 mb-1">
                                    <span class="inline-block px-2 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">
                                        ${doctor.specialization}
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500">${doctor.qualification}</p>
                                <p class="text-xs text-gray-400 mt-2">📧 ${doctor.email}</p>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                doctorListHTML = `
                    <div class="text-center py-8">
                        <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-gray-600 font-semibold mb-2">No Mental Health Specialists Available</p>
                        <p class="text-gray-500 text-sm">Please check back later or contact support for assistance.</p>
                    </div>
                `;
            }
            
            const doctorModalHTML = `
                <div id="doctorModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
                    <div class="glass-card rounded-2xl p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto animate-slide-up">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-3xl font-bold text-gray-900 mb-2">🧠 Mental Health Specialists</h2>
                                <p class="text-gray-600">Select a therapist to start a confidential conversation</p>
                            </div>
                            <button onclick="closeDoctorModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mb-6 p-4 bg-purple-50 border border-purple-200 rounded-xl">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                <div class="text-sm text-purple-700">
                                    <p class="font-semibold mb-1">Confidential Support</p>
                                    <p class="text-xs">Your conversations are private and secure. Click on a specialist to open a direct messaging channel for personalized mental health support.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                <div class="text-sm text-blue-700">
                                    <p class="font-semibold mb-1">Crisis Resources Available 24/7</p>
                                    <p class="text-xs">If you're in crisis: Call 988 (Suicide & Crisis Lifeline) or text HELLO to 741741</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            ${doctorListHTML}
                        </div>
                        
                        <div class="mt-6 text-center">
                            <button onclick="closeDoctorModal()" class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', doctorModalHTML);
        }
        
        function closeDoctorModal() {
            const modal = document.getElementById('doctorModal');
            if (modal) {
                modal.remove();
            }
        }
        
        function selectDoctor(doctorId, doctorName) {
            // Redirect to messages page with doctor parameter
            window.location.href = `messages.php?doctor=${doctorId}`;
        }
    </script>
</body>
</html>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
