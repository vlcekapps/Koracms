# Modulový audit pro RC.2, 2026-09-05

## Rozsah a metoda

Výchozí revize `2cc9dd02595bcd31a48052a540ba4b34176a2201`, čistá větev `main`, vydání `5.0.0-rc.1`. Správce oznámil, že RC.1 na hostingu běží; vyhodnocení následného cronu a logů zatím čeká. Tento audit nevydává RC.2 a nemění `VERSION`.

Navazuje na [RC.1 audit](rc-audit-2026-09.md), neopakuje jeho opravené nálezy. Hlubší ruční čtení kódu pokrývá revize napříč moduly, Food/poptávky, Galerie/metadata a Ankety/možnosti. Výběrově byly znovu přečteny také Kontakt/odpovědi, Chat/veřejné a podpůrné zprávy, odběr Vývěsky a Appmarket/ukládání a stahování vydání. U ostatních modulů je důkazem v tomto průchodu dosavadní automatizované pokrytí, nikoli nové řádkové review každého souboru.

Plná sada zahrnuje manifest, oprávnění a vypnuté moduly, schéma install/migrate, import/export, veřejné routy a modulová workflow. Izolované reprodukce neodesílají e-maily a nepoužívají provozní databázi; HTTP regrese vytvářejí a uklízejí vlastní data. Audit není bezpečnostní certifikace ani potvrzení úplné WCAG shody.

## Potvrzené nálezy

| ID | Priorita | Nález | Oprava a regresní důkaz |
|---|---|---|---|
| RC2-01 | P1 | Přihlášený autor mohl přes historii revizí číst cizí neveřejné texty bez oprávnění k danému obsahu. | Whitelist entit, capability/modulový guard a kontrola autorství i blogového přístupu před načtením revizí. Izolovaná matice a skutečné HTTP role autor/editor/moderátor. |
| RC2-02 | P2 | Food tiše měnil chybné množství a vynechával mezitím nedostupné vybrané položky. | Server odmítá neplatné množství, neplatné pole i kolizi legacy/nového klíče; žádný částečný insert. Field-level chyby, zachování hodnot, HTTP se správnou captchou. |
| RC2-03 | P2 | Součet Food ignoroval neoceněnou položku, jako by byla zdarma. | Celková cena jen při známé ceně všech položek ve stejné měně; explicitní nula dál platí. Snapshot/unit scénáře. |
| RC2-04 | P2 | Chyba licence, data či slugu fotografie zahodila rozepsaná metadata. | Jednorázový session flash omezený fotografií i albem, skutečný save handler a HTML editoru bez JS. |
| RC2-05 | P2 | Opakované ID možnosti ankety mohlo snížit skutečný počet odpovědí pod validované minimum; cizí ID se tiše změnilo na novou možnost. | Odmítnutí opakovaných, cizích a neplatných ID před prvním zápisem, DB snapshot před/po, zachovaný úspěšný save. |
| RC2-06 | P2 | Odmítnutá anketa ztratila otázku, možnosti a rozdělené datum/čas. | Jednorázová obnova hodnot i odpovědí pro konkrétní anketu, přístupná chyba a test skutečného formuláře. |
| RC2-07 | P2 | Kontakt a Food skryly chybu notifikačního e-mailu v úspěšné větvi šablony. | Viditelné textové upozornění uvnitř pojmenovaného statusu, reference a výslovná informace neodesílat duplicitu. Test obou šablon a ARIA vazeb. |
| RC2-08 | P3 | Odkazy Anket a Míst na revize vedly na nepodporovaný typ a dashboard. | Oba typy jsou v registru s příslušným oprávněním a modulem; HTTP ověřuje skutečnou historii. |

## Ověření

- Nová izolovaná sada: `php build/rc2_modules_selftest.php`; stejná sada se v `composer test:rc-core` opakuje s `PDO::ATTR_STRINGIFY_FETCHES`, bez oslabení porovnání obsahových hodnot.
- Nové HTTP scénáře: `rc2_module_revisions_http` a neplatné množství / nedostupná varianta v `food_structured_items_http`.
- Lokální ověření na PHP 8.4.12: lint všech 17 změněných/nových PHP souborů a `composer ci:module-ready` prošly. Balík zahrnul 1 532 unit testů bez chyby, statickou analýzu, schéma, ACR, balíček, izolované regrese, skutečné MySQL/HTTP testy, runtime audit i úplnou HTTP integraci. Nová sada prošla 232 kontrolami v běžném PDO režimu a znovu 232 kontrolami s číselnými řetězci.
- Runtime audit má jedinou očekávanou výjimku `smtp_connectivity = SKIP`, protože lokální připojení k SMTP na portu 25 není dostupné. Původní auditní kontrola převodu klíčů Food byla aktualizována na nový validační helper; ochrana legacy klíčů zůstává v helperu a má vykonávanou regresi. Po této aktualizaci byl celý balík zopakován bez přeskočení kontrol.
- `git diff --check` prošel. Výsledek GitHub CI je nutné vztahovat ke konkrétnímu pushnutému commitu; lokální úspěch sám neprokazuje průchod Linux/PHP 8.0 ani chování hostingu.
- Schéma, `install.php` a `migrate.php` se nemění: všechny opravy používají stávající data a session. Žádná dodatečná databázová struktura zde není potřebná.

## Ruční přijetí

1. Autor otevře vlastní revize, ale cizí článek či sdílený obsah dostane 403 bez úryvku původního textu. Editor vidí oprávněnou historii; vypnutý modul ji nepustí.
2. Bez JS zadat chybnou licenci fotografie a neplatný čas ankety; po návratu musí zůstat nové texty, odpovědi i neoznačené checkboxy.
3. S NVDA/Firefox zadat 120 či 1,5 porce; nesmí se uložit jiné množství a čtečka musí najít vysvětlení u vstupu. Zkontrolovat nově nedostupnou položku.
4. Při simulovaném selhání SMTP rozlišit úspěšné uložení od chyby oznámení, bez opakovaného podání. Skutečnou doručitelnost ověřit na hostingu.
5. Dokončit hostingový cron/log smoke, mobilní reflow, zoom 200–400 % a vlastní šablony před rozhodnutím o vydání. Automatické DOM testy nejsou náhradou NVDA ani vizuální kontroly.

Přístupnostní dopad a automatizované důkazy jsou zaznamenané v [ACR deníku](accessibility/a11y-impact-decisions.md). Nalezené a opravené problémy nezvyšují automaticky globální stav Supports.
