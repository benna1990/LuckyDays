# LuckyDays Lottery Tracker

## Overview
Modern webapp voor LuckyDays-bonnenadministratie met volledig toetsenbord-gestuurde invoer:
- Snelle spelerselectie met realtime zoeken
- Keyboard-only nummerinvoer (geen muis nodig)
- Automatische spelbepaling op basis van aantal nummers
- Per rij eigen inzet en automatische winstberekening
- Winnende nummers highlighten in badges
- Responsive, minimalistisch Tailwind design

## Tech Stack
- **Backend**: PHP 8.2 met PostgreSQL
- **Frontend**: TailwindCSS via CDN, Inter font, vanilla JavaScript
- **Scraper**: PHP cURL + Simple HTML DOM Parser
- **Database**: PostgreSQL (Replit managed)

## Database Schema
```sql
players (id, name, alias, color, created_at)
bons (id, player_id, date, numbers, bet, game_type, matches, multiplier, winnings, created_at)
winning_numbers (date, numbers)
admins (id, username, password, role)
game_types (id, name, numbers_count, min_bet, bet_step, multipliers, active)
```

## Default Login
- Username: `admin`
- Password: `admin`

## Project Structure
```
/
├── index.php           # Login page
├── dashboard.php       # Main dashboard met keyboard-first invoer
├── spelers.php         # Speler management
├── balans.php          # Balans overzicht met ROI
├── spellen.php         # Speltype configuratie
├── logout.php          # Session logout
├── config.php          # PostgreSQL database config
├── functions.php       # Helper functions + scraper
├── run_scraper.php     # Manual scrape endpoint
├── save_winning_numbers.php  # Save numbers endpoint
└── php/
    ├── simple_html_dom.php   # HTML parser library
    ├── HtmlDocument.php      # DOM parser
    ├── HtmlNode.php          # DOM node
    ├── HtmlElement.php       # DOM element
    ├── constants.php         # Parser constants
    ├── Debug.php             # Debug utilities
    └── admin_beheer.php      # User management
```

## Key Features

### 1. Keyboard-First Bon Invoer
**Workflow:**
1. Zoek speler (typ naam/nummer, pijltjes navigeren, Enter selecteert)
2. Typ nummers (1-80), Enter toevoegt badge
3. Typ "0" om nummers af te sluiten
4. Typ inzet, Enter opslaat rij
5. Automatisch nieuwe rij, focus terug naar nummerveld
6. "0" als eerste input = bon afsluiten

**Shortcuts:**
- `Enter` - Nummer toevoegen / Rij opslaan
- `Backspace` - Laatste nummer verwijderen
- `0` - Nummers afsluiten / Bon afsluiten
- `Pijltjes` - Navigeer spelers

### 2. Scraper
- **URL**: `https://luckyday.nederlandseloterij.nl/uitslag?date=YYYY-MM-DD`
- Altijd exacte datum ophalen
- Bij ontbrekende uitslag: waarschuwing + winstberekening uitgeschakeld

### 3. Automatische Spelbepaling
- Aantal nummers = speltype (3 nummers = 3-getallen)
- Geen dropdown of handmatige selectie

### 4. Multipliers (configureerbaar)
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

## Application Flow
```
1. Speler zoeken met keyboard
2. Nummers typen (Enter per nummer)
3. "0" typ = klaar met nummers
4. Inzet invoeren + Enter
5. Rij opgeslagen met berekende winst
6. Automatisch volgende rij
7. "0" als eerste = terug naar spelerselectie
```

## Recent Changes (Nov 26, 2025)
- Spelersnummer automatisch gegenereerd (database ID = spelersnummer)
- Casino-stijl dagoverzicht met per-speler afrekening
- Kleurcodering: groen = krijgt geld, rood = moet betalen
- Dagtotalen sectie met casino saldo
- "Uitslag ophalen" knop voor handmatige scrape
- Automatische scrape bij datumwissel (als geen data aanwezig)
- Weekoverzicht pagina (weekoverzicht.php) met CSV export
- Speler-detailoverzicht (speler_detail.php) met datumrange
- Paginering voor alle lijsten (compact, max 20-50 per pagina)
- Alias veld verwijderd uit speler model

## Development Notes
- Session management via PHP sessions
- PostgreSQL via environment variables
- Scraper alleen wanneer datum geen stored data heeft
- Winst berekening uitgeschakeld bij ontbrekende uitslag
