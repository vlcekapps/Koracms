# Accessibility Remediation Backlog

Tento backlog navazuje na `wcag-22-aa-conformance.md`. Neobsahuje všechny nápady, ale nejdůležitější rizika, která mohou změnit stav WCAG/ACR řádků z `Partially Supports` na `Supports`.

## Vysoká priorita

| Oblast | Kritéria | Riziko | Doporučený další krok |
|---|---|---|---|
| Aktuálně bez otevřeného kritického nálezu | — | Prioritní 320 px reflow průchod hlavních admin obrazovek byl provedený 2026-07-04 a potvrzený browser měřením. | Další kritické nálezy sem přesunout až po ručním průchodu zbylých modulů nebo po regresi. |

## Střední priorita

| Oblast | Kritéria | Riziko | Doporučený další krok |
|---|---|---|---|
| Média a titulky | 1.2.1 až 1.2.5 | CMS podporuje embedy, ale neumí systematicky vést autora k přepisům, titulkům a audio description. | Doplnit dokumentaci pro autory a zvážit metadata pro transcript/caption u vlastních video/audio médií. |
| Kontrast custom/hover/disabled stavů | 1.4.3, 1.4.11, 2.4.7 | Runtime audit měří default/admin/login text, focus, input border a button border tokeny, ale ne všechny custom theme settings, hover/disabled stavy, ikony a progress bary v reálném prohlížeči. | Ručně změřit default i aktivní theme varianty ve Firefox/Chrome a při nálezu doplnit konkrétní CSS token nebo nový auditní pár. |
| 400% reflow a custom modulové varianty | 1.4.10, 2.4.11, 2.5.8 | Browser průchody 2026-07-04 pokryly hlavní husté admin tabulky, importy, content picker a reprezentativní dlouhé formuláře při 320 px. Zbývají méně časté custom moduly, sticky/anchor kombinace a ověření 400 % zoom + asistivní technologie. | Rozšířit ruční průchod na custom module admin obrazovky, dlouhé formuláře s reálnými daty a keyboard-only/NVDA scénáře při 400 % zoomu. |
| Specializovaný input purpose | 1.3.5 | Auth flow, kontakt, food objednávky, guest rezervace a běžná Form Builder pole mají automatizované guardraily, ale případná adresní, platební nebo jiná specializovaná custom pole zatím nemají vlastní mapování. | Při přidání adresních/platebních polí doplnit explicitní `autocomplete` tokeny a ručně ověřit autofill ve Firefox/Chrome. |
| Text spacing ruční průchod | 1.4.12 | Core CSS má automatizovaný guardrail proti známým blokátorům text spacingu, ale zatím chybí prohlížečové ověření se skutečným text-spacing override. | Projít veřejné a admin šablony s text spacing bookmarkletem nebo ekvivalentním CSS testem ve Firefox/Chrome. |
| Error suggestions | 3.3.3 | Základní veřejné chyby matematického ověření, vybrané administrační URL chyby, e-mailové chyby, běžné datumové chyby a plánovací datum/čas chyby včetně rezervačních zdrojů už radí konkrétní opravu, ale méně časté administrační a custom validační chyby mohou pořád jen označit hodnotu za chybnou. | Pokračovat copy passem nad zbývajícími administračními, custom a méně častými chybami formulářů a doplnit konkrétní návrhy. |
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

- 2026-07-04: Prioritní browser průchod admin reflow při 320 px ověřil media, widgets, statistics, Form Builder a přehled formulářů. Nalezený globální horizontální scroll ve statistikách a přehledu formulářů byl opraven přes `.table-responsive`, posílené CSS containment, cache-busting `admin/assets/layout.css` a wrapper skrytých datových tabulek grafů; runtime `admin_mobile_reflow_guardrails` teď hlídá i forms overview a cache-busting.
- 2026-07-04: Navazující browser průchod admin reflow při 320 px ověřil comments, contact, chat, reservations, food, downloads, gallery, importy a content picker. Nalezený root overflow ve food/downloads/gallery a main-level scroll u contact/chat byl opraven lokálními `.table-responsive` wrappery; sekundární odkazy v `.button-row` a `.actions` mají minimální target height. Runtime `admin_mobile_reflow_guardrails` teď hlídá i tyto husté moduly.
- 2026-07-04: Browser průchod reprezentativních dlouhých admin formulářů při 320 px ověřil page/blog/news/event/download/gallery/food/board/FAQ/place/polls/reservations/podcast. Nalezený main-level scroll způsobený min-content šířkou fieldsetů byl opraven sdíleným CSS, rezervační otevírací doba a podcastové přehledy dostaly `.table-responsive` wrappery a přímé action odkazy v odstavcích mají minimální target height. Runtime `admin_mobile_reflow_guardrails` teď hlídá i tyto kontrakty.
- 2026-07-04: `1.4.10 Reflow`, `2.4.11 Focus Not Obscured` a `2.5.8 Target Size` mají runtime `admin_mobile_reflow_guardrails`, který hlídá stackování admin navigace na mobilní šířce, lokální scroll datových tabulek, one-column collapse media/Form Builder/statistics gridů, flexibilní action rows a minimální rozměr řadicích ovladačů, sekundárních action odkazů i přímých action odkazů v odstavcích; po browser průchodech prioritních, hustých tabulkových a reprezentativních dlouhých formulářových stránek zůstává práce na 400 % zoomu, custom modulech a asistivních kombinacích.
- 2026-07-04: `1.4.3 Contrast (Minimum)`, `1.4.11 Non-text Contrast` a `2.4.7 Focus Visible` mají nový runtime `contrast_focus_guardrails`, který měří default/admin/login textové páry, stavové hlášky, focus tokeny, skip link a hranice inputů/tlačítek; zbylá práce je ruční měření custom theme, hover/disabled, ikon a progress stavů.
- 2026-07-04: `3.3.8 Accessible Authentication (Minimum)` je po automatizovaných guardrailech a ručním potvrzení auth flow vedené jako `Supports`; při změnách loginu, registrace, 2FA, tokenového resetu nebo session timeoutů se ruční scénář z `manual-test-protocol.md` opakuje.
- 2026-07-04: command centrum, widget dialog a content/media picker prošly ručním NVDA/keyboard-only ověřením bez regrese; při změnách JS dialogů se znovu ověřuje Escape, Tab focus smyčka, návrat fokusu a oznamovaný stav ovládacích prvků.
- 2026-07-04: `1.3.5 Identify Input Purpose` má automatizovaný guardrail pro auth metadata, veřejný kontakt, food objednávky, guest rezervace a Form Builder renderer. Form Builder nově používá sdílený helper pro `email`, `tel`, `url`, zjevné jmenné textové pole a organizaci; HTTP integrace ověřuje render kontaktu, food objednávky a veřejného formuláře.
- 2026-07-04: `1.4.12 Text Spacing` má runtime `text_spacing_guardrails`, který v core CSS hlídá záporné `letter-spacing`, `text-overflow: ellipsis`, line clamp a `!important` zámky na text-spacing vlastnostech. Default, modern-service a editorial šablona už nepoužívají záporné prostrkání nadpisů a administrační SEO preview dlouhé titulky/popisy zalamuje místo ořezu.
- 2026-07-04: `3.3.3 Error Suggestion` má sdílený helper pro chybu veřejné matematické ověřovací otázky. Kontakt, newsletter subscribe, odběr vývěsky, Food objednávkové poptávky a Form Builder zobrazují konkrétní návrh opravy a runtime `public_error_suggestion_guardrails` s HTTP integrací hlídají, aby se nevrátila pouze generická hláška.
- 2026-07-04: Admin URL validační chyby u zajímavých míst, podcastových pořadů a podcastových epizod už nevypisují jen „platný formát“. Field-level texty radí použít http/https adresu nebo doménu bez schématu, připomínají automatické uložení jako `https://` a u volitelných polí říkají, kdy je nechat prázdná; runtime `admin_field_error_guardrails` hlídá návrat generických textů.
- 2026-07-04: Admin plánovací datum/čas chyby u statických stránek, článků, novinek, událostí, anket, rezervačních zdrojů a podcastových epizod už nevypisují pouze „neplatný formát“. Texty radí vybrat hodnotu v poli datum a čas, volitelné plánování nechat prázdné, odstranit prázdný řádek nebo opravit pořadí začátku/konce; runtime `admin_field_error_guardrails` hlídá návrat generických textů.
- 2026-07-04: Admin e-mailové chyby ve vývěsce, událostech, jídelních lístcích, Form Builderu, tématech kontaktu, místech, podcastových pořadech, nastavení, profilu a správě uživatelů už nevypisují pouze „platný formát“. Texty radí úplnou adresu ve tvaru `jmeno@example.cz`, prázdné volitelné pole nebo jedinečnost přihlašovací adresy; runtime `admin_field_error_guardrails` hlídá návrat generických textů.
- 2026-07-04: Admin běžné datumové chyby ve vývěsce, jídelních lístcích a položkách ke stažení už nevypisují pouze „neplatný formát“. Texty radí vybrat kalendářní datum, volitelné pole nechat prázdné nebo opravit pořadí od/do; datum vydání u downloadů má field-level chybu přes `aria-describedby` a runtime `admin_field_error_guardrails` hlídá návrat generických textů.
- 2026-07-04: Admin chyby zdrojů položek ke stažení už nevypisují pouze obecné validační texty. Chybějící zdroj radí lokální soubor nebo externí odkaz, neplatné URL radí http/https nebo doménu bez schématu, volitelná domovská stránka připomíná prázdné pole a checksum vysvětluje přesných 64 znaků `0-9`/`a-f`; runtime `admin_field_error_guardrails` hlídá field-level napojení i návrat generických textů.
- 2026-07-04: Admin obrazové uploady u článků, vývěsky, událostí, míst a položek ke stažení už nevypisují pouze obecné selhání uploadu. Texty radí JPEG/PNG/GIF/WebP, výslovně odmítají SVG a jiné formáty a připomínají prázdné volitelné pole; download náhled má nově field-level chybu přes `aria-describedby` a runtime `admin_field_error_guardrails` hlídá návrat generických textů.
