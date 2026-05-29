
// generator.js - Logica UI pentru pagina de generator
// Gestioneaza:
//   - adaugarea / stergerea campurilor din schema
//   - previzualizarea tabelara a datelor generate
//   - salvarea schemei via AJAX
//   - importul CSV via AJAX
// Depinde de: public/js/app.js (functii AJAX globale)
// Folosit de: views/generator.php


// --------------------------------------------------
// Starea locala a generatorului
// Retine campurile adaugate de utilizator
// --------------------------------------------------
const GeneratorState = {
    // Array cu obiectele de tip camp
    // Ex: [{id: 1, field: 'nume', type: 'full_name', label: 'Nume'}, ...]
    fields: [],

    // Contor pentru id-uri unice ale campurilor din UI
    nextId: 1,

    // Adauga un camp nou in stare
    addField(type, label) {
        const field = {
            id: this.nextId++,
            // Generam numele cheii din label (fara spatii, lowercase)
            field: label.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, ''),
            type: type,
            label: label
        };
        this.fields.push(field);
        return field;
    },

    // Sterge un camp dupa id
    removeField(id) {
        this.fields = this.fields.filter(f => f.id !== id);
    },

    // Returneaza campurile ca JSON curat (fara id-ul intern UI)
    toJSON() {
        return this.fields.map(({ field, type, label }) => ({ field, type, label }));
    },

    // Reseteaza starea
    reset() {
        this.fields = [];
        this.nextId = 1;
    }
};


// --------------------------------------------------
// Initializare cand pagina e gata
// --------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {

    // Incarcam tipurile de campuri disponibile din API
    // si populam dropdown-ul de selectie tip
    loadFieldTypes();

    // Ascultam butoanele principale
    const btnAddField    = document.getElementById('btn-add-field');
    const btnGenerate    = document.getElementById('btn-generate');
    const btnSaveSchema  = document.getElementById('btn-save-schema');
    const btnImportCsv   = document.getElementById('btn-import-csv');
    const btnExportCsv   = document.getElementById('btn-export-csv');
    const btnExportJson  = document.getElementById('btn-export-json');

    if (btnAddField)   btnAddField.addEventListener('click', handleAddField);
    if (btnGenerate)   btnGenerate.addEventListener('click', handleGenerate);
    if (btnSaveSchema) btnSaveSchema.addEventListener('click', handleSaveSchema);
    if (btnImportCsv)  btnImportCsv.addEventListener('click', handleImportCsv);
    if (btnExportCsv)  btnExportCsv.addEventListener('click', () => handleExport('csv'));
    if (btnExportJson) btnExportJson.addEventListener('click', () => handleExport('json'));
});


// --------------------------------------------------
// Incarca tipurile de campuri din api/schemas.php
// si populeaza dropdown-ul #field-type-select
// --------------------------------------------------
function loadFieldTypes() {
    const select = document.getElementById('field-type-select');
    if (!select) return;

    // Apel AJAX catre API
    ajaxGet('/api/schemas.php?action=field_types', function (data) {
        if (!data || !data.types) return;

        // Golim optiunile existente
        select.innerHTML = '<option value="">-- Alege tipul --</option>';

        // Adaugam fiecare tip ca optiune
        Object.entries(data.types).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            select.appendChild(option);
        });
    });
}


// --------------------------------------------------
// Adauga un camp nou in lista
// Citeste tipul si label-ul din formular
// --------------------------------------------------
function handleAddField() {
    const typeSelect  = document.getElementById('field-type-select');
    const labelInput  = document.getElementById('field-label-input');

    if (!typeSelect || !labelInput) return;

    const type  = typeSelect.value.trim();
    const label = labelInput.value.trim();

    // Validare
    if (!type) {
        showGeneratorMessage('Alege un tip de camp!', 'warning');
        return;
    }
    if (!label) {
        showGeneratorMessage('Introdu un nume pentru camp!', 'warning');
        labelInput.focus();
        return;
    }

    // Adaugam in stare
    const field = GeneratorState.addField(type, label);

    // Adaugam randul in tabelul de campuri din UI
    renderFieldRow(field);

    // Resetam inputurile
    labelInput.value = '';
    typeSelect.value = '';
    labelInput.focus();
}


// --------------------------------------------------
// Randeaza un rand in tabelul de campuri (#fields-table)
// pentru campul dat
// --------------------------------------------------
function renderFieldRow(field) {
    const tbody = document.querySelector('#fields-table tbody');
    if (!tbody) return;

    // Stergem mesajul "niciun camp" daca exista
    const emptyRow = tbody.querySelector('.empty-row');
    if (emptyRow) emptyRow.remove();

    const tr = document.createElement('tr');
    tr.dataset.fieldId = field.id;
    tr.innerHTML = `
        <td>${escapeHtml(field.label)}</td>
        <td><code>${escapeHtml(field.field)}</code></td>
        <td>${escapeHtml(field.type)}</td>
        <td>
            <button class="admin-btn danger small btn-remove-field"
                    data-id="${field.id}"
                    title="Sterge campul">
                ✕
            </button>
        </td>
    `;

    // Ascultam butonul de stergere
    tr.querySelector('.btn-remove-field').addEventListener('click', function () {
        removeFieldRow(field.id);
    });

    tbody.appendChild(tr);
}


// --------------------------------------------------
// Sterge un camp din UI si din stare
// --------------------------------------------------
function removeFieldRow(id) {
    // Stergem din stare
    GeneratorState.removeField(id);

    // Stergem randul din tabel
    const tr = document.querySelector(`tr[data-field-id="${id}"]`);
    if (tr) tr.remove();

    // Daca nu mai sunt campuri, afisam mesajul "gol"
    const tbody = document.querySelector('#fields-table tbody');
    if (tbody && tbody.querySelectorAll('tr').length === 0) {
        tbody.innerHTML = `
            <tr class="empty-row">
                <td colspan="4" style="text-align:center; color:#999; padding:20px;">
                    Niciun camp adaugat inca.
                </td>
            </tr>
        `;
    }

    // Stergem si previzualizarea daca exista
    clearPreviewTable();
}


// Genereaza date si afiseaza previzualizarea tabelara
// Trimite campurile la api/data.php si randeaza rezultatul

function handleGenerate() {
    if (GeneratorState.fields.length === 0) {
        showGeneratorMessage('Adauga cel putin un camp inainte de a genera!', 'warning');
        return;
    }

    const rowsInput = document.getElementById('rows-count-input');
    const rows = rowsInput ? parseInt(rowsInput.value) || 10 : 10;

    // Validam numarul de randuri
    if (rows < 1 || rows > 1000) {
        showGeneratorMessage('Numarul de randuri trebuie sa fie intre 1 si 1000!', 'warning');
        return;
    }

    // Afisam indicator de incarcare
    showGeneratorMessage('Se genereaza datele...', 'info');
    setGenerateButtonLoading(true);

    // Apel AJAX catre api/data.php
    ajaxPost('/api/data.php', {
        action: 'generate',
        fields: GeneratorState.toJSON(),
        rows:   rows
    }, function (data) {
        setGenerateButtonLoading(false);

        if (data && data.success && data.rows) {
            renderPreviewTable(GeneratorState.fields, data.rows);
            showGeneratorMessage(
                `Au fost generate ${data.rows.length} randuri cu succes!`,
                'success'
            );
        } else {
            showGeneratorMessage(
                data.message || 'Eroare la generarea datelor.',
                'danger'
            );
        }
    });
}

// Randeaza tabelul de previzualizare cu datele generate
// fields - array de obiecte camp
// rows   - array de obiecte cu datele generate

function renderPreviewTable(fields, rows) {
    const container = document.getElementById('preview-container');
    if (!container) return;

    // Construim header-ul tabelului
    const headers = fields.map(f =>
        `<th>${escapeHtml(f.label)}</th>`
    ).join('');

    // Construim randurile tabelului
    const bodyRows = rows.map(row => {
        const cells = fields.map(f =>
            `<td>${escapeHtml(String(row[f.field] ?? ''))}</td>`
        ).join('');
        return `<tr>${cells}</tr>`;
    }).join('');

    // Injectam tabelul in container
    container.innerHTML = `
        <div class="admin-table-wrapper">
            <table class="admin-table" id="preview-table">
                <thead>
                    <tr>${headers}</tr>
                </thead>
                <tbody>
                    ${bodyRows}
                </tbody>
            </table>
        </div>
        <p style="margin-top:10px; font-size:13px; color:#666;">
            ${rows.length} randuri generate
        </p>
    `;

    // Facem scroll la previzualizare
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
}


// Sterge tabelul de previzualizare

function clearPreviewTable() {
    const container = document.getElementById('preview-container');
    if (container) container.innerHTML = '';
}


// Salveaza schema curenta via AJAX (api/schemas.php)

function handleSaveSchema() {
    if (GeneratorState.fields.length === 0) {
        showGeneratorMessage('Adauga cel putin un camp inainte de a salva!', 'warning');
        return;
    }

    const nameInput = document.getElementById('schema-name-input');
    const name = nameInput ? nameInput.value.trim() : '';

    if (!name) {
        showGeneratorMessage('Introdu un nume pentru schema!', 'warning');
        if (nameInput) nameInput.focus();
        return;
    }

    const rowsInput = document.getElementById('rows-count-input');
    const rows = rowsInput ? parseInt(rowsInput.value) || 10 : 10;

    ajaxPost('/api/schemas.php', {
        action:      'save',
        name:        name,
        fields_json: JSON.stringify(GeneratorState.toJSON()),
        rows_count:  rows
    }, function (data) {
        if (data && data.success) {
            showGeneratorMessage('Schema salvata cu succes!', 'success');
        } else {
            showGeneratorMessage(
                data.message || 'Eroare la salvarea schemei.',
                'danger'
            );
        }
    });
}


// Importa date dintr-un fisier CSV
// Citeste fisierul selectat si il trimite la api/data.php

function handleImportCsv() {
    const fileInput = document.getElementById('csv-file-input');
    if (!fileInput || !fileInput.files.length) {
        showGeneratorMessage('Selecteaza un fisier CSV!', 'warning');
        return;
    }

    const file = fileInput.files[0];

    // Validam extensia
    if (!file.name.endsWith('.csv')) {
        showGeneratorMessage('Fisierul trebuie sa aiba extensia .csv!', 'warning');
        return;
    }

    // Trimitem fisierul via FormData (multipart)
    const formData = new FormData();
    formData.append('action', 'import_csv');
    formData.append('csv_file', file);

    showGeneratorMessage('Se importa fisierul CSV...', 'info');

    // Apel AJAX cu FormData
    ajaxPostForm('/api/data.php', formData, function (data) {
        if (data && data.success) {
            showGeneratorMessage(
                `CSV importat: ${data.row_count} randuri, ${data.headers.length} coloane.`,
                'success'
            );
            // Afisam previzualizarea daca avem date
            if (data.preview && data.headers) {
                renderCsvPreview(data.headers, data.preview);
            }
        } else {
            showGeneratorMessage(
                data.message || 'Eroare la importul CSV.',
                'danger'
            );
        }
    });
}


// --------------------------------------------------
// Randeaza previzualizarea datelor importate din CSV
// --------------------------------------------------
function renderCsvPreview(headers, rows) {
    const container = document.getElementById('preview-container');
    if (!container) return;

    const headerHtml = headers.map(h => `<th>${escapeHtml(h)}</th>`).join('');
    const rowsHtml = rows.map(row => {
        const cells = headers.map(h =>
            `<td>${escapeHtml(String(row[h] ?? ''))}</td>`
        ).join('');
        return `<tr>${cells}</tr>`;
    }).join('');

    container.innerHTML = `
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead><tr>${headerHtml}</tr></thead>
                <tbody>${rowsHtml}</tbody>
            </table>
        </div>
        <p style="margin-top:10px; font-size:13px; color:#666;">
            Previzualizare CSV (primele ${rows.length} randuri)
        </p>
    `;
}


// --------------------------------------------------
// Export date generate (CSV sau JSON)
// --------------------------------------------------
function handleExport(format) {
    const previewTable = document.getElementById('preview-table');
    if (!previewTable) {
        showGeneratorMessage('Genereaza date inainte de a exporta!', 'warning');
        return;
    }

    // Redirectam catre api/export.php cu parametrii necesari
    // Datele generate sunt retrimise ca JSON in body
    const rowsInput = document.getElementById('rows-count-input');
    const rows = rowsInput ? parseInt(rowsInput.value) || 10 : 10;

    ajaxPost('/api/export.php', {
        action: 'export_data',
        format: format,
        fields: GeneratorState.toJSON(),
        rows:   rows
    }, function (data) {
        if (data && data.success && data.download_url) {
            // Declansam descarcarea fisierului
            const link = document.createElement('a');
            link.href = data.download_url;
            link.download = data.filename || `export.${format}`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showGeneratorMessage(`Export ${format.toUpperCase()} generat!`, 'success');
        } else {
            showGeneratorMessage(
                data.message || `Eroare la exportul ${format}.`,
                'danger'
            );
        }
    });
}


// --------------------------------------------------
// Utilitare UI
// --------------------------------------------------

// Afiseaza un mesaj de status deasupra generatorului
function showGeneratorMessage(text, type) {
    const msgBox = document.getElementById('generator-message');
    if (!msgBox) return;
    msgBox.className = `admin-alert ${type}`;
    msgBox.textContent = text;
    msgBox.style.display = 'flex';

    // Ascundem automat mesajele de succes dupa 4 secunde
    if (type === 'success') {
        setTimeout(() => { msgBox.style.display = 'none'; }, 4000);
    }
}

// Seteaza starea de loading pe butonul Generate
function setGenerateButtonLoading(loading) {
    const btn = document.getElementById('btn-generate');
    if (!btn) return;
    btn.disabled = loading;
    btn.textContent = loading ? 'Se genereaza...' : 'Genereaza date';
}

// Escapeaza HTML pentru a preveni XSS
// Cerinta obligatorie din criteriile de evaluare
function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}
