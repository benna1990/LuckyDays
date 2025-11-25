# Lucky Day Lottery Tracker

## Overview
This is a PHP-based web application for tracking Lucky Day lottery numbers and player bets. The application allows users to:
- View winning lottery numbers by date
- Manage player bets and track results
- Automatically scrape winning numbers from loten.nl
- Smart bon-invoer with 80 number buttons and keyboard shortcuts
- Admin panel for user and game type management

## Tech Stack
- **Backend**: PHP 8.2 with PostgreSQL
- **Frontend**: Modern minimalistic CSS, Inter font, vanilla JavaScript
- **Scraper**: PHP cURL + Simple HTML DOM Parser
- **Database**: PostgreSQL (Replit managed)

## Database Schema
- `admins`: User accounts with roles (admin/user)
- `winning_numbers`: Lottery winning numbers by date
- `players`: Player profiles (name, alias, color)
- `bons`: Individual bets (player_id, game_type, numbers, bet, date)
- `game_types`: Game configurations with JSON multipliers

## Default Login
- Username: `admin`
- Password: `admin`

## Project Structure
```
/
├── index.php           # Login page with modern design
├── dashboard.php       # Main dashboard with bon-invoer
├── spelers.php         # Player management
├── balans.php          # Balance overview with ROI
├── spellen.php         # Game type configuration
├── logout.php          # Session logout
├── config.php          # PostgreSQL database config
├── functions.php       # Helper functions + scraper
├── run_scraper.php     # Manual scrape trigger endpoint
├── save_winning_numbers.php  # Save numbers endpoint
└── php/
    ├── simple_html_dom.php  # HTML parser library
    ├── HtmlDocument.php     # DOM parser component
    ├── HtmlNode.php         # DOM node component
    ├── HtmlElement.php      # DOM element component
    ├── constants.php        # Parser constants
    ├── Debug.php            # Debug utilities
    └── admin_beheer.php     # User management
```

## Key Features

### 1. Smart Bon-Invoer (Ticket Entry)
- 80 number buttons grid with visual feedback
- Keyboard shortcuts:
  - Enter: Save bon
  - Backspace: Remove last number
  - Escape: Close modal
  - Number typing: Direct number entry
- Player selection with color coding
- Game type selection with dynamic max numbers
- Winning numbers highlighted in grid

### 2. Dashboard
- 13-day calendar navigation
- 20 winning number balls display
- Bons table with match highlighting
- Stats overview (bet, winnings, profit)
- Auto-scraping when date has no data

### 3. Player Management
- Player cards with color avatars
- Alias support
- Total bons and bet statistics
- Recent bons list

### 4. Balance Overview
- Total bet/winnings/profit
- Per-player ROI calculation
- Optimized batch query for winning numbers

### 5. Game Types Configuration
- Dynamic JSON multiplier structure
- Min bet and bet step settings
- Toggle active/inactive games
- Visual multiplier grid display

## Game Types & Multipliers
Configurable via spellen.php with JSON multipliers:
- 1 getal: 1 match = 4x
- 2 getallen: 2 matches = 14x
- 3 getallen: 3 matches = 50x
- 4 getallen: 4 matches = 100x
- 5 getallen: 5 matches = 300x
- 6 getallen: 6 matches = 1500x
- 7 getallen: 7 matches = 5000x
- 8 getallen: 8 matches = 10000x
- 9 getallen: 9 matches = 25000x
- 10 getallen: 10 matches = 100000x

## Application Flow
```
1. User opens date → Check database
2. Data exists? → Show from database
3. No data? → Auto-scrape from loten.nl
4. Save scraped data → Show on dashboard
5. User adds bon → Select player, game type, numbers
6. Bon saved → Calculate matches and winnings
7. Balance page → Show ROI per player
```

## Scraper Details
- **Source**: https://www.loten.nl/luckyday/
- **Method**: PHP cURL + Simple HTML DOM Parser
- **Selector**: `ul.luckyday-getallen li` elements
- **Numbers**: 20 winning numbers per draw (range 1-80)

## Recent Changes (Nov 25, 2025)
- Complete UI redesign with modern Inter font styling
- Smart bon-invoer with 80 number buttons and keyboard shortcuts
- Game types management with JSON multipliers
- Player profiles with colors and aliases
- Balance page with ROI calculation per player
- Optimized batch queries for winning numbers
- Bons system replacing simple player entries
- Visual number ball display for winning numbers
- Match highlighting in bon numbers

## Development Notes
- All sessions managed via PHP sessions
- PostgreSQL connection uses environment variables (PGHOST, PGDATABASE, etc.)
- Scraper runs automatically when opening a date without stored data
- Dashboard shows data source indicator (database/scraped/none)
- Balance calculation uses cached winning numbers (no re-scraping)
- Win calculation skipped when no valid winning numbers available
