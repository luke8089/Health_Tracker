<?php
/**
 * Video Call API
 * Handles WebRTC signaling and call management
 */

require_once __DIR__ . '/../../src/helpers/Auth.php';
require_once __DIR__ . '/../../src/helpers/Database.php';
require_once __DIR__ . '/../../mail/Mailer.php';

header('Content-Type: application/json');

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = new Database();
$conn = $db->connect();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'initiate_call':
            // Only users can initiate calls
            if ($currentUser['role'] !== 'user') {
                throw new Exception('Only patients can initiate calls');
            }
            
            $doctorId = intval($input['doctor_id']);
            $sessionId = $input['session_id'];
            
            // Verify connection exists
            $checkStmt = $conn->prepare("
                SELECT id FROM user_doctor_connections 
                WHERE user_id = ? AND doctor_id = ? AND status = 'active'
            ");
            $checkStmt->execute([$currentUser['id'], $doctorId]);
            
            if (!$checkStmt->fetch()) {
                throw new Exception('You are not connected with this doctor');
            }
            
            // Create call record
            $stmt = $conn->prepare("
                INSERT INTO video_calls (user_id, doctor_id, session_id, status, started_at)
                VALUES (?, ?, ?, 'ringing', NOW())
            ");
            $stmt->execute([$currentUser['id'], $doctorId, $sessionId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Call initiated',
                'session_id' => $sessionId
            ]);
            break;
            
        case 'get_call_status':
            $sessionId = $input['session_id'];
            
            $stmt = $conn->prepare("
                SELECT vc.*, 
                       u.name as user_name,
                       d.name as doctor_name
                FROM video_calls vc
                LEFT JOIN users u ON vc.user_id = u.id
                LEFT JOIN users d ON vc.doctor_id = d.id
                WHERE vc.session_id = ?
            ");
            $stmt->execute([$sessionId]);
            $call = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$call) {
                throw new Exception('Call session not found');
            }
            
            // Get pending signals for this user
            $role = $currentUser['role'];
            $recipientType = ($role === 'user') ? 'user' : 'doctor';
            
            $signalStmt = $conn->prepare("
                SELECT signal_data FROM video_call_signals
                WHERE session_id = ? AND recipient = ? AND delivered = 0
                ORDER BY created_at ASC
                LIMIT 1
            ");
            $signalStmt->execute([$sessionId, $recipientType]);
            $signalRow = $signalStmt->fetch(PDO::FETCH_ASSOC);
            
            $signal = null;
            if ($signalRow) {
                $signal = json_decode($signalRow['signal_data'], true);
                
                // Mark as delivered
                $updateStmt = $conn->prepare("
                    UPDATE video_call_signals 
                    SET delivered = 1 
                    WHERE session_id = ? AND recipient = ? AND delivered = 0
                ");
                $updateStmt->execute([$sessionId, $recipientType]);
            }
            
            echo json_encode([
                'success' => true,
                'status' => $call['status'],
                'call' => $call,
                'signal' => $signal
            ]);
            break;
            
        case 'answer_call':
            // Only doctors can answer calls
            if ($currentUser['role'] !== 'doctor') {
                throw new Exception('Only doctors can answer calls');
            }
            
            $sessionId = $input['session_id'];
            
            // First check if the call exists and get its status
            $checkStmt = $conn->prepare("
                SELECT status, doctor_id 
                FROM video_calls 
                WHERE session_id = ?
            ");
            $checkStmt->execute([$sessionId]);
            $callInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$callInfo) {
                throw new Exception('Call not found');
            }
            
            if ($callInfo['doctor_id'] != $currentUser['id']) {
                throw new Exception('This call is not for you');
            }
            
            if ($callInfo['status'] === 'active') {
                // Already answered
                echo json_encode([
                    'success' => true,
                    'message' => 'Call already active',
                    'already_active' => true
                ]);
                break;
            }
            
            if ($callInfo['status'] !== 'ringing') {
                throw new Exception('Call is ' . $callInfo['status'] . ', cannot answer');
            }
            
            $stmt = $conn->prepare("
                UPDATE video_calls 
                SET status = 'active', answered_at = NOW()
                WHERE session_id = ? AND doctor_id = ? AND status = 'ringing'
            ");
            $stmt->execute([$sessionId, $currentUser['id']]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Cannot answer call - status may have changed');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Call answered'
            ]);
            break;
            
        case 'reject_call':
            // Only doctors can reject calls
            if ($currentUser['role'] !== 'doctor') {
                throw new Exception('Only doctors can reject calls');
            }
            
            $sessionId = $input['session_id'];
            
            $stmt = $conn->prepare("
                UPDATE video_calls 
                SET status = 'rejected', ended_at = NOW()
                WHERE session_id = ? AND doctor_id = ? AND status = 'ringing'
            ");
            $stmt->execute([$sessionId, $currentUser['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Call rejected'
            ]);
            break;
            
        case 'end_call':
            $sessionId = $input['session_id'];
            
            // Get call details
            $getStmt = $conn->prepare("
                SELECT user_id, doctor_id, status, started_at, answered_at
                FROM video_calls
                WHERE session_id = ?
            ");
            $getStmt->execute([$sessionId]);
            $call = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$call) {
                throw new Exception('Call not found');
            }
            
            // Verify user is part of this call
            if ($call['user_id'] != $currentUser['id'] && $call['doctor_id'] != $currentUser['id']) {
                throw new Exception('Unauthorized');
            }
            
            // Calculate duration
            $duration = 0;
            if ($call['answered_at']) {
                $duration = time() - strtotime($call['answered_at']);
            }
            
            // Update call status
            $status = ($call['status'] === 'ringing') ? 'missed' : 'ended';
            
            $stmt = $conn->prepare("
                UPDATE video_calls 
                SET status = ?, ended_at = NOW(), duration_seconds = ?
                WHERE session_id = ?
            ");
            $stmt->execute([$status, $duration, $sessionId]);
            
            // Send email notification if call was missed
            if ($status === 'missed') {
                try {
                    // Get doctor details
                    $doctorStmt = $conn->prepare("
                        SELECT name, email FROM users WHERE id = ?
                    ");
                    $doctorStmt->execute([$call['doctor_id']]);
                    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get patient details
                    $patientStmt = $conn->prepare("
                        SELECT name, avatar FROM users WHERE id = ?
                    ");
                    $patientStmt->execute([$call['user_id']]);
                    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($doctor && $patient) {
                        $mailer = new Mailer();
                        $emailResult = $mailer->sendMissedVideoCallEmail(
                            [
                                'name' => $doctor['name'],
                                'email' => $doctor['email']
                            ],
                            [
                                'name' => $patient['name'],
                                'avatar' => $patient['avatar'] ? 
                                    '<img src="' . htmlspecialchars($patient['avatar']) . '" alt="' . htmlspecialchars($patient['name']) . '" style="width: 100%; height: 100%; object-fit: cover;">' :
                                    strtoupper(substr($patient['name'], 0, 2))
                            ],
                            [
                                'time' => date('F j, Y \a\t g:i A', strtotime($call['started_at']))
                            ]
                        );
                        
                        if (!$emailResult['success']) {
                            error_log("Failed to send missed call email: " . $emailResult['message']);
                        }
                    }
                } catch (Exception $emailError) {
                    error_log("Error sending missed call email: " . $emailError->getMessage());
                    // Don't throw - email failure shouldn't block the call ending
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Call ended',
                'duration' => $duration
            ]);
            break;
            
        case 'send_signal':
            $sessionId = $input['session_id'];
            $signal = $input['signal'];
            
            // Get call details
            $callStmt = $conn->prepare("
                SELECT user_id, doctor_id FROM video_calls WHERE session_id = ?
            ");
            $callStmt->execute([$sessionId]);
            $call = $callStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$call) {
                throw new Exception('Call not found');
            }
            
            // Determine recipient
            $recipient = ($currentUser['id'] == $call['user_id']) ? 'doctor' : 'user';
            
            // Store signal
            $stmt = $conn->prepare("
                INSERT INTO video_call_signals (session_id, sender_id, recipient, signal_data, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $sessionId,
                $currentUser['id'],
                $recipient,
                json_encode($signal)
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Signal sent'
            ]);
            break;
            
        case 'get_incoming_calls':
            // Only for doctors
            if ($currentUser['role'] !== 'doctor') {
                throw new Exception('Only doctors can check incoming calls');
            }
            
            $stmt = $conn->prepare("
                SELECT vc.*, u.name as user_name, u.avatar as user_avatar
                FROM video_calls vc
                JOIN users u ON vc.user_id = u.id
                WHERE vc.doctor_id = ? AND vc.status = 'ringing'
                ORDER BY vc.started_at DESC
            ");
            $stmt->execute([$currentUser['id']]);
            $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'calls' => $calls
            ]);
            break;
            
        case 'delete_call':
            // Only doctors can delete their call history
            if ($currentUser['role'] !== 'doctor') {
                throw new Exception('Only doctors can delete call history');
            }
            
            $callId = $input['call_id'] ?? null;
            if (!$callId) {
                throw new Exception('Call ID is required');
            }
            
            // Verify the call belongs to this doctor
            $checkStmt = $conn->prepare("
                SELECT id FROM video_calls 
                WHERE id = ? AND doctor_id = ?
            ");
            $checkStmt->execute([$callId, $currentUser['id']]);
            
            $callExists = $checkStmt->fetch();
            if (!$callExists) {
                throw new Exception('Call not found or unauthorized. Call ID: ' . $callId . ', Doctor ID: ' . $currentUser['id']);
            }
            
            // Delete associated signals first
            $deleteSignalsStmt = $conn->prepare("
                DELETE FROM video_call_signals 
                WHERE session_id = (
                    SELECT session_id FROM video_calls WHERE id = ?
                )
            ");
            $deleteSignalsStmt->execute([$callId]);
            $signalsDeleted = $deleteSignalsStmt->rowCount();
            
            // Delete the call
            $deleteStmt = $conn->prepare("
                DELETE FROM video_calls 
                WHERE id = ? AND doctor_id = ?
            ");
            $deleteStmt->execute([$callId, $currentUser['id']]);
            $callDeleted = $deleteStmt->rowCount();
            
            if ($callDeleted === 0) {
                throw new Exception('Failed to delete call record');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Call deleted successfully',
                'signals_deleted' => $signalsDeleted,
                'call_deleted' => $callDeleted
            ]);
            break;
            
        case 'clear_all_history':
            // Only doctors can clear their call history
            if ($currentUser['role'] !== 'doctor') {
                throw new Exception('Only doctors can clear call history');
            }
            
            // Delete signals for all this doctor's calls
            $deleteSignalsStmt = $conn->prepare("
                DELETE FROM video_call_signals 
                WHERE session_id IN (
                    SELECT session_id FROM video_calls WHERE doctor_id = ?
                )
            ");
            $deleteSignalsStmt->execute([$currentUser['id']]);
            
            // Delete all calls for this doctor
            $deleteStmt = $conn->prepare("
                DELETE FROM video_calls 
                WHERE doctor_id = ?
            ");
            $deleteStmt->execute([$currentUser['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'All call history cleared successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
