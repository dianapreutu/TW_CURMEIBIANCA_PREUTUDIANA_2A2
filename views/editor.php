<?php

// ==================================================
// views/editor.php - Pagina editorului de sabloane
// Permite crearea si editarea sabloanelor de documente 
// ==================================================

// Includem configurarile globale
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

        <!-- Bara de navigare principala --> 
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
                <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Admin</a></li>
            </ul>
        </nav>

        <!-- Pagina editorului --> 
        <main class="editor-page">
            <div class="container">

                <!-- Header-ul editorului --> 
                <div class="editor-header">
                <h1>Editor Sabloane</h1>
                <div class="editor-header-actions">
                    <!-- Buton sablon nou --> 
                    <button id="btn-new" class="btn-editor-secondary">
                        + Sablon nou 
                    </button>
                    <!-- Buton salvare --> 
                    <button id="btn-save" class="btn-editor">
                        Salveaza
                    </button>
                </div>
            </div>

            <!-- Layout cu doua coloane --> 
            <div class="editor-layout">

                <!-- Coloana stanga: editorul --> 
                <div class="editor-panel">

                    <!-- Header panoul editor --> 
                    <div class="panel-header">Editor sablon</div>

                    <!-- Toolbar cu variabile predefinite --> 
                    <div class="editor-toolbar">
                        <!-- Butoane pentru variabile dinamice --> 
                        <button class="toolbar-btn" data-insert="{{DATE}}" title="Data curenta">{{DATE}}</button>
                        <button class="toolbar-btn" data-insert="{{TIME}}" title="Ora curenta">{{TIME}}</button>
                        <button class="toolbar-btn" data-insert="{{YEAR}}" title="Anul curent">{{YEAR}}</button>
                        <button class="toolbar-btn" data-insert="{{DATETIME}}" title="Data si ora">{{DATETIME}}</button>
                        <!-- Butoane pentru variabile de date personale --> 
                        <button class="toolbar-btn" data-insert="{{nume}}" title="Numele persoanei">{{nume}}</button>
                        <button class="toolbar-btn" data-insert="{{email}}" title="Email">{{email}}</button>
                        <button class="toolbar-btn" data-insert="{{telefon}}" title="Telefon">{{telefon}}</button>
                        <button class="toolbar-btn" data-insert="{{adresa}}" title="Adresa">{{adresa}}</button>
                        <button class="toolbar-btn" data-insert="{{cnp}}" title="CNP">{{cnp}}</button>
                        <!-- Butoane pentru variabile financiare -->
                        <button class="toolbar-btn" data-insert="{{firma}}" title="Denumire firma">{{firma}}</button>
                        <button class="toolbar-btn" data-insert="{{nr_factura}}" title="Numar factura">{{nr_factura}}</button>
                        <button class="toolbar-btn" data-insert="{{suma}}" title="Suma">{{suma}}</button>
                        <!-- Buton pentru bloc conditional -->
                        <button class="toolbar-btn" data-insert="{{IF camp}}continut{{ENDIF}}" title="Bloc conditional">{{IF...}}</button>
                    </div>

                    <!-- Area de text a editorului --> 
                    <textarea
                        id="editor-content"
                        class="editor-textarea"
                        placeholder="Scrieti continutul sablonului aici...
Folositi variabile precum {{nume}}, {{data}}, {{DATE}} etc.
Exemplu:
<h1>CV - {{nume}}</h1>
<p>Email: {{email}}</p>
<p>Data: {{DATE}}</p>"></textarea>

                    <!-- Formularul de salvare --> 
                    <div class="save-form">
                        <!-- Numele sablonului --> 
                        <div class="form-group">
                            <label for="template-name">Nume sablon:</label>
                            <input type="text"
                                id="template-name"
                                placeholder="Ex: CV simplu"
                                maxlength="100">
                        </div>

                        <!-- Tipul sablonului --> 
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

                        <!-- Numele documentului generat --> 
                        <div class="form-group">
                            <label for="doc-name">Nume document:</label>
                            <input type="text"
                                    id="doc-name"
                                    placeholder="Ex: CV Ion Popescu">
                        </div>

                        <!-- Buton generare document --> 
                        <button id="btn-generate" class="btn-editor">
                            Genereaza Document 
                        </button>
                    </div>

                </div>

                <!-- Coloana dreapta: previzualizare --> 
                <div class="preview-panel">

                    <!-- Header panoul previzualizare --> 
                    <div class="panel-header">Previzualizare document</div>

                    <!-- Containerul previzualizarii --> 
                    <div class="preview-container">
                        <div id="preview-document" class="preview-document">
                            <p class="preview-empty">
                                Scrieti continut in editor pentru previzualizare... 
                            </p>
                        </div>
                    </div>

                </div>

            </div>
            <!-- sfarsit editor-layout --> 
            

            <!-- Lista de sabloane salvate --> 
            <div class="templates-list">
                <h2>Sabloane salvate</h2>
                <!-- Grid-ul se populeaza dinamic via JavaScript --> 
                <div id="templates-grid" class="templates-grid">
                    <p class="empty-message">Se incarca sabloanele...</p>
                </div>
            </div>

            </div>
            <!-- Modal confirmare stergere -->
        <div id="confirm-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
           <div style="background:#fff;border-radius:8px;padding:2rem;max-width:400px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,.2);">
              <h3 id="confirm-title" style="font-size:1.1rem;margin-bottom:.8rem;color:#1a1a2e;">Confirmare</h3>
                 <p id="confirm-message" style="color:#555;margin-bottom:1.5rem;font-size:.95rem;"></p>
                   <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                 <button id="confirm-cancel" style="padding:.5rem 1.2rem;border:1px solid #ddd;background:#fff;border-radius:4px;cursor:pointer;font-size:.9rem;">Anulează</button>
            <button id="confirm-ok" style="padding:.5rem 1.2rem;background:#dc3545;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.9rem;">Șterge</button>
        </div>
         </div>
       </div> 
        </main>
        <!-- sfarsit editor-page --> 
        
        <!-- Scripturile JavaScript --> 
        <script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>
        <script src="<?php echo BASE_URL; ?>/public/js/editor.js"></script>

    </body>
</html>