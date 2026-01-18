<?php
/**
 * Doctor Model
 * Health Tracker Application
 */

require_once __DIR__ . '/../helpers/Database.php';

class Doctor {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function create($userId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO doctors (id, specialty, license_number, availability) 
                VALUES (?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $userId,
                $data['specialty'],
                $data['license_number'],
                $data['availability'] ?? 'offline'
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
                JOIN doctors d ON u.id = d.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE doctors 
                SET specialty = ?, license_number = ?, availability = ? 
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['specialty'],
                $data['license_number'],
                $data['availability'],
                $id
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateAvailability($id, $availability) {
        try {
            $stmt = $this->db->prepare("UPDATE doctors SET availability = ? WHERE id = ?");
            return $stmt->execute([$availability, $id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getAllDoctors($availability = null) {
        try {
            $sql = "
                SELECT u.*, d.specialty, d.license_number, d.availability 
                FROM users u 
                JOIN doctors d ON u.id = d.id
            ";
            $params = [];

            if ($availability) {
                $sql .= " WHERE d.availability = ?";
                $params[] = $availability;
            }

            $sql .= " ORDER BY u.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getAvailableDoctors($specialty = null) {
        try {
            $sql = "
                SELECT u.*, d.specialty, d.license_number 
                FROM users u 
                JOIN doctors d ON u.id = d.id 
                WHERE d.availability = 'available'
            ";
            $params = [];

            if ($specialty) {
                $sql .= " AND d.specialty = ?";
                $params[] = $specialty;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function addAvailability($doctorId, $day, $startTime, $endTime) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO doctor_availability (doctor_id, day, start_time, end_time) 
                VALUES (?, ?, ?, ?)
            ");
            
            return $stmt->execute([$doctorId, $day, $startTime, $endTime]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getAvailabilitySchedule($doctorId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM doctor_availability 
                WHERE doctor_id = ? 
                ORDER BY FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')
            ");
            $stmt->execute([$doctorId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function removeAvailability($availabilityId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM doctor_availability WHERE id = ?");
            return $stmt->execute([$availabilityId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getConnectedUsers($doctorId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, udc.status, udc.created_at as connection_date,
                       a.severity, a.score
                FROM users u
                JOIN user_doctor_connections udc ON u.id = udc.user_id
                JOIN assessments a ON udc.assessment_id = a.id
                WHERE udc.doctor_id = ? AND udc.status = 'active'
                ORDER BY udc.created_at DESC
            ");
            $stmt->execute([$doctorId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getDoctorStats($doctorId) {
        try {
            $stats = [];

            // Connected users count
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM user_doctor_connections 
                WHERE doctor_id = ? AND status = 'active'
            ");
            $stmt->execute([$doctorId]);
            $stats['connected_users'] = $stmt->fetch()['count'];

            // Total messages sent
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM messages 
                WHERE sender_id = ?
            ");
            $stmt->execute([$doctorId]);
            $stats['messages_sent'] = $stmt->fetch()['count'];

            // Unread messages
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM messages 
                WHERE recipient_id = ? AND is_read = 0
            ");
            $stmt->execute([$doctorId]);
            $stats['unread_messages'] = $stmt->fetch()['count'];

            // Completed connections
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM user_doctor_connections 
                WHERE doctor_id = ? AND status = 'completed'
            ");
            $stmt->execute([$doctorId]);
            $stats['completed_connections'] = $stmt->fetch()['count'];

            return $stats;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function connectToUser($userId, $doctorId, $assessmentId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_doctor_connections (user_id, doctor_id, assessment_id, status) 
                VALUES (?, ?, ?, 'active')
            ");
            
            return $stmt->execute([$userId, $doctorId, $assessmentId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateConnectionStatus($connectionId, $status) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_doctor_connections 
                SET status = ? 
                WHERE id = ?
            ");
            
            return $stmt->execute([$status, $connectionId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function findAvailableDoctorForAssessment($severity, $specialty = null) {
        try {
            $sql = "
                SELECT u.*, d.specialty, d.license_number 
                FROM users u 
                JOIN doctors d ON u.id = d.id 
                WHERE d.availability = 'available'
            ";
            $params = [];

            if ($specialty) {
                $sql .= " AND d.specialty = ?";
                $params[] = $specialty;
            }

            // For high severity, prioritize certain specialties
            if ($severity === 'high') {
                $sql .= " ORDER BY CASE 
                    WHEN d.specialty IN ('General Practice', 'Emergency Medicine') THEN 1
                    WHEN d.specialty = 'Mental Health' THEN 2
                    ELSE 3 
                END";
            }

            $sql .= " LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>