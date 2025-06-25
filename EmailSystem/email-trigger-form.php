<?php
// Include the trigger system
require_once 'simple-email-trigger.php';

// Process form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Construct email from form data
    $rawEmail = "From: {$_POST['from']}\r\n" .
                "To: {$_POST['to']}\r\n" .
                "Subject: {$_POST['subject']}\r\n" .
                "Date: " . date('r') . "\r\n" .
                "\r\n" .
                $_POST['body'];
    
    // Parse the email
    $parsedEmail = parseEmail($rawEmail);
    
    // Create our trigger
    $trigger = new SimpleEmailTrigger('Support Request Auto-Response');
    
    // Configure the conditions based on form settings
    if (!empty($_POST['condition_field']) && !empty($_POST['condition_operator']) && !empty($_POST['condition_value'])) {
        $trigger->when($_POST['condition_field'], $_POST['condition_operator'], $_POST['condition_value']);
    }
    
    // Add a second condition if provided
    if (!empty($_POST['condition_field2']) && !empty($_POST['condition_operator2']) && !empty($_POST['condition_value2'])) {
        $trigger->when($_POST['condition_field2'], $_POST['condition_operator2'], $_POST['condition_value2']);
    }
    
    // Configure the action
    $trigger->send($_POST['response_to'], $_POST['response_subject'], $_POST['response_body']);
    
    // Check if trigger should fire
    ob_start();
    if ($trigger->shouldFire($parsedEmail)) {
        echo "<div class='success'>Trigger matched! Sending automatic response:</div>";
        $trigger->execute($parsedEmail);
    } else {
        echo "<div class='error'>Trigger conditions did not match. No response sent.</div>";
    }
    $message = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Trigger Tester</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            display: flex;
            gap: 20px;
        }
        .column {
            flex: 1;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            min-height: 100px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .card h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .success {
            color: green;
            padding: 10px;
            background-color: #e7f7e7;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .error {
            color: #721c24;
            padding: 10px;
            background-color: #f8d7da;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .result {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <h1>Email Trigger Tester</h1>
    
    <?php if (!empty($message)): ?>
        <div class="result"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <form method="post" action="">
        <div class="container">
            <div class="column">
                <div class="card">
                    <h3>Incoming Email</h3>
                    <div class="form-group">
                        <label for="from">From:</label>
                        <input type="text" id="from" name="from" value="<?php echo $_POST['from'] ?? 'customer@example.com'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="to">To:</label>
                        <input type="text" id="to" name="to" value="<?php echo $_POST['to'] ?? 'support@example.com'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" id="subject" name="subject" value="<?php echo $_POST['subject'] ?? 'Need support with login issue'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="body">Body:</label>
                        <textarea id="body" name="body" required><?php echo $_POST['body'] ?? "Hello,\n\nI'm having trouble logging into my account. Can you help?\n\nThanks,\nJohn"; ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="column">
                <div class="card">
                    <h3>Trigger Conditions</h3>
                    <div class="form-group">
                        <label for="condition_field">Field:</label>
                        <select id="condition_field" name="condition_field">
                            <option value="">Select field...</option>
                            <option value="subject" <?php echo ($_POST['condition_field'] ?? '') === 'subject' ? 'selected' : ''; ?>>Subject</option>
                            <option value="body" <?php echo ($_POST['condition_field'] ?? '') === 'body' ? 'selected' : ''; ?>>Body</option>
                            <option value="from" <?php echo ($_POST['condition_field'] ?? '') === 'from' ? 'selected' : ''; ?>>From</option>
                            <option value="to" <?php echo ($_POST['condition_field'] ?? 'to') === 'to' ? 'selected' : ''; ?>>To</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="condition_operator">Operator:</label>
                        <select id="condition_operator" name="condition_operator">
                            <option value="">Select operator...</option>
                            <option value="equals" <?php echo ($_POST['condition_operator'] ?? '') === 'equals' ? 'selected' : ''; ?>>Equals</option>
                            <option value="contains" <?php echo ($_POST['condition_operator'] ?? 'contains') === 'contains' ? 'selected' : ''; ?>>Contains</option>
                            <option value="starts_with" <?php echo ($_POST['condition_operator'] ?? '') === 'starts_with' ? 'selected' : ''; ?>>Starts with</option>
                            <option value="ends_with" <?php echo ($_POST['condition_operator'] ?? '') === 'ends_with' ? 'selected' : ''; ?>>Ends with</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="condition_value">Value:</label>
                        <input type="text" id="condition_value" name="condition_value" value="<?php echo $_POST['condition_value'] ?? 'support'; ?>">
                    </div>
                    
                    <h4>Additional Condition (Optional)</h4>
                    <div class="form-group">
                        <label for="condition_field2">Field:</label>
                        <select id="condition_field2" name="condition_field2">
                            <option value="">None</option>
                            <option value="subject" <?php echo ($_POST['condition_field2'] ?? '') === 'subject' ? 'selected' : ''; ?>>Subject</option>
                            <option value="body" <?php echo ($_POST['condition_field2'] ?? '') === 'body' ? 'selected' : ''; ?>>Body</option>
                            <option value="from" <?php echo ($_POST['condition_field2'] ?? '') === 'from' ? 'selected' : ''; ?>>From</option>
                            <option value="to" <?php echo ($_POST['condition_field2'] ?? '') === 'to' ? 'selected' : ''; ?>>To</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="condition_operator2">Operator:</label>
                        <select id="condition_operator2" name="condition_operator2">
                            <option value="">None</option>
                            <option value="equals" <?php echo ($_POST['condition_operator2'] ?? '') === 'equals' ? 'selected' : ''; ?>>Equals</option>
                            <option value="contains" <?php echo ($_POST['condition_operator2'] ?? '') === 'contains' ? 'selected' : ''; ?>>Contains</option>
                            <option value="starts_with" <?php echo ($_POST['condition_operator2'] ?? '') === 'starts_with' ? 'selected' : ''; ?>>Starts with</option>
                            <option value="ends_with" <?php echo ($_POST['condition_operator2'] ?? '') === 'ends_with' ? 'selected' : ''; ?>>Ends with</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="condition_value2">Value:</label>
                        <input type="text" id="condition_value2" name="condition_value2" value="<?php echo $_POST['condition_value2'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="card">
                    <h3>Auto-Response</h3>
                    <div class="form-group">
                        <label for="response_to">Send To:</label>
                        <input type="text" id="response_to" name="response_to" value="<?php echo $_POST['response_to'] ?? '{from}'; ?>" required>
                        <small>Use {from} to reply to sender</small>
                    </div>
                    <div class="form-group">
                        <label for="response_subject">Subject:</label>
                        <input type="text" id="response_subject" name="response_subject" value="<?php echo $_POST['response_subject'] ?? 'RE: {subject}'; ?>" required>
                        <small>Use {subject} to include original subject</small>
                    </div>
                    <div class="form-group">
                        <label for="response_body">Body:</label>
                        <textarea id="response_body" name="response_body" required><?php echo $_POST['response_body'] ?? "Thank you for contacting our support team.\n\nWe have received your request and will get back to you as soon as possible.\n\nYour message: {subject}\n\nRegards,\nSupport Team"; ?></textarea>
                        <small>Use {subject}, {from}, {to}, {date} as placeholders</small>
                    </div>
                </div>
            </div>
        </div>
        
        <button type="submit">Test Trigger</button>
    </form>
</body>
</html>