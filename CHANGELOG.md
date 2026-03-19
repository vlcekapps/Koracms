# Changelog

Všechny důležité změny projektu Kora CMS jsou dokumentovány v tomto souboru.
Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/)
a projekt používá [Semantic Versioning](https://semver.org/lang/cs/).

## [Unreleased]

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
