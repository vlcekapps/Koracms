# Kora CMS

Kora CMS je redakční systém v čistém PHP bez frameworku. Je určený pro osobní weby, blogy, obce, spolky, menší firmy a komunitní projekty. Klade důraz na stabilní provoz, přístupnost (WCAG 2.2 AA) a praktickou administraci.

---

## Obsah

- [Požadavky](#požadavky)
- [Instalace](#instalace)
- [Konfigurace](#konfigurace)
- [Aktualizace](#aktualizace)
- [Plánované úlohy (cron.php)](#plánované-úlohy-cronphp)
- [Přehled modulů](#přehled-modulů)
- [Šablony a vzhled](#šablony-a-vzhled)
- [Uživatelské role](#uživatelské-role)
- [HTML editor a snippety](#html-editor-a-snippety)
- [Navigace webu](#navigace-webu)
- [Widgety](#widgety)
- [Bezpečnost](#bezpečnost)
- [Přístupnost](#přístupnost)
- [Zálohování a údržba](#zálohování-a-údržba)
- [Řešení problémů](#řešení-problémů)
- [Nginx](#nginx)
- [Ověření po změnách](#ověření-po-změnách)
- [Další dokumentace](#další-dokumentace)

---

## Požadavky

| Komponenta | Minimální verze | Doporučená verze |
|---|---:|---:|
| PHP | 8.0 | 8.4 |
| MySQL | 5.7 | 8.0+ |
| MariaDB | 10.3 | 11+ |
| Apache | 2.4 | 2.4+ |
| Nginx | podporováno | aktuální |

Vyžadovaná PHP rozšíření:

- `pdo` + `pdo_mysql`
- `mbstring`
- `fileinfo`
- `gd`
- `zip`

Volitelná rozšíření:

- `curl` – využívá se jako fallback při importu fotografií z eStránek

---

## Instalace

### 1. Nahrajte soubory

Zkopírujte obsah projektu do kořenového adresáře webu nebo do podsložky.

Pro lokální vývoj doporučujeme [Laragon](https://laragon.org/) s PHP 8.4.

### 2. Vytvořte databázi

```sql
CREATE DATABASE nazev_databaze CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Vytvořte `config.php`

```bash
cp config.sample.php config.php
```

Vyplňte přístup k databázi a základní URL:

```php
$server   = 'localhost';
$user     = 'root';
$pass     = '';
$database = 'nazev_databaze';

define('BASE_URL', '');
```

Pokud web běží v podsložce, nastavte například:

```php
define('BASE_URL', '/koracms');
```

### 4. Spusťte instalaci

```text
http://vas-web.cz/install.php
```

Při instalaci zvolte profil webu:

- **Osobní web** – osobní stránky s blogem
- **Blog / magazín** – zaměřeno na články a autory
- **Obec / spolek** – vývěska, události, dokumenty
- **Služby / firma** – prezentace služeb a kontakt
- **Vlastní profil** – neutrální stav pro ruční nastavení

Profily přednastaví doporučené moduly, homepage widgety, navigaci a šablonu.

### 5. Smažte `install.php`

Po instalaci soubor odstraňte ze serveru.

### 6. Přihlaste se

```text
http://vas-web.cz/admin/login.php
```

---

## Konfigurace

Veškerá konfigurace je v souboru `config.php`. Vzorový soubor `config.sample.php` obsahuje všechny dostupné konstanty s komentáři.

### SMTP (e-maily)

Kora CMS používá e-maily pro registraci, obnovu hesla, newsletter, rezervace, formuláře a interní notifikace. Bez nastavení SMTP se CMS pokusí použít `localhost:25` bez autentizace.

```php
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'uzivatel@example.com');
define('SMTP_PASS', 'heslo-nebo-app-password');
define('SMTP_SECURE', 'tls'); // '', 'tls', 'ssl'
```

Pro dobrou doručitelnost doporučujeme mít na doméně nastavené záznamy `SPF`, `DKIM` a `DMARC`.

### Privátní úložiště

Citlivé přílohy formulářů a denní zálohy databáze se ukládají mimo webroot. Výchozí cesta je `../kora_storage`. Vlastní cestu nastavíte přes:

```php
define('KORA_STORAGE_DIR', '/cesta/mimo/webroot/kora_storage');
```

### GitHub issue bridge

Propojení formulářových odpovědí s GitHub Issues vyžaduje fine-grained token s oprávněním `Issues: Read and write`:

```php
define('GITHUB_ISSUES_TOKEN', 'ghp_...');
```

Repozitář se nastavuje v administraci v sekci **Obecná nastavení**.

### Google Analytics 4

GA4 Measurement ID zadejte v administraci: **Obecná nastavení → Google Analytics a vlastní kód**. CMS automaticky vloží gtag.js do hlavičky webu.

### Režim údržby

V administraci: **Obecná nastavení → Provoz webu → Zapnout režim údržby**. Návštěvníci uvidí stránku s vlastním textem a HTTP kódem 503. Přihlášení administrátoři vidí web normálně.

### Cron token

Pokud nemáte CLI přístup a musíte používat webový cron, nastavte token:

```php
define('CRON_TOKEN', 'dlouhy-nahodny-retezec');
```

Podrobnosti viz [Plánované úlohy](#plánované-úlohy-cronphp).

---

## Aktualizace

### 1. Nahrajte nové soubory

Přepište stávající soubory novou verzí. `config.php` nepřepisujte.

### 2. Spusťte migrace

```text
http://vas-web.cz/migrate.php
```

Přihlaste se jako superadmin. Migrace doplní nové tabulky, sloupce a výchozí nastavení bez přepisu existujících dat. Čerstvá instalace přes `install.php` má aktuální schéma a `migrate.php` po ní není potřeba.

### 3. Smažte `migrate.php`

Po dokončení migrace soubor odstraňte.

---

## Plánované úlohy (`cron.php`)

Endpoint `cron.php` zajišťuje pravidelné úlohy na pozadí:

- zrušení publikace obsahu podle naplánovaného data
- úklid dočasných souborů a starých audit logů
- automatickou denní zálohu databáze

### CLI cron (doporučeno)

```bash
*/5 * * * * php /cesta/k/webu/cron.php
```

Na Windows Serveru použijte Plánovač úloh:

```text
php C:\cesta\k\webu\cron.php
```

### HTTP cron (náhradní řešení)

Vyžaduje nastavení `CRON_TOKEN` v `config.php`:

```bash
curl "https://vas-web.cz/cron.php?token=VAS_CRON_TOKEN"
```

Po nastavení doporučujeme spustit cron jednou ručně z příkazové řádky a zkontrolovat výstup.

---

## Přehled modulů

Moduly se zapínají a vypínají v administraci: **Obecná nastavení → Správa modulů**.

| Modul | Co umí |
|---|---|
| **Blogy** | Více blogů v jedné instalaci, týmy blogů, články, kategorie, štítky, komentáře, plánované publikování, veřejní autoři, author archive, globální i per-blog RSS feed |
| **Novinky** | Krátké zprávy s autorem, slug URL, veřejným hledáním, plánovaným skrytím a SEO fallbacky |
| **Události** | Přehled akcí s datem, místem a detailem |
| **Galerie** | Alba a fotografie s detailovými URL, hledáním, stránkováním, revizemi a bezpečným media endpointem |
| **Podcasty** | Více pořadů, epizody, artwork, chráněné assety, RSS feed s iTunes značkami, redirecty a revize |
| **Zajímavá místa** | Adresář s typem místa, adresou, GPS a otevírací dobou |
| **Ke stažení** | Katalog dokumentů a software s verzemi, kompatibilitou, historií změn a veřejnými filtry |
| **Jídelní lístek** | Karty jídel a nápojů s platností, archivem, hledáním a revizemi |
| **Ankety** | Hlasování s plánováním, fulltextovým hledáním, slug URL, SEO fallbacky a revizemi |
| **Znalostní báze** | FAQ s kategoriemi, hledáním, stránkováním, SEO a FAQPage strukturovanými daty |
| **Formuláře** | Form Builder s přílohami, podmínkami, helpdesk workflow, webhooky a GitHub issue bridge |
| **Vývěska** | Úřední deska s typem položky, datem vyvěšení, připnutím, filtrováním a archivem |
| **Rezervace** | Zdroje, kategorie, lokality, kalendáře, schvalování a storno přes token |
| **Statické stránky** | Vlastní stránky se slug URL a volitelným zobrazením v navigaci |
| **Kontakt** | Kontaktní formulář s CAPTCHA, honeypotem a rate limitingem |
| **Chat** | Moderovaná veřejná nástěnka s inbox workflow, historií a odpověďmi e-mailem |
| **Newsletter** | Odběr e-mailem s potvrzením, odhlášením a historií rozesílek |

README drží jen vysokou úroveň: co CMS umí, jak se instaluje, konfiguruje a provozuje. Podrobné administrační workflow, volby formulářů, podcastů a multiblogu jsou záměrně v [docs/admin-guide.md](docs/admin-guide.md).

Modul **Ke stažení** nově pokrývá i praktičtější katalogový scénář: doporučené položky, datum vydání, domovskou stránku projektu, požadavky a kompatibilitu, SHA-256 checksum, sledování počtu stažení, historii revizí a veřejné filtrování podle kategorie, typu, platformy a zdroje.

Modul **Znalostní báze** nově umí veřejné hledání, filtrování podle kategorie, stránkování, přepínání `karty / rozbalené odpovědi`, per-FAQ SEO metadata, redirecty při změně slugu a `FAQPage` strukturovaná data pro vyhledávače.

Modul **Novinky** nově drží stejný publikační model jako ostatní obsahové moduly: respektuje `unpublish_at`, podporuje veřejné fulltextové hledání, admin stránkování, redirecty po změně slugu, širší revize a volitelná SEO pole `meta title` a `meta description`.

Modul **Jídelní lístek** nově rozlišuje `platné nyní / připravované / archivní` lístky podle `Platí od / do`, podporuje veřejné hledání, scope filtry, stránkování archivu, redirecty při změně slugu, historii revizí a structured data pro detail lístku.

Modul **Galerie** nově chrání neveřejná alba i fotografie i na úrovni detailu, vyhledávání a sitemapy, používá bezpečný media endpoint místo přímých `/uploads/gallery/` cest, podporuje redirecty po změně slugu, historii revizí, veřejné hledání, stránkování alb i detailu a structured data pro alba i fotografie.

Modul **Chat** nově funguje jako moderovaná veřejná nástěnka: nové zprávy se nejdřív ukládají ke schválení, veřejně se nezobrazuje e-mail ani web autora, veřejný výpis podporuje hledání, řazení a stránkování a administrace nabízí inbox workflow se schvalováním, interní poznámkou, historií změn a odpovědí e-mailem.

Knihovna **Média** nově rozlišuje veřejné a soukromé soubory, odmítá nové SVG uploady, používá canonical media helpery místo ručně skládaných `/uploads/media/...` URL, blokuje mazání používaných souborů a podporuje náhradu souboru, rozšířená metadata i hromadné akce v administraci.

Modul **Ankety** nově používá stejný publikační helper jako widgety, sitemapa a vyhledávání, takže aktivní a archivní ankety drží konzistentní veřejnou viditelnost. Součástí modulu je veřejné fulltextové hledání, stránkování indexu, redirect při změně slugu, širší revize a volitelná SEO pole `meta title` a `meta description`.

V multiblog administraci nově umí přesun článků mezi blogy vedle automatického vyrovnání taxonomií i ruční mapování chybějících kategorií a štítků na existující taxonomie cílového blogu. Tato volba je dostupná jen uživatelům, kteří smějí spravovat taxonomie cílového blogu.

CMS automaticky generuje XML sitemapu (`sitemap.xml`) ze všech publikovaných veřejných stránek.

---

## Šablony a vzhled

Součástí CMS jsou čtyři šablony: `default`, `civic`, `editorial` a `modern-service`.

V administraci lze:

- aktivovat šablonu nebo spustit živý náhled bez ostré aktivace
- upravit barvy, akcenty, typografii a šířku obsahu
- měnit variantu hlavičky
- importovat a exportovat portable ZIP balíčky šablon

Pokud aktivní šablona neobsahuje konkrétní view, systém automaticky použije `default`.

---

## Uživatelské role

Administrace používá capability model – uživatelé vidí jen to, co potřebují.

| Role | Přístup |
|------|---------|
| **Veřejný uživatel** | Bez přístupu do administrace |
| **Autor** | Vlastní články a novinky |
| **Editor** | Širší práce s obsahem a schvalováním |
| **Moderátor** | Komentáře, chat, kontaktní zprávy |
| **Správce rezervací** | Rezervace, zdroje a lokality |
| **Admin** | Plná správa webu |

Fronta **Ke schválení** sjednocuje čekající obsah, komentáře a rezervace na jednom místě.

---

## HTML editor a snippety

Kora CMS podporuje dva editory:

- **HTML textarea** – výchozí, přístupnější varianta
- **WYSIWYG (Quill)** – volitelný vizuální režim

Pro HTML obsah je k dispozici content picker – přístupný dialog pro vložení interních odkazů, galerií, médií, formulářů, anket a hotových HTML bloků.

### Podporované snippety

| Snippet | Výstup |
|---|---|
| `[audio]https://example.test/audio.mp3[/audio]` | HTML5 audio přehrávač |
| `[video]https://example.test/video.mp4[/video]` | HTML5 video přehrávač |
| `[gallery]slug-alba[/gallery]` | Vložená galerie podle slugu |
| `[form]slug-formulare[/form]` | Živý embed veřejného formuláře |
| `[poll]slug-ankety[/poll]` | Živý embed veřejné ankety |
| `[download]slug-polozky[/download]` | Teaser karta položky ke stažení |
| `[podcast]slug-poradu[/podcast]` | Teaser karta podcastového pořadu |
| `[podcast_episode]slug-poradu/slug-epizody[/podcast_episode]` | Teaser karta epizody podcastu |
| `[place]slug-mista[/place]` | Teaser karta zajímavého místa |
| `[event]slug-udalosti[/event]` | Teaser karta události |
| `[board]slug-oznameni[/board]` | Teaser karta položky vývěsky |

Snippety fungují ve všech HTML polích, která CMS veřejně renderuje přes `renderContent()`. Formuláře a ankety se vkládají jako živé interaktivní embedy, ostatní nové snippety jako sjednocené obsahové karty.

---

## Navigace webu

Kora CMS používá jedno rozhraní pro správu pořadí navigace. V administraci (**Navigace webu**) lze řadit:

- moduly
- blogy
- veřejné formuláře
- statické stránky

Položky lze libovolně kombinovat – stránka může být mezi moduly, formulář vedle blogu.

---

## Widgety

Homepage, sidebar i footer se skládají přes widgetový systém. V administraci lze přidávat widgety do tří zón, měnit jejich pořadí a nastavovat parametry.

Widgety pokrývají typické potřeby: úvodní text, nejnovější články, novinky, události, anketa, newsletter, ke stažení, FAQ, místa, podcasty, galerie, vybraný formulář, vyhledávání, kontaktní údaje a vlastní HTML.

Widgety respektují stav modulů – vypnutý modul se nenabízí.

Kompletní seznam widgetů: [docs/admin-guide.md](docs/admin-guide.md#kompletní-seznam-widgetů)

---

## Bezpečnost

Kora CMS používá:

- volitelné 2FA přes TOTP
- CSRF ochranu
- rate limiting
- honeypot pole
- CAPTCHA
- prepared statements
- bezpečné hashování hesel
- CSP nonce
- HSTS při HTTPS
- ochranu `.env` a `.git/`
- audit log a kontrolu integrity souborů

V nastavení webu už nové uploady loga a favicony nepřijímají SVG. Backend současně hlídá i velikost branding souborů, takže se do veřejně servírovaných assetů nedostane aktivní obsah ani přehnaně velké soubory.

---

## Přístupnost

Projekt cílí na **WCAG 2.2 Level AA**:

- skip link na obsah
- viditelný focus stav
- sémantické HTML
- formuláře přes `label`, `fieldset`, `legend`
- helper texty přes `aria-describedby`
- přístupné dialogy s návratem fokusu
- klávesnicová ovladatelnost i tam, kde je drag & drop
- průběžný audit přes `build/runtime_audit.php`

---

## Zálohování a údržba

### Automatické zálohy

Cron každý den vytvoří SQL zálohu databáze do privátního úložiště (`../kora_storage/backups/`). Zálohy se uchovávají 7 dní.

### Ruční záloha

V administraci: **Import / Export → Záloha databáze**. Stáhne aktuální SQL export.

### Kontrola integrity

V administraci: **Integrita souborů**. Porovná SHA-256 otisky PHP souborů s uloženým snímkem.

### Režim údržby

V administraci: **Obecná nastavení → Provoz webu**. Zapne stránku údržby s HTTP 503 pro návštěvníky. Přihlášení administrátoři vidí web normálně.

---

## Řešení problémů

Pokud administrace po uložení obsahu vrátí formulář zpět s chybou u data nebo času, jde nově o záměrné ochranné chování. CMS přísněji validuje plánované publikování, zrušení publikace, platnost lístků, termíny anket a rezervační časy, aby se neukládaly neplatné nebo nejednoznačné hodnoty.

Totéž platí i pro veřejné rezervace: rezervační formulář nově odmítne neexistující kalendářní datum místo toho, aby ho server tiše převedl na jiný den.

| Příznak | Co zkontrolovat |
|---------|----------------|
| E-maily nedorazí | Ověřte SMTP konstanty v `config.php`. Zkontrolujte DNS záznamy SPF, DKIM a DMARC na doméně. |
| Bílá stránka po aktualizaci | Spusťte `migrate.php`. Zkontrolujte PHP error log (`php -l soubor.php` pro kontrolu syntaxe). |
| Modul se nezobrazuje v navigaci | Ověřte, že je modul zapnutý v **Obecná nastavení → Správa modulů**. |
| Upload souborů nefunguje | Zkontrolujte PHP rozšíření `gd` a `fileinfo`. Zvyšte `upload_max_filesize` a `post_max_size` v `php.ini`. |
| Cron nefunguje | Ověřte cestu k PHP a oprávnění. Zkuste ruční spuštění: `php /cesta/k/webu/cron.php`. |
| Web ukazuje stránku údržby | Zkontrolujte nastavení **Obecná nastavení → Provoz webu** – režim údržby může být zapnutý. |

---

## Nginx

Kora CMS obsahuje `.htaccess` pro Apache. Při nasazení na Nginx použijte jako základ tuto konfiguraci:

```nginx
server {
    listen 80;
    server_name vas-web.cz;
    root /cesta/k/webu;
    index index.php;

    # Bezpečnostní hlavičky
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "same-origin" always;

    # Zakázané soubory
    location ~ ^/(config|db|auth)\.php$ { deny all; }
    location ~ \.(inc|log|sql|bak|sh|cfg)$ { deny all; }
    location ~ /\.env { deny all; }
    location ~ /\.git { deny all; }

    # Chráněné adresáře
    location ^~ /uploads/forms/ { deny all; }
    location ^~ /uploads/backups/ { deny all; }

    # Čisté URL – moduly
    location ~ ^/authors/?$ { rewrite ^ /authors/index.php last; }
    location ~ ^/author/([a-z0-9\-]+)/?$ { rewrite ^/author/(.+?)/?$ /author.php?slug=$1 last; }
    location ~ ^/blog/([a-z0-9\-]+)/?$ { rewrite ^/blog/(.+?)/?$ /blog/article.php?slug=$1 last; }
    location ~ ^/board/([a-z0-9\-]+)/?$ { rewrite ^/board/(.+?)/?$ /board/document.php?slug=$1 last; }
    location ~ ^/downloads/([a-z0-9\-]+)/?$ { rewrite ^/downloads/(.+?)/?$ /downloads/item.php?slug=$1 last; }
    location ~ ^/events/([a-z0-9\-]+)/?$ { rewrite ^/events/(.+?)/?$ /events/event.php?slug=$1 last; }
    location ~ ^/faq/([a-z0-9\-]+)/?$ { rewrite ^/faq/(.+?)/?$ /faq/item.php?slug=$1 last; }
    location ~ ^/forms/([a-z0-9\-]+)/?$ { rewrite ^/forms/(.+?)/?$ /forms/index.php?slug=$1 last; }
    location ~ ^/food/card/([a-z0-9\-]+)/?$ { rewrite ^/food/card/(.+?)/?$ /food/card.php?slug=$1 last; }
    location ~ ^/gallery/album/([a-z0-9\-]+)/?$ { rewrite ^/gallery/album/(.+?)/?$ /gallery/album.php?slug=$1 last; }
    location ~ ^/gallery/photo/([a-z0-9\-]+)/?$ { rewrite ^/gallery/photo/(.+?)/?$ /gallery/photo.php?slug=$1 last; }
    location ~ ^/news/([a-z0-9\-]+)/?$ { rewrite ^/news/(.+?)/?$ /news/article.php?slug=$1 last; }
    location ~ ^/polls/([a-z0-9\-]+)/?$ { rewrite ^/polls/(.+?)/?$ /polls/index.php?slug=$1 last; }
    location ~ ^/places/([a-z0-9\-]+)/?$ { rewrite ^/places/(.+?)/?$ /places/place.php?slug=$1 last; }
    location ~ ^/podcast/([a-z0-9\-]+)/([a-z0-9\-]+)/?$ { rewrite ^/podcast/(.+?)/(.+?)/?$ /podcast/episode.php?show=$1&slug=$2 last; }
    location ~ ^/podcast/([a-z0-9\-]+)/?$ { rewrite ^/podcast/(.+?)/?$ /podcast/show.php?slug=$1 last; }

    # XML sitemap
    location = /sitemap.xml { rewrite ^ /sitemap.php last; }

    # Multi-blog catch-all (musí být poslední)
    location ~ ^/([a-z0-9\-]+)/([a-z0-9\-]+)/?$ {
        try_files $uri $uri/ /blog_router.php?blog_slug=$1&slug=$2&$args;
    }
    location ~ ^/([a-z0-9\-]+)/?$ {
        try_files $uri $uri/ /blog_router.php?blog_slug=$1&$args;
    }

    # PHP zpracování
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;  # upravte podle svého prostředí
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Toto je výchozí šablona. Upravte cesty, `server_name` a `fastcgi_pass` podle svého prostředí a konfiguraci otestujte před nasazením.

---

## Ověření po změnách

Před nasazením změn spusťte:

```bash
php build/runtime_audit.php
php build/http_integration.php
```

Při větších zásazích doplňte i PHP lint:

```bash
php -l cesta/k/souboru.php
```

---

## Další dokumentace

- [CHANGELOG.md](CHANGELOG.md) – historie verzí
- [docs/admin-guide.md](docs/admin-guide.md) – detailní práce v administraci: Form Builder, podcasty, multiblog, widgety a content picker
- [docs/ux-audit-framework.md](docs/ux-audit-framework.md) – framework pro UX a přístupnostní audit
