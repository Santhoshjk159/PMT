# Admin Email Notification System

This feature automatically sends email notifications to administrators when users make changes to paperwork records in the PMT system.

## 📋 What's Included

### New Files Created:
1. **`EmailSystem/AdminNotificationMailer.php`** - Main email notification class
2. **`email_config.php`** - Email configuration settings
3. **`EmailSystem/email-test.php`** - Test page to verify email functionality

### Modified Files:
1. **`paperworkedit.php`** - Integrated email notifications into the form processing

## ⚙️ Setup Instructions

### Step 1: Configure Email Settings
Edit `email_config.php` and update the following settings:

```php
// Admin Email Configuration
define('ADMIN_EMAIL', 'your-admin@company.com'); // Change to actual admin email
define('ADMIN_NAME', 'PMT Administrator');

// System Email Configuration  
define('SYSTEM_FROM_EMAIL', 'noreply@company.com'); // Change to your system email
define('SYSTEM_FROM_NAME', 'PMT System');
```

### Step 2: Configure SMTP Settings
Choose the appropriate SMTP configuration for your hosting environment:

#### For Local/Internal Mail Server:
```php
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_AUTH', false);
```

#### For GoDaddy Hosting:
```php
define('SMTP_HOST', 'relay-hosting.secureserver.net');
define('SMTP_PORT', 25);
define('SMTP_AUTH', false);
```

#### For Gmail SMTP:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_AUTH', true);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'your-gmail@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

#### For Office 365:
```php
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_AUTH', true);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'your-email@company.com');
define('SMTP_PASSWORD', 'your-password');
```

### Step 3: Test the Configuration
1. Navigate to `EmailSystem/email-test.php` in your browser
2. Review the configuration settings displayed
3. Click "Send Test Email" to verify the system is working
4. Check the admin email inbox for the test notification

## 📧 How It Works

### Email Triggers
An email notification is sent to the admin when a user:
- Saves changes to any paperwork record
- Only sends if there are actual changes detected
- Includes all field changes in a single email per save operation

### Email Content
Each notification email includes:
- **Candidate Name and Record ID**
- **User who made the changes** (name and email)
- **Timestamp** of when changes were made
- **Detailed change log** showing:
  - Field name that was changed
  - Old value (before change)
  - New value (after change)
- **Professional HTML formatting** with color-coded old/new values

### Tracked Changes
The system tracks changes to all fields including:
- Personal information (name, email, phone, etc.)
- Work authorization details
- Employment information
- Project details
- Rates and financial information
- Status changes
- PLC codes
- All other form fields

## 🔧 Configuration Options

### Enable/Disable Features
```php
define('ENABLE_ADMIN_NOTIFICATIONS', true);  // Enable/disable email notifications
define('ENABLE_EMAIL_LOGGING', true);        // Enable/disable email logging
```

### Email Logging
When enabled, all sent emails are logged to `email_submissions.log` for audit purposes.

## 🛠️ Troubleshooting

### Email Not Sending
1. Check `email_config.php` settings
2. Verify SMTP server details with your hosting provider
3. Check PHP error logs
4. Test with the email test page
5. Ensure firewall/server allows SMTP connections

### PHPMailer Path Errors
If you see errors like "Failed to open stream: No such file or directory" for PHPMailer files:
1. Verify that the `PHPMailer-6.9.3` folder exists in your main directory
2. Check that the folder structure is correct:
   ```
   PMT-1.0.1/
   ├── PHPMailer-6.9.3/
   │   └── src/
   │       ├── Exception.php
   │       ├── PHPMailer.php
   │       └── SMTP.php
   ├── EmailSystem/
   │   ├── AdminNotificationMailer.php
   │   └── email-test.php
   └── email_config.php
   ```
3. Ensure all files have proper read permissions
4. If the PHPMailer folder is in a different location, update the paths in `AdminNotificationMailer.php`

### Email Goes to Spam
1. Configure SPF, DKIM, and DMARC records for your domain
2. Use a domain-based "from" email address
3. Avoid using generic terms in subject lines

### Authentication Issues
1. For Gmail: Use App Passwords instead of regular passwords
2. For Office 365: Ensure "Less secure app access" is enabled or use OAuth
3. Check username/password combinations

## 📝 Email Format Example

**Subject:** PMT Record Updated - John Smith (ID: 12345)

**Content:**
- Summary section with candidate info, modifier, and timestamp
- Detailed changes section showing before/after values
- Professional styling with color-coded changes
- Plain text alternative for email clients that don't support HTML

## 🔐 Security Considerations

1. **Sensitive Data**: Emails contain candidate information - ensure admin email is secure
2. **SMTP Credentials**: Store SMTP passwords securely, consider environment variables for production
3. **Access Control**: Only users with edit permissions can trigger notifications
4. **Logging**: Email logs help track notification history for audit purposes

## 🎯 Benefits

1. **Real-time Monitoring**: Admins are immediately notified of all record changes
2. **Audit Trail**: Complete change history with old/new values
3. **User Accountability**: Clear tracking of who made what changes
4. **Professional Communication**: Well-formatted emails with all relevant details
5. **Configurable**: Easy to enable/disable or modify email settings

## 📧 Support

If you encounter issues:
1. Check the test email functionality first
2. Verify your SMTP settings with your hosting provider
3. Review PHP error logs for detailed error messages
4. Ensure all required files are properly uploaded and accessible

The system is designed to be robust - if email sending fails, it won't break the normal form submission process.
