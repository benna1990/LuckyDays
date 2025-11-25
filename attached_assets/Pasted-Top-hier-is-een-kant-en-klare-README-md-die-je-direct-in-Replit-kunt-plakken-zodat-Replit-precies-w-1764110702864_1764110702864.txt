Top, hier is een kant-en-klare README.md die je direct in Replit kunt plakken zodat Replit precies weet wat je project doet, wat de bedoeling is, hoe de scraper werkt, en hoe de dataflow is.
Alles in platte tekst, netjes, compleet en zonder overbodige troep.

⸻

README – LuckyDays Dashboard (PHP + MySQL + Webscraper)

Overzicht

LuckyDays Dashboard is een PHP-webapplicatie die automatisch de winnende LuckyDays-nummers ophaalt per geselecteerde datum.
De gebruiker kiest een datum via een datepicker, waarna het systeem:
	1.	Controleert of die datum al in de database staat
	2.	Zo niet → automatisch een scraper start die de LuckyDay-website bezoekt
	3.	De 20 winnende nummers uit de HTML leest
	4.	De nummers opslaat in de MySQL-database
	5.	De nummers toont op het dashboard

Dit proces moet volledig automatisch werken voor elke datum.

⸻

Doel van dit project
	•	De LuckyDays-uitslagen inzichtelijk maken per datum
	•	Automatisch scrapen wanneer data nog niet bestaat
	•	Data lokaal opslaan voor snelheid
	•	Een mooi, simpel dashboard met datumselectie tonen
	•	Een betrouwbare, herhaalbare workflow bouwen:

UI → Controle database → Scrape indien nodig → Opslaan → Tonen


⸻

Functionaliteiten

1. Dashboardpagina /dashboard.php
	•	Laadt automatisch de huidige datum bij eerste bezoek
	•	De URL krijgt een parameter:
?selected_date=YYYY-MM-DD
	•	Via de datepicker kun je elke andere datum kiezen
	•	Pagina toont de 20 winnende nummers van die dag

2. Datumselectie & Navigatie
	•	De datepicker synchroniseert met de URL
	•	Bij datumwijziging wordt window.location.href bijgewerkt
	•	Je kunt naast datums klikken (bijv. dagen ervoor of erna)

3. Automatische Webscraper

De applicatie scrapt deze URL:

https://luckyday.nederlandseloterij.nl/uitslag?date=YYYY-MM-DD

De scraper moet:
	•	De HTML ophalen via cURL
	•	De DOM parsen (Simple HTML DOM Parser)
	•	De 20 winnende nummers uitlezen
	•	De data teruggeven in een array
	•	Bij succes direct opslaan in MySQL

4. Database

Tabelsuggestie:

id (int, AI)
date (date)
number1 … number20
created_at (timestamp)

Regels:
	•	Bij het openen van een datum:
→ Zoek in database
→ Zo niet gevonden → scrape → opslaan → tonen
	•	Data moet altijd consistent zijn

5. Logica / Flow

Wanneer gebruiker een datum opent:
1. Check DB op matching datum
2a. Data bestaat → toon nummers
2b. Data bestaat niet → start scraper
3. Scrape LuckyDays pagina
4. Parse de 20 nummers
5. Sla op in database
6. Toon op dashboard

6. Projectbestanden
	•	dashboard.php → hoofdscherm + data ophalen
	•	functions.php → databasefuncties + scraperfuncties
	•	config.php → MySQL instellingen
	•	simple_html_dom.php → HTML parser
	•	index.php → redirect naar dashboard

Optioneel:
	•	cron.php → automatisch laatste dagen pre-scrapen
	•	/api/getNumbers.php → JSON output op datum

⸻

Technische details voor Replit
	•	PHP 8+
	•	MySQL/MariaDB
	•	cURL ingeschakeld
	•	Simple HTML DOM parser meegeleverd

Replit moet herkennen dat:
	•	Dit een server-side project is
	•	Het scraping + database + dashboard combineert
	•	De scraper automatisch moet draaien wanneer nodig
	•	Het doel is om winnende nummers per datum te verwerken, niet om AI-PDF’s of mockups te maken


