# Smart Parking System

A comprehensive parking management system that allows users to book parking spots, make payments, and manage their bookings. The system also includes an admin panel for managing users, parking spots, bookings, and payments.

## Features

### User Features
- User registration and authentication
- Dashboard with parking spot availability
- Booking parking spots with vehicle details
- Payment processing for bookings
- Viewing and managing active bookings
- Booking history
- Profile settings and password management
- Receipt generation for completed bookings

### Admin Features
- Admin dashboard with statistics
- User management
- Parking spot management
- Booking management
- Payment tracking
- Reports and analytics

## Technologies Used

- PHP 7.4+
- MySQL 5.7+
- HTML5, CSS3, JavaScript
- Bootstrap 5
- Font Awesome 6
- AJAX for asynchronous operations

## Installation

1. Clone the repository to your local machine or server
2. Create a MySQL database named `smart_parking`
3. Import the database schema from `database/schema.sql`
4. Configure the database connection in `database/db.php` if needed
5. Access the application through your web server

## Default Credentials

### Admin User
- Email: admin@gmail.com
- Password: password

### Regular User
- Email: user@gmail.com
- Password: password

## Project Structure

```
smart_parking/
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

## Database Schema

The database consists of the following tables:

- `users`: Stores user information
- `parking_spots`: Stores parking spot details
- `bookings`: Stores booking information
- `payments`: Stores payment details
- `auth_logs`: Tracks authentication attempts

## License

This project is licensed under the MIT License.

## Credits

- Bootstrap: https://getbootstrap.com/
- Font Awesome: https://fontawesome.com/
- UI Avatars: https://ui-avatars.com/

## Contact

For any inquiries or support, please contact support@smartparking.com
