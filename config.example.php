<?php

/**
 * Configuration file for Health Tracker application
 * EXAMPLE FILE - Copy this to config.php and update with your settings
 */

class Config {
    // Database Configuration
    const DB_HOST = 'localhost';
    const DB_NAME = 'health_tracker';
    const DB_USER = 'root';
    const DB_PASS = ''; // Add your database password here
    
    // Application Settings
    const APP_NAME = 'Health Tracker';
    const APP_VERSION = '1.0.0';
    const APP_URL = 'http://localhost:8000'; // Update with your application URL
    
    // Security Settings
    const HASH_ALGORITHM = 'sha256';
    const SESSION_NAME = 'health_tracker_session';
    const CSRF_TOKEN_NAME = 'csrf_token';
    
    // Upload Settings
    const UPLOAD_MAX_SIZE = 5242880; // 5MB in bytes
    const UPLOAD_ALLOWED_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    const UPLOAD_PATH = '/public/assets/uploads/';
    
    // Email Settings (for future features)
    const MAIL_HOST = ''; // Add your SMTP host
    const MAIL_PORT = 587;
    const MAIL_USERNAME = ''; // Add your email username
    const MAIL_PASSWORD = ''; // Add your email password
    const MAIL_FROM_ADDRESS = 'noreply@healthtracker.com';
    const MAIL_FROM_NAME = 'Health Tracker';
    
    // API Settings
    const API_RATE_LIMIT = 100; // requests per hour
    const API_TIMEOUT = 30; // seconds
    
    // AI Chatbot Configuration
    // Get your free API key from: https://huggingface.co/settings/tokens
    // Leave empty to use rule-based responses only (no API key required)
    const HUGGINGFACE_API_KEY = ''; // Optional: Add your Hugging Face API key here for better AI responses
    const AI_MODEL = 'microsoft/DialoGPT-medium'; // Free conversational model
    const AI_FALLBACK_ENABLED = true; // Use rule-based responses if API fails
    
    // Health Scoring Configuration
    const HEALTH_SCORE_WEIGHTS = [
        'physical' => 0.3,
        'mental' => 0.25,
        'nutrition' => 0.2,
        'sleep' => 0.15,
        'social' => 0.1
    ];
    
    // Reward System Configuration
    const POINTS_PER_HABIT = 10;
    const POINTS_PER_ACTIVITY = 20;
    const POINTS_PER_ASSESSMENT = 50;
    
    const TIER_THRESHOLDS = [
        'Bronze' => 0,
        'Silver' => 1000,
        'Gold' => 5000,
        'Platinum' => 10000
    ];
    
    // Doctor Assignment Configuration
    const HIGH_RISK_THRESHOLD = 30; // Auto-assign doctor if health score below this
    const MAX_PATIENTS_PER_DOCTOR = 100;
    
    // Development Settings
    const DEBUG_MODE = true;
    const LOG_LEVEL = 'INFO'; // DEBUG, INFO, WARNING, ERROR
    const LOG_PATH = '/logs/';
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        return defined('self::' . $key) ? constant('self::' . $key) : $default;
    }
    
    /**
     * Check if application is in debug mode
     */
    public static function isDebug() {
        return self::DEBUG_MODE;
    }
    
    /**
     * Get database configuration as array
     */
    public static function getDbConfig() {
        return [
            'host' => self::DB_HOST,
            'dbname' => self::DB_NAME,
            'username' => self::DB_USER,
            'password' => self::DB_PASS
        ];
    }
    
    /**
     * Get environment-specific database configuration
     */
    public static function getDbConfigForEnv($env = null) {
        $env = $env ?? ($_ENV['APP_ENV'] ?? 'development');
        
        switch ($env) {
            case 'docker':
                return [
                    'host' => 'db',
                    'dbname' => 'health_tracker',
                    'username' => 'health_user',
                    'password' => 'health_password'
                ];
                
            case 'testing':
                return [
                    'host' => self::DB_HOST,
                    'dbname' => 'health_tracker_test',
                    'username' => self::DB_USER,
                    'password' => self::DB_PASS
                ];
                
            default:
                return self::getDbConfig();
        }
    }
    
    /**
     * Get upload configuration
     */
    public static function getUploadConfig() {
        return [
            'max_size' => self::UPLOAD_MAX_SIZE,
            'allowed_types' => self::UPLOAD_ALLOWED_TYPES,
            'path' => self::UPLOAD_PATH
        ];
    }
}
