// ==================================================
// public/js/app.js - Logica principala + AJAX
// Acest fisier contine functiile globale folosite
// in toata aplicatia: apeluri AJAX, notificari,
// functii utilitare reutilizabile 
// ==================================================

// ==================================================
// MODUL AJAX - functii pentru comunicarea cu API-ul
// ==================================================

/**
 * ajax() - trimite o cerere AJAX catre un endpoint al API-ului
 * @param {string} url          - URL-ul endpoint-ului (ex: '../api/templates.php')
 * @param {string} method       - metoda HTTP ('GET' sau 'POST')
 * @param {object} data         - datele de trimis (pentru POST)
 * @param {function} onSuccess  - functia apelata la succes
 * @param {function} onError    - functia apelata la eroare
 */
function ajax(url, method, data, onSuccess, onError) {

    // Cream obiectul XMLHttpRequest pentru cererea AJAX
    const xhr = new XMLHttpRequest();

    // Initializam cererea cu metoda si URL-ul
    xhr.open(method, url, true); // true = asincron

    // Definim ce se intampla cand cererea s-a finalizat
    xhr.onreadystatechange = function () {

        // readyState 4 = cererea s-a terminat
        if (xhr.readyState === 4) {

            // Incercam sa parsam raspunsul JSON
            let response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                // Daca raspunsul nu e JSON valid, apelam onError
                if (typeof onError === 'function') {
                    onError('Raspuns invalid de la server!');
                }
                return;
            }

            // Verificam statusul HTTP (200 = OK)
            if (xhr.status === 200) {
                // Verificam daca API-ul a returnat succes
                if (response.success) {
                    // Apelam callback-ul de succes cu datele
                    if (typeof onSuccess === 'function') {
                        onSuccess(response.data);
                    }
                } else {
                    // API-ul a returnat eroare
                    if (typeof onError === 'function') {
                        onError(response.error || 'Eroare necunoscuta!');
                    }
                }
            } else {
                // Eroare HTTP (404, 500 etc.)
                if (typeof onError === 'function') {
                    onError('Eroare HTTP: ' + xhr.status);
                }
            }
        }
    };

    // Trimitem cererea in functie de metoda
    if (method === 'POST' && data) {
        // Pentru POST, trimitem datele cu FormData
        if (data instanceof FormData) {
            // Daca e deja FormData, il trimitem direct
            xhr.send(data);
        } else {
            // Altfel, setam header-ul si codificam datele
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            // Convertim obiectul in string URL-encoded
            const params = Object.keys(data)
                .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(data[key]))
                .join('&');
            xhr.send(params);
        }
    } else {
        // Pentru GET, nu trimitem date in body
        xhr.send();
    }
}

/**
 * ajaxGet() - shortcut pentru cereri GET
 * @param {string} url      - URL-ul endpoint-ului 
 * @param {function} onSuccess      - callback la succes
 * @param {function} onError        - callback la eroare
 */
function ajaxGet(url, onSuccess, onError) {
    ajax(url, 'GET', null, onSuccess, onError);
}

/**
 * ajaxPost() - shortcut pentru cereri POST
 * @param {string} url      - URL-ul endpoint-ului
 * @param {object} data     - datele de trimis
 * @param {function} onSuccess      - callback la succes
 * @param {function} onError        - callback la eroare
 */
function ajaxPost(url, data, onSuccess, onError) {
    ajax(url, 'POST', data, onSuccess, onError);
}

// ==================================================
// MODUL NOTIFICARI - sistem de toast notifications
// ==================================================

/**
 * showToast() - afiseaza o notificare toast
 * @param {string} message      - mesajul de afisat
 * @param {string} type         - tipul: 'success', 'error', 'info'
 * @param {number} duration     - durata in milisecunde (implicit 3000)
 */
function showToast(message, type, duration) {

    // Durata implicita: 3 secunde
    duration = duration || 3000;
    type = type || 'info';

    // Cautam containerul de toast-uri
    // Daca nu exista, il cream
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    // Cream elementul toast
    const toast = document.createElement('div');
    toast.className = 'toast ' + type; // ex: 'toast success'
    toast.textContent = message;

    // Adaugam toast-ul in container
    container.appendChild(toast);

    // Dupa durata specificata, stergem toast-ul
    setTimeout(function () {
        // Verificam ca toast-ul inca exista in DOM
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, duration);
}

/**
 * showSuccess() - shortcut pentru notificari de succes
 */
function showSuccess(message) {
    showToast(message, 'success');
}

/**
 * showError() - shortcut pentru notificari de eroare
 */
function showError(message) {
    showToast(message, 'error');
}

/**
 * showInfo() - shortcut pentru notificari informative
 */
function showInfo(message) {
    showToast(message, 'info');
}

// ==================================================
// MODUL UTILITARE - functii helper generale
// ==================================================

/**
 * sanitizeHTML() - curata un string pentru a preveni XSS
 * Inlocuieste caracterele speciale HTML cu entitati
 * @param {string} str      - string-ul de curatat
 * @returns {string}        - string-ul curatat
 */
function sanitizeHTML(str) {
    // Cream un element temporar
    const temp = document.createElement('div');
    // Setam textul (browser-ul il escapeaza automat)
    temp.textContent = str;
    // Returnam HTML-ul escaped
    return temp.innerHTML;
}

/**
 * formatDate() - formateaza o data in format romanesc
 * @param {string} dateStr - data in format ISO (ex: '2024-01-15')
 * @returns {string} - data formatata (ex: '15.01.2024')
 */
function formatDate(dateStr) {
    if (!dateStr) 
        return '';
    // Impartim data in componente
    const parts = dateStr.split('-');
    if (parts.length !== 3)
        return dateStr;
    // Returnam in format ZZ.LL.AAAA
    return parts[2] + '.' + parts[1] + '.' + parts[0];
}

/**
 * truncate() - trunchiaza un text la o lungime maxima
 * @param {string} str - textul de trunchiat
 * @param {number} maxLen - lungimea maxima
 * @returns {string} - textul trunchiat cu '...' daca e prea lung
 */
function truncate(str, maxLen) {
    if (!str)
        return '';
    if (str.length <= maxLen)
        return str;
    return str.substring(0, maxLen) + '...';
}

/**
 * confirmAction() - cere confirmare inainte de o actiune
 * @param {string} message - mesajul de confirmare
 * @param {function} callback - functia apelata daca utilizatorul confirma
 */
function confirmAction(message, callback) {
    // Folosim dialogul nativ al browserului
    if (window.confirm(message)) {
        callback();
    }
}

/**
 * showLoading() - afiseaza un indicator de incarcare
 * @param {string} elementId - ID-ul elementului de modificat
 * @param {string} message   - mesajul de afisat
 */
function showLoading(elementId, message) {
    const element = document.getElementById(elementId);
    if (element) {
        // Salvam continutul original pentru restaurare
        element.dataset.originalContent = element.innerHTML;
        element.innerHTML = '<span class="loading">' + (message || 'Se incarca...') + '</span>';
        element.disabled = true; // dezactivam butonul
    }
}

/**
 * hideLoading() - ascunde indicatorul de incarcare
 * @param {string} elementId - ID-ul elementului de restaurat
 */
function hideLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element && element.dataset.originalContent) {
        // Restauram continutul original
        element.innerHTML = element.dataset.originalContent;
        element.disabled = false; // reactivam butonul
    }
}

/**
 * debounce() - intarzie executia unei functii
 * Util pentru search sau resize - evita apeluri prea frecvente
 * @param {function} func - functia de intarziat
 * @param {number} delay - intarzierea in milisecunde
 * @returns {function} - functia cu debounce aplicat
 */
function debounce(func, delay) {
    let timeoutId;
    return function () {
        // Anulam timeout-ul anterior
        clearTimeout(timeoutId);
        // Setam un nou timeout 
        timeoutId = setTimeout(function () {
            func.apply(this, arguments);
        }, delay);
    };
}

// ==================================================
// INITIALIZARE - cod executat la incarcarea paginii
// ==================================================

document.addEventListener('DOMContentLoaded', function () {

    // Marcam linkul activ din navigare
    // Comparam URL-ul curent cu href-ul fiecarui link
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-links a');

    navLinks.forEach(function (link) {
        const linkPage = link.getAttribute('href').split('/').pop();
        // Daca URL-ul se potriveste, adaugam clasa 'active'
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