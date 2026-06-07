// public/js/app.js
// Logica principala + AJAX
// Contine functiile globale folosite in toata aplicatia:
// apeluri AJAX, notificari toast, functii utilitare


// ===== Baza URL a aplicatiei =====

// Determinam baza aplicatiei (ex: http://localhost/docgen)
var APP_BASE = (function () {
    if (window.APP_BASE) return window.APP_BASE;
    try {
        var origin = window.location.origin;
        var path = window.location.pathname || '/';
        var m = path.match(/^(.*)\/index\.php$/);
        if (m && m[1]) {
            return origin + m[1];
        }
        var segments = path.split('/').filter(Boolean);
        if (segments.length > 0) {
            return origin + '/' + segments[0];
        }
        return origin;
    } catch (e) {
        return '';
    }
})();


// ===== AJAX =====

/**
 * ajax() - trimite o cerere AJAX catre un endpoint al API-ului
 * @param {string}   url       - URL-ul endpoint-ului
 * @param {string}   method    - metoda HTTP ('GET' sau 'POST')
 * @param {object}   data      - datele de trimis (pentru POST)
 * @param {function} onSuccess - callback la succes
 * @param {function} onError   - callback la eroare
 */
function ajax(url, method, data, onSuccess, onError) {
    const xhr = new XMLHttpRequest();

    // Normalizam URL-urile relative catre API
    if (!/^https?:\/\//i.test(url) && !url.startsWith('/')) {
        if (url.indexOf('../api/') === 0) {
            url = APP_BASE + '/api/' + url.substring('../api/'.length);
        } else if (url.indexOf('api/') === 0) {
            url = APP_BASE + '/' + url;
        }
    }

    xhr.open(method, url, true);

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            let response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                if (typeof onError === 'function') {
                    onError('Raspuns invalid de la server!');
                }
                return;
            }

            if (xhr.status === 200) {
                if (response.success) {
                    // Daca API-ul returneaza payload sub cheia 'data', il folosim direct
                    if (typeof onSuccess === 'function') {
                        onSuccess(response.hasOwnProperty('data') ? response.data : response);
                    }
                } else {
                    if (typeof onError === 'function') {
                        onError(response.error || 'Eroare necunoscuta!');
                    }
                }
            } else {
                if (typeof onError === 'function') {
                    const msg = (response && response.message)
                        ? response.message
                        : ('Eroare HTTP: ' + xhr.status);
                    onError(msg, xhr.status);
                }
            }
        }
    };

    if (method === 'POST' && data) {
        if (data instanceof FormData) {
            xhr.send(data);
        } else {
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(data));
        }
    } else {
        xhr.send();
    }
}

// Shortcut GET
function ajaxGet(url, onSuccess, onError) {
    ajax(url, 'GET', null, onSuccess, onError);
}

// Shortcut POST
function ajaxPost(url, data, onSuccess, onError) {
    ajax(url, 'POST', data, onSuccess, onError);
}

/**
 * ajaxPostForm() - trimite un formular (FormData) prin POST
 * Folosit pentru uploaduri (ex: CSV)
 * @param {string}   url       - endpoint-ul API-ului
 * @param {FormData} formData  - datele formularului
 * @param {function} onSuccess - callback la succes
 * @param {function} onError   - callback la eroare
 */
function ajaxPostForm(url, formData, onSuccess, onError) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (xhr.status === 200 && response.success) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(response.hasOwnProperty('data') ? response.data : response);
                    }
                } else {
                    if (typeof onError === 'function') {
                        onError(response.error || 'Eroare necunoscuta!');
                    }
                }
            } catch (e) {
                if (typeof onError === 'function') {
                    onError('Raspuns invalid de la server!');
                }
            }
        }
    };

    // Nu setam Content-Type manual — browser-ul il seteaza automat pentru FormData
    xhr.send(formData);
}


// ===== Notificari toast =====

/**
 * showToast() - afiseaza o notificare toast
 * @param {string} message  - mesajul de afisat
 * @param {string} type     - tipul: 'success', 'error', 'info'
 * @param {number} duration - durata in milisecunde (implicit 3000)
 */
function showToast(message, type, duration) {
    duration = duration || 3000;
    type = type || 'info';

    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(function () {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, duration);
}

function showSuccess(message) { showToast(message, 'success'); }
function showError(message)   { showToast(message, 'error'); }
function showInfo(message)    { showToast(message, 'info'); }


// ===== Utilitare =====

/**
 * sanitizeHTML() - escapeaza un string pentru a preveni XSS
 * @param {string} str - string-ul de curatat
 * @returns {string}
 */
function sanitizeHTML(str) {
    const temp = document.createElement('div');
    temp.textContent = str;
    return temp.innerHTML;
}

/**
 * formatDate() - formateaza o data ISO in format romanesc (ZZ.LL.AAAA)
 * @param {string} dateStr - data in format ISO (ex: '2024-01-15')
 * @returns {string}
 */
function formatDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    return parts[2] + '.' + parts[1] + '.' + parts[0];
}

/**
 * truncate() - trunchiaza un text la o lungime maxima
 * @param {string} str    - textul de trunchiat
 * @param {number} maxLen - lungimea maxima
 * @returns {string}
 */
function truncate(str, maxLen) {
    if (!str) return '';
    if (str.length <= maxLen) return str;
    return str.substring(0, maxLen) + '...';
}

/**
 * confirmAction() - cere confirmare nativa inainte de o actiune
 * @param {string}   message  - mesajul de confirmare
 * @param {function} callback - functia apelata la confirmare
 */
function confirmAction(message, callback) {
    if (window.confirm(message)) {
        callback();
    }
}

/**
 * showLoading() - afiseaza un indicator de incarcare pe un buton/element
 * @param {string} elementId - ID-ul elementului
 * @param {string} message   - mesajul de afisat
 */
function showLoading(elementId, message) {
    const element = document.getElementById(elementId);
    if (element) {
        element.dataset.originalContent = element.innerHTML;
        element.innerHTML = '<span class="loading">' + (message || 'Se incarca...') + '</span>';
        element.disabled = true;
    }
}

/**
 * hideLoading() - restaureaza elementul dupa incarcare
 * @param {string} elementId - ID-ul elementului
 */
function hideLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element && element.dataset.originalContent) {
        element.innerHTML = element.dataset.originalContent;
        element.disabled = false;
    }
}

/**
 * debounce() - intarzie executia unei functii (util pentru search/resize)
 * @param {function} func  - functia de intarziat
 * @param {number}   delay - intarzierea in milisecunde
 * @returns {function}
 */
function debounce(func, delay) {
    let timeoutId;
    return function () {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(function () {
            func.apply(this, arguments);
        }, delay);
    };
}


// ===== Initializare =====

document.addEventListener('DOMContentLoaded', function () {

    // Marcam linkul activ din navigare pe baza paginii curente
    const currentParams = new URLSearchParams(window.location.search);
    const currentFile = window.location.pathname.split('/').pop().replace('.php', '');
    const currentPage = currentParams.get('page') || (currentFile === 'index' ? 'home' : currentFile) || 'home';
    const navLinks = document.querySelectorAll('.nav-links a');

    navLinks.forEach(function (link) {
        const url = new URL(link.getAttribute('href'), window.location.href);
        const linkFile = url.pathname.split('/').pop().replace('.php', '');
        const linkPage = url.searchParams.get('page') || linkFile || 'home';
        if (linkPage === currentPage) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // Inchidem mesajele de eroare/succes la click
    const messages = document.querySelectorAll('.success-message, .error-message');
    messages.forEach(function (msg) {
        msg.addEventListener('click', function () {
            msg.style.display = 'none';
        });
    });
});


// ===== Modal de confirmare =====

function showConfirmModal(message, onConfirm) {
    const modal     = document.getElementById('confirm-modal');
    const msgEl     = document.getElementById('confirm-message');
    const okBtn     = document.getElementById('confirm-ok');
    const cancelBtn = document.getElementById('confirm-cancel');

    msgEl.textContent = message;
    modal.style.display = 'flex';

    okBtn.onclick = function () {
        modal.style.display = 'none';
        onConfirm();
    };
    cancelBtn.onclick = function () {
        modal.style.display = 'none';
    };
}