<?php

// ==================================================
// index.php - Routerul principal al aplicatiei
// Punctul de intrare al aplicatiei DoGen
// Redirectioneaza catre pagina principala sau
// gestioneaza rutele de baza ale aplicatiei
// ==================================================

// Includem configurarile globale
require_once 'config.php';

// Citim pagina ceruta din parametru GET
// Ex: index.php?page=editor sau index.php?page=generator
$page = $_GET['page'] ?? 'home';

// Curatam parametrul pentru a preveni atacurile
// Permitem doar caractere alfanumerice si underscore
$page = preg_replace('/[^a-zA-Z0-9_]/', '', $page);

// Definim paginile disponibile si fisierele corespunzatoare
$pages = [
    'home'      => 'views/home.php',
    'editor'    => 'views/editor.php',
    'generator' => 'views/generator.php',
    'documents' => 'views/documents.php',
    'preview'   => 'views/preview.php',
    'admin'     => 'views/admin.php'
];

// Verificam daca pagina ceruta exista
if (isset($pages[$page])) {
    // Construim calea catre fisierul view
    $viewFile = $pages[$page];

    // Verificam ca fisierul exista pe server
    if (file_exists($viewFile)) {
        // Includem fisierul view corespunzator
        require_once $viewFile;
    } else {
        // Fisierul nu exista - redirectionam catre home
        header('Location: views/home.php');
        exit;
    }
} else {
    // Pagina necunoscuta - redirectionam catre home
    header('Location: views/home.php');
    exit;
}