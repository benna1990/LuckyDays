<?php
session_start();
require_once '../config.php'; // Verbind met de database

// Schakel foutmeldingen in voor debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Voeg een nieuwe gebruiker of admin toe
if (isset($_POST['add_user'])) {
    $new_username = $_POST['new_username'];
    $new_password = $_POST['new_password'];
    $role = $_POST['role'];

    // Hash het wachtwoord voor beveiliging
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Controleer of de gebruikersnaam al bestaat
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->bind_param('s', $new_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "Er bestaat al een gebruiker met deze naam.";
    } else {
        $stmt = $conn->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $new_username, $hashed_password, $role);
        $stmt->execute();
    }

    header("Location: admin_beheer.php");
    exit();
}

// Verwijder een gebruiker/admin
if (isset($_POST['delete_user'])) {
    $delete_id = $_POST['delete_user_id'];
    $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param('i', $delete_id);
    $stmt->execute();
    header("Location: admin_beheer.php");
    exit();
}

// Wijzig het wachtwoord voor een gebruiker/admin
if (isset($_POST['edit_user'])) {
    $edit_id = $_POST['edit_user_id'];
    $new_password = $_POST['new_password'];

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed_password, $edit_id);
        $stmt->execute();
    }

    header("Location: admin_beheer.php");
    exit();
}

// Include de header.php voor het navigatiemenu
include '../header.php';
?>

<!-- Page content for Admin Management -->
<div class="container mt-5">
    <h1>Gebruikers & Beheerders Beheren</h1>

    <!-- Button to toggle add user form -->
    <button class="btn btn-primary mb-3" onclick="toggleAddUserForm()">Gebruiker Toevoegen</button>

    <!-- Add User Form (hidden by default) -->
    <form id="addUserForm" action="admin_beheer.php" method="POST" style="display:none;">
        <div class="mb-3">
            <label for="new_username">Nieuwe Gebruikersnaam:</label>
            <input type="text" name="new_username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="new_password">Wachtwoord:</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="role">Rol:</label>
            <select name="role" class="form-control">
                <option value="admin">Beheerder</option>
                <option value="user">Gebruiker</option>
            </select>
        </div>
        <button type="submit" name="add_user" class="btn btn-success">Opslaan</button>
    </form>

    <!-- Display existing users -->
    <h2>Bestaande Gebruikers</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Gebruikersnaam</th>
                <th>Rol</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $result = $conn->query("SELECT * FROM admins");
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['username']}</td>";
                echo "<td>{$row['role']}</td>";
                echo "<td>
                        <form action='admin_beheer.php' method='POST' style='display:inline;'>
                            <input type='hidden' name='delete_user_id' value='{$row['id']}'>
                            <button type='submit' name='delete_user' class='btn btn-danger btn-sm'>Verwijderen</button>
                        </form>

                        <button class='btn btn-warning btn-sm' onclick='toggleEditPasswordForm({$row['id']})'>Wachtwoord Wijzigen</button>

                        <!-- Edit Password Form (hidden by default) -->
                        <form id='editPasswordForm-{$row['id']}' action='admin_beheer.php' method='POST' style='display:none;'>
                            <input type='hidden' name='edit_user_id' value='{$row['id']}'>
                            <div class='mb-3'>
                                <label for='new_password'>Nieuw Wachtwoord:</label>
                                <input type='password' name='new_password' class='form-control' required>
                            </div>
                            <button type='submit' name='edit_user' class='btn btn-success btn-sm'>Opslaan</button>
                        </form>
                    </td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script>
    function toggleAddUserForm() {
        const form = document.getElementById('addUserForm');
        form.style.display = (form.style.display === 'none') ? 'block' : 'none';
    }

    function toggleEditPasswordForm(id) {
        const form = document.getElementById('editPasswordForm-' + id);
        form.style.display = (form.style.display === 'none') ? 'block' : 'none';
    }
</script>

<?php include '../footer.php'; // Include de footer.php voor scripts en afsluitende HTML ?>