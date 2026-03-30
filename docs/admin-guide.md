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

## Blog RSS feedy

Kora CMS poskytuje globální RSS feed i samostatné feedy jednotlivých blogů.

- Globální feed: `feed.php`
- Feed konkrétního blogu: `feed.php?blog=slug-blogu`
- Blogový feed používá vlastní název, popis a self odkaz.
- V blogovém feedu jsou jen články daného blogu.

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
