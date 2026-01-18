<?php
/**
 * Physical Health Assessment Page
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
            'type' => 'physical',
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
                $recommendations[] = ['title' => 'Excellent Physical Health', 'details' => 'You\'re in great physical shape! Maintain your current exercise routine and healthy habits.', 'urgency' => 'low'];
                $recommendations[] = ['title' => 'Continue Regular Exercise', 'details' => 'Keep up your fitness routine with variety. Consider adding new activities to challenge yourself.', 'urgency' => 'low'];
            } elseif ($percentage >= 60) {
                $recommendations[] = ['title' => 'Good Physical Fitness', 'details' => 'Your physical health is on track. Increase exercise intensity gradually for better results.', 'urgency' => 'low'];
                $recommendations[] = ['title' => 'Strength Training', 'details' => 'Add 2-3 strength training sessions per week to build muscle and improve metabolism.', 'urgency' => 'medium'];
            } elseif ($percentage >= 40) {
                $recommendations[] = ['title' => 'Increase Physical Activity', 'details' => 'Aim for 30 minutes of moderate exercise 4-5 times per week. Start with walking or swimming.', 'urgency' => 'medium'];
                $recommendations[] = ['title' => 'Health Check Recommended', 'details' => 'Consider scheduling a check-up with your doctor to assess your physical health baseline.', 'urgency' => 'medium'];
            } else {
                $recommendations[] = ['title' => 'Immediate Action Required', 'details' => 'Your physical health needs attention. Consult a healthcare provider for a comprehensive evaluation.', 'urgency' => 'high'];
                $recommendations[] = ['title' => 'Start Light Exercise', 'details' => 'Begin with 10-15 minutes of gentle walking daily. Gradually increase duration as you build stamina.', 'urgency' => 'high'];
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
                        'type' => 'Physical',
                        'score' => $percentage,
                        'severity' => $severity,
                        'recommendations' => $recommendations,
                        'date' => date('F j, Y')
                    ]
                );
                
                if (!$emailResult['success']) {
                    error_log("Failed to send physical assessment email: " . $emailResult['message']);
                }
            } catch (Exception $e) {
                error_log("Physical assessment email error: " . $e->getMessage());
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

// Get available doctors for referrals
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

$pageTitle = 'Physical Health Assessment';
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Physical Health Assessment - Health Tracker</title>
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
    </style>
</head>
<body class="pt-20">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="glass-card rounded-2xl p-8 mb-8 animate-slide-up text-center">
            <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-r from-accent to-primary rounded-full flex items-center justify-center pulse-ring">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Physical Health Assessment</h1>
            <p class="text-gray-600">Answer honestly to receive personalized health recommendations</p>
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
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Ready to assess your physical health?</h2>
                    <p class="text-gray-600 mb-6">This assessment will evaluate your:</p>
                    <div class="grid md:grid-cols-2 gap-4 max-w-2xl mx-auto mb-8">
                        <div class="flex items-center gap-3 p-4 bg-green-50 rounded-xl">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span class="font-semibold text-gray-900">Energy Levels</span>
                        </div>
                        <div class="flex items-center gap-3 p-4 bg-blue-50 rounded-xl">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                            </svg>
                            <span class="font-semibold text-gray-900">Sleep Quality</span>
                        </div>
                        <div class="flex items-center gap-3 p-4 bg-yellow-50 rounded-xl">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="font-semibold text-gray-900">Activity & Fitness</span>
                        </div>
                        <div class="flex items-center gap-3 p-4 bg-red-50 rounded-xl">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span class="font-semibold text-gray-900">Nutrition & Health</span>
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
                question: "How would you describe your overall energy levels?",
                options: [
                    { text: "Excellent - Feel energized throughout the day", value: 5 },
                    { text: "Good - Generally energetic with occasional fatigue", value: 4 },
                    { text: "Moderate - Energy varies, often feel tired", value: 3 },
                    { text: "Low - Frequently feel tired and sluggish", value: 2 },
                    { text: "Very Low - Constant fatigue and exhaustion", value: 1 }
                ]
            },
            {
                id: 2,
                question: "How many hours of sleep do you get per night on average?",
                options: [
                    { text: "7-9 hours (optimal)", value: 5 },
                    { text: "6-7 hours (acceptable)", value: 4 },
                    { text: "5-6 hours (insufficient)", value: 3 },
                    { text: "4-5 hours (poor)", value: 2 },
                    { text: "Less than 4 hours (severely insufficient)", value: 1 }
                ]
            },
            {
                id: 3,
                question: "How often do you engage in physical exercise or activity?",
                options: [
                    { text: "Daily or almost daily", value: 5 },
                    { text: "3-4 times per week", value: 4 },
                    { text: "1-2 times per week", value: 3 },
                    { text: "Occasionally (few times a month)", value: 2 },
                    { text: "Rarely or never", value: 1 }
                ]
            },
            {
                id: 4,
                question: "How would you rate your diet and eating habits?",
                options: [
                    { text: "Excellent - Balanced, nutritious meals regularly", value: 5 },
                    { text: "Good - Mostly healthy with occasional treats", value: 4 },
                    { text: "Fair - Mix of healthy and unhealthy foods", value: 3 },
                    { text: "Poor - Often skip meals or eat unhealthy foods", value: 2 },
                    { text: "Very Poor - Irregular, mostly processed foods", value: 1 }
                ]
            },
            {
                id: 5,
                question: "Do you experience any chronic pain or discomfort?",
                options: [
                    { text: "None - No chronic pain", value: 5 },
                    { text: "Mild - Occasional minor aches", value: 4 },
                    { text: "Moderate - Regular discomfort that I manage", value: 3 },
                    { text: "Significant - Frequent pain affecting activities", value: 2 },
                    { text: "Severe - Constant pain limiting daily life", value: 1 }
                ]
            },
            {
                id: 6,
                question: "How would you rate your cardiovascular health?",
                options: [
                    { text: "Excellent - Very fit, no breathing issues", value: 5 },
                    { text: "Good - Generally healthy cardiovascular system", value: 4 },
                    { text: "Fair - Some shortness of breath with activity", value: 3 },
                    { text: "Poor - Frequent breathing difficulties", value: 2 },
                    { text: "Very Poor - Severe cardiovascular concerns", value: 1 }
                ]
            },
            {
                id: 7,
                question: "How much water do you drink daily?",
                options: [
                    { text: "8+ glasses (optimal hydration)", value: 5 },
                    { text: "5-7 glasses (good hydration)", value: 4 },
                    { text: "3-4 glasses (moderate)", value: 3 },
                    { text: "1-2 glasses (insufficient)", value: 2 },
                    { text: "Less than 1 glass (severely dehydrated)", value: 1 }
                ]
            },
            {
                id: 8,
                question: "How is your flexibility and mobility?",
                options: [
                    { text: "Very flexible - Can move freely without issues", value: 5 },
                    { text: "Good - Minor stiffness occasionally", value: 4 },
                    { text: "Moderate - Noticeable stiffness regularly", value: 3 },
                    { text: "Limited - Difficulty with certain movements", value: 2 },
                    { text: "Very Limited - Significant mobility restrictions", value: 1 }
                ]
            },
            {
                id: 9,
                question: "How would you rate your immune system?",
                options: [
                    { text: "Strong - Rarely get sick", value: 5 },
                    { text: "Good - Occasional mild illnesses", value: 4 },
                    { text: "Average - Get sick a few times a year", value: 3 },
                    { text: "Weak - Frequently catch infections", value: 2 },
                    { text: "Very Weak - Constantly fighting illnesses", value: 1 }
                ]
            },
            {
                id: 10,
                question: "Do you have any serious medical conditions or take regular medications?",
                options: [
                    { text: "None - No medical conditions or medications", value: 5 },
                    { text: "Minor - Well-controlled condition", value: 4 },
                    { text: "Moderate - Managing one or two conditions", value: 3 },
                    { text: "Significant - Multiple conditions requiring medication", value: 2 },
                    { text: "Serious - Complex medical issues", value: 1 }
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

        async function submitAssessment() {
            // Add loading effect
            const nextBtn = document.getElementById('nextBtn');
            const originalText = nextBtn.innerHTML;
            nextBtn.disabled = true;
            nextBtn.style.opacity = '0.7';
            nextBtn.style.cursor = 'not-allowed';
            nextBtn.innerHTML = '<svg class="animate-spin h-5 w-5 inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Submitting...';
            
            totalScore = Object.values(responses).reduce((a, b) => a + b, 0);
            const maxScore = 50; // Maximum possible score (10 questions × 5 points)
            
            const formData = new FormData();
            formData.append('action', 'submit_assessment');
            formData.append('responses', JSON.stringify(responses));
            formData.append('score', totalScore);
            formData.append('max_score', maxScore);
            
            try {
                const response = await fetch('physical_assessment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success || true) { // Show results even if save fails
                    showResults(result);
                } else {
                    alert('Error submitting assessment. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                // Show results anyway for user benefit
                showResults({
                    severity: totalScore < 10 ? 'critical' : totalScore < 20 ? 'poor' : totalScore < 30 ? 'fair' : totalScore < 40 ? 'good' : 'excellent',
                    score: totalScore,
                    max_score: maxScore,
                    percentage: ((totalScore / maxScore) * 100).toFixed(1)
                });
            }
        }

        function showResults(result) {
            const availableDoctors = <?php echo json_encode($availableDoctors); ?>;
            const severityConfig = {
                excellent: {
                    color: 'green',
                    icon: '🎉',
                    title: 'Excellent Physical Health!',
                    message: 'Your physical health is in great shape! Keep up the fantastic work.',
                    recommendations: [
                        'Continue your current healthy lifestyle',
                        'Maintain your exercise routine',
                        'Keep eating a balanced diet',
                        'Stay hydrated and get adequate sleep',
                        'Annual health checkups for preventive care'
                    ]
                },
                good: {
                    color: 'blue',
                    icon: '👍',
                    title: 'Good Physical Health',
                    message: 'You\'re doing well! A few adjustments can optimize your health further.',
                    recommendations: [
                        'Increase physical activity slightly',
                        'Focus on consistent sleep schedule',
                        'Add more fruits and vegetables to your diet',
                        'Stay consistent with hydration',
                        'Consider preventive health screenings'
                    ]
                },
                fair: {
                    color: 'yellow',
                    icon: '⚠️',
                    title: 'Fair Physical Health - Room for Improvement',
                    message: 'Your health needs some attention. Small changes can make a big difference.',
                    recommendations: [
                        'Start a regular exercise program (consult your doctor first)',
                        'Improve sleep quality and duration',
                        'Adopt a healthier diet plan',
                        'Increase water intake',
                        'Schedule a health checkup soon',
                        'Consider working with a nutritionist or trainer'
                    ]
                },
                poor: {
                    color: 'orange',
                    icon: '🚨',
                    title: 'Poor Physical Health - Urgent Action Needed',
                    message: 'Your health requires immediate attention. Please consult a healthcare professional.',
                    recommendations: [
                        'Schedule a comprehensive medical checkup immediately',
                        'Consult with a doctor about your symptoms',
                        'Start making gradual lifestyle changes',
                        'Get professional guidance for exercise and diet',
                        'Monitor your health conditions closely',
                        'Consider working with healthcare professionals'
                    ],
                    showDoctorButton: true
                },
                critical: {
                    color: 'red',
                    icon: '🆘',
                    title: 'Critical - Immediate Medical Attention Required',
                    message: 'Your health status requires urgent professional intervention. Please seek medical help immediately.',
                    recommendations: [
                        '⚠️ Contact a healthcare provider TODAY',
                        'Do not delay - schedule immediate medical evaluation',
                        'Avoid strenuous activities until cleared by a doctor',
                        'Keep a symptom journal for your doctor',
                        'Follow medical advice strictly',
                        'Reach out to healthcare professionals immediately'
                    ],
                    showDoctorButton: true
                }
            };

            const config = severityConfig[result.severity];
            
            // Remove old doctors HTML section
            let doctorsHTML = '';
            
            // Build action buttons HTML
            let actionButtonsHTML = '';
            if (config.showDoctorButton) {
                actionButtonsHTML = `
                    <div class="flex gap-3 mt-8">
                        <button onclick="location.reload()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                            Retake Assessment
                        </button>
                        <button onclick="showDoctorModal()" class="flex-1 px-6 py-3 bg-gradient-to-r from-yellow-500 to-orange-500 text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                            🩺 Reach a Doctor
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
            
            // Filter doctors with physical health related specializations
            const physicalDoctors = availableDoctors.filter(doctor => {
                const spec = doctor.specialization.toLowerCase();
                return spec.includes('physician') || 
                       spec.includes('physiotherapist') || 
                       spec.includes('cardio') || 
                       spec.includes('orthopedic') || 
                       spec.includes('sports') ||
                       spec.includes('physical') ||
                       spec.includes('general');
            });
            
            let doctorListHTML = '';
            
            if (physicalDoctors.length > 0) {
                doctorListHTML = physicalDoctors.map(doctor => `
                    <div class="doctor-card p-5 bg-white rounded-xl border-2 border-gray-200 hover:border-green-400 transition-all cursor-pointer transform hover:scale-105"
                         onclick="selectDoctor(${doctor.id}, '${doctor.name}')">
                        <div class="flex items-start gap-4">
                            <div class="w-16 h-16 bg-gradient-to-r from-accent to-primary rounded-full flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
                                ${doctor.name.charAt(0)}
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-900 text-lg">Dr. ${doctor.name}</h4>
                                <p class="text-sm text-gray-600 mb-1">
                                    <span class="inline-block px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                                        ${doctor.specialization}
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500">${doctor.qualification}</p>
                                <p class="text-xs text-gray-400 mt-2">📧 ${doctor.email}</p>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <p class="text-gray-600 font-semibold mb-2">No Physical Health Specialists Available</p>
                        <p class="text-gray-500 text-sm">Please check back later or contact support for assistance.</p>
                    </div>
                `;
            }
            
            const doctorModalHTML = `
                <div id="doctorModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
                    <div class="glass-card rounded-2xl p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto animate-slide-up">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-3xl font-bold text-gray-900 mb-2">🩺 Physical Health Specialists</h2>
                                <p class="text-gray-600">Select a doctor to start a conversation</p>
                            </div>
                            <button onclick="closeDoctorModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                <div class="text-sm text-blue-700">
                                    <p class="font-semibold mb-1">Connect with a Specialist</p>
                                    <p class="text-xs">Click on a doctor to open a direct messaging channel. You can discuss your assessment results and get personalized advice.</p>
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
