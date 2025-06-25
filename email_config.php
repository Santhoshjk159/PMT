<?php
/**
 * Email Configuration for PMT System
 * 
 * Configure your email settings here
 */

// Admin Email Configuration
define('ADMIN_EMAIL', 'santhosh.jk@vdartinc.com'); // Change this to the actual admin email address
define('ADMIN_NAME', 'PMT Administrator');

// System Email Configuration (matching old project settings)
define('SYSTEM_FROM_EMAIL', 'krishna.r@vdartinc.com'); // From email (like old project)
define('SYSTEM_FROM_NAME', 'HR Department - PMT System'); // From name (adapted from old project)

// SMTP Configuration - Updated with working Gmail settings from old project
define('SMTP_HOST', 'smtp.gmail.com'); // Gmail SMTP (working from old project)
define('SMTP_PORT', 587); // Gmail TLS port
define('SMTP_AUTH', true); // Authentication required
define('SMTP_USERNAME', 'saranraj.s@vdartinc.com'); // Your working Gmail account
define('SMTP_PASSWORD', 'xlun epju odmx ohdj'); // Your working app password
define('SMTP_SECURE', 'tls'); // TLS encryption

// Quick Gmail Setup (Alternative for testing)
// Uncomment these lines and comment out the Office 365 lines above if you prefer Gmail:
/*
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'your-gmail@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'your-app-password'); // Gmail App Password (not regular password)
define('SMTP_SECURE', 'tls');
*/

// Email Features
define('ENABLE_ADMIN_NOTIFICATIONS', true); // Set to false to disable admin email notifications
define('ENABLE_EMAIL_LOGGING', true); // Set to false to disable email logging

/**
 * Alternative configurations for different hosting environments
 * 
 * For GoDaddy Hosting:
 * SMTP_HOST: relay-hosting.secureserver.net
 * SMTP_PORT: 25
 * SMTP_AUTH: false
 * 
 * For Gmail SMTP:
 * SMTP_HOST: smtp.gmail.com
 * SMTP_PORT: 587
 * SMTP_AUTH: true
 * SMTP_SECURE: tls
 * SMTP_USERNAME: your-gmail@gmail.com
 * SMTP_PASSWORD: your-app-password
 * 
 * For Office 365:
 * SMTP_HOST: smtp.office365.com
 * SMTP_PORT: 587
 * SMTP_AUTH: true
 * SMTP_SECURE: tls
 * SMTP_USERNAME: your-email@company.com
 * SMTP_PASSWORD: your-password
 */
?>
