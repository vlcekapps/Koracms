# Theme System Roadmap

Tento dokument popisuje realistický postup, jak z aktuálního Kora CMS udělat systém, který:

- zůstane stabilní, bezpečný a přístupný,
- bude lépe vypadat na desktopu i mobilu,
- umožní postupně zavést vlastní šablony,
- nebude po první implementaci připomínat neřízený plugin/theme ekosystém.

## Výchozí stav

Aktuální frontend má několik dobrých základů:

- sdílenou navigaci a patičku,
- sdílené SEO helpery,
- základní a11y guardrails,
- některé moduly už používají responzivní grid/flex vzory.

Současně ale platí:

- veřejná část zatím nemá jednotný layout shell jako admin,
- HTML a CSS jsou z velké části roztroušené po jednotlivých stránkách,
- vzhled není oddělený od renderování obsahu dostatečně na to, aby šly bezpečně přidávat šablony,
- běžný uživatel zatím nemá komfortní cestu k vizuálním úpravám bez zásahu do kódu.

Závěr: systém je vhodný pro přechod na themeable architekturu, ale zatím není připravený na uživatelské šablony ve stylu WordPressu.

## Produktový cíl

Nejdřív potřebujeme vytvořit "themeable core", teprve potom otevřít "custom themes".

To znamená:

1. sjednotit veřejný vzhled do jedné oficiální šablony,
2. oddělit obsah, layout a vizuální vrstvu,
3. zavést bezpečný kontrakt pro šablony,
4. až poté umožnit další šablony a pokročilé přepisování vzhledu.

## Tři úrovně přizpůsobení

### 1. Běžný uživatel

Běžný uživatel by neměl vytvářet šablonu psaním kódu. Pro něj musí CMS nabídnout jednoduché vizuální přizpůsobení:

- výběr šablony,
- logo a favicon,
- barvy značky,
- typografické předvolby,
- volitelné sekce homepage,
- pořadí a viditelnost vybraných bloků,
- obrázky, úvodní texty a CTA prvky.

To je nejdůležitější vrstva z pohledu použitelnosti produktu.

### 2. Administrátor webu

Administrátor by měl umět:

- aktivovat nainstalovanou šablonu,
- přepnout zpět na default,
- zobrazit metadata šablony,
- bezpečně importovat připravený theme balíček.

Administrátor nemá být nucen upravovat PHP soubory.

### 3. Vývojář šablony

Teprve tato role pracuje s technickým kontraktem šablony:

- layouty,
- partialy,
- assety,
- manifest,
- mapování view souborů na stránky/moduly.

Pro tuto úroveň je důležitá dokumentace, stabilní API a fallback chování.

## Doporučená architektura

### A. Theme kernel

Přidat novou vrstvu, ideálně do `lib/theme.php`, která bude řešit:

- aktivní šablonu,
- načtení manifestu,
- ověření existence šablony,
- fallback na `default`,
- generování URL k assetům,
- vyhledání view/partial souboru s fallbackem na default theme.

Navržené helpery:

- `activeThemeName(): string`
- `availableThemes(): array`
- `themeManifest(?string $theme = null): array`
- `themeAssetUrl(string $path, ?string $theme = null): string`
- `themeViewPath(string $view, ?string $theme = null): string`
- `renderThemeView(string $view, array $data = [], ?string $theme = null): string`

### B. Adresářová struktura

Navržený tvar:

```text
themes/
  default/
    theme.json
    assets/
      public.css
      public.js
    layouts/
      base.php
    partials/
      head.php
      header.php
      footer.php
      flash.php
    views/
      home.php
      page.php
      listing.php
      detail.php
      form.php
      modules/
        blog-index.php
        blog-article.php
        news-index.php
        board-index.php
        gallery-index.php
        gallery-album.php
        reservations-index.php
        reservations-resource.php
```

### C. Layout contract

Veřejná část by měla mít jednotný shell:

- `head`
- skip link
- header
- hlavní navigaci
- content wrapper
- patičku
- společné skripty a live region

Obsah jednotlivých stránek se bude renderovat do layoutu jako view.

### D. Design tokens a komponenty

První oficiální šablona musí zavést jednotné vizuální tokeny:

- barvy,
- typografii,
- spacing,
- border radius,
- shadow/elevation,
- container šířky,
- breakpoints,
- focus ring,
- stavové barvy.

Z nich se pak sestaví sdílené komponenty:

- tlačítka,
- formulářová pole,
- alerty a flash zprávy,
- karty,
- seznamy,
- pager,
- taby,
- breadcrumb,
- galerie,
- tabulky,
- CTA bloky.

## Co neudělat v první verzi

Nedoporučuji:

- povolit raw PHP theme upload od běžného uživatele,
- míchat business logiku do theme souborů,
- navrhnout systém bez fallbacku na default theme,
- dělat child themes dřív, než bude stabilní základní kontrakt,
- slibovat kompatibilitu na úrovni WordPress theme API.

To by zbytečně zvýšilo riziko rozbití webu i bezpečnostních chyb.

## Bezpečnostní pravidla pro šablony

Pokud zavedeme import šablon, musí být minimálně:

- povolen jen superadminovi,
- kontrolován manifest a očekávaná struktura,
- blokované nepovolené soubory mimo definovaný kontrakt,
- zachován fallback na default theme,
- zachována funkčnost bez externích CDN závislostí, pokud to není výslovně schválené,
- validována cesta k assetům a view souborům.

Pro první verzi je bezpečnější dovolit jen:

- ručně nainstalované šablony v adresáři `themes/`,
- případně ZIP import od admina s validací obsahu.

## Přístupnost a vzhled nejsou v konfliktu

Nový theme system musí zachovat stávající guardrails:

- viditelný focus,
- skip link,
- dostatečný kontrast,
- konzistentní formuláře,
- sémantické landmarky,
- čitelnost při zoomu 200 %,
- funkčnost bez hover-only interakcí,
- nepřenášet význam pouze barvou.

Hezčí vzhled nesmí znamenat ústup od WCAG 2.2 AA.

## Responzivní strategie

Responzivitu je vhodné přestat řešit stránku po stránce a zavést ji systémově:

- jeden základní container,
- standardní rozestupy,
- jednotné chování karet a gridů,
- mobil-first přístup,
- definované breakpointy,
- sdílené utility pro obrázky, media a tabulky,
- test minimálně pro šířky 360 px, 768 px a 1280 px.

Vizuální kvalita pro běžné uživatele stojí hlavně na konzistenci:

- rytmus mezer,
- čitelná typografie,
- kvalitní hierarchie nadpisů,
- rozumné délky řádků,
- předvídatelné komponenty,
- dobrá práce s obrázky a CTA prvky.

## Migrační plán

### Fáze 1: Theme foundation

Cíl:

- přidat theme kernel,
- vytvořit `themes/default/`,
- zavést centrální public CSS,
- sjednotit veřejný layout shell.

Do této fáze patří:

- homepage,
- běžná stránka,
- login,
- registrace,
- reset hesla,
- základní list/detail vzory.

### Fáze 2: Převod veřejných modulů

Postupně převést:

- blog,
- news,
- board,
- downloads,
- events,
- FAQ,
- food,
- gallery,
- podcast,
- polls,
- places,
- reservations.

Cíl není jen "přepsat styl", ale navázat každý modul na sdílené layoutové a komponentové vzory.

### Fáze 3: Volba aktivní šablony

Přidat do administrace:

- seznam dostupných šablon,
- zobrazení názvu, verze, autora a popisu,
- aktivaci šablony,
- bezpečný návrat na default.

### Fáze 4: Import šablon

Až po stabilizaci kontraktu:

- ZIP import,
- validace manifestu,
- validace adresářové struktury,
- blokace nepovolených souborů,
- audit chyb a fallback.

## Doporučený první implementační krok

Jako první skutečný kódový krok doporučuji:

1. přidat `lib/theme.php`,
2. vytvořit `themes/default/theme.json`,
3. zavést `themes/default/assets/public.css`,
4. připravit společný veřejný layout,
5. převést na něj `index.php` a `page.php`,
6. zachovat stávající a11y chování a runtime audit.

To je dostatečně malý a bezpečný krok, ale zároveň už vytvoří pevný základ pro další rozvoj.

## Definition of done pro první milestone

První milestone má být hotová, když:

- homepage a běžná stránka používají theme vrstvu,
- veřejný vzhled je centralizovaný,
- mobilní zobrazení je konzistentní,
- runtime audit projde,
- PHP lint projde,
- aktivní vzhled lze dál rozšiřovat bez kopírování stylů mezi stránkami.

## Shrnutí

Správný směr není "napodobit WordPress za každou cenu", ale:

- postavit stabilní themeable core,
- dodat jednu opravdu dobrou default šablonu,
- zpřístupnit běžné úpravy neprogramátorům,
- až pak otevřít řízený systém dalších šablon.

Tento přístup je bezpečnější, levnější na údržbu a výrazně vhodnější pro reálné uživatele než okamžitý otevřený theme engine bez kontraktu.
