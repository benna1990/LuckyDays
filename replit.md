# Lucky Day Lottery Tracker

## Overview
Modern webapp voor Lucky Day bonnenadministratie met popup-gebaseerde invoer:
- Dag bevat bonnen, bon bevat rijen
- Popup-invoer voor naam en nummers (geen visueel toetsenbord)
- Live speler filtering en automatische aanmaak
- Automatische spelbepaling op basis van aantal nummers
- Per rij eigen inzet en automatische winstberekening
- Winnende nummers highlighten (groen = goed, wit = fout)
- Responsive, minimalistisch Tailwind design
- Unieke automatische kleuren voor nieuwe spelers

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
├── dashboard.php       # Main dashboard - popup-based bon creation
├── bon.php             # Bon detail - popup-based rij creation
├── beheer.php          # Admin page (players + bons)
├── weekoverzicht.php   # Week overview with stats, top 10, CSV export
├── logout.php          # Session logout
├── config.php          # PostgreSQL database config
├── functions.php       # Helper functions + all database queries
├── scraper.js          # Puppeteer-based scraper (loten.nl)
├── api/
│   ├── create_bon.php    # Create bon (JSON + form support)
│   ├── create_player.php # Create player with auto-color
│   ├── add_rij.php       # Add rij to bon
│   ├── delete_rij.php    # Delete rij
│   ├── delete_bon.php    # Delete bon
│   └── scrape_numbers.php  # Fetch winning numbers
```

## Key Features

### 1. Popup-Based Input Flow (dashboard.php)
**Naam invoer:**
1. Klik "Nieuwe bon" - opent naam popup
2. Typ naam - live filtering van bestaande spelers
3. Enter: selecteert bestaande OF maakt nieuwe speler met auto-kleur
4. Lege Enter: sluit popup

**Nummer invoer:**
1. Na naam opent automatisch nummer popup
2. Typ nummer, Enter = toevoegen aan rij
3. 0 of lege Enter = ga naar inzet

**Inzet invoer:**
1. Typ inzet, Enter = opslaan en nieuwe rij
2. Bij nieuwe rij: 0 als eerste nummer = bon klaar, terug naar dashboard

### 2. Rij invoer op bon.php
- Klik "Nieuwe rij" - opent nummer popup
- Zelfde flow als dashboard: typ nummers, 0 = naar inzet, Enter = opslaan

### 3. Scraper (loten.nl)
- **Source**: `https://www.loten.nl/luckyday/`
- Scrapes by matching Dutch date format
- Returns 20 winning numbers + bonus number
- Automatic retry every 10 seconds on failure
- Shows "Scraper tijdelijk niet beschikbaar" on error

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

### 6. Auto Color Generation
- New players automatically get unique colors
- 20 predefined colors, then random hex if all used

## UI Pages

### Dashboard
- Date navigation (13 days)
- Popup-based new bon flow
- Bonnen list with player, rijen count, saldo
- Day stats (totaal inzet, winst, saldo)
- Winning numbers with fetch button + auto retry
- Collapsible "Spelers van vandaag"

### Bon Detail (bon.php)
- Player info header with saldo
- Winning numbers display
- Popup-based rij creation
- Number chips (groen = match, wit = geen match, geel = pending)
- Rijen list with win/loss highlighting
- Totals summary

### Weekoverzicht
- Week navigation
- Stats cards (inzet, uitbetaald, resultaat, activiteit)
- Te betalen / Te ontvangen van spelers
- Top 10 Winnaars
- Top 10 Verliezers
- CSV export

## Design Rules
- Days contain bonnen
- Bonnen contain rijen
- Players must have unique names
- Subtle popup-based input (no visual keyboard)
- Green = winning, White = not winning, Yellow = pending

## Recent Changes (Nov 26, 2025)
- **Editable numbers in popup**: Click on any number to change it (prompt 1-80)
- **Save button**: Replaces "Bewerken", only appears when numbers changed
- **Loading state**: Save button disabled during save, shows "Opslaan..."
- **Consistent number sizing**: All numbers now 32x32px (w-8 h-8)
- **API endpoint**: New api/update_rij_numbers.php for updating row numbers
- **Dashboard bon list**: Shows player name prominently instead of "Bon [date]"
- **Bon detail popup**: Click bon to open popup with all details (rijen, calculations)
- **API endpoint**: New api/get_bon.php for bon details via AJAX
- **Klantoverzicht**: Always visible (not collapsed), shows inzet + saldo per player
- **Day stats**: Changed "Totale winst" to "Uitbetaling" for clarity
- **Empty bons filtered**: getBonnenByDate excludes bons without rijen
- **OK buttons**: Visible OK buttons for mobile users (number input, bet input)
- Replaced visual keyboard with popup-based input
- New name popup with live player filtering
- Number popup: typ + Enter flow
- 0 triggers bet input, 0 as first number = bon complete
- Scraper auto-retry on failure
- Auto-color generation for new players
- HTML date picker for easy date navigation
- Popup shows all saved rows with per-row calculations
- Winning numbers visible at top of all popups
- Running totals (inzet, winst, saldo) below saved rows
- Pending state (amber) when winning numbers unavailable
- Mobile keyboard support with enterkeyhint="go" for Enter key

## Development Notes
- Session: `$_SESSION['admin_logged_in']`
- PostgreSQL via environment variables
- Scraper: Node.js + Puppeteer + Chromium
- All rijen recalculated when numbers are fetched
