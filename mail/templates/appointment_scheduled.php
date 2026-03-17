<?php
return function($patientData, $doctorData, $appointmentData, $baseUrl) {
    $patientName = htmlspecialchars($patientData['name'] ?? 'Patient');
    $doctorName = htmlspecialchars($doctorData['name'] ?? 'Doctor');
    $doctorSpecialty = htmlspecialchars($doctorData['specialty'] ?? 'Healthcare Provider');
    $appointmentDate = !empty($appointmentData['appointment_date']) ? date('F j, Y', strtotime($appointmentData['appointment_date'])) : 'N/A';
    $appointmentTime = !empty($appointmentData['appointment_time']) ? date('g:i A', strtotime($appointmentData['appointment_time'])) : 'N/A';
    $doctorResponse = nl2br(htmlspecialchars($appointmentData['doctor_response'] ?? ''));
    $reason = nl2br(htmlspecialchars($appointmentData['reason'] ?? ''));

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Scheduled</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;">
    <table role="presentation" style="width:100%;border-collapse:collapse;background:#f3f4f6;">
        <tr>
            <td style="padding:30px 16px;">
                <table role="presentation" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);padding:34px 26px;color:#fff;text-align:center;">
                            <h1 style="margin:0;font-size:28px;">Appointment Confirmed</h1>
                            <p style="margin:10px 0 0;font-size:15px;opacity:0.92;">Your doctor has accepted and scheduled your appointment.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 26px;color:#374151;">
                            <p style="margin:0 0 14px;font-size:16px;">Hello {$patientName},</p>
                            <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">Dr. {$doctorName} ({$doctorSpecialty}) has accepted your appointment request.</p>

                            <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:16px;margin:0 0 18px;">
                                <p style="margin:0 0 8px;font-size:14px;"><strong>Date:</strong> {$appointmentDate}</p>
                                <p style="margin:0 0 8px;font-size:14px;"><strong>Time:</strong> {$appointmentTime}</p>
                                <p style="margin:0;font-size:14px;"><strong>Reason:</strong> {$reason}</p>
                            </div>

                            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin:0 0 20px;">
                                <p style="margin:0 0 8px;font-size:14px;"><strong>Doctor Response:</strong></p>
                                <p style="margin:0;font-size:14px;line-height:1.6;">{$doctorResponse}</p>
                            </div>

                            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;">Please be available on time. If you need to reschedule, contact your doctor through the app.</p>

                            <p style="text-align:center;margin:0;">
                                <a href="{$baseUrl}/public/appointments.php" style="display:inline-block;background:#1f2937;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;">View My Appointments</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 26px;border-top:1px solid #e5e7eb;text-align:center;color:#6b7280;font-size:12px;">
                            Health Tracker • Appointment Notification
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