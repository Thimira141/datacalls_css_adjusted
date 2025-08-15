# DataCaller Project

## Overview
DataCaller is a PHP-based web application for managing call data, user accounts, IVR profiles, contacts, and billing information. It integrates with MagnusBilling for telephony and billing operations and uses modern PHP practices with Eloquent ORM and secure authentication.

## Features
- User authentication and account management
- Password change with secure validation
- Contacts and institutions management
- IVR profile creation and management
- Call records, DTMF, and CDR reports
- Real-time balance display via MagnusBilling API
- Responsive dashboard and sidebar navigation
- CSRF protection for all forms

## Technologies Used
- PHP 7+
- Eloquent ORM (Illuminate Database) `https://github.com/illuminate/database`
- MagnusBilling API integration
- Bootstrap 4 & Font Awesome 6
- jQuery
- Rakit Validation - PHP Standalone Validation Library `https://github.com/rakit/validation`

## Folder Structure
```
public/         # Public web files (entry points, assets)
controller/     # API controllers and business logic
inc/            # Classes and helpers (Auth, Validator, CSRFToken)
vendor/         # Composer dependencies
old/            # Legacy code and assets
assets/         # Static assets (CSS, JS, images)
docs/           # Documentation and project notes
```

## Setup Instructions
1. **Clone the repository**
2. **Install dependencies**
   - Run `composer install` in the project root
3. **Configure environment**
   - Copy `dist.env` to `.env` and update database and MagnusBilling credentials
4. **Set up the database**
   - Import `myapp.sql` from `old/assets/` into your MySQL server
5. **Configure web server**
   - Point your web server to the `public/` directory
6. **Access the app**
   - Visit `http://localhost/projects/datacalls_css_adjusted/public/`

## Security Notes
- All forms use CSRF protection
- Passwords are hashed using PHP's `password_hash`
- User input is validated server-side and client-side

## API Integration
- MagnusBilling API is used for telephony and billing operations
- Credentials and endpoints are configured in `config.php` and `.env`

## Contribution
Pull requests and issues are welcome. Please follow PSR standards and document your code.

## License
This project is licensed under the MIT License.
