<?php

// ==================================================
// config.php - Configurari globale ale aplicatiei DoGen
// Acest fisier este inclus in toate celelalte fisiere PHP
// si contine constantele si setarile de baza ale aplicatiei
// ==================================================

// -- Setari generale --

// Numele aplicatiei (afisat in interfata)
define('APP_NAME', 'DoGen');

// Versiunea aplicatiei
define('APP_VERSION', '1.0.0');

// URL-ul de baza al aplicatiei (se modifica daca se schimba serverul)
define('BASE_URL', 'http://localhost/docgen');

// -- Cai catre directoare importante --

// Calea absoluta catre radacina proiectului
define('ROOT_PATH', dirname(__FILE__));

// Calea catre directorul cu baza de date
define('DB_PATH', ROOT_PATH . '/db/database.sqlite');

// Calea catre directorul cu sabloane (template-uri)
define('TEMPLATES_PATH', ROOT_PATH . '/templates');

// Calea unde se salveaza documentele generate (HTML)
define('GENERATED_HTML_PATH', ROOT_PATH . '/generated/html');

// Calea unde se salveaza documentele generate (PDF)
define('GENERATED_PDF_PATH', ROOT_PATH . '/generated/pdf');

// Calea unde se salveaza fisierele CSV incarcate de utilizatori
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// -- Setari pentru sesiune si securitate -- 

// Numele sesiunii (folosit pentru autentificarea in panoul admin)
define('SESSION_NAME', 'docgen_session');

// Parola pentru accesul in panoul de administrare
// IMPORTANT: in productie, aceasta parola trebuie stocata criptat
define('ADMIN_PASSWORD', 'admin1234');

// -- Setari pentru generarea de date --

// Numarul implicit de inregistrari generate cand utilizatorul nu specifica
define('DEFAULT_ROWS', 10);

// Numarul maxim de inregistrari care pot fi generate la o cerere
define('MAX_ROWS', 1000);

// -- Setari pentru afisarea erorilor --
// In dezvoltare afisam erorile. In productie: se seteaza pe 0

ini_set('display_errors', 1); // afiseaza erorile in browser
ini_set('display_startup_errors', 1); // afiseaza erorile de la pornire
error_reporting(E_ALL); // raporteaza toate tipurile de erori

// -- Setarea fusului orar --
// Important pentru functiile dinamice de data/timp din TemplateEngine
date_default_timezone_set('Europe/Bucharest');

// -- Pornirea sesiunii --
// Sesiunea este necesara pentru autentificarea in panoul admin
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME); // Setam numele sesiunii
    session_start(); // pornim sesiunea
}

// -- Autoload simplificat pentru clasele din /lib --
// Incarca automat fisierul clasei cand aceasta este folosita prima data
spl_autoload_register(function ($className) {
    // Construim calea catre fisierul clasei
    $file = ROOT_PATH . '/lib/' . $className . '.php';

    // Daca fisierul exista, il includem 
    if (file_exists($file)) {
        require_once $file;
    }
});