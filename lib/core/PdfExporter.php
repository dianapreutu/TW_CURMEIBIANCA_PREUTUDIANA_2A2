<?php

// ==================================================
// lib/core/PdfExporter.php - Serviciu pentru exportul in format PDF
// converteste documentele HTML generate in fisiere PDF
// foloseste libraria mPDF (inclusa manual, fara framework)
// ==================================================

class PdfExporter
{
    // instanta bazei de date
    private $db;

    // instanta motorului de templating
    private $templateEngine;

    // directorul unde se salveaza PDF-urile generate
    private $outputPath;

    // constructor: $db - instanta Database::getInstance()
    // $templateEngine - instanta TemplateEngine

    public function __construct($db, $templateEngine)
    {
        $this->db             = $db;
        $this->templateEngine = $templateEngine;
        $this->outputPath     = GENERATED_PDF_PATH;

        // verificam ca directorul de output exista
        // daca nu, il cream
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    // exportFromHtml() - converteste HTML in PDF (metoda principala de export)
    // $html     - continutul HTML de convertit
    // $filename - numele fisierului PDF (fara extensie)
    // $userId   - id-ul utilizatorului (pentru logare)
    // returneaza calea catre fisierul PDF generat

    public function exportFromHtml(string $html, string $filename = 'document', int $userId = null, int $templateId = 0): string
    {
        // generam un nume unic pentru fisier
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $pdfFilename = $safeName . '_' . date('Ymd_His') . '.pdf';
        $pdfPath     = $this->outputPath . '/' . $pdfFilename;

        // verificam daca mPDF e disponibil
        // mPDF trebuie instalat manual in /lib/mpdf/
        $mpdfPath = ROOT_PATH . '/lib/mpdf/autoload.php';

        file_put_contents(ROOT_PATH . '/mpdf_debug.txt', $mpdfPath . ' exists=' . (file_exists($mpdfPath) ? 'YES' : 'NO'));
if (file_exists($mpdfPath)) {
            // varianta cu mPDF (calitate mai buna) 
           $pdfPath = $this->exportWithMpdf($html, $pdfPath, $templateId);
        } else {
            // varianta fallback: HTML2PDF simplu (pentru siguranta, ca sa nu crape aplicatia daca nu e instalat mPDF)
            // generam un PDF minimal fara librarie externa
            $pdfPath = $this->exportWithFallback($html, $pdfPath);
        }

        // logam actiunea
        if ($userId) {
            $this->db->log(
                'export',
                'Export PDF: ' . $pdfFilename,
                $userId
            );
        }

        return $pdfPath;
    }

    // exportFromDocument() - exporta un document din DB in PDF
    // $documentId - id-ul documentului din tabela documents
    // $userId     - id-ul utilizatorului
    // Returneaza calea catre fisierul PDF generat

    public function exportFromDocument(int $documentId, int $userId = null): string
    {
        // Incarcam documentul din baza de date
        $document = $this->db->fetchOne(
            'SELECT * FROM documents WHERE id = ?',
            [$documentId]
        );

        if (!$document) {
            throw new Exception('Documentul nu a fost gasit in baza de date.');
        }

        // Citim fisierul HTML salvat al documentului
        $htmlPath = GENERATED_HTML_PATH . '/' . $document['html_path'];

        if (!file_exists($htmlPath)) {
            throw new Exception('Fisierul HTML al documentului nu a fost gasit.');
        }

        $html = file_get_contents($htmlPath);

        // Exportam HTML-ul ca PDF
       $pdfPath = $this->exportFromHtml(
    $html,
    pathinfo($document['html_path'], PATHINFO_FILENAME),
    $userId,
    (int)($document['template_id'] ?? 0)
);

        // Actualizam documentul in DB cu calea PDF-ului
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

        // Inregistram exportul in tabela exports
        $this->db->insert('exports', [
            'document_id' => $documentId,
            'user_id'     => $userId,
            'format'      => 'pdf',
            'file_path'   => $pdfFilename
        ]);

        return $pdfPath;
    }

    // exportFromTemplate() - genereaza si exporta direct ca PDF
    // combina TemplateEngine::generateDocument() cu exportul PDF
    // $templateId - id-ul sablonului
    // $data       - datele pentru popularea sablonului
    // $name       - numele documentului
    // $userId     - id-ul utilizatorului
    // returneaza array cu caile HTML si PDF generate

    public function exportFromTemplate(int $templateId, array $data, string $name, int $userId = null): array
    {
        // Generam documentul HTML via TemplateEngine
        $generated = $this->templateEngine->generateDocument(
            $templateId,
            $data,
            $name,
            $userId
        );

        // exportam HTML-ul generat ca PDF
        $pdfPath = $this->exportFromHtml(
            $generated['html'],
            'doc_' . $generated['id'],
            $userId
        );

        // actualizam documentul in DB cu calea PDF
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
            'document_id' => $generated['id'],
            'html_path'   => GENERATED_HTML_PATH . '/' . $generated['filename'],
            'pdf_path'    => $pdfPath,
            'pdf_filename'=> $pdfFilename
        ];
    }

    // downloadPdf() - trimite PDF-ul catre browser (declanseaza descarcarea directa)
    // $pdfPath  - calea catre fisierul PDF pe disk
    // $filename - numele fisierului pentru download

    public function downloadPdf(string $pdfPath, string $filename = 'document'): void
    {
        if (!file_exists($pdfPath)) {
            throw new Exception('Fisierul PDF nu a fost gasit.');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

        // Setam headerele HTTP pentru descarcare
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $safeName . '.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        header('Pragma: no-cache');
        header('Expires: 0');

        // trimitem continutul fisierului
        readfile($pdfPath);
        exit;
    }

    // METODE PRIVATE DE GENERARE PDF

    // exportWithMpdf() - genereaza PDF cu libraria mPDF
    // mPDF produce PDF-uri de calitate profesionala
    // suporta diacritice, CSS, imagini

private function exportWithMpdf(string $html, string $outputPath, int $templateId = 0): string
{
    require_once ROOT_PATH . '/lib/mpdf/autoload.php';

    $mpdf = new \Mpdf\Mpdf([
        'mode'         => 'utf-8',
        'format'       => 'A4',
        'orientation'  => 'P',
        'margin_top'   => 20,
        'margin_bottom'=> 20,
        'margin_left'  => 20,
        'margin_right' => 20,
        'tempDir'      => ROOT_PATH . '/generated/tmp'
    ]);

    $mpdf->SetAuthor(APP_NAME);
    $mpdf->SetCreator(APP_NAME . ' v' . APP_VERSION);

    // Detectam tipul documentului
    $cerereIds = [2];
    $facturaIds = [3];
    // CV = orice altceva

    if (in_array($templateId, $cerereIds)) {
        $type = 'cerere';
        $title = 'CERERE';
        $color = '#1a3a5c';
    } elseif (in_array($templateId, $facturaIds)) {
        $type = 'factura';
        $title = 'FACTURĂ';
        $color = '#1a5c2a';
    } else {
        $type = 'cv';
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

    // Stil suplimentar per tip
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
    Document generat automat &bull; ' . date('d.m.Y H:i') . '
</div>
</body>
</html>';

    $mpdf->WriteHTML($styledHtml);
    $mpdf->Output($outputPath, 'F');

    return $outputPath;
}
    // exportWithFallback() - genereaza PDF fara librarie externa
    // varianta simpla folosind structura PDF minima scrisa manual
    // suporta doar text simplu (fara CSS avansat)
  
    private function exportWithFallback(string $html, string $outputPath): string
    {
       // Pregatim HTML-ul ca sa pastram randurile/paragrafele in PDF
$html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
$html = preg_replace('/<\/p\s*>/i', "\n\n", $html);
$html = preg_replace('/<\/div\s*>/i', "\n", $html);
$html = preg_replace('/<\/li\s*>/i', "\n", $html);

// Extragem textul din HTML
$text = strip_tags($html);

// Decodam entitatile HTML
$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Curatam spatiile, dar pastram newline-urile
$text = preg_replace("/[ \t]+/", ' ', $text);
$text = preg_replace("/\n{3,}/", "\n\n", $text);
$text = trim($text);

$text = wordwrap($text, 80, "\n", true);
        // construim un PDF minimal valid
        // structura PDF de baza conform specificatiei PDF 1.4
        $pdf = "%PDF-1.4\n";

        // obiectul 1: catalog
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // obiectul 2: pagini
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // pregatim continutul paginii (text)
        $lines   = explode("\n", $text);
        $content = "BT\n/F1 11 Tf\n50 800 Td\n12 TL\n";
        foreach ($lines as $line) {
            // Escapam caracterele speciale PDF
            $line     = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= "(" . $line . ") Tj T*\n";
        }
        $content .= "ET\n";

        $contentLength = strlen($content);

        // obiectul 3: pagina A4
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R ";
        $pdf .= "/MediaBox [0 0 595 842] ";
        $pdf .= "/Contents 4 0 R ";
        $pdf .= "/Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

        // obiectul 4: continutul paginii
        $pdf .= "4 0 obj\n<< /Length {$contentLength} >>\nstream\n";
        $pdf .= $content;
        $pdf .= "endstream\nendobj\n";

        // obiectul 5: fontul (Helvetica standard PDF)
        $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 ";
        $pdf .= "/BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        // cross-reference table
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \n";
        $pdf .= "0000000009 00000 n \n";
        $pdf .= "0000000058 00000 n \n";
        $pdf .= "0000000115 00000 n \n";
        $pdf .= "0000000266 00000 n \n";
        $pdf .= "0000000" . str_pad(266 + $contentLength + 50, 9, '0', STR_PAD_LEFT) . " 00000 n \n";

        // trailer
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        // scriem fisierul PDF
        file_put_contents($outputPath, $pdf);

        return $outputPath;
    }

    // getPdfList() - returneaza lista PDF-urilor generate
    // $userId - filtreaza dupa utilizator

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
