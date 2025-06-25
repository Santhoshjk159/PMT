<?php
// Manual includes for PHPMailer
// Adjust the path to where you've extracted the PHPMailer files
require '../PHPMailer-6.9.3/src/Exception.php';
require '../PHPMailer-6.9.3/src/PHPMailer.php';
require '../PHPMailer-6.9.3/src/SMTP.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Configuration
$emailTo = "saranraj.s@vdartinc.com"; // Change this to your email address
$emailSubject = "New Contact Form Submission";

// Initialize response messages
$statusMsg = "";
$errorFields = [];
$formSubmitted = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $formSubmitted = true;
    
    // Collect form data
    $name = strip_tags(trim($_POST["name"] ?? ""));
    $email = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
    $subject = strip_tags(trim($_POST["subject"] ?? ""));
    $message = strip_tags(trim($_POST["message"] ?? ""));
    
    // Validate input
    if (empty($name)) {
        $errorFields[] = "name";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorFields[] = "email";
    }
    
    if (empty($subject)) {
        $errorFields[] = "subject";
    }
    
    if (empty($message)) {
        $errorFields[] = "message";
    }
    
    // If all inputs are valid, send email
    if (empty($errorFields)) {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);

        try {
            // Server settings for secureserver.net (GoDaddy)
            $mail->isSMTP();                                      // Send using SMTP
            $mail->Host = 'smtp.secureserver.net';
            $mail->SMTPAuth = true;
            $mail->Username = 'sqzportal@vdpl.co'; // Your GoDaddy email 
            $mail->Password = '#VDart@123##'; // Your GoDaddy email password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;                             // TCP port for TLS
            
            // Debug mode (remove this in production)
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;             // Enable verbose debug output

            // Recipients
            $mail->setFrom('saranraj50540@gmail.com', $name);   // Set from your GoDaddy email
            $mail->addAddress($emailTo);                          // Add a recipient
            $mail->addReplyTo($email, $name);                     // Set reply-to as the submitter's email

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $emailSubject;
            
            // Prepare email content (HTML and plain text versions)
            $htmlContent = "
            <h3>New Contact Form Submission</h3>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Subject:</strong> $subject</p>
            <p><strong>Message:</strong></p>
            <p>" . nl2br($message) . "</p>
            ";
            
            $plainContent = "Name: $name\n";
            $plainContent .= "Email: $email\n\n";
            $plainContent .= "Subject: $subject\n\n";
            $plainContent .= "Message:\n$message\n";
            
            $mail->Body    = $htmlContent;
            $mail->AltBody = $plainContent;

            // Send email
            $mail->send();
            $statusMsg = "Thank you! Your message has been sent successfully.";
        } catch (Exception $e) {
            $statusMsg = "Oops! Something went wrong. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $statusMsg = "Please fill in all required fields correctly.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        textarea {
            height: 150px;
        }
        
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        .error {
            border-color: #ff0000;
        }
        
        .status-message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .failure {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <h1>Contact Us</h1>
    
    <?php if ($formSubmitted && !empty($statusMsg)): ?>
        <div class="status-message <?php echo empty($errorFields) ? 'success' : 'failure'; ?>">
            <?php echo $statusMsg; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" 
                value="<?php echo htmlspecialchars($name ?? ''); ?>"
                class="<?php echo in_array('name', $errorFields) ? 'error' : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" 
                value="<?php echo htmlspecialchars($email ?? ''); ?>"
                class="<?php echo in_array('email', $errorFields) ? 'error' : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="subject">Subject *</label>
            <input type="text" id="subject" name="subject" 
                value="<?php echo htmlspecialchars($subject ?? ''); ?>"
                class="<?php echo in_array('subject', $errorFields) ? 'error' : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="message">Message *</label>
            <textarea id="message" name="message" 
                class="<?php echo in_array('message', $errorFields) ? 'error' : ''; ?>"
            ><?php echo htmlspecialchars($message ?? ''); ?></textarea>
        </div>
        
        <button type="submit">Send Message</button>
    </form>
</body>
</html>