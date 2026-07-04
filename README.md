# Kora CMS

Kora CMS je redakční systém v čistém PHP bez frameworku. Je určený pro osobní weby, blogy, obce, spolky, menší firmy a komunitní projekty. Klade důraz na stabilní provoz, přístupnost (WCAG 2.2 AA) a praktickou administraci.

---

## Obsah

- [Proč Kora CMS?](#proč-kora-cms)
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
- [Vývoj a CI](#vývoj-a-ci)
- [Vývoj nových modulů](#vývoj-nových-modulů)
- [Řešení problémů](#řešení-problémů)
- [Nginx](#nginx)
- [Ověření po změnách](#ověření-po-změnách)
- [Další dokumentace](#další-dokumentace)

---

## Proč Kora CMS?

Název Kora je krátký, dobře zapamatovatelný a záměrně českému uchu blízký. Evokuje kůru stromu: pevnou ochrannou vrstvu, která drží živý obsah pohromadě, ale nepřekáží růstu. Stejně má Kora CMS chránit a uspořádat obsah webu, zůstat stabilní, čitelný a přístupný, a přitom nevnucovat zbytečně složitý framework tam, kde stačí poctivě napsané PHP.

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
Základní vývojová kontrola `composer ci:basic` hlídá přes `build/config_sample_audit.php` a jeho self-test `build/config_sample_audit_selftest.php`, aby tento vzor neztratil databázové proměnné, hlavní runtime konstanty, bezpečné prázdné tokeny ani vysvětlení pro privátní úložiště, SMTP, GitHub issue bridge a cron token.

### SMTP (e-maily)

Kora CMS používá e-maily pro registraci, obnovu hesla, newsletter, rezervace, formuláře a interní notifikace. Vzorový `config.sample.php` používá bezpečný lokální default `localhost:25` bez autentizace; pro produkci hodnoty nahraďte údaji od svého poskytovatele SMTP.

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
- úklid dočasných souborů, starých audit logů a starých CSP report logů
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
| **Blogy** | Více blogů v jedné instalaci, týmy blogů, články, kategorie a štítky s veřejnými landing stránkami a SEO poli, komentáře, plánované publikování, série článků, ručně řízené související články, veřejní autoři, autorský obsahový hub, globální i per-blog RSS feed |
| **Novinky** | Krátké zprávy s autorem, slug URL, veřejným hledáním, autorským filtrem, plánovaným skrytím a SEO fallbacky |
| **Události** | Přehled akcí s typy, místy konání, opakovanými termíny, detailem a ICS exportem do kalendáře |
| **Galerie** | Alba a fotografie s detailovými URL, hledáním, stránkováním, revizemi, fotografickými metadaty a bezpečným media endpointem |
| **Podcasty** | Více pořadů, epizody, artwork, chráněné assety, RSS feed s iTunes značkami, redirecty a revize |
| **Zajímavá místa** | Adresář s typem místa, adresou, GPS a otevírací dobou |
| **Ke stažení** | Katalog dokumentů a software s kategoriemi, landing stránkami, sériemi verzí, kompatibilitou, historií změn a veřejnými filtry |
| **Jídelní lístek** | Karty jídel a nápojů s platností, denními nabídkami, strukturovanými položkami, cenami, alergeny, nutričními údaji, obrázky, veřejnými filtry, objednávkovými poptávkami, archivem, hledáním a revizemi |
| **Ankety** | Jedno- i vícevýběrové hlasování s plánováním, řízenou viditelností výsledků, CSV exportem, fulltextem, slug URL, SEO fallbacky a revizemi |
| **Znalostní báze** | FAQ s veřejnými kategoriemi, hledáním, stránkováním, SEO, zpětnou vazbou a FAQPage strukturovanými daty |
| **Formuláře** | Form Builder s přílohami, podmínkami, helpdesk workflow, webhooky a GitHub issue bridge |
| **Vývěska** | Úřední deska s typem položky, datem vyvěšení, připnutím, filtrováním, archivem, kategoriovými landing stránkami, evidencí zveřejnění a bezpečným odběrem |
| **Rezervace** | Zdroje, kategorie, lokality, kalendáře, schvalování, připomínky, ICS pozvánky, historie změn a storno přes token |
| **Statické stránky** | Vlastní stránky se slug URL, volitelným zobrazením v navigaci a blogovými stránkami se slugem unikátním jen v rámci konkrétního blogu |
| **Kontakt** | Kontaktní formulář s tématy dotazů, CAPTCHA, honeypotem, rate limitingem, referenčními kódy a odpověďmi z administrace |
| **Chat** | Moderovaná veřejná nástěnka s tématy, připnutými zprávami, vlákny a soukromým podpůrným inboxem |
| **Newsletter** | Odběr e-mailem s potvrzením, odhlášením a historií rozesílek |

README drží jen vysokou úroveň: co CMS umí, jak se instaluje, konfiguruje a provozuje. Podrobné administrační workflow, volby formulářů, podcastů a multiblogu jsou záměrně v [docs/admin-guide.md](docs/admin-guide.md).

Modul **Ke stažení** pokrývá praktičtější katalogový scénář: doporučené položky, datum vydání, domovskou stránku projektu, požadavky a kompatibilitu, SHA-256 checksum, sledování počtu stažení, historii revizí a veřejné filtrování podle kategorie, typu, platformy a zdroje. Položka může být lokální soubor, externí odkaz například na GitHub Releases, nebo obojí zároveň, takže vlastní software není nutné duplikovat do CMS. Kategorie mají čisté landing URL `/downloads/kategorie/{slug}`, volitelný popis a SEO metadata. Sériová vydání se spravují přes samostatné série/verze s URL `/downloads/serie/{slug}`; detail starší položky umí upozornit na aktuální verzi a starý `series_key` zůstává kompatibilní pro importy i starší data.

Modul **Události** podporuje spravované typy akcí s veřejnou adresou `/events/typ/{slug}`, popisem a SEO poli. Událost lze volitelně navázat na veřejné místo z modulu **Zajímavá místa** a detail pak zobrazí kartu místa, odkaz na detail i mapu, pokud jsou dostupné. Při vytváření nové události lze jednorázově vygenerovat opakované denní, týdenní nebo měsíční termíny; CMS z nich vytvoří samostatné události se společnou skupinou opakování, takže pozdější úprava jednoho termínu se automaticky nepropíše do ostatních.

Modul **Znalostní báze** umí veřejné hledání, filtrování podle kategorie, stránkování, přepínání `karty / rozbalené odpovědi`, per-FAQ SEO metadata, redirecty při změně slugu a `FAQPage` strukturovaná data pro vyhledávače. Kategorie FAQ mají vlastní landing stránky `/faq/kategorie/{slug}` s volitelným popisem a SEO poli; staré filtry `?kat=` zůstávají funkční. Detail otázky může od návštěvníků sbírat jednoduchou zpětnou vazbu `Pomohla vám tato odpověď?`, kterou správce vidí jen v administraci jako redakční signál.

Modul **Novinky** nově drží stejný publikační model jako ostatní obsahové moduly: respektuje `unpublish_at`, podporuje veřejné fulltextové hledání, admin stránkování, redirecty po změně slugu, širší revize a volitelná SEO pole `meta title` a `meta description`.

Veřejné profily autorů fungují jako obsahové huby. Na `/author/slug-autora` se zobrazuje medailonek autora a publikovaný obsah napříč zapnutými moduly Blogy a Novinky; návštěvník může přepínat `Vše`, `Články` a `Novinky`. Přehled `/authors/` ukazuje souhrn obsahu autora podle zapnutých modulů a novinky podporují filtr `news/index.php?autor=slug`.

Modul **Jídelní lístek** nově rozlišuje `platné nyní / připravované / archivní` lístky podle `Platí od / do`, podporuje veřejné hledání, scope filtry, stránkování archivu, redirecty při změně slugu, historii revizí a structured data pro detail lístku. Správce může u konkrétního lístku spravovat strukturované sekce a položky včetně ceny, měny, poznámky k ceně, alergenů 1-14, dietních štítků, dostupnosti, nutričních údajů a volitelného obrázku z knihovny médií. Sekce mohou nést datum a čas podávání, takže detail i archiv umí denní nabídky přes filtr `den=YYYY-MM-DD`. Veřejný web umí filtrovat strukturované položky podle dietních štítků, vyloučených alergenů a dostupnosti a u viditelných položek zobrazuje alergenovou legendu. Pokud strukturované položky existují, veřejný web je zobrazí jako hlavní menu a původní HTML obsah použije jako doplňkové poznámky; bez položek zůstává starý HTML obsah kompatibilním fallbackem. Lístek může volitelně povolit nezávazné objednávkové poptávky chráněné captchou, honeypotem a rate-limitem; administrace pak umožní sledovat detail poptávky a měnit její stav.

Modul **Galerie** nově chrání neveřejná alba i fotografie i na úrovni detailu, vyhledávání a sitemapy, používá bezpečný media endpoint místo přímých `/uploads/gallery/` cest, podporuje redirecty po změně slugu, historii revizí, veřejné hledání, stránkování alb i detailu a structured data pro alba i fotografie. Fotografie mohou mít samostatný alt text, viditelný popisek, delší popis, kredit, licenci, datum a místo pořízení; alba mohou nastavit výchozí kredit a licenci pro nově nahrané fotografie a veřejný detail metadata zobrazí v přístupné sekci.

Modul **Chat** funguje jako moderovaná veřejná nástěnka se strukturovanými tématy. Nové veřejné zprávy se nejdřív ukládají ke schválení, schválené zprávy lze připnout nahoru a každá veřejná zpráva má vlastní detail `/chat/zprava/{id}` s moderovanými odpověďmi. Témata mají čisté URL `/chat/tema/{slug}` a v administraci vlastní správu. Stejný veřejný formulář umí i soukromý dotaz správci: vyžaduje e-mail pro odpověď, uloží referenční kód `CHT-YYYYMMDD-XXXX` a nikdy se nezobrazí ve veřejném chatu ani sitemapě.

Modul **Kontakt** nově funguje jako lehké kontaktní centrum: správce může vytvořit témata dotazů s vlastním popisem a volitelným cílovým e-mailem, návštěvník po odeslání uvidí referenční kód zprávy a administrátor může odpovědět e-mailem přímo z detailu kontaktní zprávy. Bez aktivních témat zůstává veřejný formulář jednoduchý jako dříve.

Modul **Vývěska** nově podporuje důvěryhodnější veřejný archiv: detail položky ukazuje evidenci zveřejnění včetně změn URL, příloh a SHA-256 otisku souboru, kategorie mají čisté URL `/board/kategorie/{slug}` s popisem a SEO metadata a samostatný odběr vývěsky je oddělený od newsletteru. Přihlášení k odběru probíhá na `/board/subscribe.php`, vyžaduje captcha, rate-limit a potvrzení e-mailem.

Modul **Rezervace** kromě zdrojů, kategorií, lokalit, schvalování a storna přes token podporuje kalendářové `.ics` pozvánky a e-mailové připomínky před potvrzeným termínem. Správce je zapíná u konkrétního zdroje, volí počet hodin předem a může doplnit vlastní text. Administrace u rezervace uchovává provozní historii změn a veřejná část **Moje rezervace** nabízí u budoucích potvrzených termínů bezpečné tokenové stažení kalendářového souboru. Export/import přenáší konfiguraci rezervačních zdrojů, ale ne osobní rezervace ani jejich historii.

Knihovna **Média** nově rozlišuje veřejné a soukromé soubory, odmítá nové SVG uploady, používá canonical media helpery místo ručně skládaných `/uploads/media/...` URL, blokuje mazání používaných souborů a podporuje náhradu souboru, kolekce médií, rozšířená metadata, licenční údaje i hromadné akce v administraci. Kolekce mohou mít výchozí viditelnost, kredit a licenci pro nové uploady; přehled médií umí filtrovat podle kolekce i chybějících metadat. Souborové přesuny, náhrady a úklid miniatur se logují strukturovaně bez fyzických cest. Admin obrazovka médií používá pro upload, filtry, grid, hromadné akce a detail metadat sdílenou CSS vrstvu bez lokálních `style` atributů.

Modul **Ankety** používá stejný publikační helper jako widgety, sitemapa a vyhledávání, takže aktivní a archivní ankety drží konzistentní veřejnou viditelnost. Součástí modulu je veřejné fulltextové hledání, stránkování indexu, redirect při změně slugu, širší revize a volitelná SEO pole `meta title` a `meta description`. Správce může zvolit režim `jedna možnost` nebo `více možností`, nastavit limit vybraných odpovědí, rozhodnout, kdy se veřejně ukážou výsledky, a v administraci stáhnout bezpečný CSV export agregovaných výsledků bez raw hashů hlasujících.

V multiblog administraci nově umí přesun článků mezi blogy vedle automatického vyrovnání taxonomií i ruční mapování chybějících kategorií a štítků na existující taxonomie cílového blogu. Tato volba je dostupná jen uživatelům, kteří smějí spravovat taxonomie cílového blogu. Stejné principy nově používá i běžná editace jednoho článku: po změně blogu editor automaticky předvyplní odpovídající taxonomie cílového blogu, dovolí ručně vybrat jiné existující a správcům taxonomií umí přímo z editoru vytvořit chybějící kategorii nebo štítky.

Blogy nově podporují i volitelný alternativní text loga. Pokud ho správce vyplní, použije se na veřejném indexu blogu pro čtečky obrazovky; pokud zůstane prázdný, logo se dál bere jako dekorativní a asistivní technologie ho přeskočí.

Veřejný index blogu zobrazuje doporučený článek v přirozenějším pořadí: nejdřív nadpis bloku, potom název článku jako hlavní odkaz a až pod ním datum, přibližnou dobu čtení, počet přečtení a autora.

Editor článku umožňuje ručně vybrat související články ze stejného blogu. Veřejný detail článku zobrazí ruční výběr jako první a pokud je položek méně, doplní zbytek automaticky podle kategorie, štítků a novosti. Při změně blogu se ruční výběr validuje proti cílovému blogu, takže se do detailu nepropíše odkaz na článek z cizího blogu.

Blogy mají také tematické série článků. Správa série patří ke konkrétnímu blogu, umožňuje název, slug, popis, aktivní stav a ruční pořadí článků. Editor článku pak nabídne zařazení jen do sérií cílového blogu. Čtenář na detailu článku uvidí blok `Tento článek je součástí série`, aktuální díl je označený pro asistivní technologie a veřejná stránka série má URL `/{blog-slug}/serie/{series-slug}`.

Delší blogové články dostanou na veřejném detailu automatickou osnovu `V tomto článku`, pokud obsah obsahuje alespoň dva viditelné nadpisy `h2` nebo `h3`. Kora CMS doplní stabilní kotvy k nadpisům, ručně zadaná `id` zachová a odkazy v osnově pomáhají čtenářům i čtečkám obrazovky rychle přeskakovat mezi částmi článku.

Kategorie a štítky blogu nejsou jen interní filtry. Správce k nim může vyplnit veřejný slug, popis, meta title a meta description. Veřejné stránky mají čisté adresy `/{blog-slug}/kategorie/{category-slug}` a `/{blog-slug}/stitky/{tag-slug}`, zobrazují popis nad výpisem článků a používají vlastní canonical/SEO metadata. Staré query odkazy `?kat=` a `?tag=` zůstávají kompatibilní.

Blog zároveň automaticky chrání staré veřejné adresy. Když se změní slug nebo blog publikovaného článku, slug kategorie, slug štítku nebo slug aktivní série, CMS uloží trvalé `301` přesměrování ze staré URL na nový canonical tvar přes běžnou správu přesměrování. Při smazání nebo převodu článku se redirecty mířící na zaniklou článkovou URL uklidí, aby nevznikaly slepé odkazy.

Statické stránky přiřazené k blogu používají adresu `/{blog-slug}/stranka/{page-slug}`, proto musí být slug stránky jedinečný jen v rámci daného blogu. Stejný slug lze použít v jiném blogu bez kolize; globální statické stránky mimo blog zůstávají unikátní mezi sebou.

Přehled blogů v administraci nově nabízí přímé odkazy na články, kategorie, štítky a stránky konkrétního blogu. Převodové akce `Článek → Stránka` a `Stránka → Článek` zároveň ponechávají šipku jen jako vizuální pomůcku; čtečky obrazovky teď hlásí jen samotný název akce bez dekorativní šipky.

Ruční i automaticky ukládané `301/302` redirecty ověřují starou adresu jako interní cestu webu a novou adresu jako interní cestu nebo čistou `http/https` URL bez přihlašovacích údajů. Nejednoznačné cíle, nebezpečná schémata a CRLF znaky se odmítnou ještě před odesláním hlavičky `Location`.

Externí URL zadávané v administraci pro widgety sociálních sítí, podcasty, zajímavá místa a položky ke stažení používají sdílenou validaci: běžné domény bez schématu se doplní na `https://`, ale interní cesty, protocol-relative adresy, řídicí znaky, nebezpečná schémata a URL s přihlašovacími údaji se odmítnou. Webhooky formulářů zůstávají přísnější: vyžadují explicitní veřejné `https://` URL a dál procházejí ochranou proti privátním nebo lokálním hostům.

Stejný bezpečnostní základ používá i web autora. Pole typu `URL` ve veřejných formulářích jsou záměrně přísnější: návštěvník musí zadat úplnou adresu začínající na `http://` nebo `https://`, ne interní cestu ani holou doménu, aby se do odpovědí nedostaly nejednoznačné nebo nebezpečné odkazy.

CMS automaticky generuje XML sitemapu (`sitemap.xml`) ze všech publikovaných veřejných stránek. Sitemapa je čistě čtecí endpoint a podporuje jen metody `GET` a `HEAD`.

Součástí veřejného provozu je také dynamický `robots.txt`, který zakazuje indexaci administrace a citlivých upload adresářů a odkazuje na XML sitemapu. Veřejné stránky, které předávají kanonickou URL do SEO metadat, zároveň generují `<link rel="canonical">`; canonical helper přijímá jen interní cesty nebo platné `http://` / `https://` adresy bez přihlašovacích údajů, řídicích znaků a protocol-relative tvaru.

---

## Šablony a vzhled

Součástí CMS jsou čtyři šablony: `default`, `civic`, `editorial` a `modern-service`.

V administraci lze:

- aktivovat šablonu nebo spustit živý náhled bez ostré aktivace
- upravit barvy, akcenty, typografii a šířku obsahu
- měnit variantu hlavičky
- importovat a exportovat portable ZIP balíčky šablon

Import ZIP balíčku šablony používá sdílenou upload validaci ještě před tím, než CMS balíček rozbalí a zkontroluje manifest, povolené statické soubory a limity velikosti.

Default šablona má vlastní theme view audit v `composer ci:basic`. Ten hlídá, aby PHP view soubory zůstaly čistou prezentační vrstvou bez přímé práce s request inputem, session/server stavem, databází, souborovými zápisy, runtime časem, inline styly, inline event handlery nebo skripty bez CSP nonce. Zároveň kontroluje statická duplicitní `id`, statické vazby `aria-labelledby` / `aria-describedby` / `aria-controls`, statické `label for`, formulářová pole bez labelu nebo ARIA názvu, pojmenování veřejných sekcí, landmarků, všech veřejných `<article>` prvků a `<figure>` bloků přes skutečný cílový prvek, přítomnost `figcaption`, `alt` u obrázků, `title` u iframe, explicitní `type` u tlačítek a `rel="noopener noreferrer"` i přístupný název oznamující nové okno u odkazů s `target="_blank"`, aby se do šablon nevrátily nefunkční popisky pro čtečky obrazovky, nechtěná implicitní formulářová tlačítka nebo rizikové odkazy. Společné hodnoty jako přihlášený administrátor, aktuální datum a aktuální URL šablona dostává přes view data z `renderPublicPage()`. Součástí CI je i self-test auditu nad dočasnými fixtures, takže se ověřuje i to, že guardrail opravdu umí selhat na zakázaných vzorech.

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

### Command centrum a osobní zkratky

Administrace má globální hledání dostupné v hlavním admin layoutu. Pole **Hledat v administraci** funguje i bez JavaScriptu jako běžná stránka výsledků, klávesová zkratka `Ctrl+K` otevře přístupnou command paletu. Výsledky zahrnují administrační obrazovky, bezpečné navigační rychlé akce a editovatelný obsah napříč zapnutými moduly. Každý výsledek respektuje roli, capability a stav modulu, takže uživatel vidí jen položky, ke kterým má přístup.

Vybrané položky lze připnout jako osobní zkratky. Zobrazí se na dashboardu v bloku **Moje zkratky** a ukládají se podle `item_type + item_key`, nikoli podle URL poslané z formuláře. CMS při připnutí položku znovu dohledá v interním registru a uloží jen platný interní admin cíl.

Rezervační administrace pro přehled, ruční vytvoření, detail rezervace i editor zdrojů používá sdílenou admin CSS vrstvu bez lokálních `<style>` bloků, `style` atributů nebo JS mutací `element.style`, takže drží stejnou CSP a údržbovou hygienu jako ostatní stabilizované administrační obrazovky.

---

## HTML editor a snippety

Kora CMS podporuje dva editory:

- **HTML textarea** – výchozí, přístupnější varianta
- **WYSIWYG (Quill)** – volitelný vizuální režim

Pro HTML obsah je k dispozici content picker – přístupný dialog pro vložení interních odkazů, galerií, médií, formulářů, anket, PDF náhledu z knihovny médií a hotových HTML bloků.

### Podporované snippety

| Snippet | Výstup |
|---|---|
| `[audio]https://example.test/audio.mp3[/audio]` | HTML5 audio přehrávač |
| `[video]https://example.test/video.mp4[/video]` | HTML5 video přehrávač pro přímý video soubor |
| `[video]https://www.youtube.com/watch?v=ID_VIDEA[/video]` | Vložený YouTube přehrávač s odkazem na samostatné otevření |
| `[pdf]https://example.test/dokument.pdf[/pdf]` | Náhled PDF s odkazem na samostatné otevření |
| `[code]echo "Ahoj";[/code]` | Kopírovatelný blok obsahu s tlačítkem `Kopírovat do schránky` |
| `[gallery]slug-alba[/gallery]` | Vložená galerie podle slugu |
| `[form]slug-formulare[/form]` | Živý embed veřejného formuláře |
| `[poll]slug-ankety[/poll]` | Živý embed veřejné ankety |
| `[download]slug-polozky[/download]` | Teaser karta položky ke stažení |
| `[podcast]slug-poradu[/podcast]` | Teaser karta podcastového pořadu |
| `[podcast_episode]slug-poradu/slug-epizody[/podcast_episode]` | Teaser karta epizody podcastu |
| `[place]slug-mista[/place]` | Teaser karta zajímavého místa |
| `[event]slug-udalosti[/event]` | Teaser karta události |
| `[board]slug-oznameni[/board]` | Teaser karta položky vývěsky |

Snippety fungují ve všech HTML polích, která CMS veřejně renderuje přes `renderContent()`. Formuláře a ankety se vkládají jako živé interaktivní embedy, PDF z knihovny médií se nově vykresluje přes interní same-origin preview endpoint a ostatní snippety jako sjednocené obsahové karty nebo kopírovatelné bloky obsahu. Tyto vložené bloky mají skrytý nadpis napojený přes `aria-labelledby`, takže jsou dohledatelné i navigací čtečkou obrazovky podle nadpisů bez vizuální změny obsahu. Video snippet podporuje přímé video soubory i běžné YouTube URL (`watch`, `youtu.be`, `shorts`, `embed`) a YouTube vkládá přes privacy-friendly `youtube-nocookie.com`. URL ve snippetech pro audio, video a PDF musí být buď úplná `http://` / `https://` adresa bez přihlašovacích údajů, nebo interní absolutní cesta začínající jedním lomítkem, například `/uploads/media/soubor.pdf`; protocol-relative adresy `//example.com/...` se odmítají. Při vložení obrázku přes content/media picker se do HTML zachová `alt` atribut, ale nevkládá se automatický `figcaption` z názvu média; když médium nemá vyplněný alternativní text, picker vloží `alt=""`, který lze v editoru ručně upravit. Externí iframe a externí audio/video embedy jsou na veřejném webu podporované přes CSP, pokud je cílový zdroj sám dovolí.

---

## Navigace webu

Kora CMS používá jedno rozhraní pro správu pořadí navigace. V administraci (**Navigace webu**) lze řadit:

- moduly
- blogy
- veřejné formuláře
- externí a interní odkazy
- statické stránky

Položky lze libovolně kombinovat – stránka může být mezi moduly, formulář vedle blogu a externí odkaz třeba mezi dvěma stránkami. U odkazu se nastavuje název, cílová adresa, volitelný popis pro čtečky obrazovky, který se ve veřejné navigaci přidá jako skrytý text za viditelný název odkazu, a bezpečné otevření v novém okně. Stejný princip platí i pro blogové stránky: každý blog může mít vlastní statické stránky a vlastní odkazy v samostatném pořadí nad výpisem článků.

Správa navigace u veřejných formulářů nově používá stejnou dostupnostní logiku jako samotný veřejný web. Pokud je formulář aktivní, zveřejněný a označený pro navigaci, admin už ho neoznačuje jako skrytý a hlavní navigace ho vykreslí stejným pravidlem jako ve veřejné části.

---

## Widgety

Homepage, sidebar i footer se skládají přes widgetový systém. V administraci lze přidávat widgety do tří zón, měnit jejich pořadí a nastavovat parametry.

Widgety pokrývají typické potřeby: úvodní text, nejnovější články, novinky, události, anketa, newsletter, ke stažení, FAQ, místa, podcasty, galerie, vybraný formulář, vyhledávání, kontaktní údaje, sociální sítě, statistiky návštěvnosti a vlastní HTML.

Administrace i veřejný widget používají u základních statistik stejné pořadí a popisky `Online / Dnes / Měsíc / Celkem`. Detailní administrace statistik navíc za zvolené období ukazuje nejčtenější statické stránky, včetně blogových stránek, externí odkazující stránky a blok `Výkon obsahu`. Ten používá dlouhodobé denní agregace bez IP hashů, user-agentů a raw referrerů, umí souhrn podle modulů, nejčtenější obsah, největší nárůsty proti předchozímu stejně dlouhému období, filtr podle modulu a bezpečný CSV export agregovaných výsledků. Kvůli soukromí se u referrerů ukládá jen schéma, host a cesta bez query stringu a fragmentu; interní přechody v rámci vlastního webu se do referrer přehledu nepočítají.

Widget `Náhled galerie` vykresluje poslední veřejné fotografie jako responzivní náhledový grid. Na homepage, v sidebaru i ve footeru používá stejné šablonové CSS třídy, takže výstup zůstává konzistentní a bez inline layout stylů.

Widget `Nejnovější články` zobrazuje u každého článku odkaz a pod ním metadata článku: datum a čas publikace, přibližnou dobu čtení a počet přečtení. Metadata se počítají ze stejného obsahu článku jako běžné veřejné výpisy, takže sidebar, footer i homepage widget dávají návštěvníkům konzistentní informaci.

Widget `Úvodní text` je nově jediný podporovaný způsob, jak spravovat hlavní úvodní blok domovské stránky. Samostatné pole v `Obecných nastaveních` už neexistuje. Intro widget umí HTML i běžné snippety z HTML editoru a pokud zůstane prázdný, na webu se vůbec nevykreslí. Na homepage má zároveň skrytý nadpis pro čtečky obrazovky, takže se dá najít i navigací po nadpisech bez změny vizuálního vzhledu.

Widgety respektují stav modulů i skutečnou dostupnost obsahu. Vypnutý modul se nenabízí a aktivní widget se na webu nevyrenderuje ani tehdy, když pro něj není obsah, je navázaný na neexistující formulář nebo má prázdnou konfiguraci. Správa widgetů na to nově umí přímo upozornit textem `Na webu se teď nezobrazí: ...` a používá sdílenou admin CSS vrstvu bez lokálního `<style>` bloku.

Také obrazovka **Obecná nastavení** používá sdílenou admin CSS vrstvu pro navigaci sekcí, profilové karty, vlastní kód a branding náhledy. Nemá tedy vlastní lokální `<style>` blok ani prezentační `style` atributy.

Footer už neobsahuje natvrdo zapsané odkazy na sociální sítě, vyhledávání ani odběr novinek. Sociální sítě se nově nastavují přímo v widgetu `Sociální sítě`, widget `Vyhledávání` vykresluje hledací pole a widget `Newsletter` zobrazuje bezpečný odkaz na samostatnou stránku odběru. Vyhledávací widget pojmenovává svůj `role="search"` formulář přes skrytý `legend`, takže search landmark zůstává dohledatelný i mimo okolní widgetovou sekci. Newsletter widget neodesílá e-mail přímo z každé stránky; návštěvníka vede na `/subscribe.php`, kde přihlášení chrání CSRF, honeypot, rate limiting a serverově ověřená captcha s přístupnými chybami u polí. Sociální odkazy otevírané v novém okně mají bezpečné `rel="noopener noreferrer"` a oznámení pro čtečky obrazovky jako skrytý text přímo uvnitř odkazu, ne jen v samostatném atributu.

Dialog `Nastavení` u widgetů nově používá skutečné skupiny polí přes `fieldset` a `legend`, navázané help texty a bezpečnější focus trap jen pro viditelné prvky. Stejný přístup používá i správa blogů, kde jsou create/edit formuláře rozdělené do sekcí `Základní údaje`, `Obsah a metadata` a `Logo a zobrazení`; dialog úprav blogu, náhled loga a modální stav se stylují přes sdílenou admin vrstvu bez lokálního `<style>` bloku.

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

Přihlášení do administrace, veřejné přihlášení i obnovení hesla používají kombinovaný rate limiting. Systém hlídá počet pokusů podle IP adresy a zároveň podle hashovaného účtu nebo tokenu. Do databáze se neukládá e-mail ani token v čitelné podobě, pouze odvozený SHA-256 klíč. Veřejná registrace a žádost o obnovu hesla nevyžadují matematickou CAPTCHA; proti automatizovaným pokusům zůstávají chráněné přes CSRF, rate limit a honeypot. Auth formuláře zároveň používají `autocomplete="username"`, `current-password`, `new-password` a pro 2FA `one-time-code` s numeric patternem; chybové stavy standalone admin loginu a 2FA jsou text-backed alerty pro čtečky obrazovky. Výchozí 429 odpověď posílá `Retry-After`, explicitní HTML typ a necacheovací/noindex/no-referrer hlavičky, aby ji prohlížeč ani mezilehlá cache neukládaly a klient věděl, kdy má požadavek zkusit znovu. HTML odpověď má skutečný nadpis a kód požadavku pro podporu, stejně jako globální chybová stránka.

Veřejný kontakt, food objednávkové poptávky, guest rezervace a Form Builder formuláře poskytují `autocomplete` metadata pro běžná osobní pole. Form Builder používá sdílený helper pro `email`, `tel`, `url`, zjevné jmenné textové pole a organizaci; runtime audit a HTTP integrace hlídají, aby se tato input-purpose metadata z veřejných formulářů neztratila.

Veřejné formuláře, které stále používají matematickou ověřovací otázku proti spamu, zobrazují u chybné odpovědi field-level text s konkrétní opravou: přepočítat příklad a zadat jen číslo. Runtime audit i HTTP integrace hlídají kontakt, newsletter subscribe, odběr vývěsky, Food objednávky a Form Builder.

Administrace postupně zpřesňuje validační texty tak, aby vedle identifikace chyby nabízely i opravu. URL pole u zajímavých míst a podcastů například vysvětlují, že lze zadat `http://`/`https://` adresu nebo doménu bez schématu, kterou CMS uloží jako `https://`, případně volitelné pole nechat prázdné.

E-mailová pole ve vývěsce, událostech, jídelních lístcích, Form Builderu, tématech kontaktu, místech, podcastech, nastavení, profilu a správě uživatelů radí zadat úplnou adresu ve tvaru `jmeno@example.cz`; u volitelných polí připomínají možnost nechat pole prázdné a u přihlašovacího e-mailu jedinečnost adresy.

Stejný princip platí pro plánování publikace a časové rozsahy. U článků, stránek, novinek, událostí, anket, rezervačních zdrojů a podcastových epizod chybové hlášky vysvětlují, že má správce vybrat platnou hodnotu v poli datum/čas, volitelné plánování nechat prázdné, odstranit prázdný řádek nebo opravit pořadí začátku a konce.

Návrat po přihlášení do administrace používá bezpečný redirect jen pro interní administrační cíle a potvrzení migrace. Veřejné interní cesty, externí URL, protocol-relative URL i pokusy o smyčku zpět na login nebo 2FA se zahodí a použije se dashboard administrace. Unit testy i runtime audit hlídají, aby se z tohoto helperu nestal otevřený redirect.

Technické recoverable chyby se postupně zapisují přes strukturovaný `koraLog()` formát s `request_id`, metodou a cestou. Globální neošetřené chyby ukládají jen název souboru a hash cesty, ne plnou lokální cestu; chybová stránka návštěvníkovi ukáže bezpečný kód požadavku pro podporu a odpověď je necacheovatelná. Administrační ukládání článků, přesun článků mezi blogy, ukládání anket, cleanup šablon, mazání prezentačních souborů, hromadné mazání blogových článků a galerie, import fotek z eStránek i dílčí selhání přehledů na dashboardu už nepoužívají surové `error_log()` zprávy bez kontextu nebo s plnými lokálními cestami. Dashboard u počítadel loguje jen omezený kontext, například sekci a krátký hash dotazu, nikoli celý SQL text.

V nastavení webu už nové uploady loga a favicony nepřijímají SVG. Backend současně hlídá i velikost branding souborů a používá sdílenou upload validaci, takže se do veřejně servírovaných assetů nedostane aktivní obsah ani přehnaně velké soubory.

Náhledové obrázky článků, přílohy vývěsky, lokální soubory ke stažení a fotografie galerie používají stejnou sdílenou upload validaci pro stav PHP uploadu, MIME typ nebo bezpečnou příponu a finální uložení. Příprava cílového adresáře, nahrazení existujícího souboru i finální přesun uploadu se logují strukturovaně bez fyzických cest, takže se hostingové chyby dají dohledat podle `request_id`. Při výměně nebo odebrání obrázku se uklízí i staré miniatury, WebP a responsive varianty, aby se ve veřejných upload adresářích nehromadily nepoužívané soubory. Apache konfigurace v `uploads/.htaccess` navíc blokuje běžné skriptové přípony v upload adresáři, například `.php`, `.phtml`, `.phar`, `.cgi`, `.pl`, `.py`, `.rb`, `.sh`, `.asp`, `.aspx` a `.jsp`; release ZIP obsahuje jen tento ochranný soubor, ne lokální uživatelská média.

Veřejná default šablona nepoužívá pro potvrzení akcí a tisk inline `onclick` handlery. Potvrzení běží přes `data-confirm` a tisk přes `js-print-page` v nonce skriptu layoutu, což snižuje závislost na CSP fallbacku `unsafe-inline`.

V administraci používají jednoduché potvrzovací formuláře stejný `data-confirm` vzor i pro událost `submit`, takže potvrzení funguje také při odeslání klávesnicí a není vázané na inline `onsubmit` JavaScript.

Dlouho běžící administrační formuláře, například import z WordPressu nebo eStránek, používají `data-submit-once`. Sdílený nonce skript po odeslání změní text tlačítka a zablokuje opakované kliknutí bez inline `onclick` handleru.

XML/WXR soubory pro import z WordPressu a eStránek používají stejnou sdílenou upload validaci jako ostatní citlivější nahrávání. CMS ověří stav PHP uploadu, dočasný soubor a prázdný soubor dřív, než ho předá parseru; WordPress náhled se navíc ukládá do `uploads/tmp` přes bezpečný upload helper. Základní URL pro stahování fotografií z eStránek prochází stejným http/https normalizátorem jako ostatní externí adresy, takže nepřijme protocol-relative URL, přihlašovací údaje ani nebezpečná schémata.

Tokenové odkazy, které mění stav přes tajný `GET` odkaz, jsou metodově omezené. Potvrzení e-mailu, potvrzení nebo odhlášení newsletteru a veřejné i administrační odhlášení odmítají `POST`, `HEAD` a další nečekané metody pomocí `405` a `Allow: GET`, takže kontrolní nebo chybné HTTP požadavky nemají měnit účet, odběr ani session.

Editor anket v administraci používá pro přidávání a odebírání možností odpovědi datové atributy a delegovaný listener v nonce skriptu formuláře, ne inline `onclick` handlery.

Rezervační formuláře v administraci používají stejný princip pro přepínání typu zákazníka, práci se sloty a blokovanými dny. Runtime audit navíc hlídá, aby se do admin PHP souborů nevracely inline `onclick`, `onchange`, `onsubmit` ani `oninput` atributy.

Editor formulářů v administraci používá sdílené admin CSS třídy pro základní nastavení formuláře, potvrzovací e-mail, webhooky, editor polí i náhled potvrzení. Form builder tak zůstává bez lokálních `style` atributů a runtime audit hlídá, aby se tento vzor nevrátil.

Administrátorský dashboard používá sdílené panely, karty a sémantický `<progress>` pro mini graf návštěvnosti místo lokálních `style` atributů. Runtime audit tím drží i hlavní přehled administrace v linii postupného zpřísňování CSP.

Společný layout administrace načítá základní administrační styly ze statického a verzovaného `admin/assets/layout.css?v=...` místo generovaného inline `<style>` bloku v `admin/layout.php`. Tím se další velká část administrace posouvá k přísnější CSP bez ztráty sdílených utility tříd a změny přístupnostních CSS se po nasazení neblokují starou cache prohlížeče.

Přihlašovací obrazovky administrace včetně 2FA načítají sdílený statický stylesheet `admin/assets/login.css` místo generovaného inline `<style>` helperu. Tím zůstává skip link, viditelný focus i TOTP pole konzistentní bez dalšího inline stylového driftu.

Kontrastní baseline pro výchozí šablonu, administrační layout a přihlašovací obrazovky je hlídaný přes runtime sekci `contrast_focus_guardrails`. Ta měří textové páry, stavové hlášky, skip link, focus tokeny a hranice inputů/tlačítek; custom theme, hover/disabled a ikonové stavy zůstávají předmětem ručního testovacího protokolu.

Text spacing baseline hlídá runtime sekce `text_spacing_guardrails`. Core CSS nesmí používat záporné `letter-spacing`, textový ořez přes `text-overflow: ellipsis` nebo line clamp ani `!important` zámky na text-spacing vlastnostech; administrační SEO preview dlouhé titulky a popisy zalamuje místo ořezu.

Mobilní baseline administrace hlídá runtime sekce `admin_mobile_reflow_guardrails`. Sdílený admin stylesheet na malé šířce skládá navigaci nad obsah, zmenšuje padding hlavní části, nechává datové tabulky scrollovat lokálně přes `.table-responsive`, skládá media/Form Builder/statistics gridy do jednoho sloupce, zabraňuje min-content roztažení dlouhých fieldsetů a dorovnává malé řadicí ovladače, sekundární action odkazy i přímé akční odkazy v odstavcích na minimální target size. Browser ověření při 320 px pokrývá media, widgets, statistics, Form Builder, přehled formulářů, comments, contact, chat, reservations, food, downloads, gallery, importy, content picker, reprezentativní dlouhé formuláře a podcastové přehledy; tabulkové moduly mají wrappery tak, aby neroztahovaly celý viewport.

Společný layout administrace pojmenovává hlavní administrační navigaci skutečným nadpisem `Administrace` přes `aria-labelledby`. Serverové stavové a chybové hlášky si zároveň ponechávají vlastní `role` a skrytý live region slouží jen pro doplňková klientská oznámení.

Veřejný admin bar a vybrané pomocné navigace v administraci, například filtry komentářů, kontaktních a chatových zpráv, odpovědí formulářů, newsletteru, fronty ke schválení, nastavení webu a stránkování rezervací, používají skutečné skryté nadpisy přes `aria-labelledby`. Čtečky obrazovky je tak najdou jako landmarky i při navigaci po nadpisech, bez vizuální změny rozhraní.

Stejný vzor používají i vybrané skupinové prvky mimo navigaci: jídelní taby, rezervační časové sloty, výsledky anket, veřejný chat a souhrny návštěvnosti jsou pojmenované přes `aria-labelledby` napojené na skutečný nadpis nebo legendu.

Stabilizované administrační odkazy otevírané v novém okně nepřepisují viditelný název přes `aria-label`; informaci o novém okně vkládají jako skrytý text přímo do odkazu, aby čtečky obrazovky i vizuální rozhraní pracovaly se stejným názvem.

Živý náhled šablony a veřejný rezervační kalendář také používají skutečný textový název: preview banner je napojený na vlastní text v banneru a kalendářová tabulka má skrytý `<caption>`. Stavová potvrzení ve veřejné autentizační, účtové, newsletterové, formulářové, komentářové, anketní a rezervační části používají `role="status"` nebo `role="alert"` s vlastním textovým uzlem přes `aria-labelledby`, aby potvrzení, chyby a informační stavy nebyly pro čtečky obrazovky anonymní.

Veřejné chybové alerty u přihlášení, registrace, obnovy hesla, profilu, komentářů, chatu, kontaktu a Form Builderu mají skrytý textový nadpis přímo uvnitř hlášky. U vložitelných formulářů se ID hlášky odvozuje od konkrétního formuláře, aby se při více embedech na jedné stránce neduplikovalo.

Sdílené veřejné stavové stránky, například potvrzení e-mailu nebo newsletterové potvrzení a odhlášení, používají stejný princip: pokud mají být oznámeny čtečce obrazovky, stavová zpráva má `aria-atomic="true"` a `aria-labelledby` napojené na první textový odstavec zprávy.

Content/media picker v administraci načítá sdílený statický stylesheet `admin/assets/content-reference-picker.css` pro styly dialogu, překryvu, toolbaru a výsledků. Samotný picker tak zůstává bez lokálního i generovaného `<style>` bloku a runtime audit hlídá, aby se do něj nevracely prezentační inline styly ani JS mutace `element.style`.

Editor článku blogu používá sdílené admin CSS třídy pro zámek obsahu, taxonomie při změně blogu, plánování publikace, SEO, interní poznámku, stav článku, akční řádek i WYSIWYG wrapper. Díky tomu už samotný formulář článku nepotřebuje lokální `style` atributy ani JS mutace `element.style`.

Kontrola integrity a detail odpovědi formuláře v administraci používají sdílené administrační CSS třídy místo vlastních lokálních `<style>` bloků. Runtime audit hlídá, aby se tyto čistě prezentační styly nevracely přímo do jednotlivých obrazovek.

Veřejná hlavička šablony přidává CSP nonce i k dynamickému `<style>` bloku s theme CSS proměnnými. Díky tomu se vlastní barevné nastavení šablon drží stejného bezpečnostního režimu jako ostatní interní inline styly.

Veřejné šablony už pro skip link, `.sr-only`, cookie lištu a veřejný admin bar nepotřebují generovaný inline a11y `<style>` helper; tyto styly se načítají ze sdíleného `themes/default/assets/public-core.css` před CSS aktivní šablony. Samostatné systémové obrazovky `install.php`, `migrate.php` a `maintenance.php` jsou nezávislé na šabloně, ale používají společný statický stylesheet `assets/standalone.css` místo lokálních inline `<style>` bloků.

Nouzová chybová stránka používá `assets/error.css` a antispamový honeypot sdílenou třídu `.honeypot-field`, takže ani tyto pomocné výstupy nepotřebují lokální inline styly.

Veřejná JSON-LD strukturovaná data se vykreslují přes sdílený helper s CSP nonce, takže SEO metadata pro místa, podcasty, jídelní lístky, galerii, novinky, události a FAQ nejsou závislá na inline-script fallbacku.

CSP allowlist obsahuje také explicitní zdroje, které CMS samo vkládá pro Google Analytics a volitelný Quill editor. Externí GA/Quill skripty se renderují s nonce a runtime audit hlídá, aby se nové CDN skripty nepřidávaly bez stejné ochrany.

---

## Přístupnost

Projekt cílí na **WCAG 2.2 Level AA**:

- skip link na obsah
- viditelný focus stav
- sémantické HTML
- formuláře přes `label`, `fieldset`, `legend`
- vyhledávací, filtrační, drobečkové, stránkovací, obsahové embed bloky a další pomocné navigační landmarky pojmenované skutečnými nadpisy přes `aria-labelledby`
- helper texty přes `aria-describedby`
- přístupné dialogy s návratem fokusu
- měřený kontrast textu, focusu, skip linku a hranic ovládacích prvků ve výchozí, administrační a přihlašovací vrstvě
- mobilní baseline administrace pro stackovanou navigaci, scrollovatelné datové tabulky, jednosloupcové komplexní gridy a ovladatelné action rows
- klávesnicová ovladatelnost i tam, kde je drag & drop
- průběžný audit přes `build/runtime_audit.php`

Pro dlouhodobé vyhodnocování vznikla sada dokumentů v [docs/accessibility/](docs/accessibility/): WCAG 2.2 AA matice, VPAT/ACR draft, backlog oprav a ruční testovací protokol. Report hodnotí Kora CMS jako produkt a odděluje odpovědnost CMS od ručně vloženého obsahu autora, například titulků médií, alt textů nebo vlastního HTML v editoru. Stejné dokumenty jsou povinný vstup i pro návrh nových modulů: nový modul má předem určit, zda mění některý předpoklad conformance reportu, zda vyžaduje ruční testovací scénář a zda je potřeba aktualizovat backlog.

---

## Zálohování a údržba

### Automatické zálohy

Cron každý den vytvoří SQL zálohu databáze do privátního úložiště (`../kora_storage/backups/`). Zálohy se uchovávají 7 dní. Ruční export v administraci i automatická cron záloha používají stejný SQL dump helper, který povoluje jen CMS tabulky s bezpečným názvem a exportuje řádky explicitně jako asociativní data.

### Ruční záloha

V administraci: **Import / Export → Záloha databáze**. Stáhne aktuální SQL export.

Automatické i ruční SQL zálohy validují názvy tabulek a sloupců přes allowlist identifikátorů. Exportují se jen očekávané tabulky CMS s názvem `cms_*`.

### JSON export a import

V administraci: **Import / Export** lze obsah exportovat i znovu importovat jako JSON.

- JSON export z Kora CMS používá UTF-8 a zachovává českou diakritiku bez escapování do `\u` sekvencí
- JSON import nově odmítne soubor s neplatným UTF-8, aby se texty neuložily poškozeně
- pokud obnovujete ruční SQL dump mimo administraci, používejte při importu klientské spojení `utf8mb4`; špatný charset může změnit české znaky na `?`

### Kontrola integrity

V administraci: **Integrita souborů**. Porovná SHA-256 otisky PHP souborů s uloženým snímkem.

### Režim údržby

V administraci: **Obecná nastavení → Provoz webu**. Zapne stránku údržby s HTTP 503 pro návštěvníky. Přihlášení administrátoři vidí web normálně.

### Health check

JSON provozní endpointy pro monitoring a CSP reporty posílají vedle `Content-Type: application/json` také `X-Content-Type-Options: nosniff`, aby prohlížeč ani mezilehlá vrstva nehádaly jiný typ obsahu. Stejně jako citlivé veřejné tokenové akce jsou necacheované, neindexované přes `X-Robots-Tag: noindex, nofollow, noarchive` a posílají `Referrer-Policy: no-referrer`, aby monitoring a CSP sběr zbytečně neputovaly přes cache, index nebo referrer. Tyto provozní JSON hlavičky i odmítnutí nepovolených metod skládají sdílené helpery, aby se `health.php` a `csp-report.php` nerozjely do dvou mírně odlišných variant a každá `405` odpověď zůstala dohledatelná přes `request_id`.

Endpoint `health.php` vrací minimální JSON stav instalace pro monitoring:

- databázové připojení
- zapisovatelnost privátního úložiště
- orientační stav a čas poslední SQL zálohy
- orientační čerstvost posledního běhu cronu

Endpoint podporuje metody `GET` a `HEAD`, nezobrazuje cesty, hesla ani detailní chyby. Při jiné metodě vrací sdílenou JSON `405` odpověď s `Allow: GET, HEAD`, bezpečnostními/no-store hlavičkami a `request_id`. Při zdravé instalaci vrací HTTP 200, při selhání kritické kontroly HTTP 503. Stav cronu je informační: čerstvá instalace bez prvního běhu cronu zůstane `unknown`, pozdější běh uloží `cron_last_run_at` a health check ho označí jako `ok` nebo `stale`. Odpověď se posílá s `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow, noarchive` a `Referrer-Policy: no-referrer`, aby monitoring nedostal zastaralý stav z cache a provozní URL se neposílala dál.

Každý HTTP request zároveň dostává hlavičku `X-Request-ID`. Pokud proxy nebo hosting pošle vlastní bezpečné `X-Request-ID`, Kora CMS ho převezme; jinak vytvoří nové náhodné ID. Stejné ID se zapisuje i do strukturovaných JSON záznamů technických chyb, takže lze konkrétní problém spárovat mezi odpovědí, PHP logem a monitoringem. U neošetřené chyby se stejný kód zobrazí i na chybové stránce. Strukturovaně se logují i dílčí obnovitelné chyby veřejného blogu, detailu článku, vyhledávání, sitemapy, veřejných formulářů, chatu, kontaktu, stažení souboru a newsletterových potvrzovacích akcí, kde má stránka pokračovat ve vykreslení, ale provozní log musí jasně ukázat selhaný zdroj. Stejný zápis používají i vybrané administrační přehledy, například vyhledávání obsahu pro media picker, formuláře a statistiky, bez ukládání hledaného textu nebo obsahu zpráv do kontextu logu. Strukturované logování mají i sdílené helpery pro zámky obsahu, revize, widgety, použití médií, formulářové webhooky, e-mailové notifikace a souborové operace včetně uploadů, přesunů, úklidu knihovny médií a cron cleanupu starých temp souborů, CSP reportů nebo záloh; do logu ukládají jen technický kontext typu operace, entity, zóny, interní tabulky, webhook eventu, hostu endpointu, HTTP stavu, domény příjemce, SMTP fáze, hashe cesty nebo přípony souboru, ne celé webhook URL, tělo odpovědi protistrany, celou e-mailovou adresu, surovou SMTP odpověď ani fyzickou cestu k souboru.

Session vrstva používá cookies-only režim, strict mode a vypnuté session ID v URL. Přihlašovací flow zároveň po úspěšném přihlášení regeneruje session ID, cookie má `HttpOnly` a `SameSite=Strict` a odhlášení ji maže se stejným cookie kontextem, aby se snížilo riziko session fixation a úniku session přes odkazy nebo referrery.

Běžné administrační HTML odpovědi včetně loginu, 2FA a potvrzení migrace posílají `Cache-Control: no-store, max-age=0`, `Pragma: no-cache`, `Expires: 0`, `X-Robots-Tag: noindex, nofollow, noarchive` a `Referrer-Policy: no-referrer`. Administrace se tak zbytečně neuchovává v prohlížeči nebo mezicache, nemá se indexovat a adresa administrační stránky se neposílá dál jako HTTP referer, zatímco veřejné sociální náhledy mají dál vlastní krátce cacheovatelnou výjimku.

Citlivé veřejné tokenové a odhlašovací endpointy, například potvrzení e-mailu, potvrzení nebo odhlášení newsletteru, reset hesla, zrušení rezervace přes e-mailový token a veřejné odhlášení, používají stejné `no-store`, `noindex` a `Referrer-Policy: no-referrer` hlavičky. Pokud na ně omylem přijde sociální crawler, cache výjimka pro sdílené články se nepoužije. Běžné sociální náhledy naopak dostávají krátce cacheovatelnou odpověď s `Vary: User-Agent`, aby sdílená cache nemíchala crawler variantu s běžným návštěvníkem. Rezervační token se zároveň nepropíše do SEO metadat a tokenová adresa se neposílá dál jako HTTP referer. Nepovolené metody u těchto citlivých endpointů procházejí sdíleným `requireHttpMethods()` a vrací jednotnou `405` odpověď s přesným `Allow`, `Cache-Control: no-store, max-age=0`, `X-Robots-Tag`, `Referrer-Policy`, `X-Content-Type-Options: nosniff` a textovým typem odpovědi.
Stejnou necacheovatelnou ochranu používá také historický endpoint newsletter widgetu. Ten kvůli kompatibilitě zůstává dostupný, ale už neukládá odběratele ani neposílá potvrzovací e-mail; pouze přesměruje na zabezpečenou stránku odběru s captchou.

Odhlášení navíc posílá `Clear-Site-Data: "cache"`, aby prohlížeč zahodil cache webu po ukončení session. CMS záměrně nepoužívá agresivnější varianty pro cookies nebo storage, protože session cookie se maže cíleně a lokální autosave koncepty v administraci ani cookie preference nemají mizet překvapivě.

Veřejné i administrační odpovědi posílají také bezpečnostní hlavičky `Permissions-Policy`, `Cross-Origin-Opener-Policy: same-origin`, `Origin-Agent-Cluster: ?1`, `X-XSS-Protection: 0`, `X-Download-Options: noopen` a `X-Permitted-Cross-Domain-Policies: none`. CMS tím explicitně zakazuje prohlížečové schopnosti, které nepoužívá, například kameru, mikrofon, geolokaci, platební API, USB nebo browsing topics, izoluje top-level okna i runtime podle originu, vypíná zastaralý XSS auditor ve starších prohlížečích, omezuje otevírání stažených souborů v kontextu webu a odmítá staré cross-domain policy soubory. Záměrně neblokuje clipboard ani fullscreen, aby zůstala funkční kopírovací tlačítka a legitimní embedy; nezavádí ani COEP/CORP, aby nerozbíjel legitimní externí iframe, audio/video a sociální náhledy.

Veřejné odpovědi posílají také `Content-Security-Policy-Report-Only` s interním endpointem `csp-report.php`. Prohlížeče na něj mohou posílat porušení CSP bez blokování běžného provozu; CMS ukládá jen očištěné JSONL záznamy do privátního úložiště `logs/csp_reports-YYYY-MM-DD.jsonl`. Běžné inline styly, které historická administrace i některé helpery zatím používají, jsou v CSP výslovně povolené přes `style-src-elem` a `style-src-attr`; pokud dorazí starší inline-style report, endpoint ho potichu přijme, ale nezapíše ho do JSONL, aby logy neplnil očekávaný šum. Endpoint přijímá jen `POST`, nepovolené metody odmítá sdílenou JSON `405` odpovědí s `Allow: POST`, chybové JSON odpovědi doplňuje o `request_id`, neposílá cacheovatelný obsah a má vlastní rate limit; při překročení vrací stručnou JSON odpověď `rate_limited`. Cron zároveň maže CSP report soubory starší než 30 dní, aby se privátní logy nehromadily donekonečna.

Soubor `robots.txt` je generovaný přes `robots.php`, podporuje jen `GET` a `HEAD`, zakazuje indexaci administrace a citlivých upload adresářů a odkazuje na aktuální sitemapu. Stejné čtecí omezení metod používají také XML sitemapa, globální, blogové i podcastové RSS feedy, ICS export událostí, veřejné souborové/media endpointy a read-only administrační endpointy včetně JSON/CSV výstupů, příloh formulářů a vyhledávání obsahu pro media picker. Discovery výstupy jako `robots.txt`, sitemapa, RSS feedy a ICS posílají `Content-Type`, `X-Content-Type-Options: nosniff`, případný `Content-Disposition` a `HEAD` odpovědi přes sdílený helper, aby je prohlížeč neinterpretoval mimo deklarovaný textový, XML, RSS nebo kalendářový typ. Statické inline soubory, například thumby, galerie, místa a podcastové obrázky nebo obaly, používají sdílený souborový helper pro MIME typ, cache, `Content-Disposition` s ASCII fallbackem i UTF-8 `filename*`, `ETag`, `Last-Modified`, podmíněné `304 Not Modified`, `nosniff` a `HEAD` odpovědi; chráněné souborové downloady i PDF preview posílají stejný formát názvu souboru a `nosniff` přes download helper. Podcastové audio zůstává specializované kvůli podpoře `Range`, ale používá stejný UTF-8 `Content-Disposition`, `ETag`, `Last-Modified`, `nosniff`, `HEAD` odpovědi a pro nerange veřejné požadavky také podmíněné `304 Not Modified`. U souborů vrací `HEAD` jen hlavičky, bez přenosu těla souboru. Nepodporované metody pro tyto read-only endpointy procházejí stejným `requireHttpMethods()` jako citlivé tokenové akce a vrací jednotnou `405` odpověď s `Allow: GET, HEAD`, `Cache-Control: no-store, max-age=0`, `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff` a textovým typem odpovědi.

Pokud read-only discovery endpoint nemůže vrátit požadovaný výstup, například chybějící blogový RSS feed, podcastový RSS feed nebo ICS export události, používá sdílený `sendReadOnlyNotFoundResponse()`. Odpověď zůstává textová, necacheovaná, neindexovaná, neposílá referrer, má `nosniff` a u `HEAD` vrací jen hlavičky.

Chybějící nebo nepřístupné soubory z veřejných souborových a media endpointů vrací textovou `404` odpověď se stejnými `no-store`, `noindex`, `no-referrer` a `nosniff` hlavičkami, takže se podvržené nebo soukromé souborové URL nemají ukládat v cache ani indexovat.

Chybějící nebo nepřístupné přílohy formulářových odpovědí v administraci používají stejný bezpečný souborový fallback: odpověď zůstává textová, necacheovatelná, neindexovaná, neposílá referrer, má `nosniff` a u `HEAD` vrací jen hlavičky. Samotné stažení přílohy sdílí UTF-8 `Content-Disposition` helper s ostatními downloady, takže české názvy souborů zůstávají čitelné i v administračních exportech.

Běžné veřejné HTML 404 stránky pro chybějící obsah používají sdílený `renderPublicNotFoundPage()`. Díky tomu mají detailové moduly jednotné `Content-Type: text/html; charset=UTF-8`, `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff` a přístupný nadpis z veřejné `not-found` šablony.

Interní administrační JSON akce, které mění stav přes AJAX, jsou POST-only. Při jiné metodě procházejí sdíleným `requireJsonHttpMethods()` a vrací `405` s `Allow: POST`, JSON tělem, `request_id` a stejnými `no-store`, `noindex`, `no-referrer` a `nosniff` hlavičkami jako běžné administrační JSON odpovědi. Vlastní JSON odpovědi provozních a administračních endpointů používají sdílený `sendJsonResponse()`, takže status code, `request_id`, UTF-8 bezpečné kódování a ukončení odpovědi zůstávají konzistentní. Prohlížeč ani mezilehlá cache tak nepracují se zastaralým stavem, administrační JSON odpovědi nejsou indexované nebo referrerem propisované dál a chyba v administraci jde spárovat s technickým logem.
Read-only JSON endpoint pro vyhledávání obsahu v media pickeru používá stejný administrační JSON helper a vrací stejné diagnostické `request_id` i u prázdných výsledků, takže lze dohledat konkrétní hledání bez ukládání hledaného textu do logového kontextu.

Administrační stažení citlivějších exportů, například JSON export CMS, CSV export odpovědí formulářů, přílohy formulářových odpovědí, SQL záloha databáze, ZIP export galerie nebo ZIP export šablony, posílají přes sdílený attachment/download helper `Cache-Control: no-store, max-age=0`, `Pragma: no-cache`, `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff` a jednotný `Content-Disposition` s ASCII fallbackem i UTF-8 `filename*`. Exporty se tím zbytečně neukládají v mezicache, neindexují se, neposílají administrační URL jako referrer, české názvy souborů zůstávají čitelné a prohlížeč je nemá interpretovat jako jiný typ obsahu.

---

## Vývoj a CI

Produkční běh Kora CMS zůstává bez Composer závislostí. Composer je použitý pouze pro vývojové nástroje v `require-dev`.
Release ZIP se vytváří bez adresářů `vendor/`, `node_modules/`, lokálních AI/editor metadat typu `.codex/`, `.cursor/`, `.claude/` a bez vývojových metadata souborů jako `composer.json`, `composer.lock`, `phpstan.neon.dist` nebo `.php-cs-fixer.dist.php`; pokud je v lokálním checkoutu máte po `composer install` nebo při práci v editoru, slouží jen pro lokální vývoj a CI. Instalační ZIP i source archive zároveň povinně obsahují root `.htaccess`, `README.md`, `CHANGELOG.md`, `VERSION`, `config.sample.php`, `install.php`, `migrate.php`, `docs/admin-guide.md`, základní default šablonu, kritické CSS assety a ochranný `uploads/.htaccess` se stabilním casingem názvů. Samotné balení ZIPu používá explicitní `ZipArchive` průchod soubory včetně dotfiles, aby ochranný `.htaccess` nevypadl z artefaktu na Linux runneru. Release skript před vytvořením verze spouští statický release package audit i `composer ci:basic`, volitelně přes `-FullCi` také `composer ci:full`, aby se pravidla balíčku, `.gitignore`/`.gitattributes` ochrana lokálních artefaktů ani quality gate nerozbily potichu, a k ZIPu generuje také `.sha256` checksum. Součástí základní CI je i self-test release package auditu, který nad dočasnou kopií release guardů ověřuje, že audit opravdu selže na návratu `vendor`, lokálních AI metadat, `node_modules`, `Compress-Archive`, rozbitých release smoke kontrolách a chybějících pravidlech `.gitattributes` nebo `.gitignore`. Přepínač `-DryRun` projde stejný preflight a vytvoří ZIP se checksumem a náhledem nové verze, ale nemění pracovní `VERSION` ani `CHANGELOG.md`, nevytváří commit/tag/push a nezakládá GitHub release; náhled changelogu stejně jako ostrý release zachová nahoře novou prázdnou sekci `Unreleased` pro další vývoj.
Soubor `VERSION` je jediný zdroj pravdy pro runtime hodnotu `KORA_VERSION` i pro release balíčky. Základní CI to hlídá přes `build/version_metadata_audit.php`, aby se lokální verze, dry-run ZIP a source archive nerozjely do různých hodnot.

Základní lokální kontrola:

```bash
composer install
composer ci:basic
```

`composer ci:basic` spustí:

- PHP lint přes `build/lint_php.php` a jeho self-test `build/lint_php_selftest.php`, který ověřuje zachycení syntakticky rozbitého PHP souboru i ignorování `vendor`, `dist` a `uploads`
- HTTP server router self-test přes `build/http_server_router_selftest.php`, který v dočasné mini-instalaci ověřuje clean URL routování pro Full CI, statické soubory, chráněné cesty, query parametry a 404 fallback
- HTTP test helpery přes `build/http_test_helpers_selftest.php`, který nad dočasným PHP serverem ověřuje GET/POST/raw/multipart požadavky, redirecty, cookies, parser skrytých polí a refresh testovací CSRF session
- self-test DB audit locku přes `build/test_run_lock_selftest.php`, který ve dvou procesech ověřuje, že druhý audit čeká na uvolnění sdíleného file locku místo souběžného zápisu do lokálních testovacích nastavení
- repository guardrails audit přes `build/repository_guardrails_audit.php` a jeho self-test `build/repository_guardrails_audit_selftest.php`, které hlídají rezervované DB připojovací proměnné v souborech načítajících `db.php` nebo `config.php` a zároveň blokují nechtěně verzované lokální konfigurace, `.env` soubory, `vendor`, `node_modules`, `dist`, IDE/AI metadata a uživatelské uploady mimo ochranné `.htaccess` soubory
- config sample audit přes `build/config_sample_audit.php` a jeho self-test `build/config_sample_audit_selftest.php`, které hlídají, že `config.sample.php` zůstává sladěný s hlavní runtime konfigurací a instalačními komentáři
- version metadata audit přes `build/version_metadata_audit.php` a jeho self-test `build/version_metadata_audit_selftest.php`, které hlídají platný SemVer v `VERSION`, načítání `KORA_VERSION` z tohoto souboru a release dry-run práci s verzí v ZIP/source archive
- schema parity audit přes `build/schema_parity_audit.php` a jeho self-test `build/schema_parity_audit_selftest.php`, které hlídají kritické sloupce používané veřejnými endpointy proti driftu mezi `install.php`, `migrate.php` a aktuálním kódem
- redirect guardrails audit přes `build/redirect_guardrails_audit.php` a jeho self-test `build/redirect_guardrails_audit_selftest.php`, které hlídají, že requestem nebo formulářem dodané návratové cíle typu `redirect`, `redirect_target`, `next` a `return_url` zůstávají validované přes sdílený bezpečný helper
- audit GitHub Actions workflow přes `build/workflow_audit.php` a jeho self-test `build/workflow_audit_selftest.php`, které hlídají základní a plný CI běh včetně oprávnění, timeoutů, souběhu, připnutých actions, zakázaných write/secrets vzorů a runtime bootstrapu pro HTTP kontroly
- source encoding audit přes `build/source_encoding_audit.php` a jeho self-test `build/source_encoding_audit_selftest.php`, které hlídají platné UTF-8 ve verzovaných textových zdrojích a nepovolený UTF-8 BOM
- mojibake audit přes `build/mojibake_audit.php` a jeho self-test `build/mojibake_audit_selftest.php`, které hlídají typické zkomolené UTF-8 sekvence v českých textech a povolují jen zdokumentované legacy opravy
- whitespace audit přes `build/whitespace_audit.php` a jeho self-test `build/whitespace_audit_selftest.php`, které nad verzovanými textovými zdroji hlídají koncové mezery a chybějící finální nový řádek
- úzký PSR-12 smoke check přes `composer format:check` a navazující build/test dávky nad postupně rozšiřovanou stabilní sadou helperů; pro lokální dorovnání stejné sady lze použít `composer format:fix`, nyní včetně release smoke testů, HTTP test helperů, unit test harnessu a stabilních sdílených knihoven
- PHPStan na levelu 6 nad rozšiřovanou sadou stabilních helperů podle `phpstan.neon.dist`; používá `build/phpstan_bootstrap.php` a `scanFiles`, takže zná sdílené symboly bez načítání DB/session side efektů. Self-test `build/phpstan_bootstrap_selftest.php` hlídá, že bootstrap nenačítá databázi, autentizaci, session ani runtime konfiguraci a že zachovává už definované bezpečné konstanty. PHPStan zároveň hlídá i release/testovací build nástroje proti návratu PHP 8.1+ typů do PHP 8.0 platformy
- samostatné PHPStan level 6 smoke checky přes `composer analyse:strict` a navazující dávky; vedle lint/bootstrap helperů aktuálně pokrývají 245 stabilizovaných souborů včetně veřejných entrypointů, sdílených knihoven, workflow auditu, redirect guardrailů a rozšiřované sady admin workflow pro blogy, stránky, média, formuláře, podcasty, FAQ, události, ankety, místa, rezervace, widgety, komentáře, kontakty, chat, novinky, soubory ke stažení, jídelní a nápojové lístky, kategorie, newsletter, uživatele, galerii, převod obsahu, reorder endpointy a jednoduché akční endpointy
- validaci `composer.json` a `composer.lock` přes `composer validate --strict`, takže lokální `ci:basic` hlídá stejný Composer kontrakt jako GitHub Actions
- statický release package audit včetně self-testu `build/release_package_audit_selftest.php`, který hlídá, že instalační balíček a source archivy zůstávají bez vývojových nástrojů, lokálních metadat, citlivých konfigurací a uživatelských uploadů
- theme view audit pro default šablonu včetně self-testu, který hlídá oddělení prezentační vrstvy od requestu, session/server stavu, runtime času, databáze a souborových side effectů i statická duplicitní `id`, neexistující cíle `aria-labelledby` / `aria-describedby` / `aria-controls`, neplatné statické `label for`, veřejné `<section>`, `<nav>`, `<aside>`, `role="search"`, všechny veřejné `<article>` prvky bez `aria-labelledby` a `<figure>` bloky bez `aria-labelledby` nebo `figcaption`, formulářová pole bez labelu nebo ARIA názvu, `<fieldset>` bez `<legend>`, obrázky bez `alt`, iframe bez `title`, tlačítka bez explicitního `type`, veřejné tabulky bez `<caption>` nebo `aria-labelledby` a `target="_blank"` odkazy bez `rel="noopener noreferrer"` nebo bez oznámení nového okna v přístupném názvu
- runtime audit, který ve zdrojích i u skutečně vyrenderovaných veřejných a administračních odpovědí hlídá také POST formuláře bez `csrf_token`, iframe bez `title` a odkazy s `target="_blank"` bez `rel="noopener noreferrer"` nebo bez přístupného oznámení nového okna
- runtime guardrail pro explicitní typy formulářových prvků, který vícerádkově hlídá PHP zdroje v `admin/`, `lib/` a `themes/` i reálné HTML odpovědi, aby se do administrace ani šablon nevrátily `<button>` nebo `<input>` bez `type`
- runtime guardrail pro administraci, který hlídá, že odkazy otevírané v novém okně v `admin/*.php` používají bezpečné `rel="noopener noreferrer"` a přístupný text oznamující nové okno; stabilizované administrační obrazovky používají přednostně skrytý text přímo uvnitř odkazu, aby se nepřepisoval viditelný název přes `aria-label`, dynamicky vytvořené odkazy mají stejnou ochranu i přístupný popis a `window.open()` používá `noopener,noreferrer`
- izolovaný release smoke test, který v dočasném snapshot repozitáři skutečně spustí `build/release.ps1 -DryRun -SkipCi`, ověří čistý git stav po běhu, zkontroluje obsah release ZIPu i checksum a navíc ověří skutečný `git archive` source balíček podle `.gitattributes`; oba artefakty musí obsahovat kritické instalační soubory a nesmí obsahovat `vendor`, `node_modules`, `dist`, lokální IDE/AI metadata, citlivé konfigurace ani uploadovaný obsah mimo `uploads/.htaccess`
- unit testy přes `build/unit_tests.php`

`composer ci:full` navíc po `ci:basic` sekvenčně spustí ještě `php build/runtime_audit.php` a `php build/http_integration.php`, takže se hodí před releasem nebo po větší sadě změn. Pokud tyto dva audity spustíte ručně paralelně nad stejnou lokální databází, sdílený lock v `build/test_run_lock.php` druhý běh pozdrží, protože oba používají dočasná testovací nastavení; jeho self-test je psaný tak, aby správně četl exit code child procesu i na Linux runneru GitHub Actions. Stejný plný balík lze vyžádat i při release přes `build/release.ps1 -FullCi`; bezpečnou zkoušku bez zásahu do gitu spustíte přes `build/release.ps1 -DryRun`.

Modulové guardy jsou záměrně dvouvrstvé. Hlavní administrační přehledy modulů jsou uvedené v `coreModuleDefinitions()` jako `admin_paths` a mají vlastní `requireModuleEnabled()`. Formulářové, detailové a stav měnící admin endpointy modulů navíc chrání centrální mapa `adminRouteModuleRequirement()` v `auth.php`, takže vypnutý modul nejde obejít přímým POSTem na save/delete/action soubor. Text 403 hlášky se odvozuje ze společného `admin_label` přes `moduleAdminLabel()`, takže nový modul volá `requireModuleEnabled()` bez druhého parametru a neopisuje vlastní variantu „Modul … není povolen“ v každé routě. Reprezentativní routy z této mapy kryjí unit testy, modulový audit ověřuje existenci uvedených admin souborů, jejich login guard, absenci duplicitních disabled hlášek i provázání manifestových `admin_paths` a HTTP integrace ověřuje reálný 403 scénář pro vypnutý modul. Kód, který potřebuje zjistit modul podle cesty, používá sdílené mapy `modulePublicPathModuleMap()` a `moduleAdminPathModuleMap()`, ne ruční lokální seznamy.

GitHub Actions workflow v `.github/workflows/ci.yml` spouští stejný základní balík na `push` a `pull_request` do `main`. Samostatný workflow `.github/workflows/full-ci.yml` drží plný `composer ci:full` balík pro ruční spuštění (`workflow_dispatch`) a pravidelný noční běh; před spuštěním si připraví MySQL, `config.php`, vestavěný PHP server a čerstvou instalaci CMS. Oba workflow používají aktuální `actions/checkout@v6`, minimální `contents: read` oprávnění, řízení souběhu a timeouty, aby CI neběželo na deprecated Node 20 checkout akci a nezůstávalo zbytečně viset.

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
    add_header X-XSS-Protection "0" always;
    add_header X-Download-Options "noopen" always;
    add_header X-Permitted-Cross-Domain-Policies "none" always;
    add_header Referrer-Policy "same-origin" always;
    add_header Cross-Origin-Opener-Policy "same-origin" always;
    add_header Origin-Agent-Cluster "?1" always;
    add_header Permissions-Policy "accelerometer=(), browsing-topics=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()" always;

    # Zakázané soubory
    location ~ ^/(config|db|auth)\.php$ { deny all; }
    location ~ ^/(composer\.(json|lock)|phpstan\.neon\.dist|\.php-cs-fixer\.dist\.php)$ { deny all; }
    location ~ \.(inc|log|sql|bak|sh|cfg)$ { deny all; }
    location ~ /\.env { deny all; }
    location ~ /\.git { deny all; }
    location ^~ /.github/ { deny all; }
    location ^~ /.claude/ { deny all; }
    location ^~ /.codex/ { deny all; }
    location ^~ /.cursor/ { deny all; }
    location ^~ /vendor/ { deny all; }
    location ^~ /node_modules/ { deny all; }

    # Chráněné adresáře
    location ^~ /uploads/forms/ { deny all; }
    location ^~ /uploads/backups/ { deny all; }
    location ~* ^/uploads/.*\.(php[0-9]?|phtml|phar|cgi|pl|py|rb|sh|asp|aspx|jsp)$ { deny all; }

    # Čisté URL – moduly
    location ~ ^/authors/?$ { rewrite ^ /authors/index.php last; }
    location ~ ^/author/([a-z0-9\-]+)/?$ { rewrite ^/author/(.+?)/?$ /author.php?slug=$1 last; }
    location ~ ^/blog/([a-z0-9\-]+)/?$ { rewrite ^/blog/(.+?)/?$ /blog/article.php?slug=$1 last; }
    location ~ ^/board/kategorie/([a-z0-9\-]+)/?$ { rewrite ^/board/kategorie/(.+?)/?$ /board/index.php?category_slug=$1 last; }
    location ~ ^/board/([a-z0-9\-]+)/?$ { rewrite ^/board/(.+?)/?$ /board/document.php?slug=$1 last; }
    location ~ ^/chat/tema/([a-z0-9\-]+)/?$ { rewrite ^/chat/tema/(.+?)/?$ /chat/index.php?topic_slug=$1 last; }
    location ~ ^/chat/zprava/([0-9]+)/?$ { rewrite ^/chat/zprava/([0-9]+)/?$ /chat/message.php?id=$1 last; }
    location ~ ^/downloads/kategorie/([a-z0-9\-]+)/?$ { rewrite ^/downloads/kategorie/(.+?)/?$ /downloads/index.php?category_slug=$1 last; }
    location ~ ^/downloads/serie/([a-z0-9\-]+)/?$ { rewrite ^/downloads/serie/(.+?)/?$ /downloads/series.php?slug=$1 last; }
    location ~ ^/downloads/([a-z0-9\-]+)/?$ { rewrite ^/downloads/(.+?)/?$ /downloads/item.php?slug=$1 last; }
    location ~ ^/events/typ/([a-z0-9\-]+)/?$ { rewrite ^/events/typ/(.+?)/?$ /events/index.php?type_slug=$1 last; }
    location ~ ^/events/([a-z0-9\-]+)/?$ { rewrite ^/events/(.+?)/?$ /events/event.php?slug=$1 last; }
    location ~ ^/faq/kategorie/([a-z0-9\-]+)/?$ { rewrite ^/faq/kategorie/(.+?)/?$ /faq/index.php?category_slug=$1 last; }
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
    location = /robots.txt { rewrite ^ /robots.php last; }
    location = /sitemap.xml { rewrite ^ /sitemap.php last; }

    # Multi-blog catch-all (musí být poslední)
    location ~ ^/([a-z0-9\-]+)/kategorie/([a-z0-9\-]+)/?$ {
        try_files $uri $uri/ /blog_router.php?blog_slug=$1&category_slug=$2&$args;
    }
    location ~ ^/([a-z0-9\-]+)/stitky/([a-z0-9\-]+)/?$ {
        try_files $uri $uri/ /blog_router.php?blog_slug=$1&tag_slug=$2&$args;
    }
    location ~ ^/([a-z0-9\-]+)/stranka/([a-z0-9\-]+)/?$ {
        try_files $uri $uri/ /blog_router.php?blog_slug=$1&page_slug=$2&$args;
    }
    location ~ ^/([a-z0-9\-]+)/serie/([a-z0-9\-]+)/?$ {
        try_files $uri $uri/ /blog_router.php?blog_slug=$1&series_slug=$2&$args;
    }
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
composer ci:basic
php build/runtime_audit.php
php build/http_integration.php
```

Stejný balík lze nově pustit i jednou zkratkou:

```bash
composer ci:full
```

Při větších zásazích doplňte i PHP lint:

```bash
php build/lint_php.php
```

---

## Vývoj nových modulů

Hlavní administrační zkratka i základní položka v navigaci se pro nový modul odvozují z manifestového `admin_paths` a `admin_capability`. Pokud modul zatím nemá vlastní ručně zařazenou navigační skupinu, administrace ho nabídne ve fallback sekci `Další moduly`.

Nový modul v Kora CMS je zatím součást jádra, ne samostatný balíček. Před návrhem modulu proto používejte checklist [docs/developer-modules.md](docs/developer-modules.md), který shrnuje povinné integrační body pro databázi, migrace, administraci, veřejné routy, navigaci, bezpečnost, WCAG 2.2, testy a release dokumentaci. Module key, labely, `admin_label`, `admin_capability`, výchozí `module_*` hodnota, hlavní admin URL, typy pro content picker, typy výsledků veřejného vyhledávání, sitemap sekce, typy měřeného obsahu pro statistiky a příznaky pro nastavení, profily, veřejnou navigaci, `public_paths` a widgety patří do centrálního manifestu `coreModuleDefinitions()` v `lib/definitions.php`; `install.php` i `migrate.php` z něj doplňují modulové defaulty a audit z něj odvozuje známé module keys, takže nový modul nevyžaduje další hardcoded seznam v auditních nástrojích. Guardrail `build/module_contract_audit.php` hlídá, aby se nové moduly nerozjely mezi instalací, migrací, nastavením, navigací, widgety, content pickerem, veřejným vyhledáváním, sitemapou, statistikami, theme závislostmi a administračními entrypointy, včetně platnosti hodnot jako `settings_default`, veřejná cesta, existence veřejného PHP entrypointu, pořadí navigace, odpovídající `isModuleEnabled('...')` brána veřejného entrypointu, pokrytí veřejné cesty v `analyse:strict` / `format:check` skriptech, manifestové `public_paths`, `admin_paths`, `admin_label`, `admin_capability`, jedinečné a pojmenované `content_reference_types`, `search_result_types`, `sitemap_sections`, `stats_page_types`, `requireModuleEnabled()` brána admin entrypointu bez ruční disabled hlášky, sdílená mapa stav měnících admin rout `adminRouteModuleRequirements()`, manifestově odvozené 403 hlášky přes `moduleAdminLabel()`, pokrytí těchto admin rout v `analyse:strict` / `format:check` skriptech, literálové `isModuleEnabled('...')` odkazy a literálové `getSetting('module_...')`/`saveSetting('module_...')` odkazy v aplikačním kódu. Hlavní administrační přehledy modulů mají používat sdílený `requireModuleEnabled()` helper, aby přímá URL vypnutého modulu skončila 403; dynamický scénář `admin_disabled_modules_http` bere jejich cesty přímo z manifestu a unit testy automaticky procházejí všechny položky `adminRouteModuleRequirements()`. Admin command centrum má základní fallback zkratku odvozenou z prvního `admin_paths` a `admin_capability`, takže nový modul nezůstane skrytý jen proto, že nemá ručně přidanou položku palety. Veřejně navigovatelný modul navíc musí projít dynamickým HTTP smoke scénářem `public_module_navigation_http`, který bere cesty přímo z manifestu a ověřuje vypnutý i zapnutý stav modulu. Každý veřejný PHP endpoint řízený modulem, tedy i detail, feed, soubor nebo obrázek, patří do `public_paths`; audit ověřuje existenci souboru, modulový gate i statickou coverage. Pokud modul přidává zdroj do content pickeru, deklaruje jeho request type a popisek v manifestovém `content_reference_types`; scénář `content_reference_disabled_modules_http` ověřuje, že se po vypnutí nezobrazí v pickeru, snippetech ani výsledcích search endpointu. Pokud modul přidává výsledky do veřejného `search.php`, deklaruje jejich typy a popisky v `search_result_types`; audit hlídá i URL větve v `resultUrl()`. Pokud modul přidává URL do XML sitemapu, deklaruje interní sekce a popisky v `sitemap_sections`; audit hlídá, že každá modulová větev v `sitemap.php` odpovídá manifestu. Pokud veřejný detail modulu zapisuje obsahové návštěvy přes `trackPageView()`, deklaruje své `page_type` hodnoty v `stats_page_types`; dlouhodobé obsahové trendy pak modul zařadí bez ruční mapy ve statistikách. Sdílené helpery `moduleDefinition()`, `knownModuleKey()`, `moduleSettingKey()`, `modulePublicPathModuleMap()`, `moduleAdminPathModuleMap()`, `modulePrimaryAdminPath()`, `moduleAdminCapability()` a `moduleStatsPageTypeMap()` slouží jako jediný zdroj pro další modulové lookupy mimo manifest. Modulový audit nově hlídá i to, aby tento přehled, developer checklist a administrátorská příručka neztratily klíčové integrační body pro nový modul. Před větší modulovou změnou spusťte `composer ci:module-ready`.

Součástí návrhu nového modulu je také kontrola dokumentů [docs/accessibility/wcag-22-aa-conformance.md](docs/accessibility/wcag-22-aa-conformance.md), [docs/accessibility/a11y-remediation-backlog.md](docs/accessibility/a11y-remediation-backlog.md) a [docs/accessibility/manual-test-protocol.md](docs/accessibility/manual-test-protocol.md). Pokud modul zavádí nový typ formuláře, dialogu, uploadu, média, embedu, captcha/auth flow, tabulky, widgetu nebo autorem dodávaného obsahu, musí rovnou určit dopad na accessibility conformance report a doplnit matici, backlog nebo ruční testovací scénář.

---

## Další dokumentace

- [CHANGELOG.md](CHANGELOG.md) – historie verzí
- [docs/admin-guide.md](docs/admin-guide.md) – detailní práce v administraci: Form Builder, podcasty, multiblog, widgety a content picker
- [docs/developer-modules.md](docs/developer-modules.md) – checklist pro návrh a implementaci nového modulu
- [docs/accessibility/wcag-22-aa-conformance.md](docs/accessibility/wcag-22-aa-conformance.md) – draft WCAG 2.2 AA conformance matice pro Kora CMS
- [docs/accessibility/acr-vpat-wcag-draft.md](docs/accessibility/acr-vpat-wcag-draft.md) – VPAT/ACR draft odvozený z WCAG matice
- [docs/accessibility/a11y-remediation-backlog.md](docs/accessibility/a11y-remediation-backlog.md) – prioritizovaný backlog přístupnostních oprav
- [docs/accessibility/manual-test-protocol.md](docs/accessibility/manual-test-protocol.md) – ruční testovací protokol pro klávesnici, čtečky, zoom a mobilní šířky
- [docs/ux-audit-framework.md](docs/ux-audit-framework.md) – framework pro UX a přístupnostní audit
