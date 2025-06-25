<?php
/**
 * Test Email Functionality
 * 
 * This script allows you to test the admin notification email system
 * to ensure it's working correctly before using it in production.
 */

require_once '../db.php';
require_once 'AdminNotificationMailer.php';
require_once '../email_config.php';

// Check if this is a test request
if (isset($_POST['test_email'])) {
    try {
        $adminMailer = new AdminNotificationMailer();
        
        // Create sample changes for testing
        $testChanges = [
            [
                'field_name' => 'First Name',
                'old_value' => 'John',
                'new_value' => 'Johnny'
            ],
            [
                'field_name' => 'Status',
                'old_value' => 'Active',
                'new_value' => 'On Hold'
            ],
            [
                'field_name' => 'Client Rate',
                'old_value' => '$75.00/hour USD',
                'new_value' => '$80.00/hour USD'
            ]
        ];
        
        // Sample record information
        $recordInfo = [
            'candidate_name' => 'Test Candidate',
            'record_id' => 'TEST-001'
        ];
        
        // Send test notification
        $emailSent = $adminMailer->sendChangeNotification(
            $testChanges, 
            $recordInfo, 
            'test@example.com', 
            'Test User'
        );
        
        if ($emailSent) {
            $message = "✅ Test email sent successfully to: " . ADMIN_EMAIL;
            $messageType = "success";
        } else {
            $message = "❌ Failed to send test email. Please check your email configuration.";
            $messageType = "error";
        }
        
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System Test - PMT</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .config-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .config-item {
            margin-bottom: 10px;
        }
        .config-label {
            font-weight: bold;
            color: #333;
        }
        .config-value {
            color: #666;
            margin-left: 10px;
        }
        .test-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn {
            background: #151A2D;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #2c3352;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .instructions {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ffeaa7;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Email System Test</h1>
            <p>Test the admin notification email system</p>
        </div>

        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="instructions">
            <h3>📋 Instructions</h3>
            <p>1. Verify your email configuration below</p>
            <p>2. Click "Send Test Email" to test the system</p>
            <p>3. Check your admin email inbox for the test notification</p>
            <p>4. If the test fails, check your email configuration in <code>email_config.php</code></p>
        </div>

        <div class="config-info">
            <h3>📊 Current Email Configuration</h3>
            
            <div class="config-item">
                <span class="config-label">Admin Email:</span>
                <span class="config-value"><?php echo ADMIN_EMAIL; ?></span>
            </div>
            
            <div class="config-item">
                <span class="config-label">System From Email:</span>
                <span class="config-value"><?php echo SYSTEM_FROM_EMAIL; ?></span>
            </div>
            
            <div class="config-item">
                <span class="config-label">SMTP Host:</span>
                <span class="config-value"><?php echo SMTP_HOST; ?></span>
            </div>
            
            <div class="config-item">
                <span class="config-label">SMTP Port:</span>
                <span class="config-value"><?php echo SMTP_PORT; ?></span>
            </div>
            
            <div class="config-item">
                <span class="config-label">SMTP Authentication:</span>
                <span class="config-value"><?php echo SMTP_AUTH ? 'Enabled' : 'Disabled'; ?></span>
            </div>
            
            <div class="config-item">
                <span class="config-label">Email Notifications:</span>
                <span class="config-value"><?php echo ENABLE_ADMIN_NOTIFICATIONS ? 'Enabled' : 'Disabled'; ?></span>
            </div>
            
            <div class="config-item">
                <span class="config-label">Email Logging:</span>
                <span class="config-value"><?php echo ENABLE_EMAIL_LOGGING ? 'Enabled' : 'Disabled'; ?></span>
            </div>
        </div>

        <div class="test-section">
            <h3>🧪 Send Test Email</h3>
            <p>This will send a test notification email to the configured admin address with sample change data.</p>
            
            <form method="post">
                <button type="submit" name="test_email" class="btn">
                    📤 Send Test Email
                </button>
            </form>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="paperworkallrecords.php" style="color: #151A2D; text-decoration: none;">
                ← Back to PMT System
            </a>
        </div>
    </div>
</body>
</html>
