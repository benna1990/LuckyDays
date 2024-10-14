<?php
// Start de sessie
session_start();

// Vernietig de sessie om de gebruiker uit te loggen
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta http-equiv="refresh" content="5;url=index.html">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uitgelogd</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }

        .logout-container {
            text-align: center;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }

        .logout-container h1 {
            font-size: 2rem;
            margin-bottom: 20px;
        }

        .logout-container p {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .logout-container a {
            color: #007bff;
            text-decoration: none;
            font-size: 1.1rem;
        }

        .logout-container a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="logout-container">
    <h1>Je bent uitgelogd</h1>
    <p>Je sessie is beëindigd. Bedankt voor je bezoek!</p>
    <a href="index.html">Klik hier om opnieuw in te loggen</a>
</div>

</body>
</html>