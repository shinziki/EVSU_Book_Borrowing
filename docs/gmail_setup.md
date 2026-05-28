# Setting Up Gmail for EVSU Book Borrowing System Notifications

Follow these steps to configure your Gmail account to send emails from the library application:

## Step 1: Create an App Password (Recommended)

If you have 2-Factor Authentication (2FA) enabled on your Gmail account:

1. Go to your [Google Account](https://myaccount.google.com/)
2. Select **Security**
3. Under "Signing in to Google," select **2-Step Verification**
4. At the bottom of the page, select **App passwords**
5. Enter a name to help you remember what this app password is for (e.g., "EVSU Book Borrowing System")
6. Click **Create**
7. Google will display a 16-character app password
8. **Copy this password** - you will need it for the next step

## Step 2: Configure the mail_config.php file

1. Open `config/mail_config.php` in an editor
2. Update the following settings:

```php
$mail_config = [
    'use_smtp' => true,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_auth' => true,
    'smtp_username' => 'your_gmail_address@gmail.com',  // CHANGE THIS
    'smtp_password' => 'your_16_char_app_password',     // PASTE APP PASSWORD HERE
    'from_email' => 'your_gmail_address@gmail.com',     // CHANGE THIS
    'from_name' => 'EVSU Book Borrowing System'
];
```

## Alternative: Allow Less Secure Apps (Not Recommended)

If you don't have 2FA enabled, you can enable "Less secure app access" instead:

1. Go to your [Google Account](https://myaccount.google.com/)
2. Select **Security**
3. Under "Less secure app access," turn on **Allow less secure apps**
4. Use your regular Gmail password in the configuration

**Note:** This option is less secure and Google may disable it in the future.

## Testing Your Configuration

After setting up:

1. Go to the Notifications page in the library system
2. Click "Send Test Email"
3. Enter a test email address
4. Check if the email is received

If you still have issues, check the email logs on the Notifications page for error details.

## Troubleshooting

Common issues:

1. **"Authentication unsuccessful"**: Verify your username and app password are correct
2. **"Could not authenticate"**: Make sure you're using an App Password if 2FA is enabled
3. **"SSL/TLS error"**: Ensure your server supports TLS connections
4. **"Relay denied"**: Gmail only allows sending from the account you're authenticated with

If you continue having issues, you can view saved emails in the `emails` directory as the application will automatically save emails that couldn't be sent. 