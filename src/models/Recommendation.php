<?php
/**
 * Recommendation Model
 * Health Tracker Application
 */

require_once __DIR__ . '/../helpers/Database.php';

class Recommendation {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function create($assessmentId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO recommendations (assessment_id, title, details, urgency) 
                VALUES (?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $assessmentId,
                $data['title'],
                $data['details'],
                $data['urgency']
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, a.user_id, a.score, a.severity, u.name as user_name 
                FROM recommendations r 
                JOIN assessments a ON r.assessment_id = a.id 
                JOIN users u ON a.user_id = u.id 
                WHERE r.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getByAssessmentId($assessmentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM recommendations 
                WHERE assessment_id = ? 
                ORDER BY urgency DESC, created_at DESC
            ");
            $stmt->execute([$assessmentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getUserRecommendations($userId, $limit = null) {
        try {
            $sql = "
                SELECT r.*, a.score, a.severity 
                FROM recommendations r 
                JOIN assessments a ON r.assessment_id = a.id 
                WHERE a.user_id = ? 
                ORDER BY r.created_at DESC
            ";
            $params = [$userId];

            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function generateRecommendations($assessmentId, $responses, $score, $severity) {
        $recommendations = [];
        
        // Stress management recommendations
        if (isset($responses['stress_level']) && $responses['stress_level'] >= 7) {
            $recommendations[] = [
                'title' => 'Stress Management Techniques',
                'details' => 'Your stress levels are high. Try deep breathing exercises, meditation, or yoga. Consider speaking with a mental health professional if stress persists.',
                'urgency' => $severity === 'high' ? 'high' : 'medium'
            ];
        }
        
        // Sleep recommendations
        if (isset($responses['sleep_hours']) && $responses['sleep_hours'] < 7) {
            $recommendations[] = [
                'title' => 'Improve Sleep Quality',
                'details' => 'Aim for 7-9 hours of sleep per night. Establish a consistent bedtime routine and avoid screens before bed.',
                'urgency' => 'medium'
            ];
        }
        
        // Exercise recommendations
        if (isset($responses['exercise_frequency']) && $responses['exercise_frequency'] < 3) {
            $recommendations[] = [
                'title' => 'Increase Physical Activity',
                'details' => 'Try to exercise at least 3-4 times per week. Start with 30 minutes of moderate activity like walking or swimming.',
                'urgency' => 'medium'
            ];
        }
        
        // Diet recommendations
        if (isset($responses['diet_quality']) && $responses['diet_quality'] <= 5) {
            $recommendations[] = [
                'title' => 'Improve Nutrition',
                'details' => 'Focus on eating more fruits, vegetables, whole grains, and lean proteins. Limit processed foods and sugary drinks.',
                'urgency' => 'medium'
            ];
        }
        
        // Mental health recommendations
        if (isset($responses['mental_health']) && $responses['mental_health'] <= 5) {
            $recommendations[] = [
                'title' => 'Mental Health Support',
                'details' => 'Consider talking to a mental health professional. Practice self-care activities and maintain social connections.',
                'urgency' => $severity === 'high' ? 'high' : 'medium'
            ];
        }
        
        // Water intake recommendations
        if (isset($responses['water_intake']) && $responses['water_intake'] < 6) {
            $recommendations[] = [
                'title' => 'Increase Water Intake',
                'details' => 'Aim to drink at least 8 glasses of water per day. Carry a water bottle and set reminders if needed.',
                'urgency' => 'low'
            ];
        }
        
        // Screen time recommendations
        if (isset($responses['screen_time']) && $responses['screen_time'] > 6) {
            $recommendations[] = [
                'title' => 'Reduce Screen Time',
                'details' => 'Limit recreational screen time to reduce eye strain and improve sleep quality. Take regular breaks from devices.',
                'urgency' => 'low'
            ];
        }
        
        // Social connections recommendations
        if (isset($responses['social_connections']) && $responses['social_connections'] <= 5) {
            $recommendations[] = [
                'title' => 'Strengthen Social Connections',
                'details' => 'Make time for friends and family. Consider joining social groups or communities with shared interests.',
                'urgency' => 'medium'
            ];
        }
        
        // High severity general recommendation
        if ($severity === 'high' && empty($recommendations)) {
            $recommendations[] = [
                'title' => 'Immediate Health Consultation',
                'details' => 'Your assessment indicates several areas of concern. We recommend scheduling a consultation with a healthcare provider as soon as possible.',
                'urgency' => 'high'
            ];
        }
        
        // Create recommendations in database
        foreach ($recommendations as $rec) {
            $this->create($assessmentId, $rec);
        }
        
        return $recommendations;
    }

    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE recommendations 
                SET title = ?, details = ?, urgency = ? 
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['title'],
                $data['details'],
                $data['urgency'],
                $id
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM recommendations WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getRecommendationStats() {
        try {
            $stats = [];

            // Total recommendations
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM recommendations");
            $stmt->execute();
            $stats['total'] = $stmt->fetch()['count'];

            // By urgency
            $stmt = $this->db->prepare("
                SELECT urgency, COUNT(*) as count 
                FROM recommendations 
                GROUP BY urgency
            ");
            $stmt->execute();
            
            $urgencyStats = $stmt->fetchAll();
            $stats['by_urgency'] = [];
            foreach ($urgencyStats as $stat) {
                $stats['by_urgency'][$stat['urgency']] = $stat['count'];
            }

            return $stats;
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>