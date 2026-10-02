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

## Navazující průchod, 2026-10-02

Výchozí revize `d63e299f`, čistá větev `main`. Tento blok prochází transakční hranice Anket, trvalé mazání napříč moduly koše a zpracování veřejné zpětné vazby FAQ. Neprohlašuje nový úplný řádkový audit ostatních modulů. Schéma a `VERSION` zůstávají beze změny; vydání RC.2 není součástí tohoto kroku.

| ID | Priorita | Nález | Oprava a regresní důkaz |
|---|---|---|---|
| RC2-09 | P2 | Hlasování i editor načítaly pravidla/možnosti a kontrolovaly hlasy před transakcí. Souběh mohl přijmout hlas do uzavřené ankety, podle starého limitu nebo na mezitím odstraněnou možnost; editor mohl smazat nově odhlasovanou možnost. | Společný parent row lock, aktuální locking reads pravidel, možností a hlasů, všechny zápisy v transakci. Sedm skutečných MySQL souběhů: uzavření, odstranění možnosti, nový limit, změna režimu, duplicitní hlas, ochrana odhlasované možnosti a úspěšný hlas. Worker má starší REPEATABLE READ snapshot. |
| RC2-10 | P2 | Každá PDO chyba hlasování se vydávala za opakovaný hlas; formulář ztrácel výběr a skupina neodkazovala na chybu. | Oddělená pravdivá chyba ukládání, strukturovaný log bez nové analytiky, zachování existujících voleb a fieldset navázaný na alert. Simulovaný pád druhého INSERTu prokazuje rollback session i prvního hlasu; DOM a HTTP důkazy zachovaného výběru. |
| RC2-11 | P2 | FAQ přesměrovalo legacy `?id=` i u POSTu ještě před ověřením a uložením zpětné vazby. | Canonical redirect jen mimo POST. Skutečné HTTP ověřuje GET redirect, chybný CSRF, zachování poznámky při validační chybě a aktualizaci jediného hlasu přes legacy endpoint. |
| RC2-12 | P2 | Trvalé/hromadné mazání Anket neuklízelo hlasovací sessions. Koš mazal možnosti a hlasy před ověřením, zda je anketa skutečně smazaná; podvržené aktivní ID ztratilo vazby. | Sdílené atomické mazání s parent row lockem, kontrolou stavu koše před zápisem, cleanupem sessions i redirectů/revizí. Izolované snapshoty dokazují zachování aktivní ankety, úplný rollback selhání cleanupu a zachování cizích záznamů. Dávka je atomická po jednotlivých anketách. |
| RC2-13 | P1 | Také obecná větev koše uklízela vazby před ověřením smazaného rodiče; potvrzený POST s ID aktivního lístku mohl odstranit jeho položky i poptávky, u podcastů kapitoly a další data. Selhání posledního DELETE nevracelo předchozí úklid. | Whitelist tabulek, transakce a locking read s `deleted_at IS NOT NULL` před jakýmkoli cleanupem; kontrola výsledku DELETE a rollback chyby. HTTP matice všech 14 typů koše dokazuje zachování aktivních rodičů a vazeb. Food navíc pokrývá chybějící potvrzení, skutečný MySQL trigger po úklidu, úplný rollback a následné úspěšné smazání. |

### Ověření a přijetí průchodu

Lokální `composer ci:module-ready` na PHP 8.4.12 prošel včetně lintů, statické analýzy, schématu, ACR, balíčku, runtime auditu a celé HTTP integrace. Unit sada: 1 552 testů, žádná chyba. Izolované RC.2 regrese: 302 kontrol a znovu 302 kontrol s PDO číselnými řetězci. Skutečný MySQL test: sedm dvouprocesových souběhů. HTTP `rc2_module_revisions_http`, `rc2_trash_integrity_http`, `rc2_poll_cleanup_http`, `poll_voting_modes_http` a `faq_categories_feedback_http` prošly; fixtures i testovací trigger jsou uklizené.

Jedinou očekávanou výjimkou runtime auditu je `smtp_connectivity = SKIP`: lokální síť nedosáhla SMTP na portu 25. První plný běh zachytil zastaralou zdrojovou kontrolu seznamu větví koše po oddělení Anket; kontrola byla aktualizována, nikoli vypnuta. Po dokončení obecné ochrany koše se celý balík zopakoval bez přeskočení kontrol. `git diff --check` prošel. Výsledek GitHub CI se vztahuje až ke konkrétnímu pushnutému commitu; lokální výsledek sám neprokazuje Linux/PHP 8.0 ani hosting.

Na lokálu byly při zahájení databázového testu zastavené MySQL i Apache. Spuštěny byly existující binárky Laragonu se současnou konfigurací a datovým adresářem, bez ruční změny konfigurace nebo přesunu databázových souborů. Testy pracují pouze s vlastními uklizenými fixtures; lokální problém se neobchází SKIPem.

Ručně zůstává NVDA/Firefox, klávesnice, reflow a hostingový cron/log smoke. Při odmítnutém vícevýběru musí být původní platné checkboxy zaškrtnuté a skupina nabídnout vysvětlení chyby; po změně režimu hlasování se použije aktuální ovládací model. Tento průchod nevydává certifikaci ani stabilní verzi.
