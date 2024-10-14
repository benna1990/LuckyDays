const axios = require('axios');
const puppeteer = require('puppeteer');

// Functie om de nummers te scrapen
async function scrape(date) {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    // Ga naar de juiste pagina met de geselecteerde datum
    await page.goto(`https://luckyday.nederlandseloterij.nl/uitslag?date=${date}`);

    // Scrape de winnende nummers
    const winnendeNummers = await page.evaluate(() => {
        const nummers = [];
        document.querySelectorAll('ul.base-ticket-numbers li span').forEach(el => {
            nummers.push(el.innerText);
        });
        return nummers;
    });

    await browser.close();

    // Verstuur de winnende nummers naar de PHP API voor opslag in de database
    axios.post('http://localhost/save_winning_numbers.php', {
        date: date,
        numbers: winnendeNummers.join(',')
    }).then(response => {
        console.log(response.data);
    }).catch(error => {
        console.error(error);
    });
}

// Gebruik de datum die wordt meegegeven aan het script
const date = process.argv[2];
scrape(date);