#!/bin/bash

# Daily Lucky Day scraper script
# Runs at 19:02 every day to fetch today's lottery results

# Change to the LuckyDays directory
cd /Applications/MAMP/htdocs/LuckyDays

# Get today's date in YYYY-MM-DD format
TODAY=$(date +%Y-%m-%d)

# Log file
LOG_FILE="/Applications/MAMP/htdocs/LuckyDays/logs/scraper.log"

# Create logs directory if it doesn't exist
mkdir -p /Applications/MAMP/htdocs/LuckyDays/logs

# Run the scraper
echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting scraper for $TODAY" >> "$LOG_FILE"

# Execute scraper with Node.js
/usr/local/bin/node scraper.js "$TODAY" >> "$LOG_FILE" 2>&1

# Check exit status
if [ $? -eq 0 ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Scraper completed successfully" >> "$LOG_FILE"
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Scraper failed with error" >> "$LOG_FILE"
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') - ----------------------------------------" >> "$LOG_FILE"
