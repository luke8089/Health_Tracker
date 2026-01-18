<?php
/**
 * Email Configuration
 * EXAMPLE FILE - Copy this to mail/config.php and update with your SMTP settings
 */

return [
    'smtp' => [
        'host' => 'smtp.gmail.com', // Your SMTP host
        'username' => 'your-email@gmail.com', // Your email address
        'password' => 'your-app-password', // Your SMTP password or app-specific password
        'port' => 587,
        'encryption' => 'tls', // 'tls' or 'ssl'
        'auth' => true
    ],
    'from' => [
        'email' => 'your-email@gmail.com', // Sender email
        'name' => 'Health Tracker' // Sender name
    ],
    'admin' => [
        'email' => 'admin@example.com', // Admin email for notifications
        'name' => 'Health Tracker Admin'
    ],
    'base_url' => 'http://localhost/health-tracker' // Update with your application URL
];
