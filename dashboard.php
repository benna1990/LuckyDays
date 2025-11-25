<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
require_once 'functions.php';

$pageTitle = "Dashboard";
include 'header.php';

if (!isset($_GET['selected_date']) && !isset($_SESSION['selected_date'])) {
    $_SESSION['selected_date'] = date('Y-m-d');
}

$selected_date = $_GET['selected_date'] ?? $_SESSION['selected_date'];
$_SESSION['selected_date'] = $selected_date;

if (isset($_POST['set_winning_numbers'])) {
    $winning_numbers = explode(PHP_EOL, trim($_POST['winning_numbers']));
    $winning_numbers = array_map('trim', $winning_numbers);
    $winning_numbers = array_filter($winning_numbers);

    if (count($winning_numbers) === 20) {
        saveWinningNumbersToDatabase($selected_date, $winning_numbers, $conn);
        $success = "Winnende nummers succesvol opgeslagen!";
    } else {
        $error = "Vul precies 20 winnende cijfers in (je hebt er " . count($winning_numbers) . " ingevuld).";
    }
}

$result = getOrScrapeWinningNumbers($selected_date, $conn);
$winning_numbers = $result['numbers'];
$data_source = $result['source'];

if (isset($result['error'])) {
    $scrape_error = $result['error'];
}
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
        .day-block { padding: 15px; border: 1px solid #007bff; color: #007bff; text-align: center; border-radius: 5px; font-size: 1.1rem; background-color: #f8f9fa; transition: background-color 0.3s ease; cursor: pointer; text-decoration: none; }
        .day-block:hover { background-color: #007bff; color: white; }
        .current-day { background-color: #007bff; color: white; }
        .weekend { background-color: #fff3cd; }
        .data-source { font-size: 0.9rem; padding: 5px 10px; border-radius: 4px; display: inline-block; margin-bottom: 10px; }
        .source-database { background-color: #d4edda; color: #155724; }
        .source-scraped { background-color: #cce5ff; color: #004085; }
        .source-none { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="container mt-5">
    <h1 class="page-title">Lucky Day Dashboard</h1>

    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Selecteer een Datum</h2>
        </div>
        <div class="col-md-6 text-end">
            <input type="date" id="datePicker" class="form-control" value="<?php echo $selected_date; ?>">
        </div>
    </div>

    <div class="day-switcher mb-4">
        <?php foreach (generateDateRange($selected_date) as $day): ?>
            <?php 
                $dayClass = (date('N', strtotime($day)) >= 6) ? 'weekend' : 'weekday'; 
                if ($day == $selected_date) $dayClass = 'current-day';
            ?>
            <a href="?selected_date=<?php echo $day; ?>" class="day-block <?php echo $dayClass; ?>">
                <?php echo getDayAndAbbreviatedMonth($day); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <h2>Winnende Nummers - <?php echo date('d-m-Y', strtotime($selected_date)); ?></h2>
            
            <?php if ($data_source === 'database'): ?>
                <span class="data-source source-database">Data uit database</span>
            <?php elseif ($data_source === 'scraped'): ?>
                <span class="data-source source-scraped">Automatisch opgehaald</span>
            <?php else: ?>
                <span class="data-source source-none">Geen data beschikbaar</span>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($scrape_error)): ?>
                <div class="alert alert-warning"><?php echo $scrape_error; ?></div>
            <?php endif; ?>

            <div class="number-grid">
                <?php foreach ($winning_numbers as $index => $number): ?>
                    <div class="number-block"><?php echo htmlspecialchars($number); ?></div>
                <?php endforeach; ?>
            </div>

            <?php if (isset($isAdmin) && $isAdmin): ?>
                <button class="btn btn-warning mt-2" onclick="toggleAddNumbersForm()">Winnende Nummers Wijzigen</button>
                <button class="btn btn-primary mt-2" onclick="forceScrape()">Opnieuw Scrapen</button>

                <form id="addNumbersForm" action="dashboard.php?selected_date=<?php echo $selected_date; ?>" method="POST" style="display:none;" class="mt-3">
                    <div class="mb-3">
                        <label class="form-label">Voer 20 winnende nummers in (elk nummer op een nieuwe regel):</label>
                        <textarea name="winning_numbers" class="form-control" rows="10" placeholder="1&#10;2&#10;3&#10;..."></textarea>
                    </div>
                    <input type="hidden" name="selected_date" value="<?php echo $selected_date; ?>">
                    <button type="submit" name="set_winning_numbers" class="btn btn-success">Opslaan</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('datePicker').addEventListener('change', function() {
    window.location.href = '?selected_date=' + this.value;
});

function toggleAddNumbersForm() {
    const form = document.getElementById('addNumbersForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function forceScrape() {
    if (confirm('Wil je de nummers opnieuw ophalen van de website?')) {
        fetch('run_scraper.php?date=<?php echo $selected_date; ?>&force=1')
            .then(response => response.text())
            .then(data => {
                alert(data);
                location.reload();
            })
            .catch(error => console.error('Error:', error));
    }
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>
