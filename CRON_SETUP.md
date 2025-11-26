# Cron Job Setup voor Daily Scraper

Deze handleiding legt uit hoe je de automatische dagelijkse scraper instelt die elke dag om 19:02 draait.

## Stap 1: Test de scraper eerst handmatig

Voordat je de cron job instelt, test eerst of de scraper werkt:

```bash
cd /Applications/MAMP/htdocs/LuckyDays
node scraper.js $(date +%Y-%m-%d)
```

Of test de shell script:

```bash
/Applications/MAMP/htdocs/LuckyDays/daily_scrape.sh
```

## Stap 2: Open de crontab editor

```bash
crontab -e
```

Dit opent je crontab bestand in een editor (meestal vi/vim).

## Stap 3: Voeg de cron job toe

Voeg de volgende regel toe aan het einde van het bestand:

```
2 19 * * * /Applications/MAMP/htdocs/LuckyDays/daily_scrape.sh
```

Deze regel betekent:
- `2` = minuut 2
- `19` = uur 19 (7:02 PM)
- `* * *` = elke dag van de maand, elke maand, elke dag van de week

## Stap 4: Sla op en sluit de editor

In vi/vim:
- Druk op `i` om insert mode te activeren
- Plak de regel
- Druk op `Esc`
- Type `:wq` en druk op Enter

## Stap 5: Controleer of de cron job is toegevoegd

```bash
crontab -l
```

Dit toont alle actieve cron jobs.

## Logs bekijken

De scraper schrijft logs naar:

```
/Applications/MAMP/htdocs/LuckyDays/logs/scraper.log
```

Bekijk de logs met:

```bash
tail -f /Applications/MAMP/htdocs/LuckyDays/logs/scraper.log
```

## Alternatief: Gebruik launchd (macOS aanbevolen)

Op macOS is het beter om launchd te gebruiken in plaats van cron. Hier is hoe:

### Stap 1: Maak een plist bestand

```bash
nano ~/Library/LaunchAgents/com.luckydays.scraper.plist
```

### Stap 2: Voeg de volgende inhoud toe:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.luckydays.scraper</string>
    <key>ProgramArguments</key>
    <array>
        <string>/Applications/MAMP/htdocs/LuckyDays/daily_scrape.sh</string>
    </array>
    <key>StartCalendarInterval</key>
    <dict>
        <key>Hour</key>
        <integer>19</integer>
        <key>Minute</key>
        <integer>2</integer>
    </dict>
    <key>StandardOutPath</key>
    <string>/Applications/MAMP/htdocs/LuckyDays/logs/scraper.log</string>
    <key>StandardErrorPath</key>
    <string>/Applications/MAMP/htdocs/LuckyDays/logs/scraper_error.log</string>
</dict>
</plist>
```

### Stap 3: Laad de launchd service

```bash
launchctl load ~/Library/LaunchAgents/com.luckydays.scraper.plist
```

### Stap 4: Controleer of de service actief is

```bash
launchctl list | grep luckydays
```

## Handmatig de scraper uitvoeren

Je kunt de scraper altijd handmatig uitvoeren voor een specifieke datum:

```bash
node scraper.js 2025-01-15
```

Of voor vandaag:

```bash
node scraper.js $(date +%Y-%m-%d)
```

## Troubleshooting

### Scraper werkt niet in cron

Als de scraper werkt wanneer je hem handmatig uitvoert maar niet via cron:

1. **Controleer het volledige pad naar node**:
   ```bash
   which node
   ```

2. **Update daily_scrape.sh** met het juiste pad (bijv. `/usr/local/bin/node`)

3. **Controleer permissies**:
   ```bash
   ls -la /Applications/MAMP/htdocs/LuckyDays/daily_scrape.sh
   ```
   Moet executable zijn (`-rwxr-xr-x`)

### Logs niet zichtbaar

Controleer of de logs directory bestaat:

```bash
ls -la /Applications/MAMP/htdocs/LuckyDays/logs/
```

Zo niet, maak hem aan:

```bash
mkdir -p /Applications/MAMP/htdocs/LuckyDays/logs
```

### Scraper mislukt

Kijk in de error logs:

```bash
cat /Applications/MAMP/htdocs/LuckyDays/logs/scraper.log
```

De scraper heeft nu retry-logica (3 pogingen met exponential backoff) dus tijdelijke netwerk problemen zouden automatisch opgelost moeten worden.
