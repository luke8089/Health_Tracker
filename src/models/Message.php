<?php
/**
 * Message Model
 * Health Tracker Application
 */

require_once __DIR__ . '/../helpers/Database.php';

class Message {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function create($senderId, $recipientId, $subject, $body) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, body, is_read) 
                VALUES (?, ?, ?, ?, 0)
            ");
            
            return $stmt->execute([$senderId, $recipientId, $subject, $body]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    m.*,
                    u1.name as sender_name,
                    u1.email as sender_email,
                    u2.name as recipient_name,
                    u2.email as recipient_email
                FROM messages m
                JOIN users u1 ON m.sender_id = u1.id
                JOIN users u2 ON m.recipient_id = u2.id
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getUserMessages($userId, $type = 'all', $limit = null, $offset = null) {
        try {
            $sql = "
                SELECT 
                    m.*,
                    u1.name as sender_name,
                    u1.email as sender_email,
                    u2.name as recipient_name,
                    u2.email as recipient_email
                FROM messages m
                JOIN users u1 ON m.sender_id = u1.id
                JOIN users u2 ON m.recipient_id = u2.id
            ";
            $params = [];

            switch ($type) {
                case 'sent':
                    $sql .= " WHERE m.sender_id = ?";
                    $params[] = $userId;
                    break;
                case 'received':
                    $sql .= " WHERE m.recipient_id = ?";
                    $params[] = $userId;
                    break;
                case 'unread':
                    $sql .= " WHERE m.recipient_id = ? AND m.is_read = 0";
                    $params[] = $userId;
                    break;
                default:
                    $sql .= " WHERE m.sender_id = ? OR m.recipient_id = ?";
                    $params[] = $userId;
                    $params[] = $userId;
                    break;
            }

            $sql .= " ORDER BY m.created_at DESC";

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

    public function markAsRead($messageId, $userId) {
        try {
            // Only allow recipient to mark message as read
            $stmt = $this->db->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE id = ? AND recipient_id = ?
            ");
            
            return $stmt->execute([$messageId, $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function markAllAsRead($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE recipient_id = ? AND is_read = 0
            ");
            
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function delete($messageId, $userId) {
        try {
            // Only allow sender or recipient to delete message
            $stmt = $this->db->prepare("
                DELETE FROM messages 
                WHERE id = ? AND (sender_id = ? OR recipient_id = ?)
            ");
            
            return $stmt->execute([$messageId, $userId, $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getUnreadCount($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM messages 
                WHERE recipient_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch()['count'];
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function getConversation($userId, $otherUserId, $limit = 20) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    m.*,
                    u1.name as sender_name,
                    u1.email as sender_email,
                    u2.name as recipient_name,
                    u2.email as recipient_email
                FROM messages m
                JOIN users u1 ON m.sender_id = u1.id
                JOIN users u2 ON m.recipient_id = u2.id
                WHERE (m.sender_id = ? AND m.recipient_id = ?) 
                   OR (m.sender_id = ? AND m.recipient_id = ?)
                ORDER BY m.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $otherUserId, $otherUserId, $userId, $limit]);
            return array_reverse($stmt->fetchAll()); // Reverse to show oldest first
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getConversationList($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN m.sender_id = ? THEN m.recipient_id
                        ELSE m.sender_id
                    END as contact_id,
                    CASE 
                        WHEN m.sender_id = ? THEN u2.name
                        ELSE u1.name
                    END as contact_name,
                    m.subject,
                    m.body,
                    m.created_at,
                    m.is_read,
                    COUNT(CASE WHEN m2.recipient_id = ? AND m2.is_read = 0 THEN 1 END) as unread_count
                FROM messages m
                JOIN users u1 ON m.sender_id = u1.id
                JOIN users u2 ON m.recipient_id = u2.id
                LEFT JOIN messages m2 ON (
                    (m2.sender_id = CASE WHEN m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END)
                    AND m2.recipient_id = ?
                )
                WHERE m.sender_id = ? OR m.recipient_id = ?
                GROUP BY 
                    CASE WHEN m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END,
                    CASE WHEN m.sender_id = ? THEN u2.name ELSE u1.name END
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([
                $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId
            ]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function searchMessages($userId, $query) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    m.*,
                    u1.name as sender_name,
                    u1.email as sender_email,
                    u2.name as recipient_name,
                    u2.email as recipient_email
                FROM messages m
                JOIN users u1 ON m.sender_id = u1.id
                JOIN users u2 ON m.recipient_id = u2.id
                WHERE (m.sender_id = ? OR m.recipient_id = ?)
                AND (m.subject LIKE ? OR m.body LIKE ? OR u1.name LIKE ? OR u2.name LIKE ?)
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([
                $userId, $userId, 
                "%$query%", "%$query%", "%$query%", "%$query%"
            ]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getMessageStats($userId = null) {
        try {
            $stats = [];

            if ($userId) {
                // User-specific stats
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM messages 
                    WHERE sender_id = ?
                ");
                $stmt->execute([$userId]);
                $stats['sent'] = $stmt->fetch()['count'];

                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM messages 
                    WHERE recipient_id = ?
                ");
                $stmt->execute([$userId]);
                $stats['received'] = $stmt->fetch()['count'];

                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM messages 
                    WHERE recipient_id = ? AND is_read = 0
                ");
                $stmt->execute([$userId]);
                $stats['unread'] = $stmt->fetch()['count'];
            } else {
                // Global stats
                $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM messages");
                $stmt->execute();
                $stats['total'] = $stmt->fetch()['count'];

                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM messages 
                    WHERE is_read = 0
                ");
                $stmt->execute();
                $stats['total_unread'] = $stmt->fetch()['count'];

                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM messages 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ");
                $stmt->execute();
                $stats['this_week'] = $stmt->fetch()['count'];
            }

            return $stats;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function sendWelcomeMessage($userId, $adminId = 1) {
        $subject = "Welcome to Health Tracker!";
        $body = "Welcome to Health Tracker! We're excited to help you on your wellness journey. Start by taking your first health assessment to get personalized recommendations. If you have any questions, don't hesitate to reach out to our support team.";
        
        return $this->create($adminId, $userId, $subject, $body);
    }

    public function sendAssessmentNotification($userId, $doctorId, $assessmentId) {
        $subject = "New Health Assessment - Action Required";
        $body = "A user has completed a high-severity health assessment and has been connected to you for follow-up. Please review their assessment and provide appropriate guidance. Assessment ID: $assessmentId";
        
        return $this->create($userId, $doctorId, $subject, $body);
    }
}
?>