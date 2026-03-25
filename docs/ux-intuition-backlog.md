# UX Backlog Z Auditu Intuitivnosti

Stav k 25. březnu 2026.

Tento backlog převádí nálezy z `docs/ux-intuition-audit.md` do akčních kroků.

## Opravit hned

### 1. Zjednodušit homepage

- odstranit nebo výrazně omezit obecné kickery bez nové informace
- přepsat hero, aby nepracoval s nadpisem `Úvodní stránka`
- projít blok doporučeného článku a nechat jen jednu silnou vrstvu označení
- prověřit, zda CTA blok není výchozně zbytečný pro většinu profilů webu

### 2. Zcivilnit vyhledávání a detailové obrazovky

- odstranit `Navigace obsahem` z vyhledávání
- změnit `Hledání pro…` na `Výsledky pro…`
- odstranit technické kickery `Publikace`, `Obsah`, `Struktura`, `Obsah alba`, pokud nepřidávají kontext
- prověřit board a events detail, zda karta `Přehled` není jen duplicitní metadata

### 3. Uklidit dashboard

- zmenšit počet quick links
- přesunout pozornost z počtů na úkoly
- nechat nahoře jen to, co opravdu vyžaduje akci
- tabulku dostupných sekcí posunout níž nebo ji převést na méně dominantní blok

### 4. Přepsat nejméně srozumitelné admin texty

- `Počet článků blogu na HP` → `Počet článků na domovské stránce`
- `Veřejný název modulu…` → `Název sekce pro návštěvníky`
- `Vrátit` v inboxech → `Označit jako nové`
- prázdné stavy v Kontaktu a Chatu přepsat na civilnější formu

## Přesunout do většího polish passu

### 1. Přestavět informační architekturu administrace

- levé menu členit podle úkolů, ne podle interních modulů
- odstranit sekci `Ostatní moduly`
- vytvořit logické skupiny jako `Obsah`, `Komunikace`, `Rezervace`, `Vzhled a nastavení`

### 2. Rozdělit `Základní nastavení`

- oddělit identitu webu, domovskou stránku, komentáře, vzhled, soukromí a provoz
- zkrátit jednu dlouhou stránku do menších rozhodovacích celků

### 3. Zpřesnit veřejný jazyk napříč moduly

- sjednotit CTA slovník
- zkontrolovat, že listingy a detaily nepoužívají systémová slova
- projít moduly jako podcast, galerie, board, places, downloads a sladit první dojem

### 4. Udělat role-based dashboard

- editor vidí hlavně obsah a schvalování
- moderátor vidí zprávy, komentáře a fronty
- správce rezervací vidí rezervace a zdroje
- superadmin vidí systémové části a metriky

## Převést do automatických guardrails

### 1. Zakázané veřejné texty a vzory

- hlídat návrat textů typu `Úvodní stránka`, `Navigace obsahem`, `Obsah alba`, pokud nejsou výslovně schválené
- hlídat duplicitní kicker + stejný nadpis v jedné sekci

### 2. Guardrails pro CTA

- hlídat návrat zbytečných CTA typu `Přejít na blog`, pokud sekce už sama na blog odkazuje hlavním nadpisem nebo kartami
- hlídat konzistenci CTA v hlavních listingových komponentech

### 3. Guardrails pro admin empty states

- v klíčových admin modulech hlídat, že empty state není technický nebo strojový
- kontrolovat, že akce používají srozumitelná slovesa a ne příliš interní zkratky

### 4. Guardrails pro dashboard

- zabránit dalšímu bobtnání quick links
- hlídat, že dashboard nezačne dalšími obecnými texty místo akčních bloků

## Navržený další UX milestone

### Milestone 1

- homepage
- vyhledávání
- autor
- podcast detail
- galerie album

### Milestone 2

- dashboard
- levé menu
- fronta ke schválení
- kontakt
- chat

### Milestone 3

- základní nastavení
- newsletter
- sjednocení CTA a empty states napříč moduly

## Pravidla pro další vývoj

- Každá nová sekce musí obstát bez kickeru. Pokud bez něj nedává smysl, problém je spíš v nadpisu.
- Veřejná obrazovka má mluvit jazykem návštěvníka, ne architekturou CMS.
- Admin obrazovka má vést k akci, ne k obdivování počtů a modulů.
- Když je možné něco pojmenovat konkrétněji, nepoužívat obecné slovo jako `Obsah`, `Přehled` nebo `Modul`.
