# Food: Accessibility Conformance Report

## Stav dokumentu

- Cíl: WCAG 2.2 AA.
- Rozsah: veřejný přehled, archiv a detail jídelních a nápojových lístků, denní nabídky, strukturované položky, cenové a porční varianty, filtry, objednávkové poptávky a administrační správa lístků, sekcí, položek, variant a poptávek.
- Dokument je technická modulová příloha ACR Kora CMS, nikoli právní certifikace.
- Automatizované kontroly jsou součástí `composer ci:module-ready`; ruční scénáře níže musí být provedeny před označením modulu za plně ověřený na konkrétní šabloně.

## Testované rozhraní

### Veřejná část

- aktuální lístky na `/food/`,
- archiv `/food/archive.php` včetně fulltextu, období, typu lístku, dne podávání a položkových filtrů,
- detail `/food/card/{slug}`,
- HTML-only lístek, strukturovaný lístek i kombinace strukturovaných položek a doplňkového HTML,
- denní sekce, čas podávání, nutriční údaje, obrázky, alergeny a jejich legenda,
- Cenové a porční varianty položek včetně textově oznámené nedostupnosti,
- nezávazná objednávková poptávka `food/order.php?slug=...`,
- Způsob převzetí, podmíněná doručovací adresa a volitelně povinný požadovaný termín.

### Administrace

- přehled a editor lístků,
- správa sekcí a strukturovaných položek,
- samostatná správa variant položky,
- dostupnost a řazení tlačítky nahoru a dolů,
- duplikace položky včetně variant,
- objednávkové nastavení lístku,
- přehled, filtrování, detail a potvrzená změna stavu poptávky,
- export/import konfigurace Food modulu bez osobních údajů objednávek.

## WCAG 2.2 AA

| Kritérium | Stav | Důkaz a rozhodnutí |
|---|---|---|
| 1.1.1 Netextový obsah | Supports s odpovědností správce | Obrázek položky používá vlastní alt text, alt text média nebo název položky jako bezpečný fallback. Neveřejné, neobrázkové nebo chybějící médium se nevykreslí. Významovou kvalitu alt textu musí ověřit správce. |
| 1.3.1 Informace a vztahy | Supports | Lístky používají skutečné nadpisy, pojmenované sekce, seznamy a popisné seznamy. Filtry a varianty v objednávce používají `fieldset` a `legend`; administrační formuláře mají labely, nápovědy a field-level chyby. |
| 1.3.2 Smysluplné pořadí | Supports | Veřejné pořadí je lístek, filtry, sekce, položky, metadata a doplňkové poznámky. U objednávky následují položky, převzetí, kontakt, poznámka a ochrana proti spamu. CSS nemění významové pořadí DOM. |
| 1.3.5 Určení účelu vstupu | Supports | Objednávková poptávka používá `name`, `email`, `tel` a `street-address`; CAPTCHA má `autocomplete="off"`. Známé kontaktní údaje přihlášeného návštěvníka se bezpečně předvyplní. |
| 1.4.1 Použití barev | Supports | Dostupnost, alergeny, dietní štítky, dnešní nabídka, stav poptávky i nedostupná varianta jsou vždy vyjádřené textem. |
| 1.4.3 Kontrast | Supports podle sdílené šablony | Modul používá společný veřejný a administrační design systém s cílem WCAG AA a nezavádí význam závislý na vlastní barvě. |
| 1.4.4 Změna velikosti textu | Supports podle sdílené šablony | Rozhraní neblokuje zvětšení textu a nepoužívá obrázky textu. |
| 1.4.10 Přizpůsobení obsahu | Supports s ručním ověřením | Karty, varianty, formuláře a tabulky používají responzivní layout; při menší šířce se skupiny skládají pod sebe. Zoom 200–400 % a šířka 320 CSS px zůstávají v ručním protokolu. |
| 1.4.11 Kontrast netextových prvků | Supports podle sdílené šablony | Pole, tlačítka, checkboxy, rádia a focus indikátory používají společné kontrastní komponenty. |
| 1.4.12 Mezery textu | Supports | Modul neomezuje uživatelské změny řádkování, slov ani znaků a důležitá metadata odděluje skutečným textem. |
| 1.4.13 Obsah při hoveru nebo focusu | Not Applicable | Food nepřidává tooltipy ani obsah dostupný pouze při hoveru nebo focusu. |
| 2.1.1 Klávesnice | Supports | Filtry, objednávka, varianty i admin akce používají nativní odkazy, formulářové prvky a tlačítka. Řazení nevyžaduje drag-and-drop. |
| 2.1.2 Žádná past na klávesnici | Supports | Modul nepřidává vlastní focus kontejner ani klávesovou past. Výběr média používá sdílený přístupný picker. |
| 2.4.1 Přeskočení bloků | Supports podle layoutu | Veřejný i administrační layout zachovává globální skip link. |
| 2.4.2 Titulek stránky | Supports | Přehled, archiv, detail, objednávka i administrační obrazovky předávají konkrétní titulek sdílenému layoutu. |
| 2.4.3 Pořadí zaměření | Supports | Fokus odpovídá DOM a modul nepoužívá pozitivní `tabindex`. |
| 2.4.4 Účel odkazu | Supports | Odkazy rozlišují archiv, konkrétní lístek, položky, varianty, objednávky a administrační akce. |
| 2.4.6 Nadpisy a popisky | Supports | Nadpisy, legendy a labely rozlišují sekce lístku, varianty, množství, převzetí, adresu, termín, kontakt i stav poptávky. |
| 2.4.7 Viditelné zaměření | Supports podle sdílené šablony | Interaktivní prvky používají globální viditelný focus styl. |
| 2.4.11 Focus není zakrytý | Supports podle sdílené šablony | Modul nepřidává sticky překryvy; společný media picker má vlastní ověřované focus chování. |
| 2.5.3 Popisek v názvu | Supports | Přístupné názvy nativních ovládacích prvků obsahují jejich viditelný text. |
| 2.5.8 Velikost cíle | Supports podle sdílené šablony | Tlačítka filtrů, řazení a stavových akcí používají společné rozměry a rozestupy. |
| 3.1.1 Jazyk stránky | Supports | Veřejný i administrační layout deklaruje češtinu. |
| 3.2.2 Při vstupu | Supports | Změna filtru, varianty, množství nebo způsobu převzetí sama formulář neodešle ani nezmění kontext. |
| 3.2.3 Konzistentní navigace | Supports | Food je zapojený do společného manifestu, veřejné navigace, admin navigace a command centra. |
| 3.2.4 Konzistentní identifikace | Supports | Akce `Upravit`, `Položky lístku`, `Varianty`, `Objednávkové poptávky`, `Nahoru`, `Dolů` a `Smazat` mají konzistentní význam. |
| 3.3.1 Identifikace chyb | Supports | Veřejné i admin formuláře používají textový souhrn s `role="alert"`, `aria-invalid` a `aria-describedby` napojené na existující lokální chyby. Zadané hodnoty se při chybě zachovají. |
| 3.3.2 Popisky nebo instrukce | Supports | Povinná pole, podmíněná adresa, budoucí termín, ceny, měny, porce, dostupnost a dopad mazání mají viditelné popisky a konkrétní nápovědu. |
| 3.3.3 Návrh při chybě | Supports | Chyby radí vybrat nabízený způsob převzetí, doplnit úplnou doručovací adresu, budoucí termín, platnou cenu nebo jedinečný název varianty. |
| 3.3.4 Prevence chyb | Supports v relevantním rozsahu | Veřejná objednávka je výslovně nezávazná. Změna stavu a mazání v administraci popisují dopad, vyžadují CSRF a serverově ověřené potvrzení; historická poptávka zachová snapshot i po změně nebo smazání varianty. |
| 3.3.7 Redundantní zadávání | Supports | Známé kontaktní údaje přihlášeného návštěvníka se předvyplní a chybný submit zachová položky, převzetí, adresu, termín i kontakt. |
| 3.3.8 Přístupné ověřování | Not Applicable | Food nemá vlastní přihlašovací proces. Matematická ochrana objednávky je antispamový krok veřejného formuláře, nikoli autentizace uživatele; její chyba má konkrétní textovou nápovědu. |
| 4.1.2 Název, funkce, hodnota | Supports | Nativní prvky mají label nebo legendu; sekce, formuláře a chyby odkazují jen na existující prvky. |
| 4.1.3 Stavové zprávy | Supports | Uložení, chyba, prázdný výsledek, nedostupnost a stav poptávky jsou sdělené textově přes sdílené status/alert komponenty bez rušivého hlášení po každém znaku. |

## Bezpečnost podporující přístupnost

- Veřejná objednávka sestavuje nabídku pouze z veřejného lístku, dostupných položek a dostupných variant načtených z databáze.
- Request posílá jen stabilní klíč volby a množství; název, porce, cena, měna i dostupnost se znovu načtou serverově a uloží jako snapshot.
- Server přijme pouze způsoby převzetí povolené u konkrétního lístku. Adresu ukládá jen pro doručení a požadovaný termín jen při zapnutém nastavení.
- Objednávku chrání CSRF, honeypot, CAPTCHA a rate-limit; e-mail příjemce se načítá z konfigurace, nikoli z requestu.
- Administrační správa používá capability `content_manage_shared`, modulový guard, CSRF a item/card scope u každé variantové akce.
- Export přenáší konfiguraci lístků a varianty, ale neobsahuje osobní údaje objednávek. Historická objednávka zůstává čitelná i po smazání varianty.

## Odpovědnost CMS

Kora CMS odpovídá za sémantickou strukturu lístků a formulářů, textovou dostupnost stavů, správné vazby labelů a chyb, serverovou validaci, bezpečné načtení cen a variant, zachování zadaných hodnot, snapshot objednávky, responzivní defaultní šablonu a regresní guardraily. CMS automaticky neodhaduje cenu, porci, alergeny, nutriční hodnoty ani dostupnost.

## Odpovědnost správce obsahu

Správce odpovídá za pravdivý název, popis, cenu, porci, měnu, dostupnost, alergeny, dietní štítky, nutriční údaje, instrukce k objednávce a kvalitu alternativního textu. Musí zkontrolovat, že názvy variant jsou vzájemně srozumitelné i bez vizuálního kontextu a že nabízené způsoby převzetí odpovídají reálnému provozu.

## Automatizovaný důkaz

- `build/unit_tests.php`: hydratace variant, textové popisky, dostupnost, bezpečné klíče, výběr povolených možností, snapshot ceny a porce, způsoby převzetí, požadovaný termín a JSON-LD nabídky.
- `build/schema_parity_audit.php`: tabulka variant, nové sloupce lístků a snapshoty objednávek v `install.php` i `migrate.php`.
- `build/module_contract_audit.php`: Food manifest, tabulky, veřejné i administrační endpointy a capability.
- `build/runtime_audit.php`: DB schéma, helpery, scoped variantové akce, veřejná validace, formulářová sémantika, export/import, cleanup, CSS a návaznost na tento ACR.
- `build/http_integration.php`: vytvoření variant, chyby se zachováním hodnot, veřejný render a JSON-LD, objednávka varianty, chybějící adresa a termín, snapshot, admin detail, export/import a potvrzené mazání.
- `build/accessibility_conformance_audit.php`: změny přístupnostně citlivých souborů jsou provázané s tímto dokumentem a automatizovanými důkazy.
- `composer analyse:strict:public-food`, `composer analyse:strict:food-v4` a `composer format:check:food-v4`: statická analýza a jednotný styl změněného workflow.

## Ruční ověření

Před stabilním vydáním projít:

1. Pouze klávesnicí vytvořit lístek, sekci, položku a dvě varianty, změnit jejich pořadí a jednu označit jako nedostupnou.
2. S NVDA a Firefoxem ověřit veřejný detail, čtení názvu položky, varianty, porce, ceny, nedostupnosti, alergenů a nutričních údajů.
3. Odeslat objednávku varianty pro každý povolený způsob převzetí; u doručení ověřit chybějící adresu a u zapnutého termínu prázdnou i minulou hodnotu.
4. Ověřit, že souhrnný alert a field-level chyby mají smysluplné pořadí, hodnoty zůstaly zachované a CAPTCHA nevyvolává opakované rušivé hlášení při psaní.
5. Změnit cenu nebo smazat objednanou variantu a ověřit, že administrační detail starší poptávky dál zobrazuje původní snapshot.
6. Ověřit veřejný přehled, archiv, detail, filtry a objednávku při zoomu 200 % a 400 %.
7. Ověřit stejné obrazovky a správu variant při šířce 320 CSS px bez ztráty obsahu nebo ovládání.
8. Vyzkoušet dlouhé názvy položek a variant, dlouhou cenu s poznámkou a několik měn bez slepení textu nebo horizontální ztráty ovládání.

## Známé mezery a rozhodnutí

- Dokument pokrývá defaultní šablonu Kora CMS. Vlastní šablona musí zachovat stejnou sémantiku, focus a responzivní chování.
- Správnost alergenů, dietních a nutričních údajů ani kvalitu alt textu nelze automaticky rozhodnout.
- Objednávka je nezávazná poptávka bez plateb, skladu a automatického potvrzení. Přístupnost případné navazující komunikace závisí také na provozovateli.
- Podmíněná adresa je v serverovém formuláři stále viditelná, pokud lístek umožňuje doručení; text vysvětluje, kdy je povinná. Modul kvůli jednoduchosti nevyžaduje JavaScript.
- Ruční NVDA, zoom, mobilní a custom-theme scénáře nejsou nahrazené automatickými testy a nesmějí být vydávány za provedené pouze na základě zeleného CI.
