// public/js/preview.js
// Logica UI pentru pagina de previzualizare.
// Gestioneaza incarcarea documentului via AJAX, afisarea in iframe,
// exportul (PDF, HTML, CSV, JSON) si stergerea documentului.
// Depinde de: public/js/app.js


// ===== Initializare =====

document.addEventListener('DOMContentLoaded', function () {

    // Citim id-ul documentului din URL (ex: /preview?id=42)
    const urlParams  = new URLSearchParams(window.location.search);
    const documentId = urlParams.get('id');

    if (!documentId) {
        showPreviewMessage('Niciun document specificat.', 'warning');
        return;
    }

    loadDocument(documentId);

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


// ===== Incarcare document =====

function loadDocument(documentId) {
    showPreviewLoading(true);

    ajaxGet(BASE_URL + `/api/documents.php?action=get&id=${documentId}`, function (data) {
        showPreviewLoading(false);

        if (!data) {
            showPreviewMessage('Documentul nu a putut fi incarcat.', 'danger');
            return;
        }

        renderDocumentMeta(data);

        if (data.html_content) {
            renderDocumentPreview(data.html_content);
        } else {
            showPreviewMessage('Documentul nu are continut HTML generat inca.', 'warning');
        }
    });
}


// ===== Metadate document =====

function renderDocumentMeta(doc) {
    const titleEl = document.getElementById('document-title');
    if (titleEl) titleEl.textContent = escapeHtml(doc.title || 'Document fara titlu');

    const dateEl = document.getElementById('document-date');
    if (dateEl) dateEl.textContent = doc.created_at || '-';

    const statusEl = document.getElementById('document-status');
    if (statusEl) {
        statusEl.textContent = doc.status || '-';
        // Clasa CSS corespunzatoare statusului
        const statusClasses = {
            'draft':     'warning',
            'generated': 'info',
            'exported':  'success'
        };
        statusEl.className = `admin-badge ${statusClasses[doc.status] || 'info'}`;
    }

    const rowsEl = document.getElementById('document-rows');
    if (rowsEl) rowsEl.textContent = doc.rows_count || '-';

    const templateEl = document.getElementById('document-template');
    if (templateEl) templateEl.textContent = doc.template_label || doc.schema_name || 'Schema personalizata';

    document.title = `Previzualizare: ${doc.title || 'Document'} — DoGen`;
}


// ===== Previzualizare HTML =====

// Afisam continutul in iframe pentru izolarea stilurilor
function renderDocumentPreview(htmlContent) {
    const iframe = document.getElementById('document-iframe');
    if (!iframe) return;

    iframe.style.display = 'block';

    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(htmlContent);
    iframeDoc.close();

    // Ajustam inaltimea dupa ce continutul s-a incarcat
    iframe.onload = function () {
        adjustIframeHeight(iframe);
    };
}

// Ajusteaza inaltimea iframe-ului la continut pentru a evita scroll-ul dublu
function adjustIframeHeight(iframe) {
    try {
        const body = iframe.contentDocument.body;
        const html = iframe.contentDocument.documentElement;
        const height = Math.max(
            body.scrollHeight, body.offsetHeight,
            html.clientHeight, html.scrollHeight, html.offsetHeight
        );
        iframe.style.height = (height + 32) + 'px';
    } catch (e) {
        // Fallback pentru iframe cross-origin
        iframe.style.height = '600px';
    }
}


// ===== Export =====

function handleExport(documentId, format) {
    showPreviewMessage(`Se pregateste exportul ${format.toUpperCase()}...`, 'info');

    const formData = new FormData();
    formData.append('action', 'export_document');
    formData.append('document_id', documentId);
    formData.append('format', format);

    ajaxPost(BASE_URL + '/api/export.php', formData, function (data) {
        if (data && data.download_url) {
            triggerDownload(data.download_url, data.filename || `document.${format}`);
            showPreviewMessage(`Documentul a fost exportat ca ${format.toUpperCase()}!`, 'success');

            // Actualizam statusul afisat in UI
            const statusEl = document.getElementById('document-status');
            if (statusEl) {
                statusEl.textContent = 'exported';
                statusEl.className   = 'admin-badge success';
            }
        } else {
            showPreviewMessage(`Eroare la exportul ${format.toUpperCase()}.`, 'danger');
        }
    });
}


// ===== Stergere document =====

function handleDelete(documentId) {
    showConfirmModal('Esti sigur ca vrei sa stergi acest document? Actiunea este ireversibila.', function () {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', documentId);

        ajaxPost(BASE_URL + '/api/documents.php', formData, function (data) {
            if (data) {
                showPreviewMessage('Documentul a fost sters. Vei fi redirectionat...', 'success');
                setTimeout(() => {
                    window.location.href = BASE_URL + '/index.php?page=documents';
                }, 2000);
            } else {
                showPreviewMessage('Eroare la stergerea documentului.', 'danger');
            }
        });
    });
}


// ===== Utilitare UI =====

function showPreviewLoading(show) {
    const loader    = document.getElementById('preview-loader');
    const container = document.getElementById('preview-container');
    if (loader)    loader.style.display    = show ? 'flex'  : 'none';
    if (container) container.style.display = show ? 'none'  : 'block';
}

function showPreviewMessage(text, type) {
    const msgBox = document.getElementById('preview-message');
    if (!msgBox) return;
    msgBox.className     = `admin-alert ${type}`;
    msgBox.textContent   = text;
    msgBox.style.display = 'flex';

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