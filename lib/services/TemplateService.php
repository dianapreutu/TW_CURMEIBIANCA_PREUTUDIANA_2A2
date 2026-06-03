<?php

// lib/services/TemplateService.php - Serviciu pentru sabloane si logica de templating
// Extinde TemplateEngine pentru a expune operatiile de sablon ca servicii
// ==================================================

class TemplateService
{
    private $engine;

    public function __construct()
    {
        $this->engine = new TemplateEngine();
    }

    public function listTemplates(): array
    {
        return $this->engine->getAllTemplates();
    }

    public function getTemplate(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->engine->loadTemplate($id);
    }

    public function createTemplate(string $name, string $type, string $content, string $format = 'html', ?int $userId = null): int
    {
        if (empty($name) || empty($type) || empty($content)) {
            throw new Exception('Campurile nume, tip si continut sunt obligatorii!');
        }

        $allowedTypes = ['cv', 'cerere', 'factura', 'catalog', 'alt'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new Exception('Tipul de sablon este invalid!');
        }

        if (!in_array($format, ['html', 'json'], true)) {
            throw new Exception('Formatul trebuie sa fie html sau json!');
        }

        return $this->engine->saveTemplate($name, $type, $content, $format, $userId);
    }

    public function updateTemplate(int $id, string $name, string $content): void
    {
        if ($id <= 0) {
            throw new Exception('ID invalid!');
        }

        if (empty($name) || empty($content)) {
            throw new Exception('Campurile nume si continut sunt obligatorii!');
        }

        $existing = $this->engine->loadTemplate($id);
        if (!$existing) {
            throw new Exception('Sablonul nu a fost gasit!');
        }

        $this->engine->updateTemplate($id, $name, $content);
    }

    public function deleteTemplate(int $id): void
    {
        if ($id <= 0) {
            throw new Exception('ID invalid!');
        }

        $existing = $this->engine->loadTemplate($id);
        if (!$existing) {
            throw new Exception('Sablonul nu a fost gasit!');
        }

        $this->engine->deleteTemplate($id);
    }
}
