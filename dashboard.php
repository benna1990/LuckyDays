<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php'; // Database verbinding
require_once 'functions.php'; // Laad de database-functies

$pageTitle = "Dashboard";
include 'header.php'; // Inclusief de header met navigatie

// Zorg dat de geselecteerde datum standaard op vandaag staat als deze niet is ingesteld
if (!isset($_GET['selected_date']) && !isset($_SESSION['selected_date'])) {
    $_SESSION['selected_date'] = date('Y-m-d');
}

$selected_date = $_GET['selected_date'] ?? $_SESSION['selected_date'];

// Verwerk het handmatig instellen van winnende nummers
if (isset($_POST['set_winning_numbers'])) {
    $winning_numbers = explode(PHP_EOL, trim($_POST['winning_numbers'])); // Splits de ingevoerde nummers per regel

    if (count($winning_numbers) === 20) {
        // Sla de nummers op in de sessie voor de geselecteerde datum
        $_SESSION['winning_numbers'][$selected_date] = $winning_numbers;
        saveWinningNumbersToDatabase($selected_date, $winning_numbers, $conn); // Opslaan in de database
    } else {
        $error = "Vul precies 20 winnende cijfers in."; // Foutmelding als er geen 20 nummers zijn
    }
}

// Haal de winnende nummers voor de geselecteerde datum uit de database
$winning_numbers = getWinningNumbersFromDatabase($selected_date, $conn) ?? array_fill(0, 20, '-');

// Sync de datepicker en datumblokken met de geselecteerde datum
echo "<script>
    function syncDate(selectedDate) {
        document.querySelector('input[name=\"selected_date\"]').value = selectedDate;
        window.location.href = '?selected_date=' + selectedDate;
    }

    window.addEventListener('DOMContentLoaded', (event) => {
        const datePicker = document.querySelector('input[name=\"selected_date\"]');
        datePicker.value = '" . $selected_date . "';

        // Maak de gehele date picker klikbaar om de kalender te openen
        datePicker.addEventListener('click', function() {
            this.showPicker();
        });

        // Zorg dat de date picker de juiste geselecteerde datum laat zien
        datePicker.addEventListener('change', function() {
            syncDate(this.value);
        });
    });
</script>";
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Lucky Day</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .number-grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 10px; margin-bottom: 20px; }
        .number-block { padding: 10px; border: 1px solid #007bff; color: #007bff; text-align: center; font-size: 1.5rem; border-radius: 5px; background-color: #f8f9fa; }
        .day-switcher { display: grid; grid-template-columns: repeat(13, 1fr); gap: 10px; }
        .day-block { padding: 15px; border: 1px solid #007bff; color: #007bff; text-align: center; border-radius: 5px; font-size: 1.1rem; background-color: #f8f9fa; transition: background-color 0.3s ease; }
        .day-block:hover { background-color: #007bff; color: white; }
        .current-day { background-color: #007bff; color: white; }
    </style>
</head>
<body>

<div class="container mt-5">
    <h1 class="page-title">Welkom op het Dashboard</h1>

    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Selecteer een Datum</h2>
        </div>
        <div class="col-md-6 text-end">
            <input type="date" name="selected_date" class="form-control" value="<?php echo $selected_date; ?>" required>
        </div>
    </div>

    <div class="day-switcher">
        <?php foreach (generateDateRange($selected_date) as $day): ?>
            <?php $dayClass = (date('N', strtotime($day)) >= 6) ? 'weekend' : 'weekday'; ?>
            <?php if ($day == $selected_date) $dayClass = 'current-day'; ?>
            <a href="javascript:void(0);" class="day-block <?php echo $dayClass; ?>" onclick="syncDate('<?php echo $day; ?>')">
                <?php echo getDayAndAbbreviatedMonth($day); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <h2>Winnende Nummers</h2>
            <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <div class="number-grid">
                <?php foreach ($winning_numbers as $number): ?>
                    <div class="number-block"><?php echo htmlspecialchars($number); ?></div>
                <?php endforeach; ?>
            </div>

            <?php if ($isAdmin): ?>
                <button class="btn btn-warning mt-2" onclick="toggleAddNumbersForm()">Winnende Nummers Wijzigen</button>
                <button class="btn btn-primary mt-2" onclick="runScraper()">Automatisch Winnende Nummers Ophalen</button>

<form id="addNumbersForm" action="dashboard.php" method="POST" style="display:none;">
    <textarea name="winning_numbers" class="form-control" rows="6" required></textarea>
    <input type="hidden" name="selected_date" value="<?php echo $selected_date; ?>">
    <button type="submit" name="set_winning_numbers" class="btn btn-success mt-2">Opslaan</button>
</form>

<script>
function runScraper() {
    fetch('run_scraper.php?date=<?php echo $selected_date; ?>')
        .then(response => response.text())
        .then(data => alert(data))
        .catch(error => console.error('Error:', error));
}
</script>
                    function toggleAddNumbersForm() {
                        const form = document.getElementById('addNumbersForm');
                        form.style.display = form.style.display === 'none' ? 'block' : 'none';
                    }

                    function runScraper() {
                        fetch('run_scraper.php?date=<?php echo $selected_date; ?>')
                            .then(response => response.text())
                            .then(data => alert(data))
                            .catch(error => console.error('Error:', error));
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>