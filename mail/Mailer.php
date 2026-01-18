<?php
/**
 * Mailer Class
 * Handles all email sending functionality using PHPMailer
 */

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $config;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->mail = new PHPMailer(true);
        $this->setupSMTP();
    }
    
    /**
     * Configure SMTP settings
     */
    private function setupSMTP() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = $this->config['smtp']['host'];
            $this->mail->SMTPAuth = $this->config['smtp']['auth'];
            $this->mail->Username = $this->config['smtp']['username'];
            $this->mail->Password = $this->config['smtp']['password'];
            $this->mail->SMTPSecure = $this->config['smtp']['encryption'];
            $this->mail->Port = $this->config['smtp']['port'];
            
            // Default sender
            $this->mail->setFrom(
                $this->config['from']['email'],
                $this->config['from']['name']
            );
            
            // Character set
            $this->mail->CharSet = 'UTF-8';
            $this->mail->isHTML(true);
            
        } catch (Exception $e) {
            error_log("Mailer setup error: " . $e->getMessage());
        }
    }
    
    /**
     * Send email
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body HTML email body
     * @param string $recipientName Recipient name (optional)
     * @return array Success status and message
     */
    public function send($to, $subject, $body, $recipientName = '') {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            // Set recipient
            $this->mail->addAddress($to, $recipientName);
            
            // Content
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            // Send
            $this->mail->send();
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $this->mail->ErrorInfo);
            return [
                'success' => false,
                'message' => 'Email could not be sent. Error: ' . $this->mail->ErrorInfo
            ];
        }
    }
    
    /**
     * Send welcome email to new user
     * 
     * @param array $userData User data (name, email, role)
     * @return array Success status and message
     */
    public function sendWelcomeEmail($userData) {
        $template = require __DIR__ . '/templates/welcome.php';
        $body = $template($userData, $this->config['base_url']);
        
        $subject = "Welcome to Health Tracker - Your Wellness Journey Begins!";
        
        return $this->send(
            $userData['email'],
            $subject,
            $body,
            $userData['name']
        );
    }
    
    /**
     * Send doctor registration notification
     * 
     * @param array $doctorData Doctor data
     * @return array Success status and message
     */
    public function sendDoctorRegistrationEmail($doctorData) {
        $template = require __DIR__ . '/templates/doctor_welcome.php';
        $body = $template($doctorData, $this->config['base_url']);
        
        $subject = "Welcome Dr. {$doctorData['name']} - Health Tracker Professional Account";
        
        return $this->send(
            $doctorData['email'],
            $subject,
            $body,
            "Dr. {$doctorData['name']}"
        );
    }
    
    /**
     * Notify admin about new doctor registration
     * 
     * @param array $doctorData Doctor data
     * @return array Success status and message
     */
    public function notifyAdminNewDoctor($doctorData) {
        $template = require __DIR__ . '/templates/admin_new_doctor.php';
        $body = $template($doctorData, $this->config['base_url']);
        
        $subject = "New Doctor Registration - {$doctorData['name']}";
        
        return $this->send(
            $this->config['admin']['email'],
            $subject,
            $body,
            $this->config['admin']['name']
        );
    }
    
    /**
     * Send password reset email
     * 
     * @param string $email User email
     * @param string $name User name
     * @param string $resetToken Reset token
     * @return array Success status and message
     */
    public function sendPasswordResetEmail($email, $name, $resetToken) {
        $template = require __DIR__ . '/templates/password_reset.php';
        $body = $template($name, $resetToken, $this->config['base_url']);
        
        $subject = "Password Reset Request - Health Tracker";
        
        return $this->send($email, $subject, $body, $name);
    }
    
    /**
     * Send verification email
     * 
     * @param string $email User email
     * @param string $name User name
     * @param string $verificationToken Verification token
     * @return array Success status and message
     */
    public function sendVerificationEmail($email, $name, $verificationToken) {
        $template = require __DIR__ . '/templates/email_verification.php';
        $body = $template($name, $verificationToken, $this->config['base_url']);
        
        $subject = "Verify Your Email - Health Tracker";
        
        return $this->send($email, $subject, $body, $name);
    }
    
    /**
     * Send assessment results email with recommendations
     * 
     * @param array $userData User data (name, email)
     * @param array $assessmentData Assessment data (type, score, severity, recommendations, date)
     * @return array Success status and message
     */
    public function sendAssessmentResultsEmail($userData, $assessmentData) {
        try {
            $template = require __DIR__ . '/templates/assessment_results.php';
            $body = $template($userData, $assessmentData, $this->config['base_url']);
            
            $assessmentType = ucfirst($assessmentData['type']);
            $score = $assessmentData['score'];
            $subject = "Your {$assessmentType} Health Assessment Results - {$score}% Score";
            
            return $this->send(
                $userData['email'],
                $subject,
                $body,
                $userData['name']
            );
        } catch (Exception $e) {
            error_log("Assessment results email error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send assessment results email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send habit creation notification email
     * 
     * @param array $userData User data (name, email)
     * @param array $habitData Habit data (name, frequency, target_days, start_date, end_date)
     * @return array Success status and message
     */
    public function sendHabitCreatedEmail($userData, $habitData) {
        try {
            $template = require __DIR__ . '/templates/habit_created.php';
            $body = $template($userData, $habitData, $this->config['base_url']);
            
            $habitName = $habitData['name'];
            $subject = "New Habit Created: {$habitName} - Let's Build This Together!";
            
            return $this->send(
                $userData['email'],
                $subject,
                $body,
                $userData['name']
            );
        } catch (Exception $e) {
            error_log("Habit creation email error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send habit creation email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send doctor's recommendation to patient
     * 
     * @param array $patientData Patient data (name, email)
     * @param array $doctorData Doctor data (name, specialty)
     * @param array $recommendationData Recommendation data (recommendation_text, assessment_score, assessment_severity, review_date)
     * @return array Success status and message
     */
    public function sendDoctorRecommendationEmail($patientData, $doctorData, $recommendationData) {
        try {
            $template = require __DIR__ . '/templates/doctor_recommendation.php';
            $body = $template($patientData, $doctorData, $recommendationData, $this->config['base_url']);
            
            $doctorName = $doctorData['name'];
            $subject = "New Recommendation from Dr. {$doctorName} - Health Assessment Review";
            
            return $this->send(
                $patientData['email'],
                $subject,
                $body,
                $patientData['name']
            );
        } catch (Exception $e) {
            error_log("Doctor recommendation email error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send doctor recommendation email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send missed video call notification to doctor
     * 
     * @param array $doctorData Doctor data (name, email)
     * @param array $patientData Patient data (name, avatar)
     * @param array $callData Call data (time)
     * @return array Success status and message
     */
    public function sendMissedVideoCallEmail($doctorData, $patientData, $callData) {
        try {
            $template = require __DIR__ . '/templates/missed_video_call.php';
            $body = $template($doctorData, $patientData, $callData, $this->config['base_url']);
            
            $patientName = $patientData['name'];
            $subject = "Missed Video Call from {$patientName} - Patient Tried to Reach You";
            
            return $this->send(
                $doctorData['email'],
                $subject,
                $body,
                "Dr. {$doctorData['name']}"
            );
        } catch (Exception $e) {
            error_log("Missed video call email error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send missed video call email: ' . $e->getMessage()
            ];
        }
    }
}
