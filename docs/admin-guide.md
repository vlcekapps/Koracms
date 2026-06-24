# Podrobný průvodce administrací – Kora CMS

Tento dokument doplňuje [README.md](../README.md) o podrobnější informace k jednotlivým modulům a funkcím. README obsahuje vše potřebné pro instalaci, konfiguraci a provoz. Tento soubor je určený pro administrátory, kteří chtějí detailnější přehled o možnostech CMS.

---

## Form Builder – typy polí a workflow

### Podporované typy polí

| Typ | Popis |
|-----|-------|
| `text` | Jednořádkový textový vstup |
| `email` | E-mailová adresa s validací |
| `tel` | Telefonní číslo |
| `url` | Webová adresa |
| `textarea` | Víceřádkový text |
| `select` | Rozbalovací výběr |
| `radio` | Výběr jedné z možností |
| `checkbox` | Zaškrtávací pole |
| `více voleb` | Výběr více možností najednou |
| `number` | Číselný vstup |
| `date` | Datum |
| `file` | Příloha (podporuje více souborů, omezení typu a velikosti) |
| `hidden` | Skryté pole |
| `consent` | Souhlas (GDPR apod.) |

Každé pole může mít nápovědu, placeholder a výchozí hodnotu.

Pole typu `url` ve veřejném formuláři vyžaduje úplnou adresu začínající na `http://` nebo `https://`. CMS tím odmítá interní cesty, holé domény, nebezpečná schémata, řídicí znaky a URL s přihlašovacími údaji. Pokud chcete od návštěvníka přijmout i volnější text typu interní cesty nebo poznámky k místu, použijte raději obyčejné textové pole.

### Sekce a rozložení

- Formulář lze rozdělit do sekcí s vlastním mezititulkem a úvodním textem.
- Pole se řadí do řádků s nastavitelnou šířkou.
- Podmíněné zobrazování: pole se zobrazí jen tehdy, když jiné pole splní podmínku (`je vyplněno`, `je prázdné`, `rovná se`, `nerovná se`, `obsahuje`, `neobsahuje`).

### Workflow odpovědí (helpdesk)

Odpovědi mají referenční kód a procházejí stavy:

1. **Nové** – čerstvě odeslané
2. **Rozpracované** – převzaté řešitelem
3. **Vyřešené** – vyřízené
4. **Uzavřené** – archivované

U každé odpovědi lze nastavit prioritu, štítky, přiřazení řešiteli a interní poznámku. Z detailu odpovědi je možné přímo odpovědět odesílateli.

Rychlé kroky: `Převzít řešení`, `Označit jako rozpracované`, `Označit jako vyřešené`, `Uzavřít hlášení`.

Filtry: `Jen moje`, `Nepřiřazené`, `S GitHub issue`.

### Po odeslání formuláře

- Potvrzení na stejné stránce nebo interní přesměrování
- Až dvě navazující tlačítka po úspěšném odeslání
- Notifikační e-mail správci
- Potvrzovací e-mail odesílateli (volitelné, s vlastním předmětem a textem)
- CSV export odpovědí

### GitHub issue bridge

Z detailu odpovědi lze:

- vytvořit nové GitHub issue
- otevřít připravený návrh na GitHubu
- ručně napojit existující issue URL

### Webhooky

Formulář může po odeslání, změně workflow, odpovědi odesílateli nebo po vytvoření/napojení GitHub issue poslat JSON payload na vlastní endpoint.

Webhook URL musí být zadaná jako explicitní veřejná `https://` adresa. CMS z bezpečnostních důvodů nebere holou doménu jako webhook, odmítá interní nebo lokální hosty, jiné schéma než HTTPS, řídicí znaky i URL s uživatelským jménem nebo heslem. Ostatní běžná externí URL pole v administraci mohou holou doménu doplnit na `https://`, ale používají stejný zákaz nebezpečných schémat, protocol-relative adres a přihlašovacích údajů v URL.

### Hotové šablony formulářů

- Nahlášení chyby
- Návrh nové funkce
- Žádost o podporu
- Obecný kontaktní formulář
- Nahlášení problému s obsahem

Po vytvoření formuláře se editor vrací zpět na detail formuláře se stavovou hláškou `Formulář byl uložen.`. U šablony **Nahlášení chyby** se navíc všechna předpřipravená pole založí už při prvním uložení, takže je formulář po zveřejnění okamžitě použitelný i na veřejném webu bez nutnosti mezikroku typu „uložit znovu“.

---

## Validace dat a časů v administraci

U obsahových modulů Kora CMS platí, že datumy a časy zadané ve formulářích musí mít přesný formát. Pokud je hodnota neplatná, CMS ji nově neuloží „tiše jako prázdnou“, ale vrátí správce zpět do formuláře s chybovou hláškou.

Týká se to hlavně těchto oblastí:

- stránky: `Plánované zrušení publikace`
- blogové články: `Plánované publikování` a `Plánované zrušení publikace`
- podcast epizody: `Plánované zveřejnění`
- ankety: datum a čas začátku a konce
- jídelní a nápojové lístky: `Platí od` a `Platí do`
- zdroje rezervací: otevírací doba, předdefinované sloty a blokovaná data
- veřejný rezervační formulář: datum rezervace musí být nejen ve správném formátu, ale i skutečně existovat v kalendáři

U rezervací se navíc celý save workflow zapisuje transakčně. Když selže některý krok při ukládání otevíracích hodin, slotů nebo blokovaných dat, změna se vrátí zpět jako celek a v databázi nezůstane jen část nového nastavení.

Veřejný booking flow nově používá stejnou přísnější logiku i pro samotný den rezervace. Datum typu `2026-02-31` se už nepřevede na jiný existující den, ale formulář návštěvníka vrátí zpět s validační chybou.

Editor zdrojů rezervací používá i u existujících a dynamicky přidávaných blokovaných dnů skutečné skryté popisky polí (`label` + `id`) pro datum a důvod, takže čtečka obrazovky nepřijde o kontext ani po přidání řádku JavaScriptem.

To je důležité hlavně po ručních úpravách nebo importech, kdy se nejčastěji objeví překlep v datu, obrácený rozsah nebo neplatný čas.

---

## Import / Export dat a UTF-8

Obrazovka **Import / Export** pracuje s JSON soubory v UTF-8.

Samostatné importy z WordPressu a eStránek pracují s XML/WXR soubory. Před parsováním používají sdílenou upload validaci pro stav PHP uploadu, ověření dočasného souboru a odmítnutí prázdného souboru; WordPress náhled si dočasnou kopii ukládá přes stejný bezpečný helper do `uploads/tmp`. Downloader fotografií z eStránek zároveň normalizuje základní URL webu přes sdílený http/https helper, takže se při importu nepoužijí protocol-relative adresy, přihlašovací údaje v URL ani nebezpečná schémata.

Platí tato pravidla:

- export z Kora CMS zapisuje JSON s českou diakritikou v UTF-8
- import nově odmítne JSON soubor s neplatným UTF-8, aby se poškozené texty neuložily do databáze
- UTF-8 BOM na začátku souboru nevadí; import si ho automaticky odstraní
- import obnovuje i historii odeslaných newsletterů včetně předmětu, obsahu, počtu příjemců a času odeslání
- ruční SQL restore mimo administraci musí používat klientské spojení `utf8mb4`, jinak se české znaky mohou změnit na `?`

Prakticky to znamená, že běžný export a následný import přes administraci je bezpečný i pro české texty. Největší riziko poškození diakritiky vzniká až při ručním importu SQL dumpu přes nástroj nebo klienta, který nepoužívá `utf8mb4`.

---

## Nastavení webu – logo a favicona

V sekci **Obecná nastavení** lze dál nahrávat logo a faviconu webu, ale branding uploady mají nově přísnější ochranu.

Platí tato pravidla:

- nové SVG soubory se pro logo ani faviconu nepřijímají
- favicona musí respektovat backend limit `256 KB`
- logo musí respektovat backend limit `2 MB`
- kontrola uploadu používá sdílenou validační vrstvu pro stav PHP uploadu, typ souboru a finální uložení
- při chybě uploadu nebo neplatném dočasném souboru se formulář vrátí s čitelnou chybovou hláškou
- ukládání běží přes PRG workflow, takže refresh po uložení neopakuje POST
- validovaná pole nově vracejí i lokální field-level chyby s `aria-invalid` a zachováním zadaných hodnot po redirectu
- Apache `uploads/.htaccess` současně blokuje běžné skriptové přípony v upload adresáři, například `.php`, `.phtml`, `.phar`, `.cgi`, `.pl`, `.py`, `.rb`, `.sh`, `.asp`, `.aspx` a `.jsp`; release ZIP obsahuje jen tento ochranný soubor bez lokálních médií a runtime audit hlídá, že se pojistka neztratí.

To snižuje riziko, že se do veřejně servírovaných assetů dostane aktivní obsah nebo nepřiměřeně velký soubor.

---

## Domovská stránka – úvodní text jako widget

Úvodní text domovské stránky se nově nespravuje v **Obecných nastaveních**, ale pouze přes widget **Úvodní text**.

Platí tato pravidla:

- homepage intro má jediný zdroj pravdy ve widgetech
- widget `Úvodní text` umí HTML a stejné snippety jako ostatní obsahové bloky
- pokud obsah widgetu zůstane prázdný, na webu se vůbec nevykreslí
- na homepage má widget skrytý nadpis pro čtečky obrazovky, takže se dá najít i navigací po nadpisech bez vizuální změny stránky
- po aktualizaci starší instalace `migrate.php` automaticky převede původní `home_intro` do intro widgetu na homepage

Prakticky to znamená, že stejného výsledku jako dřívější pole v nastavení webu nově dosáhnete tím, že intro widget umístíte na homepage na požadovanou pozici.

---

## Podcasty – feed metadata

Každý podcastový pořad má vlastní nastavení feedu pro podcastové aplikace a katalogy.

### Nastavení pořadu

- Počet epizod ve feedu (nastavitelný per pořad)
- Krátký podtitul pro katalogy
- Vlastník feedu a kontaktní e-mail
- Režim `explicit`
- Typ pořadu: `episodic` / `serial`
- Příznak `complete` (uzavřený pořad)

### Nastavení epizody

- Vlastní podtitul
- Číslo série
- Typ: `full` / `trailer` / `bonus`
- Vlastní explicit režim (přepíše nastavení pořadu)
- Možnost skrýt epizodu z RSS feedu

### Generované iTunes značky

`itunes:summary`, `itunes:subtitle`, `itunes:owner`, `itunes:type`, `itunes:explicit`, `itunes:season`, `itunes:episodeType`

---

## Podcasty – artwork

Každý podcastový pořad může mít vlastní cover obrázek a každá epizoda svůj samostatný artwork.

- Formát: čtvercový `JPG` nebo `PNG`
- Minimální rozměr: `1024 × 1024 px`
- Maximální rozměr: `3000 × 3000 px`
- Pokud epizoda nemá vlastní obrázek, použije se cover pořadu.

Tyto rozměry odpovídají požadavkům Apple Podcasts a dalších katalogů.

---

## Podcasty – viditelnost, historie a veřejný výstup

- Pořad nově může být skrytý z veřejného webu i při zachování rozpracované administrace.
- Změna slugu pořadu i epizody ukládá redirect ze staré URL na nový canonical tvar.
- Editor pořadu i epizody má odkaz na historii revizí.
- Seznam pořadů i epizod v administraci používá stránkování a drží návrat do stejného filtrovaného kontextu.
- Veřejné audio, cover a obrázky epizod už nejdou přímo z `/uploads/podcasts/...`, ale přes kontrolované endpointy.
- RSS feed pořadu publikuje přesnější metadata pro katalogy včetně správného `managingEditor` a délky lokálního `enclosure`.
- RSS feed pořadu je čtecí veřejný endpoint a podporuje jen metody `GET` a `HEAD`.
- Veřejný detail pořadu i epizody doplňuje structured data a epizody respektují i veřejnou viditelnost samotného pořadu.

---

## Multiblog – týmy, metadata a veřejný výstup

Multiblog je určený pro situace, kdy má jeden web více samostatných blogů s vlastní identitou, RSS feedem a týmem autorů.

### Co patří do README a co sem

- [README.md](../README.md) popisuje, že Kora CMS multiblog umí a jak zapadá do celého systému.
- Tento dokument popisuje, jak se multiblog skutečně spravuje v administraci a co jednotlivé volby dělají.

### Správa blogu

U každého blogu lze nastavit:

- název, slug a krátký popis
- volitelné logo blogu
- volitelný alternativní text loga pro čtečky obrazovky
- rozšířený úvod blogu nad výpisem článků
- `meta title` a `meta description`
- RSS podtitulek
- výchozí stav komentářů pro nové články
- počet položek v RSS feedu daného blogu
- zobrazení blogu v hlavní navigaci

Pokud blog změní slug, Kora CMS si uloží starý slug jako redirect a staré URL i RSS feed se přesměrují na nový tvar.

Pokud je u loga vyplněný alternativní text, veřejný index blogu ho použije jako `alt` obrázku. Když pole zůstane prázdné, logo se bere jako dekorativní a čtečky obrazovky ho přeskočí. Po odebrání loga se alternativní text automaticky vyprázdní, aby v administraci nezůstal viset bez obrázku.

Create i edit formulář blogu jsou nově rozdělené do srozumitelných sekcí `Základní údaje blogu`, `Obsah a metadata blogu` a `Logo a zobrazení blogu`. Pomocné texty jsou napojené přes skutečné `aria-describedby`, takže formulář lépe funguje i pro čtečky obrazovky.

### Týmy blogů

Každý blog může mít vlastní tým v obrazovce **Tým blogu**.

Role v blogu:

- `Autor blogu` – může psát a upravovat články ve svých přidělených blogech
- `Správce blogu` – navíc může spravovat kategorie a štítky daného blogu

Jakmile začnete používat přiřazení blogů, autor už neuvidí všechny blogy v systému, ale jen ty, do kterých patří.

### Přehled přiřazení

Správa týmů je teď viditelná i z více míst:

- **Tým blogu** ukazuje u každého uživatele i jeho další blogová přiřazení, takže je hned jasné, kdo píše do více blogů
- **Uživatelé a role** nově obsahují sloupec `Blogy`, kde je vidět přiřazení každého interního účtu
- **Správa blogů** zobrazuje i počet členů týmu u každého blogu
- **Správa blogů** zároveň nově nabízí rychlé odkazy `Články blogu`, `Kategorie blogu`, `Štítky blogu` a `Stránky a odkazy blogu`, takže lze z jednoho přehledu přejít rovnou na správu konkrétního blogu
- **Správa blogů** používá pro formulář vytvoření blogu, dialog úprav a náhled loga sdílené admin CSS třídy bez lokálního `<style>` bloku a bez lokálních `style` atributů, takže méně zatěžuje CSP reporty a drží konzistentní modální chování

To je užitečné hlavně ve chvíli, kdy jeden autor spravuje více blogů nebo když chcete rychle zkontrolovat, zda má nový redaktor přístup opravdu jen tam, kam patří.

### Články v rámci blogu

Editor článku respektuje vybraný blog:

- nabídne jen kategorie a štítky tohoto blogu
- horní odkazy vedou na správný veřejný blog, jeho RSS feed a správu taxonomií
- nové články mohou převzít výchozí komentáře z blogu
- jeden článek v blogu lze označit jako `Doporučený článek blogu`
- náhledový obrázek používá sdílenou upload validaci a při výměně nebo odebrání uklízí i staré miniatury, WebP a responsive varianty

Na veřejném indexu blogu se pak bez aktivních filtrů zobrazí právě jeden doporučený článek.

### Stránky a odkazy blogu

Každý blog může mít vlastní horní navigaci nad výpisem článků. Do stejného pořadí lze přidat statické stránky blogu i externí nebo interní odkazy. Odkaz má název, cílovou adresu, volitelný přístupný popis pro čtečky obrazovky, přepínač zobrazení a volbu otevření v novém okně. Přístupný popis doplňuje viditelný název odkazu, aby čtečka nehlásila jiný název než ten, který je vidět na stránce. Pokud se odkaz otevírá v novém okně, veřejný výstup automaticky přidá bezpečné atributy `target="_blank"` a `rel="noopener noreferrer"` a oznámí nové okno i v přístupném názvu odkazu.

Globální hlavní navigace v **Navigace webu** používá stejný model pro odkazy napříč celým webem. Blogové odkazy jsou ale oddělené od globální navigace a řadí se jen v kontextu konkrétního blogu.

V přehledech `Statické stránky` a `Články` se pro převod obsahu dál zobrazuje šipka jako vizuální vodítko, ale pro čtečky obrazovky je nově skrytá. Asistivní technologie tak hlásí jen samotnou akci `Článek` nebo `Stránka`, ne dekorativní symbol před ní.

### Přesun článků mezi blogy

Z přehledu článků lze nově hromadně přesouvat články mezi blogy. Funkce je dostupná jen tehdy, když má uživatel přístup alespoň do dvou blogů, do kterých smí zapisovat.

Přesun respektuje tato pravidla:

- autor může přesouvat jen své vlastní články
- cílový blog musí patřit mezi blogy, do kterých má aktuální uživatel write přístup
- celý přesun běží v jedné databázové transakci
- po dokončení se uloží revize změny blogu, kategorie i štítků

### Jak fungují kategorie a štítky při přesunu

Nejprve se CMS pokusí použít existující shodu:

- kategorie podle stejného názvu v cílovém blogu
- štítky podle stejného slugu, případně názvu

Jen pokud shoda neexistuje, nabídne převod další možnosti.

Pro správce taxonomií cílového blogu jsou dostupné tři strategie:

- `Bez kategorizace` / `Bez štítků`
- `Vytvořit chybějící kategorie` / `Vytvořit chybějící štítky`
- `Mapovat na existující`

Varianta `Mapovat na existující` zobrazí pro každou chybějící zdrojovou kategorii a každý chybějící zdrojový štítek samostatný výběr. V něm lze rozhodnout:

- použít konkrétní existující kategorii nebo štítek cílového blogu
- nebo položku explicitně zahodit jako `Bez kategorie` / `Bez štítku`

Běžný autor bez práva spravovat taxonomie cílového blogu dostane jen bezpečné varianty `Bez kategorizace` a `Bez štítků`.

Server zároveň ověřuje, že ručně zvolená cílová kategorie nebo štítek opravdu patří do vybraného cílového blogu. Podvržené ID z jiného blogu proto přesun neprojde.

### Veřejný blog index

Každý blog může mít na svém indexu:

- název a volitelné logo
- krátký popis
- rozšířený intro blok
- přímý odkaz na RSS feed
- vlastní vyhledávání jen v rámci daného blogu
- archiv po měsících
- filtr podle autora, kategorie a štítků

### Per-blog RSS

Každý blog má vlastní feed:

- `feed.php?blog=slug-blogu`

Feed respektuje:

- název a metadata blogu
- RSS podtitulek
- počet epizod/položek nastavený pro konkrétní blog
- aktuální slug blogu i staré redirecty

---

## Blog RSS feedy

Kora CMS poskytuje globální RSS feed i samostatné feedy jednotlivých blogů.

- Globální feed: `feed.php`
- Feed konkrétního blogu: `feed.php?blog=slug-blogu`
- Blogový feed používá vlastní název, popis a self odkaz.
- V blogovém feedu jsou jen články daného blogu.
- RSS feedy jsou čtecí veřejné endpointy a podporují jen metody `GET` a `HEAD`.

---

## Jídelní a nápojové lístky

Modul jídelních a nápojových lístků je vhodný pro restaurace, kavárny, spolkové klubovny i provozy, které potřebují rozlišovat aktuální, připravované a archivní menu.

### Co se nastavuje u lístku

Každý lístek může mít:

- typ `Jídelní lístek` nebo `Nápojový lístek`
- název, slug a krátkou poznámku
- plný obsah v HTML editoru
- datum `Platí od` a `Platí do`
- příznak `Použít jako aktuální lístek`
- zveřejnění na webu

Pokud je u typu označený nový aktuální lístek, předchozí aktuální lístek stejného typu se při uložení automaticky odznačí.

### Jak funguje platnost na webu

- Stránka `Jídelní a nápojový lístek` zobrazuje jen lístky, které jsou zároveň označené jako aktuální a časově právě platí.
- Archiv nově rozlišuje scope:
  - `Platí nyní`
  - `Připravujeme`
  - `Archivní`
  - `Všechny lístky`
- Scope vychází z polí `Platí od / do`.
- Pokud pole platnosti nejsou vyplněná, lístek se bere jako časově neomezený a spadá do scope `Platí nyní`.

### Co je nové v admin workflow

- Přehled lístků nově umí hledání, workflow stav, filtr podle typu i filtr podle časové platnosti.
- Z detailu i ze seznamu vede odkaz na historii revizí.
- Revize zachycují změny typu, názvu, slugu, popisu, obsahu, platnosti, stavu aktuálnosti i zveřejnění.
- Při změně slugu se automaticky uloží redirect ze staré adresy na novou.

### Veřejný archiv a detail

Veřejný archiv nově podporuje:

- fulltextové hledání
- filtrování podle typu
- přepínání scope `Platí nyní / Připravujeme / Archivní / Všechny lístky`
- stránkování

Detail lístku nově:

- ukazuje jasný stav `Platí nyní / Připravujeme / Archivní`
- zachovává návrat do původního archivního kontextu
- nabízí akci `Vytisknout`
- vkládá structured data typu `Menu`

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že modul podporuje platnost, archiv, hledání a revize.
- Tento dokument popisuje konkrétní redakční workflow, chování scope filtrů a pravidla viditelnosti aktuálních lístků.

---

## Galerie

Modul galerie je vhodný pro fotoalba, kroniky, dokumentaci akcí i menší obrazové archivy. Nově je dotažený jak po redakční stránce, tak po stránce veřejné viditelnosti a bezpečnosti souborů.

### Co se nastavuje u alba

Každé album může mít:

- název, slug a popis
- volitelné nadřazené album
- náhledovou fotografii alba
- zveřejnění na webu

Formulář alba nově obsahuje i odkaz na historii revizí.

### Co se nastavuje u fotografie

Každá fotografie může mít:

- titulek
- slug
- pořadí v albu
- zveřejnění na webu

Fotografie lze rychle přesouvat i přímo v přehledu alba pomocí tlačítek `Nahoru` a `Dolů`, bez nutnosti ručně přepisovat pořadí.

### Veřejná viditelnost a bezpečnost

- Veřejné stránky alba i fotografie nově zobrazují jen publikovaná alba a publikované fotografie.
- Skrytá alba a skryté fotografie se nevracejí ani ve vyhledávání a nejsou ani v sitemapě.
- Obrázky se nově zobrazují přes serverový endpoint `gallery/image.php`, takže veřejný HTML výstup už neodkazuje přímo do `/uploads/gallery/`.
- Přímý přístup do `/uploads/gallery/` je zablokovaný na úrovni serveru.
- Hromadné nahrávání fotografií používá sdílenou upload validaci pro stav uploadu, velikost, MIME typ a finální uložení souboru.

To znamená, že „skryté“ už neznamená jen „není v přehledu“, ale skutečně se neukazuje ani přes detail, vyhledávání a běžné veřejné odkazy.

### Veřejný výpis a detail

Veřejná galerie nově podporuje:

- hledání v přehledu alb
- hledání uvnitř konkrétního alba
- stránkování přehledu i alba
- zachování filtračního kontextu při návratu z detailu fotografie
- související fotografie v detailu
- akci `Kopírovat odkaz`
- structured data `ImageGallery` a `ImageObject`

### Revize a změny slugů

- Změny alba i fotografie se zapisují do historie revizí.
- Pokud se změní slug alba nebo fotografie, stará veřejná adresa se uloží jako redirect na nový canonical tvar.

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že galerie podporuje detailová URL, hledání, stránkování, revize a bezpečný media endpoint.
- Tento dokument popisuje konkrétní workflow galerie, pravidla veřejné viditelnosti, práci s pořadím fotografií a bezpečnostní model doručování obrázků.

---

## Chat

Modul chatu teď funguje jako moderovaná veřejná nástěnka, ne jako okamžitě publikovaný shoutbox. Každá nová zpráva nejdřív přijde do administrace a veřejně se zobrazí až po schválení.

### Co se zobrazuje veřejně

Veřejný chat ukazuje jen:

- jméno autora
- datum odeslání
- text zprávy

E-mailová adresa zůstává jen pro administraci a veřejně se nikdy nezobrazuje. Pole `web` už veřejný formulář vůbec nenabízí.

### Jak funguje moderace

Nová zpráva se po odeslání uloží jako:

- inbox stav `Nové`
- veřejná viditelnost `Ke schválení`

Teprve po ručním schválení se objeví na veřejném webu. Administrace umí zprávu i znovu skrýt, aniž by se ztratila z interní historie.

### Veřejný formulář a ochrana proti spamu

Formulář chatu používá:

- CSRF ochranu
- rate limiting
- honeypot pole
- CAPTCHA
- zákaz vkládání URL do textu zprávy

To znamená, že veřejný chat je použitelnější i na ostrém webu a neukazuje spam nebo odkazy hned po odeslání.

### Inbox workflow v administraci

Přehled chat zpráv nově umí:

- fulltextové hledání
- stránkování
- filtr podle inbox stavu `Nové / Přečtené / Vyřízené / Všechny`
- filtr podle veřejné viditelnosti `Ke schválení / Zveřejněné / Skryté / Vše`
- hromadné akce `Schválit`, `Skrýt`, `Označit jako přečtené`, `Označit jako vyřízené`, `Smazat`

Detail zprávy přidává:

- interní poznámku
- historii změn
- rychlé workflow akce
- odpověď odesílateli e-mailem, pokud je k dispozici platná adresa

Samotné otevření detailu označí zprávu jako přečtenou, ale automaticky ji nezveřejní.

### Automatický úklid přes cron

V nastavení webu lze nově zadat počet dní, po kterých se mají automaticky mazat staré vyřízené chat zprávy. Hodnota `0` znamená, že se automatický úklid nepoužije.

Cleanup provádí `cron.php` a maže jen:

- zprávy se stavem `Vyřízené`
- starší než nastavený limit

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že chat je moderovaný, stránkovaný a má inbox workflow.
- Tento dokument popisuje konkrétní redakční workflow, veřejnou viditelnost zpráv, moderaci, odpovědi a automatický cleanup.

---

## Vývěska / Úřední deska

Modul vývěsky je určený pro oznámení, dokumenty a další veřejné položky, které se mají zobrazit od určitého data a případně po čase spadnout do archivu.

### Co se nastavuje u položky

Každá položka může mít:

- typ položky
- kategorii
- datum vyvěšení
- datum sejmutí
- krátký perex a plný text
- volitelný obrázek
- kontaktní osobu, telefon a e-mail
- volitelnou přílohu
- připnutí mezi důležité položky
- zveřejnění na webu

Formulář nově zobrazuje i kontextovou nápovědu podle typu položky a odkaz na historii revizí.

### Jak funguje veřejné zobrazení

- Položka se na veřejném webu zobrazí až od data vyvěšení.
- Pokud má datum sejmutí a to už uplynulo, přesune se do archivu.
- Veřejný výpis nově umí hledání, filtr podle kategorie, filtr podle období vyvěšení a přepínání `aktuální / archiv / vše`.
- Veřejný detail archivní položky vrací návštěvníka zpět rovnou do archivního výpisu.

### Přílohy a přístupová práva

- Veřejně publikovaná položka může nabídnout přílohu ke stažení přes bezpečný serverový endpoint.
- Neveřejné přílohy už nejsou dostupné každému přihlášenému účtu.
- Přístup k neveřejné příloze má jen správce obsahu nebo schvalovatel.
- Nahrání přílohy používá sdílenou upload validaci pro stav uploadu, MIME typ a finální uložení, takže se pravidla ukládání neliší od ostatních modulů.

### Revize a změny slugů

- Úpravy položek se zapisují do historie revizí stejně jako u dalších obsahových modulů.
- Pokud se změní slug položky, stará veřejná adresa se uloží jako redirect na nový canonical tvar.

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že vývěska podporuje datum vyvěšení, připnutí, filtrování a archiv.
- Tento dokument popisuje konkrétní workflow modulu, veřejné chování a přístupová pravidla příloh.

---

## Ke stažení – katalog verzí a veřejné filtry

Modul **Ke stažení** už neslouží jen jako jednoduchý seznam souborů. Je připravený i pro katalog dokumentů, aplikací a instalačních balíčků, kde je potřeba držet verze, kompatibilitu a bezpečnější download workflow.

### Co se nastavuje u položky

Každá položka může mít:

- typ položky
- kategorii
- krátký perex a plný popis
- lokální soubor, externí odkaz nebo obojí zároveň
- domovskou stránku projektu
- verzi a datum vydání
- platformu a licenci
- požadavky a kompatibilitu
- SHA-256 checksum
- skupinu verzí pro propojení více vydání stejné aplikace nebo dokumentu
- volitelný náhledový obrázek
- příznak `Doporučená položka`
- zveřejnění na webu

### Co je nové v admin workflow

- Přehled `Ke stažení` umí hledání i filtry podle stavu, kategorie, typu, zdroje, platformy a doporučených položek.
- U detailnějších položek lze otevřít historii revizí stejně jako u dalších obsahových modulů.
- Při změně slugu se stará veřejná adresa uloží jako redirect na nový canonical tvar.
- Přehled ukazuje i základní statistiku stažení a praktická metadata, takže správce nemusí otevírat každou položku zvlášť.

### Veřejný katalog

- Veřejný výpis podporuje hledání a filtry podle kategorie, typu, platformy, zdroje a doporučených položek.
- Výsledky jsou stránkované a řadí se přirozeně: doporučené položky výš, pak novější vydání.
- Detail položky zobrazuje i praktické informace jako verzi, datum vydání, velikost souboru, checksum, požadavky a kompatibilitu.
- Pokud více položek sdílí stejnou `Skupinu verzí`, detail ukáže i další dostupné verze ke stažení.

### Přílohy a přístupová práva

- Veřejně viditelné položky lze stahovat přes serverový download endpoint.
- Neveřejné nebo neschválené soubory nestáhne libovolný přihlášený účet.
- Přístup k neveřejným souborům má jen správce obsahu nebo schvalovatel.
- Počet veřejných stažení se zapisuje do statistiky položky.
- Lokální soubory ke stažení používají sdílenou upload validaci pro stav uploadu, bezpečnou příponu, finální uložení a automatický výpočet SHA-256 checksumu.

### Co patří do README a co sem

- [README.md](../README.md) jen stručně říká, že modul `Ke stažení` umí katalog verzí, metadata a veřejné filtrování.
- Tento dokument popisuje, jak správce pracuje s položkami, verzemi, revizemi a přístupem k neveřejným souborům.

---

## Znalostní báze – FAQ workflow

### Co se nastavuje u otázky

V editoru FAQ položky se nově nastavuje:

- otázka, slug a krátké shrnutí
- odpověď v HTML editoru
- kategorie FAQ
- `meta title` a `meta description` pro detail otázky
- publikační stav a zveřejnění na webu

Při změně slugu se automaticky uloží redirect ze staré adresy na novou, takže veřejné odkazy nepřestanou fungovat.

### Co je nové v admin workflow

- Přehled FAQ nově umí hledání, workflow stav a filtr podle kategorie.
- Revize ukládají nejen text otázky a odpovědi, ale i kategorii, SEO metadata a publikační stav.
- FAQ export a import drží stejná pole jako aktuální editor, takže se metadata neztratí při migraci obsahu.

### Veřejný výpis a detail

Veřejná znalostní báze nově podporuje:

- fulltextové hledání
- filtr podle kategorie
- stránkování
- přepínání `Přehled karet / Rozbalené odpovědi`
- zachování kontextu při návratu z detailu otázky
- související otázky na detailu
- `FAQPage` strukturovaná data pro vyhledávače

Detail otázky umí použít vlastní `meta title` a `meta description`. Pokud nejsou vyplněné, použije se otázka a shrnutí FAQ.

### Co patří do README a co sem

- [README.md](../README.md) jen stručně říká, že znalostní báze umí hledání, stránkování, SEO a strukturovaná data.
- Tento dokument popisuje konkrétní práci správce s kategoriemi, SEO poli, revizemi a veřejným výpisem FAQ.

---

## Novinky – rychlé zprávy, plánované skrytí a SEO

### Co se nastavuje u novinky

Editor novinky teď pokrývá:

- titulek a slug
- plný HTML obsah
- publikační stav
- `Plánované zrušení publikace`
- interní poznámku pro administraci
- `meta title` a `meta description` pro detail novinky

Pokud `meta title` nebo `meta description` nevyplníte, veřejný detail použije bezpečný fallback z titulku a výtahu obsahu.

### Co je nové v admin workflow

- Přehled `Novinky` nově umí fulltext, stavový filtr a stránkování.
- Filtrovaný seznam si drží stabilní návrat i po bulk akci nebo schválení položky.
- Revize nově zachycují nejen text, ale i `unpublish_at`, interní poznámku a SEO pole.
- Při změně slugu se stará adresa uloží jako redirect na nový canonical tvar.

### Veřejný výpis a detail

Veřejný modul novinek nově podporuje:

- fulltextové hledání nad `title + content`
- stránkování i při aktivním hledání
- tvrdou public visibility logiku: zobrazí se jen publikované novinky bez `deleted_at` a bez expirovaného `unpublish_at`
- `NewsArticle` structured data na detailu

To znamená, že novinka s již uplynulým `Plánovaným zrušením publikace` se neukáže:

- na veřejném indexu novinek
- v detailu
- ve veřejném vyhledávání
- ani v sitemapě

### Export / import a kompatibilita

Export i import nově drží stejnou sadu polí jako aktuální editor novinky, včetně:

- `author_id`
- `unpublish_at`
- `admin_note`
- `meta_title`
- `meta_description`
- `deleted_at`

Starší exporty zůstávají kompatibilní; chybějící novější pole se při importu doplní rozumnými výchozími hodnotami.

### Co patří do README a co sem

- [README.md](../README.md) jen stručně říká, že modul Novinky umí veřejné hledání, plánované skrytí a SEO fallbacky.
- Tento dokument popisuje konkrétní workflow editoru, plánované zrušení publikace, revize, redirecty a veřejné chování modulu.

---

## Provozní bezpečnost a vývojové nástroje

Kora CMS drží produkční běh bez Composer závislostí. Adresář `vendor/` vzniká po `composer install` jen pro lokální vývojové nástroje a CI, například PHPStan a PHP-CS-Fixer; `node_modules/`, `.codex/`, `.cursor/` a podobná lokální editorová metadata patří také jen do pracovního checkoutu. Release balíček `dist/koracms-*.zip` záměrně neobsahuje ani `vendor/`, ani `node_modules/`, ani lokální AI/editor metadata, ani vývojové metadata soubory jako `composer.json`, `composer.lock`, `phpstan.neon.dist` nebo `.php-cs-fixer.dist.php`. Root `.htaccess`, testovací PHP router i Nginx ukázka navíc tyto lokální složky blokují i při přímém webovém požadavku, takže se nemají dát omylem stáhnout ani z pracovního webrootu. Instalační ZIP i source archive naopak povinně obsahují root `.htaccess`, `README.md`, `CHANGELOG.md`, `VERSION`, `config.sample.php`, `install.php`, `migrate.php`, `docs/admin-guide.md`, základní default šablonu, kritické CSS assety a ochranný `uploads/.htaccess` se stabilním casingem názvů. ZIP se vytváří explicitním `ZipArchive` průchodem souborů včetně dotfiles, aby ochranný `.htaccess` nevypadl z artefaktu na Linux runneru. Release skript před vytvořením nové verze spouští statický release package audit i `composer ci:basic`; přepínačem `-FullCi` lze vyžádat i `composer ci:full` s runtime a HTTP integrací. Self-test release package auditu v základní CI nad dočasnou kopií release guardů ověřuje, že audit opravdu selže na návratu `vendor`, `node_modules`, lokálních AI metadat, `Compress-Archive`, rozbitých release smoke kontrolách a chybějících pravidlech `.gitattributes` nebo `.gitignore`. Přepínač `-DryRun` projde stejný preflight a vytvoří ZIP se `.sha256` otiskem a náhledem nové verze, ale nemění pracovní `VERSION` ani `CHANGELOG.md`, nevytváří commit/tag/push a nezakládá GitHub release; náhled changelogu i ostrý release ponechá nahoře novou prázdnou sekci `Unreleased` pro další změny. `composer ci:basic` navíc spouští izolovaný release smoke test nad dočasným snapshot repozitářem, takže kontroluje i skutečné chování dry-run režimu bez zásahu do vašeho pracovního stromu i reálný `git archive` source balíček podle `.gitattributes`; oba artefakty musí zůstat bez lokálních IDE/AI metadat, `dist`, `vendor`, `node_modules`, citlivých konfigurací a uživatelských uploadů mimo `uploads/.htaccess`. Soubor `VERSION` je jediný zdroj pravdy pro runtime `KORA_VERSION` i release artefakty; `build/version_metadata_audit.php` hlídá, aby se lokální verze, dry-run ZIP a source archive nerozjely. Vedle ZIPu vzniká i `.sha256` soubor se SHA-256 otiskem artefaktu.

Přihlašování a obnova hesla používají kombinovaný rate limiting:

- limit podle IP adresy
- limit podle hashovaného účtu nebo reset tokenu
- bez ukládání e-mailu nebo tokenu v čitelné podobě do tabulky `cms_rate_limit`

To chrání nejen proti opakovaným pokusům z jedné adresy, ale i proti útokům rozloženým přes více IP adres na stejný účet.

Návrat po administrátorském přihlášení zachovává původně otevřenou stránku jen tehdy, když jde o bezpečný interní cíl v administraci nebo `migrate.php`. Cíle mimo administraci, externí URL, protocol-relative adresy a návrat zpět na `admin/login.php` nebo `admin/login_2fa.php` se ignorují a uživatel skončí na dashboardu. Tuto ochranu hlídají unit testy i runtime audit, aby se login flow nestal otevřeným redirectem.

Správa `301/302` přesměrování dovoluje jako starou adresu jen interní cestu webu. Nová adresa může být interní cesta nebo úplná `http://` či `https://` URL, ale CMS odmítá nebezpečná schémata, protocol-relative URL, CRLF znaky a adresy s přihlašovacími údaji; stejná validace platí i pro automatické redirecty při změně slugů.

Řádková editace existujících přesměrování v tabulce používá skutečné skryté popisky polí (`label` + `id`) místo samotných `aria-label`, takže čtečka obrazovky oznamuje starou cestu, novou cestu a typ přesměrování stejně spolehlivě jako v hlavním formuláři.

Recoverable chyby v administraci a souborových cleanupech se postupně převádějí na strukturovaný `koraLog()` formát. Globální neošetřené chyby ukládají jen název souboru a hash cesty, ne plnou lokální cestu; chybová stránka návštěvníkovi ukáže bezpečný kód požadavku pro podporu a odpověď je necacheovatelná. Ukládání článků, přesun článků mezi blogy, ukládání anket, cleanup šablon, mazání prezentačních souborů a import fotek z eStránek tak v technickém logu nespoléhají na surové `error_log()` zprávy, ale přidávají `request_id`, metodu, cestu a omezený kontext bez dumpu celé žádosti nebo plných lokálních cest.

Přepínač veřejné registrace v obecném nastavení blokuje registrační formulář a zároveň schovává odkazy na registraci ve veřejné přihlašovací obrazovce i ve společné patičce webu. Pokud jsou zapnuté rezervace, zůstane návštěvníkům dostupný odkaz na přihlášení, ale nové účty může při vypnuté registraci zakládat jen oprávněný správce.

Vývojové kontroly:

- `composer ci:basic` spustí PHP lint včetně self-testu `build/lint_php_selftest.php` pro syntakticky rozbité PHP soubory a ignorování `vendor`, `dist` a `uploads`, `composer validate --strict`, repository guardrails audit včetně self-testu rezervovaných DB připojovacích proměnných i nechtěně verzovaných lokálních konfigurací, `.env` souborů, `vendor`, `node_modules`, `dist`, IDE/AI metadat a reálných uploadů, config sample audit `build/config_sample_audit.php` včetně self-testu `build/config_sample_audit_selftest.php` pro sladění `config.sample.php` s hlavní runtime konfigurací, bezpečnými výchozími hodnotami, prázdnými tokeny a SMTP defaultem `localhost:25`, version metadata audit `build/version_metadata_audit.php` včetně self-testu `build/version_metadata_audit_selftest.php` pro `VERSION` a release artefakty, schema parity audit `build/schema_parity_audit.php` včetně self-testu `build/schema_parity_audit_selftest.php` pro kritické sloupce sdílené mezi `install.php`, `migrate.php` a veřejným kódem, redirect guardrails audit včetně self-testu pro bezpečnou validaci návratových cílů z requestu a formulářů, audit GitHub Actions workflow včetně self-testu připnutých actions a zakázaných write/secrets vzorů, source encoding audit platného UTF-8 včetně self-testu `build/source_encoding_audit_selftest.php`, mojibake audit typických zkomolených českých znaků včetně self-testu `build/mojibake_audit_selftest.php`, whitespace audit koncových mezer a finálního nového řádku včetně self-testu `build/whitespace_audit_selftest.php`, release package audit včetně self-testu `build/release_package_audit_selftest.php` pro ochranu release skriptu, release smoke kontrol, `.gitattributes` a `.gitignore`, úzký PSR-12 smoke check přes PHP-CS-Fixer včetně build/test helperů, PHPStan na levelu 6 včetně self-testu `build/phpstan_bootstrap_selftest.php` pro bezpečný bootstrap bez databáze, autentizace, session a runtime konfigurace, statický release package audit a unit testy; PHPStan používá bezpečný bootstrap `build/phpstan_bootstrap.php` a symbol scan, takže zná sdílené helpery bez načítání databáze nebo session a hlídá i release/testovací nástroje
- Self-test `build/http_server_router_selftest.php` ověřuje vestavěný router pro Full CI nad dočasnou mini-instalací bez databáze: clean URL routy, statické soubory, blokování chráněných cest, zachování query parametrů a 404 fallback.
- Self-test `build/http_test_helpers_selftest.php` ověřuje HTTP test helpery nad dočasným PHP serverem: GET/POST/raw/multipart požadavky, redirecty, cookies, parser skrytých polí i refresh testovací CSRF session pro integrační scénáře.
- Theme view audit `build/theme_view_audit.php` běží v `composer ci:basic` a chrání default šablonu před tím, aby se do PHP view souborů vrátila přímá práce s request inputem, session/server stavem, databází, změnou hlaviček, souborovými zápisy, runtime časem, inline styly, inline event handlery nebo script tagy bez CSP nonce. Navíc hlídá statická duplicitní `id`, statické odkazy `aria-labelledby` / `aria-describedby` / `aria-controls` na neexistující prvky, statické `label for` bez cílového pole, formulářová pole bez labelu nebo ARIA názvu, obrázky bez `alt`, iframe bez `title`, tlačítka bez explicitního `type` a odkazy s `target="_blank"` bez `rel="noopener noreferrer"` nebo bez přístupného názvu, který výslovně oznamuje otevření v novém okně, aby se do veřejných šablon nevrátily rozbité popisky pro čtečky obrazovky, nechtěná implicitní formulářová tlačítka ani rizikové odkazy. Společné hodnoty jako přihlášený administrátor, aktuální datum a aktuální URL šablona dostává přes view data z `renderPublicPage()`. Self-test `build/theme_view_audit_selftest.php` na dočasných fixtures ověřuje, že audit umí projít čistou šablonu a selhat na zakázaných vzorech i rozbitých ARIA/formulářových vazbách.
- Runtime audit stejná pravidla ověřuje i ve zdrojích a na reálně vyrenderovaných veřejných a administračních odpovědích. Vedle duplicitních `id`, rozbitých ARIA vazeb, chybějících labelů a `alt` textů nově hlídá také POST formuláře bez `csrf_token`, iframe bez `title` a odkazy s `target="_blank"` bez `rel="noopener noreferrer"` nebo bez přístupného oznámení nového okna.
- Runtime audit vícerádkově hlídá explicitní `type` u `<button>` a `<input>` v PHP zdrojích `admin/`, `lib/` a `themes/` a stejný požadavek ověřuje i na reálně vyrenderovaném HTML. Cílem je, aby tlačítka bez záměru neodesílala formulář implicitním výchozím chováním a aby formulářová sémantika zůstala stabilní i při zalomení atributů na více řádků.
- Runtime audit u administrace hlídá také odkazy otevírané v novém okně. Pokud má `admin/*.php` odkaz `target="_blank"`, musí používat sdílený `newWindowLinkLabel()` a `rel="noopener noreferrer"`, aby bylo otevření v novém okně součástí přístupného názvu odkazu a nové okno nedostalo přístup k původnímu oknu ani referreru. Stejná kontrola pokrývá i dynamicky vytvořené `_blank` odkazy a `window.open()`, které musí oddělit nové okno přes `noopener,noreferrer`.
- `composer ci:basic` navíc obsahuje i izolovaný `test:release-smoke`, který v dočasném snapshot repozitáři skutečně provede `build/release.ps1 -DryRun -SkipCi`, ověří čistý git stav po doběhu, zkontroluje výsledný ZIP i checksum a navíc ověří, že `git archive` opravdu respektuje `export-ignore` pravidla pro `build/`, `docs/`, `dist/`, `vendor/`, `node_modules/`, lokální metadata a citlivé konfigurace; ZIP i source archive zároveň musí obsahovat kritické instalační soubory včetně root `.htaccess`, `config.sample.php`, `install.php`, `migrate.php`, default šablony a `uploads/.htaccess`
- `composer ci:full` navíc po `ci:basic` sekvenčně spustí `php build/runtime_audit.php` a `php build/http_integration.php`, takže je vhodný hlavně pro lokální ověření většího balíku změn nebo před releasem; při ručním spuštění je nepouštějte paralelně nad stejnou lokální databází, protože používají dočasná testovací nastavení a mohou si vyrobit falešné selhání; release skript ho umí spustit přepínačem `-FullCi` a bezpečnou zkoušku release bez zápisu do gitu přes `-DryRun`
- GitHub Actions drží dva oddělené workflow: `.github/workflows/ci.yml` pro běžné `push`/`pull_request` s `composer ci:basic` a `.github/workflows/full-ci.yml` pro ruční a noční běh plného `composer ci:full`; plný workflow si připraví MySQL, `config.php`, vestavěný PHP server a čerstvou instalaci CMS, takže runtime audit a HTTP integrace mají vzdálený guardrail bez zpomalení každého commitu
- Oba GitHub Actions workflow používají minimální `contents: read` oprávnění, řízení souběhu a job timeouty, aby se kvalita hlídala s menším oprávněním a bez visících běhů
- `composer format:fix` umí stejnou úzkou sadu helperů lokálně dorovnat do PSR-12 bez zásahu do širšího historického kódu; momentálně pokrývá lint/bootstrap helpery a první stabilní várku sdílených knihoven (`backup`, `comments`, `content`, `definitions`, `filedownloads`, `gallery`, `github`, `mail`, `media_library`, `messages`, `pagination`, `presentation`, `revisions`, `stats`, `theme`, `totp`, `ui`, `uploads`, `webhooks`, `widgets`)
- `composer analyse:strict` už na levelu 6 vedle základních helperů pokrývá 219 stabilizovaných souborů napříč veřejnými entrypointy, sdílenými knihovnami, workflow auditem, redirect guardraily a rozšiřovanou sadou admin workflow pro blogy, stránky, média, formuláře, podcasty, FAQ, události, ankety, místa, rezervace, widgety, komentáře, kontakty, chat, novinky, soubory ke stažení, jídelní a nápojové lístky, kategorie, newsletter, uživatele, galerii, převod obsahu, reorder endpointy a jednoduché akční endpointy; ta část kódu proto nově drží přesnější array kontrakty i bez baseline a bez ignore pravidel
- Veřejné i administrační požadavky dostávají `X-Request-ID`; globální neošetřené chyby a vybrané technické chyby se zapisují jako JSON záznamy se stejným `request_id`, metodou a cestou. Při dohledávání produkční chyby tak stačí porovnat ID z odpovědi nebo monitoringu s PHP logem; u neošetřené chyby se stejný kód zobrazí i na chybové stránce. Strukturovaný zápis se používá i pro dílčí obnovitelné chyby veřejného blogu, detailu článku, vyhledávání, sitemapy, veřejných formulářů, chatu, kontaktu, stažení souboru a newsletterových potvrzovacích akcí, kde má stránka pokračovat ve vykreslení, ale log musí ukázat selhaný zdroj nebo sekci. Stejný bezpečný zápis používají i vybrané administrační přehledy, například media picker/content reference search, formuláře a statistiky, bez ukládání hledaných výrazů, obsahu zpráv nebo tokenů do kontextu logu. Sdílené helpery pro zámky obsahu, revize, widgety, použití médií, formulářové webhooky, e-mailové notifikace a souborové operace logují jen technický kontext typu operace, entity, zóny, interní tabulky, webhook eventu, hostu endpointu, HTTP stavu, domény příjemce, SMTP fáze nebo přípony souboru; celé webhook URL, tělo odpovědi protistrany, celé e-mailové adresy, surové SMTP odpovědi ani fyzické cesty k souborům se do logu neukládají.
- `health.php` kromě databáze, privátního úložiště a orientačního stavu záloh uvádí i čas poslední nalezené SQL zálohy a čerstvost posledního běhu cronu. Podporuje jen `GET` a `HEAD`; ostatní metody vrací `405` s `Allow: GET, HEAD`. Cron při každém běhu uloží `cron_last_run_at`; health check ho hlásí jako `ok`, `stale` nebo `unknown`, aniž by čerstvá instalace bez prvního cronu hned spadla do chyby. Monitoring odpověď dostává s `Cache-Control: no-store`, aby se nevyhodnocoval starý stav z cache.
- CSP se na veřejných odpovědích posílá i v režimu `Content-Security-Policy-Report-Only`. Prohlížeče tak mohou hlásit podezřelé nebo chybějící zdroje na `csp-report.php`, aniž by se návštěvníkovi rozbil legitimní obsah; běžné inline styly jsou v politice výslovně povolené přes `style-src-elem` a `style-src-attr` a starší inline-style reporty endpoint přijme bez zápisu do JSONL, aby log neplnil očekávaný šum z historických admin šablon. Endpoint přijímá jen `POST`, chybové JSON odpovědi doplňuje o `request_id`, neposílá cacheovatelný obsah, ukládá jen očištěné JSONL záznamy do privátního úložiště `logs/csp_reports-YYYY-MM-DD.jsonl`, má vlastní rate limit proti zahlcení logů a cron čistí report soubory starší než 30 dní.
- CSP allowlist výslovně obsahuje jen externí zdroje, které CMS samo vkládá pro Google Analytics a volitelný Quill editor. Externí GA/Quill skripty dostávají nonce a runtime audit hlídá, aby se nové CDN skripty v administraci nepřidávaly bez nonce.
- Veřejné theme CSS proměnné se vykreslují přes `<style>` blok s CSP nonce; runtime audit hlídá jak helper v `lib/theme.php`, tak reálný výstup homepage.
- Sdílené UI helpery v `lib/ui.php` používají pro cookie lištu, veřejný admin bar a klávesnicový fallback řazení CSS třídy a `hidden` místo lokálních `style` atributů nebo JS `element.style` mutací; veřejný admin bar je zároveň pojmenovaný skutečným skrytým nadpisem přes `aria-labelledby`. Runtime audit hlídá, aby se starý vzor nevrátil.
- Pomocné navigace v administraci, například dashboardové rychlé odkazy, sekce nastavení, filtry komentářů, kontaktních a chatových zpráv, odpovědí formulářů, newsletteru, fronty ke schválení a stránkování rezervací, používají heading-backed `aria-labelledby` místo samostatného `aria-label` na navigačním landmarku.
- Skupinové prvky mimo navigační landmarky, například jídelní taby, rezervační časové sloty, výsledky anket, veřejný chat a souhrny návštěvnosti, používají `aria-labelledby` napojené na skutečný nadpis nebo legendu místo samostatného `aria-label`.
- Živý náhled šablony používá text v banneru jako název stavové oblasti a veřejný rezervační kalendář má skrytý `<caption>`, takže i tyto výstupy mají skutečný textový popisek místo samostatného `aria-label`.
- Hromadné checkboxy `Vybrat vše` i jednotlivé řádkové checkboxy v administračních tabulkách používají skrytý `label` navázaný přes `for` / `id` místo samostatného `aria-label`; runtime audit hlídá zachování těchto popisků, jedinečné řádkové identifikátory i to, aby se starý aria-only vzor nevrátil.
- Ikonová a řadicí tlačítka v administraci používají viditelný text doplněný skrytým kontextem přímo uvnitř tlačítka: dialogová tlačítka zavření mají skrytý text `Zavřít dialog` a tlačítka `Nahoru` / `Dolů` v řazení přidávají název řazené položky bez samostatného `aria-label`.
- Dynamická tlačítka `Odebrat` ve formuláři anket a v editoru zdrojů rezervací mají kontext akce jako skrytý text uvnitř tlačítka, takže nově přidané možnosti, sloty i blokované dny zůstávají čitelné pro čtečky obrazovky bez samostatného `aria-label`.
- Editor zdrojů rezervací používá skutečné skryté `label` prvky i pro checkboxy zavřených dnů v otevírací době a pro existující i dynamicky přidané blokované dny; čtečky obrazovky tak dostanou stabilní název pole bez samostatného `aria-label`.
- Content/media picker v administraci načítá sdílený statický stylesheet `admin/assets/content-reference-picker.css` místo generovaného inline `<style>` helperu v `lib/ui.php`; runtime audit hlídá návrat lokálních stylů i zachování tříd pro dialog, překryv a výsledky vyhledávání.
- Veřejný layout default šablony používá pro fallback kopírování odkazu a obsahu do schránky sdílenou třídu `.clipboard-fallback-control` místo JS `element.style` mutací; runtime audit hlídá návrat inline stylování do layoutu.
- Tlačítka `Kopírovat odkaz` na veřejných detailových stránkách včetně detailu fotografie galerie používají viditelný text doplněný skrytým kontextem uvnitř tlačítka místo samostatného `aria-label`; layoutový clipboard skript si před potvrzením ukládá původní HTML a po oznámení `Zkopírováno!` ho obnoví, aby skrytý kontext zůstal zachovaný.
- Veřejné šablony načítají styly pro skip link, `.sr-only`, cookie lištu a veřejný admin bar ze sdíleného `assets/public-core.css` místo generovaného inline a11y `<style>` helperu.
- Samostatné systémové obrazovky `install.php`, `migrate.php` a `maintenance.php` načítají společný statický stylesheet `assets/standalone.css` místo lokálních inline `<style>` bloků; maintenance stránka má zároveň skip link na hlavní obsah.
- Nouzová chybová stránka načítá `assets/error.css` a antispamový honeypot používá sdílenou třídu `.honeypot-field` ve veřejném i administračním CSS, takže ani tyto pomocné výstupy nepotřebují lokální inline styly.
- Přihlašovací obrazovky administrace včetně 2FA načítají sdílený statický stylesheet `admin/assets/login.css` místo generovaného inline `<style>` helperu v `lib/ui.php`; runtime audit hlídá návrat lokálních stylů i zachování tříd pro skip link, focus stav, TOTP pole a sekundární akci.
- Administrace profilu používá pro sekce hesla, TOTP 2FA, veřejného autora, avatar a odesílací akci sdílené utility třídy a atribut `hidden` místo lokálních `style` atributů nebo JS `element.style` mutací; runtime audit hlídá, aby se starý vzor nevrátil.
- Správa vzhledu a šablon v administraci používá sdílené utility třídy pro katalog šablon, barevné náhledy, theme settings a import/export balíčků místo lokálního `<style>` bloku a inline `style` atributů; barevné tečky se vykreslují přes SVG `fill` bez inline CSS.
- Import portable ZIP balíčku šablony používá sdílenou upload validaci pro stav PHP uploadu, ověření dočasného souboru a prázdný soubor ještě před tím, než začne vlastní package kontrola manifestu, povolených statických assetů a velikostních limitů.
- Statistiky v administraci používají sdílené utility třídy pro filtr období, souhrnné karty a grafy místo lokálních `style` atributů; grafy se vykreslují přes sémantický `<progress>`, takže runtime audit hlídá, aby se nevrátily staré inline výšky sloupců.
- Dashboard administrace používá sdílené panely, souhrnné karty, metadata tabulek a sémantický `<progress>` pro mini graf návštěvnosti místo lokálních `style` atributů; runtime audit hlídá, aby se do přehledu nevrátily inline barvy, výšky nebo starý vizuální graf.
- JSON-LD strukturovaná data pro veřejné moduly se vykreslují přes sdílený helper s CSP nonce. Runtime audit hlídá, aby se structured-data výstupy nevracely k surovým non-nonced `<script type="application/ld+json">` blokům.
- Veřejná default šablona obsluhuje potvrzení akcí přes `data-confirm` a tisk přes `js-print-page` v nonce skriptu layoutu. Runtime audit zároveň hlídá, aby se do veřejných view souborů nevracely inline `onclick` handlery.
- Jednoduché destruktivní formuláře v administraci používají `data-confirm` i na úrovni formuláře; globální nonce skript poslouchá událost `submit`, takže potvrzení zůstává funkční i při klávesnicovém odeslání a nemusí se psát jako inline `onsubmit`.
- Dlouho běžící administrační formuláře používají `data-submit-once`, aby se po odeslání změnil text tlačítka a zabránilo se opakovanému kliknutí bez inline `onclick` handlerů.
- Editor anket v administraci používá pro přidávání a odebírání možností odpovědi datové atributy a delegovaný listener v nonce skriptu formuláře, ne inline `onclick` handlery.
- Editor formulářů v administraci používá sdílené admin CSS třídy pro základní nastavení, potvrzovací e-mail, webhooky, editor polí a náhled potvrzení místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do form builderu nevracely. Checkboxy v části pro přidání nového pole používají přímo svůj viditelný label a kontext skupiny z legendy; checkboxy existujících polí přidávají kontext konkrétního pole skrytým textem uvnitř stejného labelu, aby se přístupný název nelišil od textu, který vidí uživatel.
- Formulář anket v administraci používá sdílené utility třídy pro časová pole, editor možností, SEO sekci, akční odkazy a výsledky ankety místo lokálních `style` atributů. Výsledky jsou vykreslené nativním `<progress>`, takže dynamická šířka proužku už není uložená v inline CSS.
- Veřejné výsledky anket v default šabloně používají nativní `<progress>` s čitelným názvem pro čtečky obrazovky místo vnořeného prvku s dynamickým inline `width`; runtime audit hlídá, aby se starý CSS-only proužek nevrátil.
- Nastavení webu v administraci používá sdílené admin CSS třídy pro navigaci sekcí, profil webu, komentáře, notifikace, vlastní kód, náhled loga a favicon i akční tlačítko místo lokálního `<style>` bloku a lokálních `style` atributů; runtime audit hlídá návrat starého vzoru.
- Rezervační formuláře v administraci používají datové atributy a nonce skripty pro přepínání polí, práci se sloty a blokovanými dny. Runtime audit zároveň hlídá, aby se do admin PHP souborů nevracely inline `onclick`, `onchange`, `onsubmit` ani `oninput` atributy.
- WordPress/eStránky importy v administraci používají sdílené panelové a formulářové utility pro výsledky, náhled importu, informační boxy, výběr cílového blogu a downloader fotografií místo lokálních `style` atributů; runtime audit hlídá, aby se tyto styly do importních nástrojů nevracely.
- Záloha databáze a Koš v administraci používají sdílené utility třídy pro popisy a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto obrazovek nevracely.
- Přehled vývěsky a FAQ v administraci používá sdílené utility třídy pro rychlé odkazy, filtry, metadata, malé štítky a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto přehledů nevracely.
- Přehledy novinek, událostí a míst v administraci používají sdílené utility třídy pro filtry, vyhledávací pole, metadata v tabulkách a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto přehledů nevracely.
- Přehledy stažení a jídelních lístků v administraci používají sdílené utility třídy pro filtry, rychlé odkazy, skupinové nadpisy, metadata a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto přehledů nevracely.
- Přehledy podcastů a epizod v administraci používají sdílené utility třídy pro filtry, vyhledávací pole, metadata v tabulkách, pager a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto přehledů nevracely.
- Přehled alb a fotografií galerie v administraci používá sdílené utility třídy pro filtry, hromadné akce, cesty v tabulkách, náhledy fotografií a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto přehledů nevracely.
- Přehled uživatelů a pozice modulů v administraci používají sdílené utility třídy pro autorské štítky, kompaktní seznamy blogů a řadicí seznam modulů místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto menších přehledů nevracely.
- Detaily kontaktních a chatových zpráv v administraci používají sdílenou třídu pro zachování řádků dlouhého textu místo lokálního `style` atributu; runtime audit hlídá, aby se čistě prezentační inline styly do těchto detailů nevracely.
- Import, historie newsletteru a 2FA přihlášení v administraci používají CSS třídy pro odsazení akcí, zalomení dlouhého obsahu newsletteru a vzhled 2FA kódu místo lokálních `style` atributů; runtime audit hlídá, aby se tyto drobné prezentační styly nevracely přímo do HTML atributů.
- Kategorie vývěsky, FAQ a stažení v administraci používají sdílené utility třídy pro přidání, inline editaci a mazání kategorií místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto menších taxonomií nevracely.
- Kategorie a lokality rezervací v administraci používají sdílené utility třídy pro filtry, přidávací formuláře, inline editaci a mazání místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto rezervačních číselníků nevracely.
- Přehled rezervací, ruční vytvoření rezervace, detail rezervace a editor zdrojů rezervací v administraci používají sdílené admin CSS třídy a atribut `hidden` pro přepínané části formulářů místo lokálního `<style>` bloku, lokálních `style` atributů nebo JS `element.style` mutací; runtime audit hlídá návrat starého vzoru i u dynamicky přidávaných slotů a blokovaných dnů.
- Knihovna médií v administraci používá sdílené admin CSS třídy pro upload, filtry, grid médií, hromadné akce, detail metadat a přehled použití místo lokálních `style` atributů; runtime audit hlídá návrat starého vzoru.
- Kategorie a štítky blogu v administraci používají sdílené utility třídy pro výběr blogu, inline editaci taxonomií, tlačítka a mazací formuláře místo lokálních `style` atributů. Stejný směr drží i sdílený helper hromadných akcí, který používá `admin-fieldset-card` a `field-help--flush`.
- Pořadí stránek a odkazů blogu v administraci používá sdílené utility třídy pro popis, řadicí seznam, stavové poznámky, formulář externího odkazu a tlačítkový řádek místo lokálních `style` atributů. Stav přetahování se přepíná CSS třídou a runtime audit hlídá, aby se inline styly do obrazovky nevracely.
- Přehledy chatu, kontaktních zpráv a komentářů v administraci používají sdílené utility třídy pro filtry, hledání, hromadné akce, stavové poznámky a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto přehledů nevracely.
- Audit log v administraci používá sdílené utility třídy pro popis stránky, filtry a zalomení dlouhých detailů akce místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do přehledu nevracely.
- Kontrola integrity v administraci používá sdílené administrační CSS třídy pro stavové panely, výsledky kontroly, akční řádek a informační blok místo lokálního `<style>` bloku nebo `style` atributů; runtime audit hlídá návrat obou starých vzorů.
- Přehled článků blogu v administraci používá sdílené utility třídy pro filtry, hromadné akce, náhledové tlačítko a poznámku pod tabulkou místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do přehledu nevracely.
- Editor článku blogu v administraci používá sdílené utility třídy pro zámek obsahu, převod na stránku, informace o autorovi, taxonomie, plánování publikace, SEO, interní poznámku, stav článku, akční řádek a WYSIWYG wrapper místo lokálních `style` atributů nebo JS `element.style` mutací; runtime audit hlídá, aby se čistě prezentační inline styly do formuláře nevracely.
- Přehled newsletteru v administraci používá sdílené utility třídy pro rozložení, fieldset, poznámky a stav čekajícího odběratele místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto přehledu nevracely.
- Přehled formulářů, správa přesměrování a compose obrazovka newsletteru používají sdílené utility třídy pro filtry, metadata, inline editaci a pomocné texty místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto obrazovek nevracely.
- Detail odpovědi formuláře v administraci používá sdílené administrační CSS třídy pro workflow pole, GitHub issue návrh, odpověď odesílateli, historii a mazací formulář místo lokálního `<style>` bloku nebo `style` atributů; runtime audit hlídá návrat obou starých vzorů.
- Správa modulů a tým blogu v administraci používají sdílené utility třídy pro checkbox labely, výběr blogu, odsazené fieldsety, akční řádky a kompaktní seznamy dalších blogů místo lokálních `style` atributů; runtime audit hlídá, aby se tyto prezentační styly nevracely.
- Historie revizí, přehled zdrojů rezervací a editor alba galerie používají sdílené utility třídy pro diff, filtry, metadata a akční řádky místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto obrazovek nevracely.
- Přehled statických stránek a odpovědí formulářů v administraci používá sdílené utility třídy pro filtry, hromadné akce, metadata a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto přehledů nevracely.
- Navigace webu a přehled anket v administraci používají sdílené utility třídy pro řadicí seznam včetně stavu přetahování, popisy, filtry, metadata a stavové štítky místo lokálních `style` atributů nebo JS `element.style` mutací; runtime audit hlídá, aby se čistě prezentační inline styly do těchto obrazovek nevracely.
- Přesun článků mezi blogy v administraci používá sdílené utility třídy pro cílový blog, přehled vybraných článků, mapování kategorií a štítků a akční řádky místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do této obrazovky nevracely.
- Editor FAQ v administraci používá sdílené utility třídy pro popis formuláře, kompaktní SEO textareu, WYSIWYG odpověď, zveřejnění a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor jídelních a nápojových lístků v administraci používá sdílené utility třídy pro popis formuláře, datumová pole, zveřejnění, aktuálnost, WYSIWYG rám a akční odkazy místo lokálních `style` atributů nebo JS `style` mutací; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor položek ke stažení v administraci používá sdílené utility třídy pro popis formuláře, WYSIWYG popis, checkboxy, náhled obrázku, stav publikace a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor vývěsky v administraci používá sdílené utility třídy pro upozornění na zámek obsahu, popisy, WYSIWYG detail, plánované publikování, checkboxy, náhled obrázku a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor událostí v administraci používá sdílené utility třídy pro upozornění na zámek obsahu, termín konání, WYSIWYG popis, checkboxy, náhled obrázku, stav publikace a akční odkazy místo lokálních `style` atributů nebo JS `style` mutací; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor novinek v administraci používá sdílené utility třídy pro upozornění na zámek obsahu, informaci o autorovi, plánované publikování, interní poznámku, SEO sekci a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor statických stránek v administraci používá sdílené utility třídy pro upozornění na zámek obsahu, převod stránky na článek, popis formuláře, checkboxy, plánované publikování, interní poznámku, WYSIWYG rám a akční odkazy místo lokálních `style` atributů nebo JS `style` mutací; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor fotografií galerie v administraci používá sdílené utility třídy pro náhled fotografie, viditelnost, pomocný text hromadného uploadu a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor zajímavých míst v administraci používá sdílené utility třídy pro popis formuláře, WYSIWYG detail, souřadnice, náhled obrázku, checkboxy, stav publikace a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor uživatelských účtů v administraci používá sdílené utility třídy pro heslo, veřejného autora, avatar, checkboxy, veřejný profil a akční odkazy místo lokálních `style` atributů nebo JS `style` mutací; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editory podcastového pořadu a epizody v administraci používají sdílené utility třídy pro popisy formulářů, gridy metadat, checkboxy, WYSIWYG popis, náhledy artworku, stav publikace a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto formulářů nevracely.
- Editor newsletterové rozesílky v administraci používá sdílené utility třídy pro WYSIWYG wrapper místo JS `style` mutací; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Fronta ke schválení v administraci používá sdílené utility třídy pro filtrační navigaci, rychlý přehled, souhrnné karty, inline akční formuláře a náhled komentáře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do této obrazovky nevracely.
- Společný layout administrace používá statický stylesheet `admin/assets/layout.css` pro navigaci, informaci o přihlášeném uživateli, autosave banner, počítadlo editoru, SEO náhled, patičku a sdílené utility třídy místo velkého generovaného inline `<style>` bloku v `admin/layout.php`. Runtime audit hlídá link i návrat starých inline fragmentů, aby se CSP postupně méně opírala o historický inline fallback.
- Hlavní administrativní navigace ve společném layoutu je pojmenovaná skutečným nadpisem `Administrace` přes `aria-labelledby` a klientský live region už nekopíruje ani nemaže serverem vyrenderované stavové nebo chybové hlášky.
- `robots.txt` se generuje přes `robots.php`, podporuje jen `GET` a `HEAD`, zakazuje indexaci administrace a citlivých upload adresářů a odkazuje na aktuální sitemapu. Stejné čtecí omezení metod používají také XML sitemapa, globální, blogové i podcastové RSS feedy, ICS export událostí, veřejné souborové/media endpointy a read-only administrační endpointy včetně JSON/CSV výstupů, příloh formulářů a vyhledávání obsahu pro media picker. Discovery výstupy jako `robots.txt`, sitemapa, RSS feedy a ICS posílají `X-Content-Type-Options: nosniff`, aby je prohlížeč neinterpretoval mimo deklarovaný textový, XML, RSS nebo kalendářový typ; u souborů `HEAD` posílá jen hlavičky, bez těla souboru. Canonical URL v SEO metadatech přijímají jen bezpečné interní cesty nebo platné `http://` / `https://` adresy bez přihlašovacích údajů, řídicích znaků a protocol-relative tvaru.
- Interní administrační JSON akce, které mění stav přes AJAX, jsou POST-only. Při jiné metodě vrací `405` s `Allow: POST` a odpovědi posílají `Cache-Control: no-store` a `X-Content-Type-Options: nosniff`, aby se v administraci necachoval zastaralý stav.
- Session vrstva používá strict mode, cookies-only režim a vypnuté session ID v URL. Přihlašovací flow regeneruje session ID a runtime audit hlídá i bezpečné cookie atributy `HttpOnly` a `SameSite=Strict` včetně sladěného mazání cookie při odhlášení, aby se snížilo riziko session fixation.
- Běžné administrační HTML odpovědi včetně loginu, 2FA a potvrzení migrace posílají `Cache-Control: no-store, max-age=0`, `Pragma: no-cache`, `Expires: 0` a `X-Robots-Tag: noindex, nofollow, noarchive`, aby se citlivé administrační obrazovky zbytečně nevracely z cache po odhlášení nebo na sdíleném počítači a aby se neměly indexovat.
- Odhlášení posílá `Clear-Site-Data: "cache"`, takže prohlížeč po ukončení session zahodí cache webu. CMS záměrně nemaže storage ani všechny cookies, aby se neztratily neuložené autosave koncepty nebo cookie preference; session cookie se maže cíleně.
- Veřejné i administrační odpovědi posílají `Permissions-Policy`, `Cross-Origin-Opener-Policy: same-origin`, `Origin-Agent-Cluster: ?1`, `X-XSS-Protection: 0`, `X-Download-Options: noopen` a `X-Permitted-Cross-Domain-Policies: none`, takže CMS zakazuje nepoužívaná prohlížečová API, izoluje top-level okna i runtime podle originu, vypíná zastaralý XSS auditor ve starších prohlížečích, omezuje otevírání stažených souborů v kontextu webu a odmítá staré cross-domain policy soubory. CMS záměrně neblokuje clipboard, fullscreen ani legitimní externí iframe/audio/video embedy, proto nezavádí COEP/CORP.
- Administrační stažení citlivějších exportů, například JSON export CMS, CSV export odpovědí formulářů, přílohy formulářových odpovědí, SQL záloha databáze, ZIP export galerie nebo ZIP export šablony, posílají `Cache-Control: no-store` a `X-Content-Type-Options: nosniff`, aby se exporty zbytečně necachovaly a prohlížeč je neinterpretoval mimo deklarovaný typ.
- Ruční SQL záloha v administraci a automatická denní záloha z cronu používají stejný exportní helper. Ten exportuje jen CMS tabulky s bezpečným názvem, ověřuje výsledek `SHOW CREATE TABLE` a čte data explicitně jako asociativní řádky, aby výstup nebyl závislý na globálním nastavení PDO.
- `php build/runtime_audit.php` ověřuje runtime guardrails včetně release ZIP pravidel, rate limitingu a přístupnosti; u veřejných vyhledávacích formulářů, filtračních navigací, drobečkové navigace, stránkování, obsahových embed bloků a dalších pomocných navigací hlídá i skutečné nadpisy napojené přes `aria-labelledby`
- `php build/http_integration.php` ověřuje důležité HTTP scénáře

---

## Kompletní seznam widgetů

Widgety lze přidávat do tří zón: `homepage`, `sidebar`, `footer`.

| Widget | Popis |
|--------|-------|
| Úvodní text | Hlavní textový blok pro domovskou stránku s podporou HTML a snippetů |
| Nejnovější články | Články z vybraného blogu; pod odkazem zobrazuje datum publikace, přibližnou dobu čtení a počet přečtení |
| Novinky | Poslední novinky |
| Doporučený obsah | Výběr z blogu, vývěsky, ankety nebo newsletteru |
| Vývěska | Poslední oznámení |
| Nadcházející události | Nejbližší plánované akce |
| Anketa | Aktivní hlasování |
| Newsletter | Inline formulář pro přihlášení k odběru novinek |
| Ke stažení | Poslední položky ke stažení |
| FAQ | Často kladené otázky |
| Zajímavá místa | Výběr z turistického adresáře |
| Nejnovější epizody podcastu | Poslední epizody |
| Vybraný formulář | Konkrétní veřejný formulář (jen pokud existuje alespoň jeden aktivní) |
| Náhled galerie | Výběr posledních veřejných fotografií jako responzivní náhledový grid |
| Vyhledávání | Vyhledávací pole a tlačítko `Hledat` |
| Kontaktní údaje | Adresa, telefon, e-mail |
| Sociální sítě | Odkazy na vyplněné profily Facebook / YouTube / Instagram / X |
| Statistiky návštěvnosti | Přehled `Online / Dnes / Měsíc / Celkem` |
| Vlastní HTML | Libovolný HTML kód |

Widgety respektují stav modulů – vypnutý modul se v nabídce widgetů nezobrazuje.

Veřejné widgety v sidebaru a footeru používají skutečné viditelné nadpisy jako název oblasti. Náhled galerie se zároveň styluje přes šablonové CSS třídy, takže se do HTML nevkládají inline layout styly. Widget `Sociální sítě` u odkazů otevíraných v novém okně doplňuje bezpečné `rel="noopener noreferrer"` a přístupný název přes sdílený helper s informací, že se odkaz otevře v novém okně.

Dialog `Nastavení` u widgetu nově používá skutečné `fieldset` a `legend` pro základní i typově specifická nastavení. Skrytá pole se při změně typu widgetu zároveň deaktivují, takže se nedostanou ani do tab orderu, ani do odeslaného formuláře. Prezentační styly přehledu widgetů, dialogu a drag stavu jsou uložené ve sdílené admin CSS vrstvě, ne v lokálním `<style>` bloku ani v lokálních `style` atributech.

Zároveň ale platí, že i aktivní widget se může dočasně nevykreslit. Typické důvody:

- vypnutý modul
- vypnuté sledování návštěvnosti u widgetu Statistiky návštěvnosti
- chybějící obsah, například žádné veřejné články, fotky nebo události
- prázdná konfigurace, například žádné odkazy v widgetu Sociální sítě
- neplatná vazba na formulář, album, pořad nebo blog

Správa widgetů tyto stavy nově ukazuje přímo v přehledu textem `Na webu se teď nezobrazí: ...`, takže správce nemusí zkoušet metodou pokus–omyl, proč je blok aktivní, ale na webu se neukazuje.

Praktická poznámka k footeru:

- odkazy na sociální sítě už se nenastavují v `Obecných nastaveních`, ale přímo ve widgetu `Sociální sítě`; odkazy se otevírají bezpečně v novém okně a čtečkám obrazovky tuto skutečnost oznamují
- odkaz `Vyhledávání` je nahrazen widgetem s vlastním hledacím polem
- odkaz `Odběr novinek` je nahrazen widgetem s inline formulářem pro přihlášení k newsletteru
- veřejné statistiky návštěvnosti se už nezapínají samostatným checkboxem v `Správě modulů`; zobrazí se jen při aktivním modulu Statistiky, zapnutém sledování návštěvnosti a aktivním widgetu

---

## Content picker – podrobnosti

Content picker je přístupný dialog v HTML editoru, který umožňuje:

- Vyhledat existující články, stránky a další veřejný obsah
- Vložit interní odkaz na nalezený obsah
- Vložit hotový HTML blok
- Vložit galerii nebo fotografii podle typu obsahu
- Vložit veřejný formulář nebo anketu jako živý embed
- Vložit obsahovou kartu pro download, podcast, epizodu, místo, událost nebo oznámení
- Vložit přímý odkaz ke stažení
- Vložit audio/video přehrávač přes snippety nebo přímé akce
- Vložit PDF náhled z veřejného PDF v knihovně médií

Podporované obsahové snippety v HTML editoru:

- `[audio]...[/audio]`
- `[video]...[/video]` pro přímé video soubory i běžné YouTube URL
- `[pdf]...[/pdf]`
- `[code]...[/code]`
- `[gallery]slug-alba[/gallery]`
- `[form]slug-formulare[/form]`
- `[poll]slug-ankety[/poll]`
- `[download]slug-polozky[/download]`
- `[podcast]slug-poradu[/podcast]`
- `[podcast_episode]slug-poradu/slug-epizody[/podcast_episode]`
- `[place]slug-mista[/place]`
- `[event]slug-udalosti[/event]`
- `[board]slug-oznameni[/board]`

Praktické poznámky:

- U veřejného PDF z knihovny médií nabízí picker akci `Vložit PDF náhled`, která vloží robustní shortcode navázaný na konkrétní médium.
- PDF snippet vykreslí inline náhled dokumentu přes interní same-origin preview endpoint a pod ním ponechá i odkaz `Otevřít PDF samostatně`.
- Starší PDF snippety, které už mají v obsahu jen cestu `/uploads/media/...`, fungují zpětně bez ruční úpravy.
- YouTube URL ve video snippetu se převádí na vložený `youtube-nocookie.com` přehrávač a zachová i čas začátku z parametrů `t` nebo `start`.
- URL ve snippetech pro audio, video a PDF musí být buď úplná `http://` / `https://` adresa bez přihlašovacích údajů, nebo interní absolutní cesta začínající jedním lomítkem, například `/uploads/media/soubor.pdf`. Protocol-relative adresy `//example.com/...` se z bezpečnostních důvodů odmítají.
- Shortcode `[code]...[/code]` je určený pro kopírovatelný obsah, například příkazy, konfiguraci, kód nebo jiné krátké texty; na veřejném webu zobrazí blok s tlačítkem `Kopírovat do schránky`.
- Obsahové karty a vložené bloky ze snippetů mají skrytý nadpis napojený přes `aria-labelledby`, takže je uživatel čtečky obrazovky najde i navigací po nadpisech.
- Při vložení obrázku z knihovny médií picker zachová `alt` atribut, ale nevkládá automatický `figcaption` z názvu média. Pokud médium nemá vyplněný alternativní text, vloží se `alt=""`, který lze v editoru ručně upravit.
- Externí iframe a externí audio/video embedy ve veřejném HTML obsahu jsou podporované přes CSP, pokud je cílový zdroj sám dovolí.
- Dialog content/media pickeru používá pro otevření a zavření atribut `hidden`, sdílené CSS třídy a `admin-modal-open` na těle stránky. Nevkládá lokální `style` atributy ani nemění `element.style`, takže méně zatěžuje CSP reporty a drží konzistentní fokusové chování.

---

## Ankety – plánování, SEO a veřejný výpis

Modul ankety je určený pro jednoduché hlasování s okamžitě veřejnými výsledky po odeslání hlasu. Tato vlna dotažení sjednotila veřejnou viditelnost modulu s ostatními částmi CMS, doplnila revize a přidala i základní SEO workflow.

### Co se nastavuje u ankety

Každá anketa může mít:

- otázku a slug
- volitelný popis
- stav `Aktivní` nebo `Uzavřená`
- časové okno `Začátek` a `Konec`
- 2 až 10 odpovědí
- volitelný `meta title`
- volitelný `meta description`

Pokud SEO pole nevyplníte, veřejný detail použije fallback z otázky a krátkého shrnutí.

### Jak funguje veřejná viditelnost

- Veřejný index nově používá stejný helper jako widget ankety, vyhledávání a sitemapu.
- Aktivní anketa se zobrazuje jen v době, kdy je opravdu otevřená pro hlasování.
- Archiv zahrnuje ukončené ankety a ankety uzavřené ručně.
- Pokud změníte slug ankety, stará veřejná adresa se uloží jako redirect na nový canonical tvar.

### Co je nové v administraci

- Přehled anket nově umí fulltext `q`, filtr podle stavu a stránkování.
- Po uložení, smazání nebo hromadné akci se návrat drží ve stejném filtru a na stejné stránce přehledu.
- Editor nově obsahuje SEO pole `Meta titulek` a `Meta popis`.
- Revize zachycují otázku, slug, popis, stav, termíny, možnosti i SEO metadata.

### Veřejný výpis a detail

Veřejná stránka anket nově podporuje:

- přepínání `Aktivní ankety / Archiv`
- fulltextové hledání v otázce a popisu
- stránkování i při aktivním dotazu
- zachování filtru a vyhledávacího dotazu při listování

Detail ankety dál zachovává stejné hlasovací chování:

- po hlasování se hned ukážou výsledky
- neexportují se jednotlivé hlasy
- ochrana proti opakovanému hlasování dál stojí na stávajícím IP hash modelu

### Export a import

Export/import anket přenáší:

- samotnou anketu
- její možnosti odpovědí
- stav a časové okno
- SEO pole

Nepřenáší se:

- jednotlivé hlasy
- agregované výsledky hlasování

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že ankety podporují plánování, veřejné hledání, slug URL, SEO fallbacky a revize.
- Tento dokument popisuje konkrétní redakční workflow, chování veřejné viditelnosti, časování a pravidla exportu/importu.

---

## Knihovna médií – veřejné a soukromé soubory

Knihovna médií už není jen jednoduchý seznam uploadů. Nově funguje jako bezpečnější centrální správa souborů pro editor, content picker i interní staff workflow.

### Co se u média nově spravuje

Každé médium může mít:

- `alt text`
- `caption`
- `credit`
- viditelnost `Veřejné / Soukromé`

Veřejná média dál mohou používat veřejné stránky a content picker. Soukromá média jsou určená pro interní workflow a nepůjčují se do veřejného pickeru.

### Bezpečnostní pravidla

- Nové SVG uploady jsou zakázané.
- Starší SVG soubory zůstávají v knihovně, ale nechovají se jako obrázkové preview assety.
- Soukromé soubory a SVG se servírují přes kontrolované endpointy `media/file.php` a `media/thumb.php`.
- Přímé `/uploads/media/...` odkazy zůstávají jen pro veřejná ne-SVG média.

### Mazání a kontrola použití

Před smazáním knihovna nově kontroluje, jestli se médium používá:

- ve strukturovaných modulech
- v HTML obsahu
- ve vložených odkazech a známých embed patternách

Pokud je médium použité, smazání je zablokované a administrace ukáže nalezená místa použití.

### Co je nové v administraci

- seznam médií má filtry podle typu, viditelnosti, uploadera a stavu použití
- fulltext prochází jméno souboru, `alt text`, `caption` i `credit`
- po uploadu, úpravě, náhradě souboru i mazání se používá PRG návrat, takže refresh neopakuje POST
- médium lze nahradit novým souborem ve stejné MIME rodině bez rozbití existujících referencí
- bulk akce umí přepnout na `Veřejné`, `Soukromé` a smazat nepoužitá média

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že knihovna médií podporuje `public/private`, canonical media helpery, blokaci mazání používaných souborů a náhradu souboru.
- Tento dokument popisuje konkrétní redakční workflow, bezpečnostní pravidla a chování správy médií v administraci.
