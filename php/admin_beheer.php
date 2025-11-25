<?php
session_start();
require_once '../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['add_user'])) {
    $new_username = $_POST['new_username'];
    $new_password = $_POST['new_password'];
    $role = $_POST['role'];

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $result = pg_query_params($conn, "SELECT * FROM admins WHERE username = $1", [$new_username]);

    if ($result && pg_num_rows($result) > 0) {
        echo "Er bestaat al een gebruiker met deze naam.";
    } else {
        pg_query_params($conn, "INSERT INTO admins (username, password, role) VALUES ($1, $2, $3)", 
            [$new_username, $hashed_password, $role]);
    }

    header("Location: admin_beheer.php");
    exit();
}

if (isset($_POST['delete_user'])) {
    $delete_id = $_POST['delete_user_id'];
    pg_query_params($conn, "DELETE FROM admins WHERE id = $1", [$delete_id]);
    header("Location: admin_beheer.php");
    exit();
}

if (isset($_POST['edit_user'])) {
    $edit_id = $_POST['edit_user_id'];
    $new_password = $_POST['new_password'];

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        pg_query_params($conn, "UPDATE admins SET password = $1 WHERE id = $2", [$hashed_password, $edit_id]);
    }

    header("Location: admin_beheer.php");
    exit();
}

include '../header.php';
?>

<div class="container mt-5">
    <h1>Gebruikers & Beheerders Beheren</h1>

    <button class="btn btn-primary mb-3" onclick="toggleAddUserForm()">Gebruiker Toevoegen</button>

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

    <h2>Bestaande Gebruikers</h2>
    <table class="table table-bordered">
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
            $result = pg_query($conn, "SELECT * FROM admins");
            while ($row = pg_fetch_assoc($result)):
            ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['role']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="toggleEditPasswordForm(<?php echo $row['id']; ?>)">Wachtwoord Wijzigen</button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete_user_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?');">Verwijderen</button>
                        </form>

                        <form id="editPasswordForm-<?php echo $row['id']; ?>" method="POST" style="display:none; margin-top:10px;">
                            <input type="hidden" name="edit_user_id" value="<?php echo $row['id']; ?>">
                            <input type="password" name="new_password" class="form-control" placeholder="Nieuw Wachtwoord" required>
                            <button type="submit" name="edit_user" class="btn btn-sm btn-primary mt-2">Opslaan</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
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

<?php include '../footer.php'; ?>
