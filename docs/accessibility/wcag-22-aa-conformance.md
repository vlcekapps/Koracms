# WCAG 2.2 AA Conformance Matrix

Stav: draft baseline pro produkt Kora CMS.

Tento dokument hodnotí Kora CMS jako redakční systém, ne konkrétní obsah jednoho webu. Vychází z WCAG 2.2 Level A a AA, z existujících automatických guardrailů v projektu a z dosavadní ruční práce na přístupnosti. Nejde o právní certifikaci ani náhradu nezávislého auditu.

Oficiální zdroje:

- [W3C WCAG 2 Overview](https://www.w3.org/WAI/standards-guidelines/wcag/)
- [W3C WCAG 2.2 Quick Reference](https://www.w3.org/WAI/WCAG22/quickref/)
- [W3C WCAG-EM](https://www.w3.org/WAI/test-evaluate/conformance/wcag-em/)
- [ITI VPAT](https://www.itic.org/policy/accessibility/vpat)

## Metodika

- Standard: WCAG 2.2 Level AA.
- Rozsah: administrace, veřejné šablony, widgety, moduly, formuláře, dialogy, content/media picker, systémové stránky a výchozí theme.
- Stav `Supports` znamená, že CMS má přímou podporu a existující automatické nebo dokumentované důkazy.
- Stav `Partially Supports` znamená, že CMS poskytuje významnou podporu, ale kritérium vyžaduje ruční ověření, závisí na obsahu autora, nebo má známé riziko.
- Stav `Not Applicable` znamená, že Kora CMS daný typ obsahu nebo interakce sama nevytváří.
- Author-supplied content: ručně vložené HTML, média, titulky, texty odkazů a alt texty zůstávají odpovědností autora obsahu; CMS má poskytovat bezpečná pole, nápovědu a fallbacky.

## Reprezentativní testovací sada

- Veřejná homepage a default theme layout.
- Blog index, detail článku, dlouhý článek s osnovou, kategorie, štítky a série.
- Vyhledávání, widgety, content snippety, media picker a PDF preview.
- Form Builder, komentáře, kontakt, chat, ankety, newsletter subscribe.
- Galerie, média, downloads, board, events, places, reservations, food a podcast.
- Administrace: dashboard, menu, settings, widgets, media, modulové přehledy, formulářové editory, dialogy a command centrum.
- Samostatné stránky: install, migrate, maintenance, login, 2FA, 404, 429 a tokenové potvrzovací stránky.

## WCAG 2.2 A/AA Matrix

| Kritérium | Level | Stav | Vztah ke Kora CMS | Důkaz / test | Známé mezery a další krok |
|---|---:|---|---|---|---|
| 1.1.1 Non-text Content | A | Supports | CMS má alt pole pro média, galerie, loga a obrázky; dekorativní obrázky mohou mít prázdný alt. | Theme view audit hlídá `alt`; media/gallery metadata. | Ručně ověřit author content a importované obrázky bez alt textu. |
| 1.2.1 Audio-only and Video-only | A | Partially Supports | CMS umí audio/video embedy, ale nepřidává automaticky textové alternativy. | Podcast/audio/video snippety a dokumentace snippetů. | Doplnit autorům nápovědu pro přepisy a titulky. |
| 1.2.2 Captions (Prerecorded) | A | Partially Supports | CMS podporuje vložení videí, ale titulky jsou odpovědnost autora nebo externí platformy. | Video snippet, externí embed CSP. | Přidat dokumentační požadavek a případně pole pro transcript/captions u vlastních videí. |
| 1.2.3 Audio Description or Media Alternative | A | Partially Supports | CMS nebrání popisu videa, ale neposkytuje samostatný workflow pro audio description. | HTML editor a snippety. | Ruční ověření mediálních workflow. |
| 1.2.4 Captions (Live) | AA | Not Applicable | CMS sám nevytváří živé audio/video vysílání. | Žádný live media modul v manifestu. | Pokud vznikne live modul, přidat caption policy. |
| 1.2.5 Audio Description (Prerecorded) | AA | Partially Supports | CMS umožňuje doplnit textový popis v obsahu, ale audio description není modelovaná jako pole. | HTML editor a video snippet. | Zvážit pole pro popis/transkript u vlastních video médií. |
| 1.3.1 Info and Relationships | A | Supports | Layouty, formuláře, tabulky a sekce používají nadpisy, labely, fieldset/legend, caption a ARIA vazby. | Runtime audit, theme view audit, module guardrails. | Ručně ověřit komplexní admin tabulky a dynamické dialogy. |
| 1.3.2 Meaningful Sequence | A | Supports | Veřejné šablony a admin layout drží logické pořadí nadpisů, obsahu a akcí. | Theme view audit a ruční UX pravidla. | Ověřit mobilní pořadí u gridů a dashboardu. |
| 1.3.3 Sensory Characteristics | A | Supports | Instrukce nepoužívají jen tvar, polohu nebo barvu; povinné prvky mají text. | Runtime guardrails pro barvu a hidden texty. | Ručně ověřit nové moduly a grafické prvky. |
| 1.3.4 Orientation | AA | Supports | CMS neomezuje orientaci zařízení. | Responzivní veřejné/admin CSS. | Ručně ověřit landscape/portrait u hlavních stránek. |
| 1.3.5 Identify Input Purpose | AA | Partially Supports | Auth flow používá `autocomplete` pro `username`, `current-password`, `new-password` a 2FA `one-time-code`; další formuláře mají běžné typy polí a část autocomplete podpory. | Runtime audit a `auth_accessibility_http` hlídají auth metadata; formulářové auditní guardraily. | Doplnit systematickou kontrolu `autocomplete` u kontaktu, objednávek, rezervací a Form Builder šablon. |
| 1.4.1 Use of Color | A | Supports | Stavové texty, štítky a dostupnost nejsou sděleny jen barvou. | Runtime/theme guardrails a dokumentace Food/Stats. | Ručně ověřit custom theme settings. |
| 1.4.2 Audio Control | A | Not Applicable | CMS sám automaticky nespouští audio. | Žádné autoplay audio v core snippetech. | Pokud autor vloží autoplay embed, jde o author content. |
| 1.4.3 Contrast (Minimum) | AA | Partially Supports | Výchozí theme a admin CSS cílí na AA kontrast. | CSS guardrails a dosavadní ruční práce. | Potřebné ruční měření barev všech theme variant a stavů focus/hover. |
| 1.4.4 Resize Text | AA | Supports | Layouty jsou responzivní a nepoužívají pevné blokování zoomu. | Viewport meta a CSS audit. | Ručně ověřit zoom 200 %. |
| 1.4.5 Images of Text | AA | Supports | CMS nepoužívá text v obrázcích jako UI prvek. | Theme audit obrázků a textových nadpisů. | Author content může vložit obrázek s textem; dokumentovat jako odpovědnost autora. |
| 1.4.10 Reflow | AA | Partially Supports | Veřejná i admin vrstva používá responzivní layouty. | Theme view audit, runtime smoke. | Ručně ověřit 320 px šířku u admin tabulek, pickeru a dialogů. |
| 1.4.11 Non-text Contrast | AA | Partially Supports | Focus, tlačítka a stavové prvky mají viditelné styly. | README a runtime guardrails. | Ručně změřit ikony, okraje inputů, progress bary a disabled stavy. |
| 1.4.12 Text Spacing | AA | Partially Supports | Výchozí CSS nemá známé blokování spacingu. | Theme CSS bez inline stylů. | Ručně ověřit text spacing bookmarkletem nebo ekvivalentním testem. |
| 1.4.13 Content on Hover or Focus | AA | Partially Supports | Dialogy a toolbary jsou navržené s focus managementem; hover-only obsah není hlavní vzor. | Content picker a widget dialog guardrails. | Ručně projít dropdowny, nápovědy a admin menu. |
| 2.1.1 Keyboard | A | Partially Supports | Formuláře, navigace, command centrum a hlavní dialogové vzory mají klávesové chování. | Runtime/theme audit a HTTP scénáře; guardrails hlídají Escape/Tab focus smyčku a návrat fokusu u command centra, widget dialogu a content/media pickeru; ruční průchod těchto dialogů bez regrese potvrzen 2026-07-04. | Ručně projít husté admin workflow a dlouhé formuláře bez myši ve Firefox/NVDA a Chrome keyboard-only. |
| 2.1.2 No Keyboard Trap | A | Supports | Dialogy mají focus trap s návratem fokusu a escape/close chováním. | Picker, widgets, command centrum guardrails; ruční průchod command centra, widget dialogu a content/media pickeru bez regrese potvrzen 2026-07-04. | Při změnách JS dialogů zopakovat ruční screen reader + keyboard-only průchod. |
| 2.1.4 Character Key Shortcuts | A | Supports | Globální `Ctrl+K` není jednoklávesová zkratka a je mimo formulářová pole. | Admin command centrum dokumentace a testy. | Při přidání nových zkratek zachovat modifikátor nebo vypnutí. |
| 2.2.1 Timing Adjustable | A | Partially Supports | Content lock heartbeat chrání dlouhé editace; session/auth časování vyžaduje ruční posouzení. | CSRF/content lock testy. | Popsat timeouty a ověřit re-auth flow bez ztráty dat. |
| 2.2.2 Pause, Stop, Hide | A | Supports | Core neobsahuje auto-animovaný obsah, který by vyžadoval pauzu. | Theme CSS bez rušivých animací. | Author embedy mohou mít vlastní pohyb; dokumentovat. |
| 2.3.1 Three Flashes or Below Threshold | A | Supports | CMS negeneruje blikající obsah. | Žádné core animace tohoto typu. | Author content zůstává mimo core garanci. |
| 2.4.1 Bypass Blocks | A | Supports | Veřejné i standalone stránky mají skip link na hlavní obsah. | Runtime audit a README. | Ručně ověřit viditelnost skip linku v každém theme. |
| 2.4.2 Page Titled | A | Supports | Stránky generují titulky přes společnou SEO/theme vrstvu. | Runtime audit a public smoke. | Ověřit chybové a tokenové stránky. |
| 2.4.3 Focus Order | A | Partially Supports | Layouty drží DOM pořadí; command centrum, widget dialog a content/media picker řízeně vracejí focus na spouštěcí prvek. | Theme guardrails, JS dialog guardrails a `admin_command_center_http`; ruční dialogový průchod bez regrese potvrzen 2026-07-04. | Ručně ověřit admin dashboard, husté tabulky a dlouhé formuláře. |
| 2.4.4 Link Purpose (In Context) | A | Supports | Odkazy mají konkrétní text nebo skrytý doplněk pro nové okno/akci. | New-window link guardrails, theme audit. | Ručně projít opakované odkazy v tabulkách a kartách. |
| 2.4.5 Multiple Ways | AA | Supports | Obsah je dostupný přes navigaci, vyhledávání, sitemapu a modulové výpisy. | Public search, sitemap, menu. | Ověřit výjimky pro neveřejné/tokenové stránky. |
| 2.4.6 Headings and Labels | AA | Supports | Sekce, formuláře a landmarky jsou pojmenované skutečnými nadpisy/legendami. | Runtime/theme audits. | Ručně ověřit dynamicky generované a importované obsahové bloky. |
| 2.4.7 Focus Visible | AA | Supports | Veřejné i admin styly mají viditelný focus. | README, CSS a runtime guardrails. | Ručně ověřit kontrast a tloušťku focusu v theme variantách. |
| 2.4.11 Focus Not Obscured (Minimum) | AA | Partially Supports | Skip link a sticky prvky mají offsety, ale všechny kombinace nejsou ručně ověřené. | Blog TOC scroll offset, layout CSS. | Ručně ověřit sticky header/admin bar, dialogy a anchor skoky. |
| 2.5.1 Pointer Gestures | A | Supports | Core nevyžaduje multipoint ani path-based gesture; řazení má tlačítkové alternativy. | Admin řazení nahoru/dolů, žádný povinný drag-only vzor. | Při budoucím drag-and-drop zachovat alternativu. |
| 2.5.2 Pointer Cancellation | A | Supports | Stav měnící akce jsou tlačítka/odkazy s potvrzením nebo POSTem, ne down-event side effect. | CSRF/POST guardrails. | Ručně ověřit JS ovládací prvky v dialogu widgetů a pickeru. |
| 2.5.3 Label in Name | A | Partially Supports | Viditelné názvy nejsou obecně přepisované přes `aria-label`; skrytý text se přidává dovnitř odkazu. | New-window link guardrails. | Ručně ověřit ikony, tlačítka bez viditelného textu a dynamické dialogy. |
| 2.5.4 Motion Actuation | A | Not Applicable | CMS nevyžaduje ovládání pohybem zařízení. | Žádné motion API v core. | Pokud vznikne mobilní/native část, znovu posoudit. |
| 2.5.7 Dragging Movements | AA | Supports | Řazení a přesuny mají tlačítkové/POST alternativy, nejsou drag-only. | Food položky, galerie, stránky, widgety. | Při přidání drag UI zachovat keyboard alternativu. |
| 2.5.8 Target Size (Minimum) | AA | Partially Supports | Veřejná UI míří na dostatečné cíle; admin obsahuje kompaktní tabulky. | CSS a ruční design práce. | Ručně změřit malé admin ikony, řádkové akce a mobilní navigaci. |
| 3.1.1 Language of Page | A | Supports | HTML stránky používají `lang="cs"`. | Layouty a standalone stránky. | Při vícejazyčnosti doplnit dynamický jazyk. |
| 3.1.2 Language of Parts | AA | Partially Supports | CMS umí HTML obsah, ale nevyžaduje označení cizojazyčných částí. | HTML editor. | Dokumentovat pro autory a zvážit helper pro `lang`. |
| 3.2.1 On Focus | A | Supports | Focus sám nespouští nečekanou změnu kontextu. | Form/dialog patterns. | Ručně ověřit custom JS v adminu. |
| 3.2.2 On Input | A | Supports | Změna inputu běžně neprovádí nečekanou navigaci bez akce. | PRG, POST, explicitní tlačítka. | Ručně ověřit filtry a selecty. |
| 3.2.3 Consistent Navigation | AA | Supports | Hlavní navigace, admin layout a widgetové zóny jsou konzistentní. | Shared layouts. | Ověřit theme varianty mimo default. |
| 3.2.4 Consistent Identification | AA | Supports | Akce a moduly používají sdílené labely a manifesty. | Module contract audit a dokumentace. | Pokračovat ve sjednocení CTA slovníku. |
| 3.2.6 Consistent Help | A | Partially Supports | Kontakt, nápovědy a help texty existují, ale help mechanismy nejsou zatím inventarizované jako jednotný systém. | Form helper texty, contact module. | Provést inventuru opakovaných help mechanismů. |
| 3.3.1 Error Identification | A | Supports | Chyby se zobrazují u polí i ve form-level hláškách. | Admin field error guardrails, public form guardrails. | Ručně ověřit nové a složité formuláře. |
| 3.3.2 Labels or Instructions | A | Supports | Formuláře používají labely, legendy a helper texty. | Runtime/theme audits. | Ručně ověřit dynamická pole Form Builderu a widget dialog. |
| 3.3.3 Error Suggestion | AA | Partially Supports | Mnoho formulářů má konkrétní chybové texty, ale systémová inventura všech návrhů oprav chybí. | Field-level errors. | Založit pass pro kvalitu error copy. |
| 3.3.4 Error Prevention (Legal, Financial, Data) | AA | Partially Supports | Destruktivní a stavové akce mají CSRF, potvrzení, PRG a často audit log. | CSRF/runtime guardrails. | Ručně posoudit, které akce jsou právně/datově kritické a vyžadují review/undo. |
| 3.3.7 Redundant Entry | A | Partially Supports | CMS předvyplňuje známá data tam, kde je to běžné, ale nemá plošnou redundant-entry inventuru. | Profil, rezervace, objednávky. | Ověřit opakované zadávání jména/e-mailu v auth, rezervacích a objednávkách. |
| 3.3.8 Accessible Authentication (Minimum) | AA | Supports | Login, registrace, žádost o obnovu hesla, tokenový reset a 2FA nevyžadují matematickou CAPTCHA ani jiný kognitivní test; registrace a reset request používají CSRF, rate limit a honeypot. Admin login a 2FA chyby mají text-backed alerty. | Runtime audit a `auth_accessibility_http` hlídají absenci CAPTCHA v auth flow, honeypot, auth autocomplete metadata, 2FA `one-time-code`/numeric pattern a admin/2FA chybové alerty; ruční průchod se správcem hesel, TOTP, tokenovým resetem a chybovými stavy potvrzen 2026-07-04. | Při změnách auth, 2FA, resetu hesla nebo session timeoutů zopakovat ruční NVDA/keyboard-only průchod. |
| 4.1.2 Name, Role, Value | A | Supports | Ovládací prvky mají labely/role/stavy, dialogy a landmarky jsou pojmenované; command centrum oznamuje otevřený stav přes `aria-expanded`. | Theme view audit, runtime audit, HTTP render command dialogu a ruční potvrzení dialogů bez regrese 2026-07-04. | Při změnách JS dialogů zachovat `aria-expanded`, pojmenování dialogu, focus trap a návrat fokusu. |
| 4.1.3 Status Messages | AA | Supports | Stavové a chybové hlášky používají `role="status"` / `role="alert"` a pojmenování. | Runtime guardrails, README. | Ověřit, že live regiony nečtou rušivě opakované změny. |

## Baseline závěr

Kora CMS má nadprůměrně silnou přístupnostní kostru: skip linky, heading-backed landmarky, formulářové vazby, dialog focus management, runtime a theme view guardrails. Největší rizika nejsou v jedné fatální chybě, ale v oblastech, které vyžadují ruční ověření: média a titulky, kontrast theme variant, klávesnice u dynamických prvků, target size v husté administraci, timeouts a nová WCAG 2.2 kritéria pro redundant entry.

Další práce se vede v `a11y-remediation-backlog.md` a ruční ověřování v `manual-test-protocol.md`.
