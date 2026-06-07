<?php

// ==================================================
// views/home.php - Pagina principala a aplicatiei
// Afiseaza pagina de start cu optiunile disponibile
// ==================================================

// Includem configurarile globale
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="ro">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo APP_NAME; ?> - Generator de Documente</title>
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
                <li><a href="<?php echo BASE_URL; ?>/index.php?page=home" class="active">Acasa</a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php?page=editor">Editor Sabloane</a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php?page=generator">Generator Date</a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php?page=documents">Documentele Mele</a></li>
                <?php if (isset($_SESSION['admin']) && $_SESSION['admin'] === true): ?>
                    <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Admin</a></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin'])): ?>
                  <li>
                <a href="<?php echo BASE_URL; ?>/admin/index.php?logout=1">
            Delogare
        </a>
    </li>
<?php endif; ?>
            </ul>
        </nav>

        <!-- Sectiunea hero -->
        <header class="hero">
            <div class="hero-content">
                <h1>Generator de Documente</h1>
                <p class="hero-subtitle">
                    Creeaza, editeaza si genereaza documente profesionale
                    pornind de la sabloane predefinite, cu date realiste generate automat
                </p>
                <div class="hero-buttons">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=editor" class="btn btn-primary">Creeaza Sablon</a>
                    <a href="<?php echo BASE_URL; ?>/index.php?page=generator" class="btn btn-secondary">Genereaza Date</a>
                </div>
            </div>
        </header>

        <!-- Sectiunea cu functionalitati principale --> 
        <section class="features">
            <div class="container">
                <h2>Ce poti face cu <?php echo APP_NAME; ?>?</h2>

                <div class="features-grid">

                    <!-- Functionalitatea 1: Sabloane --> 
                    <div class="feature-card">
                        <div class="feature-icon">📝</div>
                        <h3>Sabloane Flexibile</h3>
                        <p>
                            Creeaza sabloane personalizate pentru CV-uri, cereri,
                            facturi si alte documente. Foloseste variabile dinamice 
                            precum <code>{{nume}}</code> sau <code>{{data}}</code>
                        </p>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=editor" class="feature-link">Deschide editorul</a>
                    </div>

                    <!-- Functionalitate 2: Generator date --> 
                    <div class="feature-card">
                        <div class="feature-icon">⚡</div>
                        <h3>Date Realiste</h3>
                        <p>
                            Genereaza date romanesti realiste automat: nume, CNP, 
                            IBAN, adrese, firme si multe altele. Inspirat din Mockaroo,
                            adaptat pentru Romania
                        </p>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=generator" class="feature-link">Genereaza date</a>
                    </div>

                    <!-- Functionalitate 3: Import CSV --> 
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Import CSV</h3>
                        <p>
                            Importa date din fisiere CSV existente si foloseste-le 
                            pentru a popula sabloanele tale. Export disponibil
                            in CSV si JSON
                        </p>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=generator" class="feature-link">Importa date</a>
                    </div>

                    <!-- Functionalitate 4: Export documente --> 
                    <div class="feature-card">
                        <div class="feature-icon">📥</div>
                        <h3>Export HTML si PDF</h3>
                        <p>
                            Exporta documentele generate in format HTML sau PDF,
                            gata de tiparit sau distribuit. Toate documentele
                            sunt salvate pentru acces ulterior
                        </p>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=documents" class="feature-link">Vezi documentele</a>
                    </div>

                    <!-- Functionalitate 5: Templating dinamic --> 
                    <div class="feature-card">
                        <div class="feature-icon">🔧</div>
                        <h3>Templating Dinamic</h3>
                        <p>
                            Foloseste functii dinamice in sabloane:
                            <code>{{DATE}}</code>, <code>{{TIME}}</code>,
                            <code>{{YEAR}}</code> si conditii
                            <code>{{IF camp}}...{{ENDIF}}</code>
                        </p>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=editor" class="feature-link">Incearca acum</a>
                    </div>

                    <!-- Functionalitate 6: Sabloane predefinite --> 
                    <div class="feature-card">
                        <div class="feature-icon">📋</div>
                        <h3>Sabloane Predefinite</h3>
                        <p>
                            Porneste rapid cu sabloane gata facute pentru
                            CV, cerere si factura. Personalizeaza-le
                            dupa nevoile tale
                        </p>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=editor" class="feature-link">Vezi sabloanele</a>
                    </div>

                </div>
            </div>
        </section>

        <!-- Sectiunea: tipuri de documente suportate --> 
        <section class="doc-types">
            <div class="container">
                <h2>Tipuri de documente suportate</h2>
                <div class="doc-types-grid">

                    <div class="doc-type">
                        <span class="doc-icon">👤</span>
                        <span>CV</span>
                    </div>

                    <div class="doc-type">
                        <span class="doc-icon">📄</span>
                        <span>Cerere</span>
                    </div>

                    <div class="doc-type">
                        <span class="doc-icon">🧾</span>
                        <span>Factura</span>
                    </div>

                    <div class="doc-type">
                        <span class="doc-icon">📦</span>
                        <span>Catalog</span>
                    </div>

                    <div class="doc-type">
                        <span class="doc-icon">📑</span>
                        <span>Alte documente</span>
                    </div>

                </div>
            </div>
        </section>

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

        <script src="<?php echo BASE_URL; ?>/public/js/app.js"></script>

    </body>
</html>