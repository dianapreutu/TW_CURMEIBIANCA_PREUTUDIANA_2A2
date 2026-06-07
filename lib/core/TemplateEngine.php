<?php

// lib/core/TemplateEngine.php
// Motorul de templating al aplicatiei
// Proceseaza sabloanele: inlocuieste variabile, evalueaza conditii,
// aplica functii dinamice si genereaza documentele HTML finale

class TemplateEngine
{
    private $db;

    public function __construct()
    {
        // Obtinem instanta unica a bazei de date
        $this->db = Database::getInstance();
    }

    // Proceseaza un sablon si returneaza HTML-ul final
    // Aplica in ordine: functii dinamice, conditii, variabile
    public function render($template, $data = [])
    {
        $output = $this->processFunctions($template);
        $output = $this->processConditions($output, $data);
        $output = $this->processVariables($output, $data);

        return $output;
    }

    // Inlocuieste functiile dinamice din sablon cu valorile curente
    // Functii disponibile: {{DATE}}, {{TIME}}, {{DATETIME}}, {{YEAR}}, {{TIMESTAMP}}
    private function processFunctions($template)
    {
        $template = str_replace('{{DATE}}',      date('d.m.Y'),    $template);
        $template = str_replace('{{TIME}}',      date('H:i'),      $template);
        $template = str_replace('{{DATETIME}}',  date('d.m.Y H:i'),$template);
        $template = str_replace('{{YEAR}}',      date('Y'),        $template);
        $template = str_replace('{{TIMESTAMP}}', time(),           $template);

        return $template;
    }

    // Proceseaza blocurile conditionale din sablon
    // Sintaxa suportata: {{IF var}}...{{ENDIF}} si {{IF var}}...{{ELSE}}...{{ENDIF}}
    private function processConditions($template, $data)
    {
        // Procesam blocurile IF/ELSE/ENDIF
        $pattern  = '/\{\{IF\s+(\w+)\}\}(.*?)\{\{ELSE\}\}(.*?)\{\{ENDIF\}\}/s';
        $template = preg_replace_callback($pattern, function($matches) use ($data) {
            return !empty($data[$matches[1]]) ? $matches[2] : $matches[3];
        }, $template);

        // Procesam blocurile IF/ENDIF simple (fara ELSE)
        $pattern  = '/\{\{IF\s+(\w+)\}\}(.*?)\{\{ENDIF\}\}/s';
        $template = preg_replace_callback($pattern, function($matches) use ($data) {
            return !empty($data[$matches[1]]) ? $matches[2] : '';
        }, $template);

        return $template;
    }

    // Inlocuieste variabilele simple {{cheie}} cu valorile din $data
    // Valorile sunt escapate pentru a preveni XSS
    private function processVariables($template, $data)
    {
        foreach ($data as $key => $value) {
            $safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            $template  = str_replace('{{' . $key . '}}', $safeValue, $template);
        }

        return $template;
    }

    // Incarca un sablon din baza de date dupa ID
    public function loadTemplate($id)
    {
        return $this->db->fetchOne(
            'SELECT * FROM templates WHERE id = ?',
            [$id]
        );
    }

    // Salveaza un sablon nou in baza de date
    public function saveTemplate($name, $type, $content, $format = 'html', $userId = null)
    {
        $id = $this->db->insert('templates', [
            'name'        => $name,
            'label'       => $type,
            'fields_json' => $content,
            'filename'    => $name . '.json'
        ]);

        $this->db->log('save_template', "Sablon salvat: {$name}", $userId);

        return $id;
    }

    // Actualizeaza numele si continutul unui sablon existent
    public function updateTemplate($id, $name, $content)
    {
        $this->db->update('templates', [
            'name'        => $name,
            'fields_json' => $content
        ], 'id = ?', [$id]);

        $this->db->log('update_template', "Sablon actualizat ID: {$id}");
    }

    // Sterge un sablon din baza de date dupa ID
    public function deleteTemplate($id)
    {
        $this->db->delete('templates', 'id = ?', [$id]);
        $this->db->log('delete_template', "Sablon sters ID: {$id}");
    }

    // Returneaza toate sabloanele din baza de date, ordonate dupa data crearii
    public function getAllTemplates()
    {
        return $this->db->fetchAll(
            'SELECT * FROM templates ORDER BY created_at DESC'
        );
    }

    // Genereaza un document HTML pe baza unui sablon si a datelor primite
    // Salveaza fisierul pe server si inregistreaza documentul in baza de date
    public function generateDocument($templateId, $data, $name, $userId = null)
    {
        $template = $this->loadTemplate($templateId);

        if (!$template) {
            throw new Exception('Sablonul nu a fost gasit!');
        }

        $fieldsJson = $template['fields_json'];
        $decoded    = json_decode($fieldsJson, true);

        // Construim HTML-ul din campurile JSON sau folosim continutul direct
        if (is_array($decoded)) {
            $htmlTemplate = '';
            foreach ($decoded as $field) {
                $label        = htmlspecialchars($field['label'] ?? $field['field'], ENT_QUOTES, 'UTF-8');
                $var          = '{{' . $field['field'] . '}}';
                $htmlTemplate .= '<p><strong>' . $label . ':</strong> ' . $var . '</p>' . "\n";
            }
        } else {
            $htmlTemplate = $fieldsJson;
        }

        $html = $this->render($htmlTemplate, $data);

        // Determinam tipul documentului pentru stilizare
        $cerereIds  = [2];
        $facturaIds = [3];

        if (in_array($templateId, $cerereIds)) {
            $docTitle = 'CERERE';
            $color    = '#1a3a5c';
        } elseif (in_array($templateId, $facturaIds)) {
            $docTitle = 'FACTURA';
            $color    = '#1a5c2a';
        } else {
            $docTitle = 'CURRICULUM VITAE';
            $color    = '#3a1a5c';
        }

        $styledHtml = '<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $docTitle . '</title>
<style>
    body { font-family: Arial, sans-serif; font-size: 12pt; color: #222; max-width: 800px; margin: 40px auto; padding: 0 24px; background: #f9f9f9; }
    .doc-header { text-align: center; border-bottom: 3px solid ' . $color . '; margin-bottom: 28px; padding-bottom: 14px; }
    .doc-header h1 { font-size: 24pt; color: ' . $color . '; margin: 0 0 4px 0; letter-spacing: 2px; }
    .doc-header .subtitle { font-size: 9pt; color: #888; }
    .doc-body { background: white; padding: 28px 32px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
    p { margin: 10px 0; line-height: 1.7; }
    strong { color: ' . $color . '; min-width: 160px; display: inline-block; }
    table { width: 100%; border-collapse: collapse; margin: 16px 0; }
    th { background: ' . $color . '; color: white; padding: 8px; text-align: left; }
    td { padding: 7px 8px; border-bottom: 1px solid #ddd; }
    tr:nth-child(even) td { background: #f5f5f5; }
    h2 { color: ' . $color . '; font-size: 13pt; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-top: 18px; }
    .doc-footer { margin-top: 32px; border-top: 1px solid #ddd; padding-top: 10px; font-size: 9pt; color: #aaa; text-align: center; }
</style>
</head>
<body>
<div class="doc-header">
    <h1>' . $docTitle . '</h1>
    <div class="subtitle">Generat la ' . date('d.m.Y H:i') . '</div>
</div>
<div class="doc-body">
' . $html . '
</div>
<div class="doc-footer">Document generat automat &bull; ' . date('d.m.Y H:i') . '</div>
</body>
</html>';

        // Salvam fisierul HTML pe server cu un nume unic
        $filename = uniqid('doc_') . '_' . time() . '.html';
        $filePath = GENERATED_HTML_PATH . '/' . $filename;
        file_put_contents($filePath, $styledHtml);

        // Inregistram documentul in baza de date
        $docId = $this->db->insert('documents', [
            'title'       => $name,
            'template_id' => $templateId,
            'html_path'   => $filename,
            'status'      => 'generated',
            'user_id'     => $userId ?? 1,
            'rows_count'  => 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s')
        ]);

        $this->db->log('generate_document', "Document generat: {$name}", $userId);

        return [
            'id'       => $docId,
            'filename' => $filename,
            'html'     => $styledHtml
        ];
    }
}