# Changelog

Všechny důležité změny projektu Kora CMS jsou dokumentovány v tomto souboru.
Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/)
a projekt používá [Semantic Versioning](https://semver.org/lang/cs/).

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
