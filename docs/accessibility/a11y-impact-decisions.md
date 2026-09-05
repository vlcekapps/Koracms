# Accessibility Impact Decisions

Tento soubor je stručný auditní deník pro změny přístupnostně citlivého kódu, které po vyhodnocení nemění stav kritéria ve WCAG matici, nevytvářejí novou položku backlogu ani nový ruční scénář. Skutečný nález nebo změna stavu patří přímo do `wcag-22-aa-conformance.md`, `acr-vpat-wcag-draft.md`, `a11y-remediation-backlog.md` a `manual-test-protocol.md`.

Záznam je povinný u nového nebo podstatně změněného formuláře, dialogu, ovládacího prvku, tabulky, média, embedu, live regionu, autentizace, časového limitu, řazení, exportu, mazání nebo jiné datově dopadající akce, pokud stejný commit neaktualizuje některý z hlavních accessibility dokumentů. `build/accessibility_conformance_audit.php` kontroluje tuto návaznost i aktualizovaný automatizovaný důkaz.

## Povinná osnova

- Datum a rozsah:
- Dotčená kritéria:
- Rozhodnutí:
- Automatizovaný důkaz:
- Ruční ověření nebo zbývající riziko:

## Rozhodnutí

### 2026-09-05: modulový audit pro RC.2

- Datum a rozsah: historie revizí napříč moduly, Galerie, Ankety, poptávky Food a potvrzení kontaktního formuláře. Registr nálezů: `docs/rc2-module-audit-2026-09.md`.
- Dotčená kritéria: `1.3.1`, `2.1.1`, `3.3.1`, `3.3.2`, `3.3.3`, `3.3.4`, `3.3.7`, `4.1.2`, `4.1.3`.
- Rozhodnutí: odmítnuté zadání se zachovává, množství se nemění bez vědomí návštěvníka, chyby jsou přiřazené konkrétním vstupům a oznámení rozlišuje uložení od doručení e-mailu. Registr revizí vyhodnocuje skutečná oprávnění před načtením historie. Bez nového ovládacího modelu, JS závislosti nebo databázové změny; hlavní ACR se nepovyšuje na Supports.
- Automatizovaný důkaz: `build/rc2_modules_selftest.php` vykonává skutečné helpery, save handlery a šablony, kontroluje existující ARIA cíle a jednorázovou obnovu polí; běží i s PDO číselnými řetězci. `build/rc2_modules_http.php` a `build/http_integration.php` ověřují oprávnění a neplatné množství přes HTTP. `build/runtime_audit.php` hlídá jejich integraci.
- Ruční ověření nebo zbývající riziko: s NVDA/Firefox ověřit oznámení chyby množství a selhání notifikace; bez JS projít chybu licence fotografie a času ankety včetně zachování zadání. Mobilní reflow, zoom a vlastní šablony zůstávají ručním ověřením; tyto testy nejsou tímto záznamem prohlášeny za provedené.

### 2026-09-05: vydání 5.0.0-rc.1 a čistota balíčku

- Datum a rozsah: dokumentace nasazení RC, vynechání lokálních agentních metadat, cache a integrity snapshotu z distribuce a bezpečný úklid dočasného balicího adresáře.
- Dotčená kritéria: bez nové změny uživatelského rozhraní; omezení předchozího RC auditu včetně přístupného opakovaného přihlášení zůstávají platná.
- Rozhodnutí: RC je výslovně prerelease. Synchronizace `Version evaluated` release skriptem nepředstavuje nové ruční testování ani zvýšení stavu shody. Hosting lze ověřit až po nahrání; příručka uvádí kontrolu i návrat ze zálohy.
- Automatizovaný důkaz: release package audit s negativními mutacemi a release smoke test skutečně vytvořených lokálních artefaktů v ZIPu i source archive; před vydáním je vyžadován `composer ci:full`.
- Ruční ověření nebo zbývající riziko: NVDA/Firefox, klávesnice, zoom/reflow, kontrast vlastní šablony a produkční e-maily zůstávají otevřené. Nevydáváme certifikaci ani stabilní 5.0.0.

### 2026-09-05: RC audit editorů, médií a provozních akcí

- Datum a rozsah: autosave a obnova editorů, validační návraty, publikace a náhledy, náhrady médií, rezervace, ankety, newsletter, schránka a přihlášení včetně starší instalace před migrací; podrobný registr RC-01 až RC-45 je v `docs/rc-audit-2026-09.md`.
- Dotčená kritéria: `2.1.1`, `2.2.1`, `2.4.3`, `3.3.1`, `3.3.3`, `3.3.4`, `3.3.7`, `4.1.2`, `4.1.3`.
- Rozhodnutí: jednoznačné runtime chyby se opravují bez zjednodušování autorizace a bez odstraňování chybových stavů. U odmítnuté akce zůstává původní obsah a soubor zachovaný, hláška odpovídá skutečnému výsledku a návrat obsahuje vyplněné hodnoty. Potvrzené nálezy jsou zároveň v hlavním backlogu; globální stavy shody se automaticky nezvyšují.
- Automatizovaný důkaz: `composer test:rc-core`, `composer test:rc-runtime`, `rc_publication_window_http` a stávající runtime/HTTP sada. Skutečný JavaScript schránky je testovaný při zamítnutí oprávnění, selhání fallbacku i úspěchu a při navrácení fokusu; databázové testy používají izolované tabulky či vlastní uklizené fixtures.
- Následné RC-44 mění pouze vývojové audity a DB test, nikoli produkční formuláře, oprávnění ani schéma. `repository_guardrails_audit_selftest.php` prokazuje pokrytí nových souborů v pěti auditech i vynechání ignorované konfigurace; 26 MySQL kontrol rezervací zůstává zelených. Stav WCAG/ACR se tím nemění.
- Následné RC-45 řeší pouze přenositelnost testů mezi PDO ovladači, bez změny produkčního rozhraní a stavů WCAG/ACR. Automatizovaný důkaz doplňuje `rc_pdo_fetch_selftest.php`: opakuje šest sad s číselnými řetězci, včetně ochrany rozepsaných hodnot, souborů a chybových návratů.
- Ruční ověření nebo zbývající riziko: s NVDA/Firefox zopakovat obnovu dlouhého editoru, chyby polí a odmítnutí datových akcí; provést 400% reflow a kontrast aktivní šablony. Změna relací/2FA RC-02/03 byla výslovně schválena. HTTP `rc_session_security_http` ověřuje zneplatnění při změně účtu a desetiminutovou platnost druhého faktoru; nejde o časový limit editoru. Existující relace se po aktualizaci znovu přihlásí, proto před nasazením uložit rozpracovaný obsah. Ruční ověření přístupného opakovaného přihlášení zůstává podmínkou RC.

### 2026-07-24: platformně nezávislá validace serverových URL

- Datum a rozsah: sdílená SSRF ochrana pro serverové načítání vzdálených URL, zejména import fotografií z eStránek.
- Dotčená kritéria: bez přímého dopadu na WCAG 2.2 A/AA; nepřímo souvisí se stabilitou administračního workflow.
- Rozhodnutí: validátor nyní binárně rozpozná IPv4 adresu vloženou do IPv6 a ověří její skutečný rozsah stejně na Windows i Linuxu. Nemění se formulář, popis pole, klávesové ovládání, fokus, chybové hlášky ani časový limit, proto se stav hlavní WCAG matice nemění.
- Automatizovaný důkaz: unit testy pokrývají textový i hexadecimální IPv4-mapped loopback, veřejnou mapovanou adresu, literal host i celé URL; runtime guardraily vyžadují explicitní rozbalení mapované IPv4 před kontrolou soukromých a rezervovaných rozsahů.
- Ruční ověření nebo zbývající riziko: žádný nový přístupnostní scénář není nutný. Funkční import finální veřejné HTTPS URL zůstává vhodné ověřit na cílovém hostingu podle existujícího protokolu; redirecty zůstávají z bezpečnostních důvodů zakázané.

### 2026-07-22: přehled využití zdrojů Ke stažení

- Datum a rozsah: read-only sekce `Ke stažení` v detailních administračních statistikách, veřejné metriky na detailu položky a bezpečný externí redirect endpoint.
- Dotčená kritéria: `1.3.1`, `1.3.2`, `2.4.4`, `2.4.6`, `3.2.2` a `4.1.2`.
- Rozhodnutí: dosavadní čítače veřejných lokálních downloadů a otevření externích zdrojů u aktuálně zveřejněných položek se zobrazují samostatně a odděleně od návštěvnosti za zvolené období. Sekce má skutečný nadpis, tabulka je pojmenovaná přes `aria-labelledby`, používá caption a sloupcové hlavičky a viditelný text vysvětluje význam obou metrik. Externí CTA zachovává svůj srozumitelný název, před aktivací zobrazuje cílovou doménu a oznamuje nové okno; interní read-only endpoint pouze načte uložený bezpečný cíl, započítá veřejný GET a přesměruje bez další volby nebo změny kontextu formuláře. Stav hlavní WCAG matice se nemění.
- Automatizovaný důkaz: runtime guardrail kontroluje lokální preflight před čítačem, uložený a normalizovaný externí cíl, bezpečné hlavičky, oddělené metriky, heading-backed tabulku a canonical odkazy; HTTP scénáře ověřují GET/HEAD chování obou endpointů, zachování čítače při chybějícím souboru, veřejný detail, statistiky i skrytí sekce při vypnutém modulu.
- Ruční ověření nebo zbývající riziko: s NVDA/Firefox a pouze klávesnicí ověřit veřejná tlačítka, oddělené metriky detailu a průchod administrační tabulkou. Otevření externího zdroje je pouze počet veřejných přesměrování a neprokazuje dokončený download na vzdáleném webu; `nofollow` omezuje běžné roboty, ale neodfiltruje veškeré automatizované požadavky.

### 2026-07-22: bezpečná baseline integrity a vzdálený import fotografií

- Datum a rozsah: administrační kontrola integrity souborů a dávkový downloader fotografií z eStránek.
- Dotčená kritéria: `1.3.1`, `3.3.1`, `3.3.3`, `3.3.4`, `4.1.2` a `4.1.3`.
- Rozhodnutí: obnova integrity baseline nově popisuje nevratný dopad, používá fieldset s viditelným legendem, vyžaduje serverově ověřený checkbox a při odmítnutí nebo chybě zápisu vrátí atomický alert a field-level vazbu bez změny snapshotu nebo audit logu. Kontrola už nevynechává veřejné PHP šablony ani případné PHP a ochranné `.htaccess` soubory v `uploads/`. Downloader fotografií zachovává stejný field-level chybový vzor a přesněji vysvětluje povolený veřejný cíl i požadavek cURL. Stav hlavní WCAG matice se nemění; `3.3.4` zůstává `Partially Supports`, protože širší produktové ověření pokračuje.
- Automatizovaný důkaz: unit testy normalizace serverového fetch cíle, runtime `estranky_photo_guardrails` a integrity guardraily a HTTP scénáře `admin_import_error_suggestions_http` a `integrity_snapshot_error_prevention_http`.
- Ruční ověření nebo zbývající riziko: s NVDA/Firefox a pouze klávesnicí ověřit chybějící potvrzení i chybu nezapisovatelného privátního úložiště; downloader vyzkoušet s konečnou HTTPS URL eStránek. Redirecty jsou bezpečnostně zakázané, takže správce musí zadat finální URL.

### 2026-07-17: textové oddělení metadat widgetu Ke stažení

- Datum a rozsah: homepage varianta veřejného widgetu Nejnovější položky ke stažení, konkrétně řádek typu položky, verze, data vydání a platformy.
- Dotčená kritéria: 1.3.1 a 1.3.2.
- Rozhodnutí: sousední elementy metadat byly vizuálně oddělené pouze CSS vlastností gap, ale jejich textový obsah neobsahoval mezery, takže NVDA spojovalo například verzi a datum. Widget nově skládá metadata do jediné textové posloupnosti se skutečnými mezerami. Stav hlavní WCAG matice se nemění, protože jde o lokální regresní opravu již podporované struktury.
- Automatizovaný důkaz: unit sekce widget metadata semantics ověřuje přesný text Software 0.10.1 17. června 2026, 00:00 Android; runtime audit widget_registry hlídá použití sdíleného helperu a skutečného textového oddělovače v rendereru.
- Ruční ověření nebo zbývající riziko: na veřejné homepage ověřit s NVDA/Firefox plynulé čtení celé karty a správné pauzy mezi typem, verzí, datem a platformou; sidebar/footer varianta už používá textové oddělovače a nebyla měněna.

### 2026-07-10: retrospektiva rozšíření Podcastů

- Datum a rozsah: rozšíření Podcastů o kapitoly, osoby/hosty a poslechové platformy v commitech `5b2a1872`, `83d89d72` a `bf505d06` a následné opravy.
- Dotčená kritéria: `1.3.1`, `2.1.1`, `2.4.3`, `3.3.1`, `3.3.2`, `3.3.3`, `3.3.4`, `4.1.2` a `4.1.3`.
- Rozhodnutí: původní rozšíření přidalo nové administrační formuláře a testy bez současného záznamu dopadu do ACR. Commit `0b0dbfcc` později doplnil konkrétní návrhy oprav pro validační chyby, ale mazání kapitol, osob a platforem stále spoléhalo jen na klientské `data-confirm`. Aktuální náprava proto přidává serverové review-and-confirm checkboxy, přesný PRG návrat, textové atomické alerty a field-level vazby. Stav `3.3.4` zůstává `Partially Supports`, protože širší produktové a ruční ověření pokračuje.
- Automatizovaný důkaz: runtime `podcast_source_guardrails`, HTTP `podcast_metadata_delete_error_prevention_http` a selftest change-aware conformance auditu, který reprodukuje původní variantu citlivé podcastové změny s automatickým testem, ale bez accessibility impact review.
- Ruční ověření nebo zbývající riziko: později projít s NVDA/Firefox a pouze klávesnicí chybu chybějícího potvrzení i potvrzené mazání každého ze tří typů metadat; viz `manual-test-protocol.md`.
