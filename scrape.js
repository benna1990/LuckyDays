const axios = require('axios');
const puppeteer = require('puppeteer');

async function scrape(date) {
    const browser = await puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu'
        ]
    });
    
    const page = await browser.newPage();

    try {
        await page.goto(`https://luckyday.nederlandseloterij.nl/uitslag?date=${date}`, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        const winnendeNummers = await page.evaluate(() => {
            const nummers = [];
            document.querySelectorAll('ul.base-ticket-numbers li span').forEach(el => {
                nummers.push(el.innerText);
            });
            return nummers;
        });

        await browser.close();

        if (winnendeNummers.length > 0) {
            const domain = process.env.REPL_SLUG ? `https://${process.env.REPL_SLUG}.${process.env.REPL_OWNER}.repl.co` : 'http://localhost:5000';
            
            const response = await axios.post(`${domain}/save_winning_numbers.php`, {
                date: date,
                numbers: winnendeNummers.join(',')
            });
            
            console.log(response.data);
        } else {
            console.log('Geen winnende nummers gevonden voor datum:', date);
        }
    } catch (error) {
        console.error('Error scraping:', error.message);
        await browser.close();
    }
}

const date = process.argv[2] || new Date().toISOString().split('T')[0];
scrape(date);
