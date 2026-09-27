<?php
/**
 * Salone Medici — konfiguráció.
 *
 * TELEPÍTÉSKOR CSAK EZT A FÁJLT KELL SZERKESZTENI.
 * Töltsd ki a MySQL adatokat a DreamHost panelből
 * (MySQL Databases → az adatbázisod).
 */

// ---- Adatbázis ----------------------------------------------------------
// A hostname az, amit az adatbázis létrehozásakor megadtál
// (jellemzően mysql.<adomain>.hu / .com).
define('DB_HOST', 'mysql.salonemedici.hu');  // ⚠️ KITÖLTENDŐ
define('DB_NAME', 'salone_db');              // ⚠️ KITÖLTENDŐ
define('DB_USER', 'salone_user');            // ⚠️ KITÖLTENDŐ
define('DB_PASS', 'CHANGE_ME');              // ⚠️ KITÖLTENDŐ
define('DB_CHARSET', 'utf8mb4');

// ---- Útvonalak ----------------------------------------------------------
// A projekt gyökere (az a mappa, amelyben az index.php van).
define('BASE_PATH', dirname(__DIR__));
// Publikus URL-alap: '' ha a domain gyökerében fut,
// vagy pl. '/szalon' ha almappában.
define('BASE_URL', '');

// ---- Feltöltések --------------------------------------------------------
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024);        // 5 MB
define('UPLOAD_GALLERY_DIR', BASE_PATH . '/uploads/gallery');
define('UPLOAD_BRANDING_DIR', BASE_PATH . '/uploads/branding');
define('THUMB_MAX_WIDTH', 480);                     // bélyegkép szélessége

// ---- Munkamenet / biztonság ---------------------------------------------
// FONTOS: ha több oldal fut ugyanazon a szerveren, mindegyik kapjon
// EGYEDI session nevet, hogy ne üssék egymást.
define('SESSION_NAME', 'salone_admin');
define('SESSION_TIMEOUT_MINUTES', 30);              // tétlenségi időkorlát

// ---- Ajánlatkérés -------------------------------------------------------
// Hány ajánlatkérés érkezhet egy IP-ről óránként (spam-fék).
define('ENQUIRY_RATE_PER_HOUR', 5);
// Az ajánlatkérő űrlap aláírásához használt titok. A publikus űrlap
// nem indít munkamenetet (nincs sütije) — helyette ezzel írjuk alá a
// megnyitás időbélyegét, így a robotok azonnali beküldése kiszűrhető.
// ⚠️ TELEPÍTÉSKOR cseréld le egy hosszú, véletlen karakterláncra!
define('FORM_SECRET', 'CHANGE_ME_egy_hosszu_veletlen_karakterlanc');

// ---- Hibakeresés ---------------------------------------------------------
// Csak telepítési hiba keresésekor kapcsold true-ra, utána vissza false-ra!
define('DEBUG', false);

if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}
