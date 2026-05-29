
// preview.js - logica UI pentru pagina de previzualizare
// gestioneaza:
//   - incarcarea documentului generat via AJAX
//   - afisarea documentului HTML in iframe
//   - exportul documentului (PDF, HTML, CSV, JSON)
//   - navigarea intre documentele generate
// In dependenta de: public/js/app.js (functii AJAX globale) si folosit de: views/preview.php


// --------------------------------------------------
// Initializare cand pagina e gata
// --------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {

    // Citim id-ul documentului din URL
    // Ex: /preview?id=42 -> documentId = 42
    const urlParams   = new URLSearchParams(window.location.search);
    const documentId  = urlParams.get('id');

    if (!documentId) {
        showPreviewMessage('Niciun document specificat.', 'warning');
        return;
    }

    // Incarcam documentul
    loadDocument(documentId);

    // ascultam butoanele de export
    const btnExportPdf  = document.getElementById('btn-export-pdf');
    const btnExportHtml = document.getElementById('btn-export-html');
    const btnExportCsv  = document.getElementById('btn-export-csv');
    const btnExportJson = document.getElementById('btn-export-json');
    const btnDelete     = document.getElementById('btn-delete-document');
    const btnBack       = document.getElementById('btn-back');

    if (btnExportPdf)  btnExportPdf.addEventListener('click',  () => handleExport(documentId, 'pdf'));
    if (btnExportHtml) btnExportHtml.addEventListener('click', () => handleExport(documentId, 'html'));
    if (btnExportCsv)  btnExportCsv.addEventListener('click',  () => handleExport(documentId, 'csv'));
    if (btnExportJson) btnExportJson.addEventListener('click', () => handleExport(documentId, 'json'));
    if (btnDelete)     btnDelete.addEventListener('click',     () => handleDelete(documentId));
    if (btnBack)       btnBack.addEventListener('click',       () => window.history.back());
});


// --------------------------------------------------
// Incarca datele documentului din api/documents.php si randeaza continutul in pagina
// --------------------------------------------------
function loadDocument(documentId) {
    showPreviewLoading(true);

    ajaxGet(`/api/documents.php?action=get&id=${documentId}`, function (data) {
        showPreviewLoading(false);

        if (!data || !data.success) {
            showPreviewMessage(
                data.message || 'Documentul nu a putut fi incarcat.',
                'danger'
            );
            return;
        }

        // Populam metadatele documentului in UI
        renderDocumentMeta(data.document);

        // Afisam continutul HTML al documentului in iframe
        if (data.document.html_content) {
            renderDocumentPreview(data.document.html_content);
        } else {
            showPreviewMessage('Documentul nu are continut HTML generat inca.', 'warning');
        }
    });
}


// --------------------------------------------------
// Randeaza metadatele documentului (titlu, data, status, numar randuri etc.)
// --------------------------------------------------
function renderDocumentMeta(doc) {
    // Titlul documentului
    const titleEl = document.getElementById('document-title');
    if (titleEl) titleEl.textContent = escapeHtml(doc.title || 'Document fara titlu');

    // Data crearii
    const dateEl = document.getElementById('document-date');
    if (dateEl) dateEl.textContent = doc.created_at || '-';

    // Statusul documentului
    const statusEl = document.getElementById('document-status');
    if (statusEl) {
        statusEl.textContent = doc.status || '-';
        // Adaugam clasa de culoare corespunzatoare statusului
        const statusClasses = {
            'draft':     'warning',
            'generated': 'info',
            'exported':  'success'
        };
        statusEl.className = `admin-badge ${statusClasses[doc.status] || 'info'}`;
    }

    // Numarul de randuri generate
    const rowsEl = document.getElementById('document-rows');
    if (rowsEl) rowsEl.textContent = doc.rows_count || '-';

    // Numele sablonului folosit
    const templateEl = document.getElementById('document-template');
    if (templateEl) templateEl.textContent = doc.template_label || doc.schema_name || 'Schema personalizata';

    // Actualizam titlul paginii in browser
    document.title = `Previzualizare: ${doc.title || 'Document'} — DoGen`;
}


// --------------------------------------------------
// afiseaza continutul HTML al documentului
// folosim iframe pentru izolare stiluri
// --------------------------------------------------
function renderDocumentPreview(htmlContent) {
    const iframe = document.getElementById('document-iframe');
    if (!iframe) return;

    // scriem continutul HTML in iframe
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(htmlContent);
    iframeDoc.close();

    // ajustam inaltimea iframe-ului la continut dupa ce se incarca
    iframe.onload = function () {
        adjustIframeHeight(iframe);
    };
}


// --------------------------------------------------
// ajusteaza inaltimea iframe-ului la continutul sau
// pentru a evita scroll-ul dublu in pagina
// --------------------------------------------------
function adjustIframeHeight(iframe) {
    try {
        const body = iframe.contentDocument.body;
        const html = iframe.contentDocument.documentElement;
        const height = Math.max(
            body.scrollHeight,
            body.offsetHeight,
            html.clientHeight,
            html.scrollHeight,
            html.offsetHeight
        );
        iframe.style.height = (height + 32) + 'px';
    } catch (e) {
        // Daca iframe-ul e cross-origin, setam o inaltime fixa
        iframe.style.height = '600px';
    }
}


// --------------------------------------------------
// Export document in formatul dorit
// Apeleaza api/export.php si declaseaza descarcarea
// --------------------------------------------------
function handleExport(documentId, format) {
    showPreviewMessage(`Se pregateste exportul ${format.toUpperCase()}...`, 'info');

    ajaxPost('/api/export.php', {
        action:      'export_document',
        document_id: documentId,
        format:      format
    }, function (data) {
        if (data && data.success && data.download_url) {
            // Declansam descarcarea fisierului
            triggerDownload(data.download_url, data.filename || `document.${format}`);
            showPreviewMessage(
                `Documentul a fost exportat ca ${format.toUpperCase()}!`,
                'success'
            );
            // Actualizam statusul afisat in UI
            const statusEl = document.getElementById('document-status');
            if (statusEl) {
                statusEl.textContent = 'exported';
                statusEl.className   = 'admin-badge success';
            }
        } else {
            showPreviewMessage(
                data.message || `Eroare la exportul ${format.toUpperCase()}.`,
                'danger'
            );
        }
    });
}


// --------------------------------------------------
// sterge documentul curent
// cere confirmare inainte de stergere
// --------------------------------------------------
function handleDelete(documentId) {
    // Cerem confirmare utilizatorului
    if (!confirm('Esti sigur ca vrei sa stergi acest document? Actiunea este ireversibila.')) {
        return;
    }

    ajaxPost('/api/documents.php', {
        action: 'delete',
        id:     documentId
    }, function (data) {
        if (data && data.success) {
            showPreviewMessage('Documentul a fost sters. Vei fi redirectionat...', 'success');
            // Redirectam catre lista de documente dupa 2 secunde
            setTimeout(() => {
                window.location.href = '/documents';
            }, 2000);
        } else {
            showPreviewMessage(
                data.message || 'Eroare la stergerea documentului.',
                'danger'
            );
        }
    });
}


// --------------------------------------------------
// Utilitare UI
// --------------------------------------------------

// Afiseaza / ascunde indicatorul de incarcare
function showPreviewLoading(show) {
    const loader    = document.getElementById('preview-loader');
    const container = document.getElementById('preview-container');
    if (loader)    loader.style.display    = show ? 'flex' : 'none';
    if (container) container.style.display = show ? 'none' : 'block';
}

// Afiseaza un mesaj de status in pagina
function showPreviewMessage(text, type) {
    const msgBox = document.getElementById('preview-message');
    if (!msgBox) return;
    msgBox.className   = `admin-alert ${type}`;
    msgBox.textContent = text;
    msgBox.style.display = 'flex';

    // Ascundem mesajele de succes dupa 4 secunde
    if (type === 'success') {
        setTimeout(() => { msgBox.style.display = 'none'; }, 4000);
    }
}

// Declaseaza descarcarea unui fisier din browser
function triggerDownload(url, filename) {
    const link = document.createElement('a');
    link.href     = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Escapeaza HTML pentru a preveni XSS
function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}
