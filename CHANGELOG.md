# Changelog

Všechny důležité změny projektu Kora CMS jsou dokumentovány v tomto souboru.
Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/)
a projekt používá [Semantic Versioning](https://semver.org/lang/cs/).

## [3.3.5] – 2026-04-04

### Opraveno
- **Cron – při částečně dorovnaném schématu nově bezpečně přeskočí tabulku s chybějícím sloupcem** – plánované publikování a zrušení publikace už při schema driftu neskončí syrovou SQL chybou `Unknown column ...`, ale zapíší srozumitelnou informaci, které tabulce a kterým sloupcům ještě po migraci chybí dorovnání

## [3.3.4] – 2026-04-04

### Přidáno
- **Duplikace obsahu – tlačítko „Duplikovat" v přehledu novinek, stránek, událostí a úřední desky** – vytvoří kopii položky se stavem „Koncept", novým slugem a preview tokenem; nové soubory `news_clone.php`, `page_clone.php`, `event_clone.php`, `board_clone.php`
- **Stav „Koncept" pro události, soubory ke stažení, FAQ, místa a podcasty** – ENUM status rozšířen o hodnotu `draft`, formuláře obsahují výběr stavu (Koncept / Publikováno / Čeká na schválení), save handlery respektují oprávnění a při první publikaci aktualizují `created_at`

### Změněno
- **Záloha databáze – cron.php nově zapisuje SQL dump inkrementálně místo do paměti** – záloha již nestaví celý dump jako jeden PHP string, ale zapisuje řádek po řádku přes `fopen`/`fwrite`; u velkých databází tak nedojde k vyčerpání `memory_limit` a pádu cron úlohy
- **Ukládání článků – celá operace je nově obalena databázovou transakcí** – uložení článku, reset featured příznaku, smazání a vložení tagů nyní probíhá atomicky; při chybě uprostřed se provede rollback a článek nezůstane bez tagů
- **CSRF ochrana – token se nově rotuje po každém úspěšném ověření** – `verifyCsrf()` po validaci vygeneruje nový token a předchozí uloží jako záložní, takže ukradený token nelze opakovaně zneužít a dva současně otevřené formuláře (multi-tab) stále fungují
- **Rate limiting – atomický upsert místo SELECT + UPDATE** – `rateLimit()` nově používá `INSERT ... ON DUPLICATE KEY UPDATE`, čímž eliminuje TOCTOU race condition, kdy dva souběžné požadavky mohly oba projít limitem
- **Záloha e-mailových odkazů – `siteUrl()` preferuje nastavení `site_url` z databáze** – funkce pro generování absolutních URL v e-mailech nově upřednostňuje administrátorem nastavenou adresu webu před HTTP hlavičkou `Host`, která je ovladatelná klientem a mohla být zneužita k vložení podvrženého odkazu do notifikačních e-mailů
- **Migrace – opakované spuštění `migrate.php` se přeskočí, pokud je databáze aktuální** – migrate.php nově ukládá do `cms_settings` klíč `migration_version` a při opakovaném spuštění se shodná verze přeskočí bez zbytečných ALTER TABLE pokusů
- **Widget rendering – výjimka v jednom widgetu neshodí celou stránku** – `renderZone()` nově obaluje každý widget v `try/catch` a chybu zaloguje místo propagace fatální výjimky na homepage
- **`formatCzechDate('')` – prázdný řetězec vrací prázdný řetězec** – dříve `new DateTime('')` tiše vrátil aktuální datum, nyní funkce při prázdném vstupu korektně vrátí `''`
- **`slugify()` – rozšíření transliterace o slovenské, polské a německé znaky** – mapa nově pokrývá `ľ, ŕ, ĺ, ô, ą, ć, ę, ł, ń, ś, ź, ż, ä, ö, ü, ß` a jejich velké varianty, takže slovenská, polská a německá jména generují smysluplné slugy místo prázdných řetězců
- **Gallery – ochrana proti dělení nulou u poškozených obrázků** – `gallery_make_thumb()` nově kontroluje, zda `getimagesize()` vrátilo nenulové rozměry, a při nulové šířce nebo výšce vrátí `false` místo fatal error
- **VERSION soubor – bezpečný fallback** – `db.php` nově ošetřuje chybějící `VERSION` soubor a místo PHP 8.1+ deprecation warning vrátí `'0.0.0'`
- **Výkonnostní indexy pro veřejné dotazy** – migrace přidává composite indexy na `(deleted_at, status, publish_at)` pro 12 hlavních tabulek; zrychluje veřejné SQL filtry zejména u větších databází
- **Autosave – podpora Quill WYSIWYG editoru** – autosave před ukládáním synchronizuje obsah z Quill editoru do textarea a při obnově konceptu jej zpětně promítne; funguje ve všech formulářích s rich-text editorem
- **Sjednocená validace datetime-local** – nová sdílená funkce `validateDateTimeLocal()` v `lib/ui.php` nahrazuje 3 různé validační vzory v 6 save handlerech; konzistentní chování napříč všemi moduly
- **Quality hardening testů a release kontroly** – integrační a runtime helpery nově před každým POSTem automaticky obnovují aktuální CSRF token z aktivní session, takže quality audity testují skutečný stav po PRG redirektech a nepadají na zastaralých tokenech
- **Migrace a instalace – konzistence schématu se dorovnává i při opakovaném spuštění stejné verze** – `migrate.php` už nepřeskočí kontrolu chybějících sloupců jen proto, že sedí `migration_version`, a instalační schéma je sladěné s upgradem i pro `cms_events` a `cms_places`

### Opraveno
- **Soft-deleted obsah neviditelný ve všech veřejných i admin cestách** – přidán filtr `deleted_at IS NULL` do veřejných dotazů na stránky (`page.php`), články (`blog/article.php`), autory (`author.php`) a do všech admin save handlerů (10 souborů); smazané položky již nejdou editovat ani zobrazit přes preview token; opravena funkce `placePublicVisibilitySql()`, která chyběla
- **Content lock race condition** – `acquireContentLock()` nyní používá `INSERT ... ON DUPLICATE KEY UPDATE` místo dvou separátních dotazů, čímž eliminuje souběžné vytvoření duplicitních zámků
- **Dark mode: autosave, SEO náhled a počítadlo slov** – inline skripty v admin panelu nyní používají CSS custom properties místo hardcoded barev, takže správně fungují i v tmavém režimu
- **relativeTime() crash na null/invalid** – funkce nyní přijímá nullable string a při nevalidním datu vrátí `–` místo fatální výjimky
- **Kategorie: ochrana proti cirkulární referenci** – při editaci kategorie se kontroluje, zda navrhovaný nadřazený prvek není potomek editované kategorie (zabránění smyčce A→B→A)
- **Autosave: podpora checkbox a radio polí** – lokální koncept nyní ukládá i stav zaškrtávacích a přepínacích polí ve formulářích
- **Podcasty v cron publish_at** – naplánované epizody podcastů se nyní automaticky publikují přes cron, stejně jako články a novinky
- **WCAG 2.2 – 11 obrázků s obsahem nově má popisný alt text** – náhledy článků, stažení, událostí, míst a nástěnky ve widgetech i ve views měly prázdný `alt=""`; nově se jako alt text používá název/titulek příslušného záznamu (obrázky uvnitř `aria-hidden` duplicitních odkazů na homepage a v indexu nástěnky zůstávají správně dekorativní)
- **Blogové statické stránky – nová stránka se po publikaci správně objeví i bez ručně zvoleného `article_status`** – ukládání stránek nově odvodí výchozí status z checkboxu publikace, takže nově vytvořený obsah neskončí omylem jako draft a je na webu viditelný hned po uložení
- **Nastavení webu – po redirectu se vrací konkrétní validační chyba pole místo obecné hlášky** – PRG flow přenáší detailní field-level chyby i pro uploady loga a favicony, takže správce dostane po návratu do formuláře přesnou informaci, co opravit
- **Widget Statistiky návštěvnosti – landmark používá skutečný heading a odolnější guardrail** – veřejný widget je lépe dohledatelný pro čtečky obrazovky a audit už nekontroluje jeho nadpis křehce jen podle jedné pevné formulace

### Přidáno
- **Duplikace obsahu – články, novinky, stránky, události, nástěnka** – tlačítko „Duplikovat" v přehledech všech hlavních typů obsahu vytvoří kopii jako koncept s novým autorem; u článků klonuje i štítky, u nástěnky nastaví dnešní datum vyvěšení
- **Draft status pro všechny obsahové moduly** – stav „Koncept" nově podporují i události, soubory ke stažení, FAQ, místa, podcasty (pořady i epizody); status selector ve formulářích s capability-based kontrolou; při první publikaci se aktualizuje datum vytvoření
- **Plánované publikování událostí** – pole `publish_at` přidáno do `cms_events`; formulář událostí má datetime-local input; veřejný filtr a cron automaticky publikují naplánované události s korektním datem
- **Content locking pro nástěnku a události** – varování při souběžné editaci s heartbeat obnovou zámku; doplňuje existující locking pro články, novinky a stránky
- **Koš – soft delete pro další moduly** – úřední deska, soubory ke stažení, jídelní lístky, podcasty (pořady i epizody), galerie (alba i fotografie) a ankety nyní používají soft delete místo trvalého mazání; smazané položky se zobrazují v koši s možností obnovení nebo trvalého smazání
- **Související články** – pod článkem blogu se automaticky zobrazují až 3 související články ze stejného blogu na základě společné kategorie a sdílených štítků; nová funkce `relatedArticles()` v `lib/presentation.php`
- **Náhledový odkaz (preview token) pro novinky, stránky, události a nástěnku** – nový sloupec `preview_token` v tabulkách `cms_news`, `cms_pages`, `cms_events` a `cms_board`; při uložení se automaticky vygeneruje 32znakový token; v administračním formuláři se zobrazí odkaz „Náhled", který umožní zobrazit nepublikovaný obsah bez přihlášení; veřejné zobrazení přes `?preview=<token>` obchází kontrolu stavu a plánovaného publikování
- **SEO náhled ve formulářích** – pod poli meta titulek a meta popis se v administraci zobrazuje živý náhled výsledku ve vyhledávači (Google snippet preview) s počítadlem znaků a varováním při překročení doporučené délky
- **Autosave (localStorage)** – administrační formuláře s textovým obsahem se automaticky ukládají do localStorage každých 30 sekund; při pádu prohlížeče nebo neúmyslném zavření se při dalším otevření formuláře nabídne obnova neuloženého konceptu s informací o čase uložení; po odeslání formuláře se lokální koncept automaticky smaže
- **Počítadlo slov a čas čtení v editoru** – pod textovým polem obsahu se v reálném čase zobrazuje počet slov, znaků a odhadovaný čas čtení (200 slov/min); aktualizuje se při každé změně textu
- **Přístupnost – respektování prefers-reduced-motion** – veřejný CSS i admin panel nově vypínají animace a plynulé posouvání pro uživatele, kteří mají v systému zapnutou preferenci pro omezený pohyb
- **Dark mode administrace** – admin panel a přihlašovací stránky automaticky přepínají na tmavý režim podle systémové preference (`prefers-color-scheme: dark`); veškeré barvy převedeny na CSS custom properties; tmavý režim používá indigo paletu s ověřenými kontrasty splňujícími WCAG AA (4.5:1)
- **Přístupnost – upozornění na chybějící alt text u médií** – při uploadu obrázků se zobrazí připomínka k vyplnění alt textu; v editaci média s prázdným alt textem se zobrazí vizuální varování s odkazem na WCAG 2.2
- **Hierarchické kategorie blogu** – kategorie nyní podporují jednu úroveň vnoření přes `parent_id`; správa kategorií zobrazuje stromovou strukturu s odsazením; formulář článku ukazuje kategorie hierarchicky; veřejný filtr podle nadřazené kategorie automaticky zahrne i články z podkategorií
- **Content locking (varování při souběžné editaci)** – nová tabulka `cms_content_locks` s 15minutovou expirací; při otevření formuláře článku, novinky nebo stránky se zobrazí varování, pokud obsah právě edituje jiný uživatel; JavaScript heartbeat obnovuje zámek každých 60 sekund; zámky se uvolňují při uložení a čistí přes cron
- **Poslední aktivita na dashboardu** – administrační dashboard zobrazuje posledních 15 akcí z audit logu s relativním časem v češtině, jménem uživatele a podrobnostmi; nová funkce `relativeTime()` v `lib/ui.php`
- **Přístupnost – viditelná tlačítka ↑/↓ pro řazení seznamů** – sortovatelné seznamy (navigace, widgety, blogy) mají vedle každé položky tlačítka pro posun nahoru/dolů s aria-label; přesun je oznámen screen readerům přes aria-live region; doplňuje existující Ctrl+Arrow klávesovou zkratku
- **Stav „koncept" (draft) pro články, novinky a stránky** – nový stav `draft` v databázovém ENUM umožňuje autorům uložit rozpracovaný obsah bez vstupu do schvalovací fronty; koncepty jsou neviditelné na veřejném webu a v review queue; ve formuláři článku, novinky i stránky je nový selector „Stav" s možnostmi Koncept / Publikováno / Čeká na schválení; uživatelé bez schvalovacího oprávnění nemohou přímo publikovat
- **Plánované publikování přes cron** – nová cron úloha automaticky publikuje články, novinky a stránky s nastaveným `publish_at` datem, když tento čas nastane; symetrické k existujícímu plánovanému rušení publikace
- **Plánované publikování pro novinky a stránky** – pole `publish_at` přidáno do `cms_news` a `cms_pages`; formuláře novinek a stránek mají nový datetime-local input „Plánované publikování"; veřejné dotazy filtrují i podle `publish_at`
- **Hromadná změna stavu článků** – bulk akce v seznamu článků nově podporují „Nastavit jako koncept", „Ke schválení" a „Publikovat" (poslední jen pro uživatele s oprávněním schvalovat)
- **Unit testy – 89 testů pro 16 kritických funkcí** – nový `build/unit_tests.php` s vlastním bootstrapem (`build/unit_test_bootstrap.php`) testuje `h()`, `inputInt()`, `internalRedirectTarget()`, `slugify()`, `formatCzechDate()`, `readingTime()`, `paginateArray()`, `formatFileSize()`, `mailSanitizeHeaderValue()`, `base32Decode()`, `totpCalculate()`, `totpUri()`, `parseContentShortcodeAttributes()`, `normalizeContentEmbedUrl()`, `userHas2FA()`/`userHasPasskey()` a CSRF rotaci; spuštění: `php build/unit_tests.php`

## [3.3.3] – 2026-04-01

### Změněno
- **Formuláře – hlavní navigace nově správně přidává aktivní formuláře i při zapnutém `nav_order_unified`** – veřejná navigace při sjednoceném pořadí modulů, stránek, blogů a formulářů znovu korektně pracuje i s položkami `form:*`, takže formuláře označené pro zobrazení v navigaci nezmizí jen proto, že web používá novější správu pořadí v `admin/menu.php`; integrační test současně nově ověřuje i produkční variantu s neprázdným `nav_order_unified`

## [3.3.2] – 2026-04-01

### Změněno
- **Formuláře – po uložení se v editoru nově spolehlivě ukáže jednorázová success hláška** – správa formulářů používá po create/edit uložení PRG flash zprávu `Formulář byl uložen.`, takže správce po redirectu dostane jasné potvrzení o uložení a při dalším obnovení stránky se hláška znovu neopakuje
- **Formuláře – preset `Nahlášení chyby` je na webu plně použitelný hned po prvním uložení** – veřejný renderer formulářů nově korektně pracuje i s poli typu více voleb a preset formulář se po vytvoření okamžitě vykreslí se všemi poli, ne jen s CAPTCHA; současně správa menu a veřejná navigace sdílejí stejnou logiku pro zveřejněné formuláře, takže admin už netvrdí, že se formulář zobrazí, když ve veřejné navigaci neprojde
## [3.3.1] – 2026-04-01

### Změněno
- **Widgety – úvod domovské stránky má nově jediný zdroj pravdy v intro widgetu** – pole `Úvodní text` zmizelo z `Obecných nastavení`, homepage už nepoužívá legacy `home_intro` a stejný obsah se nově spravuje jen přes widget `Úvodní text`; widget zároveň podporuje HTML a stejné snippety jako ostatní obsahové bloky a při prázdném obsahu se na webu vůbec nevykreslí
- **Migrace – starý úvod domovské stránky se při upgradu bezpečně převede do widgetu** – `migrate.php` nově při aktualizaci doplní původní `home_intro` do existujícího nebo nově vytvořeného intro widgetu na homepage, takže po nasazení změny nezmizí dosavadní úvodní text ani na starších instalacích
- **Přístupnost administrace – dialogy widgetů a formuláře správy blogů mají dotaženější WCAG 2.2 sémantiku** – nastavení widgetů i create/edit formuláře blogů nově používají jasné skupiny polí přes `fieldset` a `legend`, pomocné texty jsou navázané přes existující `aria-describedby` a focus trap v dialogových oknech pracuje jen s viditelnými a aktivními prvky, takže je ovládání spolehlivější pro klávesnici i čtečky obrazovky

## [3.3.0] – 2026-04-01

### Změněno
- **Import / Export – JSON import nově přísně hlídá UTF-8 a chrání českou diakritiku** – administrace při importu JSON nejdřív ověří platné UTF-8, toleruje jen běžný UTF-8 BOM a soubor s poškozeným kódováním odmítne dřív, než by se texty zapsaly do databáze; dokumentace současně nově výslovně upozorňuje, že ruční SQL restore musí používat `utf8mb4`, jinak se české znaky mohou změnit na `?`
- **Widgety – footer už nespoléhá na natvrdo zapsané odkazy a sociální sítě** – odkazy na sociální sítě, vyhledávání napříč webem i odběr novinek se nově spravují jako skutečné widgety; footer už tyto prvky negeneruje natvrdo a administrace `Obecná nastavení` se tím zjednodušila o samostatná pole pro sociální sítě
- **Widgety – veřejné statistiky už nemají duplicitní přepínač ve správě modulů** – zobrazení widgetu `Statistiky návštěvnosti` se nově řídí jen třemi podmínkami: zapnutým modulem Statistiky, aktivním sledováním návštěvnosti a tím, že je samotný widget aktivní v některé zóně; duplicitní checkbox ve `Správě modulů` zmizel
- **Widgety – přehled widgetů nově ukazuje i důvod, proč se aktivní widget na webu nezobrazí** – správa widgetů používá stejnou dostupnostní logiku jako veřejný render a u aktivních widgetů nově přímo nad tlačítkem `Nastavení` vypíše, zda je blok skrytý kvůli vypnutému modulu, vypnutému sledování, chybějícímu obsahu nebo neplatné vazbě na formulář, blog či album
- **Blogy – změna slugu nově opravdu zachovává funkční staré veřejné adresy** – při přejmenování slugu blogu se starý slug nově spolehlivě uloží do redirect tabulky ještě před aktualizací blogu, takže staré adresy blogu, jeho článků, blogových stránek i RSS feedu korektně vrací `301` na nový canonical tvar místo `404`
- **Audity – runtime kontrola registrace už nepadá na lokálně vypnuté veřejné registraci** – probe veřejné stránky `register.php` si před vlastním ověřením explicitně zapne `public_registration_enabled`, takže audit nově stabilně testuje skutečnou dostupnost registrační stránky místo toho, aby občas skončil falešnou chybou `403 Forbidden` podle lokálního výchozího nastavení
- **Přístupnost – hlavní navigace je pro čtečky nově skutečný nadpis, ne jen skrytý text** – landmark hlavní navigace se nově odkazuje na skrytý `h2` nadpis `Hlavní navigace`, takže se navigace dá spolehlivě najít i při procházení nadpisů ve čtečkách obrazovky
- **Výchozí šablona – veřejné statistiky návštěvnosti jsou nově skutečný widget místo natvrdo renderované patičky** – footer už veřejné statistiky negeneruje přímo pod odkazem na Kora CMS; místo toho je zpřístupňuje nový widget `Statistiky návštěvnosti`, který lze umístit přes správu widgetů a u existujících webů se po spuštění `migrate.php` doplní do footer zóny automaticky, pokud bylo veřejné počítadlo dříve zapnuté
- **Výchozí šablona – live region už nebere role serverovým hláškám a sidebar má skutečný landmark-backed nadpis** – `#a11y-live` se nově používá jen pro čistě klientská potvrzení jako kopírování obsahu, zatímco serverové success/error hlášky si ponechávají vlastní `role="status"` nebo `role="alert"`; postranní panel má současně vlastní skrytý nadpis `Postranní panel`, takže je dohledatelný i při navigaci po nadpisech
- **Výchozí šablona – veřejné view jsou čistší a bez zbytečných inline stylů** – spacing a layout styly v blogu, statických stránkách, chatu, formulářích, anketech a dalších veřejných modulech se přesunuly do `public.css`, takže theme vrstva je konzistentnější a audit nově hlídá, aby se staré inline layout styly nevrátily

## [3.2.2] – 2026-03-31

### Změněno
- **HTML editor – shortcode `[code]...[/code]` má univerzálnější akci kopírování** – kopírovatelný blok už nezobrazuje horní popisek a tlačítko je nově až pod obsahem s neutrálním textem `Kopírovat do schránky`, takže se hodí i pro jiné texty než jen ukázky kódu
- **Content/media picker – vkládané obrázky už automaticky nepřidávají popisek z názvu média** – HTML vložení obrázku z knihovny médií nově zachová `alt` atribut, ale nevytváří `figcaption`; pokud médium nemá vyplněný alternativní text, vloží se bezpečné `alt=""`, které lze v editoru ručně doplnit

## [3.2.1] – 2026-03-31

### Změněno
- **HTML editor – PDF náhled nově používá bezpečný same-origin preview endpoint** – snippet `[pdf]...[/pdf]` u veřejných PDF z knihovny médií nově nevkládá do `iframe` přímou veřejnou URL, ale interní endpoint `media/preview.php`, který dovolí vložení jen z vlastního webu; tím se opravuje zobrazení PDF na stránkách s tvrdou anti-embed ochranou a současně fungují i dříve vložené PDF snippety se starší cestou `/uploads/media/...`
- **Veřejný obsah – CSP znovu povoluje legitimní externí iframe a audio/video embedy** – veřejné odpovědi nově přes Content Security Policy výslovně povolují externí `iframe` a externí audio/video média v obsahu, aniž by se oslabila ochrana `frame-ancestors`; embedy jako Castos tak znovu fungují, pokud je cílová služba sama dovolí

## [3.2.0] – 2026-03-31

### Přidáno
- **HTML editor – PDF náhled jako nový obsahový snippet** – HTML obsah nově podporuje shortcode `[pdf]...[/pdf]`, který na veřejném webu vykreslí přístupný náhled PDF s titulkem a odkazem `Otevřít PDF samostatně`; content/media picker zároveň nově u veřejných PDF z knihovny médií nabízí akci `Vložit PDF náhled`
- **HTML editor – kopírovatelné bloky obsahu přes shortcode `[code]...[/code]`** – veřejný renderer umí z HTML obsahu vytvořit kopírovatelný blok s tlačítkem `Kopírovat do schránky`, potvrzením přes live region a bezpečným zkopírováním textu do schránky bez nutnosti ručního označování

### Změněno
- **Správa modulů – první uložení už neschovává právě povolený modul zpět do vypnutého stavu** – obrazovka `admin/settings_modules.php` nově po uložení používá PRG redirect a flash zprávu místo renderu ve stejné POST odpovědi, takže se checkboxy po prvním uložení načtou z opravdu uloženého stavu a success hláška se zobrazí jen jednou
- **Formuláře – preset Nahlášení chyby už při editaci nepřichází o pole pro potvrzovací e-mail** – create flow presetů nově zachovává přesné interní názvy polí z definice šablony místo jejich zbytečného slugování, takže se formulář po vytvoření dá znovu uložit i bez změn a potvrzovací e-mail zůstane navázaný na správné e-mailové pole; současně je doplněná zpětná kompatibilita pro dříve vytvořené preset formuláře, aby dál fungovaly i jejich placeholdery v potvrzovacích zprávách

## [3.1.1] – 2026-03-31

### Změněno
- **Content/media picker – fotogalerie se ve vyhledávání znovu správně zobrazují** – hledání v administraci nově vrací publikovaná galerie podle názvu, popisu i slugu bez tichého výpadku způsobeného dotazem na neexistující sloupec `cms_gallery_albums.excerpt`; picker tak znovu nabízí i akci `Vložit fotogalerii` pro existující alba
- **Blogy – přehled blogů nabízí rychlé odkazy na správu článků, kategorií a štítků** – sloupec `Akce` v administraci blogů nově vede přímo na články konkrétního blogu, jeho kategorie a štítky, takže se správce dostane ke kompletní blogové správě bez dalšího hledání v menu
- **Přístupnost administrace – převody Stránka → Článek a Článek → Stránka už nečtou dekorativní šipky** – tlačítka pro převod obsahu v přehledu statických stránek a blogových článků ponechávají šipku jen jako vizuální vodítko a nově ji skrývají před čtečkami obrazovky, takže asistivní technologie hlásí jen samotnou akci

## [3.1.0] – 2026-03-31

### Přidáno
- **Blogy – statické stránky přiřazené ke konkrétnímu blogu** – stránky lze nově přiřadit k jednomu blogu, řadit je samostatně v rámci daného blogu a zobrazovat je na veřejném blogu jako vlastní obsahovou vrstvu s adresami `/{blog-slug}/stranka/{page-slug}`; převod článku na stránku nově zachovává příslušný blog a blogové pořadí stránek

### Změněno
- **Blogy – hlavní adresa blogu nově zobrazuje navigaci blogových stránek nad články** – pokud má blog přiřazené statické stránky, veřejný index blogu nad featured článkem a výpisem zobrazí jejich samostatnou navigaci s vlastním nadpisem, ale bez automatického vykreslení obsahu některé stránky
- **Blogy – detail blogové stránky funguje jako samostatná stránka se zpětným odkazem na blog** – otevření konkrétní blogové stránky zobrazí jen obsah dané stránky v běžném page layoutu a pod ním odkaz `Zpět na blog`, bez featured článku, seznamu článků a sekundárních blogových bloků
- **Přístupnost – hlavní navigace webu má nově i nadpis pro čtečky obrazovky** – veřejná hlavní navigace je vedle landmarku dohledatelná také přes `aria-labelledby` napojené na skutečný skrytý nadpis `Hlavní navigace`

## [3.0.0] – 2026-03-31

### Přidáno
- **Blogy – volitelný alternativní text loga blogu** – každý blog nově může mít vedle nahraného loga i samostatný alternativní text pro čtečky obrazovky a fallback při nenačtení obrázku; pokud pole zůstane prázdné, logo se na veřejném indexu blogu dál chová jako dekorativní obrázek a po odebrání loga se alt text automaticky vyprázdní

## [3.0.0-rc.11] – 2026-03-31

### Změněno
- **Blogy – změna blogu v editoru článku nově spolehlivěji pracuje s kategoriemi a štítky cílového blogu** – editor při přesunu jednoho článku do jiného blogu nově jasně rozlišuje uložený a cílový blog, už při prvním renderu serverově vyhodnocuje chybějící taxonomie a oprávněným správcům nabídne vytvoření chybějící kategorie nebo štítků přímo z editace článku; server-side validace zároveň odmítá podvržené kategorie a štítky mimo cílový blog
- **Veřejný index blogu – pomocná navigace je nově pod obsahem a s viditelnými nadpisy** – bloky `Další blogy webu`, `Hledání v blogu`, `Kategorie blogu`, `Štítky blogu`, `Archiv blogu` a aktivní autor se na hlavní stránce blogu nově zobrazují až pod featured článkem, výpisem a stránkováním a každá oblast má skutečný viditelný nadpis napojený přes `aria-labelledby`, takže ji čtečky obrazovek najdou jak podle landmarků, tak podle nadpisů

## [3.0.0-rc.10] – 2026-03-31

### Změněno
- **Blogy – změna blogu v editoru článku nově respektuje taxonomie cílového blogu** – při přesunu jednoho článku přes běžnou editaci se nově automaticky přenesou odpovídající kategorie a štítky, pokud v cílovém blogu existují, editor dovolí ručně vybrat jiné existující taxonomie cílového blogu a správce taxonomií může přímo z editoru vytvořit chybějící kategorii nebo štítky; server-side validace zároveň brání podvrženému přiřazení nebo nepovolenému vytvoření taxonomií mimo cílový blog

## [3.0.0-rc.9] – 2026-03-31

### Přidáno
- **Blogy – doplnění zakladatele u starších blogů** – stránka `Tým blogu` nově umožňuje globálním správcům bezpečně doplnit chybějícího zakladatele (`created_by_user_id`) u starších blogů jako jednorázový auditní údaj, aniž by se tím automaticky měnilo členství v týmu blogu

### Změněno
- **Blogy – rozšířený úvod nově podporuje content/media picker** – pole `Rozšířený úvod blogu` při vytváření i úpravě blogu nově používá stejný picker odkazů, médií a snippetů jako ostatní HTML editory v administraci

## [3.0.0-rc.8] – 2026-03-31

### Přidáno
- **Blogy – přesun článků mezi blogy v administraci** – autoři s přístupem do více blogů mohou nově z přehledu článků hromadně přesouvat své vlastní články mezi blogy, do kterých smějí zapisovat; převod nabízí výběr cílového blogu, shrnutí vybraných článků, bezpečné vyrovnání kategorií a štítků a pro správce cílového blogu i vytvoření chybějících taxonomií nebo ruční mapování chybějících kategorií a štítků na existující taxonomie cílového blogu, přičemž celý přesun běží transakčně a ukládá revize změn
- **Média – public/private knihovna a bezpečnější workflow** – knihovna médií nově rozlišuje veřejné a soukromé soubory, odmítá nové SVG uploady, používá canonical media helpery a chráněné endpointy pro soukromá média a SVG, blokuje mazání používaných souborů a přidává náhradu souboru, rozšířená metadata, bulk akce a filtrování
- **Ankety – veřejné hledání, SEO a revize** – modul ankety nově podporuje fulltextové hledání a stránkování na veřejném indexu, `meta title` a `meta description` v editoru, širší revize včetně termínů a možností, redirect starého slugu na nový canonical tvar a sjednocenou veřejnou viditelnost napříč widgetem, vyhledáváním, sitemapou i shortcode embedy
- **Podcasty – chráněné asset endpointy a structured data** – cover pořadu, artwork epizody i lokální audio nově tečou přes kontrolované endpointy místo přímých `/uploads/podcasts/...` cest, skryté pořady už nezpřístupní ani své soubory a veřejný detail pořadu i epizody nově doplňuje structured data
- **Novinky – veřejné hledání, SEO a redirecty** – modul Novinky nově podporuje fulltextové hledání na veřejném indexu, stránkování i při aktivním dotazu, `meta title` a `meta description` v editoru, `NewsArticle` structured data na detailu a redirect starého slugu na nový canonical tvar
- **Chat – moderovaný veřejný stream a inbox workflow** – chat nově funguje jako moderovaná veřejná nástěnka; nové zprávy se ukládají jako `Ke schválení`, veřejně se zobrazují až po ručním schválení, veřejný výpis podporuje hledání, řazení a stránkování a detail zprávy v administraci nově nabízí interní poznámku, historii změn a odpověď odesílateli e-mailem
- **FAQ – veřejné hledání, přepínání zobrazení a strukturovaná data** – znalostní báze nově umí fulltextové hledání, filtr podle kategorie, stránkování, přepínání `Přehled karet / Rozbalené odpovědi`, související otázky na detailu a `FAQPage` strukturovaná data pro vyhledávače
- **Multiblog – týmy blogů a jemnější oprávnění** – každý blog může mít vlastní tým autorů a správců blogu; autoři nově vidí jen přidělené blogy a správa kategorií a štítků se umí omezit jen na konkrétní blog
- **Multiblog – per-blog metadata a veřejný index** – blogy nově podporují `meta title`, `meta description`, RSS podtitulek, rozšířený intro blok, výchozí komentáře pro nové články, počet položek v RSS feedu a doporučený článek přímo na indexu blogu
- **Multiblog – hledání, archiv a current-blog widget kontext** – veřejný index blogu nově umí hledání v rámci konkrétního blogu, archiv po měsících a widget „Nejnovější články“ se umí na blogových stránkách přepnout na právě otevřený blog
- **Vývěska – hledání, filtry a stránkování** – veřejný výpis vývěsky nově umí hledání, filtr podle kategorie, filtr podle období vyvěšení, přepínání `aktuální / archiv / vše` a stránkování, takže je použitelnější i pro větší úřední desku nebo delší archiv
- **Ke stažení – katalog verzí a rozšířená metadata** – položky ke stažení nově podporují domovskou stránku projektu, datum vydání, požadavky a kompatibilitu, SHA-256 checksum, skupiny verzí, počítadlo stažení a příznak doporučené položky
- **Ke stažení – veřejné hledání, filtry a stránkování** – veřejný katalog nově umí hledání, filtrování podle kategorie, typu, platformy, zdroje a doporučených položek a také stránkování výpisu
- **Jídelní lístky – scope archiv a structured data** – modul jídelních a nápojových lístků nově umí veřejné hledání, přepínání `Platí nyní / Připravujeme / Archivní / Všechny lístky`, stránkování archivu, tisk detailu a structured data typu `Menu`
- **Galerie – veřejné hledání, stránkování a detail fotografie** – galerie nově umí hledání v přehledu alb i uvnitř konkrétního alba, stránkování výpisů, související fotografie, zachování kontextu při návratu z detailu a akci `Kopírovat odkaz`
- **HTML editor – obsahové snippety pro moduly** – content parser a content picker nově podporují živé embedy formulářů a anket a teaser karty pro downloady, podcasty, epizody podcastů, místa, události a položky vývěsky

### Změněno
- **Veřejné formuláře a integrační krytí RC** – `build/http_integration.php` nově ověřuje reálné veřejné odeslání formuláře včetně CAPTCHA, nepovolené přílohy, cleanupu nahraného souboru při chybě a validního submitu se soukromě uloženou přílohou; veřejný formulář zároveň doplňuje lokální chybové texty a `aria-invalid` přímo u problémových polí a CAPTCHA
- **Nastavení webu – PRG workflow, field-level chyby a lehký HTTP integration běh** – stránka `Obecná nastavení` nově používá samostatný POST handler `admin/settings_save.php`, ukládá změny atomicky až po plné validaci včetně branding uploadů, vrací se přes PRG zpět na `settings.php` a u validovaných polí zobrazuje lokální field-level chyby s `aria-invalid`; vedle `build/runtime_audit.php` je nově k dispozici i lehký request/response ověřovací skript `php build/http_integration.php` pro kritické POST flow
- **Vývěska – přísnější validace kalendářních dat** – editor položek vývěsky nově odmítá neexistující kalendářní datumy v `Datum vyvěšení` a `Sundat dne` místo tichého přijetí rozbitých hodnot a HTTP integration běh to nově ověřuje i přes reálný POST request
- **Rezervace – veřejná validace kalendářního data** – veřejný booking flow nově neověřuje jen formát `RRRR-MM-DD`, ale i skutečně existující kalendářní datum; neplatný den se už potichu nepřenormalizuje na jiný termín rezervace
- **Nastavení webu – bezpečnější upload loga a favicony** – branding uploady v administraci už nepřijímají nové SVG soubory, backend vynucuje velikostní limity pro faviconu i logo a při chybě uploadu vrací čitelnější validační hlášku místo tichého selhání
- **UTF-8 a diakritika – migrační logy a audit copy** – opravené rozbité texty v `migrate.php`, `build/runtime_audit.php` a legacy komentovaném bloku v editoru článků, aby administrace i migrační výstupy držely korektní českou diakritiku
- **Blogy – auditní stopa zakladatele a automatické přiřazení správce** – nově založené blogy si ukládají `created_by_user_id`, přehled blogů i stránka `Tým blogu` ukazují evidovaného zakladatele a create flow, WordPress import i eStránky import nově automaticky přiřazují zakladatele do týmu blogu jako `manager`; starší blogy bez této historie zůstávají záměrně s prázdnou hodnotou, aby auditní stopa nebyla zpětně vyplněná nepravdivě
- **WCAG 2.2 – field-level chybové stavy ve formulářích administrace** – editory obsahu, uživatelské formuláře, profil a newsletter nově doplňují `aria-invalid`, lokální chybové texty a přesnější `aria-describedby` přímo u problémových polí; administrace tak lépe identifikuje chyby i pro asistivní technologie a drží konzistentnější formulářový pattern napříč modulem
- **Profil uživatele – bezpečnější uložení při 2FA a avataru** – při chybě ověření TOTP se profil nově neuloží jen částečně a upload avatara se provede až po finální validaci, takže nevzniká riziko nechtěné změny profilu nebo ztráty původního avataru při neúspěšném zapnutí dvoufázového ověření
- **Quality review – přísnější validace editorů a transakční ukládání rezervací** – ukládání stránek, jídelních lístků, blogových článků, podcast epizod a anket nově odmítá neplatné datumy a časy místo tichého ignorování rozbitých hodnot; zdroje rezervací navíc ukládají otevírací dobu, sloty a blokovaná data uvnitř databázové transakce, takže při chybě nezůstane rozpracovaný nekonzistentní stav
- **Podcasty – show visibility, redirecty, revize a feed kvalita** – pořady nově podporují vlastní publikační stav `zobrazit na webu`, změny slugu pořadů i epizod ukládají redirecty, editor obou entit vede na historii revizí, veřejné i admin přehledy používají stránkování a RSS feed nově publikuje přesnější `managingEditor` a délku lokálního `enclosure`
- **Novinky – public visibility, revize a export/import** – veřejný web, vyhledávání i sitemapa nově respektují `status`, `deleted_at` a `unpublish_at`, revize novinek zachycují i plánované skrytí, interní poznámku a SEO pole a export/import drží stejnou sadu polí jako aktuální schéma `cms_news`
- **Chat – soukromí, spam ochrana a automatický úklid** – veřejný chat už nezobrazuje e-mail ani web autora, formulář odmítá zprávy s URL, inbox umí veřejnou viditelnost `ke schválení / zveřejněné / skryté`, bulk akce a stránkování a `cron.php` nově umí volitelně mazat staré vyřízené chat zprávy podle nastavení `chat_retention_days`
- **FAQ – admin workflow, SEO a migrace** – editor FAQ nově obsahuje `meta title` a `meta description`, přehled FAQ umí filtr podle kategorie, změna slugu ukládá redirect, revize zachycují i kategorii a SEO metadata a `install.php` i `migrate.php` nově drží stejné FAQ schéma včetně `parent_id`, `meta_title` a `meta_description`
- **Blogové URL a feedy** – při změně slug blogu se staré adresy i per-blog RSS feed bezpečně přesměrují na nový canonical tvar
- **Export / import** – balíčky nově zahrnují i blog membership, redirecty starých slugů, rozšířená metadata blogů a featured flag článků
- **Multiblog – přehlednější správa týmů** – obrazovka `Tým blogu` nově ukazuje i další blogy daného uživatele, přehled uživatelů zobrazuje jejich blogová přiřazení a správa blogů přidává i počet členů týmu u každého blogu
- **Dokumentace** – README nově drží jen vysokou úroveň instalace, provozu a přehledu funkcí; detailní administrátorské workflow pro multiblog, formuláře a podcasty je přesunuté do `docs/admin-guide.md`
- **Vývěska – veřejná viditelnost a bezpečnost příloh** – položky se nově na veřejném webu zobrazí až od `Data vyvěšení`, budoucí položky nejsou vidět ani na homepage, ve widgetech, vyhledávání a sitemapě a neveřejné přílohy už nestáhne libovolný přihlášený účet, ale jen správce obsahu nebo schvalovatel
- **Vývěska – revize, redirecty a formulářové vedení** – úpravy položek se nově zapisují do historie revizí, změna slugu ukládá redirect na nový canonical tvar a editor položky dostal i typovou nápovědu a kontextovější veřejný odkaz
- **Ke stažení – admin workflow a bezpečnost souborů** – správa downloadů nově nabízí širší filtry v administraci, historii revizí, redirect po změně slugu a přísnější přístup k neveřejným souborům; ty už nestáhne libovolný přihlášený účet, ale jen správce obsahu nebo schvalovatel
- **Ke stažení – widgety, vyhledávání a detail položky** – widget posledních položek, interní vyhledávání i veřejný detail položky teď respektují doporučené položky, datum vydání, skupiny verzí a praktická metadata katalogu
- **Jídelní lístky – platnost, redirecty a revize** – aktuální výpis teď respektuje `Platí od / do`, admin přehled nabízí filtry podle typu a časové platnosti, změna slugu ukládá redirect, formulář i seznam vedou na historii revizí a detail lístku vrací návštěvníka zpět do správného archivního kontextu
- **Galerie – viditelnost, redirecty a revize** – skrytá alba a fotografie se nově neukazují ani přes detail, vyhledávání a sitemapu, změna slugu ukládá redirect, editor alba i fotografie vede na historii revizí a widgety i veřejné výpisy už neodkazují přímo do `/uploads/gallery/`

## [3.0.0-rc.7] – 2026-03-30

### Přidáno
- **Release assety** – release ZIP nově vždy přibaluje také `docs/admin-guide.md` do složky `docs/admin-guide.md`, takže administrátorský návod je součástí každého distribučního balíčku a zůstává dostupný i mimo repozitář

### Změněno
- **README.md** – kompletní revize: srozumitelnější jazyk pro administrátory, obsah (TOC), sjednocená sekce Konfigurace (SMTP, GA4, režim údržby, GitHub token, privátní úložiště), nové sekce Zálohování a údržba, Řešení problémů a ukázková Nginx konfigurace; opraveny chybějící PHP rozšíření `mbstring` a `zip` v požadavcích; odstraněny duplicitní sekce a changelogový tón; detailní popis modulů přesunut do `docs/admin-guide.md`
- **Patička webu** – odkaz na Kora CMS v patičce nově směřuje na oficiální GitHub repozitář `https://github.com/vlcekapps/Koracms` místo původní domény projektu

## [3.0.0-rc.6] – 2026-03-30

### Přidáno
- **Formuláře v hlavní navigaci** – veřejné formuláře mohou nově dostat vlastní položku v `Navigaci webu`; editor formuláře má přepínač `Zobrazit formulář v navigaci webu`, sjednocená navigace je umí řadit mezi moduly, blogy a stránky a veřejný detail formuláře nově správně drží aktivní stav menu

### Opraveno
- **Runtime audit** – `menu_admin_guardrails` nově hlídá, že se formuláře z `Navigace webu` neztratí a že editor formuláře neztratí přepínač `show_in_nav`

## [3.0.0-rc.5] – 2026-03-30

### Změněno
- **Statické stránky a navigace** – druhé samostatné pozicování statických stránek bylo odstraněno; `Navigace webu` je teď jediná autorita pro veřejné pořadí menu i pro pořadí stránek v admin přehledu a původní `admin/page_positions.php` už slouží jen jako kompatibilitní přesměrování na sjednocenou správu navigace
- **Runtime audit** – guardrails pro stránky a navigaci nově hlídají jednotný model přes `Navigaci webu` a ověřují i redirect staré adresy `admin/page_positions.php`

### Opraveno
- **SMTP a doručitelnost e-mailů** – centrální `sendMail()` nově přidává hlavičky `Date` a `Message-ID`, správně kóduje UTF-8 předmět i tělo, používá explicitní `Content-Transfer-Encoding`, neposílá `EHLO localhost` a kontaktní formulář nově používá čitelnější předmět `Kontakt: ...` a `Reply-To` na návštěvníka; tím se snižuje riziko, že budou zprávy označené jako spam
- **Runtime audit** – nový `sendmail_header_guardrails` hlídá, že mail helper neztratí hlavičky, UTF-8 encoding ani vylepšený subject/reply-to u kontaktu

## [3.0.0-rc.4] – 2026-03-29

### Opraveno
- **Stažení fotek z eStránek na hostingu** – dávkový downloader nově umí bezpečně uložit stav i na hostingu, kde není zapisovatelný privátní storage mimo webroot; pokud primární batch úložiště selže, použije fallback v `uploads/tmp/estranky_photos/`
- **eStránky downloader** – batch metadata nově drží jen lehký stav dávky a informaci o použitém storage backendu, takže se po pádu nebo nedokončeném běhu umí bezpečně vyčistit a znovu spustit
- **Runtime audit** – `estranky_photo_guardrails` nově hlídá i fallback storage pro batch soubory

## [3.0.0-rc.3] – 2026-03-29

### Opraveno
- **Stažení fotek z eStránek** – `admin/estranky_download_photos.php` už neukládá celý seznam fotografií do session, ale používá lehký dávkový stav a privátní batch soubor; tím je odolnější vůči limitům hostingu a velkým galeriím
- **Kompatibilita hostingu pro eStránky downloader** – stahování fotek nově používá robustnější helper s podporou cURL fallbacku, takže není závislé jen na `file_get_contents()` a `allow_url_fopen`
- **Runtime audit** – nový `estranky_photo_guardrails` hlídá, aby downloader zůstal dávkový, nevkládal celé seznamy fotek do session a nepřišel o odolnější download helper

## [3.0.0-rc.2] – 2026-03-29

### Opraveno
- **Fresh install schéma** – `install.php` je znovu sladěný s aktuálními migracemi, takže čerstvá instalace už po prvním přihlášení nevyžaduje spuštění `migrate.php`, aby fungovaly stránky jako `admin/pages.php`
- **Mazání posledního blogu** – po smazání úplně posledního blogu se multiblog vrátí do čistého stavu a další nově vytvořený blog znovu začíná od `id = 1`; stejně se při prázdném stavu srovnají i navázané blogové tabulky pro články, kategorie, štítky a komentáře
- **Runtime audit** – nový `install_schema_guard` hlídá drift mezi `install.php` a `migrate.php` a blog guardrails nově kryjí i reset čítače po smazání posledního blogu

## [3.0.0-rc.1] – 2026-03-29

### Přidáno
- **Automatická XML sitemapa** – veřejná adresa `sitemap.xml` nově funguje jako čistá canonical URL pro sitemapu, ale obsah se dál generuje dynamicky přes `sitemap.php`; sitemapa teď zahrnuje i veřejné formuláře a lépe respektuje aktuální publikační stav statických stránek, galerií a dalších veřejných modulů
- **Blogy – globální i samostatné RSS feedy** – globální `feed.php` zůstává pro celý web a každý blog v multiblog režimu má nově i vlastní feed přes `feed.php?blog=slug`; blogový feed používá vlastní název, popis a self odkaz a nepromíchává do sebe novinky z celého webu
- **Podcasty – feed metadata a limity pořadů** – každý podcastový pořad nově umí nastavit počet epizod v RSS feedu, krátký podtitul pro katalogy, vlastníka feedu a jeho e-mail, explicit režim, typ pořadu `episodic / serial` a příznak `complete`; epizody nově podporují podtitul, číslo série, typ `full / trailer / bonus`, vlastní explicit režim a možnost skrýt konkrétní epizodu z RSS feedu
- **Podcasty – artwork pořadů i epizod** – každý podcastový pořad může mít nově vlastní volitelný cover a každá epizoda i svůj samostatný obrázek; cover pořadu i epizody používají čtvercový `JPG` nebo `PNG` v rozmezí `1024×1024` až `3000×3000 px`, veřejný detail epizody umí fallback na cover pořadu a RSS feed nově publikuje i `<itunes:image>` pro jednotlivé epizody
- **Form Builder 2.0 – základ pro skutečně použitelné formuláře** – modul `Formuláře` nově podporuje pole `radio`, `url`, `file`, `více voleb`, `souhlas` a `skryté pole`, per-field nápovědu, placeholder, výchozí hodnotu, omezení typů a velikosti souborů a per-form nastavení pro text tlačítka, notifikační e-mail, předmět notifikace, interní redirect a volitelný honeypot
- **Šablona `Nahlášení chyby`** – v přehledu formulářů lze jedním kliknutím založit hotový issue-report formulář s doporučenými poli pro závažnost, dotčené oblasti, prohlížeč a zařízení, kroky k reprodukci, očekávané a skutečné chování, dopad na práci, přílohu a souhlas se zpracováním údajů
- **Form Builder 2.0 – podmínky, více příloh a potvrzení odesílateli** – formuláře nově umí potvrzovací e-mail odesílateli, výběr e-mailového pole pro odpověď, vlastní předmět a text potvrzení s placeholdery, živou ukázku potvrzovacího e-mailu v administraci, více souborů u příloh, šířku polí v rozvržení formuláře a podmíněné zobrazování polí přes `Zobrazit jen když` s operátory `je vyplněno`, `je prázdné`, `rovná se`, `nerovná se`, `obsahuje` a `neobsahuje`
- **Form Builder 2.0 – sekce, rozložení a navazující kroky po odeslání** – formuláře nově podporují sekce s vlastním mezititulkem a nápovědou, rozložení polí po řádcích a šířkách a také volbu, co se má stát po úspěšném odeslání: potvrzení na stejné stránce nebo interní přesměrování, včetně až dvou navazujících tlačítek
- **Form Builder 2.0 – pracovní inbox odpovědí** – odpovědi formulářů se nově chovají jako malý helpdesk: mají referenční kód, stavy `nové / rozpracované / vyřešené / uzavřené`, prioritu, štítky, přiřazení řešiteli, interní poznámku, detail jedné odpovědi, interní historii změn a možnost odpovědět odesílateli přímo z detailu hlášení
- **Další hotové šablony formulářů** – k issue-reportingu přibyly i preset šablony `Návrh nové funkce`, `Žádost o podporu`, `Obecný kontaktní formulář` a `Nahlášení problému s obsahem`
- **Form Builder 2.0 – GitHub issue bridge a webhooky** – z detailu odpovědi lze nově vytvořit GitHub issue, otevřít připravený návrh na GitHubu nebo ručně napojit existující issue; formuláře současně podporují webhooky po odeslání, změně workflow, odpovědi odesílateli a po vytvoření nebo napojení GitHub issue
- **Form Builder 2.0 – jemnější ticket workflow** – přehled odpovědí nově podporuje filtry `Jen moje`, `Nepřiřazené` a `S GitHub issue` a detail hlášení přidává rychlé kroky `Převzít řešení`, `Označit jako rozpracované`, `Označit jako vyřešené` a `Uzavřít hlášení`
- **HTML content tools – Media Picker 2.0** – content picker nově umí náhledy výsledků, vkládání fotografií a galerií podle typu obsahu, přímý odkaz ke stažení a chytřejší akce pro audio a video obsah
- **Widgety – rozšířený registr a lepší vazba na moduly** – widget systém nově nabízí i bloky `Ke stažení`, `FAQ`, `Zajímavá místa`, `Nejnovější epizody podcastu` a `Vybraný formulář`, přidávání widgetu rovnou respektuje cílovou zónu a widget `Vybraný formulář` je dostupný jen tehdy, když existuje alespoň jeden aktivní veřejný formulář
- **Multiblog – volitelné logo blogu** – každý blog nově může mít vlastní volitelné logo, které se zobrazuje nad popisem na jeho veřejném indexu; administrace umí logo nahrát, zobrazit, odebrat a při smazání blogu i uklidit, a kompatibilita je dopsaná i do install/migrate a export/import workflow

### Opraveno
- **Veřejné uploady obrázků mimo knihovnu médií** – starší upload helpery a admin formuláře už nepřijímají SVG pro blog loga, obrázky vývěsky, preview obrázky downloadů, obrázky událostí a míst ani autorské avatary; tím se snižuje riziko XSS a aktivního obsahu z veřejných `uploads/` adresářů a nový runtime guardrail hlídá, aby se SVG do těchto cest nevrátilo
- **Audit a WCAG regressions po velké integrační vlně** – opraven pád detailu novinky, sjednocen wording návratu z detailu autora, doplněna objevitelnost řazení statických stránek a zpřesněn wording galerie v administraci
- **Navigace webu a statické stránky** – `Navigace webu` je teď skutečná autorita pro veřejné pořadí menu, ukládání sjednoceného pořadí doplňuje chybějící položky bezpečně na konec, stránka hlásí změny přes live region a copy u statických stránek nově jasně odlišuje hlavní navigaci od základního pořadí stránek
- **Hromadné akce a tabulky v administraci** – odstraněno duplicitní `id="check-all"` v jídelním lístku a bulk helper nyní bezpečně podporuje více tabulek se samostatným výběrem položek
- **Modální dialogy Blogy a Widgety** – doplněny přístupnostní atributy, popis dialogu, řízení `aria-expanded`, návrat fokusu a zamykání scrollu podkladové stránky
- **Přihlašovací obrazovky administrace** – `login.php` a `login_2fa.php` nově používají skip link a sjednocený focus-visible základ, aby se nechovaly hůř než zbytek administrace
- **Portable theme package a runtime audit** – opraven falešný poplach u CSS validace (`scroll-behavior` vs. zakázané `behavior:`), aktualizován roundtrip test šablon a SMTP kontrola se v lokálním prostředí bez explicitní konfigurace korektně hlásí jako `SKIP`
- **Formuláře a odpovědi** – odpovědi i CSV export nově správně zobrazují více voleb, souhlas a nahrané soubory; mazání formuláře nebo odpovědi uklízí i související uploady a runtime audit hlídá create/edit/submissions flow včetně veřejné stránky formuláře
- **WCAG 2.2 ve Form Builderu** – přehled formulářů už nepoužívá tlačítka se čteným znakem `+`, editor polí dává opakovaným checkboxům jasný kontext pro čtečky a veřejný formulář už nečte název formuláře duplicitně přes vnější legendu
- **Blog administrace bez existujícího blogu** – kategorie a štítky už bez vytvořeného blogu nejsou nabízené v levém menu, jejich obrazovky se bezpečně vrací na správu blogů a přehled článků místo slepé cesty jasně navede na vytvoření prvního blogu
- **Widgety a jejich guardrails** – `Doporučený obsah` už skutečně umí blog, vývěsku, anketu i newsletter, vyhledávací widget negeneruje duplicitní `id`, kontaktní údaje už nejsou zbytečně svázané s modulem `Kontakt` a runtime audit nově hlídá i nové widgety a jejich WCAG/UX regresní vzory

### Přidáno
- **Jednotná správa navigace** (`admin/menu.php`) – nahrazuje oddělené stránky „Pozice modulů" a „Pozice stránek"; moduly, statické stránky a blogy v jednom přetahovatelném seznamu; libovolné pořadí a kombinace; drag & drop + Ctrl+šipka + tlačítka Nahoru/Dolů; nastavení `nav_order_unified` s fallbackem na starý systém
- **Widget systém** (`admin/widgets.php`) – přetahovatelné bloky do 3 zón (homepage, sidebar, footer); 12 typů widgetů (úvodní text, nejnovější články, novinky, doporučený obsah, vývěska, nadcházející události, anketa, newsletter, galerie, vlastní HTML, vyhledávání, kontakt); drag & drop s WCAG klávesnicovým fallbackem (Ctrl+šipka); inline nastavení každého widgetu (počet položek, blog, album, zdroj); widgety respektují stav modulů – nedostupný modul = nedostupný widget; migrace existujících homepage nastavení do widgetů; `lib/widgets.php` s render funkcemi per typ a zónu
- **Koš (soft delete)** (`admin/trash.php`) – smazání článků, novinek, stránek, událostí a FAQ je nyní přesun do koše; admin stránka s přehledem smazaných položek, tlačítky „Obnovit" a „Trvale smazat"; sloupec `deleted_at` na klíčových tabulkách; smazané položky se nezobrazují v admin přehledech ani na veřejném webu
- **Drag & drop řazení** – přetahování položek myší s AJAX uložením pořadí; WCAG 2.2 fallback: stávající tlačítka Nahoru/Dolů + klávesnicový Ctrl+šipka; sdílená JS funkce `sortableJs()` v `lib/ui.php`; AJAX endpoint `admin/reorder_ajax.php`; nasazeno na pozice stránek a pořadí blogů
- **Cron endpoint** (`cron.php`) – plánované úlohy: publikování článků s `publish_at`, čištění expirovaných rate-limit záznamů, mazání starých temp souborů (24h) a audit logů (90 dní); spuštění přes CLI nebo HTTP s tokenem (`CRON_TOKEN` v config.php); výsledky se logují do `cms_log`
- **WebP konverze** – automatické generování WebP verze při uploadu obrázků (galerie, články, eStránky import); helper funkce `generateWebp()`, `webpUrl()` a `pictureTag()` v `lib/gallery.php`; `<picture>` element s WebP source ve veřejných views
- **Prohlížeč audit logu** (`admin/audit_log.php`) – výpis `cms_log` s filtry podle akce, uživatele a data; stránkování; sloupec `user_id` přidán do logu pro identifikaci autora akce
- **Záloha databáze** (`admin/backup.php`) – export všech CMS tabulek jako SQL soubor ke stažení; generuje CREATE TABLE + INSERT přes PDO; bez závislosti na mysqldump
- **301/302 přesměrování** (`admin/redirects.php`) – správa přesměrování starých URL na nové; tabulka `cms_redirects` s počítadlem přístupů; automatická kontrola na každém requestu; podpora 301 (trvalé) a 302 (dočasné); užitečné po importu obsahu nebo změně slug adres
- **Multiblog** – podpora více blogů v jedné instalaci; nová tabulka `cms_blogs`; každý blog s vlastním názvem, slugem, popisem, kategoriemi a tagy; komentáře, schvalování a oprávnění zůstávají společné; admin správa blogů (`admin/blogs.php`); selektor blogu ve formuláři článku a filtry v přehledu; kategorie a tagy scoped per blog; dynamický URL routing přes `blog_router.php` s catch-all .htaccess pravidly; veřejná navigace s položkou za každý blog; popis blogu na veřejném indexu; per-blog RSS feed (`/feed.php?blog=slug`); zpětná kompatibilita – s jedním blogem se chování a URL nemění
- **Importéry – blog selektor** – WordPress i eStránky importéry umožňují vybrat cílový blog nebo vytvořit nový z importu; název a popis z importu se zapisují do blogu (ne do globálních nastavení webu); kategorie a tagy scoped per blog
- **eStránky photo downloader – výběr cílového alba** – volba „Nikam (do kořene galerie)" nebo existující album jako parent pro importovanou strukturu
- **Filtr článků podle kategorie** (`admin/blog.php`) – select „Všechny kategorie" / „Bez kategorie" / konkrétní kategorie vedle vyhledávání; kombinovatelné s textovým hledáním; admin rychle najde články bez přiřazené kategorie
- **E-mailové notifikace** – 3 nové notifikační funkce v `lib/mail.php`: odeslání formuláře, obsah čekající na schválení, nová zpráva v chatu; nastavení v admin (Nastavení → E-mailové notifikace) s checkboxy pro každý typ; formulář a pending obsah výchozí zapnuté, chat výchozí vypnutý

### Opraveno
- **Confirm dialogy nefungovaly** – CSP s nonce ignoruje `unsafe-inline`, proto `onclick="return confirm()"` inline handlery nikdy nefungovaly; 36 výskytů ve 32 souborech nahrazeno za `data-confirm` atribut s globálním JS event handlerem v admin footer
- **Content reference picker – CSP nonce** – `<?= cspNonce() ?>` uvnitř heredoc `<<<HTML` se nevyhodnotilo; CSS nebylo aplikováno; opraveno na PHP concatenation
- **Sjednocení modulů** – WebP konverze přidána do všech upload helperů (board, place, download, podcast, avatar, logo); `unpublish_at` a `admin_note` doplněny do novinek, událostí a stránek (formuláře i save handlery); všechny moduly nyní konzistentní s blogem
- **Dvoufázové ověření (2FA TOTP)** – volitelné dvoufázové přihlášení přes TOTP (FreeOTP, Authy, Google Authenticator); aktivace v profilu přes QR kód nebo ruční zadání klíče; `lib/totp.php` s čistou PHP implementací RFC 6238 bez závislostí; `admin/login_2fa.php` pro zadání kódu po ověření hesla; sloupce `totp_secret` a `passkey_credentials` v `cms_users`
- **Centrální knihovna médií** (`admin/media.php`) – upload více souborů naráz; grid zobrazení s thumbnaily; filtr podle typu a vyhledávání; správa alt textů; kopírování URL do schránky; mazání s potvrzením; automatické thumbnail + WebP generování; tabulka `cms_media` indexuje všechny soubory
- **Vizuální diff revizí** (`admin/revisions.php`) – inline zvýrazňování změn: `<del>` pro odebrané a `<ins>` pro přidané části; sloučení starých/nových sloupců do jednoho „Změny"; skládání velkých diffů do `<details>`
- **Lazy loading + responsive obrázky** – `pictureTag()` automaticky přidává `loading="lazy"`; `generateResponsiveSizes()` vytváří varianty 400w, 800w, 1200w s WebP při uploadu článkových obrázků
- **Interní poznámky k obsahu** – sloupec `admin_note` na článcích, novinkách, stránkách a událostech; textarea v admin formuláři; viditelná jen v administraci, ne na veřejném webu
- **Upozornění na aktualizace** – admin dashboard kontroluje novou verzi přes GitHub API (1x za 24h, jen superadmin); vizuální oznámení s číslem nové verze
- **Naplánované zálohy** – cron.php automaticky vytváří denní SQL zálohu do privátního úložiště mimo webroot; rotace starších než 7 dní
- **Plánované zrušení publikace** – sloupec `unpublish_at` na článcích, novinkách, stránkách a událostech; formulářové pole v admin; cron.php automaticky skryje obsah po vypršení; WCAG 2.2 kompatibilní
- **runtime_audit.php** – přidány testy nových admin stránek: blogs, widgets, redirects, audit_log, backup, trash, menu, blog_cats, blog_tags
- **Šablona default** – opraveny hardcoded `/blog/index.php` odkazy v author.php, authors.php a home.php na dynamický `blogIndexPath()`
- **Content reference picker – čtečky** – `role="status"` na výchozí hlášce uvnitř skrytého dialogu způsobovalo nežádoucí oznamování čtečkami; výchozí text odstraněn, nastavuje se až při otevření dialogu
- **Bezpečnostní audit** – rate limiting na `subscribe_confirm.php` a `unsubscribe.php`; blokování `.env` a `.git/` v `.htaccess`; oprava user enumeration v `subscribe.php`; rate limiting na `search.php`; HSTS hlavička při HTTPS; GDPR: GA4 se načítá až po udělení cookie souhlasu
- **WCAG 2.2** – alt text na gallery covers a admin náhledech; autocomplete atributy na admin polích; focus-visible styly na veřejných formulářích a tlačítkách; duplicitní ID v `home.php`


- **WCAG 2.2 (1.3.5)** – doplněny `autocomplete` atributy na formulářová pole: `given-name`, `family-name`, `tel` v registraci, profilu a rezervacích; `email` v newsletteru, chatu a komentářích
- **Prázdné catch bloky** – doplněn `error_log()` do catch bloků v `search.php`, `sitemap.php`, `blog/index.php`, `unsubscribe.php`, `subscribe_confirm.php`, `admin/blog_form.php`, `admin/blog_save.php`, `admin/statistics.php`, `admin/content_reference_search.php`
- **`@` suppression** v `lib/presentation.php` – 6× `@unlink()` nahrazeno logovaným `unlink()` s `error_log()`
- **Runtime audit** – testovací INSERT pro `confirm_email` nyní nastavuje `confirmation_expires`; přidány testy `confirm_token_expired` a `confirm_token_valid`

### Přidáno
- **Sdílený stránkovací helper** (`lib/pagination.php`) – funkce `paginate()` a `renderPager()` nahrazují duplicitní stránkovací logiku v modulech; nasazeno na blog, news a polls
- **FULLTEXT vyhledávání** – 10 FULLTEXT indexů na klíčových tabulkách (články, novinky, stránky, akce, FAQ, vývěska, ke stažení, místa, ankety, jídelníčky); `search.php` využívá `MATCH … AGAINST` s relevančním řazením a automatickým LIKE fallbackem pro krátké dotazy nebo chybějící indexy
- **Dashboard widget „Naplánovaný obsah"** – admin dashboard zobrazuje články s `publish_at` v budoucnu; tabulka s názvem, datem publikace a odkazem na editaci
- **Tlačítko „Kopírovat odkaz"** – na detailových stránkách článků, novinek, událostí, vývěsky a míst; clipboard API s a11y feedbackem přes live region
- **Převod článek ↔ stránka** (`admin/convert_content.php`) – převod článku na statickou stránku a naopak; zachovává titulek, slug, obsah, datum; tlačítko v admin výpisu i editačním formuláři; confirm dialog; logování akce
- **Export fotoalba do ZIP** (`admin/gallery_export_zip.php`) – rekurzivní export alba včetně podalb do ZIP s hierarchickou strukturou složek; ZipArchive s fallback na streaming; UTF-8 české znaky; tlačítko „Export ZIP" v admin galerii; WCAG 2.2
- **Schvalovací workflow pro galerii** – sloupce `status` a `is_published` v `cms_gallery_albums` a `cms_gallery_photos`; filtr stavu v admin výpisech alb i fotografií; schvalovací tlačítko; bulk akce pro fotografie; veřejné stránky zobrazují jen publikovaný obsah; WCAG 2.2
- **Kontrola integrity souborů** (`admin/integrity.php`) – SHA-256 snapshot všech PHP souborů a `.htaccess`; porovnání detekuje nové, změněné a smazané soubory; varování na admin dashboardu pro superadminy; ochrana proti SEO spam injection a backdoorům
- **eStránky import – oprava hierarchie fotoalb** – správný klíč `iid` místo `directory` v `p_directories_lang` a `p_photos_lang`; import hierarchie alb s `parent_id`; konzistentní safe filename mezi importérem a downloaderem
- **eStránky stahování fotografií** (`admin/estranky_download_photos.php`) – stáhne originální fotografie z webu eStránek přes URL `/img/original/{id}/{filename}`; vytvoří thumbnaily; aktualizuje DB záznamy galerie; bezpečné pro opakované spuštění
- **eStránky importér** (`admin/estranky_import.php`) – import článků, sekcí (→ kategorie), fotoalb a fotografií z XML zálohy eStránek.cz; automatická base64 dekódování; pouze český jazyk (lang=1); deduplikace
- **WordPress importér** (`admin/wp_import.php`) – import článků s `<!--more-->` perex/content splitem, stránek, kategorií, tagů, komentářů a médií z WP SQL dumpu; automatická detekce prefixu, deduplikace, WP blokové komentáře se odstraní, dočasné tabulky se uklidí
- **Google Analytics 4 integrace** – nastavení GA4 Measurement ID v admin; gtag.js snippet se automaticky vloží do `<head>` veřejných stránek
- **Vlastní kód do head/footer** – dvě textová pole v admin nastavení pro libovolný HTML/JS kód do `<head>` a před `</body>` (náhrada za WPCode)
- **Modul Formuláře** – dynamický form builder s admin rozhraním pro definici formulářů a polí (text, email, tel, textarea, select, checkbox, number, date); veřejná stránka s CSRF, honeypot a CAPTCHA ochranou; prohlížení odpovědí v admin + CSV export; slug-based URL s .htaccess pravidlem; WCAG 2.2 kompatibilní šablona s autocomplete, aria-required a role="alert"
- **FAQ → Znalostní báze** – modul přejmenován v celém admin rozhraní; hierarchické kategorie s `parent_id` (neomezená hloubka); stromová navigace kategorií na veřejné stránce; drobečková navigace (breadcrumbs) na výpisu i detailu; filtrování podle kategorie včetně podkategorií; CSS pro breadcrumbs a stromovou navigaci; tlačítko „Kopírovat odkaz" na detailu
- **Revize obsahu** – tabulka `cms_revisions` ukládá snapshot textových polí před každou úpravou; `lib/revisions.php` s `saveRevision()` a `loadRevisions()`; napojeno na blog, novinky, stránky, události a FAQ; admin stránka `revisions.php` s kompletní historií změn; odkaz „Historie revizí" v editačních formulářích
- **Hromadné akce v admin výpisech** – generický `admin/bulk.php` handler s akcemi smazat / publikovat / skrýt; checkboxy a bulk action bar nasazeny na novinky, události, FAQ, vývěsku, ke stažení, místa, ankety a jídelníčky; UI helpery `bulkFormOpen()`, `bulkActionBar()`, `bulkCheckboxJs()` v `lib/ui.php`
- **Globální exception handler** (`db.php`) – neošetřené výjimky nyní zobrazí uživatelsky přívětivou chybovou stránku místo bílé obrazovky; v debug režimu (`display_errors=1`) zobrazí i stack trace
- **Runtime audit** – nové sekce `smtp_connectivity` (ověří SMTP připojení, STARTTLS, AUTH LOGIN) a `sendmail_return_check` (hlídá, že žádné volání `sendMail()` neignoruje návratovou hodnotu)

### Změněno
- **`db.php`** – rozdělení monolitu (4 400 řádků, 282 funkcí) do 10 tematických souborů v `lib/`: `definitions.php`, `comments.php`, `messages.php`, `presentation.php`, `gallery.php`, `content.php`, `filedownloads.php`, `ui.php`, `mail.php`, `stats.php`; `db.php` zůstává vstupním bodem (require_once), API je beze změny
- **CSP nonce** – všechny inline `<script>` a `<style>` tagy (veřejné i admin stránky) nyní používají per-request `nonce` atribut; `cspNonce()` funkce v `auth.php` generuje kryptograficky bezpečný nonce; `Content-Security-Policy` hlavička obsahuje `'nonce-…'` vedle stávajícího `'unsafe-inline'` (fallback pro starší prohlížeče)
- **Legacy role `collaborator`** – výchozí fallback v `normalizeUserRole()`, `loginUser()`, `currentUserRole()` a `admin/login.php` nahrazen odpovídajícími hodnotami (`public` / `admin`); role `collaborator` zůstává platnou hodnotou v DB schématu pro zpětnou kompatibilitu, ale již není nikde použita jako výchozí

### Opraveno
- **`sendMail()`** – přidána podpora SMTP autentizace (AUTH LOGIN) a šifrování (STARTTLS, SSL) přes konstanty `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE` v `config.php`; bez konfigurace se chová jako dříve (localhost:25)
- **Všechna volání `sendMail()`** nyní kontrolují návratovou hodnotu – uživatel vidí chybovou hlášku, pokud se e-mail nepodařilo odeslat (kontakt, registrace, newsletter, rezervace); interní notifikace (komentáře, admin) logují selhání do `error_log()`
- **Newsletter přihlášení** – nový stav `mail_error` v šabloně informuje uživatele o selhání odeslání potvrzovacího e-mailu
- **`config.sample.php`** – doplněna ukázková SMTP konfigurace
- **Kontaktní formulář** – INSERT do DB je nyní v try/catch, při selhání se zobrazí formulář s hláškou
- **Chat** – INSERT do DB je nyní v try/catch, při selhání se zobrazí formulář s hláškou
- **Kontaktní formulář** – odstraněna duplicitní sanitizace vstupů (dvojité `trim()` / `htmlspecialchars()`)

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
