<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System Test - Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-gray-800 to-green-400 px-8 py-6">
                <h1 class="text-3xl font-bold text-white">📧 Email System Test</h1>
                <p class="text-green-100 mt-2">Test your Health Tracker email configuration</p>
            </div>

            <!-- Configuration Status -->
            <div class="p-8 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Configuration Status</h2>
                <?php
                require_once __DIR__ . '/Mailer.php';
                $config = require __DIR__ . '/config.php';
                ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <p class="text-sm font-semibold text-blue-900">SMTP Host</p>
                        <p class="text-lg font-bold text-blue-700"><?php echo htmlspecialchars($config['smtp']['host']); ?></p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <p class="text-sm font-semibold text-blue-900">SMTP Port</p>
                        <p class="text-lg font-bold text-blue-700"><?php echo htmlspecialchars($config['smtp']['port']); ?></p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <p class="text-sm font-semibold text-green-900">From Email</p>
                        <p class="text-lg font-bold text-green-700"><?php echo htmlspecialchars($config['from']['email']); ?></p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <p class="text-sm font-semibold text-green-900">From Name</p>
                        <p class="text-lg font-bold text-green-700"><?php echo htmlspecialchars($config['from']['name']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Test Form -->
            <div class="p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Send Test Email</h2>
                
                <?php
                $message = '';
                $messageType = '';

                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
                    $testEmail = filter_var($_POST['test_email'] ?? '', FILTER_VALIDATE_EMAIL);
                    $emailType = $_POST['email_type'] ?? '';

                    if (!$testEmail) {
                        $message = 'Please enter a valid email address';
                        $messageType = 'error';
                    } else {
                        try {
                            $mailer = new Mailer();
                            $result = null;

                            switch ($emailType) {
                                case 'welcome':
                                    $result = $mailer->sendWelcomeEmail([
                                        'name' => 'Test User',
                                        'email' => $testEmail,
                                        'role' => 'user'
                                    ]);
                                    break;

                                case 'doctor':
                                    $result = $mailer->sendDoctorRegistrationEmail([
                                        'name' => 'Test Doctor',
                                        'email' => $testEmail,
                                        'specialty' => 'Cardiology',
                                        'license_number' => 'MD12345',
                                        'experience_years' => '5-10'
                                    ]);
                                    break;

                                case 'admin':
                                    $result = $mailer->notifyAdminNewDoctor([
                                        'name' => 'Test Doctor',
                                        'email' => $testEmail,
                                        'specialty' => 'Neurology',
                                        'license_number' => 'MD67890',
                                        'experience_years' => '10-15'
                                    ]);
                                    break;

                                case 'reset':
                                    $result = $mailer->sendPasswordResetEmail(
                                        $testEmail,
                                        'Test User',
                                        'test-reset-token-' . bin2hex(random_bytes(16))
                                    );
                                    break;

                                case 'verify':
                                    $result = $mailer->sendVerificationEmail(
                                        $testEmail,
                                        'Test User',
                                        'test-verify-token-' . bin2hex(random_bytes(16))
                                    );
                                    break;

                                default:
                                    $message = 'Invalid email type selected';
                                    $messageType = 'error';
                            }

                            if ($result) {
                                if ($result['success']) {
                                    $message = "Email sent successfully to {$testEmail}! Check your inbox (and spam folder).";
                                    $messageType = 'success';
                                } else {
                                    $message = "Failed to send email: " . $result['message'];
                                    $messageType = 'error';
                                }
                            }
                        } catch (Exception $e) {
                            $message = "Error: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                ?>

                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                        <p class="<?php echo $messageType === 'success' ? 'text-green-800' : 'text-red-800'; ?> font-semibold">
                            <?php echo $messageType === 'success' ? '✓' : '✗'; ?> <?php echo htmlspecialchars($message); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Test Email Address</label>
                        <input 
                            type="email" 
                            name="test_email" 
                            required
                            value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>"
                            placeholder="your-email@example.com"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent"
                        >
                        <p class="mt-2 text-sm text-gray-600">Enter the email address where you want to receive the test email</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email Template</label>
                        <select name="email_type" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                            <option value="">Select a template...</option>
                            <option value="welcome">Welcome Email (Regular User)</option>
                            <option value="doctor">Doctor Welcome Email</option>
                            <option value="admin">Admin New Doctor Notification</option>
                            <option value="reset">Password Reset Email</option>
                            <option value="verify">Email Verification</option>
                        </select>
                    </div>

                    <button 
                        type="submit" 
                        name="send_test"
                        class="w-full bg-gradient-to-r from-gray-800 to-green-400 text-white px-6 py-3 rounded-lg font-semibold hover:from-gray-900 hover:to-green-500 transition-all duration-300 shadow-lg"
                    >
                        Send Test Email
                    </button>
                </form>
            </div>

            <!-- Available Templates -->
            <div class="p-8 bg-gray-50 border-t border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Available Email Templates</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <h3 class="font-bold text-gray-900 mb-2">📨 Welcome Email</h3>
                        <p class="text-sm text-gray-600">Sent to new regular users upon registration. Includes feature highlights and quick start guide.</p>
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <h3 class="font-bold text-gray-900 mb-2">👨‍⚕️ Doctor Welcome</h3>
                        <p class="text-sm text-gray-600">Professional welcome email for healthcare providers with credentials confirmation.</p>
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <h3 class="font-bold text-gray-900 mb-2">🔔 Admin Notification</h3>
                        <p class="text-sm text-gray-600">Notifies admin when a new doctor registers, requiring verification.</p>
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <h3 class="font-bold text-gray-900 mb-2">🔒 Password Reset</h3>
                        <p class="text-sm text-gray-600">Secure password reset with time-limited token link.</p>
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <h3 class="font-bold text-gray-900 mb-2">✉️ Email Verification</h3>
                        <p class="text-sm text-gray-600">Email confirmation with 24-hour verification link.</p>
                    </div>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="p-8 border-t border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-4">🛠️ Troubleshooting</h2>
                <div class="space-y-3">
                    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                        <h3 class="font-bold text-yellow-900 mb-2">Email Not Received?</h3>
                        <ul class="text-sm text-yellow-800 space-y-1 list-disc list-inside">
                            <li>Check your spam/junk folder</li>
                            <li>Wait up to 1-2 minutes for delivery</li>
                            <li>Verify email address is correct</li>
                            <li>Check Gmail app password is valid</li>
                        </ul>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <h3 class="font-bold text-blue-900 mb-2">Using Gmail?</h3>
                        <ul class="text-sm text-blue-800 space-y-1 list-disc list-inside">
                            <li>Enable 2-Factor Authentication</li>
                            <li>Generate App Password (not regular password)</li>
                            <li>Update password in mail/config.php</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 bg-gray-800 text-center">
                <p class="text-gray-300">
                    <a href="../public/register.php" class="text-green-400 hover:text-green-300 font-semibold">← Back to Registration</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
