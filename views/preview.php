<?php

// views/preview.php
// Pagina de previzualizare a unui document generat
// Afiseaza documentul intr-un iframe si ofera butoane de export (PDF, HTML, CSV, JSON)
// Depinde de: api/documents.php, api/export.php, public/js/preview.js

require_once __DIR__ . '/../config.php';

// Verificam autentificarea
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin'])) {
    header('Location: ' . BASE_URL . '/index.php?page=home');
    exit;
}

// Citim si validam id-ul documentului din URL
$documentId = (int)($_GET['id'] ?? 0);
if ($documentId <= 0) {
    header('Location: ' . BASE_URL . '/index.php?page=documents');
    exit;
}

// Incarcam datele documentului din DB pentru afisarea initiala
$db       = Database::getInstance();
$document = $db->fetchOne(
    'SELECT d.*, t.label as template_label, s.name as schema_name
     FROM documents d
     LEFT JOIN templates t ON d.template_id = t.id
     LEFT JOIN schemas s ON d.schema_id = s.id
     WHERE d.id = ? AND d.user_id = ?',
    [$documentId, $_SESSION['user_id']]
);

// Daca documentul nu exista sau nu apartine utilizatorului curent, redirectionam
if (!$document) {
    header('Location: ' . BASE_URL . '/index.php?page=documents');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Previzualizare: <?php echo htmlspecialchars($document['title']); ?> &mdash; <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/main.css">
</head>
<body>

    <!-- ===== Navigare principala ===== -->
    <nav class="main-nav">
        <a href="<?php echo BASE_URL; ?>/index.php?page=home" class="nav-brand" title="Mergi la Acasa">
            <span class="brand-icon">&#128196;</span>
            <?php echo APP_NAME; ?>
        </a>
        <ul class="nav-links">
            <li><a href="<?php echo BASE_URL; ?>/index.php?page=home">Acasa</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php?page=editor">Editor Sabloane</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php?page=generator">Generator Date</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php?page=documents">Documentele Mele</a></li>
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

    <div class="preview-wrapper">

        <!-- Header cu titlu si butoane de export -->
        <div class="preview-header">
            <div class="preview-header-left">
                <a href="<?php echo BASE_URL; ?>/index.php?page=documents" class="btn btn-secondary">
                    &larr; Inapoi la documente
                </a>
                <h1 id="document-title">
                    <?php echo htmlspecialchars($document['title']); ?>
                </h1>
            </div>

            <div class="preview-header-actions">
                <button id="btn-export-html" class="btn btn-secondary" title="Descarca HTML">&#128196; HTML</button>
                <button id="btn-export-pdf"  class="btn btn-secondary" title="Descarca PDF">&#128213; PDF</button>
                <button id="btn-export-csv"  class="btn btn-secondary" title="Descarca CSV">&#128202; CSV</button>
                <button id="btn-export-json" class="btn btn-secondary" title="Descarca JSON">&#128203; JSON</button>
                <button id="btn-delete-document" class="btn btn-danger" title="Sterge documentul">&#128465; Sterge</button>
            </div>
        </div>

        <!-- Mesaj de status (populat de preview.js) -->
        <div id="preview-message" class="alert" style="display:none;"></div>

        <!-- Metadate document -->
        <div class="preview-meta">
            <div class="preview-meta-item">
                <span class="preview-meta-label">Sablon:</span>
                <span class="preview-meta-value">
                    <?php echo htmlspecialchars($document['template_label'] ?? $document['schema_name'] ?? 'Schema personalizata'); ?>
                </span>
            </div>
            <div class="preview-meta-item">
                <span class="preview-meta-label">Status:</span>
                <span id="document-status" class="badge <?php echo getStatusClass($document['status']); ?>">
                    <?php echo htmlspecialchars($document['status']); ?>
                </span>
            </div>
            <div class="preview-meta-item">
                <span class="preview-meta-label">Randuri generate:</span>
                <span id="document-rows"><?php echo $document['rows_count']; ?></span>
            </div>
            <div class="preview-meta-item">
                <span class="preview-meta-label">Data generarii:</span>
                <span id="document-date"><?php echo htmlspecialchars($document['created_at']); ?></span>
            </div>
        </div>

        <!-- Zona de previzualizare a documentului -->
        <div class="preview-container" id="preview-container">

            <!-- Indicator de incarcare -->
            <div id="preview-loader" class="preview-loader">
                <div class="loader-spinner"></div>
                <p>Se incarca documentul...</p>
            </div>

            <!-- Iframe pentru izolarea stilurilor documentului HTML -->
            <iframe id="document-iframe"
                    class="preview-iframe"
                    style="display:none;"
                    title="Previzualizare document">
            </iframe>

        </div>

    </div>

    <script>
        const DOCUMENT_ID = <?php echo $documentId; ?>;
        const BASE_URL    = '<?php echo BASE_URL; ?>';
    </script>

    <script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/preview.js"></script>

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