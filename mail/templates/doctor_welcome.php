<?php
/**
 * Welcome Email Template for New Doctors
 */

return function($doctorData, $baseUrl) {
    $name = htmlspecialchars($doctorData['name']);
    $email = htmlspecialchars($doctorData['email']);
    $specialty = htmlspecialchars($doctorData['specialty'] ?? 'General Practice');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome Dr. {$name}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1f2937 0%, #8b5cf6 100%); padding: 40px 30px; text-align: center;">
                            <div style="width: 80px; height: 80px; background-color: white; border-radius: 20px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2">
                                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <h1 style="color: white; margin: 0; font-size: 28px; font-weight: bold;">Welcome Dr. {$name}!</h1>
                            <p style="color: rgba(255, 255, 255, 0.9); margin: 10px 0 0; font-size: 16px;">Professional Healthcare Provider</p>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #1f2937; margin: 0 0 20px; font-size: 24px;">Your Professional Account is Ready! 🎉</h2>
                            
                            <p style="color: #4b5563; line-height: 1.6; margin: 0 0 20px; font-size: 16px;">
                                Thank you for joining Health Tracker as a healthcare professional. We're excited to have you as part of our network helping patients achieve better health outcomes.
                            </p>
                            
                            <!-- Account Details -->
                            <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <h4 style="color: #92400e; margin: 0 0 15px; font-size: 16px; font-weight: 600;">📋 Your Account Details</h4>
                                <table role="presentation" style="width: 100%;">
                                    <tr>
                                        <td style="color: #92400e; padding: 5px 0; font-size: 14px;"><strong>Email:</strong></td>
                                        <td style="color: #92400e; padding: 5px 0; font-size: 14px;">{$email}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #92400e; padding: 5px 0; font-size: 14px;"><strong>Specialization:</strong></td>
                                        <td style="color: #92400e; padding: 5px 0; font-size: 14px;">{$specialty}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #92400e; padding: 5px 0; font-size: 14px;"><strong>Account Type:</strong></td>
                                        <td style="color: #92400e; padding: 5px 0; font-size: 14px;">Healthcare Professional</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Call to Action Button -->
                            <table role="presentation" style="margin: 30px 0;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$baseUrl}/public/login.php" style="display: inline-block; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; padding: 16px 40px; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);">
                            Access Your Dashboard
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Features Section -->
                            <div style="background-color: #f9fafb; border-radius: 12px; padding: 25px; margin: 30px 0;">
                                <h3 style="color: #1f2937; margin: 0 0 20px; font-size: 20px;">Your Professional Tools:</h3>
                                
                                <table role="presentation" style="width: 100%;">
                                    <tr>
                                        <td style="padding: 10px 0;">
                                            <table role="presentation">
                                                <tr>
                                                    <td style="padding-right: 15px; vertical-align: top;">
                                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                            <span style="color: white; font-size: 20px;">👥</span>
                                                        </div>
                                                    </td>
                                                    <td style="vertical-align: top;">
                                                        <h4 style="color: #1f2937; margin: 0 0 5px; font-size: 16px; font-weight: 600;">Patient Management</h4>
                                                        <p style="color: #6b7280; margin: 0; font-size: 14px; line-height: 1.5;">View and manage your patient connections and their health data.</p>
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
                                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                            <span style="color: white; font-size: 20px;">📝</span>
                                                        </div>
                                                    </td>
                                                    <td style="vertical-align: top;">
                                                        <h4 style="color: #1f2937; margin: 0 0 5px; font-size: 16px; font-weight: 600;">Assessment Review</h4>
                                                        <p style="color: #6b7280; margin: 0; font-size: 14px; line-height: 1.5;">Review patient assessments and provide professional recommendations.</p>
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
                                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                            <span style="color: white; font-size: 20px;">💬</span>
                                                        </div>
                                                    </td>
                                                    <td style="vertical-align: top;">
                                                        <h4 style="color: #1f2937; margin: 0 0 5px; font-size: 16px; font-weight: 600;">Messaging System</h4>
                                                        <p style="color: #6b7280; margin: 0; font-size: 14px; line-height: 1.5;">Communicate securely with patients through our platform.</p>
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
                                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                            <span style="color: white; font-size: 20px;">🎯</span>
                                                        </div>
                                                    </td>
                                                    <td style="vertical-align: top;">
                                                        <h4 style="color: #1f2937; margin: 0 0 5px; font-size: 16px; font-weight: 600;">Habit Monitoring</h4>
                                                        <p style="color: #6b7280; margin: 0; font-size: 14px; line-height: 1.5;">Track and verify patient habit completions and progress.</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Important Notice -->
                            <div style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <h4 style="color: #1e40af; margin: 0 0 10px; font-size: 16px; font-weight: 600;">⚠️ Important Notice</h4>
                                <p style="color: #1e40af; margin: 0; font-size: 14px; line-height: 1.5;">
                                    Your account is currently pending admin approval. You will receive another email once your account has been verified and activated.
                                </p>
                            </div>
                            
                            <p style="color: #4b5563; line-height: 1.6; margin: 25px 0 0; font-size: 16px;">
                                If you have any questions or need assistance, please don't hesitate to contact our support team.
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
                                This email was sent to {$email} because you registered as a healthcare professional.
                            </p>
                            <div style="margin-top: 15px;">
                                <a href="{$baseUrl}" style="color: #3b82f6; text-decoration: none; margin: 0 10px; font-size: 14px;">Visit Website</a>
                                <span style="color: #d1d5db;">•</span>
                                <a href="{$baseUrl}/public/about.php" style="color: #3b82f6; text-decoration: none; margin: 0 10px; font-size: 14px;">Support</a>
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
