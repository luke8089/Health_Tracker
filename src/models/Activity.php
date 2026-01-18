<?php
/**
 * Activity Model
 * Health Tracker Application
 */

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Utils.php';

class Activity {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function create($userId, $data) {
        try {
            $calories = Utils::calculateCalories(
                $data['type'], 
                $data['duration_minutes'], 
                $data['weight'] ?? 70
            );

            $stmt = $this->db->prepare("
                INSERT INTO activities (user_id, type, duration_minutes, calories_burned) 
                VALUES (?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $userId,
                $data['type'],
                $data['duration_minutes'],
                $calories
            ]);

            if ($result) {
                // Update user's reward points
                $this->updateUserRewardPoints($userId);
            }

            return $result;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, u.name as user_name 
                FROM activities a 
                JOIN users u ON a.user_id = u.id 
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getUserActivities($userId, $limit = null, $offset = null) {
        try {
            $sql = "
                SELECT * FROM activities 
                WHERE user_id = ? 
                ORDER BY created_at DESC
            ";
            $params = [$userId];

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

    public function update($id, $data) {
        try {
            // Recalculate calories if duration or type changed
            if (isset($data['duration_minutes']) || isset($data['type'])) {
                $current = $this->findById($id);
                if ($current) {
                    $data['calories_burned'] = Utils::calculateCalories(
                        $data['type'] ?? $current['type'],
                        $data['duration_minutes'] ?? $current['duration_minutes']
                    );
                }
            }

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
                UPDATE activities SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            
            $result = $stmt->execute($values);

            if ($result) {
                // Update user's reward points
                $userId = $this->getActivityUserId($id);
                $this->updateUserRewardPoints($userId);
            }

            return $result;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function delete($id) {
        try {
            $userId = $this->getActivityUserId($id);
            
            $stmt = $this->db->prepare("DELETE FROM activities WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result && $userId) {
                // Update user's reward points
                $this->updateUserRewardPoints($userId);
            }
            
            return $result;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getActivityStats($userId) {
        try {
            $stats = [];

            // Total activities
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM activities WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['total_activities'] = $stmt->fetch()['count'];

            // Total duration
            $stmt = $this->db->prepare("
                SELECT SUM(duration_minutes) as total 
                FROM activities 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $stats['total_duration'] = $stmt->fetch()['total'] ?? 0;

            // Total calories burned
            $stmt = $this->db->prepare("
                SELECT SUM(calories_burned) as total 
                FROM activities 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $stats['total_calories'] = $stmt->fetch()['total'] ?? 0;

            // Activities this week
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM activities 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$userId]);
            $stats['this_week'] = $stmt->fetch()['count'];

            // Activities by type
            $stmt = $this->db->prepare("
                SELECT type, COUNT(*) as count, SUM(duration_minutes) as duration, SUM(calories_burned) as calories
                FROM activities 
                WHERE user_id = ? 
                GROUP BY type 
                ORDER BY count DESC
            ");
            $stmt->execute([$userId]);
            $stats['by_type'] = $stmt->fetchAll();

            // Average duration
            $stats['avg_duration'] = $stats['total_activities'] > 0 ? 
                round($stats['total_duration'] / $stats['total_activities']) : 0;

            return $stats;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getAllActivities($limit = null, $offset = null) {
        try {
            $sql = "
                SELECT a.*, u.name as user_name 
                FROM activities a 
                JOIN users u ON a.user_id = u.id 
                ORDER BY a.created_at DESC
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

    public function getActivityTrends($userId, $days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as activity_count,
                    SUM(duration_minutes) as total_duration,
                    SUM(calories_burned) as total_calories
                FROM activities 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$userId, $days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getPopularActivities($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    type,
                    COUNT(*) as activity_count,
                    AVG(duration_minutes) as avg_duration,
                    AVG(calories_burned) as avg_calories
                FROM activities 
                GROUP BY type 
                ORDER BY activity_count DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function searchActivities($userId, $query) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM activities 
                WHERE user_id = ? AND type LIKE ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$userId, "%$query%"]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getMonthlyStats($userId, $year = null, $month = null) {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_activities,
                    SUM(duration_minutes) as total_duration,
                    SUM(calories_burned) as total_calories,
                    AVG(duration_minutes) as avg_duration
                FROM activities 
                WHERE user_id = ? 
                AND YEAR(created_at) = ? 
                AND MONTH(created_at) = ?
            ");
            $stmt->execute([$userId, $year, $month]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getWeeklyGoalProgress($userId, $weeklyGoalMinutes = 150) {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(duration_minutes) as minutes_this_week
                FROM activities 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            $minutesThisWeek = $result['minutes_this_week'] ?? 0;
            
            return [
                'minutes_completed' => $minutesThisWeek,
                'weekly_goal' => $weeklyGoalMinutes,
                'progress_percentage' => $weeklyGoalMinutes > 0 ? 
                    round(($minutesThisWeek / $weeklyGoalMinutes) * 100) : 0,
                'goal_reached' => $minutesThisWeek >= $weeklyGoalMinutes
            ];
        } catch (PDOException $e) {
            return [
                'minutes_completed' => 0,
                'weekly_goal' => $weeklyGoalMinutes,
                'progress_percentage' => 0,
                'goal_reached' => false
            ];
        }
    }

    private function getActivityUserId($activityId) {
        try {
            $stmt = $this->db->prepare("SELECT user_id FROM activities WHERE id = ?");
            $stmt->execute([$activityId]);
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
}
?>