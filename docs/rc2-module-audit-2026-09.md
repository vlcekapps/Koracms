# Modulový audit pro RC.2, 2026-09-05

## Navazující audit Rezervací, Food a veřejných odběrů, 2026-10-05

Výchozí revize `309e0690`, čistá a synchronizovaná větev `main`. Tento blok navazuje na audit celého CMS, ale nové hloubkové čtení má vymezený rozsah: společná captcha, odběr Vývěsky, Rezervace včetně připomínek a použití médií ve Food. Dva agenti provedli oddělené čtení modulů; databázové a HTTP sady běží sekvenčně pod jedním vlastníkem. Nejde o nový řádkový audit každého souboru CMS ani o certifikaci. Schéma, `install.php`, `migrate.php` a `VERSION` se nemění: problémem je použití existujícího modelu, ne chybějící tabulka. Nové vydání není součástí kroku.

| ID | Priorita | Nález | Oprava a regresní důkaz |
|---|---|---|---|
| RC2-20 | P2 | Captcha přetypovala celý vstup na int a přijímala správný číselný prefix s dalším textem, desetinné či exponentové zápisy. | Omezený zápis celého nezáporného čísla a jednorázové spotřebování i při chybě. Nové unit scénáře před opravou skutečně selhaly; HTTP Vývěsky ověřuje správné číslo s příponou `spam` bez zápisu. |
| RC2-21 | P2 | Opakovaný odběr Vývěsky přepsal token a rozsah čekajícího odběru; souběžné potvrzení mohlo být starým načtením vráceno do čekajícího stavu. | Atomický insert/no-op a aktuální zamčené načtení, existující token/stav/kategorie se nemění. MySQL a HTTP ověřují opakování, zachovaný první odkaz a potvrzený odběr. Změna rozsahu vyžaduje odhlášení a nové přihlášení. |
| RC2-22 | P2 | Odběr a odhlášení ukládaly/mazaly rodiče a kategorie samostatně; chyba zanechala částečná data a přihlášení hlásilo falešný úspěch. | Transakce a rollback celého zápisu/cleanupu, pravdivá chyba se zachovaným formulářem. Dočasné MySQL tabulky v obou PDO režimech, selhání druhého INSERTu i finálního DELETE; HTTP trigger omezený vlastním e-mailem dokazuje rollback skutečného handleru a přístupný alert. |
| RC2-23 | P1 | Obrázek Food položky nebyl ve strukturální evidenci použití médií. Normální „smazat nepoužitá“ nebo skrytí média mohlo rozbít lístek. | Doplněná vazba položky a odkaz na její editor. Vykonávaný scanner a HTTP mazání/skrytí musí zachovat metadata i soubor; odpojení obrázku znovu umožní smazání. |
| RC2-24 | P1 | Ruční rezervace posílala `NULL` do povinných kontaktů, přestože registrovaný zákazník je nepoužívá a host může e-mail/telefon vynechat. | Prázdné řetězce podle instalačního/migračního schématu, bez oslabení `NOT NULL`. Skutečný handler se schématem a HTTP vytvoření pro registrovaného i hosta. |
| RC2-25 | P2 | Volitelně prázdný důvod blokovaného dne se také ukládal jako `NULL`, takže se rollbackl celý zdroj. | INSERT i UPDATE ukládají prázdný řetězec; regrese založení a vymazání důvodu. |
| RC2-26 | P2 | Veřejný formulář intervalu používal pro konec stejný seznam jako pro začátek. Hranice, na které začíná jiná rezervace, tak chyběla. | Samostatné koncové hranice, zachovaná serverová kontrola překryvu; přilehlý volný interval se dá zvolit i po validační chybě. |
| RC2-27 | P2 | Schválit/zamítnout v přehledu odesílalo POST bez serverem povinného čerstvého potvrzení a neukázalo jeho chybu. | Navigace na existující detail/review místo nefunkčního POSTu nebo skrytého souhlasu; test propojení a odmítnutí nepotvrzené změny. |
| RC2-28 | P2 | Podvržený oprávněný POST mohl označit dnešní či budoucí termín za no-show, přestože rozhraní to nabízí jen pro minulý den. | Stejná časová podmínka na serveru, bez uvolnění kapacity, historie nebo notifikace při odmítnutí. |
| RC2-29 | P2 | Dva cron pracovníci mohli oba poslat připomínku; dokončené storno mezi načtením a SMTP nebylo znovu chráněno. | Zámky v pořadí zdroj → aktuální rezervace, nové ověření stavu po čekání a držení do potvrzení odeslání. Skutečné MySQL procesové bariéry s nahrazeným SMTP ověřují dvě vlákna a storno před získáním zámku. |

### Ověření a omezení

Před společnou sadou prošlo 1 629 unit testů, 374 izolovaných kontrol rezervací a 109 kontrol Food médií; nové izolované sady prošly i s PDO číselnými řetězci. Odběr Vývěsky prošel 36 kontrolami na dočasných MySQL tabulkách. Skutečný MySQL test připomínek ověřil odeslání právě jedním ze dvou pracovníků, neúspěšné odeslání bez automatického opakování, dokončené storno a změnu nastavení předstihu při starém snapshotu; pozoroval stabilní čekání konkrétního resource `FOR UPDATE` a odstranil vlastní fixtures. SMTP je v tomto testu zachycené, nikoli skutečně doručované. Runtime a ACR audity prošly.

První společné běhy zastavily dvě kontroly nových testů: rezervované DB proměnné se nyní čtou přes explicitní globální konfiguraci a kontrola spotřebování captchy čte aktuální stav relace přes testovací helper. Pravidla, statická analýza ani assertions se neoslabovaly; cílená analýza testů a unit sada znovu prošly. Další běh reprodukoval nestabilní souběžné pozorování `PROCESSLIST` a `INNODB_TRX`: první ukazoval čekající resource SQL, druhý `RUNNING`. Jejich data nejsou garantovaně jedním konzistentním snapshotem ([MySQL 8.4, consistency of locking information](https://dev.mysql.com/doc/refman/8.4/en/innodb-information-schema-internal-data.html)). Test proto používá stejný důkaz jako stávající souběhové sady: stabilní konkrétní SQL před uvolněním známého držitele, tichou procesovou bariéru a výsledné locking reads, aktuální stav, přesný počet pokusů i zápisů. Produkční zámky ani požadované výsledky se nezměnily. Opravený souběhový test prošel čtyřmi po sobě jdoucími běhy po existující MySQL sadě rezervací.

Finální úplný `composer ci:module-ready` na PHP 8.4.12 dokončil běh s návratovým kódem 0: lint, 1 629 unit testů, statická analýza, modulové/schémové/ACR a release audity, izolované i skutečné MySQL regrese, runtime audit a celá HTTP integrace. Nové scénáře `rc2_reservations_audit_http` a `rc2_food_media_usage_http` i rozšířený odběr Vývěsky prošly; samostatná HTTP sada také prošla před finálním během. Jedinou očekávanou výjimkou je `smtp_connectivity = SKIP`, ne potvrzení skutečné doručitelnosti. Lokální úspěch nenahrazuje GitHub CI na konkrétní pushnuté revizi ani ruční hostingové ověření.

První GitHub CI/Full CI na `4159abef` odhalilo rozdíl PHP 8.0 v novém izolovaném Food testu: SQLite vrací `COUNT(*)` jako číselný řetězec i při vypnutém `ATTR_STRINGIFY_FETCHES`. Test nyní ověřuje výsledek přes `FILTER_VALIDATE_INT` a porovnává přesně počet 0; nenumerická hodnota tedy není přetypována na úspěšnou nulu. Striktní snapshoty dat, hashů souborů a původní scénáře zůstávají beze změny. Produkční scanner ani ochrana mazání se kvůli tomu nemění; obě GitHub sady se spouštějí znovu na následné opravné revizi.

Automatické testy nenahrazují NVDA, zoom/reflow, skutečné SMTP ani hostingový smoke. Souběhová ochrana připomínek neřeší distribuované exactly-once doručení: SMTP přijetí a databázový commit nejsou jedna atomická operace. Při pádu mezi nimi může dojít k opakování a obsluha musí ověřit log/doručení před ručním opakováním. Během odesílání může změna stejného zdroje/rezervace čekat na dokončení SMTP; nejde o nový veřejný časový limit.

Lokálně byly při zahájení zastavené MySQL a Apache. Spuštěny byly binárky a konfigurace vybrané existujícím profilem Laragonu, bez změny datového adresáře nebo konfigurace. Testy používají pouze vlastní fixtures nebo spojení-lokální dočasné tabulky. Přístupnostní rozhodnutí a zbývající ruční scénáře jsou v `docs/accessibility/a11y-impact-decisions.md`.

## Transakční hranice Receptů, 2026-10-03

Výchozí revize `686574e7`, čistá a synchronizovaná větev `main`. Hloubkové čtení tohoto bloku se týká ukládání, publikace, strukturálního editoru, obnovy a vlastního koše Receptů. Není to nové řádkové review celého CMS. Schéma, `install.php`, `migrate.php` ani `VERSION` se nemění: nové sloupce nebo tabulky pro transakční ochranu nejsou potřeba. RC.2 se nevydává.

| ID | Priorita | Nález | Oprava a regresní důkaz |
|---|---|---|---|
| RC2-16 | P1 | Vlastní koš Receptů načetl smazaný stav před transakcí; mezitím obnovený recept šlo trvale odstranit včetně vazeb. | Locking read aktuálního rodiče před cleanupem, podmíněný DELETE a atomický rollback. Před opravou skutečný handler nad izolovanou DB odstranil mezitím obnovený recept. Po opravě zachová rodiče i vazby; skutečný MySQL souběh se starým snapshotem potvrzuje odmítnutí. |
| RC2-17 | P2 | Kontrola úplnosti publikace předcházela zápisu bez zámku; souběžné odstranění poslední ingredience mohlo zveřejnit neúplný recept. Selhání revize či výchozí skupiny nechávalo částečný zápis. | Rodičovský zámek a aktuální locking reads struktury; kontrola a uložení v jedné transakci, včetně revize či založení skupiny. Izolovaná reprodukce skutečného handleru, dva MySQL souběhy (odmítnutí neúplného a úspěšná publikace), rollback při vyvolané chybě. |
| RC2-18 | P2 | Strukturální záloha a zápis nebyly atomické; selhání druhého přesunu zanechalo částečně změněné pořadí a nepravdivou historii. | Všechny POST změny editoru serializované přes rodiče; záloha a zápisy jsou jedna transakce. Selhání skupiny nebo druhého UPDATE vrátí původní data i historii a zachová vstup s alertem; deletion helper podporuje transakci volajícího. |
| RC2-19 | P2 | Jednorázové návratové hodnoty neměly vazbu na ID receptu; otevření jiného editoru v téže relaci je mohlo spotřebovat a promítnout do cizího formuláře. | Flash obsahuje přesné ID (včetně nového formuláře), jiný editor jej nepoužije ani nespotřebuje. Skutečné HTTP otevře jiný recept mezi chybou a návratem do původního editoru a ověří oba obsahy. |

### Důkazy a omezení tohoto bloku

`build/rc_recipe_integrity_selftest.php` vykonává produkční handlery nad izolovanou SQLite, bez app bootstrapu nebo e-mailů. `build/rc_recipe_mysql_selftest.php` má tři samostatné procesové souběhy, pozorovaný skutečný čekající `FOR UPDATE`, starý REPEATABLE READ snapshot a vlastní uklizená data. `rc2_recipe_integrity_http` používá skutečné endpointy, MySQL triggery omezené testovacím ID a kontroluje rollback, zachování hodnot i existující ARIA reference. Nové testy jsou součástí `composer ci:module-ready` a izolovaná sada se opakuje s PDO číselnými řetězci.

Lokální lint a celý `composer ci:module-ready` prošly na PHP 8.4.12: 1 577 unit testů, statická analýza, schéma, ACR, release balíček, runtime audit a kompletní HTTP integrace. Nová izolovaná sada prošla 59 kontrolami a znovu 59 kontrolami s PDO číselnými řetězci; skutečný MySQL test ověřil tři souběhy a HTTP scénář `rc2_recipe_integrity_http` prošel. `git diff --check` je čistý. Jediná očekávaná výjimka runtime auditu je `smtp_connectivity = SKIP`, nikoli potvrzení doručitelnosti e-mailů na hostingu.

Transakční ochrana neznamená obecné slučování textových změn dvou správců; content lock a domluva editorů zůstávají důležité. Ruční NVDA, zoom/reflow a hostingový cron/log smoke zůstávají otevřené. GitHub CI se ověřuje zvlášť na konkrétní pushnuté revizi; lokální výsledky samy nepotvrzují Linux/PHP 8.0 ani úplnou WCAG shodu.

Revize a cleanup redirectů původně zpracovávaly PDO chybu jako best-effort. Recepty proto explicitně požadují nový volitelný striktní režim těchto sdílených helperů, aby jejich selhání skutečně vrátilo transakci. Výchozí chování ostatních volání zůstává kompatibilní; izolovaná sada vykonává produkční helpery a kontroluje i tuto výjimku, nikoli jen mock revize.

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

## Souborové hranice Galerie, 2026-10-02

Výchozí revize `0bc70652`, čistá větev `main`. Cílené čtení pokrylo doručování souborů Ke stažení, Vývěsky, Médií, Míst a Appmarketu, poté hlubší průchod souborových referencí Galerie v importu, veřejném čtení, ZIP exportu a bulk cleanupu. Potvrzené problémy tohoto bloku jsou v Galerii; nejde o úplný nový řádkový audit všech modulů. Schéma, `install.php`, `migrate.php` a `VERSION` se nemění: oprava nemá nový perzistentní stav.

| ID | Priorita | Nález | Oprava a regresní důkaz |
|---|---|---|---|
| RC2-14 | P1 | Import ukládal syrový `filename` fotografie; endpoint, ZIP collector i bulk cleanup jej spojovaly s adresářem bez ověření. Při existujících vadných/importovaných metadatech mohl návštěvník číst okolní soubor přes ID fotografie, export jej zabalit a potvrzené mazání jej odstranit. Nejde o přímo veřejný parametr filename: podmínkou jsou vadná metadata v DB. | Společná validace jednoduchého názvu rasteru a skutečné cesty uvnitř galerie/miniatur, odmítnutí symlinků a žádné zachraňování basename neplatné cesty. Import vadné řádky přeskočí s počtem. Před opravou izolovaná skutečná route vrátila 200 a sentinel mimo galerii místo 404; po opravě GET/HEAD full/thumb vrací 404. HTTP dokazuje bezpečný import, ZIP a oba bulk cleanupy s nezměněným okolním souborem. |
| RC2-15 | P2 | Sanitizace názvů alb nahrazovala jen lomítka/NUL, ale ponechala `..`, tečky, řídicí znaky či Windows drive části v ZIP cestách; archiv mohl mít nebezpečné member názvy při rozbalení. | Sdílené bezpečné segmenty cest při sběru kořene, podalb i pomocném builderu. Diakritika zůstává, dot segmenty a rezervované názvy se normalizují. Unit/izolované testy a rozbor skutečného HTTP ZIPu ověřují relativní cesty, obsah běžné fotografie a prázdného podalba; žádné sentinel bytes ani dot segmenty. |

### Ověření a omezení

Regrese patří do existující `composer ci:module-ready`: unit validátory, `build/rc_media_security_selftest.php` (také s PDO číselnými řetězci), runtime contract a `rc2_gallery_file_boundaries_http`. ZIP HTTP test nepoužívá rozbalení do filesystemu a čte centrální adresář i obsah členů; funguje pro ZipArchive i PHP fallback. Testy vytvářejí pouze vlastní náhodně pojmenované soubory, alba a metadata a uklízejí je s kontrolou vlastnictví.

Linux test zahrnuje nativní symlink. Na Windows jsou symlink a únik adresáře ověřené přes izolované filesystem hooky, bez potřeby měnit oprávnění operačního systému. Neprohlašujeme odolnost proti souběžným změnám filesystemu od uživatele se serverovým přístupem ani úplnou transakční atomicitu všech bulk operací. Ruční přístupnost a hostingový cron/log smoke zůstávají otevřené; RC.2 se tímto blokem nevydává.

Lokální `composer ci:module-ready` na PHP 8.4.12 prošel včetně lintů, statické analýzy, schématu, ACR, balíčku, runtime auditu a úplné HTTP integrace. Unit sada má 1 577 úspěšných testů bez chyby; rozšířená izolovaná souborová sada má 214 kontrol a dalších 214 s PDO číselnými řetězci. Nový `rc2_gallery_file_boundaries_http` i runtime contract jsou zelené. Produkční PHP fallback ZIPu je vykonaný a ověřený i nezávisle na přítomnosti ZipArchive. Jedinou očekávanou výjimkou je `smtp_connectivity = SKIP` kvůli nedostupnému portu 25 z lokální sítě; `git diff --check` prošel.

První plný běh zachytil zastaralou zdrojovou kontrolu původních logovacích volání v bulk větvích Galerie. Kontrola nyní vyžaduje společný cleanup v obou větvích a jeho strukturovaný log; není vypnutá. Nová vykonávaná regrese selhání `unlink` ověřuje zachování souboru a log s názvem a hashem, nikoli syrovou cestou. Po úpravě kontroly a doplnění této regrese se celý balík zopakoval bez přeskočení kontrol. GitHub výsledek musí být ověřený pro konkrétní pushnutý commit, nikoli odvozený z lokálního průchodu.
