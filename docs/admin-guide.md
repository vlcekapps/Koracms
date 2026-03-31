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

### Hotové šablony formulářů

- Nahlášení chyby
- Návrh nové funkce
- Žádost o podporu
- Obecný kontaktní formulář
- Nahlášení problému s obsahem

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

To je důležité hlavně po ručních úpravách nebo importech, kdy se nejčastěji objeví překlep v datu, obrácený rozsah nebo neplatný čas.

---

## Import / Export dat a UTF-8

Obrazovka **Import / Export** pracuje s JSON soubory v UTF-8.

Platí tato pravidla:

- export z Kora CMS zapisuje JSON s českou diakritikou v UTF-8
- import nově odmítne JSON soubor s neplatným UTF-8, aby se poškozené texty neuložily do databáze
- UTF-8 BOM na začátku souboru nevadí; import si ho automaticky odstraní
- ruční SQL restore mimo administraci musí používat klientské spojení `utf8mb4`, jinak se české znaky mohou změnit na `?`

Prakticky to znamená, že běžný export a následný import přes administraci je bezpečný i pro české texty. Největší riziko poškození diakritiky vzniká až při ručním importu SQL dumpu přes nástroj nebo klienta, který nepoužívá `utf8mb4`.

---

## Nastavení webu – logo a favicona

V sekci **Obecná nastavení** lze dál nahrávat logo a faviconu webu, ale branding uploady mají nově přísnější ochranu.

Platí tato pravidla:

- nové SVG soubory se pro logo ani faviconu nepřijímají
- favicona musí respektovat backend limit `256 KB`
- logo musí respektovat backend limit `2 MB`
- při chybě uploadu nebo neplatném dočasném souboru se formulář vrátí s čitelnou chybovou hláškou
- ukládání běží přes PRG workflow, takže refresh po uložení neopakuje POST
- validovaná pole nově vracejí i lokální field-level chyby s `aria-invalid` a zachováním zadaných hodnot po redirectu

To snižuje riziko, že se do veřejně servírovaných assetů dostane aktivní obsah nebo nepřiměřeně velký soubor.

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
- **Správa blogů** zároveň nově nabízí rychlé odkazy `Články blogu`, `Kategorie blogu`, `Štítky blogu` a `Stránky blogu`, takže lze z jednoho přehledu přejít rovnou na správu konkrétního blogu

To je užitečné hlavně ve chvíli, kdy jeden autor spravuje více blogů nebo když chcete rychle zkontrolovat, zda má nový redaktor přístup opravdu jen tam, kam patří.

### Články v rámci blogu

Editor článku respektuje vybraný blog:

- nabídne jen kategorie a štítky tohoto blogu
- horní odkazy vedou na správný veřejný blog, jeho RSS feed a správu taxonomií
- nové články mohou převzít výchozí komentáře z blogu
- jeden článek v blogu lze označit jako `Doporučený článek blogu`

Na veřejném indexu blogu se pak bez aktivních filtrů zobrazí právě jeden doporučený článek.

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

## Kompletní seznam widgetů

Widgety lze přidávat do tří zón: `homepage`, `sidebar`, `footer`.

| Widget | Popis |
|--------|-------|
| Úvodní text | Hlavní textový blok |
| Nejnovější články | Články z vybraného blogu |
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
| Náhled galerie | Výběr posledních veřejných fotografií |
| Vyhledávání | Vyhledávací pole a tlačítko `Hledat` |
| Kontaktní údaje | Adresa, telefon, e-mail |
| Sociální sítě | Odkazy na vyplněné profily Facebook / YouTube / Instagram / X |
| Statistiky návštěvnosti | Přehled `Online / Dnes / Měsíc / Celkem` |
| Vlastní HTML | Libovolný HTML kód |

Widgety respektují stav modulů – vypnutý modul se v nabídce widgetů nezobrazuje.

Zároveň ale platí, že i aktivní widget se může dočasně nevykreslit. Typické důvody:

- vypnutý modul
- vypnuté sledování návštěvnosti u widgetu Statistiky návštěvnosti
- chybějící obsah, například žádné veřejné články, fotky nebo události
- prázdná konfigurace, například žádné odkazy v widgetu Sociální sítě
- neplatná vazba na formulář, album, pořad nebo blog

Správa widgetů tyto stavy nově ukazuje přímo v přehledu textem `Na webu se teď nezobrazí: ...`, takže správce nemusí zkoušet metodou pokus–omyl, proč je blok aktivní, ale na webu se neukazuje.

Praktická poznámka k footeru:

- odkazy na sociální sítě už se nenastavují v `Obecných nastaveních`, ale přímo ve widgetu `Sociální sítě`
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
- `[video]...[/video]`
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
- Shortcode `[code]...[/code]` je určený pro kopírovatelný obsah, například příkazy, konfiguraci, kód nebo jiné krátké texty; na veřejném webu zobrazí blok s tlačítkem `Kopírovat do schránky`.
- Při vložení obrázku z knihovny médií picker zachová `alt` atribut, ale nevkládá automatický `figcaption` z názvu média. Pokud médium nemá vyplněný alternativní text, vloží se `alt=""`, který lze v editoru ručně upravit.
- Externí iframe a externí audio/video embedy ve veřejném HTML obsahu jsou podporované přes CSP, pokud je cílový zdroj sám dovolí.

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
