# LuckyDays Lottery Tracker

## Overview
Modern webapp voor LuckyDays-bonnenadministratie met:
- Dagselectie met correcte uitslag ophalen
- Spelers beheren met kleuren en aliassen  
- Bonnen/rijen invoeren (meerdere rijen per speler per dag)
- Automatische spelbepaling op basis van aantal ingevulde nummers
- Per rij een eigen inzet en automatische winstberekening
- Winnende nummers highlighten in groen
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
├── dashboard.php       # Main dashboard met rij-invoer
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

### 1. Scraper
- **URL**: `https://luckyday.nederlandseloterij.nl/uitslag?date=YYYY-MM-DD`
- Altijd exacte datum ophalen, geen fallback
- Opslaan in database, geen duplicaten
- JSON response: `{date, numbers: [20 getallen]}`

### 2. Rij Invoer
- 80 nummer-knoppen grid
- Automatische spelbepaling: aantal nummers = speltype
- Per rij eigen inzet
- Keyboard shortcuts: Enter (opslaan), Backspace (verwijderen), Esc (wissen)
- Winnende nummers groen gemarkeerd in grid

### 3. Multipliers (configureerbaar)
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

### 4. Dashboard
- 13-dagen datumnavigatie
- Uitslag in pill-style bolletjes
- Rijen gegroepeerd per speler
- Per rij: nummers, inzet, treffers, winst
- Totale statistieken per dag

### 5. Balans
- Totale inzet/winst/netto per speler
- ROI berekening per speler
- Geoptimaliseerde batch queries (geen re-scraping)

## Application Flow
```
1. Gebruiker selecteert datum
2. Check database voor uitslag
3. Geen data? → Scrape van nederlandseloterij.nl
4. Uitslag opslaan → Toon op dashboard
5. Gebruiker voegt rij toe → Selecteer speler, nummers, inzet
6. Speltype automatisch bepaald (aantal nummers)
7. Rij opgeslagen → Matches en winst berekend
8. Balans pagina → ROI per speler
```

## Recent Changes (Nov 25, 2025)
- Complete rebuild met Tailwind CSS
- Scraper gefixed: nu altijd correcte datum ophalen
- Players tabel vereenvoudigd (geen bet/date meer)
- Automatische spelbepaling op basis van nummers
- Per-rij winstberekening met matches
- Minimalistisch design (Notion/Linear style)
- Keyboard shortcuts voor snelle invoer
- Batch queries voor balans (geen re-scraping)

## Development Notes
- Session management via PHP sessions
- PostgreSQL via environment variables (PGHOST, PGDATABASE, etc.)
- Scraper alleen wanneer datum geen stored data heeft
- Winst berekening overgeslagen bij ontbrekende uitslag
