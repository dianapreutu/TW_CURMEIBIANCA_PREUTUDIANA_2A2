<?php

// index.php
// Routerul principal al aplicatiei DoGen
// Citeste parametrul GET 'page' si include view-ul corespunzator

require_once 'config.php';

// Citim si sanitizam pagina ceruta (doar caractere alfanumerice si underscore)
$page = $_GET['page'] ?? 'home';
$page = preg_replace('/[^a-zA-Z0-9_]/', '', $page);

// Mapare pagini -> fisiere view
$pages = [
    'home'      => 'views/home.php',
    'editor'    => 'views/editor.php',
    'generator' => 'views/generator.php',
    'documents' => 'views/documents.php',
    'preview'   => 'views/preview.php',
    'admin'     => 'admin/index.php'
];

if (isset($pages[$page])) {
    $viewFile = $pages[$page];

    if (file_exists($viewFile)) {
        require_once $viewFile;
    } else {
        header('Location: views/home.php');
        exit;
    }
} else {
    // Pagina necunoscuta — redirectionam catre home
    header('Location: views/home.php');
    exit;
}