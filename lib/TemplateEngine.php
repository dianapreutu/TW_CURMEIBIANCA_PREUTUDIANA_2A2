<?php

// ==================================================
// lib/TemplateEngine.php - Motorul de templating
// Aceasta clasa se ocupa de procesarea sabloanelor:
// -> inlocuieste variabilele cu valori reale
// -> suporta conditii (if/else)
// -> suporta functii dinamice (data, ora etc.)
// Este componenta centrala a aplicatiei
// ==================================================

class TemplateEngine 
{
    // -- Proprietati private --

    // Instanta bazei de date (folosita pentru salvarea documentelor)
    private $db;

    // --------------------------------------------------
    // Constructorul - initializeaza conexiunea la baza de date 
    // --------------------------------------------------
    public function __construct()
    {
        // Obtinem instanta unica a bazei de date (Singleton)
        $this->db = Database::getInstance();
    }

    // --------------------------------------------------
    // render() - proceseaza un sablon si returneaza HTML-ul final 
    // $template - continutul sablon (string HTML cu variabile)
    // $data - array asociativ cu valorile variabilelor
    // --------------------------------------------------
    public function render($template, $data = [])
    {
        // Pasul 1: procesam functiile dinamice (ex: {{DATE}}, {{TIME}})
        $output = $this->processFunctions($template);

        // Pasul 2: procesam blocurile conditionale (ex: {{IF campul}} ... {{ENDIF}})
        $output = $this->processConditions($output, $data);

        // Pasul 3: inlocuim variabilele simple (ex: {{nume}}, {{email}})
        $output = $this->processVariables($output, $data);

        // Returnam HTML-ul final procesat
        return $output;
    }

    // --------------------------------------------------
    // processFunctions() - inlocuieste functiile dinamice din sablon
    // Functii disponibile:
    // {{DATE}} -> data curenta
    // {{TIME}} -> ora curenta
    // {{DATETIME}} -> data si ora curenta
    // {{YEAR}} -> anul curent
    // {{TIMESTAMP}} -> timestamp Unix curent
    // --------------------------------------------------
    private function processFunctions($template)
    {
        // Inlocuim {{DATE}} cu data curenta formatata
        $template = str_replace('{{DATE}}', date('d.m.Y'), $template);

        // Inlocuim {{TIME}} cu ora curenta
        $template = str_replace('{{TIME}}', date('H:i'), $template);

        // Inlocuim {{DATETIME}} cu data si ora curenta
        $template = str_replace('{{DATETIME}}', date('d.m.Y H:i'), $template);

        // Inlocuim {{YEAR}} cu anul curent
        $template = str_replace('{{YEAR}}', date('Y'), $template);

        // Inlocuim {{TIMESTAMP}} cu timestamp-ul Unix curent
        $template = str_replace('{{TIMESTAMP}}', time(), $template);

        // Returnam template-ul cu functiile inlocuite
        return $template;
    }

    // --------------------------------------------------
    // processConditions() - proceseaza blocurile conditionale
    // Sintaxa: {{IF variabila}}continut{{ENDIF}}
    // Sintaxa: {{IF variabila}}continut{{ELSE}}alt continut{{ENDIF}}
    // Daca variabila exista si nu e goala, afiseaza continutul
    // --------------------------------------------------
    private function processConditions($template, $data)
    {
        // Expresie regulata pentru a gasi blocurile {{IF}}...{{ENDIF}}
        // cu sau fara {{ELSE}}
        $pattern = '/\{\{IF\s+(\w+)\}\}(.*?)\{\{ELSE\}\}(.*?)\{\{ENDIF\}\}/s';

        // Procesam blocurile IF/ELSE/ENDIF
        $template = preg_replace_callback($pattern, function($matches) use ($data) {
            $variable = $matches[1]; // numele variabilei din conditie
            $ifContent = $matches[2]; // continutul din IF
            $elseContent = $matches[3]; // continutul din ELSE

            // Daca variabila exista in date si nu e goala, afisam IF
            if (!empty($data[$variable])) {
                return $ifContent;
            }

            // Altfel afisam continutul din ELSE
            return $elseContent;
        }, $template);

        // Expresie regulata pentru blocuri simple {{IF}}...{{ENDIF}} (fara ELSE)
        $pattern = '/\{\{IF\s+(\w+)\}\}(.*?)\{\{ENDIF\}\}/s';
        
        // Procesam blocurile IF/ENDIF simple
        $template = preg_replace_callback($pattern, function($matches) use ($data) {
            $variable = $matches[1]; // numele variabilei 
            $content = $matches[2]; // continutul blocului

            // Daca variabila exista si nu e goala, afisam continutul
            if (!empty($data[$variable])) {
                return $content;
            }

            // Altfel nu afisam nimic
            return '';
        }, $template);

        return $template;
    }

    // --------------------------------------------------
    // processVariables() - inlocuieste variabilele simple
    // Sintaxa: {{nume_variabila}}
    // Inlocuieste cu valoarea corespunzatoare din array-ul $data
    // Protejeaza impotriva XSS folosind htmlspecialchars()
    // --------------------------------------------------
    private function processVariables($template, $data)
    {
        // Parcurgem toate perechile cheie-valoare din date
        foreach ($data as $key => $value) {
            // Curatam valoarea pentru a preveni atacurile XSS
            // htmlspecialchars() transforma caracterele speciale in entitati HTML
            // ex: <script> devine &lt;script&gt;
            $safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

            // Inlocuim {{cheie}} cu valoarea curatata
            $template = str_replace('{{' . $key . '}}', $safeValue, $template);
        }

        // Returnam template-ul cu toate variabilele inlocuite
        return $template;
    }

    // --------------------------------------------------
    // loadTemplate() - incarca un sablon din baza de date
    // $id - ID-ul sablonului din tabela templates
    // Returneaza array-ul cu datele sablonului sau null
    // --------------------------------------------------
    public function loadTemplate($id)
    {
        // Cautam sablonul in baza de date dupa ID
        $template = $this->db->fetchOne(
            'SELECT * FROM templates WHERE id = ?',
            [$id]
        );

        // Returnam sablonul gasit (sau false daca nu exista)
        return $template;
    }

    // --------------------------------------------------
    // saveTemplate() - salveaza un sablon nou in baza de date
    // $name - numele sablonului 
    // $type - tipul documentului (cv, cerere, factura)
    // $content - continutul HTML al sablonului
    // $format - formatul: 'html' sau 'json'
    // $userId - ID-ul utilizatorului care salveaza 
    // --------------------------------------------------
    public function saveTemplate($name, $type, $content, $format = 'html', $userId = null)
    {
        // Inseram sablonul in baza de date
        $id = $this->db->insert('templates', [
            'name' => $name, 
            'type' => $type,
            'content' => $content, 
            'format' => $format,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Inregistram actiunea in logs
        $this->db->log('save_template', "Sablon salvat: {$name}", $userId);

        // Returnam ID-ul sablonului nou creat
        return $id;
    }

    // --------------------------------------------------
    // updateTemplate() - actualizeaza un sablon existent
    // $id - ID-ul sablonului de actualizat
    // $name - noul nume
    // $content - noul continut
    // --------------------------------------------------
    public function updateTemplate($id, $name, $content) 
    {
        // Actualizam sablonul in baza de date
        $this->db->update('templates', [
            'name' => $name,
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);

        // Inregistram actiunea in logs
        $this->db->log('update_template', "Sablon actualizat ID: {$id}");
    }

    // --------------------------------------------------
    // deleteTemplate() - sterge un sablon din baza de date 
    // $id - ID-ul sablonului de sters
    // --------------------------------------------------
    public function deleteTemplate($id)
    {
        // Stergem sablonul din baza de date
        $this->db->delete('templates', 'id = ?', [$id]);

        // Inregistram actiunea in logs
        $this->db->log('delete_template', "Sablon sters ID: {$id}");
    }

    // --------------------------------------------------
    // getAllTemplates() - returneaza toate sabloanele din baza de date
    // --------------------------------------------------
    public function getAllTemplates()
    {
        // Selectam toate sabloanele ordonate dupa data crearii
        return $this->db->fetchAll(
            'SELECT * FROM templates ORDER BY created_at DESC'
        );
    }

    // --------------------------------------------------
    // generateDocument() - genereaza un document final
    // Aplica datele pe sablon si salveaza fisierul HTML
    // $templateId - ID-ul sablonului folosit
    // $data - datele cu care se populeaza sablonul
    // $name - numele documentului generat
    // $userId - ID-ul utilizatorului
    // --------------------------------------------------
    public function generateDocument($templateId, $data, $name, $userId = null)
    {
        // Incarcam sablonul din baza de date
        $template = $this->loadTemplate($templateId);

        // Daca sablonul nu exista, aruncam o exceptie 
        if (!$template) {
            throw new Exception('Sablonul nu a fost gasit !');
        }

        // Procesam sablonul cu datele furnizate
        $html = $this->render($template['content'], $data);

        // Generam un nume unic pentru fisierul HTML
        $filename = uniqid('doc_') . '_' . time() . '.html';

        // Calea completa unde salvam fisierul
        $filePath = GENERATED_HTML_PATH . '/' . $filename;

        // Salvam fisierul HTML pe server
        file_put_contents($filePath, $html);

        // Salvam inregistrarea documentului in baza de date
        $docId = $this->db->insert('documents', [
            'name' => $name, 
            'template_id' => $templateId,
            'output_type' => 'html',
            'file_path'   => $filename,
            'data_source' => 'random',
            'created_by'  => $userId,
            'created_at'  => date('Y-m-d H:i:s')
        ]);

        // Inregistram actiunea in logs
        $this->db->log('generate_document', "Document generat: {$name}", $userId);

        // Returnam ID-ul documentului si calea fisierului
        return [
            'id' => $docId,
            'filename' => $filename,
            'html' => $html
        ];
    }
}