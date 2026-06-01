// ==================================================
// public/js/editor.js - Logica editorului de sabloane 
// Gestioneaza editarea sabloanelor HTML, previzualizarea
// in timp real si salvarea via AJAX
// ==================================================

// -- Variabile globale ale editorului --

// ID-ul sablonului curent editat (null = sablon nou)
let currentTemplateId = null;

// Continutul original al sablonului (pentru detectarea modificarilor)
let originalContent = '';

// ==================================================
// INITIALIZARE
// ==================================================

document.addEventListener('DOMContentLoaded', function () {

    // Incarcam lista de sabloane existente
    loadTemplates();

    // Configuram butonul de salvare
    const saveBtn = document.getElementById('btn-save');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveTemplate);
    }

    // Configuram butonul de sablon nou
    const newBtn = document.getElementById('btn-new');
    if (newBtn) {
        newBtn.addEventListener('click', newTemplate);
    }

    // Configuram butonul de previzualizare
    const previewBtn = document.getElementById('btn-preview');
    if (previewBtn) {
        previewBtn.addEventListener('click', updatePreview);
    }

    // Configuram butonul de generare document
    const generateBtn = document.getElementById('btn-generate');
    if (generateBtn) {
        generateBtn.addEventListener('click', generateDocument);
    }

    // Actualizam previzualizarea automat la tastare
    // Folosim debounce pentru a nu actualiza la fiecare tasta
    const textarea = document.getElementById('editor-content');
    if (textarea) {
        textarea.addEventListener('input', debounce(updatePreview, 500));
    }

    // Configuram butoanele din toolbar (inserare variabile)
    setupToolbar(); 
});

// ==================================================
// FUNCTII TOOLBAR
// ==================================================

/**
 * setupToolbar() - configureaza butoanele din toolbar 
 * Fiecare buton insereaza o variabila/functie in editor
 */
function setupToolbar() {

    // Obtinem toate butoanele din toolbar
    const toolbarBtns = document.querySelectorAll('.toolbar-btn');

    toolbarBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            // Citim valoarea de inserat din atributul data-insert
            const insertValue = btn.getAttribute('data-insert');
            if (insertValue) {
                // Inseram valoarea la pozitia cursorului in editor
                insertAtCursor(insertValue);
                // Actualizam previzualizarea dupa insertie
                updatePreview();
            }
        });
    });
}

/**
 * insertAtCursor() - insereaza text la pozitia cursorului 
 * @param {string} text - textul de inserat
 */
function insertAtCursor(text) {

    // Obtinem textarea editorului
    const textarea = document.getElementById('editor-content');
    if (!textarea)
        return;

    // Obtinem pozitia cursorului
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;

    // Construim noul continut cu textul inserat
    const before = textarea.value.substring(0, start);
    const after = textarea.value.substring(end);
    textarea.value = before + text + after;

    // Mutam cursorul dupa textul inserat
    const newPos = start + text.length;
    textarea.selectionStart = newPos;
    textarea.selectionEnd = newPos;

    // Refocusam textarea
    textarea.focus();
}

// ==================================================
// PREVIZUALIZARE
// ==================================================

/**
 * updatePreview() - actualizeaza panoul de previzualizare
 * Trimite continutul editorului la API pentru procesare
 */
function updatePreview() {

    // Obtinem continutul editorului
    const textarea = document.getElementById('editor-content');
    if (!textarea)
        return;

    const content = textarea.value.trim();

    // Daca editorul e gol, afisam mesajul implicit
    if (!content) {
        const previewDoc = document.getElementById('preview-document');
        if (previewDoc) {
            previewDoc.innerHTML = '<p class="preview-empty">Scrieti continut in editor pentru previzualizare...</p>';
        }
        return;
    }

    // Generam date de test pentru previzualizare
    // Folosim date statice simple pentru o previzualizare rapida
    const testData = {
        nume: 'Ion Popescu',
        email: 'ion.popescu@gmail.com',
        telefon: '0721 234 567',
        adresa: 'Strada Florilor nr. 10, Bucuresti',
        data: '15.05.2024',
        cnp: '1850515123456',
        ocupatie: 'Programator',
        firma: 'Alpha Tech SRL',
        suma: '1500.00',
        nr_factura: 'FCT-2024-00123'
    };

    // Procesam template-ul local (inlocuim variabilele simple)
    // Pentru previzualizare rapida, nu apelam API-ul
    let preview = content;

    // Inlocuim variabilele cu datele de test
    Object.keys(testData).forEach(function (key) {
        // Inlocuim toate aparitiile variabilei {{cheie}}
        const regex = new RegExp('\\{\\{' + key + '\\}\\}', 'g');
        preview = preview.replace(regex, sanitizeHTML(testData[key]));
    });

    // Inlocuim functiile dinamice
    const now = new Date();
    preview = preview.replace(/\{\{DATE\}\}/g, now.toLocaleDateString('ro-RO'));
    preview = preview.replace(/\{\{TIME\}\}/g, now.toLocaleTimeString('ro-RO', {hour: '2-digit', minute: '2-digit'}));
    preview = preview.replace(/\{\{DATETIME\}\}/g, now.toLocaleDateString('ro-RO') + ' ' + now.toLocaleTimeString('ro-RO', {hour: '2-digit', minute: '2-digit'}));
    preview = preview.replace(/\{\{YEAR\}\}/g, now.getFullYear());

    // Procesam blocurile conditionale simple (IF/ENDIF)
    preview = preview.replace(/\{\{IF\s+\w+\}\}([\s\S]*?)\{\{ENDIF\}\}/g, '$1');

    // Afisam previzualizarea in panoul din dreapta
    const previewDoc = document.getElementById('preview-document');
    if (previewDoc) {
        previewDoc.innerHTML = preview;
    }
}

// ==================================================
// CRUD SABLOANE
// ==================================================

/**
 * loadTemplates() - incarca lista de sabloane din API 
 * Afiseaza sabloanele in grid-ul din partea de jos
 */
function loadTemplates() {

    // Apelam API-ul pentru a obtine toate sabloanele
    ajaxGet('../api/templates.php?action=list', function (templates) {

        // Obtinem containerul grid-ului de sabloane
        const grid = document.getElementById('templates-grid');
        if (!grid)
            return;

        // Daca nu exista sabloane, afisam mesaj
        if (!templates || templates.length === 0) {
            grid.innerHTML = '<p class="empty-message">Nu exista sabloane salvate. Creati primul sablon!</p>';
            return;
        }

        // Construim HTML-ul pentru fiecare sablon
        let html = '';
        templates.forEach(function (template) {
            html += buildTemplateCard(template);
        });

        // Afisam grid-ul cu sabloane
        grid.innerHTML = html;

        // Adaugam event listener-e pe butoanele de editare/stergere
        attachTemplateEvents();

    }, function (error) {
        showError('Eroare la incarcarea sabloanelor: ' + error);
    });
}

/**
 * buildTemplateCard() - construieste HTML-ul unui card de sablon
 * @param {object} template - datele sablonului
 * @returns {string} - HTML-ul cardului
 */
function buildTemplateCard(template) {
    // Determinam badge-ul tipului
    const typeBadge = '<span class="type-badge ' + sanitizeHTML(template.name) + '">' + sanitizeHTML(template.label) + '</span>';

    return '<div class="template-card" data-id="' + template.id + '">' + 
        '<div class="template-card-name">' + sanitizeHTML(template.name) + '</div>' +
        '<div class="template-card-type">' + typeBadge + '</div>' +
        '<div class="template-card-actions">' +
        '<button class="btn-editor btn-edit-template" data-id="' + template.id + '">Editeaza</button>' +
        '<button class="btn-delete btn-delete-template" data-id="' + template.id + '">Sterge</button>' +
        '</div>' +
        '</div>';
}

/**
 * attachTemplateEvents() - adauga event listener-e pe cardurile de sabloane
 */
function attachTemplateEvents() {

    // Butoane de editare
    const editBtns = document.querySelectorAll('.btn-edit-template');
    editBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = parseInt(btn.getAttribute('data-id'));
            editTemplate(id);
        });
    });

    // Butoane de stergere
    const deleteBtns = document.querySelectorAll('.btn-delete-template');
    deleteBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = parseInt(btn.getAttribute('data-id'));
            // Cerem confirmare inainte de stergere
            confirmAction('Esti sigur ca vrei sa stergi acest sablon?', function () {
                deleteTemplate(id);
            });
        });
    });
}

/**
 * editTemplate() - incarca un sablon in editor pentru editare
 * @param {number} id - ID-ul sablonului de editat
 */
function editTemplate(id) {

    // Apelam API-ul pentru a obtine datele sablonului
    ajaxGet('../api/templates.php?action=get&id=' + id, function (template) {

        // Setam ID-ul sablonului curent
        currentTemplateId = template.id;

        // Populam campurile formularului
        const nameInput = document.getElementById('template-name');
        const typeSelect = document.getElementById('template-type');
        const textarea = document.getElementById('editor-content');

        if (nameInput)
            nameInput.value = template.name;
        if (typeSelect)
            typeSelect.value = template.label;
        if (textarea) {
            // Continutul sablonului este in fields_json
            textarea.value = template.fields_json;
            // Salvam continutul original
            originalContent = template.fields_json;
        }

        // Actualizam previzualizarea
        updatePreview();

        // Afisam mesaj de confirmare
        showInfo('Sablonul "' + template.name + '" a fost incarcat in editor.');

        // Scrollam la editor
        document.getElementById('editor-content').scrollIntoView({behavior: 'smooth'});

    }, function (error) {
        showError('Eroare la incarcarea sablonului: '+ error);
    });
}

/**
 * newTemplate() - reseteaza editorul pentru un sablon nou
 */
function newTemplate() {

    // Resetam ID-ul sablonului curent
    currentTemplateId = null;
    originalContent = '';

    // Golim campurile formularului
    const nameInput = document.getElementById('template-name');
    const typeSelect = document.getElementById('template-type');
    const textarea = document.getElementById('editor-content');

    if (nameInput)
        nameInput.value = '';
    if (typeSelect)
        typeSelect.value = 'cv';
    if (textarea)
        textarea.value = '';

    // Golim previzualizarea
    const previewDoc = document.getElementById('preview-document');
    if (previewDoc) {
        previewDoc.innerHTML = '<p class="preview-empty">Scrieti continut in editor pentru previzualizare...</p>';
    }

    // Afisam mesaj
    showInfo('Editor resetat. Puteti crea un sablon nou');
}

/**
 * saveTemplate() - salveaza sau actualizeaza sablonul curent
 */
function saveTemplate() {

    // Citim datele din formular  
    const name = document.getElementById('template-name') ? document.getElementById('template-name').value.trim() : '';
    const type = document.getElementById('template-type') ? document.getElementById('template-type').value : '';
    const content = document.getElementById('editor-content') ? document.getElementById('editor-content').value.trim() : '';

    // Validam campurile obligatorii
    if (!name) {
        showError('Introduceti un nume pentru sablon!');
        return;
    }

    if (!content) {
        showError('Editorul nu poate fi gol!');
        return;
    }

    // Pregatim datele pentru trimitere
    const data = {
        name: name, 
        type: type,
        content: content,
        format: 'html'
    };

    // Daca editam un sablon existent, adaugam ID-ul si actiunea update
    if (currentTemplateId) {
        data.action = 'update';
        data.id = currentTemplateId;

        ajaxPost('../api/templates.php?action=update', data, function () {
            showSuccess('Sablonul a fost actualizat cu succes!');
            loadTemplates(); // reincarcam lista
        }, function (error) {
            showError('Eroare la actualizare: ' + error);
        });

    } else {
        // Cream un sablon nou
        data.action = 'create';

        ajaxPost('../api/templates.php?action=create', data, function (result) {
            // Setam ID-ul sablonului nou creat
            currentTemplateId = result.id;
            showSuccess('Sablonul a fost salvat cu succes!');
            loadTemplates(); // reincarcam lista
        }, function (error) {
            showError('Eroare la salvare: ' + error);
        });
    }
}

/**
 * deleteTemplate() - sterge un sablon
 * @param {number} id - ID-ul sablonului de sters
 */
function deleteTemplate(id) {

    // Apelam API-ul pentru stergere
    ajaxPost('../api/templates.php?action=delete', {id: id}, function () {

        showSuccess('Sablonul a fost sters cu succes!');

        // Daca am sters sablonul curent, resetam editorul
        if (currentTemplateId === id) {
            newTemplate();
        }

        // Reincarcam lista de sabloane
        loadTemplates();

    }, function (error) {
        showError('Eroare la stergere: ' + error);
    });
}

// ==================================================
// GENERARE DOCUMENT
// ==================================================

/**
 * generateDocument() - genereaza un document din sablonul curent
 */
function generateDocument() {

    // Verificam ca avem un sablon selectat
    if (!currentTemplateId) {
        showError('Selectati sau salvati un sablon inainte de generare!');
        return;
    }

    // Citim numele documentului
    const docName = document.getElementById('doc-name') ?
        document.getElementById('doc-name').value.trim() : 'Document generat';

    // Pregatim datele pentru generare
    const data = {
        template_id: currentTemplateId,
        name: docName || 'Document generat',
        data_source: 'random'
    };

    // Afisam indicatorul de incarcare
    showLoading('btn-generate', 'Se genereaza...');

    // Apelam API-ul pentru generare
    ajaxPost('../api/documents.php?action=generate', data, function (result) {

        // Ascundem indicatorul de incarcare
        hideLoading('btn-generate');

        showSuccess('Documentul a fost generat cu succes!');

        // Afisam documentul generat in previzualizare
        const previewDoc = document.getElementById('preview-document');
        if (previewDoc && result.html) {
            previewDoc.innerHTML = result.html;
        }

    }, function (error) {
        hideLoading('btn-generate');
        showError('Eroare la generare: ' + error);
    });
}