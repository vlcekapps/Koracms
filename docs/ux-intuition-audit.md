# UX Audit Intuitivnosti a Logiky Obrazovek

Stav k 25. březnu 2026.

Tento audit hodnotí Kora CMS z pohledu:

- běžného návštěvníka veřejného webu
- ne-technického administrátora

Nejde o WCAG audit. Zaměřuje se na jazyk, hierarchii, první dojem, smysluplnost CTA, pořadí informací a celkovou intuitivnost obrazovek.

## Metoda

Audit byl proveden heuristicky nad klíčovými veřejnými i admin obrazovkami:

- veřejný web: homepage, blog, autor, vyhledávání, board, události, galerie, podcast
- administrace: dashboard, levé menu, fronta ke schválení, kontakt, chat, newsletter, nastavení

U každého nálezu je uvedeno:

- obrazovka
- problém
- proč je to matoucí
- návrh vylepšení
- priorita

## Veřejný web

### 1. Homepage: příliš mnoho obecných kickerů

**Obrazovka:** Domovská stránka  
**Problém:** Sekce používají mnoho obecných kickerů jako `Vítejte`, `Přehled`, `Doporučujeme`, `Aktuálně`, `Užitečné odkazy`, `Zapojte se`. Část z nich nepřidává žádnou novou informaci.  
**Proč je to matoucí:** Návštěvník místo jasných bloků čte další vrstvu textu, která často jen opakuje nebo rozmělňuje význam nadpisu. Stránka pak působí „ukecaně“ a méně jistě.  
**Návrh vylepšení:** Nechat kicker jen tam, kde přidává skutečný kontext, například typ obsahu. U ostatních sekcí začínat rovnou nadpisem.  
**Priorita:** Vysoká

### 2. Homepage: úvodní sekce mluví jazykem systému, ne webu

**Obrazovka:** Domovská stránka, hero  
**Problém:** Nadpis `Úvodní stránka` a kicker `Vítejte` jsou příliš obecné.  
**Proč je to matoucí:** Návštěvník se z prvního pohledu nedozví, o jaký web jde a co na něm najde. Hero by měl pomáhat pochopit účel webu, ne popisovat, že jde o homepage.  
**Návrh vylepšení:** Použít buď skutečný obsahový nadpis z nastavení webu, nebo přímo název webu s vysvětlujícím podnadpisem. `Úvodní stránka` úplně odstranit.  
**Priorita:** Vysoká

### 3. Homepage: doporučený článek je stále popsaný duplicitně

**Obrazovka:** Domovská stránka, blok doporučeného článku  
**Problém:** Kicker i nadpis používají stejný význam nebo velmi podobné označení doporučeného obsahu.  
**Proč je to matoucí:** Sekce působí jako dvakrát nadepsaná. To snižuje čitelnost a ubírá důvěru v hierarchii stránky.  
**Návrh vylepšení:** Nechat jen jeden silný nadpis. Pokud je potřeba malé označení nad ním, musí nést jinou informaci než samotný nadpis.  
**Priorita:** Vysoká

### 4. Homepage: CTA blok působí spíš jako pomocný rozcestník než jako potřeba návštěvníka

**Obrazovka:** Domovská stránka, blok `Rychlý přístup`  
**Problém:** Sekce `Užitečné odkazy` / `Rychlý přístup` nepůsobí jako přirozená součást obsahu.  
**Proč je to matoucí:** Odkazy typu vyhledávání, kontakt nebo přihlášení už bývají dostupné jinde. Blok pak spíš dubluje navigaci, než aby vedl uživatele k další důležité akci.  
**Návrh vylepšení:** Výchozně CTA blok skrýt a používat ho jen pro weby, které pro něj mají skutečný důvod. Pokud zůstane, pojmenovat ho konkrétněji podle obsahu, ne genericky.  
**Priorita:** Střední

### 5. Vyhledávání: titulek a kicker jsou zbytečně abstraktní

**Obrazovka:** Vyhledávání  
**Problém:** Kicker `Navigace obsahem` je abstraktní a nepomáhá pochopit účel stránky.  
**Proč je to matoucí:** Uživatel nepřišel navigovat obsah, ale něco najít. Takové pojmenování působí jako interní jazyk designu, ne jako jasná funkce stránky.  
**Návrh vylepšení:** Začínat rovnou `Vyhledávání` a případně použít podnadpis typu `Najděte články, stránky a další obsah na webu`. Nadpis výsledků změnit z `Hledání pro…` na `Výsledky pro…`.  
**Priorita:** Střední

### 6. Stránka autora: sekce s články je zbytečně přeznačená

**Obrazovka:** Veřejný profil autora  
**Problém:** Kicker `Publikace` nad nadpisem `Články autora` nepřidává novou informaci.  
**Proč je to matoucí:** Z pohledu návštěvníka stačí samotný nadpis. Další vrstva označení působí jako redakční terminologie.  
**Návrh vylepšení:** Nechat jen nadpis `Články autora`, případně podle typu webu `Články` nebo `Poslední články`.  
**Priorita:** Střední

### 7. Podcast epizoda: sekce `Obsah` je nad detailním popisem zbytečná

**Obrazovka:** Detail epizody podcastu  
**Problém:** Kicker `Obsah` nad nadpisem `Detail epizody` nepřináší žádné upřesnění.  
**Proč je to matoucí:** Návštěvník už ví, že je na detailu epizody. Slovo `Obsah` je obecné a nevysvětluje, zda jde o popis, poznámky, přepis nebo něco jiného.  
**Návrh vylepšení:** Pokud blok obsahuje popis epizody, nazvat ho přímo `Popis epizody` nebo nechat jen samotný obsah bez dalšího mezititulku.  
**Priorita:** Střední

### 8. Board a události: veřejné detaily opakují metadata ve zvláštním bloku `Přehled`

**Obrazovka:** Detail dokumentu, detail události  
**Problém:** Metadata jsou jednou pod hlavním nadpisem a podruhé v kartě `Přehled`.  
**Proč je to matoucí:** Vzniká pocit opakování bez přidané hodnoty. Návštěvník znovu čte stejné datum, místo nebo typ položky v jiném formátu.  
**Návrh vylepšení:** Buď metadata ponechat v hlavičce a kartu `Přehled` odstranit, nebo kartu ponechat jen pro položky, které mají opravdu více důležitých údajů než se vejde do horního meta řádku.  
**Priorita:** Střední

### 9. Galerie: sekce `Struktura` a `Obsah alba` znějí spíš technicky než lidsky

**Obrazovka:** Detail alba galerie  
**Problém:** Kicker `Struktura` nad `Podsložky` a `Obsah alba` nad `Fotografie` působí spíš jako jazyk souborového systému.  
**Proč je to matoucí:** Pro návštěvníka je důležitější, co uvidí, ne jak je to interně uspořádané.  
**Návrh vylepšení:** U podsložek nechat jen `Podsložky` nebo `Další alba`. U fotografií jen `Fotografie`.  
**Priorita:** Nízká

### 10. CTA napříč moduly nejsou jazykově sjednocená

**Obrazovka:** Blog, novinky, autor, homepage a další listingy  
**Problém:** Používají se různé varianty `Číst dále`, `Číst článek`, `Otevřít`, `Všechny…` bez jasného pravidla.  
**Proč je to matoucí:** Web pak nepůsobí jako jeden produkt, ale jako soubor různých modulů.  
**Návrh vylepšení:** Zavést jednoduché pravidlo:

- listing článků: `Číst článek`
- listing novinek: `Zobrazit novinku`
- detailové rozcestníky: `Otevřít`
- přehledy: `Všechny články`, `Všechny novinky`, `Všechny epizody`

**Priorita:** Střední

## Administrace

### 11. Dashboard je příliš hustý a míchá orientaci s provozními metrikami

**Obrazovka:** `Přehled` po přihlášení  
**Problém:** Dashboard kombinuje rychlé odkazy, pending box, obsahové souhrny, tabulku dostupných sekcí, stránky, události, rezervace, moduly a někdy i návštěvnost.  
**Proč je to matoucí:** Ne-technický správce neví, kam se podívat jako první. Stránka mu ukazuje mnoho různých „pravd“ o systému najednou, ale méně pomáhá s prvním krokem.  
**Návrh vylepšení:** Rozdělit dashboard na tři jasné zóny:

- `Co vyžaduje pozornost`
- `Co chci teď udělat`
- `Přehled webu`

Počty a tabulky až níž, ne hned jako hlavní jazyk obrazovky.  
**Priorita:** Vysoká

### 12. Quick links na dashboardu jsou užitečné, ale přerůstají v druhé hlavní menu

**Obrazovka:** `Přehled`  
**Problém:** Rychlé odkazy mohou být velmi početné a opakují to, co je už v levé navigaci.  
**Proč je to matoucí:** Uživatel musí rozlišovat, zda má používat levé menu, nebo tlačítka na dashboardu.  
**Návrh vylepšení:** Omezit quick links jen na 3 až 6 skutečně nejdůležitějších akcí podle role. Zbytek nechat v levé navigaci.  
**Priorita:** Vysoká

### 13. Levé menu je modulově přesné, ale ne úplně úkolově srozumitelné

**Obrazovka:** Levá administrativní navigace  
**Problém:** Navigace je členěná podle interní struktury systému: top položky, `Moduly`, `Ostatní moduly`, `Nastavení`, samostatné vnořené sekce.  
**Proč je to matoucí:** Nový správce si musí nejprve osvojit mental model CMS. Hledá spíš „co chci udělat“ než „ve kterém modulu to je“.  
**Návrh vylepšení:** Přiblížit menu úkolům:

- `Obsah`
- `Komunikace`
- `Rezervace`
- `Vzhled a nastavení`

Vnoření používat střídměji a sekci `Ostatní moduly` úplně odstranit.  
**Priorita:** Vysoká

### 14. Fronta ke schválení je funkčně silná, ale textově pořád trochu technická

**Obrazovka:** `Ke schválení`  
**Problém:** Pojmy jako `Obsah`, `Modul`, `Otevřít`, `Souhrn čekajících položek` jsou srozumitelné spíš správcům systému než redaktorům.  
**Proč je to matoucí:** Fronta by měla vést k akci, ne vystavovat systémovou taxonomii.  
**Návrh vylepšení:** Zcivilnit texty:

- `Obsah čekající na schválení` ponechat
- akci `Modul` přepsat na `Přejít do správy`
- karty v souhrnu pojmenovat jako `Blog`, `Novinky`, `Komentáře`, `Rezervace` bez další systémové vrstvy

**Priorita:** Střední

### 15. Nastavení webu je přerostlé do jedné dlouhé obrazovky

**Obrazovka:** `Základní nastavení`  
**Problém:** Jediný formulář sdružuje identitu webu, profil webu, homepage, komentáře, editor, sociální sítě, logo, GDPR a režim údržby.  
**Proč je to matoucí:** Ne-technický správce má pocit, že „tady se nastavuje všechno“, ale neví, co spolu souvisí. Stránka působí těžce a zvyšuje riziko chyb.  
**Návrh vylepšení:** Rozdělit nastavení do menších samostatných obrazovek nebo alespoň na jasně pojmenované sekce s lokálními akcemi:

- Základ webu
- Domovská stránka
- Diskuse a komentáře
- Vzhled a identita
- Soukromí a provoz

**Priorita:** Vysoká

### 16. Některé názvy polí v nastavení jsou příliš interní nebo zkratkové

**Obrazovka:** `Základní nastavení`  
**Problém:** Formulace typu `Počet článků blogu na HP` nebo `Veřejný název modulu Úřední deska / Vývěska` míchají administrativní jazyk, zkratky a systémové termíny.  
**Proč je to matoucí:** Správce nepřemýšlí v pojmech `HP` nebo `modul`. Přemýšlí v tom, co uvidí návštěvník.  
**Návrh vylepšení:** Přepsat texty do jazyka výsledku, například:

- `Počet článků na domovské stránce`
- `Název sekce pro návštěvníky`

**Priorita:** Střední

### 17. Inboxy Kontakt a Chat jsou silné funkčně, ale prázdné stavy a akce jsou pořád mechanické

**Obrazovka:** `Kontakt`, `Chat`  
**Problém:** Empty states typu `V této části teď nejsou žádné…` a akce `Vrátit` nebo `Vyřízeno` působí spíš provozně než lidsky.  
**Proč je to matoucí:** Moderátor potřebuje jasně vědět, co se stane po kliknutí a co stav znamená v praxi.  
**Návrh vylepšení:** Zcivilnit jazyk:

- `Zatím tu nejsou žádné nové zprávy`
- `Označit jako nové`
- `Označit jako vyřízené`

**Priorita:** Střední

### 18. Newsletter na jedné obrazovce míchá dvě různé činnosti

**Obrazovka:** `Newsletter`  
**Problém:** Přehled kombinuje historii rozesílek a databázi odběratelů na stejné obrazovce.  
**Proč je to matoucí:** Správce může chtít dělat jen jednu věc, ale stránka ho nutí skenovat dva různé typy dat.  
**Návrh vylepšení:** Zachovat jednu vstupní stránku, ale jasněji oddělit `Odběratelé` a `Historie rozesílek`, případně dát první důraz na ten blok, který je častější pro běžného správce.  
**Priorita:** Nízká až střední

## Opakující se anti-patterny

- **Kicker bez nové informace**: nadpis už význam nese sám a kicker jen přidává šum.
- **Systémový jazyk místo uživatelského**: `Obsah`, `Přehled`, `Struktura`, `Moduly`, `HP`.
- **Dvojí navigace ke stejné věci**: levé menu plus dashboard tlačítka plus CTA blok.
- **Detail začíná metou místo významem**: návštěvník chce nejdřív vědět co čte, ne v jaké je to sekci systému.
- **Příliš mnoho sekundárních sekcí na jedné obrazovce**: hlavně dashboard a nastavení.
- **Empty state bez dalšího směru**: říká jen, že nic není, ale neukazuje, co dělat dál.

## Rychlé výhry

- odstranit redundantní kickery na homepage, vyhledávání, autorovi, podcastu a galerii
- zjednodušit hero na homepage tak, aby neříkal `Úvodní stránka`
- sjednotit CTA slovník napříč webem
- přepsat quick links na dashboardu na menší, role-specific sadu
- nahradit zkratku `HP` textem `domovská stránka`
- v inboxech přepsat mechanické prázdné stavy a názvy akcí

## Nálezy pro pozdější polish pass

- rozdělení dashboardu podle úkolů místo podle metrik
- přestavba levého menu z modulového členění na úkolové
- rozsekání `Základního nastavení` do menších obrazovek
- systematická kontrola všech veřejných detailů, aby nezačínaly technickým meziblokem
- role-based landing dashboard podle typu uživatele

## Doporučená systémová pravidla

- Kicker používat jen tehdy, když přidává novou informaci.
- CTA nesmí opakovat cestu, která už je zřejmá z hlavní navigace.
- Detail obsahu má začínat názvem a klíčovou informací, ne technickým meta blokem.
- Dashboard má nejdřív ukázat úkoly a až potom přehledové počty.
- Empty state má být krátký, civilní a pokud možno akční.
