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
- `blog_management_validation_http` pro správu blogů a validační chyby,
- runtime blog guardraily v `build/runtime_audit.php` pro osnovu článku, série, taxonomie, redirecty, blogové stránky, výpisy a přístupné nadpisy,
- `public_error_suggestion_guardrails` pro sdílenou chybovou hlášku matematického ověření,
- nově také field-level guardrail a HTTP scénář pro veřejný komentářový formulář.

## WCAG 2.2 A/AA Shrnutí

| Kritérium | Stav pro Blog | Důkaz | Zbývající práce |
|---|---|---|---|
| 1.1.1 Non-text Content | Partially Supports | Média, galerie a blog logo mají metadata a fallbacky. | Ručně ověřit reálné alt texty v publikovaných článcích. |
| 1.3.1 Info and Relationships | Supports | Veřejné sekce mají nadpisy, formuláře fieldset/legend a tabulkové/admin přehledy používají strukturální prvky. | Ručně projít dlouhé custom články s vlastním HTML. |
| 1.3.2 Meaningful Sequence | Supports | Šablony drží logické pořadí: nadpis, metadata, obsah, související obsah, komentáře. | Ručně ověřit mobilní pořadí doporučeného článku a dlouhého detailu. |
| 1.4.10 Reflow | Partially Supports | Admin/mobile guardraily pokrývají dlouhé formuláře a veřejné šablony mají responzivní baseline. | Ověřit Blog při 400% zoomu, zejména TOC, filtry a editor článku. |
| 1.4.12 Text Spacing | Partially Supports | Core CSS neblokuje text spacing patterny. | Projít blogový index/detail s text-spacing override. |
| 2.1.1 Keyboard | Partially Supports | Formuláře, odkazy, kopírování a admin ovládání jsou běžné klávesové prvky. | Ručně projít editor článku, série, související články a content picker bez myši. |
| 2.4.1 Bypass Blocks | Supports | Veřejný i admin layout zachovává skip link. | Ověřit ve všech aktivních theme variantách. |
| 2.4.4 Link Purpose | Supports | Odkazy kategorií, štítků, sérií a článků mají kontextový text; nové okno používá textové doplnění. | Ručně projít opakované odkazy ve výpisech. |
| 2.4.6 Headings and Labels | Supports | Blog index, detail, TOC, série a sidebar/footer widgety používají pojmenované sekce. | Ručně ověřit pořadí nadpisů v dlouhém článku s author contentem. |
| 2.4.11 Focus Not Obscured | Partially Supports | TOC a anchor cíle mají scroll offset. | Ověřit skoky na kotvy při 400% zoomu a se sticky prvky. |
| 2.5.8 Target Size | Partially Supports | Sdílené CSS řeší tlačítka, akční řádky a mobilní baseline. | Ručně změřit malé tag/category/series odkazy v custom theme. |
| 3.1.2 Language of Parts | Partially Supports | HTML editor nabízí helper pro `lang`; checklist vysvětluje odpovědnost autora. | Ručně ověřit cizojazyčné citace v článcích. |
| 3.3.1 Error Identification | Supports | Admin editory i veřejné komentáře mají form-level alert a field-level chyby. | Ručně projít kombinované chybové stavy editoru článku. |
| 3.3.3 Error Suggestion | Partially Supports | Blog admin formuláře a veřejný komentář používají konkrétní návrhy oprav. | Pokračovat copy passem u méně častých validačních větví. |
| 3.3.4 Error Prevention | Partially Supports | Kritické obecné akce mají review/confirm guardraily; blog používá CSRF, PRG a koš/soft delete vzory. | Ručně rozhodnout, zda některé blogové hromadné akce potřebují další review krok. |
| 4.1.2 Name, Role, Value | Supports | Pole, tlačítka, landmarky a dialogové/picker vzory mají pojmenování a stav. | Ručně projít content/media picker v blog editoru po změnách. |
| 4.1.3 Status Messages | Supports | Alerty/statusy používají textové role; copy akce oznamuje výsledek přes live region. | Ručně ověřit, že live regiony nejsou rušivé při dlouhé editaci. |

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

## Ruční Scénáře Pro Blog

Veřejný Blog:

1. Projít index blogu bez myši: doporučený článek, vyhledávání, odkazy kategorií, štítků, sérií a stránkování.
2. Otevřít dlouhý článek s osnovou, projít TOC klávesnicí a ověřit, že skok na nadpis není zakrytý.
3. Odeslat komentář se špatnou captchou, prázdným jménem, neplatným e-mailem a prázdným textem; ověřit souhrnný alert i field-level chyby.
4. Ověřit článek se sérií, související články, blogovou statickou stránku a landing stránky kategorie/štítku.
5. Při 200-400% zoomu ověřit, že metadata, štítky, tlačítka a komentářový formulář zůstávají čitelné bez horizontálního scrollu hlavního obsahu.

Administrace Blogu:

1. Editor článku bez myši: titulek, slug, změna blogu, kategorie, štítky, série, související články, publikace a uložení.
2. Vyvolat chyby: chybějící titulek/text, duplicitní slug, cizí kategorie, cizí série a cizí související článek.
3. Správa kategorií, štítků, sérií, externích odkazů a blogových stránek: ověřit labely, field-level chyby, zachování hodnot a focus po PRG.
4. Dlouhá editace: nechat vypršet session, ověřit heartbeat live hlášku, návrat přes login a recovery koncept.
5. Ověřit content/media picker, HTML helper `Jazyk části textu` a author-content checklist pro reálný článek.

## Backlog

Střední priorita:

- Ručně ověřit Blog při 400% zoomu v kombinaci s TOC kotvami, sticky prvky a dlouhým editorem článku.
- Projít reálný publikovaný blogový obsah podle `docs/accessibility/author-content-checklist.md` a oddělit author-content nálezy od core/theme defektů.
- Při dalším průchodu blogových hromadných akcí rozhodnout, zda některé z nich patří pod rozšířený review krok pro WCAG `3.3.4`.

Nízká priorita:

- Přidat ruční NVDA/Firefox evidenci pro blogový detail s komentáři, sérií, TOC a souvisejícími články.
- Ověřit custom theme varianty blogového indexu a detailu, zejména kontrast malých štítků a focus stavů.
