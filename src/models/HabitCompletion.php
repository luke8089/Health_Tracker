        <?php
/**
 * HabitCompletion Model
 * Handles proof-of-completion uploads and verification workflow
 */

require_once __DIR__ . '/../helpers/Database.php';

class HabitCompletion {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * Submit proof of habit completion
     */
    public function submitProof($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO habit_completions 
                (habit_id, user_id, completion_date, proof_type, proof_path, description, verification_status, points_awarded) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', 0)
            ");
            
            $result = $stmt->execute([
                $data['habit_id'],
                $data['user_id'],
                $data['completion_date'],
                $data['proof_type'],
                $data['proof_path'],
                $data['description'] ?? null
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'completion_id' => $this->db->lastInsertId(),
                    'message' => 'Proof submitted successfully. Awaiting verification.'
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to submit proof'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get pending verifications for doctors/instructors
     * Now filters by doctor connection - doctors only see habits from connected users
     */
    public function getPendingVerifications($limit = 50, $doctorId = null) {
        try {
            // If doctor_id is provided, only show habits from connected users
            if ($doctorId !== null) {
                $stmt = $this->db->prepare("
                    SELECT 
                        hc.*,
                        h.name as habit_name,
                        h.frequency,
                        u.name as user_name,
                        u.email as user_email,
                        u.avatar as user_avatar
                    FROM habit_completions hc
                    JOIN habits h ON hc.habit_id = h.id
                    JOIN users u ON hc.user_id = u.id
                    INNER JOIN user_doctor_connections udc ON u.id = udc.user_id
                    WHERE hc.verification_status = 'pending'
                    AND udc.doctor_id = ?
                    AND udc.status = 'active'
                    ORDER BY hc.created_at ASC
                    LIMIT ?
                ");
                $stmt->execute([$doctorId, $limit]);
            } else {
                // Fallback for non-doctor users (admins, etc.)
                $stmt = $this->db->prepare("
                    SELECT 
                        hc.*,
                        h.name as habit_name,
                        h.frequency,
                        u.name as user_name,
                        u.email as user_email,
                        u.avatar as user_avatar
                    FROM habit_completions hc
                    JOIN habits h ON hc.habit_id = h.id
                    JOIN users u ON hc.user_id = u.id
                    WHERE hc.verification_status = 'pending'
                    ORDER BY hc.created_at ASC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get user's completion submissions
     */
    public function getUserCompletions($userId, $status = null) {
        try {
            $sql = "
                SELECT 
                    hc.*,
                    h.name as habit_name,
                    h.frequency,
                    v.name as verifier_name
                FROM habit_completions hc
                JOIN habits h ON hc.habit_id = h.id
                LEFT JOIN users v ON hc.verified_by = v.id
                WHERE hc.user_id = ?
            ";
            
            $params = [$userId];
            
            if ($status) {
                $sql .= " AND hc.verification_status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY hc.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Verify or reject a completion submission
     */
    public function verifyCompletion($completionId, $verifierId, $action, $notes = null) {
        try {
            $this->db->beginTransaction();
            
            // Get completion details
            $stmt = $this->db->prepare("
                SELECT hc.*, h.frequency 
                FROM habit_completions hc
                JOIN habits h ON hc.habit_id = h.id
                WHERE hc.id = ? AND hc.verification_status = 'pending'
            ");
            $stmt->execute([$completionId]);
            $completion = $stmt->fetch();
            
            if (!$completion) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Completion not found or already verified'];
            }
            
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $pointsAwarded = 0;
            
            // Calculate points if approved
            if ($action === 'approve') {
                $pointsAwarded = $this->calculatePoints($completion['frequency']);
                
                // Update habit streak and points
                $stmt = $this->db->prepare("
                    UPDATE habits 
                    SET streak = streak + 1,
                        points = points + ?,
                        completed_today = 1,
                        last_completed_date = ?,
                        missed_days = 0,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([
                    $pointsAwarded,
                    $completion['completion_date'],
                    $completion['habit_id']
                ]);
                
                // Check if habit is completed
                $stmt = $this->db->prepare("
                    SELECT streak, target_days FROM habits WHERE id = ?
                ");
                $stmt->execute([$completion['habit_id']]);
                $habit = $stmt->fetch();
                
                if ($habit && $habit['streak'] >= $habit['target_days']) {
                    $bonusPoints = $pointsAwarded * 2; // Double bonus for completion
                    $stmt = $this->db->prepare("
                        UPDATE habits 
                        SET status = 'completed',
                            points = points + ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$bonusPoints, $completion['habit_id']]);
                    $pointsAwarded += $bonusPoints;
                }
                
                // Update user rewards
                $this->updateUserRewards($completion['user_id']);
            }
            
            // Update completion record
            $stmt = $this->db->prepare("
                UPDATE habit_completions 
                SET verification_status = ?,
                    verified_by = ?,
                    verified_at = CURRENT_TIMESTAMP,
                    verification_notes = ?,
                    points_awarded = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $status,
                $verifierId,
                $notes,
                $pointsAwarded,
                $completionId
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => $action === 'approve' 
                    ? "Completion approved! {$pointsAwarded} points awarded." 
                    : 'Completion rejected.',
                'points_awarded' => $pointsAwarded
            ];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get completion by ID
     */
    public function getCompletionById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    hc.*,
                    h.name as habit_name,
                    h.frequency,
                    u.name as user_name,
                    u.email as user_email,
                    v.name as verifier_name
                FROM habit_completions hc
                JOIN habits h ON hc.habit_id = h.id
                JOIN users u ON hc.user_id = u.id
                LEFT JOIN users v ON hc.verified_by = v.id
                WHERE hc.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get completion statistics
     */
    public function getCompletionStats($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_submissions,
                    SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(points_awarded) as total_points_earned
                FROM habit_completions
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Delete completion (only if pending)
     */
    public function deleteCompletion($id, $userId) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM habit_completions 
                WHERE id = ? AND user_id = ? AND verification_status = 'pending'
            ");
            return $stmt->execute([$id, $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Calculate points based on frequency
     */
    private function calculatePoints($frequency) {
        switch ($frequency) {
            case 'daily':
                return 10;
            case 'weekly':
                return 30;
            case 'monthly':
                return 100;
            default:
                return 10;
        }
    }

    /**
     * Update user reward points
     */
    private function updateUserRewards($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    (SELECT COALESCE(SUM(points), 0) FROM habits WHERE user_id = ?) +
                    (SELECT COALESCE(SUM(calories_burned / 10), 0) FROM activities WHERE user_id = ?) as total_points
            ");
            $stmt->execute([$userId, $userId]);
            $totalPoints = $stmt->fetch()['total_points'];

            // Determine tier
            $tier = 'bronze';
            if ($totalPoints >= 1000) {
                $tier = 'gold';
            } elseif ($totalPoints >= 500) {
                $tier = 'silver';
            }

            // Update rewards table
            $stmt = $this->db->prepare("
                INSERT INTO rewards (user_id, points, tier) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE points = ?, tier = ?
            ");
            $stmt->execute([$userId, $totalPoints, $tier, $totalPoints, $tier]);
        } catch (PDOException $e) {
            // Silent fail
        }
    }

    /**
     * Check if habit has pending verification for today
     */
    public function hasPendingVerificationToday($habitId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM habit_completions
                WHERE habit_id = ? 
                AND user_id = ?
                AND completion_date = CURDATE()
                AND verification_status = 'pending'
            ");
            $stmt->execute([$habitId, $userId]);
            return $stmt->fetch()['count'] > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
