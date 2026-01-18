# Health Tracker 🏥

A comprehensive health monitoring and management system with AI-powered chatbot assistance, habit tracking, mental and physical health assessments, and doctor-patient connectivity features.

## 📋 Table of Contents

- [Features](#features)
- [Technologies Used](#technologies-used)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [Usage](#usage)
- [User Roles](#user-roles)
- [Project Structure](#project-structure)
- [API Documentation](#api-documentation)
- [Contributing](#contributing)
- [License](#license)

## ✨ Features

### For Users (Patients)
- **Health Assessments**: Comprehensive mental and physical health assessments
- **Habit Tracking**: Track and verify daily health habits with evidence upload
- **Activity Logging**: Monitor physical activities and health metrics
- **AI Chatbot**: Get instant health advice and answers using AI-powered chatbot
- **Doctor Connection**: Connect with verified healthcare professionals
- **Health Dashboard**: Visualize your health data and progress
- **Reward System**: Earn points and achieve tiers (Bronze, Silver, Gold, Platinum)
- **Assessment History**: View and track your assessment results over time
- **Profile Management**: Manage your personal health profile

### For Doctors
- **Patient Monitoring**: Monitor assigned patients' health metrics and habits
- **Habit Verification**: Verify patient-submitted habit evidence
- **Assessment Review**: Review patient assessment results
- **Video Calls**: Conduct virtual consultations with patients
- **Messaging System**: Communicate securely with patients
- **Patient Dashboard**: Comprehensive overview of patient health data
- **Reports Generation**: Generate detailed health reports

### For Administrators
- **User Management**: Manage users, doctors, and patients
- **Doctor Verification**: Approve and manage doctor registrations
- **System Settings**: Configure application settings
- **Habit Management**: Manage habit categories and types
- **Assessment Management**: Configure assessment questions and scoring
- **Reports & Analytics**: View system-wide health statistics
- **Content Management**: Manage habit requests and edit requests

## 🛠 Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Email**: PHPMailer for email notifications
- **AI Integration**: Hugging Face API (Microsoft DialoGPT)
- **Charts**: Chart.js for data visualization
- **Icons**: Font Awesome, Bootstrap Icons
- **Session Management**: PHP Sessions
- **File Uploads**: Native PHP file handling

## 📦 Prerequisites

Before you begin, ensure you have the following installed:

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server (or XAMPP/WAMP for local development)
- Composer (optional, for dependency management)
- Modern web browser (Chrome, Firefox, Safari, Edge)

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/luke8089/Health_Tracker.git
cd Health_Tracker
```

### 2. Set Up Web Server

#### Using XAMPP (Recommended for Windows)

1. Copy the project folder to `C:\xampp\htdocs\health-tracker`
2. Start Apache and MySQL from XAMPP Control Panel

#### Using LAMP/MAMP (Linux/Mac)

1. Copy the project to your web server directory
2. Ensure Apache and MySQL are running

### 3. Configure the Application

#### Create Configuration Files

```bash
# Copy example config files
cp config.example.php config.php
cp mail/config.example.php mail/config.php
```

#### Edit config.php

```php
// Database Configuration
const DB_HOST = 'localhost';
const DB_NAME = 'health_tracker';
const DB_USER = 'root';
const DB_PASS = 'your_password';

// Application URL
const APP_URL = 'http://localhost/health-tracker';

// Optional: Add Hugging Face API key for AI chatbot
const HUGGINGFACE_API_KEY = 'your_api_key_here';
```

#### Edit mail/config.php

```php
'smtp' => [
    'host' => 'smtp.gmail.com',
    'username' => 'your-email@gmail.com',
    'password' => 'your-app-password',
    'port' => 587,
    'encryption' => 'tls',
    'auth' => true
],
```

### 4. Database Setup

#### Create Database

```sql
CREATE DATABASE health_tracker;
```

#### Import Database Schema

```bash
# Using MySQL command line
mysql -u root -p health_tracker < migrations/schema.sql

# Or use phpMyAdmin to import the schema.sql file
```

### 5. Set Permissions

```bash
# Linux/Mac
chmod -R 755 public/uploads
chmod -R 755 logs

# Ensure these directories are writable
```

### 6. Create Required Directories

```bash
mkdir -p public/uploads
mkdir -p logs
```

## ⚙️ Configuration

### Email Configuration (Optional)

For Gmail SMTP:
1. Enable 2-factor authentication on your Google account
2. Generate an App Password: [Google App Passwords](https://myaccount.google.com/apppasswords)
3. Use the app password in `mail/config.php`

### AI Chatbot Configuration (Optional)

1. Create a free account at [Hugging Face](https://huggingface.co/)
2. Generate an API token: [Hugging Face Tokens](https://huggingface.co/settings/tokens)
3. Add the token to `config.php` as `HUGGINGFACE_API_KEY`
4. The chatbot will use rule-based responses if no API key is provided

## 💾 Database Setup

The database schema includes the following main tables:

- `users` - User accounts (patients, doctors, admins)
- `habits` - Health habits tracking
- `assessments` - Mental and physical health assessments
- `activities` - Physical activity logging
- `messages` - User-doctor messaging
- `appointments` - Video call scheduling
- `health_scores` - Health scoring system
- `rewards` - User reward points and tiers

Default admin credentials (created after running schema.sql):
- **Email**: admin@healthtracker.com
- **Password**: admin123

⚠️ **Important**: Change the default admin password immediately after first login!

## 🎯 Usage

### Accessing the Application

1. Open your web browser
2. Navigate to `http://localhost/health-tracker` (or your configured URL)
3. You'll be redirected to the landing page

### Getting Started

#### As a Patient:
1. Click "Get Started" on the landing page
2. Register with your email and personal details
3. Complete your first health assessment
4. Start tracking habits and activities
5. Connect with a doctor if needed

#### As a Doctor:
1. Register as a healthcare professional
2. Wait for admin approval
3. Access your dashboard to view assigned patients
4. Monitor patient health metrics
5. Verify habit submissions and conduct consultations

#### As an Administrator:
1. Login with admin credentials
2. Approve doctor registrations
3. Manage users and system settings
4. View system reports and analytics

## 👥 User Roles

### Patient
- Access to personal health dashboard
- Health assessments and habit tracking
- Doctor connection and messaging
- AI chatbot assistance

### Doctor
- Patient monitoring dashboard
- Habit verification system
- Video call capabilities
- Assessment review tools

### Administrator
- Full system access
- User and doctor management
- System configuration
- Reports and analytics

## 📁 Project Structure

```
health-tracker/
├── admin/              # Admin panel
│   ├── dashboard.php   # Admin dashboard
│   ├── users.php       # User management
│   ├── doctors.php     # Doctor management
│   └── ...
├── doctor/             # Doctor panel
│   ├── dashboard.php   # Doctor dashboard
│   ├── monitor_habits.php
│   └── ...
├── public/             # User panel
│   ├── dashboard.php   # User dashboard
│   ├── habits.php      # Habit tracking
│   ├── assessment.php  # Health assessments
│   └── api/            # API endpoints
│       └── chat.php    # AI chatbot API
├── src/                # Application source
│   ├── models/         # Data models
│   ├── controllers/    # Business logic
│   ├── helpers/        # Helper functions
│   └── views/          # View templates
├── mail/               # Email configuration and templates
│   ├── config.php      # Email settings (not in git)
│   ├── Mailer.php      # Email handler
│   └── templates/      # Email templates
├── migrations/         # Database migrations
│   └── schema.sql      # Database schema
├── landing_page/       # Landing page
├── logs/               # Application logs (not in git)
├── config.php          # Main configuration (not in git)
├── config.example.php  # Example configuration
└── index.php           # Application entry point
```

## 🔌 API Documentation

### Chat API

**Endpoint**: `/public/api/chat.php`

**Method**: POST

**Request Body**:
```json
{
  "message": "How can I improve my mental health?"
}
```

**Response**:
```json
{
  "success": true,
  "response": "Here are some ways to improve mental health...",
  "source": "ai" // or "rules"
}
```

### Authentication API

**Endpoint**: `/public/api.php`

**Login**:
```json
POST /public/api.php?action=login
{
  "email": "user@example.com",
  "password": "password"
}
```

## 🔒 Security Features

- Password hashing with PHP's `password_hash()`
- CSRF token protection
- Session management
- SQL injection prevention with prepared statements
- XSS protection
- File upload validation
- Role-based access control

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 👨‍💻 Author

**Luke Edwin**
- GitHub: [@luke8089](https://github.com/luke8089)

## 🙏 Acknowledgments

- [Bootstrap](https://getbootstrap.com/) - Frontend framework
- [Chart.js](https://www.chartjs.org/) - Data visualization
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) - Email functionality
- [Hugging Face](https://huggingface.co/) - AI/ML models
- [Font Awesome](https://fontawesome.com/) - Icons

## 📞 Support

For support, email lukeedwin81@gmail.com or open an issue in the GitHub repository.

## 🗺️ Roadmap

- [ ] Mobile application (iOS/Android)
- [ ] Advanced analytics dashboard
- [ ] Integration with wearable devices
- [ ] Telemedicine features expansion
- [ ] Multi-language support
- [ ] Export health reports to PDF
- [ ] Integration with more AI models
- [ ] Real-time notifications

## ⚠️ Disclaimer

This application is for educational and informational purposes only. It is not a substitute for professional medical advice, diagnosis, or treatment. Always seek the advice of your physician or other qualified health provider with any questions you may have regarding a medical condition.

---

**Made with ❤️ for better health monitoring**
