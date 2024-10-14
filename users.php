<?php
session_start();
require_once 'config.php';

// Controleer of de ingelogde gebruiker een beheerder is
if ($_SESSION['username'] != 'admin') { // Pas dit aan naar hoe je beheerders identificeert
    header('Location: dashboard.php'); // Als geen admin, stuur door naar dashboard
    exit;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin/Gebruikers Beheer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Admin/Gebruikers Beheer</h1>

        <!-- Formulier om een nieuwe admin/gebruiker toe te voegen -->
        <h2>Voeg Nieuwe Gebruiker of Admin Toe</h2>
        <form method="POST" action="php/admin_beheer.php">
            <div class="mb-3">
                <label for="new_username" class="form-label">Gebruikersnaam</label>
                <input type="text" class="form-control" id="new_username" name="new_username" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">Wachtwoord</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Rol</label>
                <select class="form-control" id="role" name="role">
                    <option value="user">Gebruiker</option>
                    <option value="admin">Beheerder</option>
                </select>
            </div>
            <button type="submit" name="add_user" class="btn btn-primary">Toevoegen</button>
        </form>

        <!-- Lijst van huidige gebruikers/admins -->
        <h3 class="mt-5">Bestaande Gebruikers en Admins</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Gebruikersnaam</th>
                    <th>Rol</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
    <?php
    // Alle gebruikers en admins ophalen en weergeven
    $result = $conn->query("SELECT id, username, role FROM admins");
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['username']}</td>
                <td>{$row['role']}</td>
                <td>
                    <form method='POST' action='php/admin_beheer.php' style='display:inline'>
                        <input type='hidden' name='delete_user_id' value='{$row['id']}'>
                        <button type='submit' name='delete_user' class='btn btn-danger btn-sm'>Verwijderen</button>
                    </form>
                    <form method='POST' action='php/admin_beheer.php' style='display:inline'>
                        <input type='hidden' name='edit_user_id' value='{$row['id']}'>
                        <input type='password' name='new_password' placeholder='Nieuw wachtwoord' class='form-control' required>
                        <button type='submit' name='edit_user' class='btn btn-warning btn-sm'>Wachtwoord Wijzigen</button>
                    </form>
                </td>
              </tr>";
    }
    ?>
</tbody>
        </table>
    </div>
</body>
</html>