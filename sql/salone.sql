-- ============================================================
-- SALONE MEDICI — teljes adatbázis (séma + kiinduló tartalom)
--
-- EGYETLEN import elég egy új telepítéshez: phpMyAdmin → az üres
-- adatbázis kiválasztása → Import → ez a fájl → Go.
--
-- A kiinduló tartalom a Salone Medici saját anyagaiból származik
-- (Rendezvénystratégia + Események / Exkluzív bérlés 2026), és
-- teljes egészében szerkeszthető az /admin felületen.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ------------------------------------------------------------
-- users — admin fiókok
-- (Nincs alapértelmezett jelszó: az első fiókot az /admin/install.php
--  hozza létre, ami utána automatikusan letiltja magát.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login    DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ------------------------------------------------------------
-- site_settings — kulcs/érték beállítások (admin → Beállítások)
-- A weboldal MINDEN szövege innen jön.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(80)  NOT NULL,
  setting_value TEXT         NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ------------------------------------------------------------
-- packages — a bérelhető csomagok („A kínálat")
--   name_it     olasz cím (ez a nagybetűs felső sor)
--   name_hu     magyar cím (ez a fő cím)
--   price_from  „-tól" ár; NULL, ha csak szöveges árazás van
--   price_unit  pl. „/ fő" vagy „helyszín"
--   features    soronként egy pont — a jobb oldali lista
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS packages (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(60)  NOT NULL,
  name_it       VARCHAR(120) NOT NULL,
  name_hu       VARCHAR(120) NOT NULL,
  tagline       VARCHAR(255) NULL,
  description   TEXT         NULL,
  highlight     VARCHAR(255) NULL,
  price_from    INT UNSIGNED NULL,
  price_unit    VARCHAR(60)  NULL,
  price_note    VARCHAR(160) NULL,
  capacity_note VARCHAR(120) NULL,
  features      TEXT         NULL,
  sort_order    INT          NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_packages_slug (slug),
  KEY idx_packages_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ------------------------------------------------------------
-- addons — kiegészítők bármelyik csomaghoz
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS addons (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(160) NOT NULL,
  description TEXT         NULL,
  price_from  INT UNSIGNED NULL,
  price_unit  VARCHAR(60)  NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_addons_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ------------------------------------------------------------
-- event_series — a visszatérő estformátumok (Salotto Musicale stb.)
-- Ezek állandóak: akkor is megjelennek, ha épp nincs kitűzött dátum.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_series (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(60)  NOT NULL,
  name        VARCHAR(120) NOT NULL,
  cadence     VARCHAR(60)  NULL,
  tagline     VARCHAR(255) NULL,
  description TEXT         NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_series_slug (slug),
  KEY idx_series_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ------------------------------------------------------------
-- events — a kitűzött, dátumos esték (admin → Események)
-- A lejárt esték maguktól lekerülnek a weboldalról.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  series_id     INT UNSIGNED NULL,
  title         VARCHAR(190) NOT NULL,
  event_date    DATE         NOT NULL,
  start_time    TIME         NULL,
  lead_text     VARCHAR(255) NULL,   -- NB: `lead` is reserved in MySQL 8
  body          TEXT         NULL,
  price_note    VARCHAR(120) NULL,
  capacity_note VARCHAR(120) NULL,
  ticket_url    VARCHAR(255) NULL,
  is_sold_out   TINYINT(1)   NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_date (is_active, event_date),
  CONSTRAINT fk_events_series FOREIGN KEY (series_id)
    REFERENCES event_series (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ------------------------------------------------------------
-- gallery — teljes képernyős „átrepülős" fotóoldalak a főoldalon
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gallery (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  caption    VARCHAR(190) NULL,
  image      VARCHAR(190) NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ------------------------------------------------------------
-- enquiries — a weboldalról érkező ajánlatkérések
-- (admin → Ajánlatkérések; e-mail értesítés is megy, ha be van állítva)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS enquiries (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  phone       VARCHAR(60)  NULL,
  company     VARCHAR(160) NULL,
  package_id  INT UNSIGNED NULL,
  event_date  DATE         NULL,
  guests      SMALLINT UNSIGNED NULL,
  message     TEXT         NULL,
  ip          VARBINARY(16) NULL,
  status      ENUM('new','read','archived') NOT NULL DEFAULT 'new',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_enquiries_status (status, created_at),
  KEY idx_enquiries_ip (ip, created_at),
  CONSTRAINT fk_enquiries_package FOREIGN KEY (package_id)
    REFERENCES packages (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- A KÍNÁLAT — hat bérlési csomag
-- Forrás: „Salone Medici · Események / Exkluzív bérlés 2026".
-- Az admin → Csomagok oldalon bármelyik átírható.
-- ============================================================
INSERT INTO packages
  (slug, name_it, name_hu, tagline, description, highlight,
   price_from, price_unit, price_note, capacity_note, features, sort_order) VALUES

('tavola', 'La Tavola dei Medici', 'A Medici Asztal',
 'Meghitt, ültetett vacsora a jellegzetes tükörlapos asztalnál',
 'Legtöbbet fotózott helyszínünk: egyetlen hosszú, gyertyafényes asztal csiszolt fekete üvegből, végigfutó ezüst gyertyatartókkal, kristállyal minden teríték mellett. Többfogásos menü, fogásról fogásra felszolgálva, miközben a szalon ragyog Ön körül.',
 'Az este a meghatározó pillanatoké, a vezetői vacsoráké és azoké az alkalmaké, amelyek egyetlen közös asztalt érdemelnek.',
 42000, '/ fő', NULL, '12–24 fő · esti bérlés',
 'A szalonszint kizárólagos használata\nEgyedi, többfogásos menü\nTeljes kristály- és ezüstteríték\nGyertyafény mindenütt\nEgyedi műalkotás a szalon képernyőjén\nDedikált házigazda az estére',
 10),

('degustazione', 'Degustazione', 'Kóstolók és borpárosítások',
 'Vezetett bor-, pezsgő- és párlatkóstoló esték',
 'Sommelier vezette utazás egy gondosan válogatott kóstolósoron át; minden kortyot a terem képernyője mutat be — a birtok, a pincészet, a történet —, a hangzás és a fény ehhez hangolva. Működik ültetett, szalon jellegű kóstolóként vagy állófogadásként mindkét szinten.',
 'Ideális magánvendégeknek, importőri bemutatókhoz és céges ajándékozáshoz.',
 28000, '/ fő', NULL, '20–70 fő',
 'Hat kortyból álló válogatott kóstolósor\nSommelier vagy meghívott szakértő\nPárosított falatok a konyháról\nTematikus vizuál és hangzásvilág\nSzalon vagy teljes szintes forma\nKóstolójegyzet minden vendégnek',
 20),

('celebrazioni', 'Celebrazioni', 'Esküvők és ünneplések',
 'Mikroesküvők, kerek születésnapok és évfordulók',
 'Azoknak, akik a hangulatot többre tartják a méretnél — designszalon a bálterem helyett. Meghitt szertartás, gyertyafényes vacsora, az Önök elképzelése szerint megvilágított nyitótánc, és történetük filmszerű montázsa a hatalmas képernyőn.',
 'Ez a tér a személyes hangulatú estékre készült.',
 38000, '/ fő', 'Teljes bérlés 1 200 000 Ft-tól + vacsora', 'Akár 70 fő · mindkét szint',
 'Mindkét szint és az erkély\nHelyszíni szertartás lehetősége\nÜltetett vacsora akár 70 főig\nEgyedi fénytervezés\nSzemélyes montázs a képernyőn\nKülön bár és hosszabbítás',
 30),

('arte', 'Salotto d''Arte', 'Galéria- és kulturális esték',
 'Kiállítások, zenehallgatós esték, vetítések és zongoraesték',
 'A szalon eleve galériaként él — építsünk hát erre. Mutasson be egy művészt a falakon, debütáltasson filmet vagy lemezt a képernyőn és a hangrendszeren, vagy töltse meg a teret élő zongorajátékkal a szalon saját hangszerén.',
 'Állófogadás, amelynek valódi rangja van.',
 650000, 'helyszín', '+ falatkák 16 000 Ft/fő-től', 'Állófogadás akár 100 főig',
 'Fogadás akár 100 főig\nA képernyő mint digitális vászon\nHázi zongora és hangrendszer\nErkély és galéria használatban\nFalatkák és bárkiszolgálás\nKurátori vagy házigazdai köszöntő',
 40),

('affari', 'Affari', 'Vezetői és céges események',
 'Vezetői vacsorák, ügyfélfogadások és termékbemutatók',
 'Cégeknek, amelyek legfontosabb vendégeiket egy újabb hotelterem helyett valami emlékezetessel szeretnék lenyűgözni. A képernyő barokk műalkotásba szőve viszi az Önök márkáját, a teljes technika kiszolgál egy prezentációt vagy élő közvetítést, a többit pedig elvégzi a tér.',
 'Visszafogottan lenyűgöző — pontosan úgy, ahogy az a meghívott vendégeknek számít.',
 45000, '/ fő', 'Bemutató bérlés 750 000 Ft-tól', 'Vacsora 70 fő · fogadás 100 fő',
 'Vezetői vacsora akár 70 főig\nFogadás akár 100 főig\nPrezentációs és élő közvetítési technika\nEgyedi, márkázott képernyő-műalkotás\nKülön bár és diszkrét kiszolgálás\nDedikált rendezvénymenedzser',
 50),

('esclusiva', 'Esclusiva', 'A teljes szalon',
 'Mindkét szint, az egész este, teljesen az Öné',
 'A teljes bérlés — szalon, galéria, erkély és bár —, elejétől a végéig megtervezve. Fogadás az egyik szinten, vacsora a másikon; a világítás és a képernyő az Önök estéjére koreografálva; minden részlet egyedi, a menütől a zenéig.',
 'Zászlóshajónk arra az alkalomra, amikor az egész háznak egyetlen eseményhez kell tartoznia.',
 1200000, 'helyszín', '+ egyedi vendéglátás', 'Teljesen egyedi este',
 'A teljes helyszín, mindkét szint\nErkély, bár és zongora\nTeljes világítás- és képernyőprogramozás\nKétzónás fogadás + vacsora menete\nEgyedi menü- és italkínálat-tervezés\nRendezvénymenedzser és teljes stáb',
 60);

-- ============================================================
-- KIEGÉSZÍTŐK
-- ============================================================
INSERT INTO addons (name, description, price_from, price_unit, sort_order) VALUES
('Élő zongora és vonósok',      'Zongorista a szalon saját hangszerén, vagy vonós együttes az érkezéshez és a vacsorához.', 90000,  NULL,  10),
('Egyedi képernyő-műalkotás',   'Egyedi barokk kompozíció az Önök monogramja, márkája vagy alkalma köré építve.',          120000, NULL,  20),
('Sommelier-párosítás',         'Fogásról fogásra párosított borsor, az asztalnál bemutatva.',                             18000,  '/ fő', 30),
('Virág- és gyertyadekoráció',  'Háromféle dekorációs szint a visszafogottan elegánstól a teljesen feldíszített szalonig.', 80000,  NULL,  40),
('Fotó és film',                'Fotós vagy filmes, aki megörökíti a teret és az estét.',                                   110000, NULL,  50),
('Hosszabbítás és DJ',          'Hosszabbítsa meg az estét DJ-vel, a termet az éjszakai hangulatára hangolva.',            130000, NULL,  60);

-- ============================================================
-- AZ ÉVAD — visszatérő estformátumok
-- Forrás: „A Szalon Évadterve — Rendezvénykoncepciók & stratégia".
-- Ezek akkor is látszanak, ha épp nincs kitűzött dátum.
-- ============================================================
INSERT INTO event_series (slug, name, cadence, tagline, description, sort_order) VALUES
('salotto-musicale', 'Salotto Musicale', 'havonta',
 'Intim koncertsorozat a zongoránál',
 'Kamarazene, jazztrió vagy egyetlen énekes — havonta cserélődő fellépővel. Mintegy ötven ülőhely, egy welcome itallal. A kétszintes tér és a kandeláber elvégzi a többit.', 10),
('degustazione', 'Degustazione', 'havonta',
 'Tematikus bor- és pálinkakóstoló esték',
 'Havi kóstoló, mindig más téma köré szervezve — villányi borok, olasz régiók, természetes borok. Villány fél órára van innen: a borászat hozza a palackokat, a szalon az estét.', 20),
('supper-club', 'Supper Club', 'havonta',
 'Vendégséf-vacsora a bankettasztalnál',
 'Egyetlen terítés a tükörlapos asztalnál, korlátozott létszám, fix menü, cserélődő vendégséffel. A szűkösség maga a termék.', 30),
('ballo-in-maschera', 'Ballo in Maschera', 'évente',
 'A teljes itáliai-velencei fantázia, egyetlen estére',
 'Évente egyszer, teljes pompával: black-tie, álarcok, a teljesen feldíszített szalon. Az az este, amelyet a törzsközönség egész évben vár.', 40);

-- ============================================================
-- GALÉRIA — a főoldali „átrepülős" fotóoldalak
-- A képek a uploads/gallery mappában vannak; az admin → Galéria
-- oldalon cserélhetők, átnevezhetők, sorrendezhetők.
-- ============================================================
INSERT INTO gallery (caption, image, sort_order, is_active) VALUES
('A szalonszint — gyertyafény és kristály',   'salone-szalon.jpg',                10, 1),
('Ezüst gyertyatartók a tükörlapos asztalon', 'salone-fenyek.jpg',                20, 1),
('A Medici Asztal, megterítve',               'salone-rendezvenyhelyszin.jpg',    30, 1),
('A zongora és az élő vászon',                'salone-lathatatlan-zongista.jpg',  40, 1),
('Az este színei — a fénypark munkában',      'salone-teritek.jpg',               50, 1),
('Ültetett vacsora a szalonszinten',          'salone-szalon-enterior.jpg',       60, 0),
('Részletek',                                 'salone-szalon-dekor.jpg',          70, 0);

-- ============================================================
-- BEÁLLÍTÁSOK
-- A weboldal minden szövege innen jön — az admin → Beállítások
-- oldalon szerkeszthető, HTML-hez nyúlni nem kell.
-- ============================================================
INSERT INTO site_settings (setting_key, setting_value) VALUES

-- ---- Alapadatok (forrás: salonemedici.hu — ellenőrizd élesítés előtt) ----
('venue_name',        'Salone Medici'),
('venue_city',        'Pécs'),
('address_line1',     'Verseny u. 1. · Ciao Ristorante'),
('address_line2',     '7622 Pécs'),
('phone',             '+36 70 224 7035'),
('email',             'lucrezia@salonemedici.hu'),
('map_url',           'https://maps.google.com/?q=P%C3%A9cs%2C+Verseny+u.+1'),
('availability',      'Személyes egyeztetésre és a Salone Medici megtekintésére előre egyeztetett időpontban van lehetőség.'),
('facebook_url',      'https://www.facebook.com/salonemedici/'),
('instagram_url',     'https://www.instagram.com/salonemedici/'),
('tickets_url',       ''),
('logo',              ''),
('hero_image',        'salone-hero.jpg'),
('hero_video',        ''),

-- ---- Ajánlatkérés ----
-- Ide érkezik az értesítő e-mail. Üresen hagyva csak az adatbázisba kerül
-- (admin → Ajánlatkérések), e-mail nem megy ki.
('enquiry_recipient', 'lucrezia@salonemedici.hu'),

-- ---- A weboldal szövegei ----
('hero_eyebrow',      'Salone · Medici · Pécs'),
('hero_tagline',      'Régi világ hangulatú szalon olyan estékhez, amelyekhez foghatót nem talál máshol a városban.'),
('overlay_eyebrow',   'Egy este a szalonban'),
('scroll_hint',       'Görgess — lépj be a szalonba'),

('story_heading',     'Gyertyafény'),
('story_heading_em',  'a tükörasztalon.'),
('story_text',        'Régi mesterek képei a zsályazöld falakon. Belmagas szalon, ahol a hatalmas képernyő élő műalkotássá válik, a világítás pedig együtt változik az estével. A Salone Medici olyan eseményeknek ad otthont, amelyekre évekig emlékeznek a vendégek — meghitt vacsoráknak, kóstolóknak, ünnepléseknek és visszafogottan elegáns céges estéknek.'),
('story_signature',   'Egy este a szalonban.'),

('spazio_eyebrow',    'A helyszín'),
('spazio_heading',    'Két szint,'),
('spazio_heading_em', 'egyetlen hangulat.'),
('spazio_text',       'Egy szalonszint és egy galéria egyetlen térré összekapcsolva — erkéllyel, külön bárral és a tér szívében egy barokk digitális vászonnal.'),
('cap1_num',          '70'),
('cap1_label',        'ülőhely'),
('cap1_note',         'Teljes, többfogásos vacsora mindkét szinten'),
('cap2_num',          '100'),
('cap2_label',        'állófogadás'),
('cap2_note',         'Fogadások, megnyitók és ünneplések'),
('cap3_num',          '24'),
('cap3_label',        'a Medici Asztal'),
('cap3_note',         'A jellegzetes tükörlapos díszasztal'),

('offerta_eyebrow',   'Exkluzív bérlés'),
('offerta_heading',   'A kínálat'),
('offerta_text',      'Hatféle mód a szalon bérlésére. Minden este egyedileg alakul — ezek kiindulópontok, nem korlátok.'),
('offerta_cta',       'A teljes kínálatot megnézem'),

('schermo_eyebrow',   'Az élő vászon'),
('schermo_heading',   'Sosem televízió.'),
('schermo_text',      'A szalon hatalmas képernyője digitális régi mester — barokk egek, az Önök monogramja, egy film, egy szőlőbirtok —, aranyozott keretben, a zongora fölött. A teljes fényparkkal együtt, amely gyertyaaranyba, rózsaszínbe vagy mély esti kékbe vonja a termet, egyetlen tér tucatnyi különböző hangulatúvá válhat egyetlen este alatt.'),

('eventi_eyebrow',    'Az évad'),
('eventi_heading',    'Esték a szalonban'),
('eventi_text',       'Visszatérő esték, amelyekre jegyet lehet váltani — és amelyek minden hónapban más arcát mutatják a szalonnak.'),
('eventi_empty',      'A következő est időpontja hamarosan. Kövessen minket, hogy elsőként tudja meg.'),
('eventi_cta',        'Az évad estéi'),

('richiesta_eyebrow', 'Ajánlatkérés'),
('richiesta_heading', 'Meséljen az estéről.'),
('richiesta_text',    'Írja le, mit tervez — és hamarosan egyedi ajánlattal jelentkezünk. Minden ár tájékoztató kiindulópont; az estét mindig közösen alakítjuk ki.'),
('richiesta_thanks',  'Köszönjük — megkaptuk a kérését. Hamarosan jelentkezünk a megadott elérhetőségen.'),

('closing_heading',   'Egy hely,'),
('closing_heading_em','ahol történik valami.'),
('closing_text',      'A Salone Medici nem versenyzik a nagyobb termekkel — mert nem ugyanazt kínálja. A karaktert, az intimitást, a hangulatot.'),

('addons_heading',    'Kiegészítők'),
('addons_text',       'Bármelyik csomaghoz hozzáadható, hogy az este teljesen az Öné legyen.'),
('price_note',        'Minden ár tájékoztató jellegű kiindulópont, és nem tartalmazza az áfát. Minden eseményről egyedi árajánlatot adunk, miután megismertük az Önök elképzelését.'),
('footer_extra',      '');
