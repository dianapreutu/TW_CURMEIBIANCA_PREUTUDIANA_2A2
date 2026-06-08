<?php

// views/editor.php
// Pagina editorului de sabloane HTML
// Permite crearea, editarea si previzualizarea sabloanelor de documente

require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="ro">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editor Sabloane - <?php echo APP_NAME; ?></title>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/main.css">
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/editor.css">
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
                <li><a href="<?php echo BASE_URL; ?>/index.php?page=editor" class="active">Editor Sabloane</a></li>
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

        <!-- ===== Pagina editorului ===== -->
        <main class="editor-page">
            <div class="container">

                <div class="editor-header">
                    <h1>Editor Sabloane</h1>
                    <div class="editor-header-actions">
                        <button id="btn-new" class="btn-editor-secondary">+ Sablon nou</button>
                        <button id="btn-save" class="btn-editor">Salveaza</button>
                    </div>
                </div>

                <!-- Layout cu doua coloane: editor + previzualizare -->
                <div class="editor-layout">

                    <!-- Coloana stanga: editorul -->
                    <div class="editor-panel">

                        <div class="panel-header">Editor sablon</div>

                        <!-- Toolbar cu variabile predefinite ce pot fi inserate in sablon -->
                        <div class="editor-toolbar">

                            <!-- Variabile de data si ora -->
                            <button class="toolbar-btn" data-insert="{{DATE}}"     title="Data curenta">{{DATE}}</button>
                            <button class="toolbar-btn" data-insert="{{TIME}}"     title="Ora curenta">{{TIME}}</button>
                            <button class="toolbar-btn" data-insert="{{YEAR}}"     title="Anul curent">{{YEAR}}</button>
                            <button class="toolbar-btn" data-insert="{{DATETIME}}" title="Data si ora">{{DATETIME}}</button>

                            <!-- Variabile date personale -->
                            <button class="toolbar-btn" data-insert="{{nume}}"           title="Numele persoanei">{{nume}}</button>
                            <button class="toolbar-btn" data-insert="{{email}}"          title="Email">{{email}}</button>
                            <button class="toolbar-btn" data-insert="{{telefon}}"        title="Telefon">{{telefon}}</button>
                            <button class="toolbar-btn" data-insert="{{adresa}}"         title="Adresa">{{adresa}}</button>
                            <button class="toolbar-btn" data-insert="{{cnp}}"            title="CNP">{{cnp}}</button>
                            <button class="toolbar-btn" data-insert="{{ocupatie}}"       title="Ocupatie">{{ocupatie}}</button>
                            <button class="toolbar-btn" data-insert="{{studii}}"         title="Nivel studii">{{studii}}</button>
                            <button class="toolbar-btn" data-insert="{{data_nasterii}}"  title="Data nasterii">{{data_nasterii}}</button>
                            <button class="toolbar-btn" data-insert="{{oras}}"           title="Oras">{{oras}}</button>
                            <button class="toolbar-btn" data-insert="{{judet}}"          title="Judet">{{judet}}</button>
                            <button class="toolbar-btn" data-insert="{{nume_solicitant}}" title="Nume solicitant">{{nume_solicitant}}</button>
                            <button class="toolbar-btn" data-insert="{{subiect}}"        title="Subiect">{{subiect}}</button>
                            <button class="toolbar-btn" data-insert="{{detalii}}"        title="Detalii">{{detalii}}</button>

                            <!-- Variabile financiare -->
                            <button class="toolbar-btn" data-insert="{{firma}}"       title="Denumire firma">{{firma}}</button>
                            <button class="toolbar-btn" data-insert="{{nr_factura}}"  title="Numar factura">{{nr_factura}}</button>
                            <button class="toolbar-btn" data-insert="{{suma}}"        title="Suma">{{suma}}</button>
                            <button class="toolbar-btn" data-insert="{{iban}}"        title="IBAN">{{iban}}</button>
                            <button class="toolbar-btn" data-insert="{{tva}}"         title="TVA">{{tva}}</button>
                            <button class="toolbar-btn" data-insert="{{produs}}"      title="Produs">{{produs}}</button>
                            <button class="toolbar-btn" data-insert="{{cantitate}}"   title="Cantitate">{{cantitate}}</button>
                            <button class="toolbar-btn" data-insert="{{pret_unitar}}" title="Pret unitar">{{pret_unitar}}</button>
                            <button class="toolbar-btn" data-insert="{{cui}}"         title="CUI">{{cui}}</button>

                            <!-- Bloc conditional -->
                            <button class="toolbar-btn" data-insert="{{IF camp}}continut{{ENDIF}}" title="Bloc conditional">{{IF...}}</button>

                        </div>

                        <textarea
                            id="editor-content"
                            class="editor-textarea"
                            placeholder="Scrieti continutul sablonului aici...
Folositi variabile precum {{nume}}, {{data}}, {{DATE}} etc.
Exemplu:
<h1>CV - {{nume}}</h1>
<p>Email: {{email}}</p>
<p>Data: {{DATE}}</p>"></textarea>

                        <!-- Formular de salvare si generare -->
                        <div class="save-form">
                            <div class="form-group">
                                <label for="template-name">Nume sablon:</label>
                                <input type="text" id="template-name" placeholder="Ex: CV simplu" maxlength="100">
                            </div>
                            <div class="form-group">
                                <label for="template-type">Tip:</label>
                                <select id="template-type">
                                    <option value="cv">CV</option>
                                    <option value="cerere">Cerere</option>
                                    <option value="factura">Factura</option>
                                    <option value="catalog">Catalog</option>
                                    <option value="alt">Alt tip</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="doc-name">Nume document:</label>
                                <input type="text" id="doc-name" placeholder="Ex: CV Ion Popescu">
                            </div>
                            <button id="btn-generate" class="btn-editor">Genereaza Document</button>
                        </div>

                    </div>

                    <!-- Coloana dreapta: previzualizare -->
                    <div class="preview-panel">

                        <div class="panel-header">Previzualizare document</div>

                        <div class="preview-container">
                            <div id="preview-document" class="preview-document">
                                <p class="preview-empty">Scrieti continut in editor pentru previzualizare...</p>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Lista sabloanelor salvate, populata dinamic via JS -->
                <div class="templates-list">
                    <h2>Sabloane salvate</h2>
                    <div id="templates-grid" class="templates-grid">
                        <p class="empty-message">Se incarca sabloanele...</p>
                    </div>
                </div>

            </div>

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

        </main>

        <script>
            // Transmitem rolul utilizatorului din PHP catre JavaScript
            var USER_IS_ADMIN = <?php echo (isset($_SESSION['admin']) && $_SESSION['admin'] === true) ? 'true' : 'false'; ?>;
            var USER_IS_AUTH  = <?php echo (isset($_SESSION['user_id']) || (isset($_SESSION['admin']) && $_SESSION['admin'] === true)) ? 'true' : 'false'; ?>;
        </script>
        <script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>
        <script src="<?php echo BASE_URL; ?>/public/js/editor.js"></script>

    </body>
</html>