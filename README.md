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

Skript přidá případné nové tabulky, sloupce a výchozí nastavení. Existující data ani nastavení nepřepíše.

### 3. Smažte migrační soubor

Po dokončení **odstraňte** soubor `migrate.php` ze serveru.

---

## Dostupné moduly

| Modul | Popis |
|---|---|
| **Blog** | Články s kategoriemi, tagy, komentáři a náhledem před zveřejněním |
| **Novinky** | Krátké zprávy |
| **Chat** | Jednoduchá veřejná diskuse |
| **Kontakt** | Kontaktní formulář s ochranou CAPTCHA |
| **Galerie** | Fotoalba |
| **Události** | Kalendář akcí |
| **Podcasty** | Správa více podcastů a jejich epizod; každý podcast má vlastní RSS feed (`/podcast/feed.php?show=ID`) kompatibilní s podcastovými aplikacemi |
| **Zajímavá místa** | Adresář míst s popisem a odkazem |
| **Newsletter** | Odběr novinek e-mailem |
| **Ke stažení** | Knihovna souborů ke stažení |
| **Jídelní lístek** | Správa jídelních karet |
| **Ankety** | Hlasování s výsledky a archivem |
| **Statické stránky** | Vlastní stránky se slug URL |

Každý modul lze zapnout nebo vypnout v administraci v sekci **Nastavení → Moduly**.

---

## Nastavení

Nastavení je rozděleno do tří samostatných sekcí:

- **Základní nastavení** – název webu, popis, e-mail, počty položek, editor, sociální sítě, favicon, logo, cookie lišta, režim údržby, text úvodní stránky
- **Moduly** – zapínání a vypínání jednotlivých modulů
- **Nastavení zobrazení** – pořadí modulů v navigaci pro návštěvníky (přesun nahoru / dolů)

---

## Správa uživatelů

Hlavní administrátor (účet vytvořený při instalaci) může přidávat **spolupracovníky** v sekci **Správa uživatelů**. Obsah přidaný spolupracovníkem musí hlavní administrátor před zveřejněním schválit.

---

## Přístupnost

CMS je navržen s ohledem na **WCAG 2.2** – administrace i veřejná část webu používají sémantické HTML, ARIA atributy a jsou ovladatelné pouze klávesnicí.
