# Šablona návrhu nového modulu

Tuto šablonu vyplňte před implementací nového modulu nebo větší modulové funkce. Cílem není byrokracie, ale včas zachytit schéma, přístupnost, bezpečnost a provozní dopady dřív, než vznikne hotový kód.

## 1. Shrnutí a hranice

- Název modulu:
- Module key:
- Pro koho je modul určen:
- Jaký problém řeší:
- Co do první verze výslovně nepatří:
- Nejbližší existující vzor v Kora CMS:

## 2. Veřejné a administrační workflow

- Veřejné URL a routy:
- Admin obrazovky a stav měnící endpointy:
- Role a capability:
- Chování při vypnutém modulu:
- Widgety, navigace, command centrum:
- Sitemap, veřejné vyhledávání, content picker:
- Statistiky a `trackPageView()` typy:

## 3. Datový model a migrace

- Nové tabulky:
- Nové sloupce:
- Indexy, unikátnost a cizí klíče:
- Dopad na `install.php`:
- Dopad na `migrate.php`:
- Schema parity guardrail:
- Export/import konfigurace:
- Export/import provozních nebo osobních dat:
- Cleanup při mazání, vypnutí nebo koši:

Pokud návrh potřebuje nové perzistentní chování, preferujte správný datový model před obcházením v existujících polích.

## 4. Bezpečnost a soukromí

- CSRF, CAPTCHA, honeypot, rate-limit:
- Bezpečné HTTP metody a tokenové URL:
- Uploady, externí URL, souborové odpovědi:
- Redirecty validované přes `internalRedirectTarget()`:
- Citlivá data, logování a retence:
- No-store/noindex/nosniff hlavičky:
- Error-prevention potvrzení u rizikových akcí:

## 5. Accessibility conformance dopad

- Dotčená WCAG 2.2 A/AA kritéria:
- Nové formuláře, dialogy, tabulky, widgety nebo live regiony:
- Potřebné `fieldset`/`legend`, `label`, `aria-describedby`, `aria-labelledby`:
- Keyboard-only scénář:
- NVDA/Firefox scénář:
- Zoom 200-400 % a mobilní reflow:
- Odpovědnost CMS:
- Odpovědnost autora obsahu:
- Dopad na `docs/accessibility/wcag-22-aa-conformance.md`:
- Dopad na `docs/accessibility/a11y-remediation-backlog.md`:
- Dopad na `docs/accessibility/manual-test-protocol.md`:

## 6. Testy a guardrails

- Unit testy:
- HTTP integrace:
- Runtime audit:
- Module contract audit:
- Theme view audit:
- Schema parity audit:
- PHPStan / formatter coverage:
- Ruční smoke scénáře:

Minimální dokončení větší modulové změny je zelený `composer ci:module-ready`, pokud není výslovně zdokumentovaná blokující externí závislost.

## 7. Dokumentace a release

- `CHANGELOG.md`:
- `README.md`:
- `docs/admin-guide.md`:
- `docs/developer-modules.md`, pokud se mění modulový postup:
- ACR nebo modulová accessibility příloha:
- Poznámky pro migraci existující instalace:
- Poznámky pro release ZIP:

## 8. Rozhodnutí před implementací

- Otevřené otázky:
- Rizika:
- Zvolená varianta:
- Odložené varianty:
- Kritéria přijetí:
