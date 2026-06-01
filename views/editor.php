<?php

// ==================================================
// views/editor.php - Pagina editorului de sabloane
// Permite crearea si editarea sabloanelor de documente 
// ==================================================

// Includem configurarile globale
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="ro">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editor Sabloane - <?php echo APP_NAME; ?></title>
        <link rel="stylesheet" href="../public/css/main.css">
        <link rel="stylesheet" href="../public/css/editor.css">
    </head>
    <body>

        <!-- Bara de navigare principala --> 
        <nav class="main-nav">
            <div class="nav-brand">
                <span class="brand-icon">📄</span>
                <?php echo APP_NAME; ?>
            </div>
            <ul class="nav-links">
                <li><a href="home.php">Acasa</a></li>
                <li><a href="editor.php" class="active">Editor Sabloane</a></li>
                <li><a href="generator.php">Generator Date</a></li>
                <li><a href="documents.php">Documentele Mele</a></li>
                <li><a href="../admin/index.php">Admin</a></li>
            </ul>
        </nav>

        <!-- Pagina editorului --> 
        <div class="editor-page">

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

                        <!-- Buton previzualizare --> 
                        <button id="btn-preview" class="btn-editor-secondary">
                            Previzualizare
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
        <!-- sfarsit editor-page --> 
        
        <!-- Scripturile JavaScript --> 
        <script src="../public/js/app.js"></script>
        <script src="../public/js/editor.js"></script>

    </body>
</html>