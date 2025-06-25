<?php
/**
 * Simple Email Trigger System
 * 
 * A standalone implementation of an email trigger system that can be
 * used to test out the concept without a full ticketing system.
 */

class SimpleEmailTrigger {
    private $name;
    private $conditions = [];
    private $action;
    
    /**
     * Constructor
     * 
     * @param string $name Name of the trigger
     */
    public function __construct($name) {
        $this->name = $name;
    }
    
    /**
     * Add a condition to this trigger
     * 
     * @param string $field Email field to check (subject, body, from, to)
     * @param string $operator Comparison operator (contains, equals, etc.)
     * @param mixed $value Value to compare against
     * @return $this For method chaining
     */
    public function when($field, $operator, $value) {
        $this->conditions[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value
        ];
        
        return $this;
    }
    
    /**
     * Set the action to perform when triggered
     * 
     * @param string $recipientEmail Email to send to
     * @param string $subject Email subject
     * @param string $body Email body
     * @return $this For method chaining
     */
    public function send($recipientEmail, $subject, $body) {
        $this->action = [
            'type' => 'send_email',
            'recipient' => $recipientEmail,
            'subject' => $subject,
            'body' => $body
        ];
        
        return $this;
    }
    
    /**
     * Check if the trigger should fire based on the email
     * 
     * @param array $email Parsed email data
     * @return bool True if trigger should fire
     */
    public function shouldFire($email) {
        foreach ($this->conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            
            // Get the field value from the email
            $fieldValue = $this->getFieldValue($email, $field);
            
            // Skip this condition if the field doesn't exist
            if ($fieldValue === null) {
                return false;
            }
            
            // Check the condition
            $match = $this->evaluateCondition($fieldValue, $operator, $value);
            
            // If any condition fails, the trigger shouldn't fire
            if (!$match) {
                return false;
            }
        }
        
        // All conditions passed
        return true;
    }
    
    /**
     * Get a field value from the email
     * 
     * @param array $email Parsed email data
     * @param string $field Field name
     * @return mixed Field value or null if not found
     */
    private function getFieldValue($email, $field) {
        switch ($field) {
            case 'subject':
                return isset($email['subject']) ? $email['subject'] : null;
            case 'body':
                return isset($email['body']) ? $email['body'] : null;
            case 'from':
                return isset($email['from']) ? $email['from'] : null;
            case 'to':
                return isset($email['to']) ? $email['to'] : null;
            default:
                // Check if this is a header field
                if (isset($email['headers'][$field])) {
                    return $email['headers'][$field];
                }
                return null;
        }
    }
    
    /**
     * Evaluate a condition
     * 
     * @param mixed $fieldValue Value from the email
     * @param string $operator Comparison operator
     * @param mixed $expectedValue Value to compare against
     * @return bool True if condition is met
     */
    private function evaluateCondition($fieldValue, $operator, $expectedValue) {
        switch ($operator) {
            case 'equals':
                return $fieldValue == $expectedValue;
            case 'not_equals':
                return $fieldValue != $expectedValue;
            case 'contains':
                return is_string($fieldValue) && 
                       strpos($fieldValue, $expectedValue) !== false;
            case 'not_contains':
                return is_string($fieldValue) && 
                       strpos($fieldValue, $expectedValue) === false;
            case 'starts_with':
                return is_string($fieldValue) && 
                       strpos($fieldValue, $expectedValue) === 0;
            case 'ends_with':
                return is_string($fieldValue) && 
                       substr($fieldValue, -strlen($expectedValue)) === $expectedValue;
            case 'matches':
                return is_string($fieldValue) && 
                       preg_match($expectedValue, $fieldValue);
            default:
                return false;
        }
    }
    
    /**
     * Execute the trigger action
     * 
     * @param array $email Parsed email data
     * @return bool True if action was performed
     */
    public function execute($email) {
        if (!$this->action) {
            return false;
        }
        
        if ($this->action['type'] === 'send_email') {
            $recipient = $this->action['recipient'];
            $subject = $this->replacePlaceholders($this->action['subject'], $email);
            $body = $this->replacePlaceholders($this->action['body'], $email);
            
            $this->sendEmail($recipient, $subject, $body);
            return true;
        }
        
        return false;
    }
    
    /**
     * Replace placeholders in text with email data
     * 
     * @param string $text Text with placeholders
     * @param array $email Email data
     * @return string Text with replaced placeholders
     */
    private function replacePlaceholders($text, $email) {
        $placeholders = [
            '{subject}' => isset($email['subject']) ? $email['subject'] : '',
            '{from}' => isset($email['from']) ? $email['from'] : '',
            '{to}' => isset($email['to']) ? $email['to'] : '',
            '{date}' => isset($email['date']) ? $email['date'] : date('Y-m-d H:i:s'),
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }
    
    /**
     * Send an email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @return bool True if email was sent
     */
    private function sendEmail($to, $subject, $body) {
        echo "Sending email to: $to\n";
        echo "Subject: $subject\n";
        echo "Body: $body\n";
        
        // In a real implementation, this would use mail() or a library
        // For testing, we just print the email details
        
        // Uncomment to actually send the email
        
        $headers = "From: saranraj.s@vdartinc.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($to, $subject, $body, $headers);
     
        
        return true;
    }
}

/**
 * Simple email parser
 * 
 * @param string $rawEmail Raw email content
 * @return array Parsed email data
 */
function parseEmail($rawEmail) {
    // In a real implementation, use a proper email parsing library
    // This is a very simplified version for demonstration
    
    $email = [
        'headers' => [],
        'body' => ''
    ];
    
    // Split headers and body
    $parts = explode("\r\n\r\n", $rawEmail, 2);
    
    if (count($parts) < 2) {
        $email['body'] = $rawEmail;
        return $email;
    }
    
    $headerText = $parts[0];
    $email['body'] = $parts[1];
    
    // Parse headers
    $headerLines = explode("\r\n", $headerText);
    $currentHeader = '';
    
    foreach ($headerLines as $line) {
        if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $matches)) {
            $currentHeader = strtolower($matches[1]);
            $email['headers'][$currentHeader] = $matches[2];
        } elseif ($currentHeader && preg_match('/^\s+(.+)$/', $line, $matches)) {
            // Header continuation
            $email['headers'][$currentHeader] .= ' ' . $matches[1];
        }
    }
    
    // Extract common fields
    if (isset($email['headers']['from'])) {
        $email['from'] = $email['headers']['from'];
    }
    
    if (isset($email['headers']['to'])) {
        $email['to'] = $email['headers']['to'];
    }
    
    if (isset($email['headers']['subject'])) {
        $email['subject'] = $email['headers']['subject'];
    }
    
    if (isset($email['headers']['date'])) {
        $email['date'] = $email['headers']['date'];
    }
    
    return $email;
}

// Example usage function
function testEmailTrigger() {
    // Create our trigger
    $trigger = new SimpleEmailTrigger('Support Request Auto-Response');
    
    // Configure the conditions
    $trigger->when('subject', 'contains', 'support')
            ->when('to', 'equals', 'support@example.com');
    
    // Configure the action
    $trigger->send('{from}', 'RE: {subject}', 
        "Thank you for contacting our support team.<br><br>" .
        "We have received your request and will get back to you as soon as possible.<br><br>" .
        "Your message: {subject}<br><br>" .
        "Regards,<br>Support Team"
    );
    
    // Test with a sample email
    $sampleEmail = "From: customer@example.com\r\n" .
                  "To: support@example.com\r\n" .
                  "Subject: Need support with login issue\r\n" .
                  "Date: Mon, 15 Mar 2024 10:30:45 +0000\r\n" .
                  "\r\n" .
                  "Hello,\r\n" .
                  "\r\n" .
                  "I'm having trouble logging into my account. Can you help?\r\n" .
                  "\r\n" .
                  "Thanks,\r\n" .
                  "John";
    
    // Parse the email
    $parsedEmail = parseEmail($sampleEmail);
    
    // Check if trigger should fire
    if ($trigger->shouldFire($parsedEmail)) {
        echo "Trigger fired!\n";
        $trigger->execute($parsedEmail);
    } else {
        echo "Trigger did not fire.\n";
    }
    
    // Test with a non-matching email
    $nonMatchingEmail = "From: someone@example.com\r\n" .
                       "To: info@example.com\r\n" .
                       "Subject: Newsletter signup\r\n" .
                       "Date: Mon, 15 Mar 2024 11:15:30 +0000\r\n" .
                       "\r\n" .
                       "Please sign me up for your newsletter.\r\n" .
                       "\r\n" .
                       "Thanks,\r\n" .
                       "Jane";
    
    $parsedNonMatching = parseEmail($nonMatchingEmail);
    
    if ($trigger->shouldFire($parsedNonMatching)) {
        echo "Trigger fired for non-matching email!\n";
        $trigger->execute($parsedNonMatching);
    } else {
        echo "Trigger correctly did not fire for non-matching email.\n";
    }
}

// This allows the script to be run directly or included
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    testEmailTrigger();
}

// Example of how to use this in a real application
function processIncomingEmail() {
    // Set up triggers
    $triggers = [
        // Support request auto-response
        (new SimpleEmailTrigger('Support Auto-Response'))
            ->when('to', 'contains', 'support@')
            ->send('{from}', 'RE: {subject}', 
                "Thank you for contacting support.<br><br>" .
                "Your request has been received and we'll respond shortly.<br><br>" .
                "Regards,<br>Support Team"
            ),
        
        // Sales inquiry auto-response
        (new SimpleEmailTrigger('Sales Auto-Response'))
            ->when('subject', 'contains', 'pricing')
            ->when('subject', 'contains', 'quote')
            ->send('{from}', 'RE: {subject}', 
                "Thank you for your interest in our products.<br><br>" .
                "A sales representative will contact you with pricing information soon.<br><br>" .
                "Regards,<br>Sales Team"
            ),
        
        // Out of office notification
        (new SimpleEmailTrigger('Out of Office'))
            ->when('to', 'equals', 'john@example.com')
            ->send('{from}', 'Out of Office: Re: {subject}', 
                "I'm currently out of the office until March 20th with limited email access.<br><br>" .
                "For urgent matters, please contact jane@example.com.<br><br>" .
                "Regards,<br>John"
            )
    ];
    
    // Get raw email (from stdin, file, or API in a real system)
    $rawEmail = file_get_contents('php://stdin');
    
    // Parse the email
    $email = parseEmail($rawEmail);
    
    // Process through all triggers
    $triggerFired = false;
    
    foreach ($triggers as $trigger) {
        if ($trigger->shouldFire($email)) {
            $trigger->execute($email);
            $triggerFired = true;
        }
    }
    
    return $triggerFired;
}