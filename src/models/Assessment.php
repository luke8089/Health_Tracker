<?php
/**
 * Assessment Model
 * Health Tracker Application
 */

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Utils.php';

class Assessment {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function create($userId, $responses) {
        try {
            $score = Utils::calculateHealthScore($responses);
            $severity = Utils::getSeverity($score);

            $stmt = $this->db->prepare("
                INSERT INTO assessments (user_id, responses, score, severity) 
                VALUES (?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $userId,
                json_encode($responses),
                $score,
                $severity
            ]);

            if ($result) {
                return [
                    'id' => $this->db->lastInsertId(),
                    'score' => $score,
                    'severity' => $severity
                ];
            }

            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, u.name as user_name 
                FROM assessments a 
                JOIN users u ON a.user_id = u.id 
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            $assessment = $stmt->fetch();
            
            if ($assessment) {
                $assessment['responses'] = json_decode($assessment['responses'], true);
            }
            
            return $assessment;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getUserAssessments($userId, $limit = null) {
        try {
            $sql = "SELECT * FROM assessments WHERE user_id = ? ORDER BY created_at DESC";
            $params = [$userId];

            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $assessments = $stmt->fetchAll();

            // Decode JSON responses
            foreach ($assessments as &$assessment) {
                $assessment['responses'] = json_decode($assessment['responses'], true);
            }

            return $assessments;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getAllAssessments($severity = null, $limit = null, $offset = null) {
        try {
            $sql = "
                SELECT a.*, u.name as user_name, u.email as user_email 
                FROM assessments a 
                JOIN users u ON a.user_id = u.id
            ";
            $params = [];

            if ($severity) {
                $sql .= " WHERE a.severity = ?";
                $params[] = $severity;
            }

            $sql .= " ORDER BY a.created_at DESC";

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
            $assessments = $stmt->fetchAll();

            // Decode JSON responses
            foreach ($assessments as &$assessment) {
                $assessment['responses'] = json_decode($assessment['responses'], true);
            }

            return $assessments;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getAssessmentStats() {
        try {
            $stats = [];

            // Total assessments
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM assessments");
            $stmt->execute();
            $stats['total'] = $stmt->fetch()['count'];

            // By severity
            $stmt = $this->db->prepare("
                SELECT severity, COUNT(*) as count 
                FROM assessments 
                GROUP BY severity
            ");
            $stmt->execute();
            
            $severityStats = $stmt->fetchAll();
            $stats['by_severity'] = [];
            foreach ($severityStats as $stat) {
                $stats['by_severity'][$stat['severity']] = $stat['count'];
            }

            // Recent assessments (last 7 days)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM assessments 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $stats['recent'] = $stmt->fetch()['count'];

            // Average score
            $stmt = $this->db->prepare("SELECT AVG(score) as avg_score FROM assessments");
            $stmt->execute();
            $stats['average_score'] = round($stmt->fetch()['avg_score'], 1);

            return $stats;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getHighSeverityAssessments($limit = null) {
        try {
            $sql = "
                SELECT a.*, u.name as user_name, u.email as user_email 
                FROM assessments a 
                JOIN users u ON a.user_id = u.id 
                WHERE a.severity = 'high' 
                ORDER BY a.created_at DESC
            ";

            if ($limit) {
                $sql .= " LIMIT ?";
            }

            $stmt = $this->db->prepare($sql);
            
            if ($limit) {
                $stmt->execute([$limit]);
            } else {
                $stmt->execute();
            }

            $assessments = $stmt->fetchAll();

            // Decode JSON responses
            foreach ($assessments as &$assessment) {
                $assessment['responses'] = json_decode($assessment['responses'], true);
            }

            return $assessments;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM assessments WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getAssessmentTrends($userId, $days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    score,
                    severity
                FROM assessments 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at ASC
            ");
            $stmt->execute([$userId, $days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function hasRecentAssessment($userId, $hours = 24) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM assessments 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->execute([$userId, $hours]);
            return $stmt->fetch()['count'] > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getAssessmentQuestions() {
        return [
            'stress_level' => [
                'question' => 'How would you rate your current stress level?',
                'type' => 'scale',
                'min' => 1,
                'max' => 10,
                'labels' => ['Very Low', 'Very High']
            ],
            'sleep_hours' => [
                'question' => 'How many hours of sleep do you get on average per night?',
                'type' => 'number',
                'min' => 0,
                'max' => 12
            ],
            'exercise_frequency' => [
                'question' => 'How many days per week do you exercise?',
                'type' => 'number',
                'min' => 0,
                'max' => 7
            ],
            'diet_quality' => [
                'question' => 'How would you rate your diet quality?',
                'type' => 'scale',
                'min' => 1,
                'max' => 10,
                'labels' => ['Very Poor', 'Excellent']
            ],
            'mental_health' => [
                'question' => 'How would you rate your overall mental well-being?',
                'type' => 'scale',
                'min' => 1,
                'max' => 10,
                'labels' => ['Very Poor', 'Excellent']
            ],
            'water_intake' => [
                'question' => 'How many glasses of water do you drink daily?',
                'type' => 'number',
                'min' => 0,
                'max' => 15
            ],
            'screen_time' => [
                'question' => 'How many hours do you spend on screens daily (excluding work)?',
                'type' => 'number',
                'min' => 0,
                'max' => 16
            ],
            'social_connections' => [
                'question' => 'How satisfied are you with your social connections?',
                'type' => 'scale',
                'min' => 1,
                'max' => 10,
                'labels' => ['Very Unsatisfied', 'Very Satisfied']
            ]
        ];
    }
}
?>