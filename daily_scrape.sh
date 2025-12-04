#!/bin/bash

# Daily Lucky Day scraper script
# Runs at 19:02 every day to fetch today's lottery results

# Change to the LuckyDays directory
cd /Applications/MAMP/htdocs/LuckyDays

export TZ="Europe/Amsterdam"

# Get today's date in YYYY-MM-DD format in Amsterdam timezone
TODAY=$(TZ=Europe/Amsterdam date +%Y-%m-%d)

# Log file
LOG_FILE="/Applications/MAMP/htdocs/LuckyDays/logs/scraper.log"

# Create logs directory if it doesn't exist
mkdir -p /Applications/MAMP/htdocs/LuckyDays/logs

# Run the scraper
echo "$(TZ=Europe/Amsterdam date '+%Y-%m-%d %H:%M:%S') - Starting scraper for $TODAY" >> "$LOG_FILE"

# Find node path - try multiple locations
if [ -f "/opt/homebrew/bin/node" ]; then
    NODE_PATH="/opt/homebrew/bin/node"
elif [ -f "/usr/local/bin/node" ]; then
    NODE_PATH="/usr/local/bin/node"
else
    NODE_PATH="node"
fi

# Execute scraper with Node.js and capture output
SCRAPER_OUTPUT=$($NODE_PATH scraper.js "$TODAY" 2>&1)
SCRAPER_EXIT=$?

echo "$SCRAPER_OUTPUT" >> "$LOG_FILE"

# Parse the JSON output and save to database via PHP
if [ $SCRAPER_EXIT -eq 0 ]; then
    # Find PHP path
    if [ -f "/opt/homebrew/bin/php" ]; then
        PHP_PATH="/opt/homebrew/bin/php"
    elif [ -f "/Applications/MAMP/bin/php/php8.2.0/bin/php" ]; then
        PHP_PATH="/Applications/MAMP/bin/php/php8.2.0/bin/php"
    elif [ -f "/usr/bin/php" ]; then
        PHP_PATH="/usr/bin/php"
    else
        PHP_PATH="php"
    fi

    # Call PHP to save the numbers to database
    cd /Applications/MAMP/htdocs/LuckyDays
    echo "$SCRAPER_OUTPUT" | $PHP_PATH -r "
        require_once 'config.php';
        require_once 'functions.php';
        \$json = file_get_contents('php://stdin');
        \$result = json_decode(\$json, true);
        if (isset(\$result['success']) && \$result['success'] === true && isset(\$result['numbers'])) {
            \$date = \$result['date'] ?? '$TODAY';
            saveWinningNumbersToDatabase(\$date, \$result['numbers'], \$conn);
            recalculateAllRijenForDate(\$conn, \$date, \$result['numbers']);
            echo 'Numbers saved to database for ' . \$date . PHP_EOL;
        } else {
            echo 'Failed to parse scraper output or no numbers found' . PHP_EOL;
        }
    " >> "$LOG_FILE" 2>&1

    echo "$(TZ=Europe/Amsterdam date '+%Y-%m-%d %H:%M:%S') - Scraper completed successfully" >> "$LOG_FILE"
else
    echo "$(TZ=Europe/Amsterdam date '+%Y-%m-%d %H:%M:%S') - Scraper failed with error" >> "$LOG_FILE"
fi

echo "$(TZ=Europe/Amsterdam date '+%Y-%m-%d %H:%M:%S') - ----------------------------------------" >> "$LOG_FILE"
