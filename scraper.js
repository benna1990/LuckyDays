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
                '--disable-gpu',
                '--disable-web-security',
                '--disable-features=IsolateOrigins,site-per-process'
            ]
        });
        
        const page = await browser.newPage();
        
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        await page.setViewport({ width: 1920, height: 1080 });
        
        const url = `https://luckyday.nederlandseloterij.nl/uitslag?date=${date}`;
        
        await page.goto(url, { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });
        
        await new Promise(r => setTimeout(r, 3000));
        
        const numbers = await page.evaluate(() => {
            const results = [];
            
            const selectors = [
                '.winning-numbers .number',
                '.luckyday-getallen li',
                '.result-number',
                '.winning-number',
                '[class*="winningNumber"]',
                '[class*="winning-number"]',
                '[class*="result"] [class*="number"]',
                '.ball',
                '[class*="ball"]',
                '[data-testid*="number"]',
                '.draw-result span',
                '.draw-result li',
                '.numbers span',
                '.uitslag-nummer',
                '[class*="drawn"]',
                '[class*="getrokken"]'
            ];
            
            for (const selector of selectors) {
                const elements = document.querySelectorAll(selector);
                if (elements.length >= 10) {
                    elements.forEach(el => {
                        const text = el.textContent.trim();
                        const num = parseInt(text);
                        if (!isNaN(num) && num >= 1 && num <= 80 && !results.includes(num)) {
                            results.push(num);
                        }
                    });
                    if (results.length >= 20) break;
                }
            }
            
            if (results.length < 20) {
                const allElements = document.querySelectorAll('*');
                allElements.forEach(el => {
                    if (el.children.length === 0) {
                        const text = el.textContent.trim();
                        if (/^\d+$/.test(text)) {
                            const num = parseInt(text);
                            if (num >= 1 && num <= 80 && !results.includes(num)) {
                                const style = window.getComputedStyle(el);
                                const parent = el.parentElement;
                                const parentClass = parent ? parent.className : '';
                                if (parentClass.toLowerCase().includes('number') || 
                                    parentClass.toLowerCase().includes('ball') ||
                                    parentClass.toLowerCase().includes('result') ||
                                    parentClass.toLowerCase().includes('uitslag') ||
                                    style.borderRadius.includes('50%') ||
                                    el.className.toLowerCase().includes('number')) {
                                    results.push(num);
                                }
                            }
                        }
                    }
                });
            }
            
            return results;
        });
        
        await browser.close();
        
        if (numbers.length >= 20) {
            const sorted = numbers.slice(0, 20).sort((a, b) => a - b);
            return { success: true, numbers: sorted, date: date };
        } else if (numbers.length > 0) {
            return { success: true, numbers: numbers.sort((a, b) => a - b), date: date, warning: `Slechts ${numbers.length} nummers gevonden` };
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
