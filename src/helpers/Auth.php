<?php
/**
 * Authentication Helper
 * Health Tracker Application
 */

require_once __DIR__ . '/Database.php';

class Auth {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->startSession();
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, role, email, password_hash, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['last_activity'] = time();
                
                // Store session in database
                $this->storeSession($user['id']);
                
                return [
                    'success' => true,
                    'user' => $user,
                    'redirect' => $this->getRedirectUrl($user['role'])
                ];
            }

            return ['success' => false, 'message' => 'Invalid credentials'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error'];
        }
    }

    public function register($data) {
        try {
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already exists'];
            }

            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $this->db->prepare("
                INSERT INTO users (role, email, password_hash, name, phone, bio) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['role'] ?? 'user',
                $data['email'],
                $passwordHash,
                $data['name'],
                $data['phone'] ?? null,
                $data['bio'] ?? null
            ]);

            $userId = $this->db->lastInsertId();

            // If registering as doctor, add to doctors table
            if (($data['role'] ?? 'user') === 'doctor') {
                $stmt = $this->db->prepare("
                    INSERT INTO doctors (id, specialty, license_number, availability) 
                    VALUES (?, ?, ?, 'offline')
                ");
                $stmt->execute([
                    $userId,
                    $data['specialty'] ?? '',
                    $data['license_number'] ?? ''
                ]);
            }

            // Create initial rewards record for regular users
            if (($data['role'] ?? 'user') === 'user') {
                $stmt = $this->db->prepare("
                    INSERT INTO rewards (user_id, points, tier) 
                    VALUES (?, 0, 'bronze')
                ");
                $stmt->execute([$userId]);
            }

            return ['success' => true, 'message' => 'Registration successful'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }

    public function logout() {
        try {
            if (isset($_SESSION['user_id'])) {
                // Remove session from database
                $stmt = $this->db->prepare("DELETE FROM sessions WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                // Also delete by session ID
                $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
                $stmt->execute([session_id()]);
            }
        } catch (Exception $e) {
            // If database cleanup fails, continue with session destruction
            error_log("Logout database cleanup failed: " . $e->getMessage());
        }

        // Unset all session variables
        $_SESSION = array();
        
        // Delete the session cookie
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
            // Also try with root path to ensure deletion
            setcookie(session_name(), '', time() - 42000, '/');
        }
        
        // Destroy the session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Use the globally defined base path
        $basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
        return ['success' => true, 'redirect' => $basePath . '/landing_page/index.php'];
    }

    public function isLoggedIn() {
        // Ensure session is started
        $this->startSession();
        
        // Check if user_id is set
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Optional: Check session timeout (30 minutes of inactivity)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            // Session expired due to inactivity
            $this->logout();
            return false;
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        
        return true;
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT u.*, d.specialty, d.license_number, d.availability 
                FROM users u 
                LEFT JOIN doctors d ON u.id = d.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }

    public function hasRole($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            // Use the globally defined base path
            $basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
            $landingPage = $basePath . '/landing_page/index.php';
            header('Location: ' . $landingPage);
            exit;
        }
    }

    public function requireRole($role) {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            // Redirect to appropriate dashboard based on current role
            $redirectUrl = $this->getRedirectUrl($_SESSION['user_role'] ?? 'user');
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    private function getRedirectUrl($role) {
        // Use the globally defined base path
        $basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
        
        switch ($role) {
            case 'admin':
                return $basePath . '/admin/dashboard.php';
            case 'doctor':
                return $basePath . '/doctor/dashboard.php';
            case 'user':
            default:
                return $basePath . '/public/dashboard.php';
        }
    }

    private function storeSession($userId) {
        $sessionId = session_id();
        $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours

        $stmt = $this->db->prepare("
            INSERT INTO sessions (id, user_id, expires_at) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)
        ");
        $stmt->execute([$sessionId, $userId, $expiresAt]);
    }

    public function cleanExpiredSessions() {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE expires_at < NOW()");
        $stmt->execute();
    }
}
?>