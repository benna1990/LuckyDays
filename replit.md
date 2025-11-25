# Lucky Day Lottery Tracker

## Overview
This is a PHP-based web application for tracking Lucky Day lottery numbers and player bets. The application allows users to:
- View winning lottery numbers by date
- Manage player bets and track results
- Automatically scrape winning numbers from the official lottery website
- Admin panel for user management

## Tech Stack
- **Backend**: PHP 8.2 with PostgreSQL
- **Frontend**: Bootstrap 5, vanilla JavaScript
- **Scraper**: Node.js with Puppeteer
- **Database**: PostgreSQL (Replit managed)

## Database Schema
- `admins`: User accounts with roles (admin/user)
- `winning_numbers`: Lottery winning numbers by date
- `players`: Player bets and selected numbers

## Default Login
- Username: `admin`
- Password: `admin`

## Project Structure
```
/
├── index.php           # Login page (root)
├── dashboard.php       # Main dashboard
├── config.php          # PostgreSQL database config
├── functions.php       # Helper functions
├── scrape.js          # Puppeteer scraper for winning numbers
├── php/
│   ├── login.php      # Alternative login
│   ├── admin_beheer.php  # User management
│   └── process_data.php  # Player data processing
└── htdocs_old/        # XAMPP documentation (legacy)
```

## Key Features
1. **Date Navigation**: 13-day calendar view for quick date switching
2. **Winning Numbers**: Display and manual entry of 20 winning numbers
3. **Auto-Scraping**: Automated retrieval from nederlandseloterij.nl
4. **Player Management**: Track player bets and numbers
5. **Balance Tracking**: Calculate wins/losses

## Recent Changes (Nov 25, 2025)
- Migrated from MySQL to PostgreSQL for Replit compatibility
- Updated all database queries to use pg_* functions
- Configured PHP built-in server on port 5000
- Created login page at root (index.php)
- Set up Puppeteer with headless browser flags for Replit
- Added default admin user (admin/admin)

## Development Notes
- All sessions managed via PHP sessions
- PostgreSQL connection uses environment variables (PGHOST, PGDATABASE, etc.)
- Puppeteer configured with --no-sandbox for Replit environment
- Node.js scraper posts to save_winning_numbers.php endpoint
