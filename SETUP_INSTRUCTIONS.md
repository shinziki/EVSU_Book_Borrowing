# EVSU Book Borrowing System Setup Guide

This guide will help you set up the EVSU Book Borrowing System on your local environment.

## Prerequisites

Before you begin, make sure you have the following installed:

1. **XAMPP** (or similar local server stack with PHP and MySQL)
2. **Composer** (for PHP dependencies)
3. **Web browser** (Chrome, Firefox, Edge, etc.)

## Installation Steps

### Step 1: Set Up the Web Server

1. Install [XAMPP](https://www.apachefriends.org/download.html) or another web server stack if you don't have one already
2. Start the Apache and MySQL services from the XAMPP Control Panel

### Step 2: Set Up the Database

1. Open your web browser and navigate to `http://localhost/phpmyadmin/`
2. Create a new database called `coffee_prince_library`
3. Import the database structure:
   - Click on the newly created database
   - Click on the "Import" tab
   - Browse and select the file `config/coffee_prince_library.sql` from your project directory
   - Click "Go" to import the database structure and initial data

### Step 3: Configure Database Connection

1. Open the file `config/db_connect.php` in a text editor
2. Update the database connection parameters if needed:
   ```php
   $host = 'localhost';
   $dbname = 'coffee_prince_library';
   $username = 'root'; // Change if you have a different username
   $password = ''; // Add your password if you set one for MySQL
   ```

### Step 4: Configure Email Notifications

1. Open the file `config/mail_config.php` in a text editor
2. Update the SMTP settings with your email provider information:
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

3. For Gmail users:
   - You'll need to create an App Password if you have 2-Factor Authentication enabled
   - Go to your Google Account → Security → App Passwords
   - Use this App Password in the `smtp_password` field

### Step 5: Install PHP Dependencies

1. Open a terminal or command prompt
2. Navigate to your project directory
3. Run the following command to install dependencies:
   ```
   composer install
   ```

### Step 6: Set Folder Permissions

Ensure the following directories are writable by the web server:
- `uploads/`
- `uploads/books/`
- `uploads/members/`
- `uploads/profile_images/`
- `logs/`
- `emails/`

On Windows with XAMPP, these folders should be writable by default. On Linux/Mac, you may need to run:
```
chmod -R 755 uploads/ logs/ emails/
```

### Step 7: Access the System

1. Open your web browser and navigate to `http://localhost/Coffee_Prince_Library-finals/`
2. Log in with the default administrator credentials:
   - Username: `admin`
   - Password: `admin123`
3. After logging in, it's recommended to change the default password in the Settings page

## Testing the Installation

1. Test the email functionality:
   - Go to the Notifications page
   - Click "Send Test Email"
   - Check if the email was sent or if it was saved in the `emails/` directory

2. Add a test book:
   - Go to the Books page
   - Click "Add Book"
   - Fill in the required information and submit

3. Add a test member:
   - Go to the Members page
   - Click "Add Member"
   - Fill in the required information and submit

4. Test the borrow functionality:
   - Go to the Borrow page
   - Scan or enter the book and member barcodes
   - Complete the borrowing process

## Troubleshooting

### Database Connection Issues
- Verify that MySQL is running
- Check the credentials in `config/db_connect.php`
- Ensure the database `coffee_prince_library` exists

### Email Sending Issues
- Check your SMTP settings in `config/mail_config.php`
- For Gmail, ensure you've created an App Password or enabled Less Secure Apps
- Check the email logs on the Notifications page
- Look for saved emails in the `emails/` directory

### File Upload Issues
- Ensure the `uploads/` directory and its subdirectories are writable
- Check PHP configuration for file upload limits in `php.ini`

## Security Recommendations

After installation, take these additional security steps:

1. Change the default admin password immediately
2. Update your web server configuration to prevent direct access to sensitive directories
3. Regularly back up your database
4. Keep PHP and all dependencies up-to-date

## For Development Purposes

For local development or testing:
- Email notifications will be saved as text files in the `emails/` directory if sending fails
- You can view these saved emails from the Notifications page in the system
- This allows you to test notification functionality even without a working mail server

## System Creators

The EVSU Book Borrowing System was developed by:

- Miguelito Bacho
- Nicole Cretecio
- Raziel Insigne
- Ariel Cupta
- Jhonel Andamon
- Gabriel Valmera
- Regine Pales 