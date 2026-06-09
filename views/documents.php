<?php

// views/documents.php
// Lista documentelor generate de utilizatorul logat
// Permite filtrare dupa status, paginare, previzualizare si stergere

require_once __DIR__ . '/../config.php';

// Verificam autentificarea
$isAuthenticated = isset($_SESSION['user_id']) || isset($_SESSION['admin']);
$userId = $isAuthenticated ? ($_SESSION['user_id'] ?? 1) : null;

if ($isAuthenticated) {
    $db = Database::getInstance();

    // Paginare
    $page    = max(1, (int)($_GET['p'] ?? 1));
    $perPage = 10;
    $offset  = ($page - 1) * $perPage;

    // Filtru optional dupa status
    $filterStatus = trim($_GET['status'] ?? '');
    $whereStatus  = $filterStatus ? 'AND d.status = ?' : '';
    $params       = $filterStatus ? [$userId, $filterStatus] : [$userId];

    // Total documente pentru calculul paginilor
    $total = $db->fetchOne(
        "SELECT COUNT(*) as total FROM documents d
         WHERE d.user_id = ? {$whereStatus}",
        $params
    )['total'] ?? 0;

    $totalPages = (int)ceil($total / $perPage);

    // Obtinem documentele pentru pagina curenta
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
} else {
    $page         = 1;
    $filterStatus = trim($_GET['status'] ?? '');
    $total        = 0;
    $totalPages   = 0;
    $documents    = [];
}
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

    <!-- ===== Navigare principala ===== -->
    <nav class="main-nav">
        <a href="<?php echo BASE_URL; ?>/index.php?page=home" class="nav-brand" title="Mergi la Acasa">
            <span class="brand-icon">📄</span>
            <?php echo APP_NAME; ?>
        </a>
        <ul class="nav-links">
            <li><a href="<?php echo BASE_URL; ?>/index.php?page=home">Acasa</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php?page=editor">Editor Sabloane</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php?page=generator">Generator Date</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php?page=documents" class="active">Documentele Mele</a></li>
            <?php if (!isset($_SESSION['user_id']) || (isset($_SESSION['admin']) && $_SESSION['admin'] === true)): ?>
                <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Admin</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin'])): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/admin/index.php?logout=1">Delogare</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

<div class="page-wrapper">

    <div class="page-header">
        <h1>Documentele mele</h1>
        <a href="<?php echo BASE_URL; ?>/index.php?page=editor" class="btn btn-primary">
            + Document nou
        </a>
    </div>

    <div id="documents-message" class="alert" style="display:none;"></div>

    <?php if ($isAuthenticated): ?>

        <!-- Filtre dupa status -->
        <div class="filters-bar">
            <form method="GET">
                <input type="hidden" name="page" value="documents">
                
                <div class="filters-inner">
                    <select name="status" class="select-input" onchange="this.form.submit()">
                        <option value="">Toate statusurile</option>
                        <option value="draft"      <?php echo $filterStatus === 'draft'      ? 'selected' : ''; ?>>Draft</option>
                        <option value="generated"  <?php echo $filterStatus === 'generated'  ? 'selected' : ''; ?>>Generat</option>
                        <option value="exported"   <?php echo $filterStatus === 'exported'   ? 'selected' : ''; ?>>Exportat</option>
                    </select>
                    <?php if ($filterStatus): ?>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=documents"
                           class="btn btn-secondary btn-small">
                            ✕ Reseteaza
                        </a>
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
                <a href="<?php echo BASE_URL; ?>/index.php?page=editor" class="btn btn-primary">
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
                                    <strong><?php echo htmlspecialchars($doc['title']); ?></strong>
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
                                        <a href="<?php echo BASE_URL; ?>/index.php?page=preview&id=<?php echo $doc['id']; ?>"
                                           class="btn btn-primary btn-small"
                                           title="Previzualizeaza">
                                            👁 Vezi
                                        </a>
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

            <!-- Paginare: afisata doar daca exista mai mult de o pagina -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=documents&p=<?php echo $page - 1; ?>&status=<?php echo urlencode($filterStatus); ?>">
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
                            <a href="<?php echo BASE_URL; ?>/index.php?page=documents&p=<?php echo $i; ?>&status=<?php echo urlencode($filterStatus); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=documents&p=<?php echo $page + 1; ?>&status=<?php echo urlencode($filterStatus); ?>">
                            &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <p>🔒 Trebuie sa te autentifici pentru a vedea documentele tale.</p>
            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-primary">
                Autentificare
            </a>
        </div>
    <?php endif; ?>

</div>

<script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>

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

<script>
function showConfirmModal(message, onConfirm) {
    const modal  = document.getElementById('confirm-modal');
    const msg    = document.getElementById('confirm-message');
    const cancel = document.getElementById('confirm-cancel');
    const ok     = document.getElementById('confirm-ok');

    msg.innerHTML        = message;
    modal.style.display  = 'flex';

    cancel.onclick = () => modal.style.display = 'none';
    ok.onclick = () => {
        modal.style.display = 'none';
        onConfirm();
    };
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            showConfirmModal(
                'Esti sigur ca vrei sa stergi acest document? Actiunea este ireversibila.',
                function () {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', id);

                    ajaxPost(BASE_URL + '/api/documents.php', formData, function (data) {
                        if (data) {
                            btn.closest('tr').remove();
                        } else {
                            alert('Eroare la stergere.');
                        }
                    });
                }
            );
        });
    });
});
</script>

</body>
</html>

<?php
// Returneaza clasa CSS corespunzatoare statusului unui document
function getStatusClass(string $status): string {
    $classes = [
        'draft'     => 'badge-warning',
        'generated' => 'badge-info',
        'exported'  => 'badge-success'
    ];
    return $classes[$status] ?? 'badge-info';
}
?>