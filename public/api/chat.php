<?php
/**
 * AI Chatbot API Endpoint
 * Handles chat requests and integrates with free AI service
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message']) || empty(trim($input['message']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit();
}

$userMessage = trim($input['message']);
$conversationHistory = $input['history'] ?? [];

// AI Service Configuration
// Using Hugging Face's free Inference API
$aiConfig = [
    'provider' => 'huggingface', // Options: huggingface, cohere, together
    'model' => Config::AI_MODEL ?? 'microsoft/DialoGPT-medium',
    'api_url' => 'https://api-inference.huggingface.co/models/' . (Config::AI_MODEL ?? 'microsoft/DialoGPT-medium'),
    'api_key' => Config::HUGGINGFACE_API_KEY ?? '', // Optional, but recommended for better rate limits
    'fallback' => Config::AI_FALLBACK_ENABLED ?? true // Use rule-based fallback if API fails
];

/**
 * Call Hugging Face API
 */
function callHuggingFaceAPI($message, $config) {
    $url = $config['api_url'];
    
    $data = [
        'inputs' => $message,
        'parameters' => [
            'max_length' => 200,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'do_sample' => true
        ]
    ];
    
    $headers = [
        'Content-Type: application/json',
    ];
    
    if (!empty($config['api_key'])) {
        $headers[] = 'Authorization: Bearer ' . $config['api_key'];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Hugging Face API Error: " . $error);
        return null;
    }
    
    if ($httpCode !== 200) {
        error_log("Hugging Face API HTTP Code: " . $httpCode . " Response: " . $response);
        return null;
    }
    
    $result = json_decode($response, true);
    
    if (isset($result[0]['generated_text'])) {
        return $result[0]['generated_text'];
    }
    
    return null;
}

/**
 * Fallback rule-based responses
 */
function getRuleBasedResponse($message) {
    $message = strtolower($message);
    
    // Health-related queries
    $healthPatterns = [
        '/\b(hello|hi|hey|greetings)\b/i' => "Hello! 👋 I'm your Health Assistant. I can help you with health tips, wellness advice, and information about using this platform. What would you like to know?",
        
        '/\b(how are you|how do you do)\b/i' => "I'm doing great, thank you for asking! 😊 I'm here to help you with health and wellness questions. How can I assist you today?",
        
        '/\b(exercise|workout|fitness|gym)\b/i' => "Regular exercise is crucial for maintaining good health! 💪\n\nHere are some tips:\n• Aim for at least 150 minutes of moderate activity per week\n• Include both cardio and strength training\n• Start slow and gradually increase intensity\n• Stay consistent with your routine\n• Don't forget to warm up and cool down\n\nWould you like specific exercise recommendations?",
        
        '/\b(diet|nutrition|food|eating|meal)\b/i' => "Nutrition is fundamental to good health! 🥗\n\nKey principles:\n• Eat a variety of colorful fruits and vegetables\n• Choose whole grains over refined grains\n• Include lean proteins (fish, poultry, legumes)\n• Limit processed foods and added sugars\n• Stay hydrated with water\n• Practice portion control\n\nWould you like specific dietary advice?",
        
        '/\b(sleep|rest|insomnia|tired)\b/i' => "Good sleep is essential for health! 😴\n\nSleep hygiene tips:\n• Aim for 7-9 hours per night\n• Maintain a consistent sleep schedule\n• Create a relaxing bedtime routine\n• Keep your bedroom cool, dark, and quiet\n• Avoid screens 1 hour before bed\n• Limit caffeine after 2 PM\n\nAre you experiencing specific sleep issues?",
        
        '/\b(stress|anxiety|worried|mental health|depression)\b/i' => "Mental health is just as important as physical health! 🧠💚\n\nStress management strategies:\n• Practice deep breathing or meditation\n• Exercise regularly\n• Connect with friends and family\n• Set realistic goals and priorities\n• Take breaks when needed\n• Consider professional help if needed\n\nRemember: It's okay to ask for help. Would you like more information?",
        
        '/\b(water|hydration|drink)\b/i' => "Staying hydrated is vital! 💧\n\nHydration tips:\n• Drink 8-10 glasses of water daily\n• Drink more during exercise or hot weather\n• Start your day with water\n• Carry a reusable water bottle\n• Eat water-rich foods (fruits, vegetables)\n\nSigns of dehydration: dark urine, dry mouth, fatigue, dizziness",
        
        '/\b(weight|lose weight|gain weight|obesity)\b/i' => "Healthy weight management is a journey! ⚖️\n\nKey principles:\n• Focus on sustainable lifestyle changes\n• Eat balanced, nutritious meals\n• Exercise regularly (cardio + strength)\n• Get adequate sleep\n• Manage stress\n• Track your progress\n• Be patient and consistent\n\nWould you like personalized guidance?",
        
        '/\b(habit|habits|tracking|track)\b/i' => "Great question about habits! 📊\n\nOur platform helps you:\n• Create and track daily habits\n• Submit proof of completion\n• Earn points and rewards\n• Get verified by healthcare professionals\n• Stay motivated with streaks\n\nYou can manage your habits from the dashboard. Need help getting started?",
        
        '/\b(assessment|test|evaluate|check)\b/i' => "Health assessments are important! 📋\n\nOur platform offers:\n• Comprehensive health assessments\n• Mental health evaluations\n• Physical fitness assessments\n• Personalized recommendations\n• Doctor reviews and guidance\n\nTake an assessment from your dashboard to get started!",
        
        '/\b(doctor|physician|medical|healthcare provider)\b/i' => "Connecting with healthcare professionals! 👨‍⚕️👩‍⚕️\n\nYou can:\n• Connect with verified doctors\n• Get personalized recommendations\n• Receive professional guidance\n• Share assessment results\n• Communicate through messages\n\nVisit the 'Connect with Doctors' section to get started!",
        
        '/\b(help|how to|guide|tutorial)\b/i' => "I'm here to help! 🤝\n\nI can assist with:\n• Health and wellness tips\n• Exercise and nutrition advice\n• Platform navigation\n• Feature explanations\n• General health questions\n\nWhat specific topic would you like help with?",
        
        '/\b(thank|thanks|appreciate)\b/i' => "You're very welcome! 😊 I'm glad I could help. Is there anything else you'd like to know about health, wellness, or using this platform?",
        
        '/\b(bye|goodbye|see you|exit)\b/i' => "Goodbye! 👋 Take care of your health. Feel free to come back anytime you have questions. Stay healthy! 💚"
    ];
    
    // Check patterns
    foreach ($healthPatterns as $pattern => $response) {
        if (preg_match($pattern, $message)) {
            return $response;
        }
    }
    
    // Default response for unmatched queries
    return "I'd be happy to help! 😊\n\nI can assist with:\n• Exercise and fitness advice\n• Nutrition and diet tips\n• Sleep and rest recommendations\n• Stress management strategies\n• Mental health support\n• Platform features and navigation\n\nCould you please be more specific about what you'd like to know?";
}

try {
    $response = null;
    
    // Try AI API first
    if ($aiConfig['provider'] === 'huggingface') {
        $response = callHuggingFaceAPI($userMessage, $aiConfig);
    }
    
    // Fallback to rule-based responses if API fails
    if ($response === null || empty(trim($response))) {
        $response = getRuleBasedResponse($userMessage);
    }
    
    // Clean up the response
    $response = trim($response);
    
    // If response is too similar to input, use rule-based
    if (stripos($response, $userMessage) !== false && strlen($response) < strlen($userMessage) + 20) {
        $response = getRuleBasedResponse($userMessage);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $response,
        'provider' => $response === null ? 'fallback' : $aiConfig['provider'],
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    error_log("Chat API Error: " . $e->getMessage());
    
    // Always provide a response, even on error
    $fallbackResponse = getRuleBasedResponse($userMessage);
    
    echo json_encode([
        'success' => true,
        'message' => $fallbackResponse,
        'provider' => 'fallback',
        'timestamp' => date('c')
    ]);
}
