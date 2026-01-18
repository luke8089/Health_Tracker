<?php
/**
 * Utility Helper Functions
 * Health Tracker Application
 */

class Utils {
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email address
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number (basic)
     */
    public static function validatePhone($phone) {
        return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $phone);
    }

    /**
     * Generate secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Format date for display
     */
    public static function formatDate($date, $format = 'M j, Y') {
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        return $date->format($format);
    }

    /**
     * Calculate age from birthdate
     */
    public static function calculateAge($birthdate) {
        $today = new DateTime();
        $birth = new DateTime($birthdate);
        return $today->diff($birth)->y;
    }

    /**
     * Calculate health score based on assessment responses
     */
    public static function calculateHealthScore($responses) {
        $score = 0;
        $maxScore = 0;

        foreach ($responses as $key => $value) {
            $maxScore += 10; // Assuming max score per question is 10
            $score += (int)$value;
        }

        return ($maxScore > 0) ? round(($score / $maxScore) * 100) : 0;
    }

    /**
     * Determine severity based on health score
     */
    public static function getSeverity($score) {
        if ($score >= 80) {
            return 'high';
        } elseif ($score >= 50) {
            return 'moderate';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate points based on activity
     */
    public static function calculateActivityPoints($type, $duration) {
        $pointsPerMinute = [
            'walking' => 2,
            'running' => 5,
            'cycling' => 4,
            'swimming' => 6,
            'yoga' => 3,
            'weight_training' => 4,
            'dancing' => 3,
            'default' => 2
        ];

        $multiplier = $pointsPerMinute[strtolower($type)] ?? $pointsPerMinute['default'];
        return $duration * $multiplier;
    }

    /**
     * Calculate calories burned
     */
    public static function calculateCalories($type, $duration, $weight = 70) {
        // MET values for different activities
        $metValues = [
            'walking' => 3.8,
            'running' => 11.0,
            'cycling' => 8.0,
            'swimming' => 8.0,
            'yoga' => 3.0,
            'weight_training' => 6.0,
            'dancing' => 5.0,
            'default' => 4.0
        ];

        $met = $metValues[strtolower($type)] ?? $metValues['default'];
        return round(($met * $weight * ($duration / 60)));
    }

    /**
     * Get reward tier based on points
     */
    public static function getRewardTier($points) {
        if ($points >= 1000) {
            return 'gold';
        } elseif ($points >= 500) {
            return 'silver';
        } else {
            return 'bronze';
        }
    }

    /**
     * Upload file with validation
     */
    public static function uploadFile($file, $uploadDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        $fileName = $file['name'];
        $fileTmp = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];

        if ($fileError !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error'];
        }

        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }

        if ($fileSize > 5 * 1024 * 1024) { // 5MB max
            return ['success' => false, 'message' => 'File too large'];
        }

        $newFileName = uniqid() . '.' . $fileExt;
        $uploadPath = $uploadDir . '/' . $newFileName;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($fileTmp, $uploadPath)) {
            return ['success' => true, 'filename' => $newFileName, 'path' => $uploadPath];
        }

        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }

    /**
     * Send JSON response
     */
    public static function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirect with message
     */
    public static function redirect($url, $message = null, $type = 'info') {
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        header("Location: $url");
        exit;
    }

    /**
     * Get and clear flash message
     */
    public static function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            $type = $_SESSION['flash_type'] ?? 'info';
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
            return ['message' => $message, 'type' => $type];
        }
        return null;
    }

    /**
     * Generate pagination data
     */
    public static function paginate($currentPage, $totalRecords, $recordsPerPage = 10) {
        $totalPages = ceil($totalRecords / $recordsPerPage);
        $offset = ($currentPage - 1) * $recordsPerPage;
        
        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'records_per_page' => $recordsPerPage,
            'offset' => $offset,
            'has_prev' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages
        ];
    }
}
?>