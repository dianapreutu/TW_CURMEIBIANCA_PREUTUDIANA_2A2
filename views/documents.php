<?php

// views/documents.php - Lista documentelor generate
// Afiseaza toate documentele generate de utilizatorul logat
// Permite stergerea si accesul la previzualizare
// Depinde de: lib/Database.php, config.php
// Stilizat cu: public/css/main.css
// Logica JS: public/js/app.js

require_once __DIR__ . '/../config.php';

// Verificam autentificarea
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php?page=home');
    exit;
}

$db     = Database::getInstance();
$userId = $_SESSION['user_id'];

// Paginare
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Filtru status
$filterStatus = trim($_GET['status'] ?? '');
$whereStatus  = $filterStatus ? 'AND d.status = ?' : '';
$params       = $filterStatus ? [$userId, $filterStatus] : [$userId];

// Total documente pentru paginare
$total = $db->fetchOne(
    "SELECT COUNT(*) as total FROM documents d
     WHERE d.user_id = ? {$whereStatus}",
    $params
)['total'] ?? 0;

$totalPages = (int)ceil($total / $perPage);

// Obtinem documentele
$paramsWithLimit = array_merge($params, [$perPage, $offset]);
$documents = $db->fetchAll(
    "SELECT d.*,
            t.label as template_label,
            s.name  as schema_name
     FROM documents d
     LEFT JOIN templates t ON d.template_id = t.id
     LEFT JOIN schemas   s ON d.schema_id   = s.id
     WHERE d.user_id = ? {$whereStatus}
     ORDER BY d.created_at DESC
     LIMIT ? OFFSET ?",
    $paramsWithLimit
);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentele mele — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/main.css">
</head>
<body>

<div class="page-wrapper">

    <!-- Header -->
    <div class="page-header">
        <h1>📄 Documentele mele</h1>
        <a href="<?php echo BASE_URL; ?>/index.php?page=generator"
           class="btn btn-primary">
            + Document nou
        </a>
    </div>

    <!-- Mesaj status -->
    <div id="documents-message" class="alert" style="display:none;"></div>

    <!-- Filtre -->
    <div class="filters-bar">
        <form method="GET" action="">
            <div class="filters-inner">
                <select name="status" class="select-input" onchange="this.form.submit()">
                    <option value="">Toate statusurile</option>
                    <option value="draft"
                        <?php echo $filterStatus === 'draft' ? 'selected' : ''; ?>>
                        Draft
                    </option>
                    <option value="generated"
                        <?php echo $filterStatus === 'generated' ? 'selected' : ''; ?>>
                        Generat
                    </option>
                    <option value="exported"
                        <?php echo $filterStatus === 'exported' ? 'selected' : ''; ?>>
                        Exportat
                    </option>
                </select>
                <?php if ($filterStatus): ?>
                    <a href="?" class="btn btn-secondary btn-small">✕ Reseteaza</a>
                <?php endif; ?>
            </div>
        </form>
        <span class="results-count">
            <?php echo $total; ?> document<?php echo $total !== 1 ? 'e' : ''; ?>
        </span>
    </div>

    <!-- Tabel documente -->
    <?php if (empty($documents)): ?>
        <div class="empty-state">
            <p>📭 Nu ai niciun document generat inca.</p>
            <a href="<?php echo BASE_URL; ?>/index.php?page=generator"
               class="btn btn-primary">
                Genereaza primul document
            </a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Titlu</th>
                        <th>Sablon / Schema</th>
                        <th>Randuri</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td><?php echo $doc['id']; ?></td>
                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($doc['title']); ?>
                                </strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars(
                                    $doc['template_label'] ?? $doc['schema_name'] ?? 'Schema personalizata'
                                ); ?>
                            </td>
                            <td><?php echo $doc['rows_count']; ?></td>
                            <td>
                                <span class="badge <?php echo getStatusClass($doc['status']); ?>">
                                    <?php echo htmlspecialchars($doc['status']); ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap; font-size:13px;">
                                <?php echo htmlspecialchars($doc['created_at']); ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <!-- Buton previzualizare -->
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=preview&id=<?php echo $doc['id']; ?>"
                                       class="btn btn-primary btn-small"
                                       title="Previzualizeaza">
                                        👁 Vezi
                                    </a>
                                    <!-- Buton stergere -->
                                    <button class="btn btn-danger btn-small btn-delete"
                                            data-id="<?php echo $doc['id']; ?>"
                                            title="Sterge documentul">
                                        🗑
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginare -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filterStatus); ?>">
                        &laquo;
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filterStatus); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filterStatus); ?>">
                        &raquo;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div><!-- /.page-wrapper -->

<script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>
<script>
// Stergere document via AJAX
document.querySelectorAll('.btn-delete').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        if (!confirm('Stergi acest document? Actiunea este ireversibila.')) return;

        ajaxPost('/api/documents.php', { action: 'delete', id: id }, function(data) {
            if (data && data.success) {
                // Stergem randul din tabel
                btn.closest('tr').remove();
            } else {
                alert(data.message || 'Eroare la stergere.');
            }
        });
    });
});
</script>

</body>
</html>

<?php
function getStatusClass(string $status): string {
    $classes = [
        'draft'     => 'badge-warning',
        'generated' => 'badge-info',
        'exported'  => 'badge-success'
    ];
    return $classes[$status] ?? 'badge-info';
}
?>
