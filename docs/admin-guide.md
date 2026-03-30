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

## Multiblog – týmy, metadata a veřejný výstup

Multiblog je určený pro situace, kdy má jeden web více samostatných blogů s vlastní identitou, RSS feedem a týmem autorů.

### Co patří do README a co sem

- [README.md](../README.md) popisuje, že Kora CMS multiblog umí a jak zapadá do celého systému.
- Tento dokument popisuje, jak se multiblog skutečně spravuje v administraci a co jednotlivé volby dělají.

### Správa blogu

U každého blogu lze nastavit:

- název, slug a krátký popis
- volitelné logo blogu
- rozšířený úvod blogu nad výpisem článků
- `meta title` a `meta description`
- RSS podtitulek
- výchozí stav komentářů pro nové články
- počet položek v RSS feedu daného blogu
- zobrazení blogu v hlavní navigaci

Pokud blog změní slug, Kora CMS si uloží starý slug jako redirect a staré URL i RSS feed se přesměrují na nový tvar.

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

To je užitečné hlavně ve chvíli, kdy jeden autor spravuje více blogů nebo když chcete rychle zkontrolovat, zda má nový redaktor přístup opravdu jen tam, kam patří.

### Články v rámci blogu

Editor článku respektuje vybraný blog:

- nabídne jen kategorie a štítky tohoto blogu
- horní odkazy vedou na správný veřejný blog, jeho RSS feed a správu taxonomií
- nové články mohou převzít výchozí komentáře z blogu
- jeden článek v blogu lze označit jako `Doporučený článek blogu`

Na veřejném indexu blogu se pak bez aktivních filtrů zobrazí právě jeden doporučený článek.

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
| Newsletter | Formulář pro přihlášení k odběru |
| Ke stažení | Poslední položky ke stažení |
| FAQ | Často kladené otázky |
| Zajímavá místa | Výběr z turistického adresáře |
| Nejnovější epizody podcastu | Poslední epizody |
| Vybraný formulář | Konkrétní veřejný formulář (jen pokud existuje alespoň jeden aktivní) |
| Nápověda galerie | Odkaz na galerii |
| Vyhledávání | Vyhledávací pole |
| Kontaktní údaje | Adresa, telefon, e-mail |
| Vlastní HTML | Libovolný HTML kód |

Widgety respektují stav modulů – vypnutý modul se v nabídce widgetů nezobrazuje.

---

## Content picker – podrobnosti

Content picker je přístupný dialog v HTML editoru, který umožňuje:

- Vyhledat existující články, stránky a další veřejný obsah
- Vložit interní odkaz na nalezený obsah
- Vložit hotový HTML blok
- Vložit galerii nebo fotografii podle typu obsahu
- Vložit přímý odkaz ke stažení
- Vložit audio/video přehrávač přes snippety nebo přímé akce
