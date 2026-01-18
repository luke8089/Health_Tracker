# Quick Start Guide

Get your Health Tracker application up and running in minutes!

## Prerequisites Checklist

- [ ] PHP 7.4 or higher installed
- [ ] MySQL 5.7 or higher installed
- [ ] Web server (Apache/Nginx) or XAMPP/WAMP
- [ ] Git installed

## Installation Steps

### 1. Clone the Repository

```bash
git clone https://github.com/luke8089/Health_Tracker.git
cd Health_Tracker
```

### 2. Create Configuration Files

```bash
# Copy example configurations
cp config.example.php config.php
cp mail/config.example.php mail/config.php
```

### 3. Edit config.php

Open `config.php` and update:

```php
// Database settings
const DB_HOST = 'localhost';
const DB_NAME = 'health_tracker';
const DB_USER = 'root';
const DB_PASS = 'YOUR_DB_PASSWORD';

// Application URL
const APP_URL = 'http://localhost/health-tracker';
```

### 4. Create Database

```sql
CREATE DATABASE health_tracker;
```

### 5. Import Database Schema

**Option A: Using MySQL Command Line**
```bash
mysql -u root -p health_tracker < migrations/schema.sql
```

**Option B: Using phpMyAdmin**
1. Open phpMyAdmin
2. Select `health_tracker` database
3. Click "Import"
4. Choose `migrations/schema.sql`
5. Click "Go"

### 6. Set Up Directories

```bash
# Create required directories
mkdir -p public/uploads
mkdir -p logs

# Set permissions (Linux/Mac)
chmod -R 755 public/uploads
chmod -R 755 logs
```

### 7. Access the Application

Open your browser and navigate to:
```
http://localhost/health-tracker
```

## Default Admin Credentials

After importing the database, you can login as admin:

- **Email:** admin@healthtracker.com
- **Password:** admin123

⚠️ **IMPORTANT:** Change this password immediately after first login!

## Optional: Email Configuration

### For Gmail Users:

1. Enable 2-Factor Authentication
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Update `mail/config.php`:

```php
'smtp' => [
    'host' => 'smtp.gmail.com',
    'username' => 'your-email@gmail.com',
    'password' => 'your-16-char-app-password',
    'port' => 587,
],
```

## Optional: AI Chatbot Setup

1. Create account at https://huggingface.co/
2. Get API token: https://huggingface.co/settings/tokens
3. Add to `config.php`:

```php
const HUGGINGFACE_API_KEY = 'your_api_key_here';
```

**Note:** Chatbot works without API key using rule-based responses!

## Troubleshooting

### Database Connection Error
- Check database credentials in `config.php`
- Ensure MySQL service is running
- Verify database exists

### Email Not Sending
- Check SMTP credentials in `mail/config.php`
- For Gmail, ensure you're using App Password
- Check PHP `mail()` function is enabled

### 404 Errors
- Check `.htaccess` file exists
- Verify Apache `mod_rewrite` is enabled
- Check file permissions

### Upload Issues
- Ensure `public/uploads` directory exists
- Check directory permissions (755)
- Verify PHP `upload_max_filesize` setting

## Next Steps

1. ✅ Login as admin
2. ✅ Change admin password
3. ✅ Create a test patient account
4. ✅ Complete a health assessment
5. ✅ Register as a doctor (for testing)
6. ✅ Explore the features!

## Getting Help

- 📖 Read the full [README.md](README.md)
- 🔒 Check [SECURITY.md](SECURITY.md) for security best practices
- 🐛 Report issues: https://github.com/luke8089/Health_Tracker/issues
- 📧 Email: lukeedwin81@gmail.com

## Development Mode

The application runs in DEBUG mode by default. For production:

1. Edit `config.php`:
```php
const DEBUG_MODE = false;
```

2. Set proper error reporting in production
3. Enable HTTPS
4. Implement proper security measures

---

**Estimated Setup Time:** 10-15 minutes

**Need help?** Open an issue on GitHub or contact the developer.
