# Appmarket: Accessibility Conformance Report

## Stav dokumentu

- Cíl: WCAG 2.2 AA.
- Rozsah: veřejný katalog Android aplikací, detail aplikace a vydání, stažení APK, update API, publikační API, administrační správa a lokální publisher.
- Dokument je technická modulová příloha ACR Kora CMS, nikoli právní certifikace.
- Automatizované kontroly jsou součástí `composer ci:module-ready`; ruční scénáře níže musí být provedeny před označením modulu za plně ověřený na konkrétní šabloně.

## Testované rozhraní

### Veřejný katalog

- katalog `/aplikace` včetně hledání a prázdného výsledku,
- detail aplikace `/aplikace/{slug}`,
- detail vydání `/aplikace/{slug}/verze/{versionCode}`,
- seznam vydání, snímky obrazovky, externí odkazy a ověřovací údaje APK,
- přímé stažení APK včetně `HEAD`, částečného `Range` požadavku a chybového stavu.

### Administrace

- přehled aplikací a vydání,
- založení a editace aplikace,
- výběr ikony a snímků z veřejných médií,
- nahrání APK nebo publisher ZIPu a zobrazení výsledku analýzy,
- samostatná kontrolní obrazovka vydání s identitou, podpisem, oprávněními a seznamem změn,
- schválení a zneplatnění podpisového certifikátu,
- vytvoření, jednorázové zobrazení a odvolání publikačního tokenu,
- zveřejnění, stažení a smazání konceptu vydání.

### Publikační workflow a API

- anonymní read-only update API,
- tokenové POST API, které smí vytvořit pouze koncept,
- lokální PowerShell publisher pro libovolný Android projekt,
- bezpečnostní chyby publisheru a serverové validace jsou textové a nezávislé na barvě.

## WCAG 2.2 AA

| Kritérium | Stav | Důkaz a rozhodnutí |
|---|---|---|
| 1.1.1 Netextový obsah | Supports s odpovědností správce | Ikona používá název aplikace. Screenshot lze vybrat jen z veřejného média s neprázdným alt textem; starší neúplný záznam se veřejně nevykreslí. Kvalitu popisu musí ověřit správce. |
| 1.3.1 Informace a vztahy | Supports | Veřejné i administrační části používají skutečné nadpisy, `section` s existujícím `aria-labelledby`, `fieldset`/`legend`, tabulkové `caption`, hlavičky a popisné seznamy. |
| 1.3.2 Smysluplné pořadí | Supports | Název, metadata, hlavní akce, popis, snímky a vydání jsou v logickém DOM pořadí. Formuláře zachovávají pořadí popisek, pole, nápovědy a chyby. |
| 1.3.5 Určení účelu vstupu | Not Applicable | Modul nesbírá osobní údaje návštěvníků. Administrační metadata aplikace nejsou údaje o osobě vyžadující autocomplete tokeny. |
| 1.4.1 Použití barev | Supports | Stav aplikace, certifikátu, tokenu i vydání je vždy vyjádřen textem. |
| 1.4.3 Kontrast | Supports podle sdílené šablony | Modul nepřidává vlastní barevný systém a používá společné veřejné a administrační WCAG AA styly. |
| 1.4.4 Změna velikosti textu | Supports podle sdílené šablony | Text ani ovládání nepoužívají pevné pixelové rozměry, které by blokovaly zvětšení. |
| 1.4.10 Přizpůsobení obsahu | Supports s ručním ověřením | Karty, formuláře a tabulkové obaly používají sdílené responzivní komponenty. Dlouhé hashe mají třídu pro zalomení. |
| 1.4.11 Kontrast netextových prvků | Supports podle sdílené šablony | Pole, tlačítka a focus indikátory používají sdílený kontrastní design systém. |
| 1.4.12 Mezery textu | Supports | Modul neomezuje uživatelské změny řádkování, slov ani znaků. |
| 1.4.13 Obsah při hoveru nebo focusu | Not Applicable | Modul nepřidává tooltipy ani obsah zobrazovaný jen při hoveru či focusu. |
| 2.1.1 Klávesnice | Supports | Veškeré ovládání používá nativní odkazy, formulářová pole, checkboxy a tlačítka bez drag-and-drop nebo ovládání závislého na myši. |
| 2.1.2 Žádná past na klávesnici | Supports | Modul nepřidává vlastní skriptované focus kontejnery. |
| 2.4.1 Přeskočení bloků | Supports podle layoutu | Veřejný i administrační layout zachovává globální skip link. |
| 2.4.2 Titulek stránky | Supports | Katalog, aplikace, vydání i admin obrazovky předávají konkrétní titulky sdílenému layoutu. |
| 2.4.3 Pořadí zaměření | Supports | DOM a focus pořadí jsou shodné; modul nepoužívá pozitivní `tabindex`. |
| 2.4.4 Účel odkazu | Supports | Odkazy rozlišují detail aplikace, konkrétní verzi, stažení APK, podporu, soukromí a návratovou navigaci. |
| 2.4.6 Nadpisy a popisky | Supports | Nadpisy a popisky formulářů popisují aplikaci, balíček, certifikát, token i důsledek akce. |
| 2.4.7 Viditelné zaměření | Supports podle sdílené šablony | Všechny interaktivní prvky používají globální viditelný focus styl. |
| 2.4.11 Focus není zakrytý | Supports podle sdílené šablony | Modul nepřidává sticky překryvy ani vlastní dialogy. |
| 2.5.3 Popisek v názvu | Supports | Přístupné názvy nativních ovládacích prvků obsahují jejich viditelný text. |
| 2.5.8 Velikost cíle | Supports podle sdílené šablony | Tlačítka a odkazy v administračních akcích používají společné rozměry a rozestupy. |
| 3.1.1 Jazyk stránky | Supports podle layoutu | Veřejný i administrační layout deklaruje češtinu. |
| 3.2.2 Při vstupu | Supports | Změna pole nebo checkboxu sama neodesílá formulář ani nemění kontext. |
| 3.2.3 Konzistentní navigace | Supports | Appmarket se registruje přes společný modulový manifest a sdílené navigace. |
| 3.2.4 Konzistentní identifikace | Supports | Akce `Upravit`, `Zveřejnit`, `Stáhnout vydání`, `Smazat koncept` a `Odvolat` mají konzistentní význam. |
| 3.3.1 Identifikace chyb | Supports | Souhrn používá `role="alert"` a chyby názvu, slugu, applicationId, URL, média, stavu aplikace, release notes, otisku i tokenu jsou navázané přímo na pole přes `aria-describedby`. |
| 3.3.2 Popisky nebo instrukce | Supports | Povinná pole jsou označená textem a doprovázená konkrétní nápovědou k applicationId, produkčnímu APK, certifikátu a expiraci tokenu. Volitelný slug výslovně oznamuje automatické vytvoření z názvu. |
| 3.3.3 Návrh při chybě | Supports | Chyby vysvětlují očekávaný formát nebo bezpečný další krok a formuláře zachovávají zadané hodnoty, kromě souborového pole daného prohlížečem. |
| 3.3.4 Prevence chyb | Supports | Publikace má samostatnou kontrolní obrazovku a vyžaduje výslovné potvrzení identity, podpisu, oprávnění i seznamu změn. Server těsně před publikací znovu analyzuje uložené APK a porovná package ID, `versionName`, `versionCode`, velikost, hash i certifikát. Stažení vydání, smazání konceptu, zneplatnění certifikátu a odvolání tokenu mají vlastní potvrzení; zneplatnění certifikátu zároveň stáhne všechna jeho veřejná vydání. |
| 3.3.7 Redundantní zadávání | Supports | Metadata APK načítá server nebo lokální publisher; správce je nemusí ručně přepisovat. |
| 3.3.8 Přístupné ověřování | Supports | Modul nepoužívá kognitivní test ani captchu. Token a podpis se ověřují strojově. |
| 4.1.2 Název, funkce, hodnota | Supports | Nativní prvky mají popisky; pojmenované regiony odkazují jen na existující nadpisy a legendy. |
| 4.1.3 Stavové zprávy | Supports | Úspěch, chyba, dostupnost Android nástrojů a jednorázové zobrazení tokenu používají `role="status"` nebo `role="alert"` bez nuceného přesunu fokusu. |

## Bezpečnost podporující přístupnost

- APK jsou uložené mimo webroot s omezenými oprávněními a veřejně se vydávají pouze přes kontrolovaný endpoint. Endpoint při každém požadavku ověřuje velikost i SHA-256 a používá revalidovatelnou cache, aby stažené vydání nebo zneplatněný certifikát nezůstaly dostupné z neměnné roční cache.
- Update API nevyžaduje zařízení identifikovat, nepoužívá captchu ani přihlášení, přijímá `GET`/`HEAD` a po načtení konfigurace neupravuje session ani neposílá session cookie.
- Publisher API přijímá jen `POST`, nepoužívá session cookie, má rate limit a bearer token uložený v databázi pouze jako SHA-256 hash.
- Token smí vytvořit jen koncept a neznámý nebo prázdný scope se vyhodnotí jako bez oprávnění. Publikace vyžaduje superadmina, schválený podpisový certifikát, shodný applicationId a SHA-256.
- Server musí nezávisle spustit `apkanalyzer` a `apksigner`; deklarovaná metadata publisheru nikdy neslouží jako fallback a databáze eviduje jen zdroj `apk`. Nástroje mají časový i výstupní limit a chybějící či neúplná analýza operaci bezpečně zablokuje.
- Release notes se na veřejném i kontrolním detailu vykreslují omezeným projektovým Markdown rendererem, nikoli obecným HTML obsahem.
- Lokální publisher odmítá nečistý Git, debug/QA/unsigned sestavení a token nepřijímá jako argument příkazové řádky.
- Chybové odpovědi API mají stabilní JSON tvar; vizuální rozhraní není podmínkou pro aktualizaci aplikace.

## Odpovědnost CMS

Kora CMS odpovídá za sémantiku formulářů a tabulek, focus, chybové stavy, zachování formulářových hodnot, bezpečné názvy souborů, kontrolu viditelnosti, automatické načtení APK metadat a strojově čitelný update kontrakt. CMS poskytuje pole a nápovědy potřebné pro alternativní texty, popisy, poznámky k vydání, podporu a ochranu soukromí; každý veřejný snímek obrazovky má navíc pojmenovaný `figure` navázaný na viditelný nebo čtečkový popisek.

## Odpovědnost správce obsahu

Správce odpovídá za srozumitelný název a popis aplikace, výstižné alt texty snímků v knihovně médií, čitelný seznam změn, aktuální odkazy na podporu a soukromí a pravdivé licenční údaje. Soukromý podpisový klíč nesmí nahrávat do CMS; schvaluje pouze ověřený SHA-256 otisk veřejného certifikátu.

## Automatizovaný důkaz

- `build/unit_tests.php`: normalizace package ID, verzí, SDK, hashů, fail-closed token scopes, limity release notes a oprávnění, canonical URL, update payload a HTTP Range.
- `build/schema_parity_audit.php`: pět Appmarket tabulek, úplný bezpečnostně důležitý sloupcový kontrakt a indexy v `install.php` i `migrate.php`.
- `build/runtime_audit.php`: modulový manifest, capability, privátní storage a oprávnění souborů, povinná serverová analýza bez fallbacku, porovnání verze a velikosti, časové limity, kontrolní obrazovka, revokace certifikátu s POST/Redirect/GET ochranou proti opakovanému odeslání, revalidovatelná cache, sessionless API, routy, formuláře a export/import bez tokenů.
- `build/theme_view_audit.php`: veřejné screenshoty používají `figure` pojmenovaný existujícím `figcaption`.
- `build/http_integration.php`: veřejný katalog a detail, API bez session cookie, částečné stažení a cache hlavičky, skutečné načtení editoru aplikace včetně popsané HTML nápovědy, kontrolní obrazovka, zneplatnění certifikátu, vypnutý modul, veřejná viditelnost a administrativní workflow.
- `build/release_smoke.php`: instalační ZIP musí obsahovat lokální publisher, aby dokumentované ovládání nebylo závislé na vývojovém repozitáři.
- `build/accessibility_conformance_audit.php`: změny rozhraní jsou provázané s tímto ACR dokumentem a automatizovanými důkazy.
- `composer analyse:strict:appmarket` a `composer format:check:appmarket`: statická analýza a jednotný styl celého modulu.

## Ruční ověření

Před stabilním vydáním projít:

1. Pouze klávesnicí vytvořit aplikaci i s prázdným automaticky generovaným slugem, vybrat média, nahrát vydání, schválit certifikát, projít kontrolní obrazovku a zveřejnit koncept.
2. S NVDA a Firefoxem ověřit souhrn i vazbu chyb na pole, tabulky aplikací/vydání/tokenů, kontrolní metadata a potvrzení nevratných akcí.
3. Ověřit katalog, detail aplikace a vydání při zoomu 200 % a 400 %.
4. Ověřit veřejné i administrační obrazovky při šířce 320 CSS px bez ztráty obsahu nebo ovládání.
5. Zkontrolovat, že screenshoty mají odlišné a obsahově užitečné alt texty.
6. Ověřit dlouhý název aplikace, release notes a dlouhé SHA-256 hodnoty bez překrytí dalšího obsahu.
7. Ověřit stažení přes Chrome/Edge, Firefox a Android klienta včetně přerušeného pokračujícího stahování.

## Známé mezery a rozhodnutí

- Plná kompatibilita konkrétní vlastní šablony je odpovědností jejího autora; tento report pokrývá defaultní šablonu Kora CMS.
- Kvalitu alternativních textů a release notes nelze automaticky rozhodnout. Audit kontroluje dostupnost polí a upozornění, nikoli významovou správnost textu.
- Server bez Android SDK nástrojů `apkanalyzer` a `apksigner` nemůže přijímat ani publikovat vydání. Jde o záměrné bezpečné selhání, nikoli o podporovaný fallback workflow.
- Lokální publisher je konzolový nástroj bez grafického rozhraní. Chyby jsou čistý text a lze je číst odečítačem terminálu; jeho konkrétní přístupnost závisí také na použitém terminálu.
- Ruční NVDA, zoom a mobilní scénáře zůstávají release checklistem. Automatizované kontroly nesmějí být vydávány za náhradu těchto testů.
