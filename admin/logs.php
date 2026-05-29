<?php

// admin/logs.php - Pagina de istoric activitate
// Afiseaza logurile din tabela logs pentru admin
// Suporta filtrare dupa actiune, utilizator si data

require_once __DIR__ . '/../config.php';

// Verificam ca utilizatorul e autentificat si e admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/index.php?page=home');
    exit;
}

$db = Database::getInstance();

// --------------------------------------------------
// Filtre din GET
// --------------------------------------------------
$filterAction = trim($_GET['action'] ?? '');
$filterUser   = trim($_GET['user'] ?? '');
$filterDate   = trim($_GET['date'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20; // loguri per pagina
$offset       = ($page - 1) * $perPage;

// --------------------------------------------------
// Construim query-ul cu filtrele aplicate
// Folosim prepared statements - previne SQL Injection
// --------------------------------------------------
$where  = [];
$params = [];

if (!empty($filterAction)) {
    $where[]  = 'l.action = ?';
    $params[] = $filterAction;
}

if (!empty($filterUser)) {
    $where[]  = 'u.username LIKE ?';
    $params[] = '%' . $filterUser . '%';
}

if (!empty($filterDate)) {
    $where[]  = 'DATE(l.created_at) = ?';
    $params[] = $filterDate;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// --------------------------------------------------
// Numaram totalul de loguri pentru paginare
// --------------------------------------------------
$totalLogs = $db->fetchOne(
    "SELECT COUNT(*) as total
     FROM logs l
     LEFT JOIN users u ON l.user_id = u.id
     {$whereClause}",
    $params
)['total'] ?? 0;

$totalPages = (int)ceil($totalLogs / $perPage);

// --------------------------------------------------
// Obtinem logurile pentru pagina curenta
// --------------------------------------------------
$logs = $db->fetchAll(
    "SELECT 
        l.id,
        l.action,
        l.description,
        l.entity,
        l.entity_id,
        l.ip_address,
        l.created_at,
        u.username,
        u.email
     FROM logs l
     LEFT JOIN users u ON l.user_id = u.id
     {$whereClause}
     ORDER BY l.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// --------------------------------------------------
// Obtinem lista de actiuni distincte pentru filtru
// --------------------------------------------------
$actions = $db->fetchAll(
    'SELECT DISTINCT action FROM logs ORDER BY action ASC'
);

// --------------------------------------------------
// Stergere log (doar admin, doar GET cu confirmare)
// --------------------------------------------------
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $db->delete('logs', 'id = ?', [$deleteId]);
    $db->log('admin', 'Log sters: ID ' . $deleteId, $_SESSION['user_id']);
    header('Location: ' . BASE_URL . '/admin/logs.php');
    exit;
}

// --------------------------------------------------
// Stergere toate logurile (reset complet)
// --------------------------------------------------
if (isset($_GET['clear_all']) && $_GET['clear_all'] === '1') {
    $db->query('DELETE FROM logs');
    $db->log('admin', 'Toate logurile au fost sterse', $_SESSION['user_id']);
    header('Location: ' . BASE_URL . '/admin/logs.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Istoric activitate — <?php echo APP_NAME; ?></title>
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
            <li class="admin-nav-item">
                <a href="<?php echo BASE_URL; ?>/admin/index.php">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
            </li>
            <li class="admin-nav-item">
                <a href="<?php echo BASE_URL; ?>/admin/users.php">
                    <span class="nav-icon">👥</span> Utilizatori
                </a>
            </li>
            <li class="admin-nav-item active">
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
            <span class="admin-topbar-title">📋 Istoric activitate</span>
            <div class="admin-topbar-actions">
                <span style="font-size:13px; color:#666;">
                    Total: <strong><?php echo $totalLogs; ?></strong> loguri
                </span>
                <a href="?clear_all=1"
                   class="admin-btn danger small"
                   onclick="return confirm('Stergi TOATE logurile? Actiunea este ireversibila!')">
                    🗑 Sterge toate
                </a>
            </div>
        </div>

        <!-- Continut -->
        <div class="admin-content">

            <!-- Filtre -->
            <div class="admin-card" style="margin-bottom:20px;">
                <div class="admin-card-header">
                    <span class="admin-card-title">🔍 Filtrare loguri</span>
                </div>
                <div class="admin-card-body">
                    <form method="GET" action="">
                        <div class="admin-filters">

                            <!-- Filtru actiune -->
                            <select name="action" class="admin-select">
                                <option value="">Toate actiunile</option>
                                <?php foreach ($actions as $act): ?>
                                    <option value="<?php echo htmlspecialchars($act['action']); ?>"
                                        <?php echo ($filterAction === $act['action']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($act['action']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Filtru utilizator -->
                            <input type="text"
                                   name="user"
                                   class="admin-search-input"
                                   placeholder="Cauta utilizator..."
                                   value="<?php echo htmlspecialchars($filterUser); ?>">

                            <!-- Filtru data -->
                            <input type="date"
                                   name="date"
                                   class="admin-search-input"
                                   value="<?php echo htmlspecialchars($filterDate); ?>">

                            <button type="submit" class="admin-btn primary">
                                🔍 Filtreaza
                            </button>
                            <a href="?" class="admin-btn secondary">
                                ✕ Reseteaza
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel loguri -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <span class="admin-card-title">Loguri</span>
                    <span style="font-size:13px; color:#666;">
                        Pagina <?php echo $page; ?> din <?php echo max(1, $totalPages); ?>
                    </span>
                </div>
                <div class="admin-card-body" style="padding:0;">
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Actiune</th>
                                    <th>Descriere</th>
                                    <th>Utilizator</th>
                                    <th>IP</th>
                                    <th>Data</th>
                                    <th>Actiuni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="7"
                                            style="text-align:center; padding:30px; color:#999;">
                                            Nu exista loguri<?php echo !empty($whereClause) ? ' pentru filtrele selectate' : ''; ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo $log['id']; ?></td>
                                            <td>
                                                <span class="admin-badge <?php echo getActionBadgeClass($log['action']); ?>">
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['description'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($log['username']): ?>
                                                    <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                                <?php else: ?>
                                                    <span style="color:#999;">anonim</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-family:monospace; font-size:12px;">
                                                <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                                            </td>
                                            <td style="white-space:nowrap; font-size:12px;">
                                                <?php echo htmlspecialchars($log['created_at']); ?>
                                            </td>
                                            <td>
                                                <a href="?delete_id=<?php echo $log['id']; ?>"
                                                   class="admin-btn danger small"
                                                   onclick="return confirm('Stergi acest log?')">
                                                    ✕
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Paginare -->
            <?php if ($totalPages > 1): ?>
                <div class="admin-pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&action=<?php echo urlencode($filterAction); ?>&user=<?php echo urlencode($filterUser); ?>&date=<?php echo urlencode($filterDate); ?>">
                            &laquo;
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&action=<?php echo urlencode($filterAction); ?>&user=<?php echo urlencode($filterUser); ?>&date=<?php echo urlencode($filterDate); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&action=<?php echo urlencode($filterAction); ?>&user=<?php echo urlencode($filterUser); ?>&date=<?php echo urlencode($filterDate); ?>">
                            &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div><!-- /.admin-content -->
    </main>
</div>

<?php
// --------------------------------------------------
// Functie helper: returneaza clasa badge pentru actiune
// --------------------------------------------------
function getActionBadgeClass(string $action): string {
    $map = [
        'login'         => 'success',
        'logout'        => 'info',
        'generate'      => 'info',
        'export'        => 'success',
        'import'        => 'warning',
        'delete'        => 'danger',
        'admin'         => 'admin',
        'save_schema'   => 'info',
        'update_schema' => 'warning',
        'delete_schema' => 'danger',
    ];
    return $map[$action] ?? 'info';
}
?>
</body>
</html>
