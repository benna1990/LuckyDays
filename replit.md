# Lucky Day Lottery Tracker

## Overview
Modern webapp voor Lucky Day bonnenadministratie met volledig toetsenbord-gestuurde invoer:
- Dag bevat bonnen, bon bevat rijen
- Visueel numeriek toetsenbord (sticky onderaan)
- Live validatie met subtiele waarschuwingen
- Automatische spelbepaling op basis van aantal nummers
- Per rij eigen inzet en automatische winstberekening
- Winnende nummers highlighten (groen = goed, wit = fout)
- Responsive, minimalistisch Tailwind design
- Unieke spelernamen verplicht

## Tech Stack
- **Backend**: PHP 8.2 met PostgreSQL
- **Frontend**: TailwindCSS via CDN, Inter font, vanilla JavaScript
- **Scraper**: Node.js + Puppeteer (headless Chromium)
- **Database**: PostgreSQL (Replit managed)

## Database Schema
```sql
players (id, name, color, created_at)
bons (id, player_id, name, date, created_at)
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
├── dashboard.php       # Main dashboard - day overview (redirects to ?date=today)
├── bon.php             # Bon detail with visual keyboard input
├── beheer.php          # Admin page (players + bons)
├── weekoverzicht.php   # Week overview with stats, top 10, CSV export
├── logout.php          # Session logout
├── config.php          # PostgreSQL database config
├── functions.php       # Helper functions + all database queries
├── scraper.js          # Puppeteer-based scraper (loten.nl)
├── api/
│   ├── create_bon.php  # Create bon (auto-creates player if needed)
│   ├── add_rij.php     # Add rij to bon
│   ├── delete_rij.php  # Delete rij
│   ├── delete_bon.php  # Delete bon
│   └── scrape_numbers.php  # Fetch winning numbers
```

## Key Features

### 1. Visual Numeric Keyboard (bon.php)
- Sticky keyboard at bottom of screen
- Large touch-friendly buttons (0-9)
- Live input display with count (0/10)
- Duplicate number blocking with warning
- Auto-add number after 2 digits entered
- Special buttons: + (add), OK (finish), ← (remove last), C (clear)

### 2. Number Entry Workflow
1. Tap numbers on keyboard
2. Numbers appear as chips (green if winning, white if not)
3. Press OK when done with numbers
4. Adjust bet amount with +/- buttons
5. Save rij

### 3. Scraper (loten.nl)
- **Source**: `https://www.loten.nl/luckyday/`
- Scrapes by matching Dutch date format
- Returns 20 winning numbers + bonus number
- Caches results in database

### 4. Automatic Game Type Detection
- 1-10 numbers = 1-getallen to 10-getallen
- No manual selection needed

### 5. Official Multipliers (loten.nl)
```
1-getallen: 1 goed = 2x
2-getallen: 2 goed = 5x
3-getallen: 3 goed = 16x, 2 goed = 2x
4-getallen: 4 goed = 20x, 3 goed = 5x, 2 goed = 1x
5-getallen: 5 goed = 200x, 4 goed = 8x, 3 goed = 2x
6-getallen: 6 goed = 1000x, 5 goed = 20x, 4 goed = 5x, 3 goed = 1x
7-getallen: 7 goed = 2000x, 6 goed = 100x, 5 goed = 10x, 4 goed = 2x, 3 goed = 1x
8-getallen: 8 goed = 20000x, 7 goed = 200x, 6 goed = 20x, 5 goed = 8x, 4 goed = 2x
9-getallen: 9 goed = 100000x, 8 goed = 2000x, 7 goed = 100x, 6 goed = 8x, 5 goed = 2x
10-getallen: 10 goed = 300000x, 9 goed = 4000x, 8 goed = 200x, 7 goed = 20x, 6 goed = 5x, 5 goed = 2x
```

## UI Pages

### Dashboard
- Redirects to ?date=today (date always in URL for scraper)
- Date navigation (13 days)
- Bonnen list with player, rijen count, saldo
- Day stats (totaal inzet, winst, saldo)
- Winning numbers with fetch button
- Collapsible "Spelers van vandaag"

### Bon Detail (bon.php)
- Player info header with saldo
- Winning numbers display
- Visual keyboard input system
- Number chips (groen = match, wit = geen match, geel = pending)
- Rijen list with win/loss highlighting
- Totals summary

### Weekoverzicht
- Week navigation
- Stats cards (inzet, uitbetaald, resultaat, activiteit)
- Te betalen / Te ontvangen van spelers
- Top 10 Winnaars
- Top 10 Verliezers
- Alle spelers tabel met saldo/status
- CSV export
- Per-dag breakdown

## Design Rules
- No "casino" terminology anywhere
- Days contain bonnen
- Bonnen contain rijen
- Players must have unique names
- Subtle warnings (no pop-ups)
- Green = winning, White = not winning, Yellow = pending

## Recent Changes (Nov 26, 2025)
- Visual numeric keyboard instead of text input
- Scraper now uses loten.nl (more reliable)
- Updated multipliers to official values
- Dashboard always has date in URL
- Weekoverzicht with Top 10 winnaars/verliezers
- Organization balance (te betalen / te ontvangen)
- Duplicate number blocking with subtle warnings
- Bon creation with typed player name (auto-creates)

## Development Notes
- Session: `$_SESSION['admin_logged_in']`
- PostgreSQL via environment variables
- Scraper: Node.js + Puppeteer + Chromium
- All rijen recalculated when numbers are fetched
