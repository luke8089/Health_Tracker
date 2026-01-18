<?php
/**
 * Password Reset Email Template
 */

return function($name, $resetToken, $baseUrl) {
    $name = htmlspecialchars($name);
    $resetLink = "{$baseUrl}/public/reset_password.php?token={$resetToken}";
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="color: white; margin: 0; font-size: 28px; font-weight: bold;">🔒 Password Reset Request</h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #1f2937; margin: 0 0 20px; font-size: 24px;">Hi {$name},</h2>
                            
                            <p style="color: #4b5563; line-height: 1.6; margin: 0 0 20px; font-size: 16px;">
                                We received a request to reset your password for your Health Tracker account.
                            </p>
                            
                            <p style="color: #4b5563; line-height: 1.6; margin: 0 0 30px; font-size: 16px;">
                                Click the button below to create a new password:
                            </p>
                            
                            <!-- Reset Button -->
                            <table role="presentation" style="margin: 30px 0;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$resetLink}" style="display: inline-block; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 16px 40px; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);">
                            Reset My Password
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Security Notice -->
                            <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <h4 style="color: #92400e; margin: 0 0 10px; font-size: 16px; font-weight: 600;">🔐 Security Notice</h4>
                                <p style="color: #92400e; margin: 0 0 10px; font-size: 14px; line-height: 1.5;">
                                    This password reset link will expire in <strong>1 hour</strong> for security reasons.
                                </p>
                                <p style="color: #92400e; margin: 0; font-size: 14px; line-height: 1.5;">
                                    If you didn't request a password reset, please ignore this email or contact support if you have concerns.
                                </p>
                            </div>
                            
                            <p style="color: #6b7280; line-height: 1.6; margin: 25px 0 0; font-size: 14px; background-color: #f9fafb; padding: 15px; border-radius: 8px;">
                                <strong>Can't click the button?</strong> Copy and paste this link into your browser:<br>
                                <a href="{$resetLink}" style="color: #3b82f6; word-break: break-all;">{$resetLink}</a>
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
                                This is an automated security email. Please do not reply.
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
