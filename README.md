# DoGen — Document Web Generator

## Descriere generală

**DoGen (Document Web Generator)** este o aplicație Web realizată pentru disciplina **Tehnologii Web**, având ca scop generarea, administrarea și exportul documentelor pe baza unor șabloane dinamice și a unor date generate automat sau importate din fișiere CSV.

Aplicația permite crearea de șabloane HTML, completarea automată a acestora cu date românești realiste, generarea de documente, vizualizarea documentelor generate și exportul lor în formate deschise.

Proiectul este implementat folosind **PHP 8**, **SQLite 3**, **HTML5**, **CSS3** și **JavaScript Vanilla**, fără framework-uri frontend sau backend.

---

## Autori

* **Curmei Bianca-Gabriela**
* **Preutu-Grigore Diana-Smaranda**

Grupa: **2A2**
Facultatea de Informatică, Universitatea „Alexandru Ioan Cuza” din Iași
Disciplina: **Tehnologii Web**
An universitar: **2025–2026**

---

## Funcționalități principale

### 1. Generare date

Aplicația include un modul de generare automată de date realiste, adaptate contextului românesc.

Tipuri de date suportate:

* nume complet;
* prenume;
* nume de familie;
* email;
* număr de telefon;
* adresă;
* oraș;
* județ;
* CNP;
* CUI;
* IBAN;
* dată;
* număr;
* preț;
* TVA;
* număr factură;
* firmă;
* ocupație;
* nivel de studii;
* produs;
* text scurt;
* paragraf.

În total, aplicația suportă **22 de tipuri de câmpuri**.

Datele generate pot fi exportate în formatele **CSV** și **JSON**.

---

### 2. Import CSV

Utilizatorii pot importa date din fișiere CSV. Aplicația validează fișierul încărcat și procesează datele pentru a putea fi utilizate ulterior la generarea documentelor.

---

### 3. Editor de șabloane

Aplicația include un editor pentru crearea și modificarea șabloanelor HTML.

Funcționalități:

* creare șablon;
* editare șablon existent;
* ștergere șablon de către administrator;
* previzualizare live;
* inserare variabile dinamice;
* salvare șabloane în baza de date.

Exemple de variabile:

```text
{{nume}}
{{email}}
{{telefon}}
{{adresa}}
{{firma}}
{{produs}}
{{suma}}
```

Variabile speciale suportate:

```text
{{DATE}}
{{TIME}}
{{DATETIME}}
{{YEAR}}
{{TIMESTAMP}}
```

Blocuri condiționale:

```text
{{IF camp}}
Conținut afișat condițional
{{ELSE}}
Conținut alternativ
{{ENDIF}}
```

---

### 4. Generare documente

Documentele sunt generate prin combinarea unui șablon cu date generate automat sau importate din CSV.

Flux general:

1. Utilizatorul alege un șablon.
2. Aplicația generează sau preia datele necesare.
3. Motorul de templating înlocuiește variabilele din șablon.
4. Documentul final este salvat în directorul `generated/html`.
5. Documentul poate fi vizualizat în aplicație și exportat.

---

### 5. Gestionarea documentelor

Pagina **Documentele Mele** permite:

* afișarea documentelor generate;
* paginare;
* previzualizare document;
* ștergere document;
* afișarea statusului documentului;
* acces rapid la export.

---

### 6. Export documente

Aplicația permite exportul documentelor în următoarele formate:

* **HTML**
* **PDF**
* **CSV**
* **JSON**

Exportul PDF utilizează biblioteca **mPDF** dacă aceasta este disponibilă. În lipsa bibliotecii, aplicația folosește un mecanism intern de fallback pentru generarea unui PDF text-based.

Exportul CSV extrage câmpurile și valorile documentului generat și le salvează într-un fișier `.csv`.

---

### 7. Autentificare și roluri

Aplicația permite autentificarea utilizatorilor și administratorilor.

Roluri:

#### Utilizator autentificat

Poate:

* genera date;
* importa CSV;
* salva scheme de câmpuri;
* genera documente;
* vizualiza documentele proprii;
* exporta documente.

#### Administrator

Poate face tot ce face un utilizator autentificat și, suplimentar:

* administra utilizatori;
* crea, edita și șterge șabloane;
* accesa panoul de administrare;
* vizualiza statistici;
* consulta jurnalul de activitate.

---

### 8. Modul de administrare

Aplicația include un modul propriu de administrare în directorul `admin`.

Funcționalități:

* dashboard administrativ;
* gestionare utilizatori;
* vizualizare documente generate;
* vizualizare loguri;
* statistici despre utilizatori, documente, exporturi și activitate.

---

## Arhitectura aplicației

Aplicația folosește o arhitectură modulară bazată pe servicii Web.

Fluxul principal este:

```text
Browser
   ↓ AJAX
API PHP
   ↓
Servicii PHP
   ↓
Database Layer
   ↓
SQLite
```

Interfața comunică asincron cu serverul prin cereri AJAX. Endpoint-urile din directorul `api` primesc cererile, validează datele și apelează serviciile corespunzătoare din `lib/services`.

---

## API-uri disponibile

Directorul `api` conține endpoint-urile principale ale aplicației:

```text
api/data.php
api/documents.php
api/export.php
api/schemas.php
api/templates.php
```

Roluri principale:

* `data.php` — generare date și import CSV;
* `documents.php` — generare, listare, citire și ștergere documente;
* `export.php` — export HTML, PDF, CSV și JSON;
* `schemas.php` — gestionare scheme de câmpuri;
* `templates.php` — gestionare șabloane.

---

## Servicii PHP

Logica aplicației este separată în servicii dedicate.

```text
lib/services/AuthService.php
lib/services/CsvService.php
lib/services/DataService.php
lib/services/DocumentService.php
lib/services/ExportService.php
lib/services/SchemaService.php
lib/services/TemplateService.php
```

### AuthService

Gestionează autentificarea, sesiunea curentă și verificarea rolului utilizatorului.

### CsvService

Gestionează importul și exportul fișierelor CSV.

### DataService

Validează câmpurile și coordonează generarea datelor.

### DocumentService

Gestionează documentele generate și operațiile asociate acestora.

### ExportService

Coordonează exporturile în formatele HTML, PDF, CSV și JSON.

### SchemaService

Gestionează schemele de câmpuri definite de utilizator.

### TemplateService

Gestionează șabloanele disponibile în aplicație.

---

## Motorul de templating

Motorul de templating este implementat în:

```text
lib/core/TemplateEngine.php
```

Acesta are rolul de a procesa șabloanele HTML și de a înlocui variabilele cu valori concrete.

Funcționalități:

* înlocuire variabile simple;
* procesare variabile dinamice de timp;
* procesare blocuri condiționale;
* generare document HTML final;
* salvare document generat.

---

## Export PDF

Exportul PDF este implementat în:

```text
lib/core/PdfExporter.php
```

Funcționare:

* dacă biblioteca mPDF este disponibilă, aceasta este folosită pentru conversia HTML în PDF;
* dacă mPDF nu este disponibilă, se utilizează un fallback intern care extrage textul din HTML și generează un PDF minimal.

---

## Baza de date

Aplicația utilizează SQLite.

Fișiere relevante:

```text
db/database.sqlite
db/schema.sql
```

Tabele principale:

* `users`
* `templates`
* `schemas`
* `documents`
* `exports`
* `logs`
* `csv_imports`

Conexiunea la baza de date este gestionată prin clasa:

```text
lib/Database.php
```

Aceasta folosește PDO și activează suportul pentru chei externe în SQLite.

---

## Securitate

Aplicația include măsuri pentru prevenirea atacurilor uzuale.

### SQL Injection

Interogările SQL sunt executate prin PDO și prepared statements.

### Cross-Site Scripting

Datele afișate în interfață sunt escapate folosind `htmlspecialchars`.

### Control acces

Operațiile sensibile sunt verificate prin sesiune și rol:

* utilizator;
* administrator.

### Upload CSV

Fișierele CSV sunt validate înainte de procesare.

---

## Structura proiectului

```text
TW_CURMEIBIANCA_PREUTUDIANA_2A2/
│   .gitignore
│   .htaccess
│   config.php
│   index.php
│
├── admin/
│       documents.php
│       index.php
│       logs.php
│       users.php
│
├── api/
│       data.php
│       documents.php
│       export.php
│       schemas.php
│       templates.php
│
├── db/
│       database.sqlite
│       schema.sql
│
├── generated/
│   ├── html/
│   │       .gitkeep
│   ├── pdf/
│   │       .gitkeep
│   └── tmp/
│           .gitkeep
│
├── lib/
│   │   Database.php
│   │   DataGenerator.php
│   │   FieldTypes.php
│   │
│   ├── core/
│   │       PdfExporter.php
│   │       TemplateEngine.php
│   │
│   └── services/
│           AuthService.php
│           CsvService.php
│           DataService.php
│           DocumentService.php
│           ExportService.php
│           SchemaService.php
│           TemplateService.php
│
├── public/
│   ├── css/
│   │       admin.css
│   │       editor.css
│   │       main.css
│   │
│   └── js/
│           app.js
│           editor.js
│           generator.js
│           preview.js
│
├── templates/
│       cerere.json
│       cv.json
│       factura.json
│
├── uploads/
│       .gitkeep
│
└── views/
        admin.php
        documents.php
        editor.php
        generator.php
        home.php
        preview.php
```

Fișierele generate automat din `generated` și `uploads` pot varia în funcție de utilizarea aplicației.

---

## Instalare și rulare

### Cerințe

* PHP 8+
* SQLite 3
* extensia PDO SQLite activată
* server local Apache, XAMPP sau WAMP

### Pași de instalare

1. Clonarea repository-ului:

```bash
git clone https://github.com/dianapreutu/TW_CURMEIBIANCA_PREUTUDIANA_2A2.git
```

2. Mutarea proiectului în directorul serverului local, de exemplu:

```text
htdocs/
```

3. Pornirea serverului Apache.

4. Accesarea aplicației în browser:

```text
http://localhost/TW_CURMEIBIANCA_PREUTUDIANA_2A2/
```

Baza de date SQLite este inițializată automat pe baza fișierului `schema.sql`.

---

## Cont administrator implicit

```text
Username: admin
Parolă: admin1234
```

Administratorul poate adăuga alți utilizatori din panoul de administrare.

---

## Compatibilitate cu cerințele proiectului

Aplicația respectă cerințele principale ale proiectului la Tehnologii Web:

* implementare server-side în PHP;
* fără framework-uri frontend sau backend;
* arhitectură bazată pe servicii Web;
* comunicare asincronă prin AJAX;
* interfață HTML și CSS;
* design responsiv;
* stocare în SQLite;
* prevenire SQL Injection;
* prevenire XSS;
* import/export CSV și JSON;
* modul propriu de administrare;
* modularizare și comentarii în cod;
* versionare prin Git și GitHub.

---

## Livrabile

Repository-ul conține:

* codul sursă al aplicației;
* baza de date SQLite;
* fișierul `schema.sql`;
* șabloane JSON predefinite;
* raportul tehnic în format Scholarly HTML;
* fișiere generate pentru testare;
* README-ul proiectului.

---

## Observații

Aplicația a fost dezvoltată fără utilizarea framework-urilor, conform cerințelor disciplinei. Structura proiectului este modulară, iar logica principală este separată în servicii și clase dedicate pentru a facilita mentenanța și extinderea ulterioară.