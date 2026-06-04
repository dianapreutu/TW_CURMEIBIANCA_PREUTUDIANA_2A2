<?php

// api/export.php - API pentru exportul documentelor
// Gestioneaza exportul documentelor in formatele: HTML, PDF, CSV, JSON
// Metode HTTP suportate: POST
// Depinde de: lib/Database.php, lib/services/ExportService.php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=UTF-8');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId && isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    $userId = 1;
}
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Trebuie sa fii autentificat pentru a exporta documente']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Doar metoda POST este acceptata.'
    ]);
    exit;
}

$data = getRequestData();
$action = $data['action'] ?? '';
$exportService = new ExportService();

try {
    switch ($action) {
        case 'export_document':
            $result = $exportService->exportDocument(
                (int)($data['document_id'] ?? 0),
                $userId,
                strtolower(trim($data['format'] ?? ''))
            );
            jsonSuccess($result);
            break;

        case 'export_data':
            $result = $exportService->exportData(
                $data['fields'] ?? [],
                (int)($data['rows'] ?? DEFAULT_ROWS),
                strtolower(trim($data['format'] ?? '')),
                $userId
            );
            jsonSuccess($result);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Actiune necunoscuta: ' . htmlspecialchars($action)
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Eroare server: ' . $e->getMessage()
    ]);
}

function getRequestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }
    return $_POST;
}

function jsonSuccess(array $data): void
{
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
}