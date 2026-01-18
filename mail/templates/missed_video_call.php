<?php
/**
 * Missed Video Call Email Template
 * Sent to doctors when they miss a video call from a patient
 */

return function($doctorData, $patientData, $callData, $baseUrl) {
    $doctorName = htmlspecialchars($doctorData['name']);
    $patientName = htmlspecialchars($patientData['name']);
    $callTime = htmlspecialchars($callData['time']);
    $patientAvatar = !empty($patientData['avatar']) ? htmlspecialchars($patientData['avatar']) : '';
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missed Video Call</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header with Gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 40px 30px; text-align: center;">
                            <div style="display: inline-block; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; padding: 20px; margin-bottom: 20px;">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    <line x1="23" y1="1" x2="1" y2="23"/>
                                </svg>
                            </div>
                            <h1 style="color: #ffffff; margin: 0 0 10px 0; font-size: 32px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                📞 Missed Video Call
                            </h1>
                            <p style="color: rgba(255, 255, 255, 0.95); margin: 0; font-size: 18px; font-weight: 500;">
                                You missed a video call from a patient
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                                Hello <strong>Dr. {$doctorName}</strong>,
                            </p>
                            
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                You missed a video call from one of your patients. Here are the details:
                            </p>
                            
                            <!-- Patient Info Card -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-radius: 12px; padding: 25px; margin-bottom: 30px; border: 2px solid #e5e7eb;">
                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="80" align="center" valign="top">
                                                    <!-- Patient Avatar -->
                                                    <div style="width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; font-weight: bold; overflow: hidden;">
                                                        {$patientAvatar}
                                                    </div>
                                                </td>
                                                <td style="padding-left: 20px;">
                                                    <h3 style="color: #1f2937; margin: 0 0 8px 0; font-size: 20px; font-weight: 700;">
                                                        {$patientName}
                                                    </h3>
                                                    <p style="color: #6b7280; margin: 0 0 5px 0; font-size: 14px; font-weight: 500;">
                                                        📅 Call Time: {$callTime}
                                                    </p>
                                                    <p style="color: #ef4444; margin: 0; font-size: 14px; font-weight: 600;">
                                                        ❌ Status: Missed Call
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Important Notice -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                                <tr>
                                    <td>
                                        <p style="color: #92400e; margin: 0; font-size: 14px; line-height: 1.6;">
                                            <strong>⚠️ Patient may need assistance:</strong><br>
                                            Your patient attempted to reach you for a video consultation. Please review their request and follow up as soon as possible.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Recommended Actions -->
                            <div style="background-color: #f9fafb; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                                <h3 style="color: #1f2937; margin: 0 0 20px 0; font-size: 18px; font-weight: 700;">
                                    📋 Recommended Actions
                                </h3>
                                
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding: 12px 0;">
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td width="30" valign="top">
                                                        <div style="width: 24px; height: 24px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 14px; font-weight: bold;">1</div>
                                                    </td>
                                                    <td style="padding-left: 15px;">
                                                        <p style="color: #374151; margin: 0; font-size: 15px; line-height: 1.5;">
                                                            <strong>Check Video Call Dashboard</strong> - Review call history and patient details
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td style="padding: 12px 0;">
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td width="30" valign="top">
                                                        <div style="width: 24px; height: 24px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 14px; font-weight: bold;">2</div>
                                                    </td>
                                                    <td style="padding-left: 15px;">
                                                        <p style="color: #374151; margin: 0; font-size: 15px; line-height: 1.5;">
                                                            <strong>Send a Message</strong> - Contact the patient through the messaging system
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td style="padding: 12px 0;">
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td width="30" valign="top">
                                                        <div style="width: 24px; height: 24px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 14px; font-weight: bold;">3</div>
                                                    </td>
                                                    <td style="padding-left: 15px;">
                                                        <p style="color: #374151; margin: 0; font-size: 15px; line-height: 1.5;">
                                                            <strong>Schedule a Follow-up</strong> - Set up a convenient time for a video consultation
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Action Buttons -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td align="center" style="padding: 10px;">
                                        <a href="{$baseUrl}/doctor/video_calls.php" style="display: inline-block; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #ffffff; text-decoration: none; padding: 16px 32px; border-radius: 12px; font-weight: 700; font-size: 16px; box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3);">
                                            📞 View Call History
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 10px;">
                                        <a href="{$baseUrl}/doctor/messages.php" style="display: inline-block; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff; text-decoration: none; padding: 16px 32px; border-radius: 12px; font-weight: 700; font-size: 16px; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);">
                                            💬 Send Message to Patient
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Professional Notice -->
                            <div style="background-color: #eff6ff; border-radius: 8px; padding: 20px; margin-top: 30px;">
                                <p style="color: #1e40af; margin: 0; font-size: 13px; line-height: 1.6; text-align: center;">
                                    <strong>📌 Professional Reminder:</strong> Timely communication helps build trust and ensures quality patient care. Consider setting up notification preferences to avoid missing future calls.
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #6b7280; font-size: 14px; margin: 0 0 10px 0;">
                                Best regards,<br>
                                <strong style="color: #1f2937;">Health Tracker Team</strong>
                            </p>
                            <p style="color: #9ca3af; font-size: 12px; margin: 15px 0 0 0;">
                                This is an automated notification. Please do not reply to this email.<br>
                                For support, contact us through your doctor dashboard.
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
