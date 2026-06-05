<?php

// ==================================================
// admin/index.php - Panoul de administrare
// Aceasta pagina este punctul de intrare in modulul admin
// Afiseaza statistici generale si optiuni de administrare
// ==================================================

// Includem configurarile globale
require_once '../config.php';

// Verificam daca utilizatorul este autentificat ca admin
// Daca nu, il redirectionam catre pagina de login
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {

    // Verificam daca s-a trimis formularul de login 
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Citim parola trimisa din formular
        $password = $_POST['password'] ?? '';

        // Verificam parola cu cea din config.php
       $username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === 'admin' && $password === ADMIN_PASSWORD) {
    $_SESSION['admin'] = true;
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['username'] = 'admin';

    header('Location: index.php');
    exit;
}

$db = Database::getInstance();
$user = $db->fetchOne(
    'SELECT * FROM users WHERE username = ? OR email = ?',
    [$username, $username]
);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['admin'] = ($user['role'] === 'admin');

    header('Location: ' . BASE_URL . '/index.php?page=home');
    exit;
}

$loginError = 'Utilizator sau parola incorecta!';
    }

    // Afisam formularul de login daca nu e autentificat
    ?>
    <!DOCTYPE html>
    <html lang="ro">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Login - <?php echo APP_NAME; ?></title>
            <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/main.css">
            <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
        </head>
        <body>
            <!-- Bara de navigare principala -->
            <nav class="main-nav">
                <a href="<?php echo BASE_URL; ?>/index.php?page=home" class="nav-brand" title="Mergi la Acasa">
                    <span class="brand-icon">📄</span>
                    <?php echo APP_NAME; ?>
                </a>
                <ul class="nav-links">
                    <li><a href="<?php echo BASE_URL; ?>/index.php?page=home">Acasa</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/index.php?page=editor">Editor Sabloane</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/index.php?page=generator">Generator Date</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/index.php?page=documents">Documentele Mele</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Admin</a></li>
                </ul>
            </nav>

            <div class="login-container">
                <div class="login-card">
                    <h1>Panou Administrare</h1>
                    <h2><?php echo APP_NAME; ?></h2>

                    <?php if (isset($loginError)): ?>
                        <!-- Afisam eroarea daca parola e gresita -->
                        <div class="error-message"><?php echo $loginError; ?></div>
                    <?php endif; ?>

                    <!-- Formularul de autentificare --> 
                    <form method="POST" action="index.php">

    <div class="form-group">
        <label for="username">Utilizator sau email:</label>
        <input type="text"
               id="username"
               name="username"
               required>
    </div>

          <div class="form-group">
              <label for="password">Introduceti parola:</label>
        <input type="password"
               id="password"
               name="password"
               required>
    </div>

                         <button type="submit">Autentificare</button>

                      </form>
                </div>
            </div>
        </body>
    </html>
    <?php
    // Oprim executia - nu afisam panoul admin
    exit;
}

// Verificam daca s-a cerut delogarea
if (isset($_GET['logout'])) {
     $_SESSION = [];
    // Distrugem sesiunea
    session_destroy();

    // Redirectionam catre login
    header('Location: ' . BASE_URL . '/index.php?page=home');
    exit;
}

// Obtinem instanta bazei de date
$db = Database::getInstance();

// Obtinem statisticile pentru dashboard
// Numarul total de sabloane
$totalTemplates = $db->fetchOne('SELECT COUNT(*) as count FROM templates')['count'];

// Numarul total de documente generate
$totalDocuments = $db->fetchOne('SELECT COUNT(*) as count FROM documents')['count'];

// Numarul total de scheme salvate
$totalSchemas = $db->fetchOne('SELECT COUNT(*) as count FROM schemas')['count'];

// Numarul total de utilizatori
$totalUsers = $db->fetchOne('SELECT COUNT(*) as count FROM users')['count'];

// Ultimele 5 actiuni din log
$recentLogs = $db->fetchAll(
    'SELECT * FROM logs ORDER BY created_at DESC LIMIT 5'
);

// Ultimele 5 documente generate
$recentDocuments = $db->fetchAll(
    'SELECT d.*, t.name as template_name
    FROM documents d
    LEFT JOIN templates t ON d.template_id = t.id
    ORDER BY d.created_at DESC
    LIMIT 5'
);
?>
<!DOCTYPE html>
<html lang="ro">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/main.css">
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
    </head>
    <body>
    <div class="admin-wrapper">

        <aside class="admin-sidebar">
            <div class="admin-sidebar-logo">
                Do<span>Gen</span>
            </div>
            <ul class="admin-nav">
                <li class="admin-nav-section-title">Administrare</li>
                <li class="admin-nav-item active">
                    <a href="<?php echo BASE_URL; ?>/admin/index.php">
                        <span class="nav-icon">📊</span> Dashboard
                    </a>
                </li>
                <li class="admin-nav-item">
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
                <span class="admin-topbar-title">📊 Dashboard</span>
                <div class="admin-topbar-actions">
                    <a href="<?php echo BASE_URL; ?>/" class="admin-btn secondary">Inapoi la aplicatie</a>
                    <a href="<?php echo BASE_URL; ?>/admin/index.php?logout=1" class="admin-btn secondary" style="font-weight: bold; text-decoration: none;">Delogare</a>
                </div>
            </div>
            <div class="admin-content">
                <h1>Dashboard</h1>

                <!-- Carduri cu statistici -->
                <div class="admin-stats-grid">

                    <!-- Card: total sabloane -->
                    <div class="admin-stat-card info">
                        <div class="admin-stat-value"><?php echo $totalTemplates; ?></div>
                        <div class="admin-stat-label">Sabloane</div>
                    </div>

                    <!-- Card: total documente --> 
                    <div class="admin-stat-card info">
                        <div class="admin-stat-value"><?php echo $totalDocuments; ?></div>
                        <div class="admin-stat-label">Documente generate</div>
                    </div>

                    <!-- Card: total scheme --> 
                    <div class="admin-stat-card info">
                        <div class="admin-stat-value"><?php echo $totalSchemas; ?></div>
                        <div class="admin-stat-label">Scheme de date</div>
                    </div>

                    <!-- Card: total utilizatori --> 
                    <div class="admin-stat-card info">
                        <div class="admin-stat-value"><?php echo $totalUsers; ?></div>
                        <div class="admin-stat-label">Utilizatori</div>
                    </div>

                </div>

            <!-- Sectiunea: ultimele documente generate --> 
            <section class="admin-section admin-card">
                <div class="admin-card-header">
                    <span class="admin-card-title">Ultimele documente generate</span>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($recentDocuments)): ?>
                        <p class="empty-message">Nu exista documente generate inca.</p>
                    <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nume document</th>
                                <th>Sablon folosit</th>
                                <th>Tip export</th>
                                <th>Data generarii</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDocuments as $doc): ?>
                                <tr>
                                    <td><?php echo $doc['id']; ?></td>
                                    <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                    <td><?php echo htmlspecialchars($doc['template_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($doc['status']); ?></td>
                                    <td><?php echo $doc['created_at']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                    <!-- Link catre pagina completa de logs --> 
                    <a href="<?php echo BASE_URL; ?>/admin/documents.php" class="admin-link">Vezi toate documentele generate</a>
                </div>
            </section>

            <!-- Sectiunea: activitate recenta --> 
            <section class="admin-section admin-card">
                <div class="admin-card-header">
                    <span class="admin-card-title">Activitate recenta</span>
                </div>
                <div class="admin-card-body">

                <?php if (empty($recentLogs)): ?>
                    <p class="empty-message">Nu exista activitate inregistrata</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Actiune</th>
                                <th>Detalii</th>
                                <th>Adresa IP</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                                    <td><?php echo $log['created_at']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                    <!-- Link catre pagina completa de logs --> 
                    <a href="<?php echo BASE_URL; ?>/admin/logs.php" class="admin-link">Vezi toate actiunile</a>
                </div>
            </section>

        </div>
    </main>

</div>

    </body>
</html>