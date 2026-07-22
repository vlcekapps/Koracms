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
