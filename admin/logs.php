<?php

// admin/logs.php
// Pagina de istoric activitate
// Suporta filtrare dupa actiune, utilizator si data, cu paginare

require_once __DIR__ . '/../config.php';

// Verificam ca utilizatorul este autentificat si are rol de admin
$auth = new AuthService();
if (!$auth->isAdmin()) {
    header('Location: index.php');
    exit;
}
$currentUsername = $auth->getUsername();

$db = Database::getInstance();

// Citim parametrii de filtrare din URL
$filterAction = trim($_GET['action'] ?? '');
$filterUser   = trim($_GET['user'] ?? '');
$filterDate   = trim($_GET['date'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

// Construim dinamic clauza WHERE in functie de filtrele active.
// Excludem din start actiunile interne (admin, delete_log).
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

$baseWhere   = "l.action NOT IN ('admin', 'delete_log')";
$whereClause = !empty($where)
    ? 'WHERE ' . $baseWhere . ' AND ' . implode(' AND ', $where)
    : 'WHERE ' . $baseWhere;

// Numaram totalul de loguri pentru a calcula numarul de pagini
$totalLogs = $db->fetchOne(
    "SELECT COUNT(*) as total
     FROM logs l
     LEFT JOIN users u ON l.user_id = u.id
     {$whereClause}",
    $params
)['total'] ?? 0;

$totalPages = (int)ceil($totalLogs / $perPage);

// Obtinem logurile pentru pagina curenta, ordonate descrescator dupa data
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

// Lista de actiuni distincte pentru dropdown-ul de filtrare
$actions = $db->fetchAll(
    "SELECT DISTINCT action FROM logs
     WHERE action NOT IN ('admin', 'delete_log')
     ORDER BY action ASC"
);

// Procesam stergerea unui log individual daca s-a primit un ID valid prin GET
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $db->delete('logs', 'id = ?', [$deleteId]);
    $db->log('delete_log', 'Log sters: ID ' . $deleteId, $auth->getUserId());
    header('Location: ' . BASE_URL . '/admin/logs.php');
    exit;
}

// Stergere completa a tuturor logurilor
if (isset($_GET['clear_all']) && $_GET['clear_all'] === '1') {
    $db->query('DELETE FROM logs');
    $db->log('delete_log', 'Toate logurile au fost sterse', $auth->getUserId());
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
    <script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>
</head>
<body>
<div class="admin-wrapper">

    <!-- ===== Sidebar ===== -->
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
            <li class="admin-nav-item">
                <a href="<?php echo BASE_URL; ?>/admin/documents.php">
                    <span class="nav-icon">📄</span> Documente generate
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
            <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
            <a href="<?php echo BASE_URL; ?>/admin/index.php?logout=1">Logout</a>
        </div>
    </aside>

    <!-- ===== Continut principal ===== -->
    <main class="admin-main">

        <div class="admin-topbar">
            <span class="admin-topbar-title">📋 Istoric activitate</span>
            <div class="admin-topbar-actions">
                <span style="font-size:13px; color:#666;">
                    Total: <strong><?php echo $totalLogs; ?></strong> loguri
                </span>
                <a href="javascript:void(0)"
                    class="admin-btn danger small"
                    onclick="showConfirmModal('Stergi TOATE logurile? Actiunea este ireversibila!', function(){ window.location='?clear_all=1'; })">
                    🗑 Sterge toate
                </a>
            </div>
        </div>

        <div class="admin-content">

            <h1>Istoric activitate</h1>

            <!-- Filtre de cautare -->
            <div class="admin-card" style="margin-bottom:20px;">
                <div class="admin-card-header">
                    <span class="admin-card-title">🔍 Filtrare loguri</span>
                </div>
                <div class="admin-card-body">
                    <form method="GET">
                        <div class="admin-filters">

                            <select name="action" class="admin-select">
                                <option value="">Toate actiunile</option>
                                <?php foreach ($actions as $act): ?>
                                    <option value="<?php echo htmlspecialchars($act['action']); ?>"
                                        <?php echo ($filterAction === $act['action']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($act['action']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="text"
                                   name="user"
                                   class="admin-search-input"
                                   placeholder="Cauta utilizator..."
                                   value="<?php echo htmlspecialchars($filterUser); ?>">

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
                                            Nu exista loguri<?php echo !empty($filterAction) || !empty($filterUser) || !empty($filterDate) ? ' pentru filtrele selectate' : ''; ?>.
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
                                                    <span class="admin-badge <?php echo $log['username'] === 'admin' ? 'admin' : 'info'; ?>">
                                                        <?php echo htmlspecialchars($log['username']); ?>
                                                    </span>
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
                                                <a href="javascript:void(0)"
                                                    class="admin-btn danger small"
                                                    onclick="showConfirmModal('Stergi acest log?', function(){ window.location='?delete_id=<?php echo $log['id']; ?>'; })">
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

            <!-- Paginare: afisata doar daca exista mai mult de o pagina -->
            <?php if ($totalPages > 1): ?>
                <div class="admin-pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&action=<?php echo urlencode($filterAction); ?>&user=<?php echo urlencode($filterUser); ?>&date=<?php echo urlencode($filterDate); ?>">
                            &laquo;
                        </a>
                    <?php endif; ?>

                    <?php
                    // Afisam maxim 5 pagini centrate in jurul paginii curente
                    for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++):
                    ?>
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

        </div>
    </main>

</div>

<?php
// Returneaza clasa CSS corespunzatoare tipului de actiune dintr-un log
function getActionBadgeClass(string $action): string {
    $map = [
        'login'             => 'success',
        'logout'            => 'info',
        'generate'          => 'info',
        'export'            => 'success',
        'import'            => 'warning',
        'delete'            => 'danger',
        'save_schema'       => 'info',
        'update_schema'     => 'warning',
        'delete_schema'     => 'danger',
        'delete_log'        => 'danger',
        'add_user'          => 'success',
        'delete_user'       => 'danger',
        'generate_document' => 'info',
        'delete_template'   => 'danger',
    ];
    return $map[$action] ?? 'info';
}
?>

<!-- Modal de confirmare stergere -->
<div id="confirm-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;padding:2rem;max-width:400px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,.2);">
        <h2 id="confirm-title" style="font-size:1.1rem;margin-bottom:.8rem;color:#1a1a2e;">Confirmare</h2>
        <p id="confirm-message" style="color:#555;margin-bottom:1.5rem;font-size:.95rem;"></p>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
            <button id="confirm-cancel" style="padding:.5rem 1.2rem;border:1px solid #ddd;background:#fff;border-radius:4px;cursor:pointer;font-size:.9rem;">Anuleaza</button>
            <button id="confirm-ok" style="padding:.5rem 1.2rem;background:#dc3545;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.9rem;">Sterge</button>
        </div>
    </div>
</div>

</body>
</html>