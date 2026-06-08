<?php

// config.php
// Configurari globale ale aplicatiei DoGen
// Inclus in toate fisierele PHP — defineste constante, sesiunea si autoload-ul


// ===== Setari generale =====

define('APP_NAME',    'DoGen');
define('APP_VERSION', '1.0.0');


// ===== URL de baza =====

// Detectam automat BASE_URL sau il citim din variabila de mediu
if (!defined('BASE_URL')) {
    $envBaseUrl = getenv('APP_BASE_URL');
    if ($envBaseUrl) {
        define('BASE_URL', rtrim($envBaseUrl, '/'));
    } else {
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (($_SERVER['SERVER_PORT'] ?? '') === '443')
        );
        $scheme    = $isHttps ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $scriptDir = rtrim($scriptDir, '/');

        // Eliminam subdirectoarele interne din calea detectata
        foreach (['/views', '/api', '/admin'] as $subdir) {
            if (substr($scriptDir, -strlen($subdir)) === $subdir) {
                $scriptDir = substr($scriptDir, 0, -strlen($subdir));
                break;
            }
        }

        define('BASE_URL', rtrim($scheme . '://' . $host . $scriptDir, '/'));
    }
}


// ===== Cai catre directoare =====

define('ROOT_PATH',           dirname(__FILE__));
define('DB_PATH',             ROOT_PATH . '/db/database.sqlite');
define('TEMPLATES_PATH',      ROOT_PATH . '/templates');
define('GENERATED_HTML_PATH', ROOT_PATH . '/generated/html');
define('GENERATED_PDF_PATH',  ROOT_PATH . '/generated/pdf');
define('UPLOADS_PATH',        ROOT_PATH . '/uploads');


// ===== Sesiune si securitate =====

define('SESSION_NAME', 'docgen_session');

// Parola adminului — se seteaza prin variabila de mediu in productie
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin1234');


// ===== Generare date =====

define('DEFAULT_ROWS', 10);
define('MAX_ROWS',     1000);


// ===== Mediu si afisare erori =====

// Se seteaza APP_ENV=production pe server pentru productie
define('APP_ENV', getenv('APP_ENV') ?: 'development');

if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

error_reporting(E_ALL);


// ===== Fus orar =====

// Necesar pentru functiile de data/timp din TemplateEngine
date_default_timezone_set('Europe/Bucharest');


// ===== Sesiune =====

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Bridge de compatibilitate: sincronizam $_SESSION['admin'] cu campurile user_id/role
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id']  = 1;
        $_SESSION['role']     = 'admin';
        $_SESSION['username'] = 'admin';
    }
}


// ===== Creare directoare necesare =====

$requiredPaths = [
    GENERATED_HTML_PATH,
    GENERATED_PDF_PATH,
    ROOT_PATH . '/generated/tmp',
    UPLOADS_PATH,
];

foreach ($requiredPaths as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}


// ===== Autoload =====

// Incarca automat clasele din /lib, /lib/core si /lib/services
spl_autoload_register(function ($className) {
    $paths = [
        ROOT_PATH . '/lib/services/' . $className . '.php',
        ROOT_PATH . '/lib/core/'     . $className . '.php',
        ROOT_PATH . '/lib/'          . $className . '.php',
    ];

    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});