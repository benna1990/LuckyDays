# Lucky Day Lottery Tracker

## Overview
This is a PHP-based web application for tracking Lucky Day lottery numbers and player bets. The application allows users to:
- View winning lottery numbers by date
- Manage player bets and track results
- Automatically scrape winning numbers from loten.nl
- Admin panel for user management

## Tech Stack
- **Backend**: PHP 8.2 with PostgreSQL
- **Frontend**: Modern minimalistic CSS, Inter font, vanilla JavaScript
- **Scraper**: PHP cURL + Simple HTML DOM Parser
- **Database**: PostgreSQL (Replit managed)

## Database Schema
- `admins`: User accounts with roles (admin/user)
- `winning_numbers`: Lottery winning numbers by date
- `players`: Player bets (name, numbers, bet amount, game_type)
- `game_multipliers`: Win multipliers per game type and match count

## Default Login
- Username: `admin`
- Password: `admin`

## Project Structure
```
/
├── index.php           # Login page with modern design
├── dashboard.php       # Main dashboard with date picker, players, stats
├── logout.php          # Session logout
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
4. **Manual Entry**: Admin can manually enter/edit numbers
5. **Player Management**: Add/remove players with bet tracking
6. **Win Calculation**: Automatic calculation based on game type multipliers
7. **Stats Overview**: Total bet, winnings, and net profit display

## Game Types & Multipliers
- 3-getallen: Match 3 = 15x
- 4-getallen: Match 4 = 50x
- 5-getallen: Match 5 = 150x
- 6-getallen: Match 6 = 500x
- 7-getallen: Match 7 = 1000x
- 8-getallen: Match 8 = 2000x
- 9-getallen: Match 9 = 5000x
- 10-getallen: Match 10 = 10000x

## Application Flow
```
1. User opens date → Check database
2. Data exists? → Show from database
3. No data? → Auto-scrape from loten.nl
4. Save scraped data → Show on dashboard
5. Players added → Calculate wins based on matches
```

## Scraper Details
- **Source**: https://www.loten.nl/luckyday/
- **Method**: PHP cURL + Simple HTML DOM Parser
- **Selector**: `ul.luckyday-getallen li` elements
- **Numbers**: 20 winning numbers per draw (range 1-80)

## Recent Changes (Nov 25, 2025)
- Modern minimalistic UI redesign with Inter font
- Added player management with CRUD functionality
- Implemented win calculation with game type multipliers
- Stats overview showing total bet, winnings, profit
- Robust data handling - shows "uitslag ontbreekt" when no winning numbers
- Modal dialogs for adding players and editing numbers
- Responsive design with mobile-first approach
- Session-based authentication with admin/user roles

## Development Notes
- All sessions managed via PHP sessions
- PostgreSQL connection uses environment variables (PGHOST, PGDATABASE, etc.)
- Scraper runs automatically when opening a date without stored data
- Dashboard shows data source indicator (database/scraped/none)
- Win calculation skipped when no valid winning numbers available
