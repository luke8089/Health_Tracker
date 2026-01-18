<?php
/**
 * Email System Test Script
 * Use this to test your email configuration
 */

require_once __DIR__ . '/Mailer.php';

echo "====================================\n";
echo "  Health Tracker Email System Test\n";
echo "====================================\n\n";

// Initialize mailer
try {
    $mailer = new Mailer();
    echo "✓ Mailer initialized successfully\n\n";
} catch (Exception $e) {
    echo "✗ Failed to initialize mailer: " . $e->getMessage() . "\n";
    exit(1);
}

// Test configuration
$config = require __DIR__ . '/config.php';
echo "Configuration:\n";
echo "  SMTP Host: " . $config['smtp']['host'] . "\n";
echo "  SMTP Port: " . $config['smtp']['port'] . "\n";
echo "  From Email: " . $config['from']['email'] . "\n";
echo "  From Name: " . $config['from']['name'] . "\n\n";

// Prompt for test email
echo "Enter your test email address: ";
$testEmail = trim(fgets(STDIN));

if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    echo "✗ Invalid email address\n";
    exit(1);
}

echo "\nSelect test to run:\n";
echo "1. Welcome Email (Regular User)\n";
echo "2. Doctor Welcome Email\n";
echo "3. Admin New Doctor Notification\n";
echo "4. Password Reset Email\n";
echo "5. Email Verification\n";
echo "\nEnter choice (1-5): ";

$choice = trim(fgets(STDIN));

echo "\nSending test email...\n";

$result = null;

switch ($choice) {
    case '1':
        echo "Testing: Welcome Email for Regular User\n";
        $result = $mailer->sendWelcomeEmail([
            'name' => 'Test User',
            'email' => $testEmail,
            'role' => 'user'
        ]);
        break;
        
    case '2':
        echo "Testing: Doctor Welcome Email\n";
        $result = $mailer->sendDoctorRegistrationEmail([
            'name' => 'Test Doctor',
            'email' => $testEmail,
            'specialty' => 'Cardiology',
            'license_number' => 'MD12345',
            'experience_years' => '5-10'
        ]);
        break;
        
    case '3':
        echo "Testing: Admin New Doctor Notification\n";
        $result = $mailer->notifyAdminNewDoctor([
            'name' => 'Test Doctor',
            'email' => $testEmail,
            'specialty' => 'Neurology',
            'license_number' => 'MD67890',
            'experience_years' => '10-15'
        ]);
        break;
        
    case '4':
        echo "Testing: Password Reset Email\n";
        $result = $mailer->sendPasswordResetEmail(
            $testEmail,
            'Test User',
            'test-reset-token-' . bin2hex(random_bytes(16))
        );
        break;
        
    case '5':
        echo "Testing: Email Verification\n";
        $result = $mailer->sendVerificationEmail(
            $testEmail,
            'Test User',
            'test-verify-token-' . bin2hex(random_bytes(16))
        );
        break;
        
    default:
        echo "✗ Invalid choice\n";
        exit(1);
}

echo "\n====================================\n";
if ($result && $result['success']) {
    echo "✓ SUCCESS!\n";
    echo "  Email sent successfully to: " . $testEmail . "\n";
    echo "  Message: " . $result['message'] . "\n";
    echo "\nCheck your inbox (and spam folder) for the test email.\n";
} else {
    echo "✗ FAILED!\n";
    echo "  Error: " . ($result['message'] ?? 'Unknown error') . "\n";
    echo "\nTroubleshooting tips:\n";
    echo "  1. Check your SMTP credentials in mail/config.php\n";
    echo "  2. Ensure you're using an App Password for Gmail\n";
    echo "  3. Check that port 587 is not blocked by firewall\n";
    echo "  4. Verify 2FA is enabled on your Gmail account\n";
}
echo "====================================\n";
