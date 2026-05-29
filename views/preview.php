<?php

// views/preview.php - Pagina de previzualizare document
// Afiseaza documentul generat intr-un iframe si ofera butoane de export (PDF, HTML, CSV, JSON)
// Depinde de: api/documents.php, api/export.php
// Stilizat cu: public/css/main.css
// Logica JS: public/js/preview.js

require_once __DIR__ . '/../config.php';

// Verificam autentificarea
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php?page=home');
    exit;
}

// Citim id-ul documentului din URL
$documentId = (int)($_GET['id'] ?? 0);

if ($documentId <= 0) {
    header('Location: ' . BASE_URL . '/index.php?page=documents');
    exit;
}

// Incarcam datele documentului din DB pentru afisare initiala
$db       = Database::getInstance();
$document = $db->fetchOne(
    'SELECT d.*, t.label as template_label, s.name as schema_name
     FROM documents d
     LEFT JOIN templates t ON d.template_id = t.id
     LEFT JOIN schemas s ON d.schema_id = s.id
     WHERE d.id = ? AND d.user_id = ?',
    [$documentId, $_SESSION['user_id']]
);

// Daca documentul nu exista sau nu apartine utilizatorului
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
    <title>Previzualizare: <?php echo htmlspecialchars($document['title']); ?> — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/main.css">
</head>
<body>

<div class="preview-wrapper">

    <!-- Header pagina -->
    <div class="preview-header">
        <div class="preview-header-left">
            <a href="<?php echo BASE_URL; ?>/index.php?page=documents"
               class="btn btn-secondary">
                ← Inapoi la documente
            </a>
            <h1 id="document-title">
                <?php echo htmlspecialchars($document['title']); ?>
            </h1>
        </div>

        <!-- Butoane export -->
        <div class="preview-header-actions">
            <button id="btn-export-html" class="btn btn-secondary" title="Descarca HTML">
                📄 HTML
            </button>
            <button id="btn-export-pdf" class="btn btn-primary" title="Descarca PDF">
                📕 PDF
            </button>
            <button id="btn-export-csv" class="btn btn-secondary" title="Descarca CSV">
                📊 CSV
            </button>
            <button id="btn-export-json" class="btn btn-secondary" title="Descarca JSON">
                📋 JSON
            </button>
            <button id="btn-delete-document" class="btn btn-danger" title="Sterge documentul">
                🗑 Sterge
            </button>
        </div>
    </div>

    <!-- Mesaj de status (afisat de preview.js) -->
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
            <span id="document-rows">
                <?php echo $document['rows_count']; ?>
            </span>
        </div>
        <div class="preview-meta-item">
            <span class="preview-meta-label">Data generarii:</span>
            <span id="document-date">
                <?php echo htmlspecialchars($document['created_at']); ?>
            </span>
        </div>
    </div>

    <!-- Zona de previzualizare -->
    <div class="preview-container" id="preview-container">

        <!-- Indicator de incarcare -->
        <div id="preview-loader" class="preview-loader">
            <div class="loader-spinner"></div>
            <p>Se incarca documentul...</p>
        </div>

        <!-- Iframe pentru afisarea documentului HTML -->
        <!-- Folosim iframe pentru izolarea stilurilor documentului -->
        <iframe id="document-iframe"
                class="preview-iframe"
                style="display:none;"
                title="Previzualizare document">
        </iframe>

    </div>

</div><!-- /.preview-wrapper -->

<!-- Datele documentului pentru preview.js -->
<!-- Transmitem id-ul documentului catre JavaScript -->
<script>
    // Id-ul documentului curent (folosit de preview.js)
    const DOCUMENT_ID = <?php echo $documentId; ?>;
    const BASE_URL    = '<?php echo BASE_URL; ?>';
</script>

<script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>
<script src="<?php echo BASE_URL; ?>/public/js/preview.js"></script>

</body>
</html>

<?php

// Functie helper: returneaza clasa CSS pentru status

function getStatusClass(string $status): string {
    $classes = [
        'draft'     => 'badge-warning',
        'generated' => 'badge-info',
        'exported'  => 'badge-success'
    ];
    return $classes[$status] ?? 'badge-info';
}
?>
