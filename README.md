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
| **Blog (multiblog)** | Podpora více blogů – každý s vlastním názvem, slugem, popisem, kategoriemi a tagy; komentáře a schvalování společné; dynamický URL routing (`/blog-slug/article-slug`); články s kategoriemi, tagy, komentáři (s moderací), náhledem před zveřejněním a čistými slug URL; plánované publikování, odhad doby čtení, počet přečtení a veřejná author vrstva; filtr článků podle kategorie a blogu; per-blog RSS feed |
| **Novinky** | Krátké zprávy s titulkem, autorem, detailovou stránkou a čistou slug URL |
| **Chat** | Jednoduchá veřejná diskuse s moderátorským inboxem, detailovým pohledem na zprávu a interními stavy `nová`, `přečtená`, `vyřízená` |
| **Kontakt** | Kontaktní formulář s CAPTCHA, honeypot ochranou, rate limitingem a moderátorským inboxem pro příchozí zprávy |
| **Galerie** | Vnořená fotoalba s automatickým generováním náhledů, čistými slug URL pro alba i fotografie a výběrem obálky alba |
| **Události** | Kalendář akcí s datem začátku, konce, místem konání, detailovou stránkou a čistou slug URL |
| **Podcasty** | Správa více podcastů a jejich epizod; pořady i epizody mají čisté slug URL, veřejný detail a každý pořad má vlastní RSS feed (`/podcast/feed.php?slug=slug-poradu`) kompatibilní s podcastovými aplikacemi |
| **Zajímavá místa** | Turistický adresář míst s typem, perexem, obrázkem, lokalitou, adresou, kontaktem, otevírací dobou a detailovou stránkou na čisté slug URL; veřejný výpis se řadí abecedně podle názvu |
| **Newsletter** | Odběr novinek e-mailem s potvrzovacím odkazem a možností odhlášení; administrace nabízí přehled odběratelů, detail čekajících potvrzení, historii rozesílek i hromadné akce nad odběrateli |
| **Ke stažení** | Katalog dokumentů, software a dalších materiálů s typem položky, verzí, platformou, licencí, detailovou stránkou a bezpečným file endpointem; položky se v kategoriích řadí automaticky od nejnovějších |
| **Jídelní lístek** | Správa jídelních a nápojových karet s platností od–do, archivem, veřejným detailem a čistou slug URL |
| **Ankety** | Hlasování s výsledky, archivem, čistými slug URL, volitelným časovým omezením a ochranou proti opakovanému hlasování |
| **Znalostní báze** | Hierarchické kategorie s neomezenou hloubkou, breadcrumbs, stromová navigace, filtrování podle kategorií včetně podkategorií, krátkým perexem a detailovou stránkou na čisté slug URL |
| **Formuláře** | Dynamický form builder – admin definuje formuláře s libovolnými poli (text, email, tel, textarea, select, checkbox, number, date); veřejná stránka s CSRF, honeypot a CAPTCHA; prohlížení odpovědí v admin + CSV export |
| **Úřední deska / Vývěska / Oznámení** | Dokumenty i krátká oznámení s datem vyvěšení/sejmutí, typem položky, perexem, volitelným obrázkem, kontaktem, detailovou stránkou a přílohami; čisté slug URL, automatický archiv po datu sejmutí a přirozené řazení podle připnutí a data vyvěšení |
| **Rezervace** | Univerzální rezervační systém – 3 režimy slotů, veřejný kalendář, e-mailové notifikace, zrušení přes tokenový odkaz, podpora hostů i registrovaných uživatelů, schvalování správcem a sjednocená administrace zdrojů, kategorií, míst i rezervací s capability guardy |
| **Statické stránky** | Vlastní stránky se slug URL; volitelné zobrazení v navigaci, samostatná stránka pro pořadí v hlavní navigaci a sjednocená redakční administrace s filtrováním, bezpečným slug workflow a veřejným preview |

Každý modul lze zapnout nebo vypnout v administraci v sekci **Nastavení → Správa modulů**.

---

## Widget systém a úvodní stránka

Homepage i sidebar a footer jsou plně konfigurovatelné přes **widget systém** (*Nastavení → Widgety*). Admin přetahuje widgety do 3 zón (homepage, sidebar, footer), pojmenuje je a nastaví parametry. Widgety respektují stav modulů – vypnutý modul = nedostupný widget.

Dostupné typy widgetů: úvodní text, nejnovější články (s volbou blogu), novinky, doporučený obsah, úřední deska, nadcházející události, anketa, newsletter, náhled galerie, vlastní HTML, vyhledávání, kontaktní údaje

---

## Nastavení

Nastavení je rozděleno do čtyř sekcí:

### Obecná nastavení

- **Název a popis webu** – zobrazí se v záhlaví a v SEO meta tazích
- **Profil webu** – uloží zaměření webu a volitelně jedním krokem použije doporučené moduly, pořadí navigace, homepage bloky a vhodnou first-party šablonu; `Vlastní profil` slouží jako neutrální režim bez vnuceného presetu
- **Veřejný název úřední desky** – umožní modul na webu zobrazovat jako `Úřední deska`, `Vývěska`, `Oznámení` nebo jiný krátký veřejný název bez změny interní struktury modulu
- **Kontaktní e-mail** – příjemce zpráv z kontaktního formuláře
- **Text úvodní stránky** – volitelný HTML úvod zobrazený na hlavní stránce
- **Logo a favicon** – nahrání vlastního loga (JPEG, PNG, GIF, WebP, SVG) a faviconu (ICO, PNG, SVG)
- **Výchozí OG obrázek** – obrázek pro náhledy při sdílení na sociálních sítích
- **Editor obsahu** – volba mezi doporučeným HTML editorem (textarea) a volitelným WYSIWYG editorem (Quill); pro práci s asistivními technologiemi doporučujeme HTML variantu. V HTML polích, která se veřejně renderují přes CMS, je navíc dostupný přístupný dialog pro vložení odkazu, hotového HTML bloku, fotogalerie nebo přehrávače z existujícího veřejného obsahu webu
- **Počty na hlavní stránce** – počet novinek, článků blogu a dokumentů úřední desky zobrazených na HP (0 = widget skrytý)
- **Stránkování** – počet novinek, článků a událostí na stránku
- **Komentáře blogu** – globální zapnutí komentářů, režim moderace (`vždy schvalovat`, `schválit známého autora`, `zveřejnit ihned`), automatické uzavření komentářů po zadaném počtu dnů, antispam pravidla (blokované e-maily a fráze), e-mailové upozornění na nové komentáře čekající na schválení a volitelné upozornění autorovi po schválení komentáře; používá se stejná mailová vrstva jako u registrace, resetu hesla a rezervací
- **E-mailové notifikace** – upozornění na odeslání formuláře, obsah čekající na schválení a nové zprávy v chatu; každý typ lze zapnout/vypnout; chat je výchozí vypnutý (může generovat velký objem e-mailů)
- **Sociální sítě** – odkazy na Facebook, YouTube, Instagram, X (Twitter)
- **Cookie lišta** – zapnutí GDPR lišty s vlastním textem
- **Režim údržby** – dočasně zobrazí návštěvníkům hlášku o údržbě; přihlášení admini vidí web normálně

### Správa modulů

Zapínání a vypínání jednotlivých modulů jedním přepínačem.

### Navigace webu

Jednotná správa pořadí modulů, statických stránek a blogů v hlavní navigaci (*Nastavení → Navigace webu*). Drag & drop nebo tlačítka Nahoru/Dolů. Libovolné kombinování – stránka mezi moduly, blog před novinkami.

### Vzhled a šablony

- Výběr aktivní veřejné šablony z adresáře `themes/`
- Profil webu při instalaci i v administraci: `Osobní web`, `Blog / magazín`, `Obec / spolek`, `Služby / firma`, `Vlastní profil`
- Safe customizace aktivní šablony: paleta, hlavní akcenty, typografie a šířka obsahu
- Varianta hlavičky přímo v administraci bez editace šablonových souborů
- Živý náhled šablony a draft vzhledu bez aktivace na produkčním webu
- Reset vzhledu aktivní šablony na výchozí hodnoty
- Import/export portable ZIP balíčku šablony

Součástí CMS jsou nyní tyto oficiální šablony:

- `default` – vyvážený univerzální základ
- `civic` – důvěryhodný styl pro obce, spolky a informační weby
- `editorial` – magazínovější vzhled s výraznější typografií
- `modern-service` – současný styl pro služby, firmy a projekty

First-party šablony mají i vlastní vizuální rytmus a responzivní dolaďování, takže se neliší jen barvami, ale i charakterem hlavičky, karet a homepage sekcí.

---

## Správa uživatelů

### Administrátoři a redakční role

Hlavní administrátor (účet vytvořený při instalaci) může v sekci **Nastavení → Správa uživatelů** přidávat další účty a přiřazovat jim roli. Administrace používá capability model, takže menu, dashboard i schvalovací akce se zobrazují jen tam, kde má účet opravdu oprávnění.

K dispozici jsou tyto role:

- **Veřejný uživatel** – nemá přístup do administrace; slouží pro rezervace, komentáře a veřejný profil
- **Autor** – spravuje vlastní články blogu a vlastní novinky
- **Editor** – spravuje a schvaluje blog, novinky i sdílený obsah
- **Moderátor** – řeší komentáře, chat a kontaktní zprávy
- **Správce rezervací** – spravuje zdroje, kalendáře a rezervace
- **Admin** – běžná plná správa webu včetně nastavení, newsletteru, importu/exportu a uživatelů

Starší role **Spolupracovník / Správce obsahu** zůstává kvůli kompatibilitě se staršími instalacemi zachovaná a chová se jako širší redakční role.

Každý uživatel si může upravit svůj profil (jméno, příjmení, přezdívku, e-mail a heslo). Účty s přístupem do administrace mohou navíc zapnout i **veřejný autorský profil** s vlastním slugem, bio, webem a avatarem. Pokud je profil zapnutý, blog a homepage na něj mohou veřejně odkazovat přes URL typu `/author/jmeno-autora`. Veřejné autory lze zároveň procházet i přes společný přehled `/authors/`; odkaz na tento přehled v modulu blogu je volitelný a ve výchozím stavu vypnutý.

Role se propsávají i do pracovního rozhraní administrace. Účet tak nově vidí jen moduly, dashboard a schvalovací akce, které opravdu potřebuje. Součástí administrace je i společná fronta **Ke schválení**, která sjednocuje čekající obsah, komentáře a rezervace na jedno místo.

Administrace používá i sjednocené filtry, hromadné akce, civilnější prázdné stavy a role-based dashboard. Formuláře mají konkrétní názvy akcí, návraty na přehledy a helper texty pro slugy, zveřejnění, plánování i práci se soubory nebo médii, aby se v nich lépe orientoval i ne-technický správce.

### Veřejní uživatelé

Návštěvníci se mohou zaregistrovat přes veřejný formulář (`/register.php`). Registrace vyžaduje potvrzení e-mailem. Po přihlášení mají přístup k:

- **Můj profil** – úprava osobních údajů a změna hesla
- **Moje rezervace** – přehled vlastních rezervací s možností zrušení
- **Obnovení hesla** – tokenový reset přes e-mail

Veřejní uživatelé nemají přístup do administrace. Správce vidí všechny uživatele (včetně veřejných) v přehledu se skutečnou rolí a stavem potvrzení.

---

## Další funkce

- **Vyhledávání** – fulltextové vyhledávání napříč články, novinkami, stránkami, událostmi, podcasty i jejich epizodami, FAQ, galeriemi, místy, anketami a dokumenty úřední desky, vždy s odkazem na veřejný detail obsahu
- **RSS feed** – automaticky generovaný feed nejnovějších článků a novinek (`/feed.php`) s čistými odkazy na detail obsahu
- **XML sitemap** – sitemap pro vyhledávače (`/sitemap.php`) včetně slug URL článků, novinek, galerií, událostí, podcastů, epizod, anket, dokumentů úřední desky i veřejných profilů autorů
- **SEO** – meta tagy (title, description), Open Graph a možnost nastavit vlastní meta pro jednotlivé články
- **Vkládání interního obsahu do HTML polí** – v HTML textarea polích pro obsah, která se veřejně vykreslují přes CMS, je dostupný přístupný dialog `Vložit odkaz nebo HTML z webu` pro rychlé vložení odkazu nebo hotového HTML bloku z existujících článků, stránek a dalších veřejných modulů. Podle typu výsledku umí nabídnout i `Vložit fotogalerii`, `Vložit audio přehrávač` nebo `Vložit video přehrávač`
- **Snippety v HTML/Markdown obsahu** – renderer podporuje i zápis `[audio]https://example.test/audio.mp3[/audio]`, `[video]https://example.test/video.mp4[/video]` a `[gallery]slug-alba[/gallery]` nebo `[gallery slug="slug-alba"][/gallery]`; fungují ve všech HTML/Markdown polích, která CMS na veřejném webu vykresluje přes `renderContent()`. Audio a video podporují i atributy `src` a volitelný `mime`, takže lze bezpečně použít i interní file endpointy, například `[audio src="/downloads/file.php?id=123" mime="audio/mpeg"][/audio]`
- **E-maily** – odesílání přes přímé SMTP (`fsockopen`); automatická detekce serveru z `php.ini`; spolehlivé na PHP 8.4 NTS/FastCGI i na Windows
- **Audit log** – záznam akcí administrátorů s filtry podle akce, uživatele a data; prohlížeč v administraci
- **Centrální knihovna médií** – upload více souborů, grid zobrazení s thumbnaily, filtr podle typu, správa alt textů, kopírování URL; automatické WebP + thumbnail generování
- **WebP konverze** – automatické generování WebP verze při uploadu obrázků ve všech modulech; `<picture>` element s lazy loading
- **Responsive obrázky** – generování variant 400w, 800w, 1200w při uploadu článkových obrázků
- **Import / Export** – export a import dat CMS (články, novinky, stránky, události, galerie, místa, soubory, podcasty, ankety, FAQ, vývěska, komentáře, odběratelé, newslettery)
- **WordPress importér** – import z WordPress XML exportu (WXR) s náhledem, filtrem kategorií, výběrem cílového blogu, perex/content splittem na `<!--more-->` a automatickým odstraněním WP bloků
- **eStránky importér** – import článků, kategorií, fotoalb a fotografií z XML zálohy eStránek.cz s base64 dekódováním, hierarchií alb, výběrem cílového blogu a cílového alba pro stažené fotografie
- **301/302 přesměrování** – správa přesměrování starých URL na nové s počítadlem přístupů; užitečné po importu nebo změně slug adres
- **Google Analytics 4** – GA4 Measurement ID v admin; GDPR: gtag.js se načítá až po udělení cookie souhlasu
- **Vlastní kód do head/footer** – textová pole v nastavení pro libovolný HTML/JS kód do `<head>` a před `</body>`
- **Revize obsahu** – snapshot textových polí před každou úpravou; vizuální diff se zvýrazněním přidaných a odebraných částí
- **Interní poznámky** – admin poznámka k článkům, novinkám, stránkám a událostem viditelná jen v administraci
- **Plánované publikování a zrušení** – `publish_at` pro odložené zveřejnění, `unpublish_at` pro automatické skrytí po vypršení
- **Převod článek ↔ stránka** – převod článku na statickou stránku a naopak jedním kliknutím
- **Export fotoalba do ZIP** – rekurzivní export alba včetně podalb do ZIP s hierarchickou strukturou složek
- **Koš (soft delete)** – smazání článků, novinek, stránek, událostí a FAQ je přesun do koše s možností obnovení
- **Záloha databáze** – manuální export z admin + automatické denní zálohy přes cron s rotací 7 dní
- **Cron endpoint** – plánované publikování, unpublish, čištění rate-limit/temp/logů, automatické zálohy
- **Upozornění na aktualizace** – admin dashboard kontroluje novou verzi přes GitHub API
- **Kontrola integrity souborů** – SHA-256 snapshot všech PHP souborů; detekce změn; varování na dashboardu
- **FULLTEXT vyhledávání** – 10 FULLTEXT indexů na klíčových tabulkách; relevantní řazení výsledků s LIKE fallbackem
- **Drag & drop řazení** – přetahování položek s AJAX uložením; WCAG fallback: Ctrl+šipka a tlačítka Nahoru/Dolů

---

## Bezpečnost

- **Dvoufázové ověření (2FA)** – volitelné TOTP přihlášení (FreeOTP, Authy, Google Authenticator); aktivace v profilu přes QR kód
- CSRF ochrana na všech formulářích
- Rate limiting (přihlášení, kontakt, odběr, chat, hlasování, vyhledávání)
- Honeypot pole proti spambotům
- Matematická CAPTCHA
- Prepared statements proti SQL injection
- HTML escapování proti XSS
- Bezpečné hashování hesel (bcrypt)
- Kontrola integrity souborů (SHA-256 snapshot s detekcí změn)
- CSP nonce na všech inline skriptech
- HSTS hlavička při HTTPS
- Blokování `.env` a `.git/` v `.htaccess`
- GDPR: GA4 podmíněno cookie souhlasem

---

## Přístupnost

CMS je navržen s ohledem na **WCAG 2.2 Level AA**:

- Sémantické HTML (`<header>`, `<main>`, `<nav>`, `<article>`, `<section>`, `<details>`)
- Formuláře seskupené pomocí `<fieldset>` / `<legend>` s `aria-required` a `role="alert"`
- `autocomplete` atributy na všech relevantních polích (email, tel, name, password)
- `focus-visible` styly na formulářích a tlačítkách
- Confirm dialogy přes `data-confirm` + globální JS handler (kompatibilní s CSP nonce)
- Drag & drop s klávesnicovým fallbackem (Ctrl+šipka)
- Admin sidebar s rozbalovacími sekcemi, viditelným focusem a jen pro zapnuté moduly
- Přístupný modal dialog pro content picker s `role="dialog"`, `aria-modal`, návratem fokusu
- Skip link pro přeskočení na obsah
- ARIA atributy (`aria-current`, `aria-hidden`, `aria-live`)
- Ovladatelnost pouze klávesnicí
- Nativní accordion bez závislosti na JavaScriptu
- Alt texty na všech obsahových obrázcích
