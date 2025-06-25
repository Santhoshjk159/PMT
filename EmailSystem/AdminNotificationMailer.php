<?php
/**
 * Admin Notification Mailer
 * 
 * This class handles sending email notifications to administrators
 * when users make changes to paperwork records.
 */

require_once __DIR__ . '/../PHPMailer-6.9.3/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-6.9.3/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-6.9.3/src/SMTP.php';
require_once __DIR__ . '/../email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class AdminNotificationMailer {
    private $mailer;
    private $adminEmail;
    private $fromEmail;
    private $fromName;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if email notifications are enabled
        if (!ENABLE_ADMIN_NOTIFICATIONS) {
            return;
        }
        
        $this->mailer = new PHPMailer(true);
        
        // Configure SMTP settings from config
        $this->mailer->isSMTP();
        $this->mailer->Host       = SMTP_HOST;
        $this->mailer->SMTPAuth   = SMTP_AUTH;
        $this->mailer->Port       = SMTP_PORT;
        
        if (SMTP_AUTH) {
            $this->mailer->Username   = SMTP_USERNAME;
            $this->mailer->Password   = SMTP_PASSWORD;
        }
        
        if (SMTP_SECURE) {
            $this->mailer->SMTPSecure = SMTP_SECURE;
        }
        
        $this->mailer->isHTML(true);
        
        // Set email addresses from config
        $this->adminEmail = ADMIN_EMAIL;
        $this->fromEmail = SYSTEM_FROM_EMAIL;
        $this->fromName = SYSTEM_FROM_NAME;
    }
    
    /**
     * Set admin email address
     */
    public function setAdminEmail($email) {
        $this->adminEmail = $email;
    }
    
    /**
     * Set from email and name
     */
    public function setFromEmail($email, $name = 'PMT System') {
        $this->fromEmail = $email;
        $this->fromName = $name;
    }
    
    /**
     * Send notification email to admin about record changes
     * 
     * @param array $changes Array of changes made to the record
     * @param array $recordInfo Information about the record being changed
     * @param string $modifiedBy Email of the user who made the changes
     * @param string $modifiedByName Name of the user who made the changes
     * @return bool Success status
     */
    public function sendChangeNotification($changes, $recordInfo, $modifiedBy, $modifiedByName = '') {
        // Check if email notifications are enabled
        if (!ENABLE_ADMIN_NOTIFICATIONS || empty($changes)) {
            return true; // Return true to not break the workflow
        }
        
        try {
            // Set email properties
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($this->adminEmail); // Primary admin
            $this->mailer->addAddress('santhosh.jk@vdartinc.com'); // Copy to Santhosh for testing
            $this->mailer->addReplyTo($modifiedBy, $modifiedByName);
            
            // Set subject
            $candidateName = $recordInfo['candidate_name'] ?? 'Unknown Candidate';
            $recordId = $recordInfo['record_id'] ?? 'Unknown ID';
            $this->mailer->Subject = "PMT Record Updated - {$candidateName} (ID: {$recordId})";
            
            // Build email body
            $this->mailer->Body = $this->buildEmailBody($changes, $recordInfo, $modifiedBy, $modifiedByName);
            $this->mailer->AltBody = $this->buildPlainTextBody($changes, $recordInfo, $modifiedBy, $modifiedByName);
            
            // Send email
            $result = $this->mailer->send();
            
            // Log email if logging is enabled
            if (ENABLE_EMAIL_LOGGING && $result) {
                $this->logEmailSent($changes, $recordInfo, $modifiedBy);
            }
            
            // Clear addresses for next email
            $this->mailer->clearAddresses();
            $this->mailer->clearReplyTos();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Admin notification email failed: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Build HTML email body
     */
    private function buildEmailBody($changes, $recordInfo, $modifiedBy, $modifiedByName) {
        $candidateName = $recordInfo['candidate_name'] ?? 'Unknown Candidate';
        $recordId = $recordInfo['record_id'] ?? 'Unknown ID';
        $modificationTime = date('Y-m-d H:i:s');
        $totalChanges = count($changes);
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>PMT Record Update Notification</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #151A2D;
                    color: white;
                    padding: 20px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .summary {
                    background-color: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 5px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .summary-item {
                    margin-bottom: 8px;
                }
                .summary-item strong {
                    color: #151A2D;
                }
                .changes-section {
                    margin-bottom: 20px;
                }
                .changes-title {
                    color: #151A2D;
                    font-size: 18px;
                    margin-bottom: 15px;
                    padding-bottom: 5px;
                    border-bottom: 2px solid #4dabf7;
                }
                .change-item {
                    background-color: #fff;
                    border: 1px solid #dee2e6;
                    border-radius: 5px;
                    padding: 15px;
                    margin-bottom: 10px;
                }
                .field-name {
                    font-weight: bold;
                    color: #151A2D;
                    margin-bottom: 8px;
                }
                .value-change {
                    display: flex;
                    gap: 20px;
                    align-items: center;
                }
                .old-value, .new-value {
                    flex: 1;
                    padding: 8px;
                    border-radius: 3px;
                }
                .old-value {
                    background-color: #ffe6e6;
                    border-left: 4px solid #e74c3c;
                }
                .new-value {
                    background-color: #e6ffe6;
                    border-left: 4px solid #2ecc71;
                }
                .arrow {
                    color: #666;
                    font-weight: bold;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #dee2e6;
                    color: #666;
                    font-size: 12px;
                }
                .btn {
                    display: inline-block;
                    background-color: #4dabf7;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>📝 PMT Record Update Notification</h1>
            </div>
            
            <div class='summary'>
                <div class='summary-item'><strong>Candidate:</strong> {$candidateName}</div>
                <div class='summary-item'><strong>Record ID:</strong> {$recordId}</div>
                <div class='summary-item'><strong>Modified By:</strong> " . ($modifiedByName ? $modifiedByName : $modifiedBy) . " ({$modifiedBy})</div>
                <div class='summary-item'><strong>Modification Time:</strong> {$modificationTime}</div>
                <div class='summary-item'><strong>Total Changes:</strong> {$totalChanges}</div>
            </div>
            
            <div class='changes-section'>
                <h2 class='changes-title'>📋 Changes Made</h2>";
        
        foreach ($changes as $change) {
            $fieldName = htmlspecialchars($change['field_name']);
            $oldValue = htmlspecialchars($change['old_value'] ?: '(Empty)');
            $newValue = htmlspecialchars($change['new_value'] ?: '(Empty)');
            
            $html .= "
                <div class='change-item'>
                    <div class='field-name'>{$fieldName}</div>
                    <div class='value-change'>
                        <div class='old-value'>
                            <strong>Before:</strong><br>
                            {$oldValue}
                        </div>
                        <div class='arrow'>→</div>
                        <div class='new-value'>
                            <strong>After:</strong><br>
                            {$newValue}
                        </div>
                    </div>
                </div>";
        }
        
        $html .= "
            </div>
            
            <div class='footer'>
                <p>This is an automated notification from the PMT System. Please do not reply to this email.</p>
                <p>Generated on {$modificationTime}</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Build plain text email body
     */
    private function buildPlainTextBody($changes, $recordInfo, $modifiedBy, $modifiedByName) {
        $candidateName = $recordInfo['candidate_name'] ?? 'Unknown Candidate';
        $recordId = $recordInfo['record_id'] ?? 'Unknown ID';
        $modificationTime = date('Y-m-d H:i:s');
        $totalChanges = count($changes);
        
        $text = "PMT RECORD UPDATE NOTIFICATION\n";
        $text .= "=====================================\n\n";
        $text .= "Candidate: {$candidateName}\n";
        $text .= "Record ID: {$recordId}\n";
        $text .= "Modified By: " . ($modifiedByName ? $modifiedByName : $modifiedBy) . " ({$modifiedBy})\n";
        $text .= "Modification Time: {$modificationTime}\n";
        $text .= "Total Changes: {$totalChanges}\n\n";
        
        $text .= "CHANGES MADE:\n";
        $text .= "=============\n\n";
        
        foreach ($changes as $change) {
            $fieldName = $change['field_name'];
            $oldValue = $change['old_value'] ?: '(Empty)';
            $newValue = $change['new_value'] ?: '(Empty)';
            
            $text .= "Field: {$fieldName}\n";
            $text .= "Before: {$oldValue}\n";
            $text .= "After: {$newValue}\n";
            $text .= "---\n\n";
        }
        
        $text .= "This is an automated notification from the PMT System.\n";
        $text .= "Generated on {$modificationTime}";
        
        return $text;
    }
    
    /**
     * Log sent email for audit purposes
     */
    private function logEmailSent($changes, $recordInfo, $modifiedBy) {
        $logEntry = date('Y-m-d H:i:s') . " - ";
        $logEntry .= "Admin notification sent for Record ID: " . ($recordInfo['record_id'] ?? 'Unknown') . " - ";
        $logEntry .= "Candidate: " . ($recordInfo['candidate_name'] ?? 'Unknown') . " - ";
        $logEntry .= "Modified by: " . $modifiedBy . " - ";
        $logEntry .= "Changes: " . count($changes) . " fields updated";
        $logEntry .= "\n";
        
        $logFile = __DIR__ . '/../email_submissions.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>
