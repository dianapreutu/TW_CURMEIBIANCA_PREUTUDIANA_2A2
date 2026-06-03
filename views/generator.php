<?php 

// ==================================================
// views/generator.php - Pagina generatorului de date 
// Permite generarea de date aleatorii sau import CSV
// Inspirat din Mockaroo - utilizatorul defineste campuri
// ==================================================

// Includem configurarile globale
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

        <!-- Bara de navigare principala --> 
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
                <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Admin</a></li>
            </ul>
        </nav>

        <!-- Continutul principal --> 
        <main class="generator-page">
            <div class="container">

                <div class="page-header">
                    <h1>Generator de Date</h1>
                    <p class="page-subtitle">
                        Defineste campurile dorite si genereaza date romanesti realiste.
                        Inspirat din Mockaroo, adaptat pentru Romania
                    </p>
                </div>

                <!-- Sectiunea cu doua coloeane --> 
                <div class="generator-layout">

                    <!-- Coloana stanga: configurare schema --> 
                    <div class="generator-panel">

                        <!-- Tab-uri: Generator / Import CSV --> 
                        <div class="tab-header">
                            <button class="tab-btn active" data-tab="generator">
                                Generator aleatoriu
                            </button>
                            <button class="tab-btn" data-tab="import">
                                Import CSV
                            </button>
                        </div>

                        <!-- Tab: Generator aleatoriu --> 
                        <!-- Mesaj status --> 
                        <div id="generator-message" class="admin-alert" style="display:none;"></div>

                        <!-- Numarul de randuri de generat --> 
                        <div class="form-group">
                            <label for="rows-count-input">Numar de randuri:</label>
                            <input type="number"
                                id="rows-count-input"
                                value="<?php echo DEFAULT_ROWS; ?>"
                                min="1"
                                max="<?php echo MAX_ROWS; ?>">
                            <small>Maxim <?php echo MAX_ROWS; ?> randuri</small>
                        </div>

                        <!-- Numele schemei --> 
                        <div class="form-group">
                            <label for="schema-name-input">Nume schema (optional):</label>
                            <input type="text"
                                id="schema-name-input"
                                placeholder="Ex: Schema CV">
                        </div>

                        <!-- Adaugare camp nou --> 
                        <div class="fields-section">
                            <div class="fields-header">
                                <h3>Campuri schema</h3>
                                <button id="btn-add-field" class="btn-small">+ Adauga camp</button>
                            </div>

                            <!-- Randuri de adaugare --> 
                            <div class="fields-add-row" style="display:flex; gap:8px; margin-bottom:12px;">
                                <select id="field-type-select" class="select-input">
                                    <option value="">-- Alege tipul --</option>
                                </select>
                                <input type="text" id="field-label-input" placeholder="Nume camp (ex: Nume)" class="select-input">
                            </div>

                            <!-- Tabelul campurilor -->
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
                                            Niciun camp adaugat inca 
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Butoane de actiune --> 
                        <div class="generator-actions">
                            <button id="btn-generate" class="btn-generator">Genereaza Date</button>
                            <button id="btn-save-schema" class="btn-generator-secondary">Salveaza Schema</button>
                        </div>

                        <!-- Tab: Import CSV --> 
                        <div id="tab-import" class="tab-content">

                            <div class="form-group">
                                <label>Incarca fisier CSV:</label>
                                <!-- Input pentru upload fisier CSV --> 
                                <div class="file-upload-area" id="csv-drop-area">
                                    <p>Trage fisierul CSV aici sau</p>
                                    <label for="csv-file-input" class="btn-small">
                                        Alege fisier 
                                    </label>
                                    <input type="file"
                                        id="csv-file-input"
                                        accept=".csv"
                                        style="display: none;">
                                    <p id="csv-file-name" class="file-name-display"></p>
                                </div>
                                <small>Suportat: CSV cu separator virgula. Max 5MB</small>
                            </div>

                            <!-- Buton import --> 
                            <button id="btn-import-csv" class="btn-generator">
                                Importa CSV 
                            </button>

                        </div>

                        <!-- Scheme salvate --> 
                        <div class="saved-schemas">
                            <h3>Scheme salvate</h3>
                            <div id="schemas-list" class="schemas-list">
                                <p class="empty-message">Se incarca schemele...</p>
                            </div>
                        </div>

                    </div>

                    <!-- Coloana dreapta: previzualizare date generate --> 
                    <div class="results-panel">

                        <div class="panel-header">Date generate</div>

                        <!-- Bara de actiuni export --> 
                        <div class="export-bar" id="export-bar" style="display: none;">
                            <span id="results-count" class="results-count"></span>
                            <div class="export-actions">
                                <!-- Butoane export --> 
                                <button id="btn-export-csv" class="btn-export">
                                    Export CSV 
                                </button>
                                <button id="btn-export-json" class="btn-export">
                                    Export JSON 
                                </button>
                            </div>
                        </div>

                        <!-- Tabelul cu datele generate --> 
                        <div class="results-container">
                            <div id="preview-container" class="result-table-wrapper">
                                <p class="empty-message">
                                    Configureaza campurile si apasa "Genereaza Date" pentru a vedea rezultatele
                                </p>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </main>

        <!-- Footer --> 
        <footer class="main-footer">
            <div class="container">
                <p>
                    &copy; <?php echo date('Y'); ?>
                    <?php echo APP_NAME; ?>
                    v<?php echo APP_VERSION; ?> - 
                    Proiect Tehnologii Web
                </p>
            </div>
        </footer>

        <!-- Scripturile JavaScript --> 
        <script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>
        <script src="<?php echo BASE_URL; ?>/public/js/generator.js"></script>

    </body>
</html>