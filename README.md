# Kora CMS

Kora CMS je redakční systém bez frameworku zaměřený na stabilní provoz, přístupnost a praktické použití pro osobní weby, blogy, obce, spolky, menší firmy i komunitní projekty.

Systém dnes stojí na těchto principech:

- profilově řízené nasazení při instalaci (`Osobní web`, `Blog / magazín`, `Obec / spolek`, `Služby / firma`, `Vlastní profil`)
- first-party šablony s živým náhledem a bezpečným fallbackem
- sjednocená administrace s capability modelem, schvalováním a audit logem
- důraz na WCAG 2.2 AA, skip linky, focus stavy a čitelné formuláře
- plný runtime audit přes `build/runtime_audit.php`

---

## Co Kora CMS dnes umí

- **Multiblog** – více blogů v jedné instalaci, každý s vlastním názvem, slugem, popisem, kategoriemi a štítky
- **Veřejní autoři** – veřejný profil autora, author archive i přehled autorů na `/authors/`
- **Widgetová homepage** – homepage, sidebar a footer se skládají přes widgety
- **Jednotná navigace webu** – moduly, blogy a statické stránky se řadí v jednom rozhraní
- **Redakční workflow** – role, schvalování, fronta `Ke schválení`, moderátorské inboxy
- **HTML editorové pomůcky** – content picker pro interní odkazy, galerie, média a vložení hotových bloků
- **Snippety v HTML obsahu** – audio, video a galerie
- **Form Builder 2.0** – formuláře s více typy polí, přílohami, podmínkami, helpdesk workflow, webhooky a GitHub issue bridge
- **Portable theme packages** – import/export statických šablon bez PHP override
- **Bezpečnost a audit** – 2FA, rate limiting, CAPTCHA, honeypot, audit log, kontrola integrity

---

## Lokální vývoj

Pro lokální vývoj doporučujeme **[Laragon](https://laragon.org/)** s **PHP 8.4**.

Laragon pohodlně zajistí:

- Apache
- MySQL nebo MariaDB
- PHP
- phpMyAdmin

Stačí zkopírovat projekt do `laragon/www/`, vytvořit databázi a spustit `install.php`.

---

## Minimální požadavky

| Komponenta | Minimální verze | Doporučená verze |
|---|---:|---:|
| PHP | 8.0 | 8.4 |
| MySQL | 5.7 | 8.0+ |
| MariaDB | 10.3 | 11+ |
| Apache | 2.4 | 2.4+ |
| Nginx | podporováno | aktuální |

Vyžadovaná PHP rozšíření:

- `pdo`
- `pdo_mysql`
- `fileinfo`
- `gd`

---

## Instalace

### 1. Nahrajte soubory

Zkopírujte obsah projektu do kořenového adresáře webu nebo do podsložky.

### 2. Vytvořte databázi

```sql
CREATE DATABASE nazev_databaze CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Vytvořte `config.php`

Zkopírujte vzorový soubor:

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

### 3a. Doplňte SMTP konfiguraci

Pro produkční web doporučujeme v `config.php` rovnou nastavit i SMTP, protože Kora CMS používá e-maily pro:

- registraci a potvrzení účtu
- obnovení hesla
- newsletter
- rezervace
- formuláře
- interní notifikace administrace

Ukázka:

```php
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'uzivatel@example.com');
define('SMTP_PASS', 'heslo-nebo-app-password');
define('SMTP_SECURE', 'tls'); // '', 'tls', 'ssl'
```

Pokud SMTP konstanty nenastavíte, CMS se pokusí použít `localhost:25` bez autentizace. To může fungovat na lokálním serveru nebo některých hostingech, ale pro běžný produkční provoz je lepší explicitní SMTP konfigurace.

### 4. Spusťte instalaci

Otevřete:

```text
http://vas-web.cz/install.php
```

Při instalaci můžete rovnou zvolit profil webu:

- `Osobní web`
- `Blog / magazín`
- `Obec / spolek`
- `Služby / firma`
- `Vlastní profil`

První čtyři profily mohou jedním krokem přednastavit doporučené moduly, homepage, navigaci a vhodnou first-party šablonu. `Vlastní profil` ponechá CMS v neutrálním stavu pro ruční nastavení.

### 5. Smažte `install.php`

Po instalaci soubor odstraňte ze serveru.

### 6. Přihlaste se

```text
http://vas-web.cz/admin/login.php
```

---

## Aktualizace

### 1. Nahrajte nové soubory

Přepište stávající soubory novou verzí. `config.php` nepřepisujte.

### 2. Spusťte migrace

```text
http://vas-web.cz/migrate.php
```

Před spuštěním se přihlaste jako superadmin. Migrace doplní nové tabulky, sloupce a výchozí nastavení bez přepisu existujících dat.

### 3. Smažte `migrate.php`

Po dokončení migrace soubor opět odstraňte.

---

## Doporučené ověření po změnách

Před releasem nebo nasazením změn spusťte:

```bash
php build/runtime_audit.php
```

Při větších zásazích dává smysl přidat i PHP lint nad upravenými soubory:

```bash
php -l cesta/k/souboru.php
```

---

## Přehled modulů

| Modul | Co umí |
|---|---|
| **Blogy** | Multiblog, články, kategorie, štítky, komentáře, preview, plánované publikování, odhad čtení, počet přečtení, veřejní autoři, author archive, author index, RSS feed |
| **Novinky** | Krátké zprávy s autorem, detailem a čistou slug URL |
| **Události** | Přehled akcí s datem, místem, detailem a slug URL |
| **Galerie** | Alba a fotografie s detailovými URL, obálkou alba, exportem do ZIP a přístupnou veřejnou prezentací |
| **Podcasty** | Více pořadů i epizod, detail pořadu, detail epizody a RSS feed pořadu |
| **Zajímavá místa** | Turistický adresář s typem místa, perexem, obrázkem, adresou, GPS, kontaktem a otevírací dobou |
| **Ke stažení** | Katalog dokumentů a software s typem položky, verzí, platformou, licencí, externím odkazem a bezpečným file endpointem |
| **Jídelní lístek** | Karty jídel a nápojů, aktuální lístek, archiv a detail přes slug URL |
| **Ankety** | Aktivní anketa, archiv, detail přes slug URL a ochrana proti opakovanému hlasování |
| **Znalostní báze** | FAQ / knowledge base s kategoriemi, detailem, slug URL a přehlednou veřejnou navigací |
| **Formuláře** | Form builder s veřejnými formuláři, helpdesk inboxem odpovědí, CSV exportem, přílohami, podmínkami zobrazení, webhooky, GitHub issue bridge a issue-report presety |
| **Vývěska / Oznámení** | Úřední deska nebo komunitní vývěska s typem položky, perexem, obrázkem, kontaktem, připnutím a archivem |
| **Rezervace** | Zdroje, kategorie, lokality, veřejné rezervace, kalendáře, schvalování a storno přes token |
| **Statické stránky** | Vlastní stránky se slug URL, volitelným zobrazením v navigaci a samostatným řazením |
| **Kontakt** | Kontaktní formulář s CAPTCHA, honeypotem, rate limitingem a moderátorským inboxem |
| **Chat** | Jednoduchá veřejná diskuse s moderátorským inboxem a interními stavy zpráv |
| **Newsletter** | Odběr e-mailem, potvrzení, odhlášení, přehled odběratelů, historie rozesílek a hromadné akce |

Moduly lze zapínat a vypínat v administraci v sekci **Obecná nastavení → Správa modulů**.

---

## Widgety a homepage

Homepage, sidebar i footer se skládají přes widgetový systém.

V administraci lze:

- přidávat widgety do zón `homepage`, `sidebar`, `footer`
- při přidání rovnou zvolit cílovou zónu
- měnit jejich pořadí
- nastavovat parametry jednotlivých widgetů
- kombinovat systémové widgety a vlastní HTML

Typické widgety:

- úvodní text
- nejnovější články
- novinky
- doporučený obsah z blogu, vývěsky, ankety nebo newsletteru
- vývěska
- nadcházející události
- anketa
- newsletter
- ke stažení
- FAQ
- zajímavá místa
- nejnovější epizody podcastu
- vybraný formulář
- nápověda galerie
- vyhledávání
- kontaktní údaje
- vlastní HTML

Widgety respektují stav modulů. Vypnutý modul se nenabízí.
Widget `Vybraný formulář` se nabídne jen tehdy, když existuje alespoň jeden aktivní veřejný formulář.

---

## Navigace webu

Kora CMS používá jednotné rozhraní pro správu pořadí navigace.

V jedné správě lze řadit:

- moduly
- blogy
- statické stránky

To znamená, že stránka může být mezi moduly a blog může být před novinkami. `Navigace webu` je hlavní autorita pro skutečné pořadí položek v menu návštěvníka.

Samostatná stránka `Základní pořadí stránek` slouží už jen jako fallback pořadí mezi statickými stránkami samotnými. Hodí se pro interní přehledy a jako výchozí stabilní řazení, ale neřídí přímo hlavní menu webu.

Řazení navigace je oddělené od interních seznamů modulů – navigace webu se spravuje jinak než obsah uvnitř modulů.

---

## Šablony a vzhled

Součástí CMS jsou čtyři first-party šablony:

- `default`
- `civic`
- `editorial`
- `modern-service`

Administrace umí:

- aktivovat šablonu
- spustit živý náhled bez ostré aktivace
- upravit barvy, akcenty, typografii a šířku obsahu
- měnit variantu hlavičky
- importovat a exportovat portable ZIP balíčky šablon

Pokud aktivní šablona neobsahuje konkrétní view, systém bezpečně fallbackuje na `default`.

---

## Role, workflow a administrace

Administrace používá capability model, takže uživatelé vidí jen to, co opravdu potřebují.

Typické role:

- **Veřejný uživatel** – bez přístupu do administrace
- **Autor** – vlastní články a novinky
- **Editor** – širší práce s obsahem a schvalováním
- **Moderátor** – komentáře, chat, kontaktní zprávy
- **Správce rezervací** – rezervace, zdroje a lokality
- **Admin** – běžná plná správa webu

K dispozici je také fronta **Ke schválení**, která sjednocuje čekající obsah, komentáře nebo rezervace.

Administrace má:

- role-based dashboard
- sjednocené filtry a hromadné akce
- přehledné návratové odkazy
- civilnější prázdné stavy a detailové obrazovky

---

## Blogy a autoři

Blog vrstva je dnes výrazně dál než jen „modul článků“:

- více blogů v jedné instalaci
- veřejný detail článku přes čistou slug URL
- veřejný profil autora
- author archive
- přehled autorů na `/authors/`
- volitelný odkaz z blogu na veřejný seznam autorů
- featured článek na homepage podle `view_count`
- počet přečtení v metadatech článku

Blog karty na homepage i ve veřejném výpisu používají stejné pořadí informací:

1. nadpis
2. metadata
3. perex
4. odkaz na článek

---

## HTML editor, content picker a snippety

Kora CMS podporuje dva editory obsahu:

- **HTML textarea** – doporučená a přístupnější výchozí varianta
- **WYSIWYG (Quill)** – volitelný vizuální režim

Pro HTML pole, která se veřejně renderují přes `renderContent()`, je k dispozici přístupný dialog:

- vložení interního odkazu
- vložení hotového HTML bloku
- vložení galerie
- vložení audio nebo video přehrávače

### Podporované snippety

| Snippet | Výstup |
|---|---|
| `[audio]https://example.test/audio.mp3[/audio]` | HTML5 audio přehrávač |
| `[video]https://example.test/video.mp4[/video]` | HTML5 video přehrávač |
| `[gallery]slug-alba[/gallery]` | vložená galerie podle slugu |
| `[gallery slug="slug-alba"][/gallery]` | totéž s atributem |
| `[audio src="/downloads/file.php?id=123" mime="audio/mpeg"][/audio]` | audio přes interní endpoint |
| `[video src="/downloads/file.php?id=456" mime="video/mp4"][/video]` | video přes interní endpoint |

Tyto snippety fungují ve všech HTML/Markdown polích, která CMS veřejně renderuje přes `renderContent()`.

---

## Form Builder 2.0

Modul `Formuláře` už dnes není jen jednoduchý kontaktní wrapper. Umí:

- veřejné formuláře s vlastním názvem, popisem a slug URL
- pole typu `text`, `email`, `tel`, `url`, `textarea`, `select`, `radio`, `checkbox`, `více voleb`, `number`, `date`, `file`, `hidden`, `consent`
- per-field nápovědu, placeholder, výchozí hodnotu
- omezení příloh podle typu a velikosti
- více souborů u file pole
- sekce formuláře s vlastním mezititulkem a úvodním textem
- rozložení polí po řádcích a šířkách
- podmíněné zobrazování polí (`Zobrazit jen když`)
- interní redirect po odeslání
- potvrzení na stejné stránce nebo interní přesměrování po odeslání
- až dvě navazující tlačítka po úspěšném odeslání
- notifikační e-mail správci
- potvrzovací e-mail odesílateli
- přehled odpovědí a CSV export
- workflow odpovědí se stavy `nové / rozpracované / vyřešené / uzavřené`
- prioritu, štítky, přiřazení řešiteli a interní poznámku
- detail odpovědi, interní historii a odpověď odesílateli přímo z administrace
- rychlé kroky `Převzít řešení`, `Označit jako rozpracované`, `Označit jako vyřešené` a `Uzavřít hlášení`
- filtry `Jen moje`, `Nepřiřazené` a `S GitHub issue`
- propojení hlášení s GitHub issue včetně vytvoření, ručního napojení a uložení odkazu zpět do odpovědi
- webhooky po odeslání, změně workflow, odpovědi odesílateli a vytvoření nebo napojení GitHub issue

Součástí modulu jsou i hotové preset šablony:

- **Nahlášení chyby**
- **Návrh nové funkce**
- **Žádost o podporu**
- **Obecný kontaktní formulář**
- **Nahlášení problému s obsahem**

Pro issue reporting mimo GitHub je teď builder použitelný i jako lehký helpdesk inbox.

Stejný formulářový workflow lze navíc napojit i na GitHub nebo vlastní automatizace:

- **GitHub issue bridge** – z detailu odpovědi lze vytvořit nové GitHub issue, otevřít připravený návrh na GitHubu nebo ručně připojit už existující issue URL
- **Webhooky** – formulář může po odeslání nebo změně workflow poslat JSON payload na vlastní endpoint, Discord/Slack bridge nebo další integrační vrstvu

---

## HTML content tools

Čistý HTML editor dnes umí víc než jen ruční psaní kódu:

- vyhledat existující články, stránky a další veřejný obsah
- vložit interní odkaz nebo hotový HTML blok
- vložit galerii, fotografii nebo přímý odkaz ke stažení podle typu obsahu
- vložit audio/video přehrávač přes snippety nebo přímé akce z pickeru

Podporované snippety:

- `[audio]https://example.com/audio.mp3[/audio]`
- `[video]https://example.com/video.mp4[/video]`
- `[gallery]slug-alba[/gallery]`
- `[gallery slug="slug-alba"][/gallery]`

---

## Další důležité funkce

- fulltextové vyhledávání napříč veřejným obsahem
- RSS feed
- XML sitemap
- audit log administrace
- knihovna médií
- WebP a responzivní varianty obrázků
- revize obsahu
- interní poznámky k obsahu
- plánované publikování a zrušení publikace
- import/export dat CMS
- import z WordPressu
- import z eStránek
- přesměrování 301/302
- ruční i automatické zálohy databáze
- cron endpoint
- kontrola integrity souborů

---

## Bezpečnost

Kora CMS dnes používá mimo jiné:

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
- audit log a kontrolu integrity

---

## Přístupnost

Projekt cílí na **WCAG 2.2 Level AA**.

Prakticky to znamená například:

- skip link na obsah
- viditelný focus stav
- sémantické HTML
- formuláře přes `label`, `fieldset`, `legend`
- helper texty pod polem přes `aria-describedby`
- přístupné dialogy s návratem fokusu
- klávesnicovou ovladatelnost i tam, kde je drag & drop
- průběžný audit přes `build/runtime_audit.php`

UX a informační logiku průběžně doplňuje i dokument:

- [docs/ux-audit-framework.md](C:/laragon/www/docs/ux-audit-framework.md)

---

## Poznámky k provozu

- SMTP vrstva funguje přímo přes socketové připojení (`fsockopen`)
- runtime audit v lokálním prostředí korektně hlásí `smtp_connectivity` jako `SKIP`, pokud SMTP není explicitně nastavené
- při větších úpravách se vyplatí spustit `migrate.php` i na lokální databázi, aby se promítly nové sloupce a výchozí hodnoty

---

## Dokumentace změn

Historie verzí je v:

- [CHANGELOG.md](C:/laragon/www/CHANGELOG.md)
