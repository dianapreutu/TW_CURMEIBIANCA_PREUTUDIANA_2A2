<?php

// lib/core/PdfExporter.php
// Serviciu pentru exportul documentelor in format PDF
// Foloseste libraria mPDF daca e disponibila, altfel un fallback minimal
// Depinde de: lib/mpdf/autoload.php (optional), Database, TemplateEngine

class PdfExporter
{
    private $db;
    private $templateEngine;
    private $outputPath;

    public function __construct($db, $templateEngine)
    {
        $this->db             = $db;
        $this->templateEngine = $templateEngine;
        $this->outputPath     = GENERATED_PDF_PATH;

        // Cream directorul de output daca nu exista
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    // Converteste HTML in PDF si returneaza calea fisierului generat
    public function exportFromHtml(string $html, string $filename = 'document', int $userId = null, int $templateId = 0): string
    {
        $safeName    = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $pdfFilename = $safeName . '_' . date('Ymd_His') . '.pdf';
        $pdfPath     = $this->outputPath . '/' . $pdfFilename;

        // Verificam daca mPDF e disponibil; altfel folosim fallback-ul minimal
        $mpdfPath = ROOT_PATH . '/vendor/autoload.php';

        if (file_exists($mpdfPath)) {
            $pdfPath = $this->exportWithMpdf($html, $pdfPath, $templateId);
        } else {
            $pdfPath = $this->exportWithFallback($html, $pdfPath);
        }

        if ($userId) {
            $this->db->log('export', 'Export PDF: ' . $pdfFilename, $userId);
        }

        return $pdfPath;
    }

    // Exporta un document existent din baza de date in format PDF
    public function exportFromDocument(int $documentId, int $userId = null): string
    {
        $document = $this->db->fetchOne(
            'SELECT * FROM documents WHERE id = ?',
            [$documentId]
        );

        if (!$document) {
            throw new Exception('Documentul nu a fost gasit in baza de date.');
        }

        $htmlPath = GENERATED_HTML_PATH . '/' . $document['html_path'];

        if (!file_exists($htmlPath)) {
            throw new Exception('Fisierul HTML al documentului nu a fost gasit.');
        }

        $html = file_get_contents($htmlPath);

        // Daca fisierul contine un document HTML complet, extragem doar body-ul
        // pentru a evita dublarea stilurilor in mPDF
        if (strpos($html, '<!DOCTYPE html>') !== false || strpos($html, '<html') !== false) {
            preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches);
            if (!empty($matches[1])) {
                $html = $matches[1];
            }
            // Eliminam tagurile de stil inline care ar aparea ca text
            $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        }

        $pdfPath = $this->exportFromHtml(
            $html,
            pathinfo($document['html_path'], PATHINFO_FILENAME),
            $userId,
            (int)($document['template_id'] ?? 0)
        );

        $pdfFilename = basename($pdfPath);
        $this->db->update(
            'documents',
            [
                'pdf_path'   => $pdfFilename,
                'status'     => 'exported',
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$documentId]
        );

        $this->db->insert('exports', [
            'document_id' => $documentId,
            'user_id'     => $userId,
            'format'      => 'pdf',
            'file_path'   => $pdfFilename
        ]);

        return $pdfPath;
    }

    // Genereaza un document HTML dintr-un sablon si il exporta direct ca PDF
    public function exportFromTemplate(int $templateId, array $data, string $name, int $userId = null): array
    {
        $generated = $this->templateEngine->generateDocument(
            $templateId,
            $data,
            $name,
            $userId
        );

        $pdfPath     = $this->exportFromHtml($generated['html'], 'doc_' . $generated['id'], $userId);
        $pdfFilename = basename($pdfPath);

        $this->db->update(
            'documents',
            [
                'pdf_path'   => $pdfFilename,
                'status'     => 'exported',
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$generated['id']]
        );

        return [
            'document_id'  => $generated['id'],
            'html_path'    => GENERATED_HTML_PATH . '/' . $generated['filename'],
            'pdf_path'     => $pdfPath,
            'pdf_filename' => $pdfFilename
        ];
    }

    // Trimite PDF-ul catre browser si declanseaza descarcarea directa
    public function downloadPdf(string $pdfPath, string $filename = 'document'): void
    {
        if (!file_exists($pdfPath)) {
            throw new Exception('Fisierul PDF nu a fost gasit.');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $safeName . '.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($pdfPath);
        exit;
    }

    // Genereaza PDF de calitate folosind libraria mPDF
    // Suporta diacritice, CSS si imagini
    private function exportWithMpdf(string $html, string $outputPath, int $templateId = 0): string
    {
        require_once ROOT_PATH . '/vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 20,
            'margin_bottom' => 20,
            'margin_left'   => 20,
            'margin_right'  => 20,
            'tempDir'       => ROOT_PATH . '/generated/tmp'
        ]);

        $mpdf->SetAuthor(APP_NAME);
        $mpdf->SetCreator(APP_NAME . ' v' . APP_VERSION);

        // Determinam tipul documentului dupa template_id pentru stilizare
        $cerereIds  = [2];
        $facturaIds = [3];

        if (in_array($templateId, $cerereIds)) {
            $type  = 'cerere';
            $title = 'CERERE';
            $color = '#1a3a5c';
        } elseif (in_array($templateId, $facturaIds)) {
            $type  = 'factura';
            $title = 'FACTURA';
            $color = '#1a5c2a';
        } else {
            $type  = 'cv';
            $title = 'CURRICULUM VITAE';
            $color = '#3a1a5c';
        }

        $mpdf->SetTitle($title);

        $css = '
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11pt;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .doc-header {
            text-align: center;
            border-bottom: 3px solid ' . $color . ';
            margin-bottom: 24px;
            padding-bottom: 12px;
        }
        .doc-header h1 {
            font-size: 22pt;
            color: ' . $color . ';
            margin: 0 0 4px 0;
            letter-spacing: 2px;
        }
        .doc-header .subtitle {
            font-size: 9pt;
            color: #888;
        }
        p {
            margin: 10px 0;
            line-height: 1.7;
        }
        strong {
            color: ' . $color . ';
            min-width: 160px;
            display: inline-block;
        }
        .doc-footer {
            margin-top: 40px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            font-size: 9pt;
            color: #aaa;
            text-align: center;
        }
        ';

        // Adaugam stiluri suplimentare specifice tipului de document
        if ($type === 'factura') {
            $css .= '
            table { width:100%; border-collapse:collapse; margin:16px 0; }
            th { background:' . $color . '; color:white; padding:8px; text-align:left; }
            td { padding:7px 8px; border-bottom:1px solid #ddd; }
            tr:nth-child(even) td { background:#f0f7f0; }
            ';
        } elseif ($type === 'cv') {
            $css .= '
            h2 { color:' . $color . '; font-size:13pt; border-bottom:1px solid #ccc; padding-bottom:3px; margin-top:18px; }
            ';
        }

        // Eliminam header-ul duplicat din HTML-ul original
        $html = preg_replace('/<div[^>]*class="doc-header"[^>]*>.*?<\/div>/s', '', $html);
        $html = preg_replace('/<div[^>]*class="doc-footer"[^>]*>.*?<\/div>/s', '', $html);
        $html = preg_replace('/<h1[^>]*>.*?<\/h1>/s', '', $html);

        // Eliminam si tagurile de wrapper ramase
        $html = preg_replace('/<\/?div[^>]*class="doc-(wrapper|body|header|footer|subtitle)"[^>]*>/s', '', $html);

        $html = preg_replace('/CURRICULUM VITAE\s*/i', '', $html);
        $html = preg_replace('/CERERE\s*/i', '', $html);
        $html = preg_replace('/FACTURA\s*/i', '', $html);

        $styledHtml = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>' . $css . '</style>
</head>
<body>
<div class="doc-header">
    <h1>' . $title . '</h1>
    <div class="subtitle">Generat de ' . APP_NAME . ' &bull; ' . date('d.m.Y') . '</div>
</div>
' . $html . '
<div class="doc-footer">
    Document generat automat ' . date('d.m.Y H:i') . '
</div>
</body>
</html>';

        $mpdf->WriteHTML($styledHtml);
        $mpdf->Output($outputPath, 'F');

        return $outputPath;
    }

    // Genereaza un PDF minimal fara librarie externa
    // Suporta doar text simplu, fara CSS avansat
    private function exportWithFallback(string $html, string $outputPath): string
    {
        // Convertim tagurile HTML in newline-uri pentru a pastra structura textului
        $html = preg_replace('/<\s*br\s*\/?>/i',  "\n",   $html);
        $html = preg_replace('/<\/p\s*>/i',        "\n\n", $html);
        $html = preg_replace('/<\/div\s*>/i',      "\n",   $html);
        $html = preg_replace('/<\/li\s*>/i',       "\n",   $html);

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/",  ' ',    $text);
        $text = preg_replace("/\n{3,}/",  "\n\n", $text);
        $text = trim($text);
        $text = wordwrap($text, 80, "\n", true);

        // Construim structura PDF minima conform specificatiei PDF 1.4
        $pdf  = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // Construim continutul paginii din liniile de text extrase
        $lines   = explode("\n", $text);
        $content = "BT\n/F1 11 Tf\n50 800 Td\n12 TL\n";
        foreach ($lines as $line) {
            $line     = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= "(" . $line . ") Tj T*\n";
        }
        $content .= "ET\n";

        $contentLength = strlen($content);

        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R ";
        $pdf .= "/MediaBox [0 0 595 842] ";
        $pdf .= "/Contents 4 0 R ";
        $pdf .= "/Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

        $pdf .= "4 0 obj\n<< /Length {$contentLength} >>\nstream\n";
        $pdf .= $content;
        $pdf .= "endstream\nendobj\n";

        // Font standard PDF (Helvetica)
        $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 ";
        $pdf .= "/BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Cross-reference table si trailer
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \n";
        $pdf .= "0000000009 00000 n \n";
        $pdf .= "0000000058 00000 n \n";
        $pdf .= "0000000115 00000 n \n";
        $pdf .= "0000000266 00000 n \n";
        $pdf .= "0000000" . str_pad(266 + $contentLength + 50, 9, '0', STR_PAD_LEFT) . " 00000 n \n";
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        file_put_contents($outputPath, $pdf);

        return $outputPath;
    }

    // Returneaza lista PDF-urilor generate, filtrata optional dupa utilizator
    public function getPdfList(int $userId = null): array
    {
        if ($userId) {
            return $this->db->fetchAll(
                'SELECT e.*, d.title as document_title
                 FROM exports e
                 JOIN documents d ON e.document_id = d.id
                 WHERE e.user_id = ? AND e.format = "pdf"
                 ORDER BY e.exported_at DESC',
                [$userId]
            );
        }

        return $this->db->fetchAll(
            'SELECT e.*, d.title as document_title
             FROM exports e
             JOIN documents d ON e.document_id = d.id
             WHERE e.format = "pdf"
             ORDER BY e.exported_at DESC'
        );
    }
}