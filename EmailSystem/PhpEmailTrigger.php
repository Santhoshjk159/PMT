<?php
// Contact Form Handler - For GoDaddy Hosting

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Form submission details with sanitization
    $name = sanitizeInput($_POST['name'] ?? 'Unknown');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? 'New Website Inquiry');
    $message = sanitizeInput($_POST['message'] ?? 'No message provided');
    
    // Recipient details
    $to = "saranraj.s@vdartinc.com"; // Change this to your email
    
    // Additional headers for better deliverability
    $headers = "From: webform@" . $_SERVER['HTTP_HOST'] . "\r\n"; // Use your domain
    $headers .= "Reply-To: $email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Format email body in HTML
    $email_body = "
    <html>
    <head>
        <title>$subject</title>
    </head>
    <body>
        <h2>New Contact Form Submission</h2>
        <p><strong>Name:</strong> $name</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Subject:</strong> $subject</p>
        <p><strong>Message:</strong></p>
        <p>$message</p>
        <hr>
        <p><small>This email was sent from your website contact form.</small></p>
    </body>
    </html>
    ";
    
    // Send email using GoDaddy's default mail server
    $success = mail($to, $subject, $email_body, $headers);
    
    // Handle the result
    if ($success) {
        echo "Thank you! Your message has been sent.";
    } else {
        echo "Sorry, there was an error sending your message. Please try again later.";
    }
}
?>

<!-- Sample HTML Form -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        textarea { height: 150px; }
        button { padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <h1>Contact Us</h1>
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="form-group">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="subject">Subject:</label>
            <input type="text" id="subject" name="subject" required>
        </div>
        
        <div class="form-group">
            <label for="message">Message:</label>
            <textarea id="message" name="message" required></textarea>
        </div>
        
        <button type="submit">Send Message</button>
    </form>
</body>
</html>