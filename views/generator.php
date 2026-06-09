<?php

// views/generator.php
// Pagina generatorului de date aleatorii si import CSV
// Utilizatorul defineste campuri, genereaza date si le exporta in CSV/JSON

require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="ro">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Generator Date - <?php echo APP_NAME; ?></title>
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
                <li><a href="<?php echo BASE_URL; ?>/index.php?page=generator" class="active">Generator Date</a></li>
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

        <!-- ===== Continut principal ===== -->
        <main class="generator-page">
            <div class="container">

                <div class="page-header">
                    <h1>Generator de Date</h1>
                </div>

                <!-- Layout cu doua coloane: configurare + rezultate -->
                <div class="generator-layout">

                    <!-- Coloana stanga: configurare schema -->
                    <div class="generator-panel">

                        <!-- Tab-uri: Generator aleatoriu / Import CSV -->
                        <div class="tab-header">
                            <button class="tab-btn active" data-tab="generator">
                                Generator aleatoriu
                            </button>
                            <?php if (!empty($_SESSION['user_id'])): ?>
                                <button class="tab-btn" data-tab="import">
                                    Import CSV
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Mesaj de status al generatorului -->
                        <div id="generator-message" class="admin-alert" style="display:none; padding:12px 16px; border-radius:6px; margin-bottom:16px; font-size:0.9rem; font-weight:500; align-items:center; gap:8px;"></div>

                        <div class="form-group">
                            <label for="rows-count-input">Numar de randuri:</label>
                            <input type="number"
                                id="rows-count-input"
                                value="<?php echo DEFAULT_ROWS; ?>"
                                min="1"
                                max="<?php echo MAX_ROWS; ?>">
                            <small>Maxim <?php echo MAX_ROWS; ?> randuri</small>
                        </div>

                        <div class="form-group">
                            <label for="schema-name-input">Nume schema (optional):</label>
                            <input type="text" id="schema-name-input" placeholder="Ex: Schema CV">
                        </div>

                        <!-- Definire campuri schema -->
                        <div class="fields-section">
                            <div class="fields-header">
                                <h2>Campuri schema</h2>
                                <button id="btn-add-field" class="btn-small">+ Adauga camp</button>
                            </div>

                            <div class="fields-add-row" style="display:flex; gap:8px; margin-bottom:12px;">
                                <select id="field-type-select" class="select-input">
                                    <option value="">-- Alege tipul --</option>
                                </select>
                                <input type="text" id="field-label-input" placeholder="Nume camp (ex: Nume)" class="select-input">
                            </div>

                            <table id="fields-table" class="data-table">
                                <thead>
                                    <tr>
                                        <th>Label</th>
                                        <th>Cheie</th>
                                        <th>Tip</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="empty-row">
                                        <td colspan="4" style="text-align:center; color:#999; padding:20px;">
                                            Niciun camp adaugat inca.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="generator-actions">
                            <button id="btn-generate"    class="btn-generator">Genereaza Date</button>
                            <button id="btn-save-schema" class="btn-generator-secondary">Salveaza Schema</button>
                        </div>

                        <!-- Tab: Import CSV (vizibil doar pentru utilizatorii autentificati) -->
                        <?php if (!empty($_SESSION['user_id'])): ?>
                        <div id="tab-import" class="tab-content">
                            <div class="form-group">
                                <label>Incarca fisier CSV:</label>
                                <div class="file-upload-area" id="csv-drop-area">
                                    <p>Trage fisierul CSV aici sau</p>
                                    <label for="csv-file-input" class="btn-small">Alege fisier</label>
                                    <input type="file" id="csv-file-input" accept=".csv" style="display:none;">
                                    <p id="csv-file-name" class="file-name-display"></p>
                                </div>
                                <small>Suportat: CSV cu separator virgula. Max 5MB</small>
                            </div>
                            <button id="btn-import-csv" class="btn-generator">Importa CSV</button>
                        </div>
                        <?php endif; ?>

                        <!-- Scheme salvate, populate dinamic via JS -->
                        <div class="saved-schemas">
                            <h2>Scheme salvate</h2>
                            <div id="schemas-list" class="schemas-list">
                                <p class="empty-message">Se incarca schemele...</p>
                            </div>
                        </div>

                    </div>

                    <!-- Coloana dreapta: previzualizare date generate -->
                    <div class="results-panel">

                        <div class="panel-header">Date generate</div>

                        <!-- Bara de export, vizibila dupa generare -->
                        <div class="export-bar" id="export-bar" style="display:none;">
                            <span id="results-count" class="results-count"></span>
                            <div class="export-actions">
                                <button id="btn-export-csv"  class="btn-export">Export CSV</button>
                                <button id="btn-export-json" class="btn-export">Export JSON</button>
                            </div>
                        </div>

                        <div class="results-container">
                            <div id="preview-container" class="result-table-wrapper">
                                <p class="empty-message">
                                    Configureaza campurile si apasa "Genereaza Date" pentru a vedea rezultatele.
                                </p>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

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

        </main>

        <!-- ===== Footer ===== -->
        <footer class="main-footer">
            <div class="container">
                <p>
                    &copy; <?php echo date('Y'); ?>
                    <?php echo APP_NAME; ?>
                    v<?php echo APP_VERSION; ?> —
                    Proiect Tehnologii Web
                </p>
            </div>
        </footer>

        <script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>
        <script src="<?php echo BASE_URL; ?>/public/js/generator.js"></script>

    </body>
</html>