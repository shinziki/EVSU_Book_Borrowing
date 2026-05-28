# EVSU Book Borrowing System

A barcode-based library management system for EVSU Book Borrowing System.

## Features

- Book management with barcode scanning
- Member management
- Borrowing and returning books
- Transaction tracking
- Email notifications for borrowing, returning, and overdue books
- Dark mode support

## Recent Changes

- Two-factor authentication (2FA) has been removed from the login system for simplicity
- Users can now log in directly with username and password
- Email notifications have been simplified to focus only on book-related notifications
- OTP-related email logs and files have been cleaned up

## Email Configuration

The system is set up to send email notifications for various events such as:
- Book borrowing confirmation
- Book return confirmation
- Due date reminders
- Overdue notifications

### Setting up Email Functionality

1. **Edit mail configuration file:**

Open `config/mail_config.php` and update the SMTP settings:

```php
$mail_config = [
    'use_smtp' => true,
    'smtp_host' => 'smtp.gmail.com',  // Change to your SMTP server
    'smtp_port' => 587,               // Common ports: 25, 465 (SSL), 587 (TLS)
    'smtp_secure' => 'tls',           // Options: '', 'ssl', 'tls'
    'smtp_auth' => true,
    'smtp_username' => 'your_email@gmail.com', // Change to your email
    'smtp_password' => 'your_password',        // Change to your password or app password
    'from_email' => 'noreply@coffeeprincelibrary.com',
    'from_name' => 'EVSU Book Borrowing System'
];
```

2. **Install PHPMailer (Recommended):**

For better email delivery, install PHPMailer:

```bash
composer require phpmailer/phpmailer
```

If you don't have Composer installed, you can download it from [getcomposer.org](https://getcomposer.org/).

### Notes for Gmail Users

If you're using Gmail as your SMTP server:

- You need to enable "Less secure app access" or create an App Password if you have 2-Factor Authentication enabled
- To create an App Password: Go to your Google Account → Security → App Passwords
- Use this App Password in the `smtp_password` field instead of your regular Gmail password

## Testing Email Functionality

You can test your email configuration:

1. Go to the Notifications page
2. Click on "Send Test Email"
3. Enter an email address and click Send
4. Check the email logs in the Notifications page for details

## Troubleshooting Email Issues

If you're experiencing issues with email sending:

1. Check the email logs on the Notifications page
2. Verify your SMTP server settings
3. Make sure your email provider allows sending through SMTP
4. If using Gmail, ensure you've created an App Password
5. Try disabling `use_smtp` in the config to use PHP's built-in mail() function instead

### Local Development Mode

For local development (like XAMPP), the system includes a file-based email backup system:

- When real email sending fails, emails are saved as text files in the `emails/` directory
- You can view these emails from the Notifications page
- This ensures you can test notification functionality even without a working mail server

This feature is especially useful for:
- Development environments without mail server access
- Testing notification content without sending actual emails
- Debugging email-related functionality

## Installation

1. Set up a web server with PHP and MySQL
2. Import the `db_setup.sql` file into your MySQL database
3. Configure database connection in `config/db_connect.php`
4. Set up email configuration as described above
5. Access the application through your web server

## Default Login

- Username: `admin`
- Password: `admin123` 

## Developers

The EVSU Book Borrowing System was developed by:

- Miguelito Bacho
- Sherwin Dave Osma
