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
        if ($password === ADMIN_PASSWORD) {

            // Parola corecta - marcam sesiunea ca autentificata
            $_SESSION['admin'] = true;

            // Redirectionam catre panoul admin
            header('Location: index.php');
            exit;

        } else {
            // Parola gresita - setam mesajul de eroare
            $loginError = 'Parola incorecta!';
        }
    }

    // Afisam formularul de login daca nu e autentificat
    ?>
    <!DOCTYPE html>
    <html lang="ro">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Login - <?php echo APP_NAME; ?></title>
            <link rel="stylesheet" href="../public/css/admin.css">
        </head>
        <body>
            <div class="login-container">
                <h1>Panou Administrare</h1>
                <h2><?php echo APP_NAME; ?></h2>

                <?php if (isset($loginError)): ?>
                    <!-- Afisam eroarea daca parola e gresita -->
                    <div class="error-message"><?php echo $loginError; ?></div>
                <?php endif; ?>

                <!-- Formularul de autentificare --> 
                <form method="POST" action="index.php">
                    <div class="form-group">
                        <label for="password">Parola administrator:</label>
                        <input type="password"
                                id="password"
                                name="password"
                                placeholder="Introduceti parola"
                                required>
                    </div>
                    <button type="submit">Autentificare</button>
                </form>
            </div>
        </body>
    </html>
    <?php
    // Oprim executia - nu afisam panoul admin
    exit;
}

// Verificam daca s-a cerut delogarea
if (isset($_GET['logout'])) {
    // Distrugem sesiunea
    session_destroy();

    // Redirectionam catre login
    header('Location: index.php');
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
        <link rel="stylesheet" href="../public/css/admin.css">
    </head>
    <body>

        <!-- Bara de navigare admin --> 
        <nav class="admin-nav">
            <div class="nav-brand"><?php echo APP_NAME; ?> - Admin</div>
            <ul class="nav-links">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="users.php">Utilizatori</a></li>
                <li><a href="logs.php">Jurnalul activitatii</a></li>
                <li><a href="../index.php">Inapoi la aplicatie</a></li>
                <li><a href="index.php?logout=1">Delogare</a></li>
            </ul>
        </nav>

        <!-- Continutul principal al dashboard-ului --> 
        <main class="admin-main">
            <h1>Dashboard</h1>

            <!-- Carduri cu statistici -->
            <div class="stats-grid">

                <!-- Card: total sabloane -->
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalTemplates; ?></div>
                    <div class="stat-label">Sabloane</div>
                </div>

                <!-- Card: total documente --> 
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalDocuments; ?></div>
                    <div class="stat-label">Documente generate</div>
                </div>

                <!-- Card: total scheme --> 
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalSchemas; ?></div>
                    <div class="stat-label">Scheme de date</div>
                </div>

                <!-- Card: total utilizatori --> 
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalUsers; ?></div>
                    <div class="stat-label">Utilizatori</div>
                </div>

            </div>

            <!-- Sectiunea: ultimele documente generate --> 
            <section class="admin-section">
                <h2>Ultimele documente generate</h2>

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
                <a href="logs.php" class="admin-link">Vezi toate activitatile</a>
            </section>

            <!-- Sectiunea: activitate recenta --> 
            <section class="admin-section">
                <h2>Activitate recenta</h2>

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
                <a href="logs.php" class="admin-link">Vezi toate actiunile</a>
            </section>

        </main>

    </body>
</html>