const puppeteer = require('puppeteer');

async function scrapeLuckyDayNumbers(date, retries = 3) {
    let browser;
    let attempt = 0;

    while (attempt < retries) {
        try {
            attempt++;

            // Detecteer omgeving en gebruik juiste Chrome/Chromium path
            const launchOptions = {
                headless: 'new',
                args: [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu'
                ]
            };

            // Op macOS gebruikt Puppeteer standaard Chrome uit Applications
            // Als dit faalt, probeer dan zonder executablePath (gebruikt systeem Chrome)
            browser = await puppeteer.launch(launchOptions);

            const page = await browser.newPage();

            await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

            // Format date for the API URL (YYYY-MM-DD format is perfect)
            const url = `https://luckyday.nederlandseloterij.nl/uitslag?date=${date}`;

            await page.goto(url, {
                waitUntil: 'networkidle2',
                timeout: 30000
            });

            await new Promise(r => setTimeout(r, 3000));

            const result = await page.evaluate(() => {
                let numbers = [];
                let bonusNumber = null;

                // Try to find the numbers in various possible selectors
                // Look for number balls or list items containing numbers
                const numberElements = document.querySelectorAll('span.ball, div.ball, li.ball, .winning-number, .number-ball, [class*="number"], [class*="ball"]');

                numberElements.forEach(el => {
                    const text = el.textContent.trim();
                    const num = parseInt(text);
                    if (!isNaN(num) && num >= 1 && num <= 80 && !numbers.includes(num)) {
                        numbers.push(num);
                    }
                });

                // If we didn't find numbers with specific classes, try all spans/divs
                if (numbers.length < 20) {
                    numbers = [];
                    const allElements = document.querySelectorAll('span, div');
                    allElements.forEach(el => {
                        const text = el.textContent.trim();
                        // Only if it's a single/double digit number
                        if (/^\d{1,2}$/.test(text)) {
                            const num = parseInt(text);
                            if (!isNaN(num) && num >= 1 && num <= 80 && !numbers.includes(num) && numbers.length < 20) {
                                numbers.push(num);
                            }
                        }
                    });
                }

                // Look for bonus number specifically
                const bonusElements = document.querySelectorAll('[class*="bonus"], [class*="extra"]');
                bonusElements.forEach(el => {
                    const text = el.textContent.trim();
                    const num = parseInt(text);
                    if (!isNaN(num) && num >= 1 && num <= 80) {
                        bonusNumber = num;
                    }
                });

                if (numbers.length >= 20) {
                    return {
                        success: true,
                        numbers: numbers.slice(0, 20).sort((a, b) => a - b),
                        bonusNumber: bonusNumber
                    };
                }

                return { success: false, foundCount: numbers.length };
            });

            await browser.close();

            if (result.success && result.numbers && result.numbers.length >= 20) {
                return {
                    success: true,
                    numbers: result.numbers,
                    bonusNumber: result.bonusNumber,
                    date: date
                };
            }

            // Als eerste poging mislukt en we hebben retries over, probeer opnieuw
            if (attempt < retries) {
                await new Promise(r => setTimeout(r, 2000 * attempt)); // Exponential backoff
                continue;
            }

            return { success: false, error: 'Geen uitslag gevonden voor ' + date };

        } catch (error) {
            if (browser) {
                try {
                    await browser.close();
                } catch (closeError) {
                    // Ignore close errors
                }
            }

            // Als we nog retries hebben, probeer opnieuw
            if (attempt < retries) {
                await new Promise(r => setTimeout(r, 2000 * attempt)); // Exponential backoff
                continue;
            }

            return { success: false, error: error.message };
        }
    }

    return { success: false, error: 'Max retries bereikt' };
}

function getAmsterdamDateString() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Amsterdam' }).format(new Date());
}

const date = process.argv[2] || getAmsterdamDateString();
scrapeLuckyDayNumbers(date).then(result => {
    console.log(JSON.stringify(result));
}).catch(err => {
    console.log(JSON.stringify({ success: false, error: err.message }));
});
