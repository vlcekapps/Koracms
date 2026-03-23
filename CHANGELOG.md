# Changelog

Všechny důležité změny projektu Kora CMS jsou dokumentovány v tomto souboru.
Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/)
a projekt používá [Semantic Versioning](https://semver.org/lang/cs/).

## [Unreleased]

### Přidáno
- Repo-local pravidla v `AGENTS.md` pro bezpečnost, diakritiku a WCAG
- `build/runtime_audit.php` – HTTP smoke audit pro klíčové veřejné i admin stránky
- Sdílené veřejné a11y styly pro skip link, screen-reader text a focus ring
- Public theme kernel v `lib/theme.php`, první oficiální šablona v `themes/default/` a jednotný layout pro veřejný web
- Administrace `Vzhled a šablony` pro výběr aktivní veřejné šablony s metadaty a bezpečným fallbackem na `default`
- Manifest-driven theme settings pro default theme: paleta, akcenty, typografie a šířka obsahu bez zásahu do PHP
- Theme-aware layout varianty pro default theme: hlavička (`balanced` / `centered` / `split`) a homepage (`balanced` / `editorial` / `compact`)
- Homepage composer pro default theme: featured modul, pořadí sekcí a bezpečné zapínání/vypínání homepage bloků podle theme settings
- Tři nové first-party šablony `civic`, `editorial` a `modern-service`, každá s vlastním manifestem, stylem a výchozím profilem vzhledu
- Session-based živý náhled šablon a jejich draft nastavení bez změny `active_theme` v produkční konfiguraci
- Bezpečný portable ZIP formát pro šablony: import/export statických theme balíčků bez PHP override souborů

### Opraveno
- Veřejné přihlášení nyní validuje interní redirecty a odmítá externí / protocol-relative URL
- Opakovaná registrace nepotvrzeného účtu již nepadá na duplicitním vložení uživatele
- Veřejné formuláře používají `aria-describedby` jen tehdy, když cílový error blok skutečně existuje
- Rezervační kalendář používá čitelnější kontrast u neaktivních stavů
- Administrace `Ke stažení` otevírá uložený soubor podle interního názvu, ne podle původního jména
- Administrace se vyhýbá kolizním lokálním proměnným `$user` v dotčených souborech
- Veřejné stránky bez lokálního CSS dostaly funkční skip link a `.sr-only` helper přes sdílený a11y styl
- Jídelní a nápojový lístek používá klávesnicově ovladatelné taby s korektním `tabindex` a `type="button"`
- Administrační navigace a pevná admin lišta mají větší klikací plochy a čitelnější kontrast stavů rezervací
- Runtime audit kontroluje také skip-link/sr-only patterny a širší sadu veřejných modulových stránek
- `install.php` ověřuje CSRF a `migrate.php` je chráněné na superadmina, potvrzovací POST a bezpečný anonymní redirect
- Release ZIP nově nevkládá `AGENTS.md` a Git source archive vynechává `AGENTS.md` i `build/runtime_audit.php`
- Instalace a migrace nově seedují `active_theme` a runtime audit ověřuje i pád na výchozí šablonu při chybné konfiguraci
- Theme settings validují bezpečné hodnoty a kontrast kritických barev; runtime audit ověřuje i propsání vlastních CSS proměnných do veřejného webu
- Homepage default theme umí bezpečně měnit rytmus a pořadí hlavních bloků bez zásahu do business logiky modulů
- Administrace vzhledu skrývá homepage volby, které patří k vypnutým modulům, a runtime audit ověřuje i tento modulový gating
- Runtime audit nově ověřuje celý katalog oficiálních šablon včetně dostupnosti jejich assetů a správného aktivování na homepage
- Runtime audit nově testuje i skutečný preview flow přes admin formulář, veřejný preview banner a bezpečné ukončení náhledu
- Runtime audit nově testuje i celý roundtrip portable theme package: ZIP upload, aktivaci, render homepage a zpětný export

## [2.1.1] – 2026-03-20

### Přidáno
- Blog: přibližná doba čtení článku na výpisu (`blog/index.php`), detailu (`blog/article.php`) i widgetu na homepage
- Blog: odkaz „Upravit" přímo z výpisu článků a homepage widgetu (viditelný jen pro admin/spolupracovníky)

## [2.1.0] – 2026-03-20

### Přidáno
- **Modul Statistiky** – sledování návštěvnosti a přehledy napříč moduly
  - Veřejné počítadlo v patičce webu (Online / Dnes / Měsíc / Celkem)
  - Dashboard widgety: souhrn návštěvnosti, graf posledních 7 dní, nejčtenější články
  - Detailní stránka `admin/statistics.php` s CSS-only grafy a filtrem období
  - Sekce: návštěvnost, nejčtenější články, rezervace, newsletter, komentáře, kontaktní zprávy
  - Každá sekce se zobrazuje pouze pokud je příslušný modul zapnutý
  - Počítadlo zobrazení článků (`view_count`)
  - GDPR: IP adresy ukládány jako SHA-256 hash, automatické mazání raw dat dle nastavené retence
  - Líná agregace denních statistik (`cms_stats_daily`) – souhrnné statistiky zůstávají trvale
  - Sledování návštěvnosti nezávislé na modulu statistik (samostatné nastavení)
  - WCAG 2.2: tabulky `.sr-only` pro čtečky pod každým grafem, `aria-label`, `role="list"`

## [2.0.0] – 2026-03-20

### Přidáno
- **Modul Rezervace** – univerzální rezervační systém s veřejnou registrací uživatelů
  - Tři režimy slotů: předdefinované sloty, pevná délka, volný rozsah
  - Správa zdrojů, kategorií a míst v administraci
  - Veřejný kalendář s barevným rozlišením dostupnosti (volné / obsazené / zavřeno / mimo rozsah)
  - Rezervace pro přihlášené uživatele i neregistrované hosty (volitelně)
  - Schvalování rezervací správcem (volitelné)
  - E-mailové notifikace při vytvoření, schválení, zamítnutí a zrušení rezervace
  - Zrušení rezervace přes tokenový odkaz v e-mailu (`cancel_booking.php`) – funguje pro hosty i registrované
  - Zrušení přihlášeným uživatelem ze sekce „Moje rezervace"
  - Lhůta pro bezplatné zrušení (nastavitelná v hodinách)
  - Kapacita, souběžné rezervace, minimální/maximální předstih
  - Stavy rezervací: čekající, potvrzená, dokončená, zrušená, neomluvená
  - Automatické dokončení proběhlých rezervací (`autoCompleteBookings()`)
  - Admin: filtrování rezervací podle stavu a zdroje, detail rezervace se změnou stavu
  - Administrátor nemůže označit rezervaci jako dokončenou před koncem jejího času
  - Admin: ruční vytvoření rezervace za hosta
  - Veřejné stránky: přehled zdrojů, detail zdroje s kalendářem, rezervační formulář, moje rezervace
- **Veřejná registrace a přihlášení** – registrace s potvrzením e-mailem, přihlášení, profil, odhlášení (`register.php`, `public_login.php`, `public_profile.php`, `public_logout.php`, `confirm_email.php`)
- **Obnovení hesla** – tokenový reset hesla s expirací 1 hodiny (`reset_password.php`)
- **Role „Veřejný uživatel"** (`public`) – oddělená od admin/spolupracovníků; nemá přístup do administrace
- **Správa uživatelů** – admin seznam nyní zobrazuje skutečnou roli (Hlavní admin / Admin / Veřejný uživatel / Spolupracovník) a stav potvrzení (Aktivní / Nepotvrzený)
- **`siteUrl()`** – helper pro absolutní URL v e-mailech (automatická detekce schématu a domény)

### Vylepšeno
- **`sendMail()`** – kompletně přepsáno na přímé SMTP odesílání přes `fsockopen()` (spolehlivé na PHP 8.4 NTS/FastCGI na Windows, kde `mail()` nefunguje)
- **Všechny e-maily** nyní používají `sendMail()` místo přímého `mail()` (registrace, odběr, reset hesla, newsletter)
- **Všechny e-mailové odkazy** nyní obsahují plnou URL s doménou díky `siteUrl()` (dříve chybělo schéma a doména)
- **Patička webu** – správné rozlišení tří stavů: admin (bez public odkazů), veřejný uživatel (Moje rezervace · Můj profil · Odhlásit se), nepřihlášený (Přihlášení · Registrace)
- **Nepotvrzený účet** – při opakované registraci se odešle nový aktivační e-mail místo chyby „účet existuje"; při přihlášení se zobrazí specifická hláška místo „nesprávné heslo"
- **migrate.php** – přidána sekce pro automatické vytvoření všech potřebných `uploads/` adresářů (`site`, `articles`, `articles/thumbs`, `gallery`, `gallery/thumbs`, `downloads`, `board`, `podcasts`, `podcasts/covers`); po migraci na novou verzi tak není nutné adresáře zakládat ručně
- **WCAG 2.2** – podmíněné `aria-describedby` (jen při chybách), `aria-atomic="true"` na status/alert zprávách, skip linky a focus styly na všech nových stránkách, `aria-readonly` na needitovatelných polích, propojení nápověd přes `aria-describedby`
- **Pravidla rezervací** – srozumitelnější formulace na veřejné stránce zdroje; zobrazení informace o nutnosti registrace na webu nebo možnosti rezervovat jako host

### Opraveno
- **`confirm_email.php`** – opravena chyba `confirmation_token cannot be null` (sloupec je NOT NULL, nyní se nastavuje prázdný řetězec)
- **`reset_password.php`** – stejná oprava pro `reset_token`
- **Patička** – opravena kontrola přihlášení (používala neexistující `$_SESSION['public_user_id']` místo `isPublicUser()`)
- **Admin přihlášení** – public uživatel nemůže vstoupit do administrace (zobrazí se hláška s odkazem na veřejné přihlášení)
- **Přesměrování** – přihlášený admin na `register.php`/`public_login.php` je přesměrován do administrace, ne na veřejný profil

## [1.0.8] – 2026-03-19

### Opraveno
- **Hlasování v anketách** – opravena chyba, kdy `rateLimit()` (void funkce) byla volána jako podmínka v if, což způsobovalo vždy chybu „Příliš mnoho pokusů"

## [1.0.7] – 2026-03-19

## [1.0.6] – 2026-03-19

### Přidáno
- **Modul Úřední deska** – dokumenty s datem vyvěšení/sejmutí, kategoriemi a přílohami; automatický archiv po datu sejmutí; veřejná stránka s rozbalovacím archivem (`<details>`)
- Widget úřední desky na hlavní stránce (počet dokumentů nastavitelný, 0 = skrytý)
- Vyhledávání v úřední desce (název + popis)
- Nastavení `home_board_count` v administraci

### Vylepšeno
- **WCAG 2.2 – formuláře** – `<fieldset>` / `<legend>` seskupení přidáno do 25 formulářů napříč celým CMS (admin i veřejné stránky)
- **WCAG 2.2 – admin sidebar** – podmenu modulů seskupena pomocí `role="group"` s `aria-label`; jednoduché moduly ve skupině „Ostatní moduly"
- **Admin sidebar** – zobrazují se pouze zapnuté moduly; vypnuté moduly jsou skryté
- **Admin sidebar** – Správa uživatelů přesunuta do sekce Nastavení
- **Export / Import** – rozšířen o ankety, možnosti anket, FAQ kategorie, FAQ, kategorie úřední desky, úřední desku, komentáře, odběratele newsletteru a odeslané newslettery
- **Markdown + HTML podpora** – obsahová pole (články, stránky, události, FAQ, podcast, jídelní lístky, úřední deska, ke stažení, místa, úvodní text) nyní zpracovávají Markdown i HTML současně pomocí knihovny Parsedown; admin formuláře zobrazují nápovědu o podpoře MD syntaxe

## [1.0.4] – 2026-03-19

### Přidáno
- **Modul Ankety (polls)** – veřejné hlasování s anonymní IP deduplicí, CSS-only sloupcový graf výsledků, archiv uzavřených anket
- Admin CRUD pro ankety s dynamickým přidáváním/odebíráním možností (2–10), ochrana možností s hlasy před smazáním
- Widget nejnovější ankety na hlavní stránce
- Integrace do `migrate.php`, `install.php`, navigace, admin sidebaru, dashboardu a nastavení modulů

### Vylepšeno
- **WCAG 2.2** – pole stránkování oddělena do vlastního `<fieldset>` s `<legend>Stránkování</legend>` (dříve chybně seskupena pod „Počty položek na hlavní stránce")
- Počty položek na HP lze nastavit na 0 – sekce se na hlavní stránce nezobrazí (nápověda pod legendou)

## [1.0.3] – 2026-03-19

### Vylepšeno
- **Přehled v administraci** – tabulka počtů záznamů nyní pokrývá všechny moduly (události, podcast, místa, stránky, ke stažení, food, galerie)
- Modul food a galerie přidány do seznamu povolených modulů a sledování čekajícího obsahu
- **WCAG 2.2** – dekorativní Unicode šipky (`←`, `→`, `‹`, `›`) a emotikona `📋` obaleny `aria-hidden="true"` v 17 souborech

### Opraveno
- Odkaz „Zobrazit stránky" v admin přehledu se již neotevírá v novém okně
- Odhlášení z admin přesměruje na hlavní stránku místo na přihlašovací formulář

## [1.0.2] – 2026-03-19

### Opraveno
- Syntaktická chyba v `migrate.php` (neplatné UTF-8 uvozovky na řádku 352)

## [1.0.1] – 2026-03-19

### Přidáno
- **Modul Ke stažení** – samostatná tabulka `cms_dl_categories` pro kategorie souborů
- Správa kategorií ke stažení (`dl_cats.php`, `dl_cat_delete.php`)
- Podmenu v admin navigaci: Soubory / Kategorie
- Export/Import podporuje nové kategorie ke stažení
- Migrace automaticky převede existující textové kategorie na záznamy v DB
- Skip-link v admin sekci (WCAG 2.4.1)
- `aria-required="true"` na povinných polích ve formulářích
- `aria-describedby` propojení chybových zpráv s poli (frontend formuláře)
- `<caption>` ve všech admin tabulkách
- `aria-current="page"` ve stránkování událostí
- CSP hlavička v `auth.php`

### Opraveno
- Open Redirect v `approve.php` (validace proti `BASE_URL`)
- Parametrizované LIMIT/OFFSET v SQL dotazech (index, blog, news)
- `@unlink()` nahrazeno za `file_exists()` + `unlink()`
- Logická chyba v `migrate.php` (`prepare`/`execute`/`fetchColumn`)
- `logAction()` doplněn do `news_delete`, `contact_delete`, `page_delete`
- Dvojí `db_connect()` v `blog_cat_delete.php`
- Kontrast textu v admin layoutu (`#aaa` → `#999`)
- Hlavní nadpis `subscribe.php` opraven z `<h2>` na `<h1>`
- Minimální velikost interaktivních prvků 24 px (WCAG 2.5.8)

## [1.0.0] – 2026-03-18

### Přidáno
- Základní CMS s moduly: Blog, Novinky, Události, Ke stažení, Galerie, Podcast, Jídelníček, Chat, Kontakt, Stránky, Místa
- Administrace s přihlášením a správou uživatelů
- Newsletter s přihlášením odběratelů
- Export/Import dat
- Instalátor a migrace
- Verzování a release skript
