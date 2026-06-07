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
                $email = trim(htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'));

                $db->insert('users', [
                    'username' => $username,
                    'email'    => $email,
                    'password' => $hashedPassword,
                    'role'     => $role
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
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/main.css">
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
        <script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>
    </head>
    <body>
    <div class="admin-wrapper">

        <aside class="admin-sidebar">
            <div class="admin-sidebar-logo">
                Do<span>Gen</span>
            </div>
            <ul class="admin-nav">
                <li class="admin-nav-section-title">Administrare</li>
                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/index.php">
                        <span class="nav-icon">📊</span> Dashboard
                    </a>
                </li>
                <li class="admin-nav-item active">
                    <a href="<?php echo BASE_URL; ?>/admin/users.php">
                        <span class="nav-icon">👥</span> Utilizatori
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/documents.php">
                        <span class="nav-icon">📄</span> Documente generate
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/logs.php">
                        <span class="nav-icon">📋</span> Istoric activitate
                    </a>
                </li>
                <li class="admin-nav-section-title">Aplicatie</li>
                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/">
                        <span class="nav-icon">🏠</span> Inapoi la aplicatie
                    </a>
                </li>
            </ul>
            <div class="admin-sidebar-footer">
                <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong>
            </div>
        </aside>

        <main class="admin-main">
            <div class="admin-topbar">
                <span class="admin-topbar-title">👥 Gestionare Utilizatori</span>
                <div class="admin-topbar-actions">
                    <a href="<?php echo BASE_URL; ?>/" class="admin-btn secondary">Inapoi la aplicatie</a>
                </div>
            </div>
            <div class="admin-content">
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
            <section class="admin-section admin-card">
                <div class="admin-card-header">
                    <span class="admin-card-title">Adauga utilizator nou</span>
                </div>
                <div class="admin-card-body">
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
                        <label for="email">Email:</label>
                        <input type="email"
                            id="email"
                            name="email"
                            placeholder="Introduceti adresa email"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="role">Rol:</label>
                        <select id="role" name="role">
                            <option value="user">Utilizator</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <button type="submit" class="admin-btn primary">Adauga utilizator</button>
                </form>
                </div>
            </section>

            <!-- Tabelul cu toti utilizatorii --> 
            <section class="admin-section admin-card">
                <div class="admin-card-header">
                    <span class="admin-card-title">Lista utilizatori (<?php echo count($users); ?>)</span>
                </div>
                <div class="admin-card-body">

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
                                        <span class="admin-badge <?php echo $user['role'] === 'admin' ? 'admin' : 'info'; ?>">
                                            <?php echo $user['role'] === 'admin' ? 'Administrator' : 'Utilizator'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['created_at']; ?></td>
                                    <td>
                                        <!-- Nu afisam butonul de stergere pentru adminul principal --> 
                                        <?php if ($user['username'] !== 'admin'): ?>
                                            <form id="delete-form-<?php echo $user['id']; ?>" method="POST" action="users.php">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">

                                                <button type="button"
                                                        class="btn-delete"
                                                        onclick="showConfirmModal(
                                                            'Esti sigur ca vrei sa stergi acest utilizator?',
                                                            function() {
                                                                document.getElementById('delete-form-<?php echo $user['id']; ?>').submit();
                                                            }
                                                        )">
                                                    Sterge
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Admin principal - nu poate fi sters --> 
                                            <span class="admin-badge" style="background-color:#f1f3f5;color:#495057;">Protejat</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                </div>
            </section>

        </div>
    </main>

</div>

<!-- Modal confirmare -->
<div id="confirm-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;padding:2rem;max-width:400px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,.2);">
        <h3 id="confirm-title" style="font-size:1.1rem;margin-bottom:.8rem;color:#1a1a2e;">Confirmare</h3>
        <p id="confirm-message" style="color:#555;margin-bottom:1.5rem;font-size:.95rem;"></p>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
            <button id="confirm-cancel" style="padding:.5rem 1.2rem;border:1px solid #ddd;background:#fff;border-radius:4px;cursor:pointer;font-size:.9rem;">Anulează</button>
            <button id="confirm-ok" style="padding:.5rem 1.2rem;background:#dc3545;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.9rem;">Șterge</button>
        </div>
    </div>
</div>

    </body>
</html>