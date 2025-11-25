<?php
// Start session only if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: /index.php');
    exit();
}



// Check if the user is an admin
$isAdmin = ($_SESSION['role'] === 'admin');
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Styling for the navigation menu and dropdown */
        .navbar-nav .nav-item {
            margin-right: 15px;
        }

        .navbar-dark .navbar-nav .nav-link {
            color: #fff;
        }

        .navbar-dark .navbar-nav .nav-link:hover {
            color: #ddd;
        }

        .dropdown-menu {
            position: absolute;
            right: 0;
            left: auto;
        }

        .page-title {
            margin-top: 20px;
            text-align: center;
            font-size: 2rem;
        }

        /* Styling for day blocks */
.day-switcher {
    display: grid;
    grid-template-columns: repeat(13, 1fr); /* 13 uniform blocks */
    gap: 10px;
}

.day-block {
    padding: 15px;
    border: 1px solid #007bff;
    color: #007bff;
    text-align: center;
    border-radius: 5px;
    font-size: 1.1rem;
    background-color: #f8f9fa;
    transition: background-color 0.3s ease;
}

.day-block:hover {
    background-color: #007bff;
    color: white;
}

.current-day {
    background-color: #007bff;
    color: white;
}

.weekend {
    background-color: #ffefef;
}

/* Date picker styling */
.date-picker-inline {
    display: flex;
    justify-content: flex-end;
}

input[type="date"] {
    width: 250px;
    font-size: 1rem;
    padding: 5px;
}

/* Uniform text styling */
h1, h2, h3, p, li {
    font-family: 'Arial', sans-serif;
    color: #333;
}

/* Winning numbers grid */
.number-grid {
    display: grid;
    grid-template-columns: repeat(10, 1fr); /* Ensure all blocks are the same size */
    gap: 10px;
}

.number-grid div {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: center;
    font-size: 1.5em;
}
    </style>
</head>
<body>
    <!-- Navigation Menu -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Lucky Day</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <!-- Use absolute paths to the root -->
                <li class="nav-item"><a href="/dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="/dagen.php" class="nav-link">Dagen</a></li>
                <li class="nav-item"><a href="/spelers.php" class="nav-link">Spelers</a></li>
                <li class="nav-item"><a href="/balans.php" class="nav-link">Balans</a></li>
                <?php if ($isAdmin): ?>
                    <li class="nav-item"><a href="/php/admin_beheer.php" class="nav-link">Beheer</a></li>
                <?php endif; ?>
            </ul>
            <!-- User Info Dropdown -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo $_SESSION['username']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="#">Wachtwoord Wijzigen</a></li>
                        <li><a class="dropdown-item" href="/logout.php">Uitloggen</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>