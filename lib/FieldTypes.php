<?php

// Tipuri de campuri suportate de generator
// Fiecare tip stie sa genereze o valoare aleatorie realista si sa se descrie (pt UI si pt export)

class FieldTypes {
 
    public static function generate(string $type, array $options = []): string {
        // Apelam metoda corespunzatoare tipului
        // Daca tipul nu exista, returnam un string gol
        switch ($type) {
            case 'full_name':       return self::fullName();
            case 'first_name':      return self::firstName();
            case 'last_name':       return self::lastName();
            case 'email':           return self::email();
            case 'phone':           return self::phone();
            case 'address':         return self::address();
            case 'city':            return self::city();
            case 'county':          return self::county();
            case 'cnp':             return self::cnp();
            case 'cui':             return self::cui();
            case 'iban':            return self::iban();
            case 'date':            return self::date($options);
            case 'number':          return self::number($options);
            case 'price':           return self::price($options);
            case 'tva':             return self::tva();
            case 'invoice_number':  return self::invoiceNumber();
            case 'company':         return self::company();
            case 'job_title':       return self::jobTitle();
            case 'education':       return self::education();
            case 'product':         return self::product();
            case 'text':            return self::shortText();
            case 'paragraph':       return self::paragraph();
            default:                return '';
        }
    }
 
    // Returneaza lista tuturor tipurilor disponibile
    
    public static function getAll(): array {
        return [
            // Date personale
            'full_name'      => 'Nume complet',
            'first_name'     => 'Prenume',
            'last_name'      => 'Nume de familie',
            'email'          => 'Email',
            'phone'          => 'Telefon',
            'address'        => 'Adresa completa',
            'city'           => 'Oras',
            'county'         => 'Judet',
            // Identificatori romani
            'cnp'            => 'CNP',
            'cui'            => 'CUI firma',
            'iban'           => 'IBAN',
            // Date financiare
            'price'          => 'Pret (RON)',
            'tva'            => 'TVA (%)',
            'invoice_number' => 'Numar factura',
            // Date profesionale
            'company'        => 'Firma',
            'job_title'      => 'Functie/Ocupatie',
            'education'      => 'Nivel studii',
            'product'        => 'Produs/Serviciu',
            // Date generice
            'date'           => 'Data',
            'number'         => 'Numar intreg',
            'text'           => 'Text scurt',
            'paragraph'      => 'Paragraf text',
        ];
    }
 
    // Returneaza descrierea unui tip (pt tooltip in UI)

    public static function describe(string $type): string {
        $descriptions = [
            'full_name'      => 'Nume si prenume romanesc (ex: Ion Popescu)',
            'first_name'     => 'Prenume romanesc (ex: Maria)',
            'last_name'      => 'Nume de familie romanesc (ex: Ionescu)',
            'email'          => 'Adresa email valida (ex: ion.popescu@gmail.com)',
            'phone'          => 'Numar de telefon roman (ex: 0721 234 567)',
            'address'        => 'Adresa completa cu strada si numar',
            'city'           => 'Oras din Romania',
            'county'         => 'Judet din Romania',
            'cnp'            => 'Cod Numeric Personal valid ca format (13 cifre)',
            'cui'            => 'Cod Unic de Inregistrare firma (ex: RO12345678)',
            'iban'           => 'IBAN romanesc valid ca format (RO + 22 caractere)',
            'price'          => 'Pret in RON cu 2 zecimale (ex: 149.99)',
            'tva'            => 'Cota TVA romaneasca (0, 5, 9 sau 19%)',
            'invoice_number' => 'Numar factura fiscala (ex: FCT-2024-00123)',
            'company'        => 'Denumire firma romaneasca',
            'job_title'      => 'Titlu/functie profesionala',
            'education'      => 'Nivel de educatie (ex: Licenta, Master)',
            'product'        => 'Denumire produs sau serviciu',
            'date'           => 'Data in format ZZ.LL.AAAA',
            'number'         => 'Numar intreg pozitiv',
            'text'           => 'Text scurt (1-2 propozitii)',
            'paragraph'      => 'Paragraf de text (3-5 propozitii)',
        ];
        return $descriptions[$type] ?? 'Tip necunoscut';
    }

    // Generatoare private 
 
    // Nume romanesti

    private static function firstName(): string {
        $masculine = [
            'Alexandru', 'Andrei', 'Bogdan', 'Catalin', 'Cristian',
            'Daniel', 'Dragos', 'Emil', 'Florin', 'Gabriel',
            'George', 'Gheorghe', 'Ioan', 'Ion', 'Ionut',
            'Lucian', 'Marian', 'Mihai', 'Mircea', 'Nicolae',
            'Octavian', 'Paul', 'Radu', 'Razvan', 'Sebastian',
            'Stefan', 'Tudor', 'Valentin', 'Victor', 'Vlad'
        ];
        $feminine = [
            'Alexandra', 'Alina', 'Ana', 'Andreea', 'Bianca',
            'Camelia', 'Carmen', 'Cristina', 'Diana', 'Elena',
            'Gabriela', 'Ioana', 'Irina', 'Laura', 'Luminita',
            'Maria', 'Mihaela', 'Monica', 'Nicoleta', 'Oana',
            'Raluca', 'Ramona', 'Roxana', 'Simona', 'Sorina',
            'Teodora', 'Valentina', 'Veronica', 'Victoria', 'Zorica'
        ];
        // Alegem aleatoriu intre masculin si feminin
        $pool = (rand(0, 1) === 0) ? $masculine : $feminine;
        return $pool[array_rand($pool)];
    }
 
    private static function lastName(): string {
        $lastNames = [
            'Andrei', 'Badea', 'Barbu', 'Bucur', 'Chelaru',
            'Ciobanu', 'Cojocaru', 'Constantin', 'Cosma', 'Cristea',
            'Dima', 'Dinu', 'Dumitrescu', 'Florescu', 'Gheorghiu',
            'Ghita', 'Iancu', 'Iliescu', 'Ionescu', 'Ivan',
            'Lazar', 'Lupu', 'Marin', 'Marinescu', 'Matei',
            'Mihai', 'Mihalcea', 'Moldovan', 'Morar', 'Muresan',
            'Nedelcu', 'Neagu', 'Niculescu', 'Oprea', 'Pana',
            'Pavel', 'Petrescu', 'Popa', 'Popescu', 'Preda',
            'Radu', 'Roman', 'Rusu', 'Sandu', 'Serban',
            'Stan', 'Stanciu', 'Stoica', 'Stoicescu', 'Tudor',
            'Ungureanu', 'Vasile', 'Vlad', 'Voicu', 'Zaharia'
        ];
        return $lastNames[array_rand($lastNames)];
    }
 
    private static function fullName(): string {
        // Format romanesc: Prenume Nume
        return self::firstName() . ' ' . self::lastName();
    }
 
    // Contact
 
    private static function email(): string {
        $firstName = strtolower(self::removeAccents(self::firstName()));
        $lastName  = strtolower(self::removeAccents(self::lastName()));
        $domains   = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'yahoo.ro'];
        $separators = ['.', '_', ''];
        $sep = $separators[array_rand($separators)];
        $domain = $domains[array_rand($domains)];
        // Variante: ion.popescu@gmail.com sau ionpopescu42@yahoo.ro
        $suffix = (rand(0, 1) === 0) ? '' : rand(10, 99);
        return $firstName . $sep . $lastName . $suffix . '@' . $domain;
    }
 
    private static function phone(): string {
        // Prefixe reale de retele din Romania
        $prefixes = ['072', '073', '074', '075', '076', '077', '078', '021', '031'];
        $prefix = $prefixes[array_rand($prefixes)];
        // Restul de 7 cifre
        $rest = str_pad(rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        return $prefix . ' ' . substr($rest, 0, 3) . ' ' . substr($rest, 3);
    }
 
    // Adresa
 
    private static function address(): string {
        $streets = [
            'Calea Victoriei', 'Strada Florilor', 'Bulevardul Unirii',
            'Strada Mihai Eminescu', 'Calea Dorobantilor', 'Strada Libertatii',
            'Bulevardul Magheru', 'Strada Ion Creanga', 'Aleea Trandafirilor',
            'Strada Republicii', 'Calea Mosilor', 'Strada Independentei',
            'Bulevardul Nicolae Balcescu', 'Strada Vasile Alecsandri'
        ];
        $street = $streets[array_rand($streets)];
        $number = rand(1, 200);
        $city   = self::city();
        // Uneori adaugam si nr apartament
        $apt = (rand(0, 1) === 0) ? ', Ap. ' . rand(1, 50) : '';
        return $street . ' nr. ' . $number . $apt . ', ' . $city;
    }
 
    private static function city(): string {
        $cities = [
            'Bucuresti', 'Cluj-Napoca', 'Timisoara', 'Iasi', 'Constanta',
            'Craiova', 'Brasov', 'Galati', 'Ploiesti', 'Oradea',
            'Braila', 'Arad', 'Pitesti', 'Sibiu', 'Bacau',
            'Targu Mures', 'Baia Mare', 'Buzau', 'Satu Mare', 'Botosani',
            'Ramnicu Valcea', 'Drobeta-Turnu Severin', 'Suceava', 'Piatra Neamt', 'Deva'
        ];
        return $cities[array_rand($cities)];
    }
 
    private static function county(): string {
        $counties = [
            'Alba', 'Arad', 'Arges', 'Bacau', 'Bihor',
            'Bistrita-Nasaud', 'Botosani', 'Braila', 'Brasov', 'Buzau',
            'Calarasi', 'Cluj', 'Constanta', 'Covasna', 'Dambovita',
            'Dolj', 'Galati', 'Giurgiu', 'Gorj', 'Harghita',
            'Hunedoara', 'Ialomita', 'Iasi', 'Ilfov', 'Maramures',
            'Mehedinti', 'Mures', 'Neamt', 'Olt', 'Prahova',
            'Salaj', 'Satu Mare', 'Sibiu', 'Suceava', 'Teleorman',
            'Timis', 'Tulcea', 'Valcea', 'Vaslui', 'Vrancea',
            'Bucuresti'
        ];
        return $counties[array_rand($counties)];
    }
 
    // Identificatori romani
 
    private static function cnp(): string {
        // Genereaza un CNP cu format valid (nu neaparat cifra de control corecta)
        // S (sex+era): 1-8
        $s = rand(1, 8);
        // AA (an nastere): 00-99
        $aa = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
        // LL (luna): 01-12
        $ll = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        // ZZ (zi): 01-28 (simplificat)
        $zz = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
        // JJ (judet): 01-46 (coduri judete Romania)
        $jj = str_pad(rand(1, 46), 2, '0', STR_PAD_LEFT);
        // NNN (numar ordine): 001-999
        $nnn = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        // C (cifra control) - calculez corect
        $partial = $s . $aa . $ll . $zz . $jj . $nnn;
        $c = self::cnpControlDigit($partial);
        return $partial . $c;
    }
 
    // Calculeaza cifra de control a CNP-ului (algoritm oficial)
    private static function cnpControlDigit(string $partial): string {
        $weights = [2, 7, 9, 1, 4, 6, 3, 5, 8, 2, 7, 9];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$partial[$i] * $weights[$i];
        }
        $remainder = $sum % 11;
        return ($remainder === 10) ? '1' : (string)$remainder;
    }
 
    private static function cui(): string {
        // CUI = RO urmat de 2-10 cifre
        $digits = rand(10000000, 99999999);
        return 'RO' . $digits;
    }
 
    private static function iban(): string {
        // IBAN romanesc: RO + 2 cifre control + 4 litere banca + 16 cifre cont
        $banks = ['RNCB', 'BTRL', 'INGB', 'BRDE', 'BPOS', 'CECE', 'PIRB', 'BACX'];
        $bank = $banks[array_rand($banks)];
        $control = str_pad(rand(10, 99), 2, '0', STR_PAD_LEFT);
        $account = str_pad(rand(1000000000000000, 9999999999999999), 16, '0', STR_PAD_LEFT);
        return 'RO' . $control . $bank . $account;
    }

    // Date financiare

    private static function price(array $options = []): string {
        $min = $options['min'] ?? 1;
        $max = $options['max'] ?? 9999;
        // Pret cu 2 zecimale
        $price = rand($min * 100, $max * 100) / 100;
        return number_format($price, 2, '.', '');
    }
 
    private static function tva(): string {
        // Cotele de TVA din Romania
        $rates = ['0', '5', '9', '19'];
        return $rates[array_rand($rates)];
    }
 
    private static function invoiceNumber(): string {
        $year   = date('Y');
        $series = ['FCT', 'FACT', 'F', 'INV'];
        $serie  = $series[array_rand($series)];
        $number = str_pad(rand(1, 9999), 5, '0', STR_PAD_LEFT);
        return $serie . '-' . $year . '-' . $number;
    }
 
    // Date profesionale

    private static function company(): string {
        $prefixes = ['SC', 'SRL', 'SA'];
        $names = [
            'Alpha Tech', 'Beta Solutions', 'Construct Total', 'Digital Mind',
            'Euro Trans', 'Fast Delivery', 'Global Trade', 'Horizon Group',
            'Info Systems', 'Logic Net', 'Max Import', 'Nova Consulting',
            'Omega Design', 'Prime Services', 'Quick Build', 'Roman Invest',
            'Smart Energy', 'Top Production', 'Ultra Media', 'Vest Trading'
        ];
        $suffix = $prefixes[array_rand($prefixes)];
        $name   = $names[array_rand($names)];
        return $name . ' ' . $suffix;
    }
 
    private static function jobTitle(): string {
        $titles = [
            'Programator', 'Analist', 'Manager de proiect', 'Designer UI/UX',
            'Contabil', 'Jurist', 'Inginer software', 'Administrator retea',
            'Specialist marketing', 'Consultant vanzari', 'Arhitect', 'Medic',
            'Profesor', 'Economist', 'Auditor', 'Director executiv',
            'Specialist HR', 'Analist financiar', 'Inginer civil', 'Farmacist'
        ];
        return $titles[array_rand($titles)];
    }
 
    private static function education(): string {
        $levels = [
            'Studii medii (Bacalaureat)',
            'Studii postliceale',
            'Licenta (3 ani)',
            'Licenta (4 ani)',
            'Master',
            'Doctorat',
            'Studii postdoctorale'
        ];
        return $levels[array_rand($levels)];
    }
 
    private static function product(): string {
        $products = [
            'Servicii consultanta IT', 'Dezvoltare software', 'Mentenanta sistem',
            'Licenta software', 'Formare profesionala', 'Audit IT',
            'Hosting si domeniu', 'Servicii cloud', 'Suport tehnic',
            'Laptop Dell Latitude', 'Monitor LG 27"', 'Imprimanta HP LaserJet',
            'Scaun ergonomic', 'Birou reglabil', 'Telefon Samsung Galaxy',
            'Material de constructie', 'Servicii curatenie', 'Transport marfa'
        ];
        return $products[array_rand($products)];
    }
 
    // Date generice
 
    private static function date(array $options = []): string {
        // Interval implicit: ultimii 30 de ani pana azi
        $startYear = $options['start_year'] ?? (date('Y') - 30);
        $endYear   = $options['end_year']   ?? (int)date('Y');
        $start = mktime(0, 0, 0, 1, 1, $startYear);
        $end   = mktime(0, 0, 0, 12, 31, $endYear);
        $timestamp = rand($start, $end);
        // Format romanesc: ZZ.LL.AAAA
        return date('d.m.Y', $timestamp);
    }
 
    private static function number(array $options = []): string {
        $min = $options['min'] ?? 1;
        $max = $options['max'] ?? 100;
        return (string)rand($min, $max);
    }
 
    private static function shortText(): string {
        $texts = [
            'Solicit aprobarea cererii mentionate mai sus.',
            'Va rog sa luati in considerare aceasta solicitare.',
            'Documentul a fost verificat si este conform.',
            'Informatiile furnizate sunt corecte si complete.',
            'Va multumesc pentru atentia acordata.',
            'Astept un raspuns in termenul legal.',
            'Confirm primirea documentelor solicitate.',
            'Datele sunt valabile la data emiterii.',
        ];
        return $texts[array_rand($texts)];
    }
 
    private static function paragraph(): string {
        $paragraphs = [
            'Subsemnatul, in calitate de solicitant, va rog sa aprobati cererea de fata. Toate documentele necesare sunt anexate prezentei cereri. Va asigur ca informatiile furnizate sunt reale si complete.',
            'Prin prezenta va informam ca societatea noastra doreste sa stabileasca un parteneriat de colaborare. Suntem convinsi ca o astfel de cooperare va fi benefica pentru ambele parti. Asteptam raspunsul dumneavoastra.',
            'In urma analizei documentatiei depuse, va comunicam rezultatul evaluarii. Toate criteriile necesare au fost indeplinite conform reglementarilor in vigoare. Va rugam sa confirmati primirea acestei notificari.',
        ];
        return $paragraphs[array_rand($paragraphs)];
    }
 
    // Elimina diacriticele din string, pentru generarea email-urilor
    private static function removeAccents(string $str): string {
        $accents = ['ă','â','î','ș','ț','Ă','Â','Î','Ș','Ț','ş','ţ','Ş','Ţ'];
        $replace = ['a','a','i','s','t','A','A','I','S','T','s','t','S','T'];
        return str_replace($accents, $replace, $str);
    }
}
