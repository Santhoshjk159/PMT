<?php
// Include the database connection file
include 'db.php';
session_start();

// Include email configuration
require_once 'email_config.php';

// Include the AdminNotificationMailer class
require_once 'EmailSystem/AdminNotificationMailer.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type for JSON response
header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle both JSON and FormData inputs
    $recordId = null;
    $status = null;
    $reason = '';
    $changedBy = $_SESSION['email'] ?? 'Unknown User';
    
    // Check if it's JSON data or FormData
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // Handle JSON data
        $data = json_decode(file_get_contents('php://input'), true);
        $recordId = !empty($data['id']) ? intval($data['id']) : null;
        $status = !empty($data['status']) ? $data['status'] : null;
        $reason = !empty($data['reason']) ? $data['reason'] : '';
    } else {
        // Handle FormData
        $recordId = !empty($_POST['id']) ? intval($_POST['id']) : null;
        $status = !empty($_POST['status']) ? $_POST['status'] : null;
        $reason = !empty($_POST['reason']) ? $_POST['reason'] : '';
        
        // Handle start_date if provided
        if (!empty($_POST['start_date'])) {
            $reason = "Start Date: " . $_POST['start_date'];
        }
    }

    // Check if status is 'started' and format reason to include the start date
    if ($status === 'started' && $reason) {
        $formattedStartDate = DateTime::createFromFormat('Y-m-d', trim(str_replace('Start Date: ', '', $reason)));
        if ($formattedStartDate) {
            $reason = "Start Date: " . $formattedStartDate->format('Y-m-d'); // Format as required
        }
    }

    // Check if both record ID and status are provided
    if ($recordId && $status) {
        // Retrieve the current status and candidate info from the database before updating
        $currentStatus = '';
        $candidateName = '';
        $selectQuery = "SELECT status, cfirstname, clastname FROM paperworkdetails WHERE id = ?";
        if ($stmt = $conn->prepare($selectQuery)) {
            $stmt->bind_param("i", $recordId);
            $stmt->execute();
            $stmt->bind_result($currentStatus, $firstName, $lastName);
            $stmt->fetch();
            $candidateName = $firstName . ' ' . $lastName;
            $stmt->close();
        }

        // Prepare the SQL query to update the status and reason in the database
        $updateQuery = "UPDATE paperworkdetails SET status = ?, reason = ? WHERE id = ?";
        if ($stmt = $conn->prepare($updateQuery)) {
            // Bind parameters: 's' for strings, 'i' for integers
            $stmt->bind_param('ssi', $status, $reason, $recordId);

            // Execute the statement
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    // Create status_change_log table if it doesn't exist
                    $createTableQuery = "CREATE TABLE IF NOT EXISTS `status_change_log` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `record_id` int(11) NOT NULL,
                        `old_status` varchar(100) DEFAULT NULL,
                        `new_status` varchar(100) NOT NULL,
                        `changed_by` varchar(255) NOT NULL,
                        `reason` text DEFAULT NULL,
                        `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `record_id` (`record_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $conn->query($createTableQuery);
                    
                    // Prepare the log insertion query for status_change_log
                    $logQuery = "INSERT INTO status_change_log (record_id, old_status, new_status, changed_by, reason) VALUES (?, ?, ?, ?, ?)";
                    if ($logStmt = $conn->prepare($logQuery)) {
                        $logStmt->bind_param("issss", $recordId, $currentStatus, $status, $changedBy, $reason);
                        $logStmt->execute();
                        $logStmt->close();
                    }
                    
                    // Send email notification to admin about status change
                    if (!empty($currentStatus) && $currentStatus !== $status) {
                        try {
                            // Get user's full name for email
                            $userName = '';
                            $userQuery = "SELECT name FROM users WHERE email = ?";
                            if ($userStmt = $conn->prepare($userQuery)) {
                                $userStmt->bind_param("s", $changedBy);
                                $userStmt->execute();
                                $userStmt->bind_result($userName);
                                $userStmt->fetch();
                                $userStmt->close();
                            }
                            
                            $adminMailer = new AdminNotificationMailer();
                            
                            // Prepare status change information for email
                            $statusChange = [
                                [
                                    'field_name' => 'Status',
                                    'old_value' => formatStatusForEmail($currentStatus),
                                    'new_value' => formatStatusForEmail($status)
                                ]
                            ];
                            
                            // Add reason if provided
                            if (!empty($reason)) {
                                $statusChange[] = [
                                    'field_name' => 'Status Change Reason',
                                    'old_value' => '(No reason)',
                                    'new_value' => $reason
                                ];
                            }
                            
                            // Prepare record information for email
                            $recordInfo = [
                                'candidate_name' => $candidateName,
                                'record_id' => $recordId
                            ];
                            
                            // Send the notification using the status notification mailer
                            $emailSent = sendStatusChangeNotification($statusChange, $recordInfo, $changedBy, $userName);
                            
                            if ($emailSent) {
                                error_log("Status change notification email sent successfully for record ID: $recordId");
                            } else {
                                error_log("Failed to send status change notification email for record ID: $recordId");
                            }
                        } catch (Exception $emailException) {
                            error_log("Exception while sending status change notification email: " . $emailException->getMessage());
                            // Don't fail the entire operation if email fails
                        }
                    }
                    
                    echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'No rows affected - record may not exist']);
                }
            } else {
                echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Update failed: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Database prepare error: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid data - missing ID or status']);
    }
} else {
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid request method']);
}

/**
 * Format status codes into readable text for email
 */
function formatStatusForEmail($status) {
    $statusMap = [
        'paperwork_created' => 'Paperwork Created',
        'initiated_agreement_bgv' => 'Initiated – Agreement, BGV',
        'paperwork_closed' => 'Paperwork Closed',
        'started' => 'Started',
        'client_hold' => 'Client – Hold',
        'client_dropped' => 'Client – Dropped',
        'backout' => 'Backout',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
    
    return $statusMap[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/**
 * Send status change notification email to admin
 */
function sendStatusChangeNotification($changes, $recordInfo, $modifiedBy, $modifiedByName = '') {
    // Check if email notifications are enabled
    if (!ENABLE_ADMIN_NOTIFICATIONS || empty($changes)) {
        return true; // Return true to not break the workflow
    }
    
    try {
        // Include PHPMailer files
        require_once __DIR__ . '/PHPMailer-6.9.3/src/Exception.php';
        require_once __DIR__ . '/PHPMailer-6.9.3/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer-6.9.3/src/SMTP.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configure SMTP settings from config
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Port       = SMTP_PORT;
        
        if (SMTP_AUTH) {
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
        }
        
        if (SMTP_SECURE) {
            $mail->SMTPSecure = SMTP_SECURE;
        }
        
        $mail->isHTML(true);
        
        // Set email properties
        $mail->setFrom(SYSTEM_FROM_EMAIL, SYSTEM_FROM_NAME);
        $mail->addAddress(ADMIN_EMAIL); // Primary admin
        $mail->addAddress('santhosh.jk@vdartinc.com'); // Copy to Santhosh
        $mail->addReplyTo($modifiedBy, $modifiedByName);
        
        // Set subject with status change indication
        $candidateName = $recordInfo['candidate_name'] ?? 'Unknown Candidate';
        $recordId = $recordInfo['record_id'] ?? 'Unknown ID';
        $mail->Subject = "PMT Status Changed - {$candidateName} (ID: {$recordId})";
        
        // Build email body with status change specific design
        $mail->Body = buildStatusChangeEmailBody($changes, $recordInfo, $modifiedBy, $modifiedByName);
        $mail->AltBody = buildStatusChangePlainTextBody($changes, $recordInfo, $modifiedBy, $modifiedByName);
        
        // Send email
        $result = $mail->send();
        
        // Log the email if logging is enabled
        if ($result && ENABLE_EMAIL_LOGGING) {
            $logEntry = date('Y-m-d H:i:s') . " - ";
            $logEntry .= "Status change notification sent for Record ID: " . ($recordInfo['record_id'] ?? 'Unknown') . " - ";
            $logEntry .= "Candidate: " . ($recordInfo['candidate_name'] ?? 'Unknown') . " - ";
            $logEntry .= "Modified by: " . $modifiedBy . " - ";
            $logEntry .= "Status changed";
            $logEntry .= "\n";
            
            $logFile = __DIR__ . '/email_submissions.log';
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Status change notification email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Build HTML email body for status changes
 */
function buildStatusChangeEmailBody($changes, $recordInfo, $modifiedBy, $modifiedByName) {
    $candidateName = $recordInfo['candidate_name'] ?? 'Unknown Candidate';
    $recordId = $recordInfo['record_id'] ?? 'Unknown ID';
    $modificationTime = date('Y-m-d H:i:s');
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>PMT Status Change Notification</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.5;
                color: #333333;
                margin: 0;
                padding: 0;
                background-color: #f5f5f5;
            }
            .email-container {
                max-width: 600px;
                margin: 20px auto;
                background-color: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                background-color: #1e3a8a;
                color: white;
                padding: 30px 40px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content {
                padding: 40px;
            }
            .info-section {
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 24px;
                margin-bottom: 30px;
            }
            .info-row {
                display: flex;
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 1px solid #e2e8f0;
            }
            .info-row:last-child {
                margin-bottom: 0;
                border-bottom: none;
                padding-bottom: 0;
            }
            .info-label {
                font-weight: 600;
                color: #374151;
                min-width: 120px;
                margin-right: 16px;
            }
            .info-value {
                color: #1f2937;
            }
            .status-section {
                margin-bottom: 30px;
            }
            .section-title {
                color: #1e3a8a;
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 20px;
                padding-bottom: 8px;
                border-bottom: 2px solid #1e3a8a;
            }
            .status-change-item {
                background-color: #ffffff;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                padding: 20px;
                margin-bottom: 16px;
            }
            .change-label {
                font-weight: 600;
                color: #374151;
                margin-bottom: 12px;
                font-size: 16px;
            }
            .status-comparison {
                display: table;
                width: 100%;
                border-collapse: separate;
                border-spacing: 12px 0;
            }
            .status-item {
                display: table-cell;
                width: 45%;
                padding: 12px 16px;
                border-radius: 4px;
                text-align: center;
                font-weight: 500;
                vertical-align: middle;
            }
            .old-status {
                background-color: #fef2f2;
                color: #dc2626;
                border: 1px solid #fecaca;
            }
            .new-status {
                background-color: #f0fdf4;
                color: #16a34a;
                border: 1px solid #bbf7d0;
            }
            .arrow {
                display: table-cell;
                width: 10%;
                text-align: center;
                vertical-align: middle;
                font-size: 18px;
                color: #6b7280;
            }
            .reason-box {
                margin-top: 16px;
                padding: 16px;
                background-color: #f8fafc;
                border-left: 4px solid #1e3a8a;
                border-radius: 0 4px 4px 0;
            }
            .reason-label {
                font-weight: 600;
                color: #374151;
                margin-bottom: 8px;
            }
            .reason-text {
                color: #1f2937;
            }
            .footer {
                background-color: #f8fafc;
                padding: 24px 40px;
                text-align: center;
                border-top: 1px solid #e2e8f0;
            }
            .footer-text {
                color: #6b7280;
                font-size: 14px;
                margin: 0;
            }
            .timestamp {
                color: #9ca3af;
                font-size: 13px;
                margin-top: 8px;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>PMT Status Change Notification</h1>
            </div>
            
            <div class='content'>
                <div class='info-section'>
                    <div class='info-row'>
                        <div class='info-label'>Candidate:</div>
                        <div class='info-value'>{$candidateName}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Record ID:</div>
                        <div class='info-value'>{$recordId}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Changed By:</div>
                        <div class='info-value'>" . ($modifiedByName ? $modifiedByName : $modifiedBy) . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Change Time:</div>
                        <div class='info-value'>{$modificationTime}</div>
                    </div>
                </div>
                
                <div class='status-section'>
                    <h2 class='section-title'>Status Changes</h2>";
    
    foreach ($changes as $change) {
        $fieldName = htmlspecialchars($change['field_name']);
        $oldValue = htmlspecialchars($change['old_value'] ?: '(Not Set)');
        $newValue = htmlspecialchars($change['new_value'] ?: '(Not Set)');
        
        $html .= "
                    <div class='status-change-item'>
                        <div class='change-label'>{$fieldName}</div>
                        <div class='status-comparison'>
                            <div class='status-item old-status'>{$oldValue}</div>
                            <div class='arrow'>→</div>
                            <div class='status-item new-status'>{$newValue}</div>
                        </div>";
        
        if ($fieldName === 'Status Change Reason' && $newValue !== '(Not Set)') {
            $html .= "
                        <div class='reason-box'>
                            <div class='reason-label'>Reason:</div>
                            <div class='reason-text'>{$newValue}</div>
                        </div>";
        }
        
        $html .= "
                    </div>";
    }
    
    $html .= "
                </div>
            </div>
            
            <div class='footer'>
                <p class='footer-text'>This is an automated notification from the PMT System</p>
                <p class='timestamp'>Generated on {$modificationTime}</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

/**
 * Build plain text email body for status changes
 */
function buildStatusChangePlainTextBody($changes, $recordInfo, $modifiedBy, $modifiedByName) {
    $candidateName = $recordInfo['candidate_name'] ?? 'Unknown Candidate';
    $recordId = $recordInfo['record_id'] ?? 'Unknown ID';
    $modificationTime = date('Y-m-d H:i:s');
    
    $text = "PMT STATUS CHANGE NOTIFICATION\n";
    $text .= "=====================================\n\n";
    $text .= "Candidate: {$candidateName}\n";
    $text .= "Record ID: {$recordId}\n";
    $text .= "Changed By: " . ($modifiedByName ? $modifiedByName : $modifiedBy) . " ({$modifiedBy})\n";
    $text .= "Change Time: {$modificationTime}\n\n";
    
    $text .= "STATUS CHANGES:\n";
    $text .= "===============\n\n";
    
    foreach ($changes as $change) {
        $fieldName = $change['field_name'];
        $oldValue = $change['old_value'] ?: '(Not Set)';
        $newValue = $change['new_value'] ?: '(Not Set)';
        
        $text .= "{$fieldName}:\n";
        $text .= "  Previous: {$oldValue}\n";
        $text .= "  New: {$newValue}\n";
        $text .= "---\n\n";
    }
    
    $text .= "This is an automated notification from the PMT System.\n";
    $text .= "Generated on {$modificationTime}";
    
    return $text;
}
?>

