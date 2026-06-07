// public/js/editor.js
// Logica editorului de sabloane HTML
// Gestioneaza editarea, previzualizarea in timp real si salvarea via AJAX


// ===== Stare globala =====

// ID-ul sablonului curent editat (null = sablon nou)
let currentTemplateId = null;

// Continutul original al sablonului (pentru detectarea modificarilor)
let originalContent = '';

let currentTemplateFields = [];


// ===== Initializare =====

document.addEventListener('DOMContentLoaded', function () {

    loadTemplates();

    const saveBtn = document.getElementById('btn-save');
    if (saveBtn) saveBtn.addEventListener('click', saveTemplate);

    const newBtn = document.getElementById('btn-new');
    if (newBtn) newBtn.addEventListener('click', newTemplate);

    const previewBtn = document.getElementById('btn-preview');
    if (previewBtn) previewBtn.addEventListener('click', updatePreview);

    const generateBtn = document.getElementById('btn-generate');
    if (generateBtn) generateBtn.addEventListener('click', generateDocument);

    // Previzualizare automata la tastare, cu debounce pentru performanta
    const textarea = document.getElementById('editor-content');
    if (textarea) {
        textarea.addEventListener('input', debounce(updatePreview, 500));
    }

    setupToolbar();
});


// ===== Toolbar =====

/**
 * setupToolbar() - configureaza butoanele din toolbar
 * Fiecare buton insereaza o variabila/functie la pozitia cursorului
 */
function setupToolbar() {
    const toolbarBtns = document.querySelectorAll('.toolbar-btn');
    toolbarBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const insertValue = btn.getAttribute('data-insert');
            if (insertValue) {
                insertAtCursor(insertValue);
                updatePreview();
            }
        });
    });
}

/**
 * insertAtCursor() - insereaza text la pozitia cursorului in editor
 * @param {string} text - textul de inserat
 */
function insertAtCursor(text) {
    const textarea = document.getElementById('editor-content');
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const before = textarea.value.substring(0, start);
    const after = textarea.value.substring(end);
    textarea.value = before + text + after;

    const newPos = start + text.length;
    textarea.selectionStart = newPos;
    textarea.selectionEnd = newPos;
    textarea.focus();
}


// ===== Previzualizare =====

/**
 * updatePreview() - actualizeaza panoul de previzualizare
 * Proceseaza template-ul local, fara apel la API, pentru viteza
 */
function updatePreview() {
    const textarea = document.getElementById('editor-content');
    if (!textarea) return;

    const content = textarea.value.trim();

    if (!content) {
        const previewDoc = document.getElementById('preview-document');
        if (previewDoc) {
            previewDoc.innerHTML = '<p class="preview-empty">Scrieti continut in editor pentru previzualizare...</p>';
        }
        return;
    }

    // Date de test statice folosite la previzualizare
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
        nr_factura: 'FCT-2024-00123',
        data_nasterii: '15.05.1985',
        studii: 'Licenta',
        nume_solicitant: 'Ion Popescu',
        subiect: 'Cerere de concediu',
        detalii: 'Va rog sa aprobati cererea mea.',
        furnizor: 'Alpha Tech SRL',
        cui_furnizor: 'RO12345678',
        client: 'Beta SRL',
        cui_client: 'RO87654321',
        produs: 'Servicii IT',
        cantitate: '1',
        pret_unitar: '1500.00',
        tva: '19',
        data_emitere: '04.06.2026',
        iban: 'RO42RNCB0000123456789012',
        cui: 'RO12345678',
        oras: 'Bucuresti',
        judet: 'Ilfov'
    };

    let preview = content;

    // Inlocuim variabilele {{cheie}} cu datele de test
    Object.keys(testData).forEach(function (key) {
        const regex = new RegExp('\\{\\{' + key + '\\}\\}', 'g');
        preview = preview.replace(regex, sanitizeHTML(testData[key]));
    });

    // Inlocuim functiile dinamice de data/ora
    const now = new Date();
    preview = preview.replace(/\{\{DATE\}\}/g, now.toLocaleDateString('ro-RO'));
    preview = preview.replace(/\{\{TIME\}\}/g, now.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }));
    preview = preview.replace(/\{\{DATETIME\}\}/g, now.toLocaleDateString('ro-RO') + ' ' + now.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }));
    preview = preview.replace(/\{\{YEAR\}\}/g, now.getFullYear());

    // Procesam blocurile conditionale simple IF/ENDIF
    preview = preview.replace(/\{\{IF\s+\w+\}\}([\s\S]*?)\{\{ENDIF\}\}/g, '$1');

    const previewDoc = document.getElementById('preview-document');
    if (previewDoc) {
        previewDoc.innerHTML = preview;
    }
}


// ===== CRUD sabloane =====

/**
 * loadTemplates() - incarca lista de sabloane din API
 * si le afiseaza in grid
 */
function loadTemplates() {
    ajaxGet('../api/templates.php?action=list', function (templates) {
        const grid = document.getElementById('templates-grid');
        if (!grid) return;

        if (!templates || templates.length === 0) {
            grid.innerHTML = '<p class="empty-message">Nu exista sabloane salvate. Creati primul sablon!</p>';
            return;
        }

        let html = '';
        templates.forEach(function (template) {
            html += buildTemplateCard(template);
        });
        grid.innerHTML = html;

        attachTemplateEvents();

    }, function (error) {
        showError('Eroare la incarcarea sabloanelor: ' + error);
    });
}

/**
 * buildTemplateCard() - construieste HTML-ul unui card de sablon
 * @param {object} template - datele sablonului
 * @returns {string}
 */
function buildTemplateCard(template) {
    const typeBadge = '<span class="type-badge ' + sanitizeHTML(template.name) + '">' + sanitizeHTML(template.label) + '</span>';

    return '<div class="template-card" data-id="' + template.id + '">' +
        '<div class="template-card-name">' + sanitizeHTML(template.name) + '</div>' +
        '<div class="template-card-type">' + typeBadge + '</div>' +
        '<div class="template-card-actions">' +
        '<button class="btn-editor btn-edit-template" data-id="' + template.id + '">Editeaza</button>' +
        (USER_IS_ADMIN ? '<button class="btn-delete btn-delete-template" data-id="' + template.id + '">Sterge</button>' : '') +
        '</div>' +
        '</div>';
}

/**
 * attachTemplateEvents() - ataseaza event listener-e pe cardurile de sabloane
 */
function attachTemplateEvents() {
    const editBtns = document.querySelectorAll('.btn-edit-template');
    editBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            editTemplate(parseInt(btn.getAttribute('data-id')));
        });
    });

    const deleteBtns = document.querySelectorAll('.btn-delete-template');
    deleteBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = parseInt(btn.getAttribute('data-id'));
            showConfirmModal('Esti sigur ca vrei sa stergi acest sablon? Actiunea este ireversibila.', function () {
                deleteTemplate(id);
            });
        });
    });
}

/**
 * editTemplate() - incarca un sablon in editor
 * @param {number} id - ID-ul sablonului
 */
function editTemplate(id) {
    ajaxGet('../api/templates.php?action=get&id=' + id, function (template) {
        currentTemplateId = template.id;

        // Parsam campurile sablonului
        try {
            const parsed = JSON.parse(template.fields_json);
            currentTemplateFields = Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            currentTemplateFields = [];
        }

        const nameInput  = document.getElementById('template-name');
        const typeSelect = document.getElementById('template-type');
        const textarea   = document.getElementById('editor-content');

        if (nameInput)  nameInput.value  = template.name;
        if (typeSelect) typeSelect.value = template.label;

        if (textarea) {
            let templateContent = template.fields_json || '';

            // Daca fields_json e un array JSON, generam HTML din campuri
            // Altfel il folosim direct ca HTML
            try {
                const parsed = JSON.parse(templateContent);
                if (Array.isArray(parsed)) {
                    templateContent = parsed.map(function (f) {
                        return '<p><strong>' + (f.label || f.field) + ':</strong> {{' + f.field + '}}</p>';
                    }).join('\n');
                }
            } catch (e) {}

            textarea.value = templateContent;
            originalContent = templateContent;
        }

        updatePreview();
        showInfo('Sablonul "' + template.name + '" a fost incarcat in editor.');
        document.getElementById('editor-content').scrollIntoView({ behavior: 'smooth' });

    }, function (error) {
        showError('Eroare la incarcarea sablonului: ' + error);
    });
}

/**
 * newTemplate() - reseteaza editorul pentru un sablon nou
 */
function newTemplate() {
    currentTemplateId = null;
    originalContent = '';
    currentTemplateFields = [];

    const nameInput  = document.getElementById('template-name');
    const typeSelect = document.getElementById('template-type');
    const textarea   = document.getElementById('editor-content');

    if (nameInput)  nameInput.value  = '';
    if (typeSelect) typeSelect.value = 'cv';
    if (textarea)   textarea.value   = '';

    const previewDoc = document.getElementById('preview-document');
    if (previewDoc) {
        previewDoc.innerHTML = '<p class="preview-empty">Scrieti continut in editor pentru previzualizare...</p>';
    }

    showInfo('Editor resetat. Puteti crea un sablon nou.');
}

/**
 * saveTemplate() - salveaza sau actualizeaza sablonul curent
 */
function saveTemplate() {
    const name    = document.getElementById('template-name')  ? document.getElementById('template-name').value.trim()  : '';
    const type    = document.getElementById('template-type')  ? document.getElementById('template-type').value          : '';
    const content = document.getElementById('editor-content') ? document.getElementById('editor-content').value.trim() : '';

    if (!name) {
        showError('Introduceti un nume pentru sablon!');
        return;
    }
    if (!content) {
        showError('Editorul nu poate fi gol!');
        return;
    }

    const data = {
        name: name,
        type: type,
        content: content,
        format: 'html'
    };

    if (currentTemplateId) {
        data.action = 'update';
        data.id = currentTemplateId;

        ajaxPost('../api/templates.php?action=update', data, function () {
            showSuccess('Sablonul a fost actualizat cu succes!');
            loadTemplates();
        }, function (error) {
            showError('Eroare la actualizare: ' + error);
        });

    } else {
        data.action = 'create';

        ajaxPost('../api/templates.php?action=create', data, function (result) {
            currentTemplateId = result.id;
            showSuccess('Sablonul a fost salvat cu succes!');
            loadTemplates();
        }, function (error) {
            showError('Eroare la salvare: ' + error);
        });
    }
}

/**
 * deleteTemplate() - sterge un sablon
 * @param {number} id - ID-ul sablonului
 */
function deleteTemplate(id) {
    ajaxPost('../api/templates.php?action=delete', { id: id }, function () {
        showSuccess('Sablonul a fost sters cu succes!');
        // Daca am sters sablonul curent, resetam editorul
        if (currentTemplateId === id) {
            newTemplate();
        }
        loadTemplates();
    }, function (error) {
        showError('Eroare la stergere: ' + error);
    });
}


// ===== Generare document =====

/**
 * generateDocument() - genereaza un document din sablonul curent
 */
function generateDocument() {
    if (!currentTemplateId) {
        showError('Selectati sau salvati un sablon inainte de generare!');
        return;
    }

    const docName = document.getElementById('doc-name')
        ? document.getElementById('doc-name').value.trim()
        : 'Document generat';

    const data = {
        template_id: currentTemplateId,
        name: docName || 'Document generat',
        data_source: 'random',
        fields: currentTemplateFields.length > 0 ? currentTemplateFields : []
    };

    showLoading('btn-generate', 'Se genereaza...');

    ajaxPost('../api/documents.php?action=generate', data, function (result) {
        hideLoading('btn-generate');
        showSuccess('Documentul a fost generat cu succes!');

        const previewDoc = document.getElementById('preview-document');
        if (previewDoc && result.html) {
            previewDoc.innerHTML = result.html;
        }

        // Stergem butonul de descarcare anterior daca exista
        const existingBtn = document.getElementById('download-btn-container');
        if (existingBtn) existingBtn.remove();

        const container = document.createElement('div');
        container.id = 'download-btn-container';
        container.style.cssText = 'text-align:center; padding: 1rem 0 0.5rem 0;';

        const downloadBtn = document.createElement('button');
        downloadBtn.className = 'btn-editor';
        downloadBtn.textContent = 'Descarca document HTML';
        downloadBtn.addEventListener('click', function () {
            const blob = new Blob([result.html], { type: 'text/html;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (document.getElementById('doc-name').value.trim() || 'document') + '.html';
            a.click();
            URL.revokeObjectURL(url);
        });

        container.appendChild(downloadBtn);

        const previewPanel = document.querySelector('.preview-panel');
        if (previewPanel) {
            previewPanel.appendChild(container);
        }

    }, function (error) {
        hideLoading('btn-generate');
        showError('Eroare la generare: ' + error);
    });
}