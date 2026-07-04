# Accessibility Remediation Backlog

Tento backlog navazuje na `wcag-22-aa-conformance.md`. Neobsahuje všechny nápady, ale nejdůležitější rizika, která mohou změnit stav WCAG/ACR řádků z `Partially Supports` na `Supports`.

## Vysoká priorita

| Oblast | Kritéria | Riziko | Doporučený další krok |
|---|---|---|---|
| Ruční reflow průchod administrace | 1.4.10, 2.4.11, 2.5.8 | Runtime audit hlídá sdílený mobilní baseline pro admin layout, datové tabulky a komplexní gridy, ale reálný 320 px / 400 % průchod nad daty ještě není potvrzený. | Projít hlavní admin workflow při 320 px/400 % zoomu, prioritně media, widgets, statistics, Form Builder a řádkové akce v tabulkách. |

## Střední priorita

| Oblast | Kritéria | Riziko | Doporučený další krok |
|---|---|---|---|
| Média a titulky | 1.2.1 až 1.2.5 | CMS podporuje embedy, ale neumí systematicky vést autora k přepisům, titulkům a audio description. | Doplnit dokumentaci pro autory a zvážit metadata pro transcript/caption u vlastních video/audio médií. |
| Kontrast custom/hover/disabled stavů | 1.4.3, 1.4.11, 2.4.7 | Runtime audit měří default/admin/login text, focus, input border a button border tokeny, ale ne všechny custom theme settings, hover/disabled stavy, ikony a progress bary v reálném prohlížeči. | Ručně změřit default i aktivní theme varianty ve Firefox/Chrome a při nálezu doplnit konkrétní CSS token nebo nový auditní pár. |
| Autocomplete a input purpose | 1.3.5 | Auth flow má automatizovaný guardrail, ale ne všechna další osobní pole mají ověřené `autocomplete`. | Projít kontakt, rezervace, food objednávky a Form Builder šablony. |
| Text spacing | 1.4.12 | CSS pravděpodobně neblokuje spacing, ale není doložené ručním testem. | Projít veřejné a admin šablony s text spacing bookmarkletem nebo ekvivalentním CSS testem. |
| Error suggestions | 3.3.3 | Některé validace jen řeknou, že hodnota je chybná, ale nemusí poradit opravu. | Udělat copy pass nad chybami formulářů a doplnit konkrétní návrhy. |
| Redundant entry | 3.3.7 | Opakované zadávání jména/e-mailu nebo kontaktních údajů může zůstávat ve více modulech. | Zmapovat veřejná a admin flow, kde lze bezpečně předvyplnit nebo znovu použít dříve zadanou hodnotu. |

## Nízká priorita

| Oblast | Kritéria | Riziko | Doporučený další krok |
|---|---|---|---|
| Language of parts | 3.1.2 | Cizojazyčný obsah v HTML editoru závisí na autorovi. | Doplnit nápovědu do dokumentace autorů a zvážit snippet nebo toolbar helper pro `lang`. |
| Consistent Help | 3.2.6 | Help mechanismy nejsou ještě inventarizované jako jeden systém. | Rozhodnout, zda CMS bude mít jednotný help/contact vzor na všech admin stránkách. |
| Author content governance | 1.1.1, 1.2.x, 3.1.2 | CMS může být přístupný, ale ruční obsah může conformance rozbít. | Připravit krátký checklist pro editory obsahu. |

## Evidence pravidla

- Každá položka backlogu musí po opravě aktualizovat `wcag-22-aa-conformance.md`.
- Pokud oprava mění chování CMS, doplní se test nebo audit guardrail.
- Pokud oprava vyžaduje nová data, postupuje se end-to-end přes instalaci, migraci, export/import, schema parity a dokumentaci.
- Pokud se rozhodne, že problém zůstává odpovědností autora obsahu, musí být jasně popsán v dokumentaci.
- Nový modul nebo větší modulové rozšíření musí před implementací ověřit, zda nezhoršuje některou položku tohoto backlogu. Pokud přidává nový rizikový vzor, například média, captcha/auth flow, dialog, upload, tabulku, časový limit nebo autorem dodávaný obsah, doplní se nová položka backlogu nebo odpovídající řádek ve WCAG matici.

## Uzavřená evidence

- 2026-07-04: `1.4.10 Reflow`, `2.4.11 Focus Not Obscured` a `2.5.8 Target Size` mají nový runtime `admin_mobile_reflow_guardrails`, který hlídá stackování admin navigace na mobilní šířce, lokální scroll datových tabulek, one-column collapse media/Form Builder/statistics gridů, flexibilní action rows a minimální rozměr řadicích ovladačů; zbylá práce je ruční průchod 320 px / 400 % s reálnými daty.
- 2026-07-04: `1.4.3 Contrast (Minimum)`, `1.4.11 Non-text Contrast` a `2.4.7 Focus Visible` mají nový runtime `contrast_focus_guardrails`, který měří default/admin/login textové páry, stavové hlášky, focus tokeny, skip link a hranice inputů/tlačítek; zbylá práce je ruční měření custom theme, hover/disabled, ikon a progress stavů.
- 2026-07-04: `3.3.8 Accessible Authentication (Minimum)` je po automatizovaných guardrailech a ručním potvrzení auth flow vedené jako `Supports`; při změnách loginu, registrace, 2FA, tokenového resetu nebo session timeoutů se ruční scénář z `manual-test-protocol.md` opakuje.
- 2026-07-04: command centrum, widget dialog a content/media picker prošly ručním NVDA/keyboard-only ověřením bez regrese; při změnách JS dialogů se znovu ověřuje Escape, Tab focus smyčka, návrat fokusu a oznamovaný stav ovládacích prvků.
