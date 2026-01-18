<?php
return function($patientData, $doctorData, $recommendationData, $baseUrl) {
    $patientName = htmlspecialchars($patientData['name']);
    $doctorName = htmlspecialchars($doctorData['name']);
    $doctorSpecialty = htmlspecialchars($doctorData['specialty'] ?? 'Healthcare Provider');
    $recommendation = nl2br(htmlspecialchars($recommendationData['recommendation_text']));
    $assessmentScore = htmlspecialchars($recommendationData['assessment_score']);
    $assessmentSeverity = htmlspecialchars($recommendationData['assessment_severity']);
    $reviewDate = htmlspecialchars($recommendationData['review_date']);
    
    // Severity colors and messages
    $severityConfig = [
        'excellent' => ['color' => '#10b981', 'emoji' => '✅', 'message' => 'Excellent'],
        'good' => ['color' => '#3b82f6', 'emoji' => '👍', 'message' => 'Good'],
        'fair' => ['color' => '#f59e0b', 'emoji' => '⚠️', 'message' => 'Fair'],
        'poor' => ['color' => '#ef4444', 'emoji' => '⚡', 'message' => 'Needs Attention'],
        'critical' => ['color' => '#dc2626', 'emoji' => '🚨', 'message' => 'Critical']
    ];
    
    $severityInfo = $severityConfig[$assessmentSeverity] ?? $severityConfig['fair'];
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor's Recommendation</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td style="padding: 40px 20px;">
                <!-- Main Container -->
                <table role="presentation" style="max-width: 650px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header with Gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%); padding: 48px 40px; text-align: center;">
                            <div style="width: 80px; height: 80px; background-color: rgba(255, 255, 255, 0.2); border-radius: 16px; margin: 0 auto 24px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                    <path d="M22 11h-6"></path>
                                    <path d="M22 8h-6"></path>
                                    <path d="M22 14h-6"></path>
                                </svg>
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: -0.5px;">Doctor's Recommendation</h1>
                            <p style="margin: 16px 0 0 0; color: rgba(255, 255, 255, 0.95); font-size: 16px; line-height: 1.5;">Professional Assessment Review</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 48px 40px;">
                            <h2 style="margin: 0 0 24px 0; color: #1f2937; font-size: 24px; font-weight: 600;">Hello, {$patientName}!</h2>
                            
                            <p style="margin: 0 0 32px 0; color: #4b5563; font-size: 16px; line-height: 1.7;">
                                Dr. {$doctorName} has reviewed your recent health assessment and provided personalized recommendations based on your responses.
                            </p>
                            
                            <!-- Doctor Info Card -->
                            <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin-bottom: 32px;">
                                <div style="display: flex; align-items: center; margin-bottom: 12px;">
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 style="margin: 0; color: #1e40af; font-size: 18px; font-weight: 600;">Dr. {$doctorName}</h3>
                                        <p style="margin: 4px 0 0 0; color: #1e3a8a; font-size: 14px;">{$doctorSpecialty}</p>
                                    </div>
                                </div>
                                <p style="margin: 12px 0 0 0; color: #1e3a8a; font-size: 13px;">
                                    <strong>Review Date:</strong> {$reviewDate}
                                </p>
                            </div>
                            
                            <!-- Assessment Score Card -->
                            <div style="background-color: #f9fafb; border: 2px solid {$severityInfo['color']}; border-radius: 12px; padding: 24px; margin-bottom: 32px; text-align: center;">
                                <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 14px; font-weight: 500;">Your Assessment Score</p>
                                <div style="font-size: 42px; color: {$severityInfo['color']}; font-weight: 700; margin-bottom: 8px;">{$assessmentScore}%</div>
                                <div style="display: inline-block; background-color: {$severityInfo['color']}; color: #ffffff; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;">
                                    {$severityInfo['emoji']} {$severityInfo['message']}
                                </div>
                            </div>
                            
                            <!-- Professional Recommendation -->
                            <h3 style="margin: 0 0 20px 0; color: #1f2937; font-size: 20px; font-weight: 600;">📋 Professional Recommendation</h3>
                            
                            <div style="background-color: #ffffff; border: 2px solid #e5e7eb; border-radius: 12px; padding: 28px; margin-bottom: 32px;">
                                <div style="color: #374151; font-size: 15px; line-height: 1.8;">
                                    {$recommendation}
                                </div>
                            </div>
                            
                            <!-- Important Notice -->
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 32px;">
                                <div style="display: flex; align-items: start;">
                                    <div>
                                        <h4 style="margin: 0 0 8px 0; color: #92400e; font-size: 15px; font-weight: 600;">⚕️ Medical Advice</h4>
                                        <p style="margin: 0; color: #78350f; font-size: 14px; line-height: 1.6;">
                                            This recommendation is based on your self-reported assessment. Please follow your doctor's advice and schedule a follow-up appointment if recommended.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <table role="presentation" style="margin: 32px auto; width: 100%;">
                                <tr>
                                    <td style="text-align: center; padding-bottom: 12px;">
                                        <a href="{$baseUrl}/public/dashboard.php" style="display: inline-block; background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 12px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);">
                                            View on Dashboard
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$baseUrl}/public/messages.php" style="display: inline-block; background-color: #f3f4f6; color: #1f2937; text-decoration: none; padding: 14px 32px; border-radius: 12px; font-size: 15px; font-weight: 600; border: 2px solid #e5e7eb;">
                                            Reply to Doctor
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Next Steps -->
                            <div style="background-color: #f0fdf4; border-radius: 12px; padding: 24px; margin-top: 24px;">
                                <h4 style="margin: 0 0 16px 0; color: #065f46; font-size: 16px; font-weight: 600;">🎯 Next Steps</h4>
                                <ul style="margin: 0; padding-left: 20px; color: #064e3b; font-size: 14px; line-height: 1.8;">
                                    <li>Review the recommendation carefully</li>
                                    <li>Follow the advice provided by your doctor</li>
                                    <li>Track your progress and habits regularly</li>
                                    <li>Schedule a follow-up if needed</li>
                                    <li>Contact your doctor if you have questions</li>
                                </ul>
                            </div>
                            
                            <p style="margin: 32px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                Your health journey matters to us. We're here to support you every step of the way.<br>
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
                                        <a href="{$baseUrl}/public/assessment_history.php" style="color: #3b82f6; text-decoration: none; font-size: 13px; margin: 0 12px;">Assessments</a>
                                        <span style="color: #d1d5db;">|</span>
                                        <a href="{$baseUrl}/public/messages.php" style="color: #3b82f6; text-decoration: none; font-size: 13px; margin: 0 12px;">Messages</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                                        <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.6;">
                                            This is a professional medical recommendation from your healthcare provider.<br>
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
