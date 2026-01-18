<?php
/**
 * Admin Notification Email Template for New Doctor Registration
 */

return function($doctorData, $baseUrl) {
    $name = htmlspecialchars($doctorData['name']);
    $email = htmlspecialchars($doctorData['email']);
    $specialty = htmlspecialchars($doctorData['specialty'] ?? 'General Practice');
    $license = htmlspecialchars($doctorData['license_number'] ?? 'N/A');
    $experience = htmlspecialchars($doctorData['experience_years'] ?? 'N/A');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Doctor Registration</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #dc2626 0%, #f59e0b 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="color: white; margin: 0; font-size: 24px; font-weight: bold;">🔔 New Doctor Registration</h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #1f2937; margin: 0 0 20px; font-size: 22px;">Action Required: Review Doctor Application</h2>
                            
                            <p style="color: #4b5563; line-height: 1.6; margin: 0 0 25px; font-size: 16px;">
                                A new healthcare professional has registered on Health Tracker and requires verification.
                            </p>
                            
                            <!-- Doctor Details -->
                            <div style="background-color: #f9fafb; border-radius: 12px; padding: 25px; margin: 25px 0; border: 2px solid #e5e7eb;">
                                <h3 style="color: #1f2937; margin: 0 0 20px; font-size: 18px; font-weight: 600;">Doctor Information</h3>
                                
                                <table role="presentation" style="width: 100%;">
                                    <tr>
                                        <td style="color: #6b7280; padding: 10px 0; font-size: 14px; font-weight: 600; width: 40%;">Full Name:</td>
                                        <td style="color: #1f2937; padding: 10px 0; font-size: 14px; font-weight: 500;">Dr. {$name}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; padding: 10px 0; font-size: 14px; font-weight: 600;">Email Address:</td>
                                        <td style="color: #1f2937; padding: 10px 0; font-size: 14px; font-weight: 500;">{$email}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; padding: 10px 0; font-size: 14px; font-weight: 600;">Specialization:</td>
                                        <td style="color: #1f2937; padding: 10px 0; font-size: 14px; font-weight: 500;">{$specialty}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; padding: 10px 0; font-size: 14px; font-weight: 600;">License Number:</td>
                                        <td style="color: #1f2937; padding: 10px 0; font-size: 14px; font-weight: 500;">{$license}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; padding: 10px 0; font-size: 14px; font-weight: 600;">Experience:</td>
                                        <td style="color: #1f2937; padding: 10px 0; font-size: 14px; font-weight: 500;">{$experience}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; padding: 10px 0; font-size: 14px; font-weight: 600;">Registration Date:</td>
                                        <td style="color: #1f2937; padding: 10px 0; font-size: 14px; font-weight: 500;">{date('F j, Y g:i A')}</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Call to Action -->
                            <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <h4 style="color: #92400e; margin: 0 0 10px; font-size: 16px; font-weight: 600;">⚡ Next Steps</h4>
                                <p style="color: #92400e; margin: 0; font-size: 14px; line-height: 1.5;">
                                    Please review the doctor's credentials and approve or reject their application through the admin dashboard.
                                </p>
                            </div>
                            
                            <!-- Action Button -->
                            <table role="presentation" style="margin: 30px 0;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$baseUrl}/admin/doctors.php" style="display: inline-block; background: linear-gradient(135deg, #1f2937 0%, #34d399 100%); color: white; padding: 16px 40px; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 12px rgba(31, 41, 55, 0.3);">
                            Review Application
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #6b7280; margin: 0; font-size: 14px;">
                                © 2025 Health Tracker Admin System
                            </p>
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
