# Vývoj nového modulu v Kora CMS

Tento dokument je praktický checklist pro návrh a implementaci nového modulu. Kora CMS zatím nemá oddělený plugin systém; modul je součást jádra, která musí být zapojená do instalace, migrací, administrace, veřejné části, testů, dokumentace a release guardrailů.

## Než začnete

- Sepište účel modulu, cílového správce, veřejné URL a zda modul bude mít veřejný detail, výpis, widgety, sitemapu, vyhledávání, RSS/ICS/export nebo přílohy.
- Vyberte nejbližší existující vzor: `Blog` pro komplexní obsah, `Novinky` pro jednoduchý obsahový modul, `Vývěska` pro přílohy a platnost, `Ankety` pro veřejnou interakci, `Rezervace` pro workflow s termíny.
- Rozhodněte, které role smí modul spravovat, zda bude modul sdílený pro celý web nebo navázaný na konkrétní blog, a jak se bude chovat při vypnutém modulu.
- Návrh musí počítat s WCAG 2.2 AA, bezpečností requestů, UTF-8 diakritikou a regresními testy ještě před psaním větší části kódu.

## Povinné integrační body

- Databáze: nové tabulky a sloupce přidejte do `install.php` i `migrate.php`; pokud veřejný kód očekává nový sloupec, doplňte i schema parity guardrail.
- Definice: doplňte module key do centrálního manifestu `coreModuleDefinitions()` v `lib/definitions.php`. Manifest drží labely, výchozí `module_*` hodnotu a kontextové příznaky `profile_managed`, `settings_configurable`, `settings_default`, `public_nav`, `public_nav_path`, `public_nav_order`, `settings_label`, `nav_label`, `widget_label`, `admin_label`, `content_reference_types` a `admin_paths`; `install.php`, `migrate.php`, `admin/settings_modules.php`, veřejná navigace, widgetové popisky, administrační 403 hlášky, modulové typy content pickeru a testy administračních vstupů se z něj odvozují automaticky. `settings_default` smí být jen `0` nebo `1`; veřejné moduly musí mít cestu začínající `/`, existující PHP entrypoint, odpovídající `isModuleEnabled('...')` bránu, kladné pořadí, pokrytí v některém `composer analyse:strict*` a `composer format:check*` skriptu a musí projít dynamickým HTTP smoke scénářem `public_module_navigation_http` ve vypnutém i zapnutém stavu, neveřejné moduly naopak nechávají veřejnou cestu prázdnou a pořadí `0`.
- Modulový audit považuje `coreModuleDefinitions()` za jediný zdroj známých module keys pro widgety, šablony, content picker, admin routy a `module_*` nastavení. Nový module key proto nepřidávejte do žádného dalšího seznamu v auditu; audit pouze chrání, aby omylem nezmizely stávající vestavěné moduly.
- Administrace: používejte `requireLogin()` nebo přesnější capability helper, u hlavních administračních přehledů modulu přidejte `requireModuleEnabled()` bez druhého parametru a jejich URL uveďte v manifestovém `admin_paths`; všechny formulářové, detailové a stav měnící admin soubory modulu zároveň doplňte do sdílené mapy `adminRouteModuleRequirements()` v `auth.php`, ze které vychází `adminRouteModuleRequirement()`, aby přímá URL nebo POST skončily při vypnutém modulu 403. Hlášku mapy odvozujte přes `adminRouteModuleDisabledMessage()` a manifestové `admin_label`, ne ručně psaným textem u každé routy. Stav měnící akce musí mít CSRF tokeny, PRG po POSTu, jasné flash hlášky, audit log a validaci všech ID proti správnému kontextu.
- Read-only administrační downloady, JSON exporty nebo CSV exporty odmítejte přes `requireReadOnlyHttpMethod()` ještě před capability nebo modulovým guardem, aby POST/PUT na vypnutý modul neskončil zavádějícím 403 místo bezpečného 405 s hlavičkou `Allow`.
- Veřejná část: veřejné entrypointy musí hned na začátku respektovat `isModuleEnabled()` pro svůj module key, používat sdílené 404 helpery, SEO metadata, canonical URL, bezpečné HTTP metody a noindex/no-store u neveřejných nebo tokenových stavů.
- Navigace a discoverability: zvažte zapojení do navigace webu, sitemapu, vyhledávání, widgetů, content pickeru, RSS/ICS/exportu a administrativních rychlých odkazů.
- Widgety a šablony: pokud widget používá `requires_module` nebo theme manifest `requires_modules`, hodnota musí být existující module key z `coreModuleDefinitions()`; `build/module_contract_audit.php` hlídá překlepy i neexistující modulové závislosti.
- Content picker: pokud nový modul přidává zdroje do `admin/content_reference_search.php`, zapište jeho request type a popisek do manifestového `content_reference_types`; `admin/content_reference_picker.php` i povolené typy search endpointu se z manifestu odvozují automaticky. V `isModuleEnabled()` používejte stejný module key jako v `coreModuleDefinitions()`; modulový audit tuto vazbu hlídá spolu s widgety a šablonami. Vypnutý modul se nesmí nabízet v typu pickeru, v nápovědě snippetů ani vracet výsledky z přímého JSON dotazu.
- Modulové brány: literálové `isModuleEnabled('...')` odkazy v aplikačních PHP souborech mimo `build/` a `vendor/` musí používat existující module key z `coreModuleDefinitions()`; překlepy zachytí `build/module_contract_audit.php`.
- Modulová nastavení: pokud musíte použít literálové `getSetting('module_...')` nebo `saveSetting('module_...')`, suffix za `module_` musí odpovídat existujícímu module key; audit hlídá překlepy v aplikačním kódu stejně jako u `isModuleEnabled()`.
- Revize a redirecty: u editovatelného obsahu s veřejnou URL preferujte historii revizí a redirect při změně slugu, aby se nerozbily staré odkazy.
- Dokumentace: doplňte `README.md`, `docs/admin-guide.md` a `CHANGELOG.md`; u většího modulu přidejte krátký workflow popis pro správce.

## Bezpečnostní pravidla

- Vstupy čtěte přes explicitní validaci; ID z requestu vždy ověřte proti databázi a oprávněnému kontextu.
- Redirecty z requestu nebo formuláře validujte pouze přes `internalRedirectTarget()` nebo existující bezpečný wrapper.
- Uploady dělejte přes sdílené helpery v `lib/uploads.php`; MIME typ, velikost, přípona a cílový adresář nesmí být ručně opsané v novém modulu.
- Externí URL normalizujte přes sdílené URL helpery, ne přes ruční přidávání `https://` nebo samotné `FILTER_VALIDATE_URL`.
- Souborové operace nesmí tiše používat `@unlink`, `@rename`, `@copy` ani `@mkdir`; použijte existující helper nebo logujte selhání přes `koraLog()` bez fyzické cesty.
- Do logů neukládejte celé e-mailové adresy, tokeny, webhook URL, obsah zpráv, uploadované soubory ani lokální fyzické cesty.
- Veřejné výstupy escapujte přes `h()` nebo zpracujte přes schválený renderer obsahu; HTML od správce nepropouštějte mimo stávající content renderer.

## WCAG 2.2 checklist

- Formuláře mají skutečné `label`, skupiny polí přes `fieldset` a `legend`, chybové hlášky přes `role="alert"` nebo `role="status"` a `aria-describedby` pouze na existující prvky.
- Navigace, vyhledávání, aside a významné sekce se pojmenovávají přes skutečný nadpis a `aria-labelledby`, ne samotným `aria-label`.
- Odkazy do nového okna používají viditelný text plus `newWindowLinkSrOnlySuffix()`, `target="_blank"` a `rel="noopener noreferrer"`.
- Tabulky mají `<caption>` nebo vazbu na skutečný nadpis; význam nesmí být nesen jen barvou, ikonou nebo pořadím.
- Dynamické ovládací prvky musí mít viditelný focus stav, klávesnicový fallback a nesmí po akci nečekaně ztrácet focus.
- Veřejné theme view šablony ověřte přes `build/theme_view_audit.php`, pokud modul přidává nové šablonové soubory.

## Testy a guardrails

- Minimální lokální sada před commitem: `php -l` nad změněnými PHP soubory, `php build/unit_tests.php`, `php build/runtime_audit.php`, `php build/http_integration.php` a `git diff --check`.
- Před větší modulovou změnou spusťte `composer ci:module-ready`; jde o pojmenovaný průchod přes základní CI, `build/module_contract_audit.php`, runtime audit a HTTP integraci.
- Nové schéma kryjte v `install.php`, `migrate.php` a schema parity auditu.
- Nový veřejně navigovatelný modul zahrňte do `coreModuleDefinitions()`; společný HTTP scénář `public_module_navigation_http` pak automaticky ověří jeho manifestovou cestu, redirect vypnutého modulu na homepage, HTML odpověď a stav zapnutého modulu. Nový samostatný veřejný endpoint zahrňte do HTTP integrace také tehdy, pokud má vlastní routu, hlavičky, redirect, souborovou odpověď nebo stav měnící workflow.
- Hlavní administrační přehled modulu musí být uvedený v `admin_paths` a přes `requireModuleEnabled()` vracet 403 při vypnutém modulu; formulářové, detailové a stav měnící endpointy doplňte do sdílené mapy `adminRouteModuleRequirements()`. Unit testy sekce `adminRouteModuleRequirement()` automaticky projdou všechny položky této mapy a ponechávají i několik reprezentativních testů normalizace cest. Modulový audit ověřuje, že mapované admin soubory existují, module key je známý, lookup funkce používá sdílenou mapu, manifestové `admin_paths` jsou v centrální mapě zahrnuté, 403 copy se odvozuje z `admin_label` přes `moduleAdminLabel()` a žádný modulový admin soubor nepředává duplicitní disabled hlášku jako druhý parametr `requireModuleEnabled()`. Každý mapovaný soubor má zároveň login guard přes `requireLogin()`, `requireSuperAdmin()`, `requireModuleEnabled()` nebo `requireCapability()`. Scénář `admin_disabled_modules_http` hlídá přímé URL z manifestu automaticky a zároveň ověřuje, že stav měnící admin endpoint vypnutého modulu vrací 403 a nic neuloží.
- Modulový zdroj v content pickeru musí mít manifestový záznam `content_reference_types` a projít scénářem `content_reference_disabled_modules_http`, který ověřuje skrytí vypnutého typu v UI i prázdný výsledek přímého search endpointu.
- Modulové HTTP scénáře, které testují pozitivní uložení, validaci nebo veřejné zobrazení konkrétního modulu, si musí před akcí explicitně nastavit svůj `module_*` přepínač. Test nesmí spoléhat na aktuální profil webu nebo lokální databázi, jinak může projít na localhostu a spadnout v čistém Full CI prostředí.
- Novou veřejnou šablonu zahrňte do theme view auditu, pokud obsahuje `section`, `article`, `nav`, `aside`, `table`, `fieldset`, `figure`, `role="search"` nebo odkazy do nového okna.
- Nový admin soubor musí být zahrnutý do některého `composer analyse:strict*` a `composer format:check*` skriptu; modulový audit to u souborů z `adminRouteModuleRequirements()` hlídá automaticky, takže nový formulářový, detailový nebo stav měnící endpoint nemůže zůstat mimo PHPStan a PSR-12 smoke check.
- Pokud modul přidá nový helper se samostatnými pravidly, přidejte unit test a runtime guardrail proti návratu k ruční duplicitní logice.

## Definition of done

Nový modul je připravený až tehdy, když má databázovou migraci i čistou instalaci, oprávnění, veřejné i administrační workflow, vypnutí přes moduly, dokumentaci, changelog, přístupnostní vazby, bezpečné hlavičky, regresní testy a zelený základní CI běh. Pokud některá část záměrně chybí, musí být v dokumentaci modulu uvedeno proč.
