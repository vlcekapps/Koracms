# Recepty: Accessibility Conformance Report

## Stav dokumentu

- Cíl: WCAG 2.2 AA.
- Rozsah: veřejný katalog receptů, landing stránky kategorií, detail s přepočtem porcí, nákupní seznam, kuchařka EPUB, administrační správa receptů, kategorií, ingrediencí, kroků a historie struktury.
- Dokument je technická modulová příloha ACR Kora CMS, nikoli právní certifikace.
- Automatizované kontroly jsou součástí `composer ci:module-ready`; ruční scénáře níže musí být provedeny před označením modulu za plně ověřený na konkrétní šabloně a v konkrétní EPUB čtečce.

## Testované rozhraní

### Veřejný katalog

- katalog `/recipes` včetně hledání, filtrů maximálního času a vyloučených alergenů a prázdného výsledku,
- landing stránka `/recipes/kategorie/{slug}`,
- detail `/recipes/{slug}` včetně formuláře `Přepočet porcí`,
- session-only `Nákupní seznam` na `/recipes/nakupni-seznam`,
- seznam ingrediencí seskupený podle částí receptu,
- číslovaný pracovní postup s volitelnými obrázky,
- metadata času, počtu porcí, obtížnosti, dietních štítků a alergenů,
- vložení receptu do jiného obsahu přes shortcode `[recipe]slug[/recipe]`.

### Administrace

- přehled receptů a filtrování podle stavu a kategorie,
- založení a editace základních údajů receptu,
- samostatná správa skupin ingrediencí, ingrediencí a kroků,
- oddělené přesné číselné a slovní množství bez automatického odhadu,
- přístupné řazení tlačítky nahoru a dolů bez drag-and-drop,
- potvrzená duplikace receptu jako konceptu a obnovitelná `Historie struktury`,
- výběr veřejného obrázku z knihovny médií,
- publikace až po doplnění aktivní kategorie, alespoň jedné ingredience a jednoho kroku,
- správa kategorií včetně slugu, popisu, SEO metadat a pořadí.

### EPUB

- veřejný export `/recipes/kucharka.epub`,
- export jedné kategorie `/recipes/kucharka/{slug}.epub`,
- sémantický EPUB 3 s českým jazykem, navigací, nadpisy, seznamy a metadaty přístupnosti,
- textový obsah bez závislosti na obrázcích, barvě nebo skriptech.

## WCAG 2.2 AA

| Kritérium | Stav | Důkaz a rozhodnutí |
|---|---|---|
| 1.1.1 Netextový obsah | Supports s odpovědností správce | Hlavní a krokové obrázky používají samostatný alt text, alt text média nebo název receptu/kroku jako bezpečný fallback. Kvalitu významového popisu musí ověřit správce. EPUB není závislý na obrázcích. |
| 1.3.1 Informace a vztahy | Supports | Veřejný katalog, detail a nákupní seznam používají skutečné nadpisy, pojmenované sekce, fieldsety filtrů, seznamy ingrediencí, číslovaný postup a popisné seznamy. Metadata na kartách odděluje skutečný text, ne pouze vizuální mezery. Admin formuláře používají popisky, `fieldset` a `legend`, historie struktury tabulku s caption; kuchařka používá sémantické XHTML a EPUB navigaci. |
| 1.3.2 Smysluplné pořadí | Supports | Metadata, úvod, ingredience a pracovní postup jsou v logickém DOM pořadí. Admin formuláře zachovávají pořadí popisek, nápověda, pole a chyba. |
| 1.3.5 Určení účelu vstupu | Not Applicable | Veřejná část nesbírá osobní údaje. Administrační metadata receptu nevyžadují autocomplete tokeny pro údaje o osobě. |
| 1.4.1 Použití barev | Supports | Obtížnost, dietní štítky, alergeny, stav publikace a povinné podmínky jsou vždy uvedené textem. |
| 1.4.3 Kontrast | Supports podle sdílené šablony | Modul používá společný veřejný a administrační design systém s cílem WCAG 2.2 AA a nezavádí vlastní barevný význam. |
| 1.4.4 Změna velikosti textu | Supports podle sdílené šablony | Rozhraní používá sdílenou responzivní typografii a neblokuje zvětšení textu. |
| 1.4.10 Přizpůsobení obsahu | Supports s ručním ověřením | Karty, formuláře, filtry a seznamy používají sdílené responzivní komponenty; zoom a šířka 320 CSS px zůstávají v ručním protokolu. |
| 1.4.11 Kontrast netextových prvků | Supports podle sdílené šablony | Pole, tlačítka, odkazy a focus indikátory používají společný kontrastní design systém. |
| 1.4.12 Mezery textu | Supports | Modul neomezuje uživatelské změny řádkování, slov ani znaků. |
| 1.4.13 Obsah při hoveru nebo focusu | Not Applicable | Modul nepřidává tooltipy ani obsah dostupný jen při hoveru nebo focusu. |
| 2.1.1 Klávesnice | Supports | Veškeré ovládání včetně přepočtu porcí, filtrů, nákupního seznamu a obnovy historie používá nativní odkazy, formulářová pole a tlačítka. Řazení ingrediencí a kroků nevyžaduje drag-and-drop. |
| 2.1.2 Žádná past na klávesnici | Supports | Modul nepřidává vlastní focus kontejnery ani klávesové pasti. Výběr média používá sdílený přístupný picker. |
| 2.4.1 Přeskočení bloků | Supports podle layoutu | Veřejný i administrační layout zachovává globální skip link. |
| 2.4.2 Titulek stránky | Supports | Katalog, kategorie, detail receptu i admin obrazovky předávají konkrétní titulek sdílenému layoutu. |
| 2.4.3 Pořadí zaměření | Supports | Pořadí fokusu odpovídá DOM; modul nepoužívá pozitivní `tabindex`. |
| 2.4.4 Účel odkazu | Supports | Odkazy rozlišují detail receptu, kategorii, návrat, editaci, správu obsahu, historii struktury, nákupní seznam a stažení konkrétní kuchařky. |
| 2.4.6 Nadpisy a popisky | Supports | Nadpisy a popisky pojmenovávají údaje receptu, filtry, ingredience, postup, média, publikaci i důsledky akcí. |
| 2.4.7 Viditelné zaměření | Supports podle sdílené šablony | Interaktivní prvky používají globální viditelný focus styl. |
| 2.4.11 Focus není zakrytý | Supports podle sdílené šablony | Modul nepřidává sticky překryvy; media picker používá společné ověřované chování. |
| 2.5.3 Popisek v názvu | Supports | Přístupné názvy nativních ovládacích prvků obsahují jejich viditelný text. |
| 2.5.8 Velikost cíle | Supports podle sdílené šablony | Tlačítka řazení, odkazy a formulářové akce používají společné rozměry a rozestupy. |
| 3.1.1 Jazyk stránky | Supports | Veřejný i administrační layout deklaruje češtinu; EPUB deklaruje `cs` v XHTML i balíčku. |
| 3.2.2 Při vstupu | Supports | Změna filtru, počtu porcí, checkboxu nákupního seznamu nebo administračního pole sama neodesílá formulář ani nemění kontext. |
| 3.2.3 Konzistentní navigace | Supports | Recepty jsou registrované přes společný modulový manifest, admin navigaci a command centrum. |
| 3.2.4 Konzistentní identifikace | Supports | Akce `Upravit`, `Ingredience a postup`, `Publikovat`, `Přesunout` a `Smazat` mají napříč modulem konzistentní význam. |
| 3.3.1 Identifikace chyb | Supports | Validační chyby používají souhrn s `role="alert"` a field-level vazby přes existující `aria-describedby`; zadané hodnoty se při chybě zachovají. |
| 3.3.2 Popisky nebo instrukce | Supports | Povinné údaje, číselné množství nebo rozsah, oddělené slovní množství, přesné jednotky, volitelné časy, alt text, počet porcí a podmínky publikace mají viditelné popisky a konkrétní nápovědu. |
| 3.3.3 Návrh při chybě | Supports | Chyby vysvětlují bezpečný další krok, například volbu aktivní kategorie, doplnění ingredience nebo pracovního kroku. |
| 3.3.4 Prevence chyb | Supports | Nový i duplikovaný recept vzniká jako koncept. Publikace je samostatná potvrzená akce a proběhne jen s úplnou strukturou. Před každou strukturální změnou vzniká snapshot; obnova nejdřív uloží současný stav, vyžaduje výslovné potvrzení a upozorňuje na souběžnou editaci. Trvalé smazání a další datově dopadající akce vyžadují CSRF a potvrzení. |
| 3.3.7 Redundantní zadávání | Supports | Výchozí hodnoty se zachovávají a media picker přebírá existující metadata; CMS nevyžaduje opakované zadávání stejných ingrediencí nebo kroků. |
| 3.3.8 Přístupné ověřování | Supports | Modul nepoužívá captchu ani kognitivní test. Administrace využívá běžné přihlášení Kora CMS. |
| 4.1.2 Název, funkce, hodnota | Supports | Nativní prvky mají popisky; regiony a formuláře odkazují jen na existující nadpisy, legendy a nápovědy. |
| 4.1.3 Stavové zprávy | Supports | Úspěchy, chyby, prázdné výsledky, stav publikace, duplikace a obnova jsou oznamované textově přes sdílené `status` nebo `alert` komponenty bez nuceného přesunu fokusu. Přepočet porcí nic neoznamuje po každém znaku; proběhne až po odeslání formuláře. |

## Bezpečnost podporující přístupnost

- Veřejný katalog načítá pouze publikované, nesmazané a časově platné recepty v aktivní kategorii.
- Publikace je možná až po ověření struktury receptu; čtenář proto nedostane veřejný detail bez ingrediencí nebo postupu.
- Veřejné obrázky lze vybírat jen z knihovny médií a neplatné nebo neveřejné médium se nevykreslí.
- Zdrojová URL receptu přijímá jen bezpečné `http://` nebo `https://` adresy.
- Administrační změny používají capability modulu, CSRF a interně skládané návratové cíle.
- Nákupní seznam ukládá jen ID veřejných receptů a počty porcí do session, při každém zobrazení znovu ověřuje veřejnou dostupnost a posílá `no-store`/`noindex`.
- Duplikace i obnova historie používají CSRF a výslovné potvrzení; obnova zachová předchozí stav jako další snapshot.
- EPUB endpoint nepřijímá cestu k souboru ani jméno výstupu z requestu; obsah sestavuje jen z veřejných receptů.

## Odpovědnost CMS

Kora CMS odpovídá za sémantickou strukturu katalogu, detailu, nákupního seznamu a EPUB, správné pořadí obsahu, pojmenované regiony, přístupné formuláře, zachování zadaných hodnot, přesné zobrazení uložených údajů bez odhadů, bezpečné fallbacky obrázků, kontrolu veřejné viditelnosti a regresní guardraily. CMS poskytuje oddělená pole pro přesné číselné množství nebo rozsah a pro nepřepočítávaný slovní údaj, přepočítává jen číselné hodnoty a neslučuje ingredience s různými jednotkami nebo z různých receptů.

## Odpovědnost správce obsahu

Správce odpovídá za pravdivý a srozumitelný název, popis, ingredience, množství, postup, alergeny, dietní štítky a případné časové nebo nutriční údaje. Musí zkontrolovat významovou kvalitu alternativních textů, popsat neobvyklé techniky bez závislosti na obrázku a zadat jen hodnoty, které skutečně zná. Přesně škálovatelné množství má zadat do číselného pole a údaj typu `podle chuti` do slovního pole. Kora CMS časy, porce, kalorie ani množství automaticky neodhaduje.

## Automatizovaný důkaz

- `build/unit_tests.php`: normalizace výběrů, slugů, přesných časů a množství, škálování porcí, nákupního výběru, veřejné viditelnosti, alt fallbacků, strukturovaných dat a EPUB balíčku.
- `build/schema_parity_audit.php`: šest tabulek Receptů včetně strukturálních snapshotů, jejich sloupce a důležité indexy v `install.php` i `migrate.php`.
- `build/module_contract_audit.php`: modulový manifest, databázové tabulky, veřejné a administrační endpointy a capability.
- `build/runtime_audit.php`: načtení společných helperů, route pořadí, CSRF, potvrzení, content lock, publikace, veřejná viditelnost, přesná množství, nákupní seznam, formulářová sémantika, hledání, sitemap, statistiky, EPUB metadata a export/import.
- `build/http_server_router_selftest.php`: čisté URL katalogu, kategorie, detailu, nákupního seznamu a EPUB exportů.
- `build/http_integration.php`: veřejný katalog a filtry, přepočet porcí, session nákupní seznam, duplikace, snapshot a obnova, kategorie, detail, skrytí konceptů a budoucích receptů, EPUB odpověď a vypnutý modul.
- `build/accessibility_conformance_audit.php`: změny rozhraní jsou provázané s tímto ACR dokumentem a automatizovanými důkazy.
- `composer analyse:strict:recipes` a `composer format:check:recipes`: statická analýza a jednotný styl modulu.

## Ruční ověření

Před stabilním vydáním projít:

1. Pouze klávesnicí vytvořit kategorii, založit recept, vybrat médium, doplnit skupiny ingrediencí a kroky, změnit jejich pořadí a recept publikovat.
2. S NVDA a Firefoxem ověřit souhrn i field-level chyby, seznam ingrediencí, číslovaný postup, filtry a potvrzení datově dopadajících akcí.
3. Ověřit katalog, kategorii a detail při zoomu 200 % a 400 %.
4. Ověřit veřejné i administrační obrazovky při šířce 320 CSS px bez ztráty obsahu nebo ovládání.
5. Zkontrolovat hlavní a krokové obrázky s různými alt texty a bez dostupného média.
6. Otevřít celou i kategoriovou kuchařku nejméně ve dvou EPUB čtečkách a ověřit jazyk, navigaci, nadpisy, seznamy a pořadí receptů.
7. Ověřit dlouhé názvy, ingredience, jednotky a pracovní kroky bez překrytí nebo nečitelného slepení textu.
8. Přepočítat recept z výchozího počtu porcí na jiný počet a ověřit, že číselná množství se změní až po odeslání, slovní množství zůstane a čtečka neoznamuje každý stisk klávesy.
9. Pouze klávesnicí přidat dva recepty do nákupního seznamu, změnit porce, zaškrtnout položky, vytisknout seznam a vymazat jej.
10. Duplikovat recept a obnovit starší strukturu; ověřit varování dopadu, stav konceptu kopie, stavovou zprávu a návratovou verzi vytvořenou před obnovou.

## Známé mezery a rozhodnutí

- Plná kompatibilita vlastní šablony je odpovědností jejího autora; dokument pokrývá defaultní šablonu Kora CMS.
- Kvalitu receptu, správnost alergenů a alternativních textů nelze spolehlivě automaticky rozhodnout. Audit kontroluje dostupnost polí a sémantiku, nikoli odbornou správnost obsahu.
- Kuchařka je textově zaměřený sémantický EPUB 3. Obrázky se do balíčku nevkládají, aby export nebyl závislý na vzdálených zdrojích a zůstal malý a čitelný.
- EPUB čtečky se v podpoře přístupnostních metadat liší; ruční ověření v konkrétních čtečkách zůstává release checklistem.
- Modul nevytváří PDF. Tisk veřejných stránek je možný přes prohlížeč, ale takto vzniklé PDF nelze bez samostatného ověření označit za tagované nebo přístupné.
- Přepočet neprovádí převody jednotek ani odhad slovních zlomků. Nákupní seznam záměrně neslučuje stejnojmenné ingredience napříč recepty, protože bez znalosti jednotek a významu by mohl vytvořit chybný součet.
- Nákupní seznam je dočasný a vázaný na session prohlížeče; nesynchronizuje se mezi zařízeními a není součástí exportu.
- Ruční NVDA, zoom, mobilní a EPUB scénáře zatím nejsou nahrazené automatickými testy a nesmějí být vydávány za provedené pouze na základě zeleného CI.
