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

### 2026-07-10: retrospektiva rozšíření Podcastů

- Datum a rozsah: rozšíření Podcastů o kapitoly, osoby/hosty a poslechové platformy v commitech `5b2a1872`, `83d89d72` a `bf505d06` a následné opravy.
- Dotčená kritéria: `1.3.1`, `2.1.1`, `2.4.3`, `3.3.1`, `3.3.2`, `3.3.3`, `3.3.4`, `4.1.2` a `4.1.3`.
- Rozhodnutí: původní rozšíření přidalo nové administrační formuláře a testy bez současného záznamu dopadu do ACR. Commit `0b0dbfcc` později doplnil konkrétní návrhy oprav pro validační chyby, ale mazání kapitol, osob a platforem stále spoléhalo jen na klientské `data-confirm`. Aktuální náprava proto přidává serverové review-and-confirm checkboxy, přesný PRG návrat, textové atomické alerty a field-level vazby. Stav `3.3.4` zůstává `Partially Supports`, protože širší produktové a ruční ověření pokračuje.
- Automatizovaný důkaz: runtime `podcast_source_guardrails`, HTTP `podcast_metadata_delete_error_prevention_http` a selftest change-aware conformance auditu, který reprodukuje původní variantu citlivé podcastové změny s automatickým testem, ale bez accessibility impact review.
- Ruční ověření nebo zbývající riziko: později projít s NVDA/Firefox a pouze klávesnicí chybu chybějícího potvrzení i potvrzené mazání každého ze tří typů metadat; viz `manual-test-protocol.md`.
