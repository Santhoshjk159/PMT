<?php
/**
 * osTicket-style Mail System with actual email sending capability
 */

// First, we need to install PHPMailer
// If you have Composer:
// composer require phpmailer/phpmailer

// If PHPMailer is installed via Composer, include autoloader
// require 'vendor/autoload.php';

// Manual include of PHPMailer files if you downloaded them directly
require '../PHPMailer-6.9.3/src/Exception.php';
require '../PHPMailer-6.9.3/src/PHPMailer.php';
require '../PHPMailer-6.9.3/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailConfig {
    // Default settings
    private $settings = [
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_auth' => false,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_secure' => 'tls',
        'default_from' => 'support@example.com',
        'default_from_name' => 'Support System',
        'default_reply_to' => 'support@example.com',
        'method' => 'mail' // 'mail', 'smtp', or 'sendmail'
    ];
    
    /**
     * Constructor
     * 
     * @param array $settings Email configuration settings
     */
    public function __construct($settings = []) {
        // Override defaults with provided settings
        foreach ($settings as $key => $value) {
            if (array_key_exists($key, $this->settings)) {
                $this->settings[$key] = $value;
            }
        }
    }
    
    /**
     * Get a configuration setting
     * 
     * @param string $key Setting key
     * @return mixed Setting value or null if not found
     */
    public function get($key) {
        return isset($this->settings[$key]) ? $this->settings[$key] : null;
    }
}

/**
 * Email template class
 */
class EmailTemplate {
    private $subject;
    private $body;
    private $variables = [];
    
    /**
     * Constructor
     * 
     * @param string $subject Email subject template
     * @param string $body Email body template
     */
    public function __construct($subject, $body) {
        $this->subject = $subject;
        $this->body = $body;
    }
    
    /**
     * Set template variables
     * 
     * @param array $variables Template variables
     */
    public function setVariables($variables) {
        $this->variables = $variables;
    }
    
    /**
     * Get processed subject
     * 
     * @return string Processed subject
     */
    public function getSubject() {
        return $this->processTemplate($this->subject);
    }
    
    /**
     * Get processed body
     * 
     * @return string Processed body
     */
    public function getBody() {
        return $this->processTemplate($this->body);
    }
    
    /**
     * Process template with variables
     * 
     * @param string $template Template text
     * @return string Processed text
     */
    private function processTemplate($template) {
        $processed = $template;
        
        // Process variables in %variable% format (osTicket style)
        foreach ($this->variables as $key => $value) {
            $processed = str_replace('%' . $key . '%', $value, $processed);
        }
        
        // Also support {variable} format
        foreach ($this->variables as $key => $value) {
            $processed = str_replace('{' . $key . '}', $value, $processed);
        }
        
        return $processed;
    }
}

/**
 * Abstract email transport class
 */
abstract class EmailTransport {
    /**
     * Send an email
     * 
     * @param string $to Recipient
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $options Additional options
     * @return bool True if email was sent
     */
    abstract public function send($to, $subject, $body, $options = []);
}

/**
 * Simple mail() transport
 */
class MailTransport extends EmailTransport {
    private $config;
    
    /**
     * Constructor
     * 
     * @param EmailConfig $config Email configuration
     */
    public function __construct(EmailConfig $config) {
        $this->config = $config;
    }
    
    /**
     * Send an email using mail()
     * 
     * @param string $to Recipient
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $options Additional options
     * @return bool True if email was sent
     */
    public function send($to, $subject, $body, $options = []) {
        $from = isset($options['from']) ? $options['from'] : $this->config->get('default_from');
        $fromName = isset($options['from_name']) ? $options['from_name'] : $this->config->get('default_from_name');
        
        $headers = "From: $fromName <$from>\r\n";
        $headers .= "Reply-To: " . (isset($options['reply_to']) ? $options['reply_to'] : $this->config->get('default_reply_to')) . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Log what we're sending
        echo "Using mail() transport<br>";
        echo "To: $to<br>";
        echo "Subject: $subject<br>";
        
        // Actually send the email
        $result = mail($to, $subject, $body, $headers);
        
        if ($result) {
            echo "Email sent successfully via mail()<br>";
            
            // Log to file for verification
            $logEntry = date('Y-m-d H:i:s') . " - Email sent to: $to - Subject: $subject\n";
            file_put_contents('email_log.txt', $logEntry, FILE_APPEND);
        } else {
            echo "Failed to send email via mail()<br>";
        }
        
        return $result;
    }
}

/**
 * SMTP transport using PHPMailer
 */
class SMTPTransport extends EmailTransport {
    private $config;
    
    /**
     * Constructor
     * 
     * @param EmailConfig $config Email configuration
     */
    public function __construct(EmailConfig $config) {
        $this->config = $config;
    }
    
    /**
     * Send an email using SMTP with PHPMailer
     * 
     * @param string $to Recipient
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $options Additional options
     * @return bool True if email was sent
     */
    public function send($to, $subject, $body, $options = []) {
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo "PHPMailer not available. Please install PHPMailer to use SMTP transport.<br>";
            return false;
        }
        
        $from = isset($options['from']) ? $options['from'] : $this->config->get('default_from');
        $fromName = isset($options['from_name']) ? $options['from_name'] : $this->config->get('default_from_name');
        
        // Display configuration
        echo "Using SMTP transport<br>";
        echo "SMTP Host: " . $this->config->get('smtp_host') . "<br>";
        echo "From: $fromName &lt;$from&gt;<br>";
        echo "To: $to<br>";
        echo "Subject: $subject<br>";
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config->get('smtp_host');
            $mail->Port = $this->config->get('smtp_port');
            
            if ($this->config->get('smtp_auth')) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->config->get('smtp_user');
                $mail->Password = $this->config->get('smtp_pass');
            }
            
            $secureOption = $this->config->get('smtp_secure');
            if ($secureOption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($secureOption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            // Enable verbose debug output
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Or use SMTP::DEBUG_OFF in production
            
            // Sender and recipient
            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo(isset($options['reply_to']) ? $options['reply_to'] : $this->config->get('default_reply_to'));
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));
            
            // Send the mail
            $result = $mail->send();
            
            if ($result) {
                echo "Email sent successfully via SMTP<br>";
                
                // Log to file for verification
                $logEntry = date('Y-m-d H:i:s') . " - Email sent to: $to - Subject: $subject\n";
                file_put_contents('email_log.txt', $logEntry, FILE_APPEND);
            }
            
            return $result;
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}<br>";
            return false;
        }
    }
}

/**
 * Email factory class
 */
class EmailFactory {
    /**
     * Create an email transport based on configuration
     * 
     * @param EmailConfig $config Email configuration
     * @return EmailTransport Email transport
     */
    public static function createTransport(EmailConfig $config) {
        switch ($config->get('method')) {
            case 'smtp':
                return new SMTPTransport($config);
            case 'sendmail':
                // In a real implementation, would have a SendmailTransport
                return new MailTransport($config);
            case 'mail':
            default:
                return new MailTransport($config);
        }
    }
}

/**
 * Main mailer class
 */
class Mailer {
    private $transport;
    private $defaultOptions = [];
    
    /**
     * Constructor
     * 
     * @param EmailTransport $transport Email transport
     * @param array $defaultOptions Default options
     */
    public function __construct(EmailTransport $transport, $defaultOptions = []) {
        $this->transport = $transport;
        $this->defaultOptions = $defaultOptions;
    }
    
    /**
     * Send an email
     * 
     * @param string $to Recipient
     * @param EmailTemplate $template Email template
     * @param array $options Additional options
     * @return bool True if email was sent
     */
    public function send($to, EmailTemplate $template, $options = []) {
        // Merge default options with provided options
        $options = array_merge($this->defaultOptions, $options);
        
        // Get subject and body from template
        $subject = $template->getSubject();
        $body = $template->getBody();
        
        // Send the email
        return $this->transport->send($to, $subject, $body, $options);
    }
}

/**
 * Email trigger class
 */
class EmailTrigger {
    private $name;
    private $conditions = [];
    private $template;
    private $options = [];
    
    /**
     * Constructor
     * 
     * @param string $name Trigger name
     * @param EmailTemplate $template Email template
     */
    public function __construct($name, EmailTemplate $template) {
        $this->name = $name;
        $this->template = $template;
    }
    
    /**
     * Add a condition
     * 
     * @param string $field Field to check
     * @param string $operator Comparison operator
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
     * Set send options
     * 
     * @param array $options Send options
     * @return $this For method chaining
     */
    public function withOptions($options) {
        $this->options = $options;
        return $this;
    }
    
    /**
     * Check if trigger should fire
     * 
     * @param array $data Data to check against conditions
     * @return bool True if should fire
     */
    public function shouldFire($data) {
        foreach ($this->conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            
            // Skip if field doesn't exist
            if (!isset($data[$field])) {
                return false;
            }
            
            $fieldValue = $data[$field];
            
            // Check condition
            $match = false;
            switch ($operator) {
                case 'equals':
                    $match = $fieldValue == $value;
                    break;
                case 'not_equals':
                    $match = $fieldValue != $value;
                    break;
                case 'contains':
                    $match = is_string($fieldValue) && strpos($fieldValue, $value) !== false;
                    break;
                case 'not_contains':
                    $match = is_string($fieldValue) && strpos($fieldValue, $value) === false;
                    break;
                case 'starts_with':
                    $match = is_string($fieldValue) && strpos($fieldValue, $value) === 0;
                    break;
                case 'ends_with':
                    $match = is_string($fieldValue) && substr($fieldValue, -strlen($value)) === $value;
                    break;
                default:
                    $match = false;
            }
            
            if (!$match) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Execute trigger
     * 
     * @param array $data Data for template variables
     * @param Mailer $mailer Mailer to use
     * @return bool True if executed
     */
    public function execute($data, Mailer $mailer) {
        // Set template variables
        $this->template->setVariables($data);
        
        // Determine recipient - in a real trigger system, we'd reply to sender
        $to = isset($data['from']) ? $data['from'] : '';
        
        if (empty($to)) {
            return false;
        }
        
        // Send email
        return $mailer->send($to, $this->template, $this->options);
    }
}

// Example usage function - modified to actually send emails
function testEmailTrigger() {
    // Configuration - REPLACE WITH YOUR ACTUAL CREDENTIALS
    $config = new EmailConfig([
        'method' => 'smtp',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_auth' => true,
        'smtp_user' => 'your-email@gmail.com',  // REPLACE THIS
        'smtp_pass' => 'your-app-password',     // REPLACE THIS
        'smtp_secure' => 'tls',
        'default_from' => 'your-email@gmail.com',  // REPLACE THIS
        'default_from_name' => 'Support System'
    ]);
    
    // Create transport based on config
    $transport = EmailFactory::createTransport($config);
    
    // Create mailer
    $mailer = new Mailer($transport);
    
    // Create template
    $template = new EmailTemplate(
        'RE: %subject%',
        'Thank you for contacting our support team.<br><br>' .
        'We have received your request and will get back to you as soon as possible.<br><br>' .
        'Your message: %subject%<br><br>' .
        'Regards,<br>Support Team'
    );
    
    // Create trigger
    $trigger = new EmailTrigger('Support Auto-Response', $template);
    $trigger->when('subject', 'contains', 'support')
            ->when('to', 'equals', 'support@example.com');
    
    // Sample email data - MODIFY THE "from" TO YOUR ACTUAL TARGET EMAIL
    $emailData = [
        'from' => 'recipient@example.com',  // REPLACE WITH ACTUAL RECIPIENT
        'to' => 'support@example.com',
        'subject' => 'Need support with login issue',
        'body' => "Hello,\n\nI'm having trouble logging into my account. Can you help?\n\nThanks,\nJohn"
    ];
    
    // Check and execute trigger
    if ($trigger->shouldFire($emailData)) {
        echo "Trigger matched! Executing...<br>";
        $result = $trigger->execute($emailData, $mailer);
        
        if ($result) {
            echo "Auto-response sent successfully!<br>";
            echo "Check inbox of " . $emailData['from'] . " for the email.<br>";
        } else {
            echo "Failed to send auto-response.<br>";
        }
    } else {
        echo "Trigger conditions did not match. No response sent.<br>";
    }
}

// Create a form to manually test the system
function displayTestForm() {
    // Check if form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create email data from form
        $emailData = [
            'from' => $_POST['recipient_email'],
            'to' => $_POST['to_email'],
            'subject' => $_POST['subject'],
            'body' => $_POST['body']
        ];
        
        // Update the testEmailTrigger function to use this data
        // For simplicity, we'll just modify the global variable
        global $formData;
        $formData = $emailData;
        
        // Run the test
        testEmailTrigger();
    } else {
        // Display the form
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Email Trigger Test</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .form-container { max-width: 600px; margin: 0 auto; }
                .form-group { margin-bottom: 15px; }
                label { display: block; margin-bottom: 5px; font-weight: bold; }
                input[type="text"], input[type="email"], textarea {
                    width: 100%;
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                textarea { height: 100px; }
                button { 
                    background-color: #4CAF50;
                    color: white;
                    padding: 10px 15px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                }
                button:hover { background-color: #45a049; }
                .info { 
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-radius: 4px;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="form-container">
                <h1>Email Trigger Test</h1>
                
                <div class="info">
                    <p>This form allows you to test the email trigger system. Fill in the details below and click "Test Trigger" to see if your email meets the trigger conditions.</p>
                    <p><strong>Current trigger conditions:</strong> Subject contains "support" AND To equals "support@example.com"</p>
                </div>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="recipient_email">Recipient Email (where to send the auto-response):</label>
                        <input type="email" id="recipient_email" name="recipient_email" value="saranraj.s@vdartinc.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="to_email">To Email (for trigger condition):</label>
                        <input type="email" id="to_email" name="to_email" value="support@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" id="subject" name="subject" value="Need support with login issue" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="body">Body:</label>
                        <textarea id="body" name="body" required>Hello,

I'm having trouble logging into my account. Can you help?

Thanks,
John</textarea>
                    </div>
                    
                    <button type="submit">Test Trigger</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit; // Stop execution after displaying form
    }
}

// Global variable for form data
$formData = null;

// Display form if accessed directly
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    // First display the form to get input
    displayTestForm();
    
    // If form was submitted, testEmailTrigger() will be called
    // from within displayTestForm()
}