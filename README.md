# Salone Medici — rendezvényhelyszín weboldal + admin

Egy este a szalonban: a látogató végigrepül az érkezéstől a téren, a
kínálaton, az élő vásznon és az évadon az ajánlatkérésig. Minden tartalom
— a csomagok, az esték, a fotók és **a weboldal összes szövege** — az
`/admin` felületen szerkeszthető; HTML-hez nyúlni sosem kell.

Készült DreamHost (vagy bármilyen) shared hostingra:
**PHP 8.1+, MySQL, HTML5, CSS, vanilla JS** — nincs Node, nincs Composer,
nincs framework, nincs frissítendő függőség.

---

## Mit tud?

| Funkció | Hol kezelhető |
|---|---|
| **Csomagok** — a hatféle bérlési mód, árral, létszámmal, „mit tartalmaz" listával | admin → **Csomagok** |
| **Kiegészítők** — élő zongora, sommelier, dekor, fotó… | admin → **Kiegészítők** |
| **Események** — dátumos esték jegylinkkel; a lejártak maguktól lekerülnek | admin → **Események** |
| **Sorozatok** — a visszatérő formátumok (Salotto Musicale, Degustazione, Supper Club, Ballo in Maschera) | admin → **Sorozatok** |
| **Ajánlatkérés** — űrlap a weboldalon, e-mail értesítéssel; minden kérés eltárolva | admin → **Ajánlatkérések** |
| Galéria fotók a JSON API-hoz (a főoldali „utazásban" **nem** jelennek meg) | admin → **Galéria** |
| Cím, telefon, e-mail, közösségi linkek, nyitókép, logó | admin → **Beállítások** |
| **A főoldal összes szövege** — fejezetenként | admin → **Beállítások** → „A weboldal szövegei" |
| JSON API (`/api/packages.php`, `events.php`, `gallery.php`, `settings.php`) | — |

Ha egy csomagot vagy estet *rejtettre* állítasz, azonnal eltűnik a
weboldalról — a csomag az ajánlatkérő űrlap listájából is.

---

## Telepítés (kb. 20 perc)

### 1. Fájlok feltöltése
Töltsd fel a mappa **teljes tartalmát** a domain gyökérmappájába
(pl. `~/salonemedici.hu/`), hogy az `index.php` közvetlenül ott legyen.
Almappába telepítve lásd a 4. pontot.

### 2. MySQL adatbázis
A hosting panelben hozz létre egy **üres adatbázist** + egy felhasználót
jelszóval. Jegyezd fel: hostname, adatbázisnév, felhasználó, jelszó.

### 3. Az adatbázis feltöltése
phpMyAdmin → válaszd ki az új adatbázist → **Import** →
**`sql/salone.sql`** → Go.
Ez létrehoz minden táblát, és feltölti a **teljes kiinduló tartalmat**:
a hat csomagot, a hat kiegészítőt, a négy estformátumot, a galériát és
a weboldal összes szövegét. *(Egyetlen fájl — nincs külön frissítő szkript.)*

### 4. `includes/config.php` kitöltése
```php
define('DB_HOST', 'mysql.salonemedici.hu');
define('DB_NAME', 'salone_db');
define('DB_USER', 'salone_user');
define('DB_PASS', '••••••••');
```
Ugyanitt **cseréld le a `FORM_SECRET` értékét** egy hosszú, véletlen
karakterláncra — ezzel írjuk alá az ajánlatkérő űrlapot (lásd „Biztonság").

- A domain gyökerében vagy aldomainen: `BASE_URL` maradjon `''`.
- Almappában (pl. `salonemedici.hu/uj`): `define('BASE_URL', '/uj');`
- Ha **több** oldal fut ugyanazon a szerveren, adj mindegyiknek
  egyedi `SESSION_NAME`-et.

### 5. Írási jog
Az `uploads/` mappa (és almappái) legyenek írhatók a PHP számára
(általában alapból jó; ha nem: `chmod 755`).

### 6. PHP verzió
Állítsd **PHP 8.1+**-ra a hosting panelben.

### 7. Első belépés
Nyisd meg: **`https://salonemedici.hu/admin/`**
A telepítő bekéri az első admin fiókot (felhasználónév + min. 10 karakteres
jelszó), majd **véglegesen letiltja magát**. Alapértelmezett jelszó nincs.

---

## Beüzemelés az ügyfélnek (sorrendben)

1. **Beállítások → A helyszín adatai** — ellenőrizd a címet, telefont,
   e-mailt. ⚠️ Ezek a `salonemedici.hu`-ról származó adatokkal vannak
   előre kitöltve; **élesítés előtt erősítsd meg őket az ügyféllel.**
2. **Beállítások → Ajánlatkérés** — az értesítési e-mail cím. Üresen hagyva
   nem megy levél, a kérések akkor is megmaradnak az adminban.
3. **Beállítások → Nyitókép** — a kiinduló nyitókép a zongorás fotó
   (`uploads/branding/salone-hero.jpg`). Bátran cserélhető.
   Logó nem kötelező: amíg nincs, a helyszín neve jelenik meg szép szedéssel.
4. **Csomagok** — a hat csomag árai a 2026-os ajánlati lapról származnak;
   érdemes átfutni, hogy még aktuálisak-e.
5. **Események** — tűzd ki az első estéket, és add meg a jegyvásárlási
   linket (lásd „Jegyértékesítés" lent).
6. **Galéria** — a fotók az adminban maradtak, de a főoldali repülésben
   már nem jelennek meg (csak a JSON API adja őket).
7. **Beállítások → A weboldal szövegei** — a hang már a szalon anyagaiból
   származik, de itt bármelyik mondat átírható.

---

## Fájlszerkezet

```
/
├── index.php              publikus oldal (mindent az adatbázisból renderel)
├── css/style.css          a teljes dizájn
├── js/main.js             a 3D „átrepülős" görgetésmotor + a lapok
├── assets/                logo.png és hero-video.mp4 helye (nem kötelező)
├── uploads/               galéria, nyitókép, logó (+ thumbs/)
├── admin/                 a teljes kezelőfelület
├── includes/              config, adatbázis, auth, renderelés, ajánlatkérés
├── api/                   JSON végpontok
└── sql/salone.sql         ⬅ EZT KELL IMPORTÁLNI
```

---

## Hogyan épül fel a főoldal?

A látogató egy „repülésben" haladva éri el a fejezeteket:

```
nyitókép → a szalon → a tér → a kínálat
        → az élő vászon → az évad → ajánlatkérés → kapcsolat
```

A fejezetek helyét **a szerver számolja ki** (`build_journey()` a
`includes/site-render.php`-ben), és a `window.SITE_JOURNEY` objektumban
adja át a böngészőnek. Ezért **egy új fejezet felvétele hosszabbítja az
utazást** — nem szorulnak össze a meglévők, és nem kell JavaScriptet
szerkeszteni.

A repülésben **nincsenek teljes képernyős fotóoldalak**: a korábbi
átrepülős galéria fejezetek kikerültek. A galéria fotói megmaradtak az
adminban és a `/api/gallery.php` végponton, de a főoldalon nem látszanak.

### A repülés „megáll" minden fejezetnél

A görgetés **nem folyamatos**: a kamera egy fejezeten *megpihen*, és
onnan **egy lökés = egy fejezet**. Egy egérgörgetés, egy ujjhúzás vagy egy
nyílbillentyű pontosan a következő megállóig visz, tovább nem — a
következő fejezethez új mozdulat kell. Egy hosszú „pörgetés" is csak
egyetlen lökésnek számít; a rendszer akkor élesedik újra, amikor a
mozdulat egy pillanatra abbamarad.

A nyíl-/menü-/rail-ugrások és a mélylinkek (`#viaggio-...`) továbbra is
egyből a megadott fejezetre visznek. Ha bármi máshogy mozdul el a
görgetés (görgetősáv húzása, böngészős keresés), a rendszer a legközelebbi
fejezetre igazítja — a repülés soha nem áll meg két megálló között.

A finomhangolás a `js/main.js` tetején, a „detent" blokkban van:
`SNAP_MS` (utazási idő), `WHEEL_TRIGGER` (mekkora görgetés számít
lökésnek), `GESTURE_GAP` (mennyi szünet kezd új mozdulatot),
`SWIPE_TRIGGER` (ujjhúzás küszöbe).

A hosszú tartalom nem a repülésben, hanem **teljes képernyős, görgethető
lapokon** („sheet") jelenik meg: a teljes kínálat, az évad estéi és az
ajánlatkérő űrlap. Ez azért van így, mert egy repülő fejezet nem tud
görgetni — ami nem fér ki, az levágódna.

**Csökkentett mozgás** (`prefers-reduced-motion`) vagy 3D nélküli böngésző
esetén az oldal automatikusan hagyományos, egymás alatti szakaszokra vált
(`.flat` mód). JavaScript nélkül is olvasható marad az egész tartalom.

---

## Jegyértékesítés

Ez a rendszer **nem ad el jegyet** — nincs benne pénztár. Minden esthez
megadható egy **jegyvásárlási link** (admin → Események), amely a meglévő
jegyértékesítő oldalra visz, és a láblécben is elhelyezhető egy általános
„Jegyek" link (admin → Beállítások).

> ⚠️ A `salonemedici.hu` jelenleg egy WordPress + WooCommerce oldal, amely
> **online árul jegyeket az egyes estékre**. Ha ez a rendszer váltja le,
> tisztázni kell, hogy a jegyeladás hol történjen ezután: marad-e a
> WooCommerce külön aldomainen (és ide csak linkelünk), vagy más
> jegyértékesítő szolgáltatás lép a helyére. **Ez döntést kíván az
> élesítés előtt.**

---

## Betűtípusok

**Cormorant Garamond** (a szalon hangja) + **Jost** (nagybetűs jelzések,
űrlapok) — mindkettő **SIL Open Font License**, a Google Fontsról töltve.
Nincs betűfájl a projektben, és nincs licencgond.

Ha az ügyfélnek saját arculati betűje van, cseréld le:
1. a `<link rel="stylesheet" href="https://fonts.googleapis.com/...">` sort
   az `index.php`-ben és az `includes/header.php`-ben,
2. a `css/style.css` tetején a `--font-display` / `--font-caps` /
   `--font-body` változókat.

*(A korábbi `pizzeria-website` sablon a CIAO arculati betűit tartalmazta
licenc-tisztázás nélkül; ez a projekt szándékosan nem viszi tovább őket.)*

---

## Biztonság

- Minden lekérdezés PDO **prepared statement**.
- Jelszavak `password_hash()` (bcrypt); alapértelmezett jelszó nincs.
- Az adminban **CSRF token** minden adatmódosító űrlapon; 30 perc
  tétlenség után kilép.
- **A publikus oldal nem tesz le sütit** (nincs munkamenet, nincs
  sütibanner-kényszer). Az ajánlatkérő űrlapot ezért nem session-CSRF,
  hanem **HMAC-cal aláírt időbélyeg** védi (`FORM_SECRET`): az azonnal
  beküldött vagy régi űrlap elutasításra kerül. Emellett rejtett
  „honeypot" mező és **IP-alapú óránkénti korlát** (`ENQUIRY_RATE_PER_HOUR`)
  szűri a robotokat.
- Az ajánlatkérés **először az adatbázisba kerül**, és csak utána indul az
  e-mail — egy nem működő levelezés sosem veszíthet el megkeresést.
- Minden kimenet escape-elve; feltöltésnél MIME-ellenőrzés + 5 MB limit.
- Az `uploads/` alatt PHP-futtatás tiltva; `includes/` és `sql/` webről zárt.
- A `/api/settings.php` **engedélyezőlistából** dolgozik, így az értesítési
  e-mail cím és a többi belső beállítás nem szivárog ki.

---

## Fejlesztői jegyzetek

- **Ezen a gépen nincs PHP**, így a kód lokálisan nem futtatható. Az
  ellenőrzés szerkezeti (zárójel- és `endforeach`-egyensúly, séma- és
  beállításkulcs-egyeztetés) volt, a dizájnt pedig egy statikusan
  legenerált másolaton néztük meg böngészőben. **Az éles teszt a szerveren
  még hátravan** — lásd a projekt CLAUDE.md-jét.
- `session_boot()` **minden kimenet ELŐTT** kell (különben a CSRF cookie
  nem áll be).
- `.htaccess`-be **ne kerüljön `php_flag`** — DreamHost PHP-FPM-en 500-as
  hibát ad.
- MySQL 8-ban a `lead` **fenntartott szó**, ezért az `events` táblában az
  egysoros leírás oszlopa `lead_text`.
- Telepítési sorrend frissítéskor: **előbb adatbázis, utána fájlok**.
