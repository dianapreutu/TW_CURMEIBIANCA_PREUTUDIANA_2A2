<?php

// views/admin.php - Dashboard panou de administrare
// Afiseaza statistici generale ale aplicatiei
// Acces restrictionat doar pentru utilizatorii admin
// Depinde de: lib/Database.php, config.php
// Stilizat cu: public/css/main.css, public/css/admin.css

require_once __DIR__ . '/../config.php';

// Verificam autentificarea si rolul de admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/index.php?page=home');
    exit;
}

$db = Database::getInstance();

// Obtinem statisticile pentru cardurile de dashboard

// Total utilizatori
$totalUsers = $db->fetchOne(
    'SELECT COUNT(*) as total FROM users'
)['total'] ?? 0;

// Total documente generate
$totalDocuments = $db->fetchOne(
    'SELECT COUNT(*) as total FROM documents'
)['total'] ?? 0;

// Total exporturi
$totalExports = $db->fetchOne(
    'SELECT COUNT(*) as total FROM exports'
)['total'] ?? 0;

// Total scheme salvate
$totalSchemas = $db->fetchOne(
    'SELECT COUNT(*) as total FROM schemas'
)['total'] ?? 0;

// Total importuri CSV
$totalCsvImports = $db->fetchOne(
    'SELECT COUNT(*) as total FROM csv_imports'
)['total'] ?? 0;

// Total loguri
$totalLogs = $db->fetchOne(
    'SELECT COUNT(*) as total FROM logs'
)['total'] ?? 0;

// --------------------------------------------------
// Ultimele 5 documente generate (activitate recenta)
// --------------------------------------------------
$recentDocuments = $db->fetchAll(
    'SELECT d.title, d.status, d.created_at, u.username
     FROM documents d
     LEFT JOIN users u ON d.user_id = u.id
     ORDER BY d.created_at DESC
     LIMIT 5'
);


// Ultimele 5 loguri (activitate recenta)
$recentLogs = $db->fetchAll(
    'SELECT l.action, l.description, l.created_at, u.username
     FROM logs l
     LEFT JOIN users u ON l.user_id = u.id
     ORDER BY l.created_at DESC
     LIMIT 5'
);

// --------------------------------------------------
// Distributia exporturilor pe formate
// --------------------------------------------------
$exportsByFormat = $db->fetchAll(
    'SELECT format, COUNT(*) as total
     FROM exports
     GROUP BY format
     ORDER BY total DESC'
);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrare — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/main.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
</head>
<body>

<div class="admin-wrapper">

    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="admin-sidebar-logo">
            Do<span>Gen</span>
        </div>
        <ul class="admin-nav">
            <li class="admin-nav-section-title">Administrare</li>
            <li class="admin-nav-item active">
                <a href="<?php echo BASE_URL; ?>/index.php?page=admin">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
            </li>
            <li class="admin-nav-item">
                <a href="<?php echo BASE_URL; ?>/admin/users.php">
                    <span class="nav-icon">👥</span> Utilizatori
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
            <a href="<?php echo BASE_URL; ?>/index.php?action=logout">Logout</a>
        </div>
    </aside>

    <!-- Continut principal -->
    <main class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
            <span class="admin-topbar-title">📊 Dashboard</span>
            <div class="admin-topbar-actions">
                <span style="font-size:13px; color:#666;">
                    <?php echo date('d.m.Y H:i'); ?>
                </span>
            </div>
        </div>

        <!-- Continut -->
        <div class="admin-content">

            <!-- Carduri statistici -->
            <div class="admin-stats-grid">

                <div class="admin-stat-card info">
                    <div class="admin-stat-icon">👥</div>
                    <div class="admin-stat-info">
                        <div class="admin-stat-value"><?php echo $totalUsers; ?></div>
                        <div class="admin-stat-label">Utilizatori</div>
                    </div>
                </div>

                <div class="admin-stat-card success">
                    <div class="admin-stat-icon">📄</div>
                    <div class="admin-stat-info">
                        <div class="admin-stat-value"><?php echo $totalDocuments; ?></div>
                        <div class="admin-stat-label">Documente generate</div>
                    </div>
                </div>

                <div class="admin-stat-card warning">
                    <div class="admin-stat-icon">📤</div>
                    <div class="admin-stat-info">
                        <div class="admin-stat-value"><?php echo $totalExports; ?></div>
                        <div class="admin-stat-label">Exporturi</div>
                    </div>
                </div>

                <div class="admin-stat-card info">
                    <div class="admin-stat-icon">🗂</div>
                    <div class="admin-stat-info">
                        <div class="admin-stat-value"><?php echo $totalSchemas; ?></div>
                        <div class="admin-stat-label">Scheme salvate</div>
                    </div>
                </div>

                <div class="admin-stat-card warning">
                    <div class="admin-stat-icon">📥</div>
                    <div class="admin-stat-info">
                        <div class="admin-stat-value"><?php echo $totalCsvImports; ?></div>
                        <div class="admin-stat-label">Importuri CSV</div>
                    </div>
                </div>

                <div class="admin-stat-card danger">
                    <div class="admin-stat-icon">📋</div>
                    <div class="admin-stat-info">
                        <div class="admin-stat-value"><?php echo $totalLogs; ?></div>
                        <div class="admin-stat-label">Loguri activitate</div>
                    </div>
                </div>

            </div><!-- /.admin-stats-grid -->

            <!-- Randuri cu tabele de activitate recenta -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">

                <!-- Documente recente -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <span class="admin-card-title">📄 Documente recente</span>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=documents"
                           style="font-size:13px; color:#3498db;">
                            Vezi toate →
                        </a>
                    </div>
                    <div class="admin-card-body" style="padding:0;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Titlu</th>
                                    <th>Utilizator</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentDocuments)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; color:#999; padding:20px;">
                                            Niciun document inca.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentDocuments as $doc): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                            <td><?php echo htmlspecialchars($doc['username'] ?? 'anonim'); ?></td>
                                            <td>
                                                <span class="admin-badge <?php echo getStatusClass($doc['status']); ?>">
                                                    <?php echo htmlspecialchars($doc['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Activitate recenta (loguri) -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <span class="admin-card-title">📋 Activitate recenta</span>
                        <a href="<?php echo BASE_URL; ?>/admin/logs.php"
                           style="font-size:13px; color:#3498db;">
                            Vezi toate →
                        </a>
                    </div>
                    <div class="admin-card-body" style="padding:0;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Actiune</th>
                                    <th>Utilizator</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentLogs)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; color:#999; padding:20px;">
                                            Niciun log inca.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <tr>
                                            <td>
                                                <span class="admin-badge <?php echo getActionClass($log['action']); ?>">
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['username'] ?? 'anonim'); ?></td>
                                            <td style="font-size:12px; white-space:nowrap;">
                                                <?php echo htmlspecialchars($log['created_at']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Exporturi pe formate -->
            <?php if (!empty($exportsByFormat)): ?>
            <div class="admin-card" style="margin-top:24px;">
                <div class="admin-card-header">
                    <span class="admin-card-title">📤 Exporturi pe formate</span>
                </div>
                <div class="admin-card-body">
                    <div style="display:flex; gap:16px; flex-wrap:wrap;">
                        <?php foreach ($exportsByFormat as $exp): ?>
                            <div class="admin-stat-card info" style="min-width:150px;">
                                <div class="admin-stat-info">
                                    <div class="admin-stat-value"><?php echo $exp['total']; ?></div>
                                    <div class="admin-stat-label">
                                        <?php echo strtoupper(htmlspecialchars($exp['format'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.admin-content -->
    </main>
</div><!-- /.admin-wrapper -->

<?php
function getStatusClass(string $status): string {
    $classes = [
        'draft'     => 'warning',
        'generated' => 'info',
        'exported'  => 'success'
    ];
    return $classes[$status] ?? 'info';
}

function getActionClass(string $action): string {
    $classes = [
        'login'         => 'success',
        'logout'        => 'info',
        'generate'      => 'info',
        'export'        => 'success',
        'import'        => 'warning',
        'delete'        => 'danger',
        'admin'         => 'admin',
    ];
    return $classes[$action] ?? 'info';
}
?>

</body>
</html>
