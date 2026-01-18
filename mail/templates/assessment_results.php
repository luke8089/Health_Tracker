<?php
return function($userData, $assessmentData, $baseUrl) {
    $name = htmlspecialchars($userData['name']);
    $assessmentType = htmlspecialchars($assessmentData['type']);
    $score = htmlspecialchars($assessmentData['score']);
    $severity = htmlspecialchars($assessmentData['severity']);
    $recommendations = $assessmentData['recommendations'];
    $assessmentDate = htmlspecialchars($assessmentData['date']);
    
    // Severity colors and messages
    $severityConfig = [
        'excellent' => ['color' => '#10b981', 'emoji' => '🎉', 'message' => 'Outstanding Health!'],
        'good' => ['color' => '#3b82f6', 'emoji' => '👍', 'message' => 'Good Health'],
        'fair' => ['color' => '#f59e0b', 'emoji' => '⚠️', 'message' => 'Needs Attention'],
        'poor' => ['color' => '#ef4444', 'emoji' => '⚡', 'message' => 'Action Required'],
        'critical' => ['color' => '#dc2626', 'emoji' => '🚨', 'message' => 'Urgent Action Needed']
    ];
    
    $severityInfo = $severityConfig[$severity] ?? $severityConfig['fair'];
    
    // Urgency colors
    $urgencyColors = [
        'high' => '#dc2626',
        'medium' => '#f59e0b',
        'low' => '#10b981'
    ];
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Health Assessment Results</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td style="padding: 40px 20px;">
                <!-- Main Container -->
                <table role="presentation" style="max-width: 650px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header with Gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%); padding: 48px 40px; text-align: center;">
                            <div style="width: 80px; height: 80px; background-color: rgba(255, 255, 255, 0.2); border-radius: 16px; margin: 0 auto 24px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                                </svg>
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: -0.5px;">Your Health Assessment Results</h1>
                            <p style="margin: 16px 0 0 0; color: rgba(255, 255, 255, 0.95); font-size: 16px; line-height: 1.5;">{$assessmentType} Health Assessment</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 48px 40px;">
                            <h2 style="margin: 0 0 24px 0; color: #1f2937; font-size: 24px; font-weight: 600;">Hello, {$name}!</h2>
                            
                            <p style="margin: 0 0 32px 0; color: #4b5563; font-size: 16px; line-height: 1.7;">
                                Thank you for completing your {$assessmentType} health assessment. We've analyzed your responses and prepared personalized recommendations to help you improve your wellness journey.
                            </p>
                            
                            <!-- Score Card -->
                            <table role="presentation" style="width: 100%; margin-bottom: 32px; border-radius: 12px; overflow: hidden; border: 2px solid {$severityInfo['color']};">
                                <tr>
                                    <td style="background: linear-gradient(135deg, {$severityInfo['color']} 0%, {$severityInfo['color']}dd 100%); padding: 32px; text-align: center;">
                                        <div style="font-size: 48px; margin-bottom: 8px;">{$severityInfo['emoji']}</div>
                                        <div style="color: #ffffff; font-size: 48px; font-weight: 700; margin-bottom: 8px;">{$score}%</div>
                                        <div style="color: rgba(255, 255, 255, 0.95); font-size: 18px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">{$severityInfo['message']}</div>
                                        <div style="color: rgba(255, 255, 255, 0.85); font-size: 14px; margin-top: 8px;">Completed on {$assessmentDate}</div>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Recommendations Section -->
                            <h3 style="margin: 0 0 24px 0; color: #1f2937; font-size: 20px; font-weight: 600;">📋 Your Personalized Recommendations</h3>
                            
HTML;
    
    // Add each recommendation
    foreach ($recommendations as $index => $rec) {
        $urgencyColor = $urgencyColors[$rec['urgency']] ?? $urgencyColors['low'];
        $urgencyLabel = ucfirst($rec['urgency']) . ' Priority';
        $title = htmlspecialchars($rec['title']);
        $details = htmlspecialchars($rec['details']);
        
        $html .= <<<HTML
                            <!-- Recommendation Card {$index} -->
                            <div style="background-color: #f9fafb; border-left: 4px solid {$urgencyColor}; border-radius: 8px; padding: 24px; margin-bottom: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <h4 style="margin: 0; color: #1f2937; font-size: 17px; font-weight: 600;">{$title}</h4>
                                    <span style="background-color: {$urgencyColor}; color: #ffffff; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; margin-left: 12px;">{$urgencyLabel}</span>
                                </div>
                                <p style="margin: 0; color: #4b5563; font-size: 15px; line-height: 1.6;">{$details}</p>
                            </div>

HTML;
    }
    
    $html .= <<<HTML
                            
                            <!-- Action Button -->
                            <table role="presentation" style="margin: 32px auto; width: 100%;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$baseUrl}/public/assessment_history.php" style="display: inline-block; background: linear-gradient(135deg, #1f2937 0%, #34d399 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 12px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 12px rgba(31, 41, 55, 0.3);">
                                            View Full Assessment History
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Important Notice -->
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin-top: 32px;">
                                <div style="display: flex; align-items: start;">
                                    <div>
                                        <h4 style="margin: 0 0 8px 0; color: #92400e; font-size: 15px; font-weight: 600;">📌 Important Note</h4>
                                        <p style="margin: 0; color: #78350f; font-size: 14px; line-height: 1.6;">
                                            These recommendations are based on your self-assessment responses and are meant for general wellness guidance. They do not replace professional medical advice. If you have specific health concerns, please consult with a healthcare provider.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Next Steps -->
                            <div style="background-color: #eff6ff; border-radius: 12px; padding: 24px; margin-top: 24px;">
                                <h4 style="margin: 0 0 16px 0; color: #1e40af; font-size: 16px; font-weight: 600;">🎯 Next Steps</h4>
                                <ul style="margin: 0; padding-left: 20px; color: #1e3a8a; font-size: 14px; line-height: 1.8;">
                                    <li>Review your recommendations carefully</li>
                                    <li>Track your progress with our habit tracking features</li>
                                    <li>Consider connecting with a healthcare professional on our platform</li>
                                    <li>Retake assessments monthly to monitor your improvements</li>
                                </ul>
                            </div>
                            
                            <p style="margin: 32px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                Keep up the great work on your wellness journey!<br>
                                <strong style="color: #1f2937;">The Health Tracker Team</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 32px 40px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" style="width: 100%;">
                                <tr>
                                    <td style="text-align: center; padding-bottom: 16px;">
                                        <p style="margin: 0; color: #6b7280; font-size: 14px; font-weight: 600;">
                                            Health Tracker - Your Wellness Partner
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; padding-bottom: 16px;">
                                        <a href="{$baseUrl}/public/dashboard.php" style="color: #3b82f6; text-decoration: none; font-size: 13px; margin: 0 12px;">Dashboard</a>
                                        <span style="color: #d1d5db;">|</span>
                                        <a href="{$baseUrl}/public/assessment_history.php" style="color: #3b82f6; text-decoration: none; font-size: 13px; margin: 0 12px;">History</a>
                                        <span style="color: #d1d5db;">|</span>
                                        <a href="{$baseUrl}/public/connect_doctor.php" style="color: #3b82f6; text-decoration: none; font-size: 13px; margin: 0 12px;">Find a Doctor</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                                        <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.6;">
                                            This is an automated health assessment report.<br>
                                            © 2025 Health Tracker. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
};
?>
