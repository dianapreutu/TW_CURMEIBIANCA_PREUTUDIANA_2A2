<?php 

// ==================================================
// admin/users.php - Gestionarea utilizatorilor
// Aceasta pagina permite administratorului sa vada, 
// sa adauge si sa stearga utilizatorii aplicatiei
// ==================================================

// Include configurarile globale 
require_once '../config.php';

// Verificam daca utilizatorul este autentificat ca admin
// Daca nu, il redirectionam catre pagina de login
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: index.php');
    exit;
}

// Obtinem instanta bazei de date
$db = Database::getInstance();

// Mesaj de succes sau eroare (afisat dupa operatii)
$message = '';
$error = '';

// Verificam daca s-a trimis formularul
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Citim actiunea din formular
    $action = $_POST['action'] ?? '';

    // -- Adaugare utilizator nou --
    if ($action === 'add') {

        // Citim si curatam datele din formular
        $username = trim(htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'));
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';

        // Validam campurile obligatorii
        if (empty($username) || empty($password)) {
            $error = 'Username-ul si parola sunt obligatorii!';

        // Validam rolul (doar user sau admin)
        } elseif (!in_array($role, ['user', 'admin'])) {
            $error = 'Rolul ales este invalid!';

        } else {
            // Verificam daca username-ul exista deja 
            $existing = $db->fetchOne(
                'SELECT id FROM users WHERE username = ?',
                [$username]
            );

            if ($existing) {
                $error = 'Username-ul este deja folosit!';
            } else {
                // Criptam parola folosind bcrypt
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                // Inseram utilizatorul in baza de date 
                $db->insert('users', [
                    'username' => $username,
                    'password' => $hashedPassword,
                    'role' => $role,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                // Inregistram actiunea in logs
                $db->log('add_user', "Utilizatorul adaugat: {$username}");

                $message = 'Utilizatorul a fost adaugat cu succes!';
            }
        }
    }

    // -- Stergere utilizator --
    if ($action === 'delete') {

        // Citim si validam ID-ul utilizatorului de sters
        $userId = intval($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            $error = 'ID utilizator invalid!';
        } else {
            // Cautam utilizatorul in baza de date
            $user = $db->fetchOne(
                'SELECT * FROM users WHERE id = ?',
                [$userId]
            );

            if (!$user) {
                $error = 'Utilizatorul nu a fost gasit!';

            // Nu permitem stergerea adminului principal    
            } elseif ($user['username'] === 'admin') {
                $error = 'Utilizatorul admin principal nu poate fi sters!';

            } else {
                // Stergem utilizatorul din baza de date
                $db->delete('users', 'id = ?', [$userId]);

                // Inregistram actiunea in logs
                $db->log('delete_user', "Utilizator sters: {$user['username']}");

                $message = 'Utilizatorul a fost sters cu succes!';
            }
        }
    }
}

// Obtinem lista tuturor utilizatorilor din baza de date
$users = $db->fetchAll(
    'SELECT * FROM users ORDER BY created_at DESC'
);
?>
<!DOCTYPE html>
<html lang="ro">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Utilizatori - <?php echo APP_NAME; ?></title>
        <link rel="stylesheet" href="../public/css/admin.css">
    </head>
    <body>

        <!-- Bara de navigare admin --> 
        <nav class="admin-nav">
            <div class="nav-brand"><?php echo APP_NAME; ?> - Admin</div>
            <ul class="nav-links">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="users.php" class="active">Utilizatori</a></li>
                <li><a href="logs.php">Jurnalul activitatii</a></li>
                <li><a href="../index.php">Inapoi la aplicatie</a></li>
                <li><a href="index.php?logout=1">Delogare</a></li>
            </ul>
        </nav>

        <!-- Continutul principal --> 
        <main class="admin-main">
            <h1>Gestionare Utilizatori</h1>

            <!-- Afisam mesajul de succes daca exista --> 
            <?php if (!empty($message)): ?>
                <div class="success-message"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Afisam eroarea daca exista --> 
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Formularul de adaugare utilizator nou --> 
            <section class="admin-section">
                <h2>Adauga utilizator nou</h2>

                <form method="POST" action="users.php">
                    <!-- Camp ascuns pentru actiune --> 
                    <input type="hidden" name="action" value="add">

                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text"
                                id="username"
                                name="username"
                                placeholder="Introduceti username-ul"
                                required>
                    </div>

                    <div class="form-group">
                        <label for="password">Parola:</label>
                        <input type="password"
                                id="password"
                                name="password"
                                placeholder="Introduceti parola"
                                required>
                    </div>

                    <div class="form-group">
                        <label for="role">Rol:</label>
                        <select id="role" name="role">
                            <option value="user">Utilizator</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-admin">Adauga utilizator</button>
                </form>
            </section>

            <!-- Tabelul cu toti utilizatorii --> 
            <section class="admin-section">
                <h2>Lista utilizatori (<?php echo count($users); ?>)</h2>

                <?php if (empty($users)): ?>
                    <p class="empty-message">Nu exista utilizatori inregistrati</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Rol</th>
                                <th>Data crearii</th>
                                <th>Actiuni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <!-- Afisam rolul cu stil diferit --> 
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo $user['role'] === 'admin' ? 'Administrator' : 'Utilizator'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['created_at']; ?></td>
                                    <td>
                                        <!-- Nu afisam butonul de stergere pentru adminul principal --> 
                                        <?php if ($user['username'] !== 'admin'): ?>
                                            <form method="POST" action="users.php"
                                                    onsubmit="return confirm('Esti sigur ca vrei sa stergi acest utilizator?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn-delete">Sterge</button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Admin principal - nu poate fi sters --> 
                                            <span class="protected-label">Protejat</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

        </main>

    </body>
</html>