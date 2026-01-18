# Security Configuration Guide

## Important Security Steps

### 1. Configuration Files

This repository does **NOT** include sensitive configuration files. You must create them manually:

#### Required Files to Create:

1. **config.php** (root directory)
   - Copy from `config.example.php`
   - Update database credentials
   - Add your Hugging Face API key (optional)

2. **mail/config.php** (mail directory)
   - Copy from `mail/config.example.php`
   - Update SMTP credentials
   - Use App Password for Gmail (not regular password)

### 2. API Keys and Credentials

#### Database Configuration
```php
const DB_HOST = 'localhost';
const DB_NAME = 'health_tracker';
const DB_USER = 'your_username';
const DB_PASS = 'your_password';  // NEVER commit this!
```

#### Email Configuration
- Use App-specific passwords (especially for Gmail)
- Never commit real email credentials
- Store sensitive data in environment variables when possible

#### AI Chatbot API Key
- Get free API key from: https://huggingface.co/settings/tokens
- The chatbot works without an API key (rule-based mode)
- API key improves responses but is optional

### 3. Files Excluded from Git

The following files are automatically excluded via `.gitignore`:

- `config.php` - Main configuration
- `mail/config.php` - Email configuration
- `logs/` - Application logs
- `public/uploads/` - User uploaded files
- `PHPMailer/` - Should be installed via Composer

### 4. Best Practices

1. **Never commit sensitive data**
   - API keys
   - Passwords
   - Database credentials
   - Email passwords

2. **Use environment variables**
   - Consider using `.env` files for sensitive data
   - Load them at runtime, never commit them

3. **Change default credentials**
   - Change admin password immediately after setup
   - Use strong passwords for database and email

4. **Keep software updated**
   - Regularly update dependencies
   - Monitor security advisories

5. **Secure file permissions**
   ```bash
   chmod 755 public/uploads
   chmod 755 logs
   chmod 600 config.php
   chmod 600 mail/config.php
   ```

### 5. Gmail SMTP Setup

To use Gmail for sending emails:

1. Enable 2-Factor Authentication on your Google account
2. Generate an App Password:
   - Go to: https://myaccount.google.com/apppasswords
   - Select "Mail" and your device
   - Copy the 16-character password
3. Use this App Password in `mail/config.php`, NOT your regular password

### 6. Production Deployment

When deploying to production:

1. Set `DEBUG_MODE = false` in config.php
2. Use environment-specific configurations
3. Enable HTTPS/SSL
4. Implement rate limiting
5. Regular security audits
6. Backup database regularly

### 7. What's Safe to Share

✅ **Safe to commit:**
- Example configuration files (*.example.php)
- Database schema (without data)
- Application code
- Documentation
- Public assets

❌ **NEVER commit:**
- Actual configuration files with credentials
- API keys
- Passwords
- User data
- Session data
- Log files with sensitive information

## Need Help?

If you accidentally committed sensitive data:

1. Remove the sensitive file:
   ```bash
   git rm --cached config.php
   git commit -m "Remove sensitive config file"
   git push
   ```

2. Change all compromised credentials immediately

3. Consider using tools like `git-filter-branch` or BFG Repo-Cleaner to remove sensitive data from history

## Reporting Security Issues

If you discover a security vulnerability, please email: lukeedwin81@gmail.com

Do NOT create a public issue for security vulnerabilities.
