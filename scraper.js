const puppeteer = require('puppeteer');

async function scrapeLuckyDayNumbers(date) {
    let browser;
    try {
        browser = await puppeteer.launch({
            headless: 'new',
            executablePath: '/nix/store/qa9cnw4v5xkxyip6mb9kxqfq1z4x2dx1-chromium-138.0.7204.100/bin/chromium',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu'
            ]
        });
        
        const page = await browser.newPage();
        
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        const url = 'https://www.loten.nl/luckyday/';
        
        await page.goto(url, { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });
        
        await new Promise(r => setTimeout(r, 2000));
        
        const targetDate = new Date(date);
        const dutchDays = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
        const dutchMonths = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
        
        const dayName = dutchDays[targetDate.getDay()];
        const day = targetDate.getDate();
        const monthName = dutchMonths[targetDate.getMonth()];
        const year = targetDate.getFullYear();
        
        const datePattern = `${dayName} ${day} ${monthName} ${year}`.toLowerCase();
        const shortDatePattern = `${day} ${monthName} ${year}`.toLowerCase();
        
        const result = await page.evaluate((datePattern, shortDatePattern) => {
            const h2Elements = document.querySelectorAll('h2');
            
            for (const h2 of h2Elements) {
                const headerText = h2.textContent.toLowerCase().trim();
                
                if (!headerText.includes('lucky day uitslag')) continue;
                
                const matchesDate = headerText.includes(datePattern) || headerText.includes(shortDatePattern);
                
                if (!matchesDate) continue;
                
                let numbers = [];
                let bonusNumber = null;
                
                let sibling = h2.nextElementSibling;
                while (sibling) {
                    if (sibling.tagName === 'H2') break;
                    
                    const items = sibling.querySelectorAll('li');
                    items.forEach(item => {
                        const num = parseInt(item.textContent.trim());
                        if (!isNaN(num) && num >= 1 && num <= 80 && !numbers.includes(num)) {
                            numbers.push(num);
                        }
                    });
                    
                    const bonusEl = sibling.querySelector('.luckyday-bonusgetal span');
                    if (bonusEl) {
                        const bonusNum = parseInt(bonusEl.textContent.trim());
                        if (!isNaN(bonusNum) && bonusNum >= 1 && bonusNum <= 80) {
                            bonusNumber = bonusNum;
                        }
                    }
                    
                    sibling = sibling.nextElementSibling;
                }
                
                if (numbers.length >= 20) {
                    return {
                        success: true,
                        numbers: numbers.slice(0, 20).sort((a, b) => a - b),
                        bonusNumber: bonusNumber,
                        dateFound: headerText
                    };
                }
            }
            
            return { success: false };
        }, datePattern, shortDatePattern);
        
        await browser.close();
        
        if (result.success && result.numbers && result.numbers.length >= 20) {
            return {
                success: true,
                numbers: result.numbers,
                bonusNumber: result.bonusNumber,
                date: date
            };
        }
        
        return { success: false, error: 'Geen uitslag gevonden voor ' + date };
        
    } catch (error) {
        if (browser) await browser.close();
        return { success: false, error: error.message };
    }
}

const date = process.argv[2] || new Date().toISOString().split('T')[0];
scrapeLuckyDayNumbers(date).then(result => {
    console.log(JSON.stringify(result));
}).catch(err => {
    console.log(JSON.stringify({ success: false, error: err.message }));
});
