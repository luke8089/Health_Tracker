<?php
/**
 * Habit Model
 * Health Tracker Application
 */

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Utils.php';

class Habit {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function create($userId, $data) {
        try {
            $startDate = date('Y-m-d');
            $targetDays = $data['target_days'] ?? 30;
            $endDate = date('Y-m-d', strtotime("+{$targetDays} days"));
            
            $stmt = $this->db->prepare("
                INSERT INTO habits (user_id, name, frequency, target_days, streak, points, status, start_date, end_date, completed_today, last_completed_date, missed_days) 
                VALUES (?, ?, ?, ?, 0, 0, 'active', ?, ?, 0, NULL, 0)
            ");
            
            return $stmt->execute([
                $userId,
                $data['name'],
                $data['frequency'] ?? 'daily',
                $targetDays,
                $startDate,
                $endDate
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT h.*, u.name as user_name 
                FROM habits h 
                JOIN users u ON h.user_id = u.id 
                WHERE h.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getUserHabits($userId) {
        try {
            // Update habit statuses before fetching
            $this->updateHabitStatuses($userId);
            
            $stmt = $this->db->prepare("
                SELECT * FROM habits 
                WHERE user_id = ? 
                ORDER BY status ASC, created_at DESC
            ");
            $stmt->execute([$userId]);
            $habits = $stmt->fetchAll();
            
            // Calculate days remaining and completion percentage for each habit
            foreach ($habits as &$habit) {
                $habit['days_completed'] = $habit['streak'];
                $habit['days_remaining'] = max(0, $habit['target_days'] - $habit['streak']);
                $habit['completion_percentage'] = $habit['target_days'] > 0 
                    ? round(($habit['streak'] / $habit['target_days']) * 100) 
                    : 0;
                $habit['is_overdue'] = !$habit['completed_today'] && 
                    $habit['frequency'] === 'daily' && 
                    $habit['status'] === 'active' &&
                    (!$habit['last_completed_date'] || $habit['last_completed_date'] < date('Y-m-d'));
            }
            
            return $habits;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                if ($key !== 'id' && $key !== 'user_id') {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }

            $values[] = $id;

            $stmt = $this->db->prepare("
                UPDATE habits SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            
            return $stmt->execute($values);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM habits WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function incrementStreak($id) {
        try {
            // This method is deprecated - use HabitCompletion model for proof-based verification
            return ['success' => false, 'message' => 'Please submit proof of completion for verification'];
        } catch (PDOException $e) {
            return false;
        }
    }

    public function resetStreak($id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE habits 
                SET streak = 0, 
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getHabitStats($userId) {
        try {
            $stats = [];

            // Total habits
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM habits WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['total_habits'] = $stmt->fetch()['count'];

            // Active streaks (streak > 0)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM habits 
                WHERE user_id = ? AND streak > 0
            ");
            $stmt->execute([$userId]);
            $stats['active_streaks'] = $stmt->fetch()['count'];

            // Total points from habits
            $stmt = $this->db->prepare("SELECT SUM(points) as total FROM habits WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['total_points'] = $stmt->fetch()['total'] ?? 0;

            // Longest streak
            $stmt = $this->db->prepare("
                SELECT MAX(streak) as max_streak 
                FROM habits 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $stats['longest_streak'] = $stmt->fetch()['max_streak'] ?? 0;

            // Habits by frequency
            $stmt = $this->db->prepare("
                SELECT frequency, COUNT(*) as count 
                FROM habits 
                WHERE user_id = ? 
                GROUP BY frequency
            ");
            $stmt->execute([$userId]);
            
            $frequencyStats = $stmt->fetchAll();
            $stats['by_frequency'] = [];
            foreach ($frequencyStats as $stat) {
                $stats['by_frequency'][$stat['frequency']] = $stat['count'];
            }

            return $stats;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getTopHabits($userId, $limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM habits 
                WHERE user_id = ? 
                ORDER BY streak DESC, points DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getAllHabits($limit = null, $offset = null) {
        try {
            $sql = "
                SELECT h.*, u.name as user_name 
                FROM habits h 
                JOIN users u ON h.user_id = u.id 
                ORDER BY h.created_at DESC
            ";
            $params = [];

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

    public function searchHabits($userId, $query) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM habits 
                WHERE user_id = ? AND name LIKE ? 
                ORDER BY name
            ");
            $stmt->execute([$userId, "%$query%"]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getHabitTrends($userId, $days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(updated_at) as date,
                    COUNT(*) as habits_updated,
                    SUM(CASE WHEN streak > 0 THEN 1 ELSE 0 END) as active_streaks
                FROM habits 
                WHERE user_id = ? 
                AND updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(updated_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$userId, $days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function checkHabitCompletion($userId, $habitId) {
        try {
            // Check if habit was marked as completed today
            $stmt = $this->db->prepare("
                SELECT * FROM habits 
                WHERE id = ? AND user_id = ? 
                AND DATE(updated_at) = CURDATE()
            ");
            $stmt->execute([$habitId, $userId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function getHabitUserId($habitId) {
        try {
            $stmt = $this->db->prepare("SELECT user_id FROM habits WHERE id = ?");
            $stmt->execute([$habitId]);
            $result = $stmt->fetch();
            return $result ? $result['user_id'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    private function updateUserRewardPoints($userId) {
        if (!$userId) return;

        try {
            // Calculate total points from all user activities
            $stmt = $this->db->prepare("
                SELECT 
                    (SELECT COALESCE(SUM(points), 0) FROM habits WHERE user_id = ?) +
                    (SELECT COALESCE(SUM(calories_burned / 10), 0) FROM activities WHERE user_id = ?) as total_points
            ");
            $stmt->execute([$userId, $userId]);
            $totalPoints = $stmt->fetch()['total_points'];

            $tier = Utils::getRewardTier($totalPoints);

            // Update or create reward record
            $stmt = $this->db->prepare("
                INSERT INTO rewards (user_id, points, tier) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE points = ?, tier = ?
            ");
            $stmt->execute([$userId, $totalPoints, $tier, $totalPoints, $tier]);
        } catch (PDOException $e) {
            // Silent fail for reward updates
        }
    }
    
    // New method to update habit statuses
    public function updateHabitStatuses($userId) {
        try {
            $today = date('Y-m-d');
            
            // Mark habits as completed if target is reached
            $stmt = $this->db->prepare("
                UPDATE habits 
                SET status = 'completed' 
                WHERE user_id = ? 
                AND status = 'active' 
                AND streak >= target_days
            ");
            $stmt->execute([$userId]);
            
            // Reset completed_today flag for new day
            $stmt = $this->db->prepare("
                UPDATE habits 
                SET completed_today = 0 
                WHERE user_id = ? 
                AND (last_completed_date IS NULL OR last_completed_date < ?)
            ");
            $stmt->execute([$userId, $today]);
            
            // Calculate missed days for daily habits
            $stmt = $this->db->prepare("
                UPDATE habits 
                SET missed_days = DATEDIFF(?, COALESCE(last_completed_date, start_date)) - streak
                WHERE user_id = ? 
                AND frequency = 'daily' 
                AND status = 'active'
                AND DATEDIFF(?, COALESCE(last_completed_date, start_date)) > streak
            ");
            $stmt->execute([$today, $userId, $today]);
            
        } catch (PDOException $e) {
            // Silent fail
        }
    }
    
    // New method to mark habit as complete for today
    public function markComplete($habitId, $userId) {
        try {
            $today = date('Y-m-d');
            
            // Check if already completed today
            $stmt = $this->db->prepare("
                SELECT completed_today, last_completed_date 
                FROM habits 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$habitId, $userId]);
            $habit = $stmt->fetch();
            
            if (!$habit || $habit['completed_today']) {
                return ['success' => false, 'message' => 'Already completed today'];
            }
            
            // Mark as completed and increment streak
            $points = $this->calculatePoints($habitId);
            $stmt = $this->db->prepare("
                UPDATE habits 
                SET completed_today = 1,
                    last_completed_date = ?,
                    streak = streak + 1,
                    points = points + ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ?
            ");
            $result = $stmt->execute([$today, $points, $habitId, $userId]);
            
            if ($result) {
                $this->updateUserRewardPoints($userId);
                return ['success' => true, 'points_earned' => $points];
            }
            
            return ['success' => false];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    // Calculate points based on frequency
    private function calculatePoints($habitId) {
        try {
            $stmt = $this->db->prepare("SELECT frequency FROM habits WHERE id = ?");
            $stmt->execute([$habitId]);
            $habit = $stmt->fetch();
            
            if ($habit) {
                switch ($habit['frequency']) {
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
            return 10;
        } catch (PDOException $e) {
            return 10;
        }
    }
}
?>