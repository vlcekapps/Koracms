# Changelog

Všechny důležité změny projektu Kora CMS jsou dokumentovány v tomto souboru.
Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/)
a projekt používá [Semantic Versioning](https://semver.org/lang/cs/).

## [3.0.0-beta.6] – 2026-03-26

### Přidáno
- Statické stránky nově mají samostatnou obrazovku Pozice statických stránek, kde lze měnit pořadí položek v hlavní navigaci přes přesun nahoru a dolů; nové stránky se automaticky ukládají na konec a formulář už ruční pořadí nenabízí

### Změněno
- Modul Ke stažení už nepoužívá ruční pořadí položek v administraci; položky se nově řadí přirozeně podle data vytvoření, od nejnovějších
- Modul Úřední deska / Vývěska / Oznámení už nepoužívá ruční pořadí položek v administraci; položky se nově řadí podle připnutí, data vyvěšení a data vytvoření
- Modul FAQ už nepoužívá ruční pořadí otázek v administraci; otázky se v rámci kategorií nově řadí přirozeně od nejnovějších
- Modul Zajímavá místa už nepoužívá ruční pořadí položek v administraci; výpisy se nově řadí abecedně podle názvu
- Vyhledávání, sitemapa, homepage blok úřední desky a runtime audit nyní respektují nové přirozené řazení těchto modulů a hlídají i odstranění starého sort_order pole z formulářů

## [3.0.0-beta.5] – 2026-03-26

### Přidáno
- Blog nově podporuje i veřejný přehled autorů na `/authors/`, takže návštěvník může projít všechny veřejné autory na jednom místě a otevřít jejich detail

### Změněno
- Detail autora nově nabízí přirozené návratové odkazy na `Všichni autoři` i na `Blog`
- Blog nově umí i filtr podle veřejného autora přes parametr `?autor=slug`, takže z author vrstvy lze přirozeně přejít na články konkrétního autora
- V nastavení blogu je nově i volitelný přepínač pro zobrazení odkazu na veřejný přehled autorů; ve výchozím stavu zůstává vypnutý
- XML sitemap nově zahrnuje i veřejný přehled autorů a jednotlivé veřejné profily autorů

## [3.0.0-beta.4] – 2026-03-26

### Změněno
- Domovská stránka nyní u blogu přesně respektuje nastavený počet článků v běžném výpisu; `Doporučený článek` se vybírá samostatně podle počtu zobrazení a už neubírá položku z blogové sekce
- Homepage composer už nenabízí `Novinky` jako zdroj pro zvýrazněný blok; blog zůstává obsahovým featured zdrojem a civic preset nově používá jako výchozí zvýraznění `Úřední desku`
- Blogové karty, detail článku i stránka autora nyní u článků zobrazují sjednocený údaj `min čtení, přečteno X krát`, takže je vedle odhadované doby čtení vidět i skutečný počet zobrazení článku

## [3.0.0-beta.3] – 2026-03-26

### Přidáno
- Přístupný dialog `Vložit odkaz nebo HTML z webu` je nově dostupný ve všech HTML polích, která se veřejně renderují přes CMS, takže lze z administrace rychle vkládat odkazy nebo hotové HTML bloky z existujících článků, stránek a dalších veřejných modulů
- Veřejný render obsahu nyní podporuje snippety `[audio]...[/audio]`, `[video]...[/video]` a `[gallery]slug-alba[/gallery]` / `[gallery slug="slug-alba"][/gallery]`, které se převádějí na HTML5 přehrávače a jednoduchý embed galerie
- Content picker v HTML polích nyní nabízí i chytré vložení podle typu výsledku: fotogalerii vloží rovnou jako `[gallery]slug-alba[/gallery]` a vhodné audio nebo video z podcastů či položek ke stažení umí vložit jako hotový přehrávač bez ručního psaní URL
- Audio a video snippety nově podporují i atributy `src` a volitelný `mime`, takže lze bezpečně vkládat přehrávače i nad interními file endpointy typu `[audio src="/downloads/file.php?id=123" mime="audio/mpeg"][/audio]`

### Opraveno
- Runtime audit nově hlídá i přítomnost content pickeru a snippet nápovědy napříč HTML formuláři v administraci a převod audio, video a gallery snippetů do veřejného HTML výstupu
- Přístupný modal dialog content pickeru nyní používá i přesnější stav `aria-expanded`, konkrétnější názvy akcí pro čtečky a guardrails pro základní dialogové minimum (`aria-haspopup="dialog"`, `role="dialog"`, `aria-modal="true"`)

## [3.0.0-beta.2] – 2026-03-25

### Přidáno
- Praktický screen-by-screen audit intuitivnosti v `docs/ux-intuition-audit.md` a prioritizovaný backlog oprav v `docs/ux-intuition-backlog.md`
- Moderace komentářů po vzoru WordPressu v jednodušší podobě: stavy `čekající`, `schválený`, `spam`, `koš`, rychlé akce v administraci, badge čekajících komentářů a globální pravidla moderace v nastavení
- Per-article přepínač komentářů v editoru článku a veřejné hlášky pro vypnuté nebo uzavřené komentáře
- Antispam komentářů přes blokované e-maily / domény a zakázané fráze, plus e-mailové upozornění administrátorovi na nové komentáře čekající na schválení
- Volitelné e-mailové upozornění autorovi po schválení komentáře, napojené na stejnou mailovou vrstvu jako registrace, reset hesla a rezervace
- Blogové články nově podporují slug a čisté URL typu `/blog/moje-prvni-clanky`, včetně náhledu, exportu/importu a použití v RSS feedu a sitemapě
- Veřejná autorská vrstva pro blog a osobní weby: `/author/{slug}`, proklik autora v blogu a autorský medailonek pod detailem článku
- Správa veřejného autora v administraci i v Mém profilu: zapnutí profilu, vlastní slug, bio, web a avatar uložený do `uploads/authors/`
- Capability-based role model pro administraci: nové role `autor`, `editor`, `moderátor` a `správce rezervací`, při zachování kompatibility se starší rolí `spolupracovník`
- Role-aware administrace: levé menu, dashboard a schvalovací akce se nově řídí oprávněními konkrétního účtu, takže autor vidí jen svůj obsah, moderátor moderaci a správce rezervací pouze rezervační část
- Jednotná admin fronta `Ke schválení` pro obsah, komentáře a rezervace, včetně rychlých akcí a návratu zpět do stejného filtru po schválení nebo moderaci
- Novinky nově podporují titulek, slug a čisté URL typu `/news/moje-prvni-novinka`, včetně vlastního detailu, RSS feedu, sitemapy a použití ve vyhledávání i na homepage
- Události nově podporují slug a čisté URL typu `/events/moje-akce`, včetně vlastního detailu, vyhledávání, sitemapy a použití v exportu/importu
- Úřední deska nově podporuje slug a čisté URL typu `/board/moje-vyhlaska`, včetně veřejného detailu dokumentu a odděleného CTA na stažení přílohy přes bezpečný file endpoint
- Modul `Úřední deska` se nově umí chovat i jako `Vývěska` nebo `Oznámení`: podporuje typ položky, krátký perex, volitelný obrázek, kontakt, připnutí důležitých položek a veřejný název modulu nastavitelný v administraci
- Modul `Zajímavá místa` se nově chová jako turistický adresář: podporuje slug a čisté URL typu `/places/moje-misto`, detailovou stránku, typ místa, krátký perex, volitelný obrázek, adresu, lokalitu, GPS, kontakt a otevírací dobu
- Modul `Ke stažení` se nově chová jako katalog dokumentů a software: podporuje slug a čisté URL typu `/downloads/moje-aplikace`, detailovou stránku, typ položky včetně `software`, krátký perex, volitelný náhled, verzi, platformu, licenci a kombinaci lokálního souboru s externím odkazem
- Modul `FAQ` se nově chová jako znalostní báze: podporuje slug a čisté URL typu `/faq/moje-otazka`, krátký perex, vlastní detail odpovědi a redakční workflow se schvalováním v adminu
- Modul `Podcasty` nově podporuje čisté URL pořadů i epizod (`/podcast/slug-poradu` a `/podcast/slug-poradu/slug-epizody`), veřejný detail epizody, RSS feed navázaný na slug pořadu a redakční administraci s filtrováním pořadů i epizod
- Modul `Ankety` nově podporuje slug a čisté URL typu `/polls/moje-anketa`, veřejný detail ankety, kanonické přesměrování starého `?id=` odkazu a redakční administraci s filtrem, slug workflow a časovým plánováním
- Modul `Galerie` nově podporuje slug a čisté URL pro alba i fotografie (`/gallery/album/moje-album` a `/gallery/photo/moje-fotografie`), veřejný detail alba i fotografie a modernější administraci s filtrováním, slug workflow a kanonickým přesměrováním starých `?id=` odkazů
- Modul `Jídelní lístek` nově podporuje slug a čisté URL typu `/food/card/moje-menu`, veřejný detail lístku a redakční administraci s filtrem, slug workflow a kanonickým přesměrováním starých `?id=` odkazů
- Modul `Rezervace` nově používá capability-aware administraci pro rezervace, zdroje, kategorie a místa, se sjednoceným vyhledáváním, stavovými filtry a bezpečnými návraty zpět do stejného workflow
- Modul `Statické stránky` nově používá capability-aware administraci s vyhledáváním, stavovým filtrem, robustnějším slug workflow, veřejným preview a bezpečnými návraty zpět do stejného seznamu
- Moduly `Kontakt` a `Chat` nově používají moderátorský inbox se stavy `nové`, `přečtené`, `vyřízené`, detailovou obrazovkou zprávy, bulk akcemi, hledáním a stavovým filtrem
- Modul `Newsletter` nově používá capability-aware administraci s hledáním a filtrem odběratelů, detailem odběratele, potvrzením a znovu-posláním potvrzovacího e-mailu, detailní historií rozesílek a přehlednějším compose workflow bez veřejného archivu
- Odběratelé newsletteru nově podporují i hromadné akce: potvrdit vybrané odběry, znovu poslat potvrzení a smazat vybrané, včetně bezpečného návratu do stejného filtru

### Opraveno
- Veřejný web nově používá klidnější a logičtější texty na homepage i detailech obsahu: z homepage zmizely redundantní kickery a souhrny, doporučený článek má přirozenější pořadí informací a autorský medailonek se zobrazuje přímo pod detailem článku
- Veřejné i admin formuláře nyní drží jednotný WCAG vzor pomocných textů pod polem s korektním `aria-describedby`; runtime audit hlídá i zákaz inline helper textů uvnitř `label` a `legend`
- Administrace nyní používá role-based dashboard, klidnější levé menu, srozumitelnější popisky sekcí, detailů a návratových odkazů, jednotné filtry a civilnější prázdné stavy
- Administrace nyní používá i jednotnější hromadné akce, captiony tabulek a přesnější názvy sekcí včetně newsletterových bulk akcí pro odběratele
- Administrace nyní používá i přesnější mikrocopy uvnitř formulářů: konkrétní helper texty pro slugy, plánování, zveřejnění a práci se soubory nebo médii
- Opravena rozházená diakritika ve sdíleném admin layoutu po accessibility passu
- Administrace nyní používá srozumitelnější titulky formulářů, horní návratové odkazy na příslušné přehledy a civilnější veřejné odkazy `Zobrazit na webu`, takže je při vytváření a úpravě obsahu hned jasné, kde se správce nachází a kam se může vrátit
- Administrace nyní používá i jednotnější spodní akce formulářů: konkrétní hlavní tlačítka typu `Přidat novinku`, `Vytvořit stránku` nebo `Uložit změny`, konzistentní `Zrušit` a veřejné odkazy `Zobrazit na webu` jen tam, kde je stránka skutečně dostupná
- Administrace nyní používá i srozumitelnější úvodní věty a stavové volby ve formulářích: `Zveřejnit na webu`, `Zobrazit v hlavní navigaci` nebo `Použít jako aktuální lístek` nově jasněji popisují, co se po uložení skutečně stane
- Administrace nyní používá i konkrétnější názvy sekcí uvnitř formulářů, například `Základní údaje události`, `Obsah a zobrazení stránky`, `Audio a text epizody` nebo `Náhled a zveřejnění`, takže formuláře lépe vedou podle skutečné práce správce
- Administrace teď používá civilnější a jednotnější texty v seznamech: přirozenější prázdné stavy, první kroky při prázdném seznamu, sjednocené akce `Použít filtr` / `Zrušit filtr` a srozumitelnější odkazy typu `Zobrazit na webu` nebo `Zobrazit detail`
- Nastavení veřejného názvu vývěsky v administraci má přesnější popisek `Veřejný název sekce vývěsky`, takže je hned zřejmé, k jakému modulu se pole vztahuje
- Administrace nově používá civilnější názvy sekcí a přehledů: `Obecná nastavení`, `Správa modulů`, `Pozice modulů`, přesnější položky rezervací v levém menu a čitelnější souhrny `Celkem: X / Publikováno: X / Čeká na schválení: X` na dashboardu
- Dashboard a fronta `Ke schválení` nově používají srozumitelnější názvy přehledů a akcí jako `Přehled administrace`, `Další přehledy`, `Upřesnění`, `Otevřít sekci`, `Otevřít seznam` a `Otevřít moderaci`
- Dashboard už nepoužívá redundantní relationship vrstvu u rozbalovacích skupin v levém menu, takže čtečky nehlásí duplicitní text typu `Blog seskupení, Blog tlačítko`
- Detailové obrazovky v administraci teď používají srozumitelnější návraty a akční nadpisy jako `Zpět na přehled kontaktních zpráv`, `Zpět na odběratele newsletteru` nebo `Co můžete udělat`; přesnější názvy dostaly i kategorie a rezervační přehledy
- Import/export nyní zachovává i stav komentářů, e-mail autora a per-article volbu `comments_enabled`
- Staré odkazy `blog/article.php?id=...` se nyní kanonicky přesměrují na slug URL a veřejné vyhledávání vrací jen publikované články
- Runtime audit nově hlídá i veřejnou stránku autora, autorské odkazy v blogu a nová pole v administraci
- Installace a migrace rozšiřují `cms_users.role` o nové redakční role a runtime audit nově ověřuje i přístupovou matici pro autora, moderátora a správce rezervací
- Schvalování dokumentů úřední desky je nově napojené na stejný endpoint jako ostatní sdílený obsah a moderace komentářů i rezervací nově podporuje bezpečný interní návrat přes validovaný `redirect`
- Administrace i README teď výslovně doporučují HTML textarea jako přístupnější výchozí editor a Quill popisují jen jako volitelný vizuální režim
- Import/export nově zachovává i titulky, slugy a čas poslední úpravy u novinek a runtime audit hlídá i detail novinky a kanonické přesměrování starého `news/article.php?id=...`
- Import/export nově zachovává i slugy událostí a runtime audit hlídá detail události i kanonické přesměrování starého `events/event.php?id=...`
- Import/export nově zachovává i slugy dokumentů úřední desky, typ položky, perex, obrázek, kontakt i připnutí; administrace úřední desky má vyhledávání a stavový filtr a runtime audit hlídá detail dokumentu, detail linky na výpisu, nové formulářové prvky i kanonické přesměrování starého `board/document.php?id=...`
- Import/export nově zachovává i slugy, typ, perex, obrázek, adresu, lokalitu, GPS a kontaktní údaje u modulu `Zajímavá místa`; administrace má vyhledávání a stavový filtr a runtime audit hlídá detail místa, detail linky na výpisu, nové formulářové prvky i kanonické přesměrování starého `places/place.php?id=...`
- Import/export nově zachovává i slugy, typ položky, perex, náhled, verzi, platformu, licenci a externí odkaz u modulu `Ke stažení`; administrace má vyhledávání a stavový filtr a runtime audit hlídá detail položky, detail linky na výpisu, nové formulářové prvky i kanonické přesměrování starého `downloads/item.php?id=...`
- Import/export nově zachovává i slug, perex a stav FAQ; administrace FAQ má vyhledávání a stavový filtr a runtime audit hlídá detail FAQ, detail linky na výpisu, nové formulářové prvky i kanonické přesměrování starého `faq/item.php?id=...`
- Import/export nově zachovává i slugy a čas poslední úpravy podcastových pořadů i epizod; vyhledávání, sitemapa, RSS feed i runtime audit teď používají kanonické podcast URL a hlídají veřejný detail pořadu i epizody, admin formuláře a legacy redirecty ze starých query URL
- Import/export nově zachovává i slug, popis a čas poslední úpravy anket; vyhledávání, sitemapa, homepage i runtime audit teď používají kanonické poll URL a hlídají veřejný detail ankety, detail linky na výpisu, admin formuláře i legacy redirect ze starého `polls/index.php?id=...`
- Import/export nově zachovává i slugy a čas poslední úpravy jídelních a nápojových lístků; vyhledávání, sitemapa a runtime audit teď používají kanonické food URL a hlídají veřejný detail lístku, admin formulář i legacy redirect ze starého `food/card.php?id=...`
- Import/export nově zachovává i slugy alb a fotografií galerie; vyhledávání, sitemapa a runtime audit teď používají kanonické gallery URL a hlídají veřejný detail alba i fotografie, admin formuláře a legacy redirecty ze starých `gallery/album.php?id=...` a `gallery/photo.php?id=...`
- Vyhledávání a sitemap nyní zahrnují i aktivní rezervační zdroje a runtime audit hlídá novou administraci zdrojů, kategorií a míst i přístup booking managera k celé rezervační části
- Vyhledávání, sitemapa, navigace a dashboard teď používají sdílené helpery i pro statické stránky a runtime audit hlídá seznam stránek, formulář i přístupová pravidla pro role mimo shared content
- Dashboard a levé menu teď ukazují nové kontaktní a chat zprávy přímo v moderátorském workflow a runtime audit nově hlídá i admin inboxy a detail zprávy
- Veřejné přihlášení k newsletteru i administrativní znovu-poslání potvrzení teď používají stejnou sdílenou mailovou vrstvu a runtime audit nově hlídá i přehled newsletteru, detail odběratele, detail rozesílky a compose obrazovku

## [3.0.0-beta.1] – 2026-03-23

### Přidáno
- `build/release.ps1` nově umí i prerelease verze (`alpha`, `beta`, `rc`) a GitHub release správně označí jako prerelease
- Repo-local pravidla v `AGENTS.md` pro bezpečnost, diakritiku a WCAG
- `build/runtime_audit.php` – HTTP smoke audit pro klíčové veřejné i admin stránky
- Sdílené veřejné a11y styly pro skip link, screen-reader text a focus ring
- Public theme kernel v `lib/theme.php`, první oficiální šablona v `themes/default/` a jednotný layout pro veřejný web
- Administrace `Vzhled a šablony` pro výběr aktivní veřejné šablony s metadaty a bezpečným fallbackem na `default`
- Obrázkové preview karty šablon v administraci včetně statických SVG náhledů pro first-party themes
- Další vizuální polish first-party themes: výraznější identita `civic`, `editorial` a `modern-service` včetně lepšího desktop/mobile rytmu
- Manifest-driven theme settings pro default theme: paleta, akcenty, typografie a šířka obsahu bez zásahu do PHP
- Theme-aware layout varianty pro default theme: hlavička (`balanced` / `centered` / `split`) a homepage (`balanced` / `editorial` / `compact`)
- Homepage composer pro default theme: featured modul, pořadí sekcí a bezpečné zapínání/vypínání homepage bloků podle theme settings
- Tři nové first-party šablony `civic`, `editorial` a `modern-service`, každá s vlastním manifestem, stylem a výchozím profilem vzhledu
- Session-based živý náhled šablon a jejich draft nastavení bez změny `active_theme` v produkční konfiguraci
- Bezpečný portable ZIP formát pro šablony: import/export statických theme balíčků bez PHP override souborů
- Repo-local UX audit framework v `docs/ux-audit-framework.md` a základní UX guardrails v `build/runtime_audit.php`
- Profil webu při instalaci i v administraci: `Osobní web`, `Blog / magazín`, `Obec / spolek`, `Služby / firma` a `Vlastní profil`; první čtyři mají volitelné doporučené presety modulů, navigace, homepage composeru a aktivní šablony, `Vlastní profil` je neutrální režim pro ruční správu

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
- Runtime audit nově hlídá i základní UX heuristiky jako skip link, `main#obsah`, jeden `h1`, prázdné titulkové texty a stabilitu homepage struktury
- Migrace nově seeduje `site_profile` i pro starší instalace; pokud hodnota chybí, CMS použije bezpečný odhad podle aktivní šablony a zapnutých modulů
- Moduly `Ke stažení` a `Úřední deska` nově stahují přes serverový endpoint s `Content-Disposition`, takže návštěvník dostává původní název souboru a veřejné HTML neodhaluje interní jméno na disku
- Sanitizace příjemce (`$to`) v `sendMail()` proti email header injection (SMTP RCPT TO i hlavička To)
- Ochrana proti timing útokům v `public_login.php` – `password_verify()` se volá vždy + zpoždění při neúspěchu
- Potvrzovací tokeny mají nyní 24h expiraci (`confirmation_expires`) – migrace, registrace i ověření
- Prepared statement místo přímé interpolace `$window` v `rateLimit()`
- Tichý catch v `rateLimit()` nyní loguje chybu přes `error_log()`
- Prázdné catch bloky v `blog/article.php` doplněny o `error_log()`
- Potlačení chyb (`@`) v `themeDeleteDirectory()` a importu šablon nahrazeno logováním

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
