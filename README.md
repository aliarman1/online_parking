# Online Parking System

A comprehensive parking management system that allows users to book parking spots, make payments, and manage their bookings. The system also includes an admin panel for managing users, parking spots, bookings, and payments.

## Project Overview

The Online Parking System is designed to streamline the process of finding and booking parking spots. Users can search for available parking spots, book them for specific time periods, make payments, and manage their bookings. The system provides a user-friendly interface for both regular users and administrators.

### Key Features

#### User Features
- User registration and authentication
- Secure login with remember me functionality
- Password reset capability
- Dashboard with parking spot availability
- Booking parking spots with vehicle details
- Payment processing for bookings
- Viewing and managing active bookings
- Booking history with usage statistics
- Profile settings and password management
- Receipt generation for completed bookings

#### Admin Features
- Admin dashboard with statistics
- Complete user management
- Parking spot management
- Booking management
- Payment tracking
- Comprehensive reports and analytics

## Technologies Used

- PHP 7.4+
- MySQL 5.7+
- HTML5, CSS3, JavaScript
- Bootstrap 5
- Font Awesome 6
- AJAX for asynchronous operations

## Setup Instructions

### Prerequisites
- XAMPP, WAMP, MAMP, or any PHP development environment
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser (Chrome, Firefox, Safari, etc.)

### Installation Steps

1. **Clone or Download the Repository**
   - Place the project files in your web server's document root (e.g., `htdocs` folder for XAMPP)

2. **Database Setup**
   - Start your MySQL server
   - There are two ways to set up the database:
     
     **Option 1: Using the Setup Script**
     - Navigate to `http://localhost/online_project/` in your browser
     - Follow the on-screen instructions to create the database and tables
     
     **Option 2: Manual Setup**
     - Create a new MySQL database named `online_parking`
     - Import the database schema from `database/schema.sql`
     - Configure the database connection in `database/db.php` if needed

3. **Access the Application**
   - Open your web browser and navigate to `http://localhost/online_project/`
   - You will be redirected to the login page

## Default Login Credentials

### Admin User
- **Email:** admin@gmail.com
- **Password:** password

### Regular User
- **Email:** user@gmail.com
- **Password:** password

## Project Structure

```
online_parking/
├── admin/                  # Admin panel files
│   ├── dashboard.php       # Admin dashboard
│   ├── users.php           # User management
│   ├── parking-spots.php   # Parking spot management
│   ├── bookings.php        # Booking management
│   ├── payments.php        # Payment management
│   ├── reports.php         # Reports and analytics
│   └── sidebar.php         # Admin sidebar component
├── assets/                 # Static assets
│   ├── css/                # CSS files
│   │   ├── style.css       # Main stylesheet
│   │   └── dashboard.css   # Dashboard specific styles
│   └── js/                 # JavaScript files
├── database/               # Database files
│   ├── db.php              # Database connection
│   └── schema.sql          # Database schema
├── image/                  # Image assets
├── includes/               # Reusable components
│   ├── security.php        # Security functions
│   └── sidebar.php         # User sidebar component
├── config.php              # Configuration and utility functions
├── dashboard.php           # User dashboard
├── history.php             # Booking history
├── index.php               # Entry point
├── login.php               # Login page
├── logout.php              # Logout functionality
├── my-bookings.php         # Active bookings management
├── register.php            # User registration
├── settings.php            # User settings
├── setup.php               # Database setup script
└── view-receipt.php        # Receipt generation
```

## User Guide

### For Regular Users

1. **Registration and Login**
   - Register with your email and password
   - Login using your credentials
   - Use the "Remember Me" option for convenience
   - Reset your password if forgotten

2. **Booking a Parking Spot**
   - Navigate to the dashboard
   - Search for available parking spots
   - Select a spot and specify booking details
   - Enter vehicle information
   - Confirm and make payment

3. **Managing Bookings**
   - View active bookings in "My Bookings"
   - Cancel bookings if needed
   - View booking history and receipts

### For Administrators

1. **User Management**
   - View all users
   - Add, edit, or delete users
   - Change user roles

2. **Parking Spot Management**
   - Add new parking spots
   - Edit spot details
   - Mark spots as available/unavailable

3. **Reports**
   - Generate booking reports
   - View payment statistics
   - Monitor system usage

## Troubleshooting

- **Database Connection Issues**
  - Ensure MySQL server is running
  - Check database credentials in `database/db.php`
  - Run the setup script to initialize the database

- **Login Problems**
  - Clear browser cookies and cache
  - Reset your password if forgotten
  - Contact an administrator if account is locked

## Security Features

- Password hashing using PHP's password_hash()
- CSRF protection for forms
- Input sanitization
- Session security
- Login attempt monitoring

## License

This project is licensed under the MIT License.

## Contact

For any inquiries or support, please contact support@onlineparking.com
