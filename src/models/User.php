<?php
/**
 * User Model
 * Health Tracker Application
 */

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Utils.php';

class User {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (role, email, password_hash, name, phone, bio, avatar) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['role'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['name'],
                $data['phone'] ?? null,
                $data['bio'] ?? null,
                $data['avatar'] ?? null
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, d.specialty, d.license_number, d.availability 
                FROM users u 
                LEFT JOIN doctors d ON u.id = d.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function findByEmail($email) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                if ($key !== 'id' && $key !== 'password') {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }

            if (isset($data['password']) && !empty($data['password'])) {
                $fields[] = "password_hash = ?";
                $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $values[] = $id;

            $stmt = $this->db->prepare("
                UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            
            return $stmt->execute($values);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getAllUsers($role = null, $limit = null, $offset = null) {
        try {
            $sql = "SELECT u.*, d.specialty FROM users u LEFT JOIN doctors d ON u.id = d.id";
            $params = [];

            if ($role) {
                $sql .= " WHERE u.role = ?";
                $params[] = $role;
            }

            $sql .= " ORDER BY u.created_at DESC";

            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
                
                if ($offset) {
                    $sql .= " OFFSET ?";
                    $params[] = $offset;
                }
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getUserStats($userId) {
        try {
            $stats = [];

            // Get habits count (active habits only)
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM habits WHERE user_id = ? AND status = 'active'");
            $stmt->execute([$userId]);
            $stats['habits'] = $stmt->fetch()['count'];

            // Get activities count
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM activities WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['activities'] = $stmt->fetch()['count'];

            // Get total points (sum all points from rewards table)
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(points), 0) as total_points FROM rewards WHERE user_id = ?");
            $stmt->execute([$userId]);
            $totalPoints = $stmt->fetch()['total_points'];
            $stats['points'] = $totalPoints;
            
            // Calculate tier based on total points
            if ($totalPoints >= 1000) {
                $stats['tier'] = 'gold';
            } elseif ($totalPoints >= 500) {
                $stats['tier'] = 'silver';
            } else {
                $stats['tier'] = 'bronze';
            }

            // Get assessments count
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM assessments WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['assessments'] = $stmt->fetch()['count'];

            return $stats;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function updateAvatar($userId, $avatarPath) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            return $stmt->execute([$avatarPath, $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function searchUsers($query, $role = null) {
        try {
            $sql = "
                SELECT u.*, d.specialty 
                FROM users u 
                LEFT JOIN doctors d ON u.id = d.id 
                WHERE (u.name LIKE ? OR u.email LIKE ?)
            ";
            $params = ["%$query%", "%$query%"];

            if ($role) {
                $sql .= " AND u.role = ?";
                $params[] = $role;
            }

            $sql .= " ORDER BY u.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getUserCount($role = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM users";
            $params = [];

            if ($role) {
                $sql .= " WHERE role = ?";
                $params[] = $role;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch()['count'];
        } catch (PDOException $e) {
            return 0;
        }
    }
}
?>