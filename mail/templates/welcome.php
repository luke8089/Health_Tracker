<?php
/**
 * Welcome Email Template for New Users
 */

return function($userData, $baseUrl) {
    $name = htmlspecialchars($userData['name']);
    $email = htmlspecialchars($userData['email']);
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Health Tracker</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%); padding: 40px 30px; text-align: center;">
                            <div style="width: 80px; height: 80px; background-color: white; border-radius: 20px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1f2937" stroke-width="2">
                                    <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <h1 style="color: white; margin: 0; font-size: 28px; font-weight: bold;">Welcome to Health Tracker!</h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #1f2937; margin: 0 0 20px; font-size: 24px;">Hi {$name}! 👋</h2>
                            
                            <p style="color: #4b5563; line-height: 1.6; margin: 0 0 20px; font-size: 16px;">
                                Welcome aboard! We're thrilled to have you join our community of health-conscious individuals dedicated to improving their wellness.
                            </p>
                            
                            <p style="color: #4b5563; line-height: 1.6; margin: 0 0 30px; font-size: 16px;">
                                Your account has been successfully created with the email: <strong>{$email}</strong>
                            </p>
                            
                            <!-- Call to Action Button -->
                            <table role="presentation" style="margin: 30px 0;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$baseUrl}/public/login.php" style="display: inline-block; background: linear-gradient(135deg, #1f2937 0%, #34d399 100%); color: white; padding: 16px 40px; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 12px rgba(31, 41, 55, 0.3);">
                            Get Started Now
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Features Section -->
                            <div style="background-color: #f9fafb; border-radius: 12px; padding: 25px; margin: 30px 0;">
                                <h3 style="color: #1f2937; margin: 0 0 20px; font-size: 20px;">What You Can Do:</h3>
                                
                                <table role="presentation" style="width: 100%;">
                                    <tr>
                                        <td style="padding: 10px 0;">
                                            <table role="presentation">
                                                <tr>
                                                    <td style="padding-right: 15px; vertical-align: top;">
                                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #34d399 0%, #10b981 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                            <span style="color: white; font-size: 20px;">📊</span>
                                                        </div>
                                                    </td>
                                                    <td style="vertical-align: top;">
                                                        <h4 style="color: #1f2937; margin: 0 0 5px; font-size: 16px; font-weight: 600;">Take Health Assessments</h4>
                                                        <p style="color: #6b7280; margin: 0; font-size: 14px; line-height: 1.5;">Complete mental and physical health assessments to track your wellness.</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td style="padding: 10px 0;">
                                            <table role="presentation">
                                                <tr>
                                                    <td style="padding-right: 15px; vertical-align: top;">
                                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #34d399 0%, #10b981 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                            <span style="color: white; font-size: 20px;">🎯</span>
                                                        </div>
                                                    </td>
                                                    <td style="vertical-align: top;">
                                                        <h4 style="color: #1f2937; margin: 0 0 5px; font-size: 16px; font-weight: 600;">Build Healthy Habits</h4>
                                                        <p style="color: #6b7280; margin: 0; font-size: 14px; line-height: 1.5;">Create and track daily habits to improve your lifestyle.</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td style="padding: 10px 0;">
                                            <table role="presentation">
                                                <tr>
                                                    <td style="padding-right: 15px; vertical-align: top;">
                                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #34d399 0%, #10b981 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                            <span style="color: white; font-size: 20px;">👨‍⚕️</span>
                                                        </div>
                                                    </td>
                                                    <td style="vertical-align: top;">
                                                        <h4 style="color: #1f2937; margin: 0 0 5px; font-size: 16px; font-weight: 600;">Connect with Doctors</h4>
                                                        <p style="color: #6b7280; margin: 0; font-size: 14px; line-height: 1.5;">Get personalized recommendations from healthcare professionals.</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td style="padding: 10px 0;">
                                            <table role="presentation">
                                                <tr>
                                                    <td style="padding-right: 15px; vertical-align: top;">
                                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #34d399 0%, #10b981 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                            <span style="color: white; font-size: 20px;">📈</span>
                                                        </div>
                                                    </td>
                                                    <td style="vertical-align: top;">
                                                        <h4 style="color: #1f2937; margin: 0 0 5px; font-size: 16px; font-weight: 600;">Track Your Progress</h4>
                                                        <p style="color: #6b7280; margin: 0; font-size: 14px; line-height: 1.5;">Monitor your health journey with detailed analytics and insights.</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Quick Tips -->
                            <div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <h4 style="color: #1e40af; margin: 0 0 10px; font-size: 16px; font-weight: 600;">💡 Quick Tip</h4>
                                <p style="color: #1e40af; margin: 0; font-size: 14px; line-height: 1.5;">
                                    Start by taking a health assessment to get personalized recommendations tailored to your needs!
                                </p>
                            </div>
                            
                            <p style="color: #4b5563; line-height: 1.6; margin: 25px 0 0; font-size: 16px;">
                                If you have any questions or need assistance, feel free to reach out to our support team.
                            </p>
                            
                            <p style="color: #4b5563; line-height: 1.6; margin: 20px 0 0; font-size: 16px;">
                                Best regards,<br>
                                <strong style="color: #1f2937;">The Health Tracker Team</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #6b7280; margin: 0 0 10px; font-size: 14px;">
                                © 2025 Health Tracker. All rights reserved.
                            </p>
                            <p style="color: #9ca3af; margin: 0; font-size: 12px;">
                                This email was sent to {$email} because you registered for a Health Tracker account.
                            </p>
                            <div style="margin-top: 15px;">
                                <a href="{$baseUrl}" style="color: #3b82f6; text-decoration: none; margin: 0 10px; font-size: 14px;">Visit Website</a>
                                <span style="color: #d1d5db;">•</span>
                                <a href="{$baseUrl}/public/about.php" style="color: #3b82f6; text-decoration: none; margin: 0 10px; font-size: 14px;">About Us</a>
                            </div>
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
