<?php
return function($userData, $habitData, $baseUrl) {
    $name = htmlspecialchars($userData['name']);
    $habitName = htmlspecialchars($habitData['name']);
    $frequency = htmlspecialchars($habitData['frequency']);
    $targetDays = htmlspecialchars($habitData['target_days']);
    $startDate = htmlspecialchars($habitData['start_date']);
    $endDate = htmlspecialchars($habitData['end_date']);
    
    // Frequency display
    $frequencyText = ucfirst($frequency);
    $frequencyEmoji = [
        'daily' => '📅',
        'weekly' => '📆',
        'monthly' => '🗓️'
    ][$frequency] ?? '📋';
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Habit Created</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td style="padding: 40px 20px;">
                <!-- Main Container -->
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header with Gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 48px 40px; text-align: center;">
                            <div style="width: 80px; height: 80px; background-color: rgba(255, 255, 255, 0.2); border-radius: 16px; margin: 0 auto 24px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
                                <span style="font-size: 40px;">🎯</span>
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: -0.5px;">New Habit Created!</h1>
                            <p style="margin: 16px 0 0 0; color: rgba(255, 255, 255, 0.95); font-size: 16px; line-height: 1.5;">Your journey to better habits starts now</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 48px 40px;">
                            <h2 style="margin: 0 0 24px 0; color: #1f2937; font-size: 24px; font-weight: 600;">Hello, {$name}!</h2>
                            
                            <p style="margin: 0 0 32px 0; color: #4b5563; font-size: 16px; line-height: 1.7;">
                                Congratulations on creating a new habit! You've taken the first step toward positive change. Consistency is key, and we're here to support you every step of the way.
                            </p>
                            
                            <!-- Habit Details Card -->
                            <table role="presentation" style="width: 100%; margin-bottom: 32px; border-radius: 12px; overflow: hidden; border: 2px solid #6366f1;">
                                <tr>
                                    <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 24px; text-align: center;">
                                        <div style="font-size: 48px; margin-bottom: 12px;">{$frequencyEmoji}</div>
                                        <h3 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">{$habitName}</h3>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="background-color: #f9fafb; padding: 24px;">
                                        <table style="width: 100%;">
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                                    <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Frequency:</span>
                                                    <span style="color: #1f2937; font-size: 16px; font-weight: 600; float: right;">{$frequencyText}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                                    <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Target Duration:</span>
                                                    <span style="color: #1f2937; font-size: 16px; font-weight: 600; float: right;">{$targetDays} days</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                                    <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Start Date:</span>
                                                    <span style="color: #1f2937; font-size: 16px; font-weight: 600; float: right;">{$startDate}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0;">
                                                    <span style="color: #6b7280; font-size: 14px; font-weight: 500;">Target End Date:</span>
                                                    <span style="color: #1f2937; font-size: 16px; font-weight: 600; float: right;">{$endDate}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Tips Section -->
                            <h3 style="margin: 0 0 20px 0; color: #1f2937; font-size: 20px; font-weight: 600;">💡 Tips for Success</h3>
                            
                            <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                                <h4 style="margin: 0 0 8px 0; color: #1e40af; font-size: 15px; font-weight: 600;">Start Small</h4>
                                <p style="margin: 0; color: #1e3a8a; font-size: 14px; line-height: 1.6;">
                                    Don't overwhelm yourself. Focus on completing your habit consistently rather than perfectly.
                                </p>
                            </div>
                            
                            <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                                <h4 style="margin: 0 0 8px 0; color: #065f46; font-size: 15px; font-weight: 600;">Track Your Progress</h4>
                                <p style="margin: 0; color: #064e3b; font-size: 14px; line-height: 1.6;">
                                    Log your completions daily and upload proof to build accountability and see your streak grow!
                                </p>
                            </div>
                            
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 32px;">
                                <h4 style="margin: 0 0 8px 0; color: #92400e; font-size: 15px; font-weight: 600;">Stay Consistent</h4>
                                <p style="margin: 0; color: #78350f; font-size: 14px; line-height: 1.6;">
                                    Missing one day won't ruin your progress. Just get back on track the next day and keep going!
                                </p>
                            </div>
                            
                            <!-- Action Button -->
                            <table role="presentation" style="margin: 32px auto; width: 100%;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$baseUrl}/public/habits.php" style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 12px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
                                            Track Your Habit Now
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Motivation Quote -->
                            <div style="background-color: #faf5ff; border-radius: 12px; padding: 24px; margin-top: 24px; text-align: center;">
                                <p style="margin: 0; color: #6b21a8; font-size: 18px; font-weight: 500; font-style: italic; line-height: 1.6;">
                                    "We are what we repeatedly do. Excellence, then, is not an act, but a habit."
                                </p>
                                <p style="margin: 12px 0 0 0; color: #7c3aed; font-size: 14px; font-weight: 600;">- Aristotle</p>
                            </div>
                            
                            <p style="margin: 32px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                You've got this! We believe in your ability to build this habit.<br>
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
                                        <a href="{$baseUrl}/public/habits.php" style="color: #3b82f6; text-decoration: none; font-size: 13px; margin: 0 12px;">My Habits</a>
                                        <span style="color: #d1d5db;">|</span>
                                        <a href="{$baseUrl}/public/dashboard.php" style="color: #3b82f6; text-decoration: none; font-size: 13px; margin: 0 12px;">Dashboard</a>
                                        <span style="color: #d1d5db;">|</span>
                                        <a href="{$baseUrl}/public/verify_habits.php" style="color: #3b82f6; text-decoration: none; font-size: 13px; margin: 0 12px;">Verify Progress</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                                        <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.6;">
                                            Keep building healthy habits for a better you!<br>
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
