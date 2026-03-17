<?php
/**
 * Appointment Model
 * Health Tracker Application
 */

require_once __DIR__ . '/../helpers/Database.php';

class Appointment {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function getConnectedDoctors($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.avatar,
                    d.specialty,
                    udc.created_at AS connected_at
                FROM user_doctor_connections udc
                INNER JOIN users u ON u.id = udc.doctor_id
                INNER JOIN doctors d ON d.id = u.id
                WHERE udc.user_id = ? AND udc.status = 'active'
                ORDER BY u.name ASC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function isConnectedToDoctor($userId, $doctorId) {
        $stmt = $this->db->prepare("
            SELECT id
            FROM user_doctor_connections
            WHERE user_id = ? AND doctor_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$userId, $doctorId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createAppointment($userId, $doctorId, $appointmentDate, $appointmentTime, $reason = '', $notes = '') {
        try {
            if (!$this->isConnectedToDoctor($userId, $doctorId)) {
                return ['success' => false, 'message' => 'You can only book appointments with connected doctors.'];
            }

            $appointmentDateTime = strtotime($appointmentDate . ' ' . $appointmentTime);
            if (!$appointmentDateTime || $appointmentDateTime <= time()) {
                return ['success' => false, 'message' => 'Please choose a future date and time.'];
            }

            $conflictStmt = $this->db->prepare("
                SELECT id
                FROM appointments
                WHERE doctor_id = ?
                  AND appointment_date = ?
                  AND appointment_time = ?
                  AND status IN ('pending', 'accepted')
                LIMIT 1
            ");
            $conflictStmt->execute([$doctorId, $appointmentDate, $appointmentTime]);

            if ($conflictStmt->fetch(PDO::FETCH_ASSOC)) {
                return ['success' => false, 'message' => 'That time slot is already booked. Please choose another slot.'];
            }

            $stmt = $this->db->prepare("
                INSERT INTO appointments
                (user_id, doctor_id, appointment_date, appointment_time, reason, notes, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");

            $result = $stmt->execute([
                $userId,
                $doctorId,
                $appointmentDate,
                $appointmentTime,
                $reason ?: null,
                $notes ?: null
            ]);

            if ($result) {
                return ['success' => true, 'message' => 'Appointment request submitted successfully.'];
            }

            return ['success' => false, 'message' => 'Failed to book appointment.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'An error occurred while booking appointment.'];
        }
    }

    public function getUserAppointments($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    a.*,
                    u.name AS doctor_name,
                    u.email AS doctor_email,
                    d.specialty AS doctor_specialty
                FROM appointments a
                INNER JOIN users u ON u.id = a.doctor_id
                INNER JOIN doctors d ON d.id = a.doctor_id
                WHERE a.user_id = ?
                ORDER BY a.appointment_date DESC, a.appointment_time DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getDoctorAppointments($doctorId, $status = null) {
        try {
            $sql = "
                SELECT 
                    a.*,
                    u.name AS patient_name,
                    u.email AS patient_email,
                    u.phone AS patient_phone,
                    u.avatar AS patient_avatar
                FROM appointments a
                INNER JOIN users u ON u.id = a.user_id
                WHERE a.doctor_id = ?
            ";

            $params = [$doctorId];
            if (!empty($status)) {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function updateStatus($appointmentId, $doctorId, $status, $doctorResponse = '') {
        try {
            if (!in_array($status, ['accepted', 'declined'], true)) {
                return ['success' => false, 'message' => 'Invalid appointment status.'];
            }

            $findStmt = $this->db->prepare("
                SELECT id, status
                FROM appointments
                WHERE id = ? AND doctor_id = ?
                LIMIT 1
            ");
            $findStmt->execute([$appointmentId, $doctorId]);
            $appointment = $findStmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                return ['success' => false, 'message' => 'Appointment not found.'];
            }

            if ($appointment['status'] !== 'pending') {
                return ['success' => false, 'message' => 'Only pending appointments can be updated.'];
            }

            $stmt = $this->db->prepare("
                UPDATE appointments
                SET status = ?,
                    doctor_response = ?,
                    responded_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND doctor_id = ?
            ");

            $ok = $stmt->execute([
                $status,
                $doctorResponse ?: null,
                $appointmentId,
                $doctorId
            ]);

            if ($ok) {
                return ['success' => true, 'message' => 'Appointment updated successfully.'];
            }

            return ['success' => false, 'message' => 'Failed to update appointment.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'An error occurred while updating appointment.'];
        }
    }

    public function getAppointmentWithParties($appointmentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    a.*,
                    patient.name AS patient_name,
                    patient.email AS patient_email,
                    doctor.name AS doctor_name,
                    doctor.email AS doctor_email,
                    d.specialty AS doctor_specialty
                FROM appointments a
                INNER JOIN users patient ON patient.id = a.user_id
                INNER JOIN users doctor ON doctor.id = a.doctor_id
                INNER JOIN doctors d ON d.id = a.doctor_id
                WHERE a.id = ?
                LIMIT 1
            ");
            $stmt->execute([$appointmentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>