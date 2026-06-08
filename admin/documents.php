<?php

// admin/documents.php
// Pagina de administrare a documentelor generate
// Suporta filtrare dupa sablon, status si data, cu paginare

require_once __DIR__ . '/../config.php';

// Verificam ca utilizatorul este autentificat si are rol de admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

// Citim parametrii de filtrare din URL
$filterTemplate = trim($_GET['template'] ?? '');
$filterStatus   = trim($_GET['status'] ?? '');
$filterDate     = trim($_GET['date'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 20;
$offset         = ($page - 1) * $perPage;

// Construim dinamic clauza WHERE in functie de filtrele active
$where  = [];
$params = [];

if (!empty($filterTemplate)) {
    $where[]  = 't.name = ?';
    $params[] = $filterTemplate;
}

if (!empty($filterStatus)) {
    $where[]  = 'd.status = ?';
    $params[] = $filterStatus;
}

if (!empty($filterDate)) {
    $where[]  = 'DATE(d.created_at) = ?';
    $params[] = $filterDate;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Numaram totalul de documente pentru a calcula numarul de pagini
$totalDocuments = $db->fetchOne(
    "SELECT COUNT(*) as total
     FROM documents d
     LEFT JOIN templates t ON d.template_id = t.id
     LEFT JOIN users u ON d.user_id = u.id
     {$whereClause}",
    $params
)['total'] ?? 0;

$totalPages = (int)ceil($totalDocuments / $perPage);

// Obtinem documentele pentru pagina curenta, ordonate descrescator dupa data
$documents = $db->fetchAll(
    "SELECT
        d.id,
        d.title,
        d.status,
        d.rows_count,
        d.html_path,
        d.pdf_path,
        d.created_at,
        d.updated_at,
        t.name  AS template_name,
        t.label AS template_label,
        u.username
     FROM documents d
     LEFT JOIN templates t ON d.template_id = t.id
     LEFT JOIN users u ON d.user_id = u.id
     {$whereClause}
     ORDER BY d.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// Listele de valori pentru dropdown-urile de filtrare
$templates = $db->fetchAll(
    'SELECT DISTINCT name, label FROM templates ORDER BY label ASC'
);

$statuses = ['draft', 'generated', 'exported'];

// Procesam stergerea unui document daca s-a primit un ID valid prin GET
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $db->delete('documents', 'id = ?', [$deleteId]);
    $db->log('admin', 'Document sters: ID ' . $deleteId, $_SESSION['user_id']);
    header('Location: ' . BASE_URL . '/admin/documents.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documente generate — <?php echo APP_NAME; ?></title>
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
            <li class="admin-nav-item active">
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
            <a href="<?php echo BASE_URL; ?>/admin/index.php?logout=1">Logout</a>
        </div>
    </aside>

    <!-- ===== Continut principal ===== -->
    <main class="admin-main">

        <div class="admin-topbar">
            <span class="admin-topbar-title">📄 Documente generate</span>
            <div class="admin-topbar-actions">
                <span style="font-size:13px; color:#666;">
                    Total: <strong><?php echo $totalDocuments; ?></strong> documente
                </span>
            </div>
        </div>

        <div class="admin-content">

            <!-- Filtre de cautare -->
            <div class="admin-card" style="margin-bottom:20px;">
                <div class="admin-card-header">
                    <span class="admin-card-title">🔍 Filtrare documente</span>
                </div>
                <div class="admin-card-body">
                    <form method="GET" action="">
                        <div class="admin-filters">

                            <select name="template" class="admin-select">
                                <option value="">Toate sabloanele</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?php echo htmlspecialchars($tpl['name']); ?>"
                                        <?php echo ($filterTemplate === $tpl['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tpl['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__none__" <?php echo ($filterTemplate === '__none__') ? 'selected' : ''; ?>>
                                    Fara sablon (N/A)
                                </option>
                            </select>

                            <select name="status" class="admin-select">
                                <option value="">Toate statusurile</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?php echo $s; ?>"
                                        <?php echo ($filterStatus === $s) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($s); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

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

            <!-- Tabel documente -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <span class="admin-card-title">Documente</span>
                    <span style="font-size:13px; color:#666;">
                        Pagina <?php echo $page; ?> din <?php echo max(1, $totalPages); ?>
                    </span>
                </div>
                <div class="admin-card-body" style="padding:0;">
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titlu</th>
                                    <th>Sablon</th>
                                    <th>Utilizator</th>
                                    <th>Status</th>
                                    <th>Randuri</th>
                                    <th>Fisiere</th>
                                    <th>Data generarii</th>
                                    <th>Actiuni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="9"
                                            style="text-align:center; padding:30px; color:#999;">
                                            Nu exista documente<?php echo !empty($whereClause) ? ' pentru filtrele selectate' : ''; ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td><?php echo $doc['id']; ?></td>
                                            <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                            <td>
                                                <?php if ($doc['template_label']): ?>
                                                    <span class="admin-badge info">
                                                        <?php echo htmlspecialchars($doc['template_label']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#999;">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($doc['username']): ?>
                                                    <strong><?php echo htmlspecialchars($doc['username']); ?></strong>
                                                <?php else: ?>
                                                    <span style="color:#999;">anonim</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="admin-badge <?php echo getStatusBadgeClass($doc['status']); ?>">
                                                    <?php echo htmlspecialchars($doc['status']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align:center;">
                                                <?php echo $doc['rows_count']; ?>
                                            </td>
                                            <td style="font-size:12px;">
                                                <?php if ($doc['html_path']): ?>
                                                    <a href="<?php echo BASE_URL . '/generated/html/' . htmlspecialchars($doc['html_path']); ?>"
                                                    target="_blank">HTML</a>
                                                <?php endif; ?>
                                                <?php if ($doc['pdf_path']): ?>
                                                    &nbsp;
                                                    <a href="<?php echo BASE_URL . '/generated/pdf/' . htmlspecialchars($doc['pdf_path']); ?>"
                                                    target="_blank">PDF</a>
                                                <?php endif; ?>
                                                <?php if (!$doc['html_path'] && !$doc['pdf_path']): ?>
                                                    <span style="color:#999;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap; font-size:12px;">
                                                <?php echo htmlspecialchars($doc['created_at']); ?>
                                            </td>
                                            <td>
                                                <a href="javascript:void(0)"
                                                    class="admin-btn danger small"
                                                    onclick="showConfirmModal(
                                                        'Stergi acest document?',
                                                        function() {
                                                            window.location='?delete_id=<?php echo $doc['id']; ?>';
                                                        }
                                                    )">
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
                        <a href="?page=<?php echo $page - 1; ?>&template=<?php echo urlencode($filterTemplate); ?>&status=<?php echo urlencode($filterStatus); ?>&date=<?php echo urlencode($filterDate); ?>">
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
                            <a href="?page=<?php echo $i; ?>&template=<?php echo urlencode($filterTemplate); ?>&status=<?php echo urlencode($filterStatus); ?>&date=<?php echo urlencode($filterDate); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&template=<?php echo urlencode($filterTemplate); ?>&status=<?php echo urlencode($filterStatus); ?>&date=<?php echo urlencode($filterDate); ?>">
                            &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

</div>

<?php
// Returneaza clasa CSS corespunzatoare statusului unui document
function getStatusBadgeClass(string $status): string {
    $map = [
        'draft'     => 'warning',
        'generated' => 'info',
        'exported'  => 'success',
    ];
    return $map[$status] ?? 'info';
}
?>

<!-- Modal de confirmare stergere -->
<div id="confirm-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;padding:2rem;max-width:400px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,.2);">
        <h3 id="confirm-title" style="font-size:1.1rem;margin-bottom:.8rem;color:#1a1a2e;">Confirmare</h3>
        <p id="confirm-message" style="color:#555;margin-bottom:1.5rem;font-size:.95rem;"></p>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
            <button id="confirm-cancel" style="padding:.5rem 1.2rem;border:1px solid #ddd;background:#fff;border-radius:4px;cursor:pointer;font-size:.9rem;">Anuleaza</button>
            <button id="confirm-ok" style="padding:.5rem 1.2rem;background:#dc3545;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.9rem;">Sterge</button>
        </div>
    </div>
</div>

</body>
</html>