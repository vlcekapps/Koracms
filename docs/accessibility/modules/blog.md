# Blog Module Accessibility Conformance Pass

Stav: modulová příloha k hlavnímu WCAG/ACR draftu pro Kora CMS.

Tento dokument hodnotí Blog jako součást produktu Kora CMS podle WCAG 2.2 Level AA. Nejde o právní certifikaci. Cílem je prakticky popsat pokryté obrazovky, automatické důkazy, ruční scénáře a místa, kde odpovědnost přechází z CMS na autora obsahu.

## Scope

Veřejná část Blogu:

- index blogu včetně doporučeného článku, vyhledávání, filtrů a stránkování,
- detail článku včetně metadat, souvisejících článků, komentářů a kopírování odkazu,
- dlouhý článek s automatickou osnovou `V tomto článku`,
- landing stránky kategorií, štítků a sérií,
- blogová statická stránka,
- veřejné externí odkazy v navigaci blogu,
- legacy redirecty a read-only chování starých URL.

Administrace Blogu:

- přehled článků a hromadné akce,
- editor článku včetně změny cílového blogu, kategorií, štítků, sérií a ručně souvisejících článků,
- správa blogů, kategorií, štítků, sérií, externích odkazů a blogových statických stránek,
- tým blogu a oprávnění,
- přesun článků mezi blogy,
- dlouhá editace s content lockem, autosave/recovery a návratem po re-auth.

Mimo přímou odpovědnost CMS:

- kvalita ručně vložených nadpisů, odkazů a tabulek v obsahu článku,
- alt texty a titulky médií dodané autorem,
- titulky, přepisy a popisy externích embedů,
- jazyk částí textu v author contentu.

Tyto body CMS podporuje redakčním checklistem a helpery, ale jejich správné použití musí ověřit editor obsahu.

## Automatické důkazy

Aktuální automatizované guardraily a HTTP scénáře pokrývají hlavně:

- `blog_article_series_http` pro série článků, cross-blog validaci a veřejný blok série,
- `blog_related_articles_http` pro ručně související články a field-level chyby editoru článku,
- `blog_taxonomy_landing_http` pro kategorie/štítky, čisté URL, SEO metadata a duplicitní slugy,
- `blog_static_pages_http` pro blogové statické stránky, kontextovou unikátnost slugu a zachování rozepsaného obsahu,
- `blog_management_validation_http` pro správu blogů, validační chyby a review-and-confirm mazání celého blogu,
- runtime blog guardraily v `build/runtime_audit.php` pro osnovu článku, série, taxonomie, redirecty, blogové stránky, výpisy a přístupné nadpisy,
- `public_error_suggestion_guardrails` pro sdílenou chybovou hlášku matematického ověření,
- field-level guardrail a HTTP scénář pro veřejný komentářový formulář včetně předvyplnění jména/e-mailu přihlášeného veřejného uživatele přes `currentUserContactDefaults()`,
- nově také guardrail a HTTP scénář pro srozumitelné odebrání článku ze všech sérií v editoru článku.

## WCAG 2.2 A/AA Shrnutí

| Kritérium | Stav pro Blog | Důkaz | Zbývající práce |
|---|---|---|---|
| 1.1.1 Non-text Content | Partially Supports | Média, galerie a blog logo mají metadata a fallbacky. | Ručně ověřit reálné alt texty v publikovaných článcích. |
| 1.3.1 Info and Relationships | Supports | Veřejné sekce mají nadpisy, formuláře fieldset/legend a tabulkové/admin přehledy používají strukturální prvky. | Ručně projít dlouhé custom články s vlastním HTML. |
| 1.3.2 Meaningful Sequence | Supports | Šablony drží logické pořadí: nadpis, metadata, obsah, související obsah, komentáře. | Ručně ověřit mobilní pořadí doporučeného článku a dlouhého detailu. |
| 1.3.5 Identify Input Purpose | Partially Supports | Komentářový formulář používá `autocomplete="name"` a `autocomplete="email"` a HTTP integrace hlídá render. | Ručně ověřit chování autofillu v prohlížeči. |
| 1.4.10 Reflow | Partially Supports | Admin/mobile guardraily pokrývají dlouhé formuláře a veřejné šablony mají responzivní baseline; ruční NVDA průchod blogů a blogových stránek byl potvrzený 2026-07-09 bez nahlášené regrese. | Vizuálně ověřit Blog při 400% zoomu, zejména TOC, filtry, sticky prvky a editor článku. |
| 1.4.12 Text Spacing | Partially Supports | Core CSS neblokuje text spacing patterny. | Projít blogový index/detail s text-spacing override. |
| 2.1.1 Keyboard | Supports | Formuláře, odkazy, kopírování a admin ovládání jsou běžné klávesové prvky; ruční NVDA/keyboard průchod blogů, dlouhých formulářů a blogových stránek byl potvrzený 2026-07-09 bez nahlášené regrese. | Při změnách blog editoru, sérií, souvisejících článků nebo pickerů zopakovat NVDA/keyboard průchod. |
| 2.4.1 Bypass Blocks | Supports | Veřejný i admin layout zachovává skip link. | Ověřit ve všech aktivních theme variantách. |
| 2.4.4 Link Purpose | Supports | Odkazy kategorií, štítků, sérií a článků mají kontextový text; nové okno používá textové doplnění. | Ručně projít opakované odkazy ve výpisech. |
| 2.4.6 Headings and Labels | Supports | Blog index, detail, TOC, série a sidebar/footer widgety používají pojmenované sekce. | Ručně ověřit pořadí nadpisů v dlouhém článku s author contentem. |
| 2.4.11 Focus Not Obscured | Partially Supports | TOC a anchor cíle mají scroll offset; ruční NVDA průchod blogů a blogových stránek byl potvrzený 2026-07-09 bez nahlášené regrese. | Vizuálně ověřit skoky na kotvy při 400% zoomu a se sticky prvky. |
| 2.5.8 Target Size | Partially Supports | Sdílené CSS řeší tlačítka, akční řádky a mobilní baseline. | Ručně změřit malé tag/category/series odkazy v custom theme. |
| 3.1.2 Language of Parts | Partially Supports | HTML editor nabízí helper pro `lang`; blogové kategorie, štítky a série mají stejný helper u veřejně renderovaných popisů a runtime guardrail hlídá editor coverage. Checklist vysvětluje odpovědnost autora. | Ručně ověřit cizojazyčné citace v článcích i taxonomických popisech. |
| 3.3.1 Error Identification | Supports | Admin editory i veřejné komentáře mají form-level alert a field-level chyby. | Ručně projít kombinované chybové stavy editoru článku. |
| 3.3.3 Error Suggestion | Partially Supports | Blog admin formuláře a veřejný komentář používají konkrétní návrhy oprav. | Pokračovat copy passem u méně častých validačních větví. |
| 3.3.4 Error Prevention | Partially Supports | Kritické obecné akce mají review/confirm guardraily; mazání celého blogu i blogových kategorií, štítků a sérií má item-level review, potvrzovací checkbox, serverové odmítnutí bez potvrzení a HTTP důkaz. | Ručně rozhodnout, zda některé blogové hromadné akce potřebují další review krok. |
| 3.3.7 Redundant Entry | Partially Supports | Přihlášený veřejný uživatel dostane v komentářovém formuláři předvyplněné jméno a e-mail z profilu a POST chyba zachová ručně zadané hodnoty. | Ručně ověřit sdílená zařízení a širší custom komentářové workflow. |
| 4.1.2 Name, Role, Value | Supports | Pole, tlačítka, landmarky a dialogové/picker vzory mají pojmenování a stav; ruční NVDA průchod blogů a blogové administrace byl potvrzený 2026-07-09 bez nahlášené regrese. | Při změnách content/media pickeru nebo blog editoru zopakovat NVDA/keyboard průchod. |
| 4.1.3 Status Messages | Supports | Alerty/statusy používají textové role; copy akce oznamuje výsledek přes live region a ruční NVDA průchod dlouhé editace byl potvrzený 2026-07-09 bez nahlášené regrese. | Při změnách live regionů nebo autosave/content-lock chování zopakovat NVDA průchod. |

## Nález Opravený V Tomto Passu

### Veřejný komentářový formulář neměl field-level chyby u jednotlivých polí

Priorita: střední.

Riziko: při chybné captche, prázdném jménu nebo prázdném komentáři čtečka oznámila souhrnný alert, ale konkrétní pole nebyla označená přes `aria-invalid` a `aria-describedby`.

Oprava:

- controller článku mapuje chyby na konkrétní pole,
- captcha používá sdílený text `publicCaptchaErrorMessage()`,
- šablona komentářů vykresluje field-level chyby pro jméno, e-mail, text komentáře a captchu,
- runtime audit a HTTP integrace hlídají návrat patternu.

Snížené riziko: WCAG `3.3.1 Error Identification`, `3.3.3 Error Suggestion` a `4.1.3 Status Messages`.

### Výběr sérií v editoru článku nebyl dostatečně zřejmý při odebrání všech sérií

Priorita: střední.

Riziko: editor článku technicky používal zaškrtávací políčka, ale správce neměl jednoznačný stavový text ani rychlou akci pro návrat na „bez série“. Při přepínání blogu mohlo působit matoucím dojmem, proč je v seznamu série a zároveň se pracuje s prázdným stavem.

Oprava:

- sekce sérií oznamuje textový stav `Článek není zařazený do žádné série` nebo počet vybraných sérií,
- tlačítko `Odebrat ze všech sérií` odškrtne všechny série a vrátí focus do seznamu,
- checkboxy sérií mají stavový text v `aria-describedby`,
- runtime audit a HTTP integrace hlídají návrat patternu.

Snížené riziko: WCAG `2.1.1 Keyboard`, `3.3.2 Labels or Instructions`, `4.1.2 Name, Role, Value` a `4.1.3 Status Messages`.

### Mazání celého blogu nemělo server-side review potvrzení

Priorita: střední.

Riziko: smazání blogu má dopad na veřejné URL, články, taxonomie, série a týmová přiřazení. Při více blozích se obsah přesouvá do fallback blogu, při posledním blogu jde o nevratné odstranění obsahu.

Oprava:

- přehled blogů zobrazuje počet dotčených článků, kategorií, štítků, sérií a týmových přiřazení,
- formulář vyžaduje `confirm_blog_delete_<id>` a chybějící potvrzení vrací atomický alert s field-level chybou,
- handler odmítá nepotvrzený POST před přesunem obsahu, smazáním sérií, zrušením týmu, smazáním blogu nebo audit logem,
- potvrzený databázový průchod běží v transakci a explicitně uklízí `cms_blog_members`,
- runtime audit a HTTP integrace hlídají render, odmítnutí i potvrzený cleanup.

Snížené riziko: WCAG `3.3.4 Error Prevention` a `4.1.3 Status Messages`.

## Ruční Evidence

2026-07-09: uživatel ručně zrevidoval blogy s NVDA bez nahlášené regrese. Evidence pokrývá veřejný i administrační blogový průchod včetně focus order, dlouhých formulářů, statických stránek a blogových stránek. Pro aktuální ACR draft tím už Blog nezůstává otevřený jen kvůli základnímu screen-reader/keyboard průchodu.

Zbývající blogové ruční oblasti jsou vizuální: 400% zoom, text-spacing override, sticky/anchor skoky, custom theme variace, kontrast malých štítků/focus stavů a kvalita reálného author contentu podle `author-content-checklist.md`.

## Ruční Scénáře Pro Blog

Veřejný Blog:

1. Projít index blogu bez myši: doporučený článek, vyhledávání, odkazy kategorií, štítků, sérií a stránkování.
2. Otevřít dlouhý článek s osnovou, projít TOC klávesnicí a ověřit, že skok na nadpis není zakrytý.
3. Odeslat komentář se špatnou captchou, prázdným jménem, neplatným e-mailem a prázdným textem; ověřit souhrnný alert i field-level chyby.
4. Ověřit článek se sérií, související články, blogovou statickou stránku a landing stránky kategorie/štítku.
5. Při 200-400% zoomu ověřit, že metadata, štítky, tlačítka a komentářový formulář zůstávají čitelné bez horizontálního scrollu hlavního obsahu.

Administrace Blogu:

1. Editor článku bez myši: titulek, slug, změna blogu, kategorie, štítky, série, tlačítko pro odebrání ze všech sérií, související články, publikace a uložení.
2. Vyvolat chyby: chybějící titulek/text, duplicitní slug, cizí kategorie, cizí série a cizí související článek.
3. Správa kategorií, štítků, sérií, externích odkazů a blogových stránek: ověřit labely, field-level chyby, zachování hodnot a focus po PRG.
4. Ve správě blogů zkusit smazání celého blogu bez `confirm_blog_delete_<id>`: čtečka má oznámit review dopadu, alert i field-level chybu, obsah/tým/audit log se nesmí změnit; potvrzený průchod má vrátit PRG stav.
5. Dlouhá editace: nechat vypršet session, ověřit heartbeat live hlášku, návrat přes login a recovery koncept.
6. Ověřit content/media picker, HTML helper `Jazyk části textu` a author-content checklist pro reálný článek.

## Backlog

Střední priorita:

- Ručně ověřit Blog při 400% zoomu v kombinaci s TOC kotvami, sticky prvky a dlouhým editorem článku.
- Projít reálný publikovaný blogový obsah podle `docs/accessibility/author-content-checklist.md` a oddělit author-content nálezy od core/theme defektů.
- Při dalším průchodu blogových hromadných akcí rozhodnout, zda některé z nich patří pod rozšířený review krok pro WCAG `3.3.4`.

Nízká priorita:

- Opakovat ruční NVDA/Firefox evidenci při změnách blogového detailu, komentářů, série, TOC, souvisejících článků nebo blogové administrace.
- Ověřit custom theme varianty blogového indexu a detailu, zejména kontrast malých štítků a focus stavů.
