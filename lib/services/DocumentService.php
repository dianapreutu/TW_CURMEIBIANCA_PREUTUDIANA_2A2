<?php

// lib/services/DocumentService.php - Serviciu pentru documente generate
// Centralizeaza listarea, obtinerea si stergerea documentelor
// ==================================================

class DocumentService
{
    private $db;
    private $engine;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->engine = new TemplateEngine();
    }

    public function listDocuments(?int $userId, bool $isAdmin): array
    {
        if ($isAdmin) {
            return $this->db->fetchAll(
                'SELECT d.*, t.name as template_name
                 FROM documents d
                 LEFT JOIN templates t ON d.template_id = t.id
                 ORDER BY d.created_at DESC'
            );
        }

        return $this->db->fetchAll(
            'SELECT d.*, t.name as template_name
             FROM documents d
             LEFT JOIN templates t ON d.template_id = t.id
             WHERE d.user_id = ?
             ORDER BY d.created_at DESC',
            [$userId]
        );
    }

    public function getDocument(int $id, ?int $userId, bool $isAdmin): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $query = 'SELECT d.*, t.name as template_name
            FROM documents d
            LEFT JOIN templates t ON d.template_id = t.id
            WHERE d.id = ?';
        $params = [$id];

        if (!$isAdmin) {
            $query .= ' AND d.user_id = ?';
            $params[] = $userId;
        }

        $document = $this->db->fetchOne($query, $params);

        if (!$document) {
            return null;
        }

        $filePath = GENERATED_HTML_PATH . '/' . $document['html_path'];
        if (file_exists($filePath)) {
            $document['html_content'] = file_get_contents($filePath);
        }

        return $document;
    }

    public function deleteDocument(int $id, ?int $userId, bool $isAdmin): void
    {
        if ($id <= 0) {
            throw new Exception('ID invalid!');
        }

        $query = 'SELECT id FROM documents WHERE id = ?';
        $params = [$id];

        if (!$isAdmin) {
            $query .= ' AND user_id = ?';
            $params[] = $userId;
        }

        $existing = $this->db->fetchOne($query, $params);
        if (!$existing) {
            throw new Exception('Documentul nu a fost gasit!');
        }

        $this->db->delete('documents', 'id = ?' . ($isAdmin ? '' : ' AND user_id = ?'), $params);
    }

    public function generateDocument(int $templateId, array $data, string $name, int $userId): array
    {
        return $this->engine->generateDocument($templateId, $data, $name, $userId);
    }
}
