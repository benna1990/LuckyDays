# Lucky Day Lottery Tracker

## Overview
This is a PHP-based web application for tracking Lucky Day lottery numbers and player bets. The application allows users to:
- View winning lottery numbers by date
- Manage player bets and track results
- Automatically scrape winning numbers from loten.nl
- Admin panel for user management

## Tech Stack
- **Backend**: PHP 8.2 with PostgreSQL
- **Frontend**: Bootstrap 5, vanilla JavaScript
- **Scraper**: PHP cURL + Simple HTML DOM Parser
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
├── dashboard.php       # Main dashboard with date picker
├── config.php          # PostgreSQL database config
├── functions.php       # Helper functions + scraper
├── header.php          # Navigation header
├── footer.php          # Page footer
├── php/
│   ├── simple_html_dom.php  # HTML parser library
│   ├── HtmlDocument.php     # DOM parser component
│   ├── HtmlNode.php         # DOM node component
│   ├── HtmlElement.php      # DOM element component
│   ├── constants.php        # Parser constants
│   ├── Debug.php            # Debug utilities
│   ├── login.php            # Alternative login
│   ├── admin_beheer.php     # User management
│   └── process_data.php     # Player data processing
├── run_scraper.php     # Manual scrape trigger endpoint
└── save_winning_numbers.php  # Save numbers endpoint
```

## Key Features
1. **Date Navigation**: 13-day calendar view for quick date switching
2. **Winning Numbers**: Display 20 winning numbers in a grid
3. **Auto-Scraping**: Automatic retrieval from loten.nl when date is opened
4. **Manual Entry**: Admin can manually enter numbers
5. **Balance Tracking**: Calculate wins/losses

## Application Flow
```
1. User opens date → Check database
2. Data exists? → Show from database
3. No data? → Auto-scrape from loten.nl
4. Save scraped data → Show on dashboard
```

## Scraper Details
- **Source**: https://www.loten.nl/luckyday/
- **Method**: PHP cURL + Simple HTML DOM Parser
- **Selector**: `ul.luckyday-getallen li` elements
- **Numbers**: 20 winning numbers per draw (range 1-80)

## Recent Changes (Nov 25, 2025)
- Migrated from MySQL to PostgreSQL for Replit compatibility
- Updated all database queries to use pg_* functions
- Configured PHP built-in server on port 5000
- Implemented auto-scraping with PHP cURL instead of Node.js Puppeteer
- Using loten.nl as source (official site requires JavaScript)
- Updated Simple HTML DOM library for PHP 8.2 compatibility
- Fixed header redirect path to /index.php

## Development Notes
- All sessions managed via PHP sessions
- PostgreSQL connection uses environment variables (PGHOST, PGDATABASE, etc.)
- Scraper runs automatically when opening a date without stored data
- Dashboard shows data source indicator (database/scraped/none)
