<?php
// Include de Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Importeer de benodigde namespace
use voku\helper\HtmlDomParser;

// Functie om winnende nummers van de gegeven datum op te halen
function getWinningNumbersByDate($date) {
    // Bouw de URL voor de uitslagpagina op basis van de datum
    $url = 'https://luckyday.nederlandseloterij.nl/uitslag?datum=' . urlencode($date);

    // Haal de HTML van de uitslagpagina op
    $html = file_get_contents($url);

    // Controleer of er HTML is opgehaald
    if ($html === false) {
        return ['error' => 'Kon de pagina niet ophalen. Controleer de verbinding.'];
    }

    // Maak een Simple HTML DOM-object aan om de HTML te parseren
    $dom = HtmlDomParser::str_get_html($html);

    // Controleer of het DOM-object geldig is
    if (!$dom) {
        return ['error' => 'Kon de inhoud van de pagina niet verwerken.'];
    }

    // Zoek naar de winnende nummers in de HTML
    $winningNumbers = [];

    // Zoek naar het juiste element dat de winnende nummers bevat
    foreach ($dom->find('.base-ticket-numbers__number span') as $element) {
        $winningNumbers[] = trim($element->innertext ?? '');
    }

    // Controleer of er nummers zijn gevonden
    if (empty($winningNumbers)) {
        return ['error' => 'Geen winnende nummers gevonden voor de opgegeven datum.'];
    }

    return $winningNumbers;
}

// Haal de datum op van het POST-verzoek
$requestedDate = $_POST['date'] ?? null;

if ($requestedDate) {
    // Haal de winnende nummers op voor de ingevoerde datum
    $winningNumbers = getWinningNumbersByDate($requestedDate);

    // Retourneer de winnende nummers als JSON
    echo json_encode($winningNumbers);
} else {
    echo json_encode(['error' => 'Geen datum opgegeven.']);
}
?>