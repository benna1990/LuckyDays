<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use voku\helper\HtmlDomParser;
require_once 'config.php'; // Verbinding met de database

##require_once 'php/simple_html_dom.php'; // Voeg Simple HTML DOM Parser toe

// Controleer of de gebruiker is ingelogd
if (!isset($_SESSION['username'])) {
    header('Location: login.php'); // Stuur naar login als niet ingelogd
    exit();
}

// Controleer of de gebruiker een beheerder is
$isAdmin = ($_SESSION['role'] === 'admin');



// Winnende nummers opslaan als deze via POST zijn verzonden
if (isset($_POST['set_winning_numbers'])) {
    $winning_numbers = array(
        $_POST['win_num1'], $_POST['win_num2'], $_POST['win_num3'],
        $_POST['win_num4'], $_POST['win_num5'], $_POST['win_num6'],
        $_POST['win_num7'], $_POST['win_num8'], $_POST['win_num9'], $_POST['win_num10']
    );

    // Valideer de winnende nummers
    foreach ($winning_numbers as $number) {
        if ($number < 1 || $number > 80 || !is_numeric($number)) {
            echo "Ongeldig nummer ingevoerd: $number. Nummers moeten tussen 1 en 80 liggen.";
            exit();
        }
    }

    $_SESSION['winning_numbers'] = $winning_numbers; // Sla de winnende nummers op in de sessie
    echo "Winnende nummers ingesteld: " . implode(', ', $winning_numbers);
}

// Haal de winnende nummers van een externe bron
function fetchWinningNumbersFromWeb() {
    $url = "https://luckyday.nederlandseloterij.nl/uitslag";
    $htmlContent = file_get_contents($url);

    if (!$htmlContent) {
        return ['error' => 'Kon de pagina niet ophalen.'];
    }

    $dom = HtmlDomParser::str_get_html($htmlContent);

    if (!$dom) {
        return ['error' => 'Kon de HTML niet verwerken.'];
    }

    // Vind de winnende nummers
    $winningNumbers = [];
    foreach ($dom->find('.base-ticket-numbers__number span') as $element) {
        $winningNumbers[] = trim($element->innertext ?? '');
    }

    if (empty($winningNumbers)) {
        return ['error' => 'Geen winnende nummers gevonden.'];
    }

    return $winningNumbers;
}

// Winnende nummers ophalen als ze niet al in de sessie staan
if (!isset($_SESSION['winning_numbers'])) {
    $winning_numbers = fetchWinningNumbersFromWeb();
    $_SESSION['winning_numbers'] = $winning_numbers;
}

$winning_numbers = $_SESSION['winning_numbers'] ?? [];
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuckyDays Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Welkom op het Dashboard, <?php echo $_SESSION['username']; ?></h1>

        <?php if ($isAdmin): ?>
        <div class="mt-3">
            <a href="php/admin_beheer.php" class="btn btn-warning">Beheer Gebruikers & Beheerders</a>
        </div>
    <?php endif; ?>
    
        <!-- Winnende nummers invoeren -->
        <h2>Winnende Nummers Invoeren</h2>
        <form method="POST">
            <div class="row">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div class="col">
                        <label for="win_num<?php echo $i; ?>">Nummer <?php echo $i; ?></label>
                        <input type="number" name="win_num<?php echo $i; ?>" class="form-control" required min="1" max="80">
                    </div>
                <?php endfor; ?>
            </div>
            <button type="submit" name="set_winning_numbers" class="btn btn-primary mt-3">Winnende Nummers Instellen</button>
        </form>

        <!-- Weergave van winnende nummers -->
        <div id="winningNumbersDisplay" class="mt-4">
            <?php if (!empty($winning_numbers)): ?>
                <h3>Ingevoerde Winnende Nummers</h3>
                <p><?php echo implode(', ', $winning_numbers); ?></p>
            <?php else: ?>
                <p>Geen winnende nummers ingesteld.</p>
            <?php endif; ?>
        </div>

        <!-- Filteren op naam of datum -->
        <h2 class="mt-5">Filteren</h2>
        <input type="text" id="filterInput" class="form-control" placeholder="Filter op naam of datum">

        <!-- Tabel met spelersgegevens -->
        <h3 class="mt-5">Spelersgegevens</h3>
        <table id="dataTable" class="table table-striped">
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Datum</th>
                    <th>Ingezet</th>
                    <th>Gespeeld</th>
                    <th>Actie</th>
                </tr>
            </thead>
            <tbody id="dataTableBody">
                <!-- Dynamisch toegevoegde rijen komen hier -->
            </tbody>
        </table>

        <!-- Knop om een nieuwe rij toe te voegen -->
        <button type="button" class="btn btn-primary" onclick="addRow()">Voeg een rij toe</button>

        <!-- Formulier voor het versturen van gegevens -->
        <form action="php/process_data.php" method="POST">
            <button type="submit" class="btn btn-success mt-3">Opslaan</button>
        </form>
    </div>

    <script>
    // Nieuwe rij toevoegen
    function addRow() {
        let table = document.getElementById("dataTableBody");
        let row = document.createElement("tr");

        row.innerHTML = `
            <td><input type="text" name="name[]" class="form-control" required></td>
            <td><input type="date" name="date[]" class="form-control" required></td>
            <td><input type="number" name="bet[]" class="form-control" required></td>
            <td><input type="text" name="played[]" class="form-control" required></td>
            <td><button type="button" class="btn btn-danger" onclick="removeRow(this)">Verwijderen</button></td>
        `;
        table.appendChild(row);
    }

    // Rij verwijderen
    function removeRow(button) {
        let row = button.parentNode.parentNode;
        row.parentNode.removeChild(row);
    }

    // Filteren van de tabel
    document.getElementById("filterInput").addEventListener("keyup", function() {
        let input = this.value.toLowerCase();
        let rows = document.getElementById("dataTable").getElementsByTagName("tr");

        for (let i = 1; i < rows.length; i++) {
            let row = rows[i];
            let cells = row.getElementsByTagName("td");
            let match = false;

            for (let j = 0; j < cells.length; j++) {
                if (cells[j].innerText.toLowerCase().includes(input)) {
                    match = true;
                    break;
                }
            }

            row.style.display = match ? "" : "none";
        }
    });
    </script>
</body>
</html>