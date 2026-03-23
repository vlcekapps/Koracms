# Kora CMS

Kora CMS je jednoduchý redakční systém bez frameworku. Vhodný pro osobní weby, spolky nebo menší projekty.

---

## Lokální vývoj a testování

Pro testování na vlastním počítači důrazně doporučujeme **[Laragon](https://laragon.org/)** s **PHP 8.4**.

Laragon automaticky zajistí Apache, MySQL a PHP bez nutnosti ruční konfigurace. Stačí zkopírovat soubory do složky `laragon/www/`, vytvořit databázi přes phpMyAdmin a spustit `install.php`.

---

## Minimální požadavky

| Komponenta | Minimální verze | Doporučená verze |
|---|---|---|
| PHP | ≥ 8.0 | **8.4** |
| MySQL | ≥ 5.7 | 8.0+ |
| MariaDB | ≥ 10.3 *(alternativa k MySQL)* | 11+ |
| Webový server | Apache ≥ 2.4 nebo Nginx | — |

**Vyžadovaná PHP rozšíření:** `pdo`, `pdo_mysql`, `fileinfo`, `gd`

> Na sdíleném hostingu tato rozšíření bývají povolena ve výchozím nastavení.

---

## Instalace

### 1. Zkopírujte soubory

Nahrajte obsah repozitáře do kořenového adresáře webu (nebo do podsložky).

### 2. Vytvořte databázi

Přihlaste se do MySQL a vytvořte prázdnou databázi:

```sql
CREATE DATABASE nazev_databaze CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Vytvořte `config.php`

Zkopírujte přiložený vzorový soubor a přejmenujte ho:

```
cp config.sample.php config.php
```

Otevřete `config.php` a vyplňte přihlašovací údaje k databázi a základní URL:

```php
$server   = 'localhost';
$user     = 'root';           // databázový uživatel
$pass     = '';               // heslo (prázdné na lokálním serveru)
$database = 'nazev_databaze';

// Pokud je web v kořeni domény, ponechte prázdné.
// Pokud je ve složce, zadejte např. '/koracms'
define('BASE_URL', '');
```

### 4. Spusťte instalaci

Otevřete v prohlížeči:

```
http://vas-web.cz/install.php
```

Vyplňte název webu, e-mail a heslo hlavního administrátora a klikněte na **Nainstalovat**.
Při instalaci si můžete rovnou vybrat i **profil webu** (`Osobní web`, `Blog / magazín`, `Obec / spolek`, `Služby / firma`, `Vlastní profil`). První čtyři varianty přednastaví vhodné moduly, domovskou stránku a doporučenou šablonu, zatímco `Vlastní profil` ponechá CMS v neutrálním režimu pro ruční nastavení.

### 5. Smažte instalační soubor

Po úspěšné instalaci **odstraňte** soubor `install.php` ze serveru – jinak by jej mohl kdokoli spustit znovu.

### 6. Přihlaste se

```
http://vas-web.cz/admin/login.php
```

---

## Aktualizace

Při přechodu na novější verzi CMS postupujte takto:

### 1. Nahrajte nové soubory

Přepište stávající soubory novými. Soubor `config.php` **nepřepisujte** – obsahuje vaše nastavení připojení k databázi.

### 2. Spusťte migrace

Otevřete v prohlížeči:

```
http://vas-web.cz/migrate.php
```

Před spuštěním se přihlaste do administrace jako **superadmin**. Skript po potvrzení přidá případné nové tabulky, sloupce a výchozí nastavení. Existující data ani nastavení nepřepíše.

### 3. Smažte migrační soubor

Po dokončení **odstraňte** soubor `migrate.php` ze serveru.

---

## Dostupné moduly

| Modul | Popis |
|---|---|
| **Blog** | Články s kategoriemi, tagy, komentáři (s moderací) a náhledem před zveřejněním; plánované publikování |
| **Novinky** | Krátké zprávy |
| **Chat** | Jednoduchá veřejná diskuse |
| **Kontakt** | Kontaktní formulář s CAPTCHA, honeypot ochranou a rate limitingem |
| **Galerie** | Vnořená fotoalba s automatickým generováním náhledů; výběr obálky alba |
| **Události** | Kalendář akcí s datem začátku, konce a místem konání |
| **Podcasty** | Správa více podcastů a jejich epizod; každý podcast má vlastní RSS feed (`/podcast/feed.php?show=ID`) kompatibilní s podcastovými aplikacemi |
| **Zajímavá místa** | Adresář míst s popisem, kategorií a odkazem |
| **Newsletter** | Odběr novinek e-mailem s potvrzovacím odkazem a možností odhlášení |
| **Ke stažení** | Knihovna souborů ke stažení s kategoriemi |
| **Jídelní lístek** | Správa jídelních a nápojových karet s platností od–do a archivem |
| **Ankety** | Hlasování s výsledky, archivem a volitelným časovým omezením; ochrana proti opakovanému hlasování |
| **FAQ** | Často kladené otázky s kategoriemi; accordion (`<details>` / `<summary>`) bez JavaScriptu |
| **Úřední deska** | Dokumenty s datem vyvěšení/sejmutí, kategoriemi a přílohami; automatický archiv po datu sejmutí |
| **Rezervace** | Univerzální rezervační systém – 3 režimy slotů, veřejný kalendář, e-mailové notifikace, zrušení přes tokenový odkaz, podpora hostů i registrovaných uživatelů, schvalování správcem |
| **Statické stránky** | Vlastní stránky se slug URL; volitelné zobrazení v navigaci |

Každý modul lze zapnout nebo vypnout v administraci v sekci **Nastavení → Moduly**.

---

## Úvodní stránka

Na hlavní stránce se zobrazují widgety zapnutých modulů:

- **Úvodní text** – volitelný HTML text nastavitelný v administraci (*Nastavení → Základní nastavení → Text úvodní stránky*)
- **Nejnovější novinky** – počet položek lze nastavit; hodnota 0 widget skryje
- **Nejnovější články blogu** – počet položek lze nastavit; hodnota 0 widget skryje
- **Úřední deska** – nejnovější aktuální dokumenty; počet položek lze nastavit; hodnota 0 widget skryje
- **Aktuální anketa** – pokud je modul Ankety zapnutý a existuje aktivní anketa

---

## Nastavení

Nastavení je rozděleno do čtyř sekcí:

### Základní nastavení

- **Název a popis webu** – zobrazí se v záhlaví a v SEO meta tazích
- **Profil webu** – uloží zaměření webu a volitelně jedním krokem použije doporučené moduly, pořadí navigace, homepage bloky a vhodnou first-party šablonu; `Vlastní profil` slouží jako neutrální režim bez vnuceného presetu
- **Kontaktní e-mail** – příjemce zpráv z kontaktního formuláře
- **Text úvodní stránky** – volitelný HTML úvod zobrazený na hlavní stránce
- **Logo a favicon** – nahrání vlastního loga (JPEG, PNG, GIF, WebP, SVG) a faviconu (ICO, PNG, SVG)
- **Výchozí OG obrázek** – obrázek pro náhledy při sdílení na sociálních sítích
- **Editor obsahu** – volba mezi prostým HTML editorem a WYSIWYG editorem (Quill)
- **Počty na hlavní stránce** – počet novinek, článků blogu a dokumentů úřední desky zobrazených na HP (0 = widget skrytý)
- **Stránkování** – počet novinek, článků a událostí na stránku
- **Sociální sítě** – odkazy na Facebook, YouTube, Instagram, X (Twitter)
- **Cookie lišta** – zapnutí GDPR lišty s vlastním textem
- **Režim údržby** – dočasně zobrazí návštěvníkům hlášku o údržbě; přihlášení admini vidí web normálně

### Moduly

Zapínání a vypínání jednotlivých modulů jedním přepínačem.

### Nastavení zobrazení

Vlastní pořadí modulů v navigaci pro návštěvníky (přesun nahoru / dolů).

### Vzhled a šablony

- Výběr aktivní veřejné šablony z adresáře `themes/`
- Profil webu při instalaci i v administraci: `Osobní web`, `Blog / magazín`, `Obec / spolek`, `Služby / firma`, `Vlastní profil`
- Zobrazení názvu, verze, autora a popisu dostupných šablon
- Obrázkové preview karty šablon přímo v administraci pro rychlejší orientaci při výběru
- Bezpečný fallback na `default`, pokud uložená šablona na serveru chybí nebo je neplatná
- Safe customizace aktivní šablony: paleta, hlavní akcenty, typografie a šířka obsahu
- Varianta hlavičky a homepage přímo v administraci bez editace šablonových souborů
- Homepage composer pro default theme: featured modul, pořadí hlavních sekcí a viditelnost bloků bez zásahu do kódu
- Homepage composer vždy respektuje globální stav modulů, takže nenabízí ani nevyrenderuje blok pro vypnutý modul
- Živý náhled šablony a draft vzhledu bez aktivace na produkčním webu
- Reset vzhledu aktivní šablony na výchozí hodnoty bez zásahu do souborů
- Bezpečný import portable ZIP balíčku: `theme.json` + statické assety v `assets/`, bez PHP override souborů
- Export portable ZIP balíčku z administrace včetně uložených výchozích theme settings
- Portable balíčky záměrně nepřenášejí layouty, partialy a view override; veřejný web dál používá fallback kontrakt na `default`
- UX audit má vlastní framework v `docs/ux-audit-framework.md`; automatické guardrails běží přes `php build/runtime_audit.php`

Součástí CMS jsou nyní tyto oficiální šablony:

- `default` – vyvážený univerzální základ
- `civic` – důvěryhodný styl pro obce, spolky a informační weby
- `editorial` – magazínovější vzhled s výraznější typografií
- `modern-service` – současný styl pro služby, firmy a projekty

First-party šablony mají i vlastní vizuální rytmus a responzivní dolaďování, takže se neliší jen barvami, ale i charakterem hlavičky, karet a homepage sekcí.

---

## Správa uživatelů

### Administrátoři a spolupracovníci

Hlavní administrátor (účet vytvořený při instalaci) může přidávat **spolupracovníky** v sekci **Nastavení → Správa uživatelů**. Obsah přidaný spolupracovníkem musí hlavní administrátor před zveřejněním schválit.

Každý uživatel si může upravit svůj profil (jméno, příjmení, přezdívku, e-mail a heslo).

### Veřejní uživatelé

Návštěvníci se mohou zaregistrovat přes veřejný formulář (`/register.php`). Registrace vyžaduje potvrzení e-mailem. Po přihlášení mají přístup k:

- **Můj profil** – úprava osobních údajů a změna hesla
- **Moje rezervace** – přehled vlastních rezervací s možností zrušení
- **Obnovení hesla** – tokenový reset přes e-mail

Veřejní uživatelé nemají přístup do administrace. Správce vidí všechny uživatele (včetně veřejných) v přehledu se skutečnou rolí a stavem potvrzení.

---

## Další funkce

- **Vyhledávání** – fulltextové vyhledávání napříč články, novinkami, stránkami, událostmi, podcasty, FAQ, místy a úřední deskou
- **RSS feed** – automaticky generovaný feed nejnovějších článků (`/feed.php`)
- **XML sitemap** – sitemap pro vyhledávače (`/sitemap.php`)
- **SEO** – meta tagy (title, description), Open Graph a možnost nastavit vlastní meta pro jednotlivé články
- **E-maily** – odesílání přes přímé SMTP (`fsockopen`); automatická detekce serveru z `php.ini`; spolehlivé na PHP 8.4 NTS/FastCGI i na Windows
- **Audit log** – záznam akcí administrátorů (přihlášení, úpravy obsahu, změny nastavení)
- **Import / Export** – export a import dat CMS (články, novinky, stránky, události, galerie, místa, soubory ke stažení, jídelní lístky, podcasty, ankety, FAQ, úřední deska, komentáře, odběratelé, newslettery)

---

## Bezpečnost

- CSRF ochrana na všech formulářích
- Rate limiting (přihlášení, kontakt, odběr, chat, hlasování)
- Honeypot pole proti spambotům
- Matematická CAPTCHA
- Prepared statements proti SQL injection
- HTML escapování proti XSS
- Bezpečné hashování hesel (bcrypt)

---

## Přístupnost

CMS je navržen s ohledem na **WCAG 2.2**:

- Sémantické HTML (`<header>`, `<main>`, `<nav>`, `<article>`, `<section>`, `<details>`)
- Formuláře seskupené pomocí `<fieldset>` / `<legend>` s `aria-required` a `role="alert"`
- Admin sidebar – podmenu modulů seskupena pomocí `role="group"` s `aria-label` (Blog, Ke stažení, FAQ, Úřední deska); v sidebaru se zobrazují jen zapnuté moduly
- Skip link pro přeskočení na obsah
- ARIA atributy (`aria-current`, `aria-hidden`, `aria-live`)
- Ovladatelnost pouze klávesnicí
- Nativní accordion (FAQ, archiv úřední desky) bez závislosti na JavaScriptu
