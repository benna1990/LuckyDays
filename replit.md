# Lucky Day Lottery Tracker

## Overview
Modern webapp voor Lucky Day bonnenadministratie met volledig toetsenbord-gestuurde invoer:
- Dag bevat bonnen, bon bevat rijen
- Keyboard-first nummerinvoer met code-stijl tips
- Automatische spelbepaling op basis van aantal nummers
- Per rij eigen inzet en automatische winstberekening
- Winnende nummers highlighten in blokjes
- Responsive, minimalistisch Tailwind design
- Unieke spelernamen verplicht

## Tech Stack
- **Backend**: PHP 8.2 met PostgreSQL
- **Frontend**: TailwindCSS via CDN, Inter font, vanilla JavaScript
- **Scraper**: Node.js + Puppeteer (headless Chromium)
- **Database**: PostgreSQL (Replit managed)

## Database Schema
```sql
players (id, name, color, created_at)       -- Unique name constraint
bons (id, player_id, name, date, created_at) -- Container for rijen
rijen (id, bon_id, numbers, bet, game_type, matches, multiplier, winnings, created_at)
winning_numbers (date, numbers)
admins (id, username, password, role)
```

## Default Login
- Username: `admin`
- Password: `admin`

## Project Structure
```
/
├── index.php           # Login page
├── dashboard.php       # Main dashboard - clean day overview
├── bon.php             # Bon detail page with rijen
├── beheer.php          # Admin page (players + bons)
├── weekoverzicht.php   # Week overview with CSV export
├── logout.php          # Session logout
├── config.php          # PostgreSQL database config
├── functions.php       # Helper functions + all database queries
├── scraper.js          # Puppeteer-based number scraper
├── api/
│   ├── create_bon.php  # Create new bon
│   ├── add_rij.php     # Add rij to bon
│   ├── delete_rij.php  # Delete rij
│   ├── delete_bon.php  # Delete bon
│   └── scrape_numbers.php  # Fetch winning numbers
└── php/
    └── simple_html_dom.php   # HTML parser library (legacy)
```

## Key Features

### 1. Data Structure
- **Dag**: Contains bonnen
- **Bon**: Container with name, player, date
- **Rij**: Individual bet with numbers, bet amount, result

### 2. Keyboard-First Rij Entry
**Workflow:**
1. Open bon detail page
2. Type numbers (1-80), Enter adds number block
3. Type "0" to finish numbers
4. Type bet amount, Enter saves rij
5. Numbers clear, ready for next rij

**Shortcuts:**
- `Enter` - Add number / Save rij
- `Backspace` - Remove last number
- `0` - Finish entering numbers

### 3. Scraper
- **URL**: `https://luckyday.nederlandseloterij.nl/uitslag?date=YYYY-MM-DD`
- Uses Puppeteer with headless Chromium (website requires JS)
- Saves to database and recalculates all rijen for that date

### 4. Automatic Game Type Detection
- Number count determines game type (3 numbers = 3-getallen)
- No manual selection needed

### 5. Multipliers
```
1-getallen: 1 match = 4x
2-getallen: 2 matches = 14x
3-getallen: 3 matches = 50x, 2 matches = 10x
4-getallen: 4 matches = 100x, 3 matches = 20x, 2 matches = 2x
5-getallen: 5 matches = 300x, 4 matches = 80x, 3 matches = 5x
6-getallen: 6 matches = 1500x, 5 matches = 100x, 4 matches = 10x, 3 matches = 2x
7-getallen: 7 matches = 5000x, 6 matches = 500x, 5 matches = 25x, 4 matches = 5x
8-getallen: 8 matches = 10000x, 7 matches = 1000x, 6 matches = 100x, 5 matches = 10x, 4 matches = 2x
9-getallen: 9 matches = 25000x, 8 matches = 2500x, 7 matches = 250x, 6 matches = 25x, 5 matches = 5x
10-getallen: 10 matches = 100000x, 9 matches = 5000x, 8 matches = 500x, 7 matches = 50x, 6 matches = 10x, 5 matches = 2x
```

## UI Pages

### Dashboard
- Clean day overview
- List of bonnen (name, total, rijen count)
- "Nieuwe bon" button
- Collapsible "Spelers van vandaag" section at bottom
- Date navigation (13 days)
- Winning numbers display with fetch button

### Bon Detail
- Player info header
- Winning numbers display
- Keyboard tips in code-style blocks
- Number entry with live preview
- Rij list with number blocks (winner = green, neutral = gray)
- Totals summary

### Beheer (Admin)
- Players list with edit/delete
- Unique name validation
- Recent bonnen list

### Weekoverzicht
- Week navigation
- Stats cards (bonnen, rijen, inzet, winst)
- Player breakdown table with saldo
- CSV export
- Per-day breakdown

## Naming Rules
- No "casino" terminology
- Days contain bonnen
- Bonnen contain rijen
- Players must have unique names

## Recent Changes (Nov 26, 2025)
- Complete redesign with new data structure (bon → rijen)
- Removed alias field from players
- Added unique name constraint
- Collapsible players section (not icons/cards)
- Code-style keyboard tips
- Clean minimalist card design
- Removed scraped data (fresh start)

## Development Notes
- Session management via `$_SESSION['admin_logged_in']`
- PostgreSQL via environment variables
- Scraper uses Node.js + Puppeteer (not cURL)
- Winnings recalculated when numbers are fetched
