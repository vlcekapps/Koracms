# Podrobný průvodce administrací – Kora CMS

Tento dokument doplňuje [README.md](../README.md) o podrobnější informace k jednotlivým modulům a funkcím. README obsahuje vše potřebné pro instalaci, konfiguraci a provoz. Tento soubor je určený pro administrátory, kteří chtějí detailnější přehled o možnostech CMS.

Nový modul, který má v manifestu `admin_paths` a `admin_capability`, dostane automaticky základní zkratku v command centru a v případě chybějícího ručního zařazení také fallback položku v administrační navigaci v sekci `Další moduly`.

Veřejné PHP endpointy modulu se zapisují do `public_paths`. Modulový audit prochází i top-level PHP soubory ve veřejné složce modulu, takže nový detail, feed, souborový endpoint nebo formulář nezůstane mimo manifest, modulový guard a statickou kontrolu.

Vývoj nových modulů má vlastní návrhovou šablonu v [docs/module-proposal-template.md](module-proposal-template.md) a checklist v [docs/developer-modules.md](developer-modules.md). Ty shrnují povinné kroky pro schéma, migrace, administraci, veřejné routy, bezpečnost, WCAG 2.2, testy a dokumentaci před tím, než se začne psát nový modul. Modulová metadata se zapisují do centrálního manifestu `coreModuleDefinitions()`, ze kterého se odvozuje instalace, migrace, administrace modulů, veřejná navigace, widgetové popisky, modulové typy content pickeru, typy výsledků veřejného vyhledávání přes `search_result_types`, sekce XML sitemapu přes `sitemap_sections`, základní zkratka admin command centra přes `admin_paths` a `admin_capability` i obsahové trendy statistik přes `stats_page_types`; modulový audit zároveň hlídá platné defaulty, veřejné cesty, pořadí navigace, `public_paths`, `isModuleEnabled()` brány veřejných entrypointů a HTTP smoke pokrytí vypnutého i zapnutého stavu veřejně navigovatelných modulů. Hlavní administrační přehledy modulů jsou uvedené v manifestovém `admin_paths` a používají `requireModuleEnabled()`, takže přímá URL vypnutého modulu neskončí běžným přehledem; formulářové, detailové a stav měnící admin endpointy jsou navíc v jedné sdílené mapě `adminRouteModuleRequirements()`, kterou prochází unit testy i modulový audit. Content picker a jeho vyhledávací endpoint zároveň nesmí nabízet zdroje modulů, které jsou v nastavení vypnuté, a každý modulový picker zdroj musí mít odpovídající, jedinečný a pojmenovaný `content_reference_types` záznam v manifestu. Veřejné `search.php` bere popisky typů výsledků z manifestu a audit hlídá, že nový typ má i odpovídající URL větev. XML sitemap má pro modulové větve manifestové `sitemap_sections`, aby se nové veřejné URL nedaly přidat mimo centrální modulový kontrakt. Obsahové statistiky berou mapování `page_type` na modul z `stats_page_types`, takže nový veřejný detail měřený přes `trackPageView()` nesmí přidat vlastní ruční mapu ve statistikách. Pokud další kód potřebuje zjistit modul podle nastavovacího klíče, cesty, primární administrační cesty, administrátorské capability nebo měřeného typu obsahu, používá `moduleSettingKey()`, `modulePublicPathModuleMap()`, `moduleAdminPathModuleMap()`, `modulePrimaryAdminPath()`, `moduleAdminCapability()` nebo `moduleStatsPageTypeMap()` místo ručního seznamu. Modulový audit hlídá i dokumentační drift mezi tímto průvodcem, README, návrhovou šablonou a developer checklistem, aby se při přípravě dalšího modulu neztratily povinné integrační body. Pro větší modulové změny slouží kontrolní příkaz `composer ci:module-ready`.

Stejný checklist nově odkazuje i na accessibility conformance report v `docs/accessibility/wcag-22-aa-conformance.md`, `docs/accessibility/a11y-remediation-backlog.md`, `docs/accessibility/manual-test-protocol.md`, `docs/accessibility/author-content-checklist.md` a modulové přílohy jako `docs/accessibility/modules/blog.md`. Při návrhu nového modulu se proto předem řeší, zda modul mění některý WCAG/ACR předpoklad, jestli potřebuje ruční test se čtečkou nebo klávesnicí a kde končí odpovědnost CMS a začíná ručně dodaný obsah autora.

Vlastní databázové tabulky modulů se zapisují do manifestového `database_tables`; společné lookupy poskytují `moduleDatabaseTables()` a `moduleDatabaseTableModuleMap()`. Module contract audit u každé deklarované tabulky ověřuje jednoznačného vlastníka a `CREATE TABLE IF NOT EXISTS` v `install.php` i `migrate.php`. Sdílené tabulky jádra, které modul jen používá, se do jeho vlastnictví nezařazují.

Obrazovky se sdíleným administračním řazením dávají po přesunu položky textovou zpětnou vazbu k uložení pořadí. Pokud AJAX uložení selže, správce dostane inline chybovou hlášku s doporučením zkontrolovat připojení a zkusit změnu znovu, ne prohlížečový dialog.

Redakční kvalitu ručně vloženého obsahu pokrývá [redakční checklist přístupného obsahu](accessibility/author-content-checklist.md). Používejte ho před publikací článků, stránek, médií a embedů: kontroluje alt texty, přepisy, titulky, jazyk částí, srozumitelné odkazy, nadpisy, tabulky, barvu, vlastní HTML a externí služby. Nález v ručním obsahu se zapisuje jako author-content issue, zatímco chyba výchozího UI, šablony nebo helperu patří do backlogu oprav CMS.

---

## Nápověda a podpora v administraci

Ve spodní části administrační navigace je stálý odkaz **Nápověda a podpora**. Stránka slouží jako jednotné místo pro rychlou orientaci v administraci, profil a zabezpečení účtu, redakční accessibility checklist, kontaktní/chat/Form Builder workflow, provozní nastavení a odkazy na dokumentaci v repozitáři.

Ve veřejné šablonové vrstvě se stejný princip promítá do footeru jako navigace **Pomoc a kontakt**. Pokud je zapnutý modul Kontakt nebo Chat, patička nabídne příslušné odkazy na stejném místě napříč veřejnými stránkami. Bundled i importované portable/static theme varianty dědí sdílený PHP layout a footer; theme balíček může měnit assety a nastavení, ale nemá přepisovat PHP patičku, layout ani views mimo samostatný review.

Při odpovědi z detailu kontaktní zprávy, chat zprávy nebo odpovědi Form Builderu administrace kontroluje předmět i text e-mailu. Pokud některá část chybí, souhrnná chyba se doplní konkrétní field-level nápovědou u obou polí, aby bylo jasné, co před odesláním opravit.

V detailu odpovědi Form Builderu stejný princip platí i pro GitHub issue bridge. Pokud chybí repozitář, název nebo tělo nového issue, případně je neplatná adresa existujícího issue, souhrnný alert doplní konkrétní nápovědu přímo u dotčeného pole.

Stejný field-level pattern používají i další běžná administrační workflow: série článků, externí odkazy blogu, základní chyby rezervačního zdroje, podcastové pořady a newsletter composer. Při chybě má souhrnný text zůstat textový a atomický, dotčené pole má mít `aria-invalid`, `aria-describedby` jen na existující nápovědu nebo chybu a lokální text má správci říct konkrétní opravu.

Ve správě podcastu to platí také pro kapitoly, osoby a odkazy na platformy. Po chybě zůstanou rozepsané hodnoty ve formuláři; nápověda u dotčeného pole vysvětlí přijatelný formát času, nabídne příklad názvu, popíše bezpečný http/https nebo doménový tvar adresy a u duplicitní položky doporučí jiný čas nebo úpravu existujícího záznamu.

Ve správě strukturovaných jídelních lístků dostávají stejnou konkrétní nápovědu sekce, položky, datum/čas podávání, cena, výživové hodnoty a výběr obrázku. Smazání položky nebo sekce je trvalé, proto formulář před akcí popisuje dotčený obsah a metadata, u sekce počet položek, vyžaduje potvrzovací checkbox a server nepotvrzený požadavek odmítne. Potvrzené smazání zachová soubory v knihovně médií.

Stejný guardrail platí pro hlavní navigační externí odkazy, přesměrování, alba galerie, fotografie galerie a ruční vytvoření rezervace. Chyby mají správci poradit doplnění názvu, opravu interní cesty nebo URL, jiný slug, platné nadřazené album, platnou licenci, kalendářní datum pořízení, výběr zdroje nebo uživatele, doplnění údajů hosta, opravu času a řešení kolize rezervace.

Hlavní obsahové editory používají stejnou smlouvu pro editor článku, správu blogů, položky vývěsky, jídelní/nápojové lístky a ankety. Chyba má vždy říct, které pole opravit a jak: doplnit titulek nebo název, zvolit jedinečný slug, použít obsah z cílového blogu, vybrat existující kategorii, zadat datum vyvěšení, doplnit možnosti odpovědi nebo opravit limit vícevýběru.

Stejný pattern platí i mimo obsahové editory pro profil správce, správu uživatelských účtů, nastavení webu a správu šablon. Neplatný e-mail, krátké nebo neshodné heslo, TOTP kód, veřejný autor, název webu, veřejný název vývěsky, GitHub repozitář, branding uploady, aktivní šablona, theme settings i ZIP import/export mají mít atomický souhrnný alert, `aria-invalid`, existující `aria-describedby` a konkrétní radu, jak hodnotu opravit.

Ruční SQL záloha databáze používá stejný error-prevention přístup jako ostatní právně nebo datově dopadající akce. Správce má před stažením přečíst review citlivosti exportu, potvrdit oprávnění ke stažení a server má nepotvrzený požadavek odmítnout bez odeslání souboru nebo zápisu audit logu.

Hlavní JSON export CMS se také nestahuje přímo bez review kroku. Obrazovka nejdřív popíše citlivé části exportu, správce potvrdí oprávnění ke stažení a server bez potvrzení vrátí textový alert a chybu u checkboxu místo attachmentu.

CSV export odpovědí Form Builderu používá stejný princip. Přehled odpovědí nejdřív otevře review citlivosti exportu a aktuálního filtru, stažení vyžaduje potvrzení oprávnění a server bez potvrzení vrátí textový alert a chybu u checkboxu bez attachmentu i audit logu.

CSV export výsledků anket používá stejný princip pro agregovaná data. Kontrolní obrazovka ukáže téma ankety, typ hlasování, viditelnost výsledků, počet hlasujících a vybraných odpovědí; stažení vyžaduje potvrzení oprávnění a server bez potvrzení vrátí textový alert a chybu u checkboxu bez attachmentu i audit logu.

CSV export statistik obsahu používá stejný princip pro interní výkon obsahu. Kontrolní obrazovka ukáže období, filtr modulu, počet řádků a upozorní, že agregovaná data neobsahují raw identifikátory návštěvníků, ale mohou prozrazovat redakční a provozní priority; stažení vyžaduje potvrzení oprávnění a server bez potvrzení neodešle attachment ani nezapíše audit log.

CSV export audit logu používá stejný princip pro provozní a bezpečnostní záznamy. Kontrolní obrazovka ukáže aktuální filtr, počet záznamů a upozorní na citlivost detailů administračních akcí; stažení vyžaduje potvrzení oprávnění a server bez potvrzení neodešle attachment ani nezapíše audit log.

ZIP export šablony také vyžaduje kontrolu před stažením. Obrazovka připomíná, že balíček přenáší manifest, uloženou vizuální konfiguraci a statické assety šablony; správce musí potvrdit kontrolu vybrané šablony a oprávnění ke stažení, jinak server neodešle attachment ani nezapíše audit log.

Galerie používá stejný pattern pro hromadné smazání alb a ZIP export vybraných alb. Přehled alb zobrazí review dopadu, správce potvrdí kontrolu výběru a zvolené akce a server bez potvrzení neodstraní alba/fotografie/revize ani neodešle ZIP attachment.

Sdílené hromadné mazání v běžných administračních přehledech používá obecný `confirm_bulk_delete` guardrail. Přehled zobrazí review dopadu smazání, správce potvrdí kontrolu výběru a server bez potvrzení neprovede cleanup, nesmaže data ani nezapíše audit log.

Mazání jednotlivého přesměrování používá stejný review-and-confirm princip. Řádková akce zobrazí starou a novou cestu, vyžaduje potvrzení dopadu na veřejnou URL a server bez `confirm_redirect_delete_<id>` záznam nesmaže ani nezapíše audit log.

Tyto odkazy jsou součástí WCAG 2.2 `3.2.6 Consistent Help` evidence. Pokud nový modul zavádí vlastní podporu, inbox, kontaktní workflow nebo redakční pravidla, zvažte doplnění této stránky, veřejné patičky nebo dokumentace tak, aby správce ani návštěvník nemuseli hledat nápovědu na každé obrazovce jiným způsobem.

---

## Command centrum administrace

Na každé administrační stránce je v hlavní navigaci pole **Hledat v administraci**. Po odeslání otevře běžnou stránku výsledků, takže funguje i bez JavaScriptu. Klávesová zkratka `Ctrl+K` otevře command paletu jako přístupný dialog s vlastním nadpisem, stavem `aria-expanded`, focus trapem, klávesou `Esc` pro zavření a nerušivým live regionem, který oznamuje až stav výsledků, ne každý napsaný znak.

Command centrum hledá v administračních obrazovkách, bezpečných rychlých akcích typu `Nový článek`, `Nová stránka`, `Nahrát média` nebo `Nastavení webu` a ve vybraném editovatelném obsahu. Výsledky se vždy filtrují podle role, capability a zapnutých modulů. Vypnutý modul ani obsah mimo oprávnění uživatele se v paletě ani ve fallback výsledcích nezobrazí.

Užitečné položky si administrátor může připnout. Připnuté položky jsou osobní a zobrazují se na dashboardu v bloku **Moje zkratky**. Pin endpoint nepřijímá libovolnou URL z formuláře; bere jen typ a klíč položky, znovu ji dohledá v interním registru a uloží pouze validní interní administrační cíl.

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
- Hromadné změny stavu a mazání odpovědí s potvrzením kontroly

CSV export odpovědí může obsahovat osobní a provozní údaje z formuláře, interní poznámky, štítky a přiřazení. Administrace proto před stažením zobrazí review citlivosti exportu, počet odpovědí a aktuální filtr; soubor se odešle až po potvrzení oprávnění ke stažení.

Hromadné akce nad odpověďmi, například změna stavu nebo trvalé smazání vybraných odpovědí, vyžadují kontrolní checkbox potvrzující kontrolu výběru a zvolené akce. Bez potvrzení server akci odmítne ještě před změnou dat, odstraněním příloh/historie nebo zápisem audit logu.

Smazání celého formuláře je samostatně potvrzované v přehledu formulářů. Řádkový review text předem popíše veřejnou URL formuláře, počet polí, odpovědí, záznamů historie odpovědí a dopad na nahrané soubory v odpovědích; bez checkboxu `confirm_form_delete_<id>` server formulář, pole, odpovědi, historii ani audit log nezmění.

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

Klientské chyby v hromadném generátoru slotů a při přidání blokovaného dne bez data se zobrazují jako inline text u dané části formuláře, ne jako prohlížečové modální okno. Text radí, co opravit, a focus se vrací na pole, které vyžaduje zásah.

To je důležité hlavně po ručních úpravách nebo importech, kdy se nejčastěji objeví překlep v datu, obrácený rozsah nebo neplatný čas.

---

## Import / Export dat a UTF-8

Obrazovka **Import / Export** pracuje s JSON soubory v UTF-8.

JSON export nejdřív zobrazí review citlivosti dat. Soubor může obsahovat obsah webu, nastavení, metadata médií, chatové zprávy, komentáře, odběratele, tokeny odběru a další provozní údaje, proto stažení vyžaduje potvrzení oprávnění. Bez potvrzení se vrátí field-level chyba u checkboxu a soubor se neodešle.

CSV export odpovědí Form Builderu pracuje stejně: review obrazovka popíše citlivost odpovědí, aktuální filtr a počet exportovaných odpovědí. Bez potvrzovacího checkboxu se soubor neodešle a nevznikne audit log; po potvrzení se CSV stáhne přes bezpečné no-store/noindex/nosniff hlavičky.

Samostatné importy z WordPressu a eStránek pracují s XML/WXR soubory. Před parsováním používají sdílenou upload validaci pro stav PHP uploadu, ověření dočasného souboru a odmítnutí prázdného souboru; WordPress náhled si dočasnou kopii ukládá přes stejný bezpečný helper do `uploads/tmp`. Downloader fotografií z eStránek zároveň normalizuje základní URL webu přes sdílený http/https helper, takže se při importu nepoužijí protocol-relative adresy, přihlašovací údaje v URL ani nebezpečná schémata.

Chyby importních formulářů se mají ozvat nahoře jako alert a současně u konkrétního pole. JSON import radí neupravený export z Kora CMS v platném UTF-8, WordPress import radí WXR/XML export z administrace WordPressu, eStránky import radí XML zálohu z eStránek a downloader fotek odděleně označí XML soubor i základní URL webu.

Importy, které zapisují obsah, nastavení, galerie nebo soubory, vyžadují před spuštěním potvrzovací checkbox. Server bez něj odmítne JSON import, finální WordPress WXR import, XML import z eStránek i dávkové stahování fotografií z eStránek ještě před datovou změnou.

Ruční SQL záloha databáze je také citlivý export. Obrazovka **Záloha databáze** popíše, že SQL soubor obsahuje kompletní strukturu a data CMS včetně účtů, e-mailů, zpráv, objednávek a dalších provozních údajů. Stažení je povolené až po potvrzení oprávnění; bez něj se vrátí field-level chyba u checkboxu a soubor se neodešle.

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

V přehledu pořadů i epizod je dostupná akce `Kontrola RSS`. Diagnostika upozorní na chybějící metadata pořadu, artwork, audio soubor a neúplné enclosure údaje veřejných epizod. Chyby je vhodné opravit před odesláním feedu do podcastového katalogu.

Pořad i epizoda mají interní neměnný RSS GUID. GUID se nemění při úpravě názvu nebo slugu a je součástí exportu/importu. U externě hostovaného audia vyplňte vedle přímé URL také MIME typ, například `audio/mpeg`, a přesnou velikost souboru v bajtech. U lokálně nahraného audia CMS tyto údaje zjistí automaticky.

Přehledy pořadů a epizod rozlišují stavy `Koncept`, `Čeká na schválení`, `Naplánováno`, `Publikováno` a podle typu záznamu také `Skrytý`; koncepty lze samostatně filtrovat.

U uložené epizody je dostupná akce `Spravovat kapitoly`. Začátek kapitoly lze zadat jako počet sekund, `MM:SS` nebo `H:MM:SS`; název je povinný a související odkaz i obrázek jsou volitelné veřejné `http://`/`https://` adresy. Kapitoly se automaticky řadí podle času, zobrazují se na veřejném detailu epizody a podcastovým aplikacím se poskytují přes Podcasting 2.0 JSON endpoint.

Vyplněný přepis epizody zůstává viditelný na detailu a RSS feed na něj navíc odkazuje značkou `podcast:transcript`. Samostatný HTML přepis i JSON kapitol podporují jen `GET`/`HEAD`, kontrolují veřejnost epizody a pořadu a používají cache validátory; koncept ani skrytý obsah přes ně nelze načíst.

U pořadu lze přes `Spravovat tvůrce pořadu` evidovat moderátory, producenty a další stálé členy týmu. U epizody slouží `Spravovat hosty a tvůrce` pro hosty i osoby, které se podílely jen na daném dílu. Každá osoba má jméno, normalizovanou roli, skupinu `Účinkující` nebo `Tvůrčí tým`, volitelný veřejný profil, obrázek a pořadí.

Osoby pořadu a epizody se zobrazují v samostatných sekcích s viditelnými nadpisy. RSS feed je publikuje pomocí Podcasting 2.0 `podcast:person`; změna osoby zároveň změní cache validátory feedu. Export/import zachovává osoby a při trvalém odstranění epizody nebo pořadu CMS jejich vazby uklidí.

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
- Přepis epizody jako textová alternativa audia

### Generované iTunes značky

`itunes:summary`, `itunes:subtitle`, `itunes:owner`, `itunes:type`, `itunes:explicit`, `itunes:season`, `itunes:episodeType`

Přepis epizody vyplňujte u audio obsahu, který nemá jinou textovou alternativu. Veřejný detail epizody ho zobrazí v samostatné sekci **Přepis epizody**, takže je dostupný pro čtečky obrazovky, uživatele bez možnosti poslouchat audio i vyhledávání v obsahu. Přepis se ukládá do revizí epizody a zachovává se v JSON exportu/importu.

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

U uloženého podcastového pořadu lze přes odkaz `Spravovat platformy` přidat jeho bezpečné veřejné adresy na Spotify, Apple Podcasts, YouTube Music a dalších službách. Volba `Jiná platforma` vyžaduje vlastní viditelný název; každá adresa musí používat `http://` nebo `https://`. Odkazy se na veřejném detailu zobrazí jako pojmenovaná navigace a při exportu/importu zůstávají svázané s pořadem.

Číslo sezóny u epizody je volitelné. Jakmile pořad obsahuje veřejné epizody s číslem sezóny, jeho veřejná stránka nabídne filtr `Všechny epizody` a jednotlivé sezóny. Detail epizody současně nabídne předchozí a další veřejnou epizodu podle sezóny, čísla epizody a data publikování; koncepty ani budoucí epizody se v této navigaci neobjeví.

Veřejný katalog podcastů nabízí hledání v názvu, autorovi, popisu a kategorii a samostatný filtr kategorií, které používá alespoň jeden veřejný pořad. Zvolené hodnoty zůstávají zachované při stránkování. Také administrační přehled epizod umožňuje vedle textu a publikačního stavu omezit výpis na konkrétní sezónu; sezóny bez epizod se ve výběru nenabízejí.

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
- **Správa blogů** zároveň nově nabízí rychlé odkazy `Články blogu`, `Série článků`, `Kategorie blogu`, `Štítky blogu` a `Stránky a odkazy blogu`, takže lze z jednoho přehledu přejít rovnou na správu konkrétního blogu
- **Správa blogů** používá pro formulář vytvoření blogu, dialog úprav a náhled loga sdílené admin CSS třídy bez lokálního `<style>` bloku a bez lokálních `style` atributů, takže méně zatěžuje CSP reporty a drží konzistentní modální chování

To je užitečné hlavně ve chvíli, kdy jeden autor spravuje více blogů nebo když chcete rychle zkontrolovat, zda má nový redaktor přístup opravdu jen tam, kam patří.

### Články v rámci blogu

Editor článku respektuje vybraný blog:

- nabídne jen kategorie a štítky tohoto blogu
- horní odkazy vedou na správný veřejný blog, jeho RSS feed a správu taxonomií
- nové články mohou převzít výchozí komentáře z blogu
- jeden článek v blogu lze označit jako `Doporučený článek blogu`
- článek lze zařadit do jedné nebo více sérií článků stejného blogu
- u článku lze ručně vybrat související články ze stejného blogu; na veřejném detailu se ruční výběr zobrazí před automaticky doplněnými články
- náhledový obrázek používá sdílenou upload validaci a při výměně nebo odebrání uklízí i staré miniatury, WebP a responsive varianty
- při hromadném mazání se případné selhání odstranění náhledového obrázku nebo miniatury zapíše do strukturovaného logu bez plné cesty k souboru

Na veřejném indexu blogu se pak bez aktivních filtrů zobrazí právě jeden doporučený článek.

### Série článků

Každý blog má vlastní správu sérií v odkazu **Série článků**. Série má název, slug, volitelný popis, aktivní stav a ručně seřazené články daného blogu. Článek lze v první verzi zařadit do více sérií, ale vždy jen do sérií blogu, do kterého článek patří.

Při mazání blogové kategorie, štítku nebo série článků administrace nejdřív ukáže dopad na navázané články, u kategorií i podkategorie, a vyžaduje potvrzovací checkbox. Bez potvrzení server akci odmítne, obsah i vazby zůstanou beze změny a nezapíše se audit log.

V editoru článku se série vybírají zaškrtávacími políčky. Pokud článek nemá být v žádné sérii, nechte všechna políčka nezaškrtnutá nebo použijte tlačítko `Odebrat ze všech sérií`; není potřeba hledat zvláštní volbu `Žádná`. Editor zároveň textově oznamuje, zda článek není v žádné sérii, nebo kolik sérií je vybraných.

Veřejně se série zobrazí jen tehdy, když je aktivní a obsahuje alespoň jeden publikovaný, nesmazaný a nebudoucí článek. Index blogu zobrazí blok `Série článků` nad výpisem článků, detail článku zobrazí blok `Tento článek je součástí série` s aktuálním dílem označeným přes `aria-current="page"` a samostatná stránka série má tvar `/{blog-slug}/serie/{series-slug}`.

Server při ukládání článku znovu ověřuje, že vybraná série patří do cílového blogu. Podvržené ID série z jiného blogu uložením neprojde a článek se nepřesune do cizí série.

Ručně vybrané související články se validují proti právě vybranému blogu. Editor nabídne jen publikované články stejného blogu a server při uložení znovu ověří, že podstrčené ID nepatří do jiného blogu. Pokud autor nic nevybere, veřejný detail článku dál použije automatické související články podle kategorie, štítků a novosti.

### Veřejné stránky kategorií a štítků

Kategorie i štítky každého blogu mají vlastní veřejné landing stránky. Ve správě **Kategorie blogu** a **Štítky blogu** lze kromě názvu vyplnit slug, popis, `Meta title` a `Meta description`. Když slug necháte prázdný, CMS ho vygeneruje z názvu a v rámci daného blogu ho automaticky odliší číselnou příponou.

Veřejné adresy mají tvar:

- `/{blog-slug}/kategorie/{category-slug}`
- `/{blog-slug}/stitky/{tag-slug}`

Landing stránka zobrazuje stejné publikované články jako původní filtry `?kat=` a `?tag=`, ale má vlastní nadpis, popis nad výpisem článků a canonical/SEO metadata z vyplněných polí. Staré query odkazy zůstávají funkční kvůli starším sdíleným odkazům a kombinovaným filtrům. Sitemap do XML přidá jen ty kategorie a štítky, které mají alespoň jeden veřejně publikovaný článek.

Když změníte slug kategorie nebo štítku, Kora CMS automaticky uloží trvalé `301` přesměrování ze staré čisté URL na novou. Stejně se chrání publikované články při změně slugu nebo přesunu do jiného blogu a aktivní série při změně slugu. Vzniklé redirecty jsou uložené ve společné správě **Přesměrování (301/302)** a počítají přístupy stejně jako ručně založené redirecty.

### Stránky a odkazy blogu

Každý blog může mít vlastní horní navigaci nad výpisem článků. Do stejného pořadí lze přidat statické stránky blogu i externí nebo interní odkazy. Odkaz má název, cílovou adresu, volitelný přístupný popis pro čtečky obrazovky, přepínač zobrazení a volbu otevření v novém okně. Přístupný popis se ve veřejném výstupu přidává jako skrytý text za viditelný název odkazu, takže čtečka nehlásí jiný název než ten, který je vidět na stránce. Pokud se odkaz otevírá v novém okně, veřejný výstup automaticky přidá bezpečné atributy `target="_blank"` a `rel="noopener noreferrer"` a stejným skrytým textem oznámí otevření v novém okně.

Statická stránka přiřazená k blogu má veřejnou adresu `/{blog-slug}/stranka/{page-slug}`. Slug tak musí být jedinečný jen v daném blogu; stejný slug lze použít v jiném blogu, protože výsledná URL je jiná. Globální statické stránky mimo blog zůstávají unikátní mezi globálními stránkami.

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

Pokud je nastavený doporučený článek blogu a návštěvník nemá aktivní filtr, default šablona ho zobrazí nad výpisem článků jako samostatný blok. Nejprve je vidět nadpis `Doporučený článek`, pod ním název článku jako hlavní odkaz a až potom metadata článku: datum, přibližná doba čtení, počet přečtení a autor.

Na detailu článku může být blok `Související články`. Pokud autor v editoru vybral konkrétní související články, zobrazí se jako první; pokud jich je méně než limit bloku, Kora CMS doplní další položky automaticky. Díky tomu lze u důležitých článků vést čtenáře přesněji, ale běžné články dál fungují bez ruční práce.

Pokud má blog aktivní série s publikovanými články, veřejný index nad výpisem článků nabídne blok `Série článků`. Detail článku potom vedle souvisejících článků ukáže i kontext série, pořadí dílů a odkazy na předchozí nebo další díl.

U delších blogových článků default šablona automaticky vytvoří osnovu `V tomto článku`, pokud veřejně vyrenderovaný obsah obsahuje alespoň dva viditelné nadpisy `h2` nebo `h3`. Nadpisy dostanou stabilní kotvy, ručně zadaná `id` se zachovají a osnova se zobrazí nad samotným obsahem článku jako rychlá navigace pro čtenáře i čtečky obrazovky.

### Veřejní autoři

Veřejný profil autora na `/author/slug-autora` zobrazuje medailonek autora a sekci `Obsah autora`. Ta sdružuje veřejně publikované články a novinky autora podle toho, které moduly jsou zapnuté. Návštěvník může použít filtry `Vše`, `Články` a `Novinky`; neplatný parametr `typ` se bezpečně chová jako `Vše`.

Přehled `/authors/` u každého autora ukazuje souhrn dostupného obsahu, například `3 články, 2 novinky`. Pokud je modul Novinky vypnutý, novinky se nezapočítávají ani nezobrazují. Stránka novinek podporuje filtr `news/index.php?autor=slug-autora`, takže z autorského profilu lze přejít rovnou na novinky konkrétního autora.

### Per-blog RSS

Každý blog má vlastní feed:

- `feed.php?blog=slug-blogu`

Feed respektuje:

- název a metadata blogu
- RSS podtitulek
- počet epizod/položek nastavený pro konkrétní blog
- aktuální slug blogu i staré redirecty

---

## Blog – měsíční archiv

Veřejný přehled blogu vytváří pro měsíce s publikovanými články sekci `Archiv blogu`. Odkazy používají stabilní adresu `/{slug-blogu}/archiv/RRRR/MM`, například `/snd/archiv/2026/07`, mají vlastní canonical URL a neprázdné měsíce se zapisují do sitemap. Starší odkazy s parametrem `?archiv=RRRR-MM` zůstávají funkční; při změně slugu blogu se čistá archivní URL přesměruje na aktuální slug stejně jako ostatní blogové adresy.

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
- volitelné nezávazné objednávkové poptávky, cílový e-mail a instrukce pro zákazníka

Pokud je u typu označený nový aktuální lístek, předchozí aktuální lístek stejného typu se při uložení automaticky odznačí.

### Strukturované položky lístku

Z přehledu lístků i z editoru konkrétního lístku vede odkaz `Položky lístku`. Tam lze spravovat:

- sekce lístku, například polévky, hlavní jídla nebo nápoje
- den podávání sekce, čas od/do a poznámku k podávání pro denní nabídky
- položky v sekcích
- cenu, měnu a volitelnou poznámku k ceně
- alergeny podle číselníku 1-14
- dietní štítky, například vegetariánské, veganské, bez lepku, bez laktózy, pikantní nebo alkohol
- volitelné výživové údaje položky: porce, energie v kJ/kcal, bílkoviny, sacharidy, tuky a sůl
- volitelný obrázek z veřejných obrázků knihovny médií a vlastní alt text obrázku
- dostupnost položky a pořadí v sekci

Správa položek nabízí i rychlé bezpečné akce: sekce a položky lze posouvat nahoru/dolů, položku lze duplikovat a vybrané položky lze hromadně označit jako dostupné nebo nedostupné.

Pokud lístek žádné strukturované položky nemá, veřejný web dál použije původní HTML obsah lístku. Pokud má lístek strukturované položky i HTML obsah, položky se zobrazí jako hlavní menu a HTML obsah jako doplňkové poznámky k lístku. Alergeny, dietní štítky a nedostupnost se zobrazují textově, ne jen barvou. U zobrazených strukturovaných položek se doplní také alergenová legenda, aby návštěvník nemusel význam čísel hádat.

### Denní nabídky a nutriční údaje

Denní nabídka se nastavuje u sekce lístku, ne u každé položky zvlášť. Pokud má sekce vyplněné datum podávání, veřejný detail lístku nabídne přepínání dnů přes filtr `den=YYYY-MM-DD`; dnešní sekce se zvýrazní jako `Dnešní nabídka`. Archiv při aktivním denním filtru zobrazí jen lístky, které mají odpovídající datovanou strukturovanou sekci.

Výživové údaje jsou volitelné. Nevyplněná pole se veřejně nezobrazí. Pokud jsou u položky zadané, zobrazí se textově v přehledu položky a JSON-LD data detailu lístku doplní `NutritionInformation`, aby informace nebyly závislé jen na vizuálním stylu.

### Veřejné filtry strukturovaných položek

Veřejný index, archiv i detail konkrétního lístku podporují položkové filtry:

- dietní štítky, například `Veganské` nebo `Bez lepku`
- alergeny, které má výpis vynechat
- volbu `Pouze dostupné položky`

Více dietních štítků se vyhodnocuje současně, takže položka musí splnit všechny vybrané štítky. Filtr `Bez alergenu` znamená, že se zobrazí jen položky, které vybrané alergeny neobsahují. Pokud je aktivní položkový filtr, starší HTML-only lístky se v archivu nezobrazují, protože jejich položky nejde bezpečně filtrovat.

### Nezávazné objednávkové poptávky

Objednávkové poptávky jsou volitelné a slouží jako nezávazný kontakt zákazníka, ne jako platební nebo skladový systém. Pokud je u lístku zapnete a lístek je veřejně viditelný, návštěvník se dostane na formulář `food/order.php?slug=...`, vybere dostupné položky, množství a vyplní kontaktní údaje.

Formulář je chráněný CSRF tokenem, honeypotem, captchou a rate-limitem. Uložená poptávka drží snapshot názvu položky, ceny a měny, takže pozdější změna lístku nepřepíše historický požadavek. Notifikace se posílá na e-mail objednávek vyplněný u lístku, případně na kontaktní nebo administrační e-mail webu. V administraci je samostatný přehled `Objednávkové poptávky` s filtrem podle stavu a detail s bezpečnou změnou stavu `Nová / Potvrzená / Odmítnutá / Vyřízená / Zrušená`.

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
- Odkaz `Položky lístku` otevře samostatnou správu strukturovaných sekcí, cen, alergenů a dietních štítků.
- Odkaz `Objednávkové poptávky` otevře administraci nezávazných objednávek Food modulu.
- Z detailu i ze seznamu vede odkaz na historii revizí.
- Revize zachycují změny typu, názvu, slugu, popisu, obsahu, platnosti, stavu aktuálnosti i zveřejnění.
- Při změně slugu se automaticky uloží redirect ze staré adresy na novou.

### Veřejný archiv a detail

Veřejný archiv nově podporuje:

- fulltextové hledání
- hledání podle názvů a popisů strukturovaných položek
- filtrování podle typu
- přepínání scope `Platí nyní / Připravujeme / Archivní / Všechny lístky`
- filtrování strukturovaných položek podle dietních štítků, alergenů a dostupnosti
- filtrování podle dne podávání strukturovaných sekcí
- stránkování

Detail lístku nově:

- ukazuje jasný stav `Platí nyní / Připravujeme / Archivní`
- zobrazuje strukturované sekce a položky, pokud jsou vyplněné
- zvýrazňuje dnešní nabídku, zobrazuje čas podávání a volitelné nutriční údaje položek
- zachovává aktivní položkové filtry i při návratu zpět do archivu
- zachovává návrat do původního archivního kontextu
- nabízí odkaz na nezávaznou objednávkovou poptávku, pokud je u lístku povolená
- nabízí akci `Vytisknout`
- vkládá structured data typu `Menu`; u strukturovaných položek doplňuje i `MenuSection`, `MenuItem`, cenu, obrázek z veřejné knihovny médií a nutriční údaje, pokud jsou vyplněné

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že modul podporuje platnost, strukturované položky, archiv, hledání a revize.
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
- výchozí kredit a licenci pro nově nahrané fotografie

Formulář alba nově obsahuje i odkaz na historii revizí.

### Co se nastavuje u fotografie

Každá fotografie může mít:

- titulek
- slug
- alt text, popisek a delší popis
- kredit autora, licenci, datum a místo pořízení
- pořadí v albu
- zveřejnění na webu

Fotografie lze rychle přesouvat i přímo v přehledu alba pomocí tlačítek `Nahoru` a `Dolů`, bez nutnosti ručně přepisovat pořadí.

Přehled fotografií obsahuje filtr `Chybí alt text`, který pomáhá dohledat snímky s neúplnými popisnými metadaty. Výchozí kredit a licence z alba se použijí jen při novém uploadu; existující fotografie se tím zpětně nepřepisují.

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
- sekci `Informace o fotografii` s datem, místem, kreditem a licencí, pokud jsou vyplněné
- akci `Kopírovat odkaz`
- structured data `ImageGallery` a `ImageObject`

Veřejný obrázek používá alt text v pořadí: ručně vyplněný alt text, potom popisek nebo titulek a nakonec bezpečný název fotografie. Metadata fotografie se ukládají i do exportu/importu CMS, takže se při přenosu obsahu neztratí.

### Revize a změny slugů

- Změny alba i fotografie se zapisují do historie revizí.
- Pokud se změní slug alba nebo fotografie, stará veřejná adresa se uloží jako redirect na nový canonical tvar.

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že galerie podporuje detailová URL, hledání, stránkování, revize, fotografická metadata a bezpečný media endpoint.
- Tento dokument popisuje konkrétní workflow galerie, pravidla veřejné viditelnosti, práci s pořadím fotografií, popisná/licenční metadata a bezpečnostní model doručování obrázků.

---

## Chat

Modul chatu funguje jako moderovaná veřejná nástěnka, ne jako okamžitě publikovaný shoutbox. Každá nová veřejná zpráva nejdřív přijde do administrace a veřejně se zobrazí až po schválení. Nově lze chat členit do témat, připínat důležité schválené zprávy a u veřejných zpráv povolit moderované odpovědi ve vlákně.

Stejný formulář umí také soukromý podpůrný dotaz správci. V tom režimu je povinný e-mail pro odpověď, CMS vygeneruje referenční kód ve tvaru `CHT-YYYYMMDD-XXXX` a zpráva se nikdy nezobrazí ve veřejném chatu, v detailu veřejné zprávy ani v sitemapě.

### Co se zobrazuje veřejně

Veřejný chat ukazuje jen:

- jméno autora
- datum odeslání
- text zprávy
- případné téma
- stav připnutí u důležitých zpráv
- odkaz na detail veřejného vlákna

E-mailová adresa zůstává jen pro administraci a veřejně se nikdy nezobrazuje. Pole `web` už veřejný formulář vůbec nenabízí.

### Témata a veřejná vlákna

V administraci je u chatu odkaz **Témata chatu**. U každého tématu lze nastavit:

- název a slug
- krátký popis pro veřejnou stránku tématu
- aktivní/vypnutý stav
- pořadí ve veřejné navigaci témat

Aktivní téma má čistou veřejnou adresu `/chat/tema/{slug}`. Bez témat se chat chová jako dříve a všechny veřejné zprávy se zobrazují v jednom proudu.

Každá schválená veřejná zpráva má detail `/chat/zprava/{id}`. Návštěvník na něm vidí zprávu a schválené odpovědi; nová odpověď se po odeslání uloží jako `Ke schválení` a veřejně se zobrazí až po ruční moderaci.

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
- filtr podle typu `Veřejná zpráva / Soukromý dotaz`
- filtr podle tématu
- filtr jen na připnuté zprávy
- filtr na zprávy s odpověďmi ke schválení
- hromadné akce `Schválit`, `Skrýt`, `Označit jako přečtené`, `Označit jako vyřízené`, `Smazat`

Detail zprávy přidává:

- interní poznámku
- historii změn
- rychlé workflow akce
- typ zprávy a případný referenční kód
- téma zprávy
- připnutí veřejné zprávy včetně volitelného času konce
- moderaci odpovědí ve veřejném vlákně
- odpověď odesílateli e-mailem, pokud je k dispozici platná adresa

Samotné otevření detailu označí zprávu jako přečtenou, ale automaticky ji nezveřejní.

### Automatický úklid přes cron

V nastavení webu lze nově zadat počet dní, po kterých se mají automaticky mazat staré vyřízené chat zprávy. Hodnota `0` znamená, že se automatický úklid nepoužije.

Cleanup provádí `cron.php` a maže jen:

- zprávy se stavem `Vyřízené`
- starší než nastavený limit
- související historii a veřejné odpovědi vlákna

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že chat je moderovaný, stránkovaný, má témata, veřejná vlákna a podpůrný inbox.
- Tento dokument popisuje konkrétní redakční workflow, veřejnou viditelnost zpráv, témata, připnutí, moderaci odpovědí, soukromé dotazy a automatický cleanup.

---

## Kontakt

Modul Kontakt zůstává jednoduchým veřejným kontaktním formulářem, ale nově umí směrovat dotazy podle témat a dovoluje odpovědět přímo z administrace.

### Témata dotazů

V administraci kontaktních zpráv je odkaz **Témata kontaktu**. U každého tématu lze nastavit:

- název a slug
- popis, který se zobrazí u veřejného formuláře
- volitelný cílový e-mail
- aktivní stav
- pořadí

Pokud existuje alespoň jedno aktivní téma, veřejný formulář vyžaduje výběr tématu. Notifikace správci se pošle na e-mail tématu; když není vyplněný nebo není platný, použije se globální kontaktní e-mail z nastavení webu.

### Veřejné odeslání

Veřejný formulář dál používá CSRF ochranu, honeypot, rate limiting a captchu. Návštěvník může nově vyplnit i jméno. Po úspěšném odeslání CMS zobrazí referenční kód zprávy ve tvaru `KNT-YYYYMMDD-XXXX`, který se uloží i do administrace.

Bez aktivních témat se formulář chová jako dříve, jen navíc nabízí volitelné jméno a po odeslání zobrazí referenční kód.

### Odpověď z administrace

Detail kontaktní zprávy zobrazuje referenční kód, jméno, e-mail, téma, stav a poslední uloženou odpověď. Pokud zpráva obsahuje platný e-mail odesílatele, lze odeslat odpověď přímo z detailu.

Po úspěšném odeslání odpovědi CMS uloží předmět, text odpovědi, čas, uživatele a zprávu označí jako **Vyřízené**. První verze ukládá poslední odpověď; plná historie konverzace patří případně do budoucího helpdesk rozšíření.

### Import a export

JSON export/import přenáší konfiguraci témat kontaktu, ale nepřenáší samotné kontaktní zprávy. Ty jsou soukromou komunikací návštěvníků a nemají se zbytečně kopírovat mezi instalacemi.

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
- Detail veřejné položky může zobrazit sekci **Evidence zveřejnění**, kde návštěvník uvidí důležité veřejné události položky: zveřejnění, změnu URL, změnu přílohy, sejmutí, skrytí, smazání nebo obnovu. U přílohy se ukládá i název, velikost a SHA-256 otisk souboru.

### Kategorie vývěsky

Kategorie vývěsky mají vlastní veřejné landing stránky. Ve správě kategorií lze kromě názvu vyplnit slug, popis, `Meta title` a `Meta description`. Když slug necháte prázdný, CMS ho vygeneruje z názvu a pohlídá jeho jedinečnost.

Veřejná adresa kategorie má tvar `/board/kategorie/slug-kategorie`. Zobrazuje stejný typ výpisu jako filtr vývěsky, ale s vlastním nadpisem, popisem, canonical URL a SEO metadaty. Starý filtr přes parametr kategorie zůstává funkční kvůli kompatibilitě.

### Odběr vývěsky

Odběr vývěsky je oddělený od běžného newsletteru. Návštěvník se přihlásí přes samostatnou stránku `/board/subscribe.php`, vyplní e-mail, volitelně vybere konkrétní kategorie, opíše captcha kód a odběr potvrdí odkazem z e-mailu. Teprve potvrzený odběratel může dostávat upozornění.

Upozornění se posílá jen při první veřejné publikaci položky nebo při přechodu položky z neveřejného stavu do veřejného. Běžná editace už veřejné položky e-maily znovu neposílá, aby vývěska odběratele nespamovala.

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

### Kategorie a série

Kategorie ke stažení mají vlastní správu metadat. Kromě názvu lze nastavit slug, popis, `meta title` a `meta description`; veřejná adresa má tvar `/downloads/kategorie/slug-kategorie`. Při změně slugu CMS uloží redirect ze staré adresy na novou a starý filtr `downloads/index.php?kat=ID` zůstává funkční kvůli kompatibilitě.

Série ke stažení slouží pro vydání jedné aplikace, dokumentu nebo balíčku. Správce vytvoří sérii s názvem, slugem, popisem, aktivním stavem a pořadím. Položka se pak v editoru zařadí do série výběrem ze seznamu a může být označená jako `Aktuální verze`; pokud se jako aktuální označí jiná položka stejné série, ostatní se automaticky odznačí. Veřejná stránka série má tvar `/downloads/serie/slug-serie` a zobrazuje jen publikované položky. Starší importy a data se starým textovým `series_key` zůstávají podporované jako fallback, ale nové UI používá primárně spravované série.

Při chybě u názvu, slugu nebo meta title se správa kategorií a sérií nezastaví jen na obecné hlášce. Souhrnný alert ukáže, že je potřeba opravit konkrétní pole, a field-level text poradí doplnění krátkého názvu, použitelný nebo jiný unikátní slug, prázdné slug pole pro automatické vytvoření, případně zkrácení meta title na 160 znaků.

Smazání kategorie nebo série je samostatně potvrzované. Řádkový formulář nejdřív ukáže, kolika položek ke stažení se změna dotkne, u série také kolik položek přijde o označení aktuální verze. Bez potvrzovacího checkboxu server smazání odmítne, položky zůstanou zachované a nezmění se ani audit log.

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
- spravovanou sérii/verzi pro propojení více vydání stejné aplikace nebo dokumentu
- příznak `Aktuální verze` v rámci vybrané série
- volitelný náhledový obrázek
- příznak `Doporučená položka`
- zveřejnění na webu

Upload souboru není povinný, pokud je vyplněný bezpečný externí odkaz ve tvaru `http://`/`https://` adresy nebo domény bez schématu, kterou CMS uloží jako `https://`. Hodí se to pro software a dokumenty hostované mimo CMS, například na GitHub Releases nebo jiné oficiální stránce projektu. Pokud vyplníte lokální soubor i externí odkaz, veřejný výpis i detail nabídnou obě akce.

### Co je nové v admin workflow

- Přehled `Ke stažení` umí hledání i filtry podle stavu, kategorie, typu, zdroje, platformy a doporučených položek.
- Položky bez lokálního souboru se v přehledu označují jako externí zdroj, aby bylo jasné, že nejde o chybu uploadu.
- Správa kategorií nabízí veřejný odkaz, popis a SEO pole.
- Správa sérií/verzí nabízí veřejný odkaz na sérii a základní přehled počtu položek i aktuální verze.
- U detailnějších položek lze otevřít historii revizí stejně jako u dalších obsahových modulů.
- Při změně slugu se stará veřejná adresa uloží jako redirect na nový canonical tvar.
- Přehled ukazuje i základní statistiku stažení a praktická metadata, takže správce nemusí otevírat každou položku zvlášť.

### Veřejný katalog

- Veřejný výpis podporuje hledání a filtry podle kategorie, typu, platformy, zdroje a doporučených položek.
- Odkazy na kategorie vedou primárně na čisté URL `/downloads/kategorie/{slug}`; staré query filtry zůstávají kompatibilní.
- Veřejná stránka série `/downloads/serie/{slug}` zobrazuje vydání v pořadí aktuální verze, datum vydání a novost.
- Výsledky jsou stránkované a řadí se přirozeně: doporučené položky výš, pak novější vydání.
- Detail položky zobrazuje i praktické informace jako verzi, datum vydání, velikost souboru, checksum, požadavky a kompatibilitu.
- Pokud je položka součástí série, detail ukáže další dostupné verze. U starší položky navíc nabídne odkaz na aktuální verzi.

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

## Události – typy, místa a opakované termíny

Modul **Události** slouží pro veřejný kalendář akcí. Vedle původního výpisu, detailu a ICS exportu umí spravované typy akcí, napojení na modul Místa a jednorázové vytvoření opakovaných termínů.

### Typy akcí

Typy akcí se spravují v administraci událostí přes odkaz **Typy akcí**. U každého typu lze nastavit název, slug, popis, `meta title`, `meta description`, aktivní stav a pořadí. Veřejná adresa typu má tvar `/events/typ/slug-typu` a zobrazuje jen publikované události daného typu. Starší interní hodnota `event_kind` zůstává kompatibilní fallback pro importy a starší data.

Výchozí typy se při instalaci nebo migraci doplní ze starších definic, takže stávající události po aktualizaci nepřijdou o své zařazení.

### Místo konání

Událost může mít pořád ručně vyplněné textové pole `Místo konání`. Pokud je ale zapnutý modul **Zajímavá místa**, editor události nabídne i spravované místo. Veřejný detail pak zobrazí kartu místa s názvem, adresou, odkazem na detail místa a odkazem do mapy, pokud má místo souřadnice.

Textové místo zůstává vhodné pro doplnění sálu, patra nebo jiné praktické poznámky i v případě, že je vybrané spravované místo.

### Opakované termíny

Při vytváření nové události lze zvolit opakování: žádné, denně, týdně nebo měsíčně. Nastavuje se interval a počet termínů v rozsahu 2 až 52. CMS při uložení vytvoří skutečné samostatné události s posunutým začátkem i koncem, unikátním slugem a společným `recurrence_group_id`.

Důležité: opakování je jednorázový generátor, ne živé kalendářové pravidlo. Pozdější úprava jednoho termínu se automaticky nepropíše do celé série. Detail opakované události zobrazí sekci **Další termíny této akce** a aktuální termín označí pro čtečky obrazovky přes `aria-current`.

### Veřejný výstup a SEO

- Detail události zobrazuje typ akce jako odkaz na landing stránku typu.
- Landing stránka typu používá vlastní nadpis, popis a SEO metadata, pokud jsou vyplněná.
- Sitemap obsahuje jen aktivní typy akcí, které mají alespoň jednu veřejně publikovanou událost.
- ICS export zůstává kompatibilní se stávajícími URL a u místa používá nejlepší dostupný veřejný popis.

---

## Rezervace – připomínky, kalendář a historie

Modul **Rezervace** kromě zdrojů, kategorií, lokalit, veřejného booking flow a storna přes token umí kalendářové `.ics` pozvánky, e-mailové připomínky a provozní historii změn rezervace.

### Nastavení zdroje

V editoru zdroje rezervací je část **Připomínky a kalendář**. Správce zde může zapnout kalendářovou pozvánku, připomínky před termínem, určit počet hodin před začátkem rezervace a doplnit vlastní text připomínky. Nastavení platí vždy pro konkrétní zdroj, takže různé provozy mohou mít jiné chování.

Kalendářová pozvánka se přikládá k e-mailu při vytvoření potvrzené rezervace nebo při pozdějším schválení čekající rezervace. Připomínky zpracovává běžný `cron.php`: pošle je jen potvrzeným budoucím rezervacím, jen jednou, a výsledek uloží k rezervaci.

### Administrace rezervací

Přehled rezervací nabízí filtr **Připomínka**, který rozlišuje odeslané, čekající a vypnuté připomínky. U jednotlivých řádků je vidět i případná chyba posledního pokusu, aby správce poznal, proč připomínka neodešla.

Detail rezervace obsahuje sekci **Historie rezervace**. Zapisuje vytvoření, schválení, zamítnutí, zrušení, dokončení, no-show, automatické dokončení a odeslání nebo chybu připomínky. Historie je provozní audit pro administraci; není to veřejná časová osa.

Změny stavů v detailu rezervace mají ochranu před nechtěným dopadem na zákazníka. Schválení, zamítnutí, zrušení, dokončení i označení no-show zobrazují review text, vyžadují potvrzovací checkbox a server nepotvrzený požadavek odmítne dřív, než změní stav, zapíše historii nebo odešle e-mailovou notifikaci.

### Veřejný kalendářový soubor

V části **Moje rezervace** se u budoucích potvrzených rezervací zobrazí odkaz **Stáhnout do kalendáře**. Odkaz vede na tokenový endpoint `/reservations/calendar.php?token=...`, který vrací `.ics` soubor s názvem zdroje, datem, časem, lokalitou, jménem zákazníka a bezpečným storno odkazem, pokud existuje.

Tokenové kalendářové URL se neposílají do canonical URL, sitemapy ani SEO metadat a odpověď používá `no-store`, `noindex` a `no-referrer`, aby se osobní rezervační odkaz zbytečně neukládal v cache nebo náhledech sociálních sítí.

### Import a export

JSON export/import přenáší konfiguraci rezervačních kategorií, zdrojů, otevíracích hodin, slotů, blokovaných dnů a lokalit včetně nastavení připomínek a kalendáře. Neexportují se osobní rezervace, kalendářové tokeny ani historie změn.

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
- Kategorie FAQ se spravují samostatně: mají editovatelný slug, popis, `meta title`, `meta description` a veřejný odkaz `/faq/kategorie/{slug}`. Při změně slugu CMS uloží redirect ze staré URL.
- Přehled FAQ umí filtr `Potřebuje doplnit`, který vychází ze zpětné vazby návštěvníků na detailu odpovědi.

### Veřejný výpis a detail

Veřejná znalostní báze nově podporuje:

- fulltextové hledání
- filtr podle kategorie
- stránkování
- přepínání `Přehled karet / Rozbalené odpovědi`
- zachování kontextu při návratu z detailu otázky
- související otázky na detailu
- veřejné landing stránky kategorií `/faq/kategorie/{slug}` s popisem a SEO poli
- přístupnou zpětnou vazbu `Pomohla vám tato odpověď?` na detailu otázky
- `FAQPage` strukturovaná data pro vyhledávače

Detail otázky umí použít vlastní `meta title` a `meta description`. Pokud nejsou vyplněné, použije se otázka a shrnutí FAQ.

Zpětná vazba na odpověď je neveřejná redakční pomůcka. CMS ukládá hlas `pomohlo / nepomohlo`, volitelnou poznámku a anonymní hash návštěvníka pro deduplikaci opakovaného hlasu u stejné otázky; neukládá IP adresu ani e-mail.

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

Kora CMS drží produkční běh bez Composer závislostí. Adresář `vendor/` vzniká po `composer install` jen pro lokální vývojové nástroje a CI, například PHPStan a PHP-CS-Fixer; `node_modules/`, `.codex/`, `.cursor/` a podobná lokální editorová metadata patří také jen do pracovního checkoutu. Release balíček `dist/koracms-*.zip` záměrně neobsahuje ani `vendor/`, ani `node_modules/`, ani lokální AI/editor metadata, ani vývojové metadata soubory jako `composer.json`, `composer.lock`, `phpstan.neon.dist` nebo `.php-cs-fixer.dist.php`. Root `.htaccess`, testovací PHP router i Nginx ukázka navíc tyto lokální složky blokují i při přímém webovém požadavku, takže se nemají dát omylem stáhnout ani z pracovního webrootu. Instalační ZIP i source archive naopak povinně obsahují root `.htaccess`, `README.md`, `CHANGELOG.md`, `VERSION`, `LICENSE`, `NOTICE.md`, `config.sample.php`, `install.php`, `migrate.php`, `docs/admin-guide.md`, základní default šablonu, kritické CSS assety a ochranný `uploads/.htaccess` se stabilním casingem názvů. ZIP se vytváří explicitním `ZipArchive` průchodem souborů včetně dotfiles, aby ochranný `.htaccess` nevypadl z artefaktu na Linux runneru. Release skript před vytvořením nové verze spouští statický release package audit i `composer ci:basic`; přepínačem `-FullCi` lze vyžádat i `composer ci:full` s runtime a HTTP integrací. Self-test release package auditu v základní CI nad dočasnou kopií release guardů ověřuje, že audit opravdu selže na návratu `vendor`, `node_modules`, lokálních AI metadat, `Compress-Archive`, rozbitých release smoke kontrolách a chybějících pravidlech `.gitattributes` nebo `.gitignore`. Přepínač `-DryRun` projde stejný preflight a vytvoří ZIP se `.sha256` otiskem a náhledem nové verze, ale nemění pracovní `VERSION` ani `CHANGELOG.md`, nevytváří commit/tag/push a nezakládá GitHub release; náhled changelogu i ostrý release ponechá nahoře novou prázdnou sekci `Unreleased` pro další změny. `composer ci:basic` navíc spouští izolovaný release smoke test nad dočasným snapshot repozitářem, takže kontroluje i skutečné chování dry-run režimu bez zásahu do vašeho pracovního stromu i reálný `git archive` source balíček podle `.gitattributes`; oba artefakty musí zůstat bez lokálních IDE/AI metadat, `dist`, `vendor`, `node_modules`, citlivých konfigurací a uživatelských uploadů mimo `uploads/.htaccess`. Soubor `VERSION` je jediný zdroj pravdy pro runtime `KORA_VERSION` i release artefakty; `build/version_metadata_audit.php` hlídá, aby se lokální verze, dry-run ZIP a source archive nerozjely. Vedle ZIPu vzniká i `.sha256` soubor se SHA-256 otiskem artefaktu.

Kora CMS je licencovaný pod `GPL-3.0-or-later`. Plné znění licence je v root souboru `LICENSE` a krátké projektové oznámení v `NOTICE.md`; oba soubory jsou povinnou součástí instalačního ZIPu i source archivu. Licence se vztahuje na zdrojový kód CMS, přibalené šablony, skripty, styly a runtime assety distribuované s projektem, pokud konkrétní soubor neuvádí jinak. Obsah webu, nahraná média, databázová data a texty vložené správci nebo návštěvníky zůstávají pod licencí vlastníka webu nebo autora obsahu.

Veřejný changelog Kora CMS je dostupný na `/changelog`. Jde o read-only systémovou stránku, která vykresluje celý `CHANGELOG.md` včetně `Unreleased` v běžné veřejné šabloně. CMS ji záměrně nepřidává automaticky do hlavní navigace ani sitemapu; pokud ji má konkrétní web ukazovat návštěvníkům, přidejte odkaz ručně do menu, patičky nebo obsahu.

Přihlašování a obnova hesla používají kombinovaný rate limiting:

- limit podle IP adresy
- limit podle hashovaného účtu nebo reset tokenu
- bez ukládání e-mailu nebo tokenu v čitelné podobě do tabulky `cms_rate_limit`

To chrání nejen proti opakovaným pokusům z jedné adresy, ale i proti útokům rozloženým přes více IP adres na stejný účet.
Výchozí 429 odpověď při překročení limitu posílá `Retry-After`, explicitní HTML typ a necacheovací/noindex/no-referrer hlavičky, takže se odpověď neukládá v cache ani indexu a klient dostane srozumitelný čas pro opakování požadavku. HTML odpověď má skutečný nadpis a kód požadavku pro podporu, stejně jako globální chybová stránka.

Návrat po administrátorském přihlášení zachovává původně otevřenou stránku jen tehdy, když jde o bezpečný interní cíl v administraci nebo `migrate.php`. Cíle mimo administraci, externí URL, protocol-relative adresy a návrat zpět na `admin/login.php` nebo `admin/login_2fa.php` se ignorují a uživatel skončí na dashboardu. Tuto ochranu hlídají unit testy i runtime audit, aby se login flow nestal otevřeným redirectem.

Správa `301/302` přesměrování dovoluje jako starou adresu jen interní cestu webu. Nová adresa může být interní cesta nebo úplná `http://` či `https://` URL, ale CMS odmítá nebezpečná schémata, protocol-relative URL, CRLF znaky a adresy s přihlašovacími údaji; stejná validace platí i pro automatické redirecty při změně slugů.

Řádková editace existujících přesměrování v tabulce používá skutečné skryté popisky polí (`label` + `id`) místo samotných `aria-label`, takže čtečka obrazovky oznamuje starou cestu, novou cestu a typ přesměrování stejně spolehlivě jako v hlavním formuláři.

Řádkové smazání přesměrování je POST akce s vlastním review textem. Správce musí zaškrtnout potvrzení u konkrétního redirectu; server bez něj požadavek odmítne s textovým alertem a field-level chybou, aniž smaže záznam nebo zapíše audit log.

Koš v administraci funguje jako review krok před nevratným odstraněním. Běžné mazání přesune obsah do koše, trvalé smazání v koši zobrazuje typ, název a datum smazání v řádku, vyžaduje samostatné potvrzení checkboxem u konkrétní položky a server odmítne purge bez tohoto potvrzení. Chybějící potvrzení se vrátí na konkrétní položku s textovým alertem, `aria-invalid` a field-level chybou u checkboxu. HTTP integrace zároveň ověřuje, že nepotvrzené trvalé smazání položku neodstraní, nezapíše audit log a potvrzené smazání projde s PRG stavem.

Stejný princip se používá i při změně role uživatelského účtu. Editační formulář ukazuje aktuální a novou roli, změna oprávnění vyžaduje potvrzovací checkbox a server nepotvrzenou změnu odmítne bez uložení nové role. Běžné úpravy profilu, které roli nemění, tímto potvrzením blokované nejsou.

Mazání uživatelského účtu má podobný řádkový review krok ve správě uživatelů. Formulář popíše dopad na přístup do CMS a osobní administrační zkratky, vyžaduje potvrzovací checkbox u konkrétního účtu a server bez potvrzení nesmaže účet, zkratky ani nezapíše audit log. Potvrzené smazání současně uklidí osobní zkratky daného uživatele.

Mazání nepoužitého média má stejný review krok v kartě souboru. Správce před odesláním vidí, že se odstraní fyzický soubor, metadata a odvozené miniatury, musí potvrdit `confirm_media_delete_<id>` a server bez potvrzení nesmaže databázový záznam, soubor ani audit log.

Mazání kategorií a sérií Ke stažení používá stejný princip v řádkových akcích. Správce vidí dopad na navázané položky, musí potvrdit kontrolu checkboxem a server bez `confirm_download_category_delete_<id>` nebo `confirm_download_series_delete_<id>` nezruší vazby, nesmaže taxonomii ani nezapíše audit log.

Mazání kategorií vývěsky a typů akcí používá stejný review krok. Správce vidí počet navázaných položek vývěsky a odběrů nebo počet událostí, musí potvrdit kontrolu checkboxem a server bez `confirm_board_category_delete_<id>` nebo `confirm_event_type_delete_<id>` nezmění vazby, nesmaže taxonomii ani nezapíše audit log.

Mazání témat kontaktu a chatu používá stejný review krok. Správce vidí dopad na veřejný kontaktní formulář nebo chat a na existující zprávy, musí potvrdit kontrolu checkboxem a server bez `confirm_contact_topic_delete_<id>` nebo `confirm_chat_topic_delete_<id>` nezmění vazby zpráv, nesmaže téma ani nezapíše audit log.

Mazání rezervačních kategorií a míst používá stejný review krok. Správce vidí počet navázaných rezervačních zdrojů a dopad na existující zdroje/rezervace, musí potvrdit kontrolu checkboxem a server bez `confirm_res_category_delete_<id>` nebo `confirm_res_location_delete_<id>` nezmění vazby zdrojů, nesmaže číselník ani nezapíše audit log.

Mazání rezervačního zdroje má samostatný review krok. Správce vidí počet budoucích nezrušených rezervací, vazeb na místa, pravidel otevírací doby, slotů a blokovaných dnů, musí potvrdit kontrolu checkboxem a server bez `confirm_res_resource_delete_<id>` nezruší budoucí rezervace, nesmaže dostupnost, zdroj ani nezapíše audit log.

Mazání blogových kategorií, štítků a sérií článků používá stejný review krok. Správce vidí počet navázaných článků a u kategorie i počet podkategorií, musí potvrdit kontrolu checkboxem a server bez `confirm_blog_category_delete_<id>`, `confirm_blog_tag_delete_<id>` nebo `confirm_blog_series_delete_<id>` nezmění vazby, nesmaže taxonomii ani nezapíše audit log.

Mazání FAQ kategorií používá stejný review krok. Správce vidí počet navázaných otázek a podkategorií, musí potvrdit kontrolu checkboxem a server bez `confirm_faq_category_delete_<id>` neodpojí otázky, nepřesune podkategorie na kořen, nesmaže kategorii ani nezapíše audit log.

Mazání celého blogu má samostatný review krok. Správce vidí počet článků, kategorií, štítků, sérií a týmových přiřazení, musí potvrdit kontrolu checkboxem a server bez `confirm_blog_delete_<id>` nepřesune obsah, neodstraní série, nezruší týmové vazby, nesmaže blog ani nezapíše audit log.

Odebrání widgetu ve správě widgetů používá stejný review-and-confirm pattern. Správce před odesláním vidí název widgetu, typ, zónu a dopad na veřejné zobrazení; server bez `confirm_widget_delete_<id>` widget nesmaže ani nezapíše audit log.

Newsletter composer před odesláním rozesílky ukazuje počet potvrzených a čekajících odběratelů. Odeslání vyžaduje potvrzovací checkbox; pokud správce odešle formulář bez potvrzení, server rozesílku odmítne a nevytvoří záznam v historii newsletteru.

Hromadné akce nad odběrateli newsletteru používají stejnou serverovou pojistku. Před potvrzením vybraných odběrů, znovuodesláním potvrzovacích e-mailů nebo smazáním vybraných odběratelů musí správce zaškrtnout kontrolní checkbox; bez něj server akci odmítne a data ani tokeny odběratelů nezmění.

Individuální mazání odběratele newsletteru má stejný review krok v přehledu i detailu odběratele. Formulář popisuje, že se odstraní e-mail z aktivních odběrů a historie odeslaných rozesílek zůstane zachovaná; bez `confirm_newsletter_subscriber_delete_<id>` server odběratele nesmaže ani nezapíše audit log.

Hromadné akce nad odpověďmi Form Builderu navazují stejným principem. Před změnou stavu nebo trvalým smazáním vybraných odpovědí musí správce potvrdit, že zkontroloval výběr i zvolenou akci; bez potvrzení se odpovědi, přílohy, historie ani audit log nezmění.

Individuální mazání odpovědi Form Builderu používá stejný review krok v přehledu odpovědí i detailu odpovědi. Správce vidí referenci, stav, počet záznamů interní historie a počet nahraných souborů uložených v odpovědi; bez `confirm_form_submission_delete_<id>` server nesmaže odpověď, historii, přílohy ani nezapíše audit log.

Mazání celého Form Builder formuláře používá stejný review-and-confirm pattern přímo v přehledu formulářů. Formulář popíše veřejnou URL, počet polí, odpovědí, záznamů historie odpovědí a dopad na nahrané soubory; bez `confirm_form_delete_<id>` server nesmaže formulář, pole, odpovědi, historii ani nezapíše audit log.

Hromadné akce nad alby galerie používají stejný review-and-confirm pattern. Před smazáním vybraných alb nebo ZIP exportem fotografií musí správce potvrdit, že zkontroloval výběr i zvolenou akci; bez potvrzení se alba, fotografie a revize nesmažou a ZIP export se neodešle ani nezapíše do audit logu.

ZIP export šablony používá podobný review-and-confirm pattern pro přenos vzhledu mezi instalacemi. Před stažením musí správce potvrdit, že zkontroloval vybranou šablonu a má oprávnění balíček sdílet; bez potvrzení se ZIP neodešle ani nezapíše audit log.

CSV export statistik obsahu používá review-and-confirm pattern pro interní metriky výkonu obsahu. Před stažením musí správce zkontrolovat období, filtr modulu a počet řádků, potvrdit oprávnění a server bez potvrzení neodešle CSV ani nezapíše audit log.

CSV export audit logu používá review-and-confirm pattern pro provozní a bezpečnostní záznamy administrace. Před stažením musí správce zkontrolovat aktuální filtr, počet záznamů a citlivost detailů akcí, potvrdit oprávnění a server bez potvrzení neodešle CSV ani nezapíše audit log.

Sdílený helper hromadných akcí pro běžné administrační moduly používá pro smazání obecný review-and-confirm pattern. Před `delete` akcí musí správce zaškrtnout `confirm_bulk_delete`; bez něj `admin/bulk.php` požadavek odmítne ještě před module-specific cleanupem, smazáním záznamů a zápisem audit logu. Publikování a skrývání zůstává bez tohoto potvrzení, protože nejde o trvalé smazání.

Recoverable chyby v administraci a souborových cleanupech se postupně převádějí na strukturovaný `koraLog()` formát. Globální neošetřené chyby ukládají jen název souboru a hash cesty, ne plnou lokální cestu; chybová stránka návštěvníkovi ukáže bezpečný kód požadavku pro podporu a odpověď je necacheovatelná. Ukládání článků, přesun článků mezi blogy, ukládání anket, cleanup šablon, mazání prezentačních souborů, import fotek z eStránek i dílčí selhání přehledů na administračním dashboardu tak v technickém logu nespoléhají na surové `error_log()` zprávy, ale přidávají `request_id`, metodu, cestu a omezený kontext bez dumpu celé žádosti nebo plných lokálních cest. Dashboard u počítadel používá jen sekci, počet parametrů a krátký hash dotazu, ne celý SQL text.

Přepínač veřejné registrace v obecném nastavení blokuje registrační formulář a zároveň schovává odkazy na registraci ve veřejné přihlašovací obrazovce i ve společné patičce webu. Pokud jsou zapnuté rezervace, zůstane návštěvníkům dostupný odkaz na přihlášení, ale nové účty může při vypnuté registraci zakládat jen oprávněný správce.

Veřejná registrace a žádost o obnovu hesla nevyžadují matematickou CAPTCHA ani jiný kognitivní test. Ochranu proti automatizovaným pokusům zajišťují CSRF tokeny, rate limiting a skrytý honeypot, takže auth flow zůstává použitelnější pro správce hesel, čtečky obrazovky i uživatele s kognitivní zátěží. Přihlašovací pole používají `autocomplete="username"` a `current-password`, nová hesla `new-password` a 2FA kód `one-time-code` s numeric patternem; chybové stavy admin loginu a 2FA se oznamují jako textové alerty.

Veřejný kontakt, objednávkové poptávky ve Food modulu, guest rezervace, veřejný chat, odpovědi v chatu, blogové komentáře a formuláře vytvořené přes Form Builder používají `autocomplete` metadata pro běžná osobní pole. Form Builder automaticky nastavuje účel pro pole typu e-mail, telefon a URL a u zjevně pojmenovaných krátkých textových nebo datumových polí také pro jméno, křestní jméno, příjmení, organizaci, pracovní pozici, datum narození a adresní údaje jako ulice, PSČ, město, kraj nebo země. Pokud nový formulář sbírá další specializované údaje, například platební údaje, je potřeba doplnit explicitní návrh a ověření podle accessibility protokolu.

Veřejný kontakt, Food objednávka, veřejný chat, odpověď v chatu a blogový komentář zároveň u přihlášeného veřejného uživatele předvyplňují známé jméno a e-mail z profilu přes `currentUserContactDefaults()`; kontakt a Food navíc předvyplňují telefon. Pokud návštěvník hodnotu ve formuláři ručně změní a formulář spadne na validační chybě, CMS zachová odeslanou hodnotu a nepřepíše ji znovu profilem. Přihlášené veřejné rezervace kontaktní údaje znovu nevyžadují; při uložení vytvoří snapshot jména, e-mailu a telefonu ze stejného profilového helperu.

Veřejné formuláře s matematickou ověřovací otázkou mají u chybné odpovědi sdílenou field-level hlášku, která kromě identifikace chyby radí přepočítat příklad a zadat jen číslo. Týká se kontaktu, newsletter subscribe, odběru vývěsky, objednávkových poptávek ve Food modulu, guest rezervací a Form Builder formulářů.

Veřejná custom pole ve Form Builderu mají konkrétní návrhy oprav i mimo ověřovací otázku. Povinná textová, výběrová, souhlasová a souborová pole říkají, co má návštěvník doplnit; e-mail radí úplnou adresu ve tvaru `jmeno@example.cz`, URL radí http/https adresu bez přihlašovacích údajů, nepovolená výběrová hodnota vede uživatele zpět k nabízeným možnostem a upload připomíná povolený typ a velikost souboru.

Validační hlášky v administraci mají u známých oprav říkat, jak pokračovat. URL pole u zajímavých míst a podcastů proto vysvětlují povolený `http://`/`https://` tvar, doménu bez schématu s automatickým uložením jako `https://` a kdy je bezpečné volitelné pole nechat prázdné.

Editor Form Builderu se drží stejného pravidla u názvu, slugu, notifikačního e-mailu, potvrzovacího e-mailového pole a webhook URL. Chyba má správci říct konkrétní další krok: doplnit název, zvolit jiný nebo prázdný slug, zadat úplnou e-mailovou adresu, přidat/vybrat e-mailové pole formuláře nebo použít veřejně dostupný HTTPS webhook endpoint.

Editor statických stránek používá stejný field-level pattern pro název, slug, přiřazený blog a plánovaná data publikování/skrytí. Souhrnný alert upozorní, které pole potřebuje opravu, a lokální text poradí konkrétní další krok.

Editor FAQ používá stejný field-level pattern pro povinnou otázku, odpověď a slug veřejné stránky. Souhrnný alert je atomický a lokální text poradí, jak otázku formulovat pro návštěvníka, doplnit odpověď nebo zadat unikátní slug z malých písmen, číslic a pomlček.

Editor FAQ kategorií navazuje u názvu, slugu a meta title. Chyba má správci poradit krátký název kategorie, slug s použitelným písmenem nebo číslem, jiný unikátní slug nebo zkrácení meta title na 160 znaků.

Mazání FAQ kategorií má vlastní kontrolu dopadu. Před odesláním správce vidí počet otázek, které zůstanou bez kategorie, a počet podkategorií přesouvaných na kořen; bez potvrzení `confirm_faq_category_delete_<id>` server akci odmítne.

Blogové kategorie a štítky používají stejný princip u povinného názvu a duplicitního slugu. Chyba má správci poradit krátký srozumitelný název, jiný unikátní slug nebo prázdné slug pole pro automatické vytvoření.

Kategorie a série ke stažení stejný princip používají u prázdného názvu, nepoužitelného nebo duplicitního slugu a u příliš dlouhého meta title kategorie. Chyba má být napojená na existující text přes `aria-describedby`, pole má být označené jako neplatné a text má správci říct, jakou hodnotu zkusit nebo že může slug nechat prázdný.

Stejný field-level pattern používají kategorie vývěsky, typy akcí, témata kontaktu/chatu a rezervační kategorie/místa. U povinného názvu, nepoužitelného nebo duplicitního slugu a příliš dlouhého meta title má souhrnný alert upozornit na dotčené pole, lokální text poradit konkrétní opravu a `aria-describedby` mířit jen na existující nápovědu nebo chybový text. Rezervační kategorie a místa navíc po chybě zachovávají rozepsané hodnoty.

Hlavní navigační externí odkazy, přesměrování, alba galerie, fotografie galerie a ruční vytvoření rezervace navazují na stejný pattern. U navigace a redirectů má chyba vysvětlit povinný název, interní cestu začínající lomítkem nebo úplnou http/https adresu bez přihlašovacích údajů. U galerie má poradit jedinečný slug, platné nadřazené album, úplnou URL licence nebo skutečné kalendářní datum pořízení. U ruční rezervace má správce dostat konkrétní informaci k výběru zdroje, uživatele nebo hosta, data, času a konfliktu rezervace.

Editor článku, správa blogů, položky vývěsky, jídelní/nápojové lístky a ankety už u hlavních chyb nemají zůstat u obecného „povinné“ nebo „neplatné“. Souhrnný alert má být atomický a lokální text má poradit konkrétní opravu: doplnit titulek/název/text, zvolit jedinečný slug, vybrat položky ze stejného cílového blogu, opravit kategorii, datum vyvěšení, možnosti odpovědi nebo limit vícevýběru.

Editory novinek, událostí, míst, položek ke stažení a podcastových epizod navazují na stejný vzor. Chyba má správci poradit doplnění názvu nebo textu, jedinečný slug, opravu termínů a opakování akce, výběr existujícího typu/místa/série, souřadnice v platném rozsahu nebo vhodný čtvercový obrázek epizody.

GitHub issue bridge v detailu odpovědi Form Builderu používá stejný field-level pattern. Chybějící repozitář, název nebo tělo issue a neplatná URL existujícího issue mají vedle souhrnného alertu konkrétní opravu přímo u pole.

E-mailová pole ve vývěsce, událostech, jídelních lístcích, Form Builderu, tématech kontaktu, místech, podcastech, nastavení, profilu a správě uživatelů nepoužívají pouze obecný „platný formát“. Chyba má správci poradit úplnou adresu ve tvaru `jmeno@example.cz`; u volitelných polí možnost pole vynechat a u přihlašovacího e-mailu jedinečnost adresy.

Běžná datumová pole ve vývěsce, jídelních lístcích a položkách ke stažení nepředpokládají ruční znalost formátu. Chyba má správci poradit výběr kalendářního data, prázdné volitelné pole nebo opravu pořadí od/do; datum vydání u downloadů má field-level chybu napojenou přes `aria-describedby`.

Položky ke stažení mají stejný field-level pattern i pro zdroj a metadata souboru. Pokud chybí lokální soubor i externí odkaz, formulář vysvětlí obě použitelné cesty. Neplatná externí URL nebo domovská stránka projektu radí `http://`/`https://` adresu nebo doménu bez schématu a u volitelné domovské stránky i možnost nechat pole prázdné. SHA-256 checksum radí přesných 64 znaků `0-9` a `a-f`, připomíná ignorování mezer a automatický dopočet u lokálního souboru.

Obrazové uploady v článcích, vývěsce, událostech, místech a položkách ke stažení mají správci poradit povolené formáty JPEG, PNG, GIF nebo WebP, zákaz SVG a možnost volitelné pole neměnit. Náhledový obrázek u downloadů používá stejné field-level `aria-describedby` napojení jako ostatní validační chyby.

Přílohy vývěsky a audio soubory podcastových epizod mají podobně konkrétní field-level nápovědu. Příloha vývěsky radí povolené formáty PDF, Office/OpenDocument, ZIP a TXT; audio epizody radí MP3, OGG, WAV, M4A nebo AAC a připomíná, že při použití externího audio odkazu má upload pole zůstat prázdné.

Knihovna médií používá stejný princip pro hromadný upload i náhradu existujícího souboru. Chyba zůstává nahoře jako alert, ale po redirectu se současně napojí na file input přes `aria-describedby`; text správci připomene podporovaný formát do nastaveného limitu uploadu, zákaz SVG, u náhrady stejnou MIME rodinu a u veřejného souboru zachování přípony. Limit běžných uploadů se nastavuje v obecných nastaveních webu v MB; hosting zároveň musí povolit odpovídající hodnoty `upload_max_filesize` a `post_max_size`.

U plánování publikace, ukončení publikace, rezervační dostupnosti a časových rozsahů CMS nepředpokládá ruční znalost formátu. Pokud prohlížeč pošle neplatnou hodnotu, chyba má správci poradit, aby znovu vybral datum a čas v ovládacím prvku, volitelné plánování nechal prázdné, odstranil prázdný řádek nebo opravil pořadí začátku a konce.

Vývojové kontroly:

- `composer ci:basic` spustí PHP lint včetně self-testu `build/lint_php_selftest.php` pro syntakticky rozbité PHP soubory a ignorování `vendor`, `dist` a `uploads`, `composer validate --strict`, repository guardrails audit včetně self-testu rezervovaných DB připojovacích proměnných i nechtěně verzovaných lokálních konfigurací, `.env` souborů, `vendor`, `node_modules`, `dist`, IDE/AI metadat a reálných uploadů, config sample audit `build/config_sample_audit.php` včetně self-testu `build/config_sample_audit_selftest.php` pro sladění `config.sample.php` s hlavní runtime konfigurací, bezpečnými výchozími hodnotami, prázdnými tokeny a SMTP defaultem `localhost:25`, version metadata audit `build/version_metadata_audit.php` včetně self-testu `build/version_metadata_audit_selftest.php` pro `VERSION` a release artefakty, schema parity audit `build/schema_parity_audit.php` včetně self-testu `build/schema_parity_audit_selftest.php` pro kritické sloupce sdílené mezi `install.php`, `migrate.php` a veřejným kódem, redirect guardrails audit včetně self-testu pro bezpečnou validaci návratových cílů z requestu a formulářů, audit GitHub Actions workflow včetně self-testu připnutých actions a zakázaných write/secrets vzorů, source encoding audit platného UTF-8 včetně self-testu `build/source_encoding_audit_selftest.php`, mojibake audit typických zkomolených českých znaků včetně self-testu `build/mojibake_audit_selftest.php`, whitespace audit koncových mezer a finálního nového řádku včetně self-testu `build/whitespace_audit_selftest.php`, release package audit včetně self-testu `build/release_package_audit_selftest.php` pro ochranu release skriptu, release smoke kontrol, `.gitattributes` a `.gitignore`, úzký PSR-12 smoke check přes PHP-CS-Fixer včetně build/test helperů, PHPStan na levelu 6 včetně self-testu `build/phpstan_bootstrap_selftest.php` pro bezpečný bootstrap bez databáze, autentizace, session a runtime konfigurace, statický release package audit a unit testy; PHPStan používá bezpečný bootstrap `build/phpstan_bootstrap.php` a symbol scan, takže zná sdílené helpery bez načítání databáze nebo session a hlídá i release/testovací nástroje
- Self-test `build/http_server_router_selftest.php` ověřuje vestavěný router pro Full CI nad dočasnou mini-instalací bez databáze: clean URL routy, statické soubory, blokování chráněných cest, zachování query parametrů a 404 fallback.
- Self-test `build/http_test_helpers_selftest.php` ověřuje HTTP test helpery nad dočasným PHP serverem: GET/POST/raw/multipart požadavky, redirecty, cookies, parser skrytých polí i refresh testovací CSRF session pro integrační scénáře.
- Self-test `build/test_run_lock_selftest.php` ověřuje sdílený lock DB auditů ve dvou procesech: druhý proces nesmí pokračovat, dokud první neuvolní zámek, takže paralelní ruční spuštění `runtime_audit` a `http_integration` nevyrábí falešné pády nad stejnou lokální databází.
- Theme view audit `build/theme_view_audit.php` běží v `composer ci:basic` a chrání default šablonu před tím, aby se do PHP view souborů vrátila přímá práce s request inputem, session/server stavem, databází, změnou hlaviček, souborovými zápisy, runtime časem, inline styly, inline event handlery nebo script tagy bez CSP nonce. Navíc hlídá statická duplicitní `id`, statické odkazy `aria-labelledby` / `aria-describedby` / `aria-controls` na neexistující prvky, statické `label for` bez cílového pole, veřejné `<section>`, `<nav>`, `<aside>`, `role="search"` a všechny veřejné `<article>` prvky bez `aria-labelledby`, veřejné `<figure>` bloky bez `aria-labelledby` nebo `figcaption`, formulářová pole bez labelu nebo ARIA názvu, `<fieldset>` bez `<legend>`, obrázky bez `alt`, iframe bez `title`, tlačítka bez explicitního `type`, veřejné tabulky bez `<caption>` nebo `aria-labelledby` a odkazy s `target="_blank"` bez `rel="noopener noreferrer"` nebo bez skrytého textu přímo uvnitř odkazu, který výslovně oznamuje otevření v novém okně. U veřejných šablon už audit netoleruje atributové `aria-label` jako náhradu tohoto dovětku, jediný název tabulky, jediný název landmarku ani náhradu fieldset legendy, aby se přístupný název neodpojil od viditelného nebo dohledatelného textu. Společné hodnoty jako přihlášený administrátor, aktuální datum a aktuální URL šablona dostává přes view data z `renderPublicPage()`. Self-test `build/theme_view_audit_selftest.php` na dočasných fixtures ověřuje, že audit umí projít čistou šablonu a selhat na zakázaných vzorech i rozbitých ARIA/formulářových vazbách.
- Runtime audit stejná pravidla ověřuje i ve zdrojích a na reálně vyrenderovaných veřejných a administračních odpovědích. Vedle duplicitních `id`, rozbitých ARIA vazeb, chybějících labelů a `alt` textů nově hlídá také POST formuláře bez `csrf_token`, iframe bez `title` a odkazy s `target="_blank"` bez `rel="noopener noreferrer"` nebo bez přístupného oznámení nového okna.
- Runtime audit vícerádkově hlídá explicitní `type` u `<button>` a `<input>` v PHP zdrojích `admin/`, `lib/` a `themes/` a stejný požadavek ověřuje i na reálně vyrenderovaném HTML. Cílem je, aby tlačítka bez záměru neodesílala formulář implicitním výchozím chováním a aby formulářová sémantika zůstala stabilní i při zalomení atributů na více řádků.
- Runtime audit u administrace hlídá také odkazy otevírané v novém okně. Pokud má `admin/*.php` odkaz `target="_blank"`, musí používat `rel="noopener noreferrer"` a informaci o novém okně jako skrytý text přímo uvnitř odkazu, ne samostatný `aria-label`. Stejná kontrola pokrývá i dynamicky vytvořené `_blank` odkazy v JavaScriptu a `window.open()`, které musí oddělit nové okno přes `noopener,noreferrer`.
- `composer ci:basic` navíc obsahuje i izolovaný `test:release-smoke`, který v dočasném snapshot repozitáři skutečně provede `build/release.ps1 -DryRun -SkipCi`, ověří čistý git stav po doběhu, zkontroluje výsledný ZIP i checksum a navíc ověří, že `git archive` opravdu respektuje `export-ignore` pravidla pro `build/`, `docs/`, `dist/`, `vendor/`, `node_modules/`, lokální metadata a citlivé konfigurace; ZIP i source archive zároveň musí obsahovat kritické instalační soubory včetně root `.htaccess`, `config.sample.php`, `install.php`, `migrate.php`, default šablony a `uploads/.htaccess`
- `composer ci:full` navíc po `ci:basic` sekvenčně spustí `php build/runtime_audit.php` a `php build/http_integration.php`, takže je vhodný hlavně pro lokální ověření většího balíku změn nebo před releasem; při ručním paralelním spuštění nad stejnou lokální databází sdílený lock v `build/test_run_lock.php` druhý běh pozdrží, protože tyto kontroly používají dočasná testovací nastavení; release skript ho umí spustit přepínačem `-FullCi` a bezpečnou zkoušku release bez zápisu do gitu přes `-DryRun`
- Vypnutý modul se blokuje i v administraci. Hlavní přehledy modulů hlídá manifest `admin_paths` a přímé formulářové, detailové a stav měnící admin endpointy chrání centrální mapa `adminRouteModuleRequirement()` v `auth.php`; HTTP integrace ověřuje i přímý POST na vypnutý modul.
- Tokenové odkazy, které mění stav přes tajný `GET` odkaz, jsou metodově omezené. Potvrzení e-mailu, potvrzení nebo odhlášení newsletteru a veřejné i administrační odhlášení odmítají `POST`, `HEAD` a další nečekané metody pomocí `405` a `Allow: GET`; runtime audit i HTTP integrace hlídají, aby kontrolní nebo chybné HTTP požadavky neměnily účet, odběr ani session.
- Dlouho otevřené editory existujícího obsahu obnovují zámek obsahu na pozadí bez rotace hlavního CSRF tokenu. Po úspěšném heartbeat požadavku administrace synchronizuje aktuální token do všech formulářů na stránce, takže delší editace článku, blogové stránky, novinky, události nebo Vývěsky nemá skončit chybou `Neplatný bezpečnostní token`.
- GitHub Actions drží dva oddělené workflow: `.github/workflows/ci.yml` pro běžné `push`/`pull_request` s `composer ci:basic` a `.github/workflows/full-ci.yml` pro ruční a noční běh plného `composer ci:full`; plný workflow si připraví MySQL, `config.php`, vestavěný PHP server a čerstvou instalaci CMS, takže runtime audit a HTTP integrace mají vzdálený guardrail bez zpomalení každého commitu
- Oba GitHub Actions workflow používají minimální `contents: read` oprávnění, řízení souběhu a job timeouty, aby se kvalita hlídala s menším oprávněním a bez visících běhů
- `composer format:fix` umí stejnou úzkou sadu helperů lokálně dorovnat do PSR-12 bez zásahu do širšího historického kódu; momentálně pokrývá lint/bootstrap helpery a první stabilní várku sdílených knihoven (`backup`, `comments`, `content`, `definitions`, `filedownloads`, `gallery`, `github`, `mail`, `media_library`, `messages`, `pagination`, `presentation`, `revisions`, `stats`, `theme`, `totp`, `ui`, `uploads`, `webhooks`, `widgets`)
- `composer analyse:strict` už na levelu 6 vedle základních helperů pokrývá 245 stabilizovaných souborů napříč veřejnými entrypointy, sdílenými knihovnami, workflow auditem, redirect guardraily a rozšiřovanou sadou admin workflow pro blogy, stránky, média, formuláře, podcasty, FAQ, události, ankety, místa, rezervace, widgety, komentáře, kontakty, chat, novinky, soubory ke stažení, jídelní a nápojové lístky, kategorie, newsletter, uživatele, galerii, převod obsahu, reorder endpointy a jednoduché akční endpointy; ta část kódu proto nově drží přesnější array kontrakty i bez baseline a bez ignore pravidel
- Veřejné i administrační požadavky dostávají `X-Request-ID`; globální neošetřené chyby a vybrané technické chyby se zapisují jako JSON záznamy se stejným `request_id`, metodou a cestou. Při dohledávání produkční chyby tak stačí porovnat ID z odpovědi nebo monitoringu s PHP logem; u neošetřené chyby se stejný kód zobrazí i na chybové stránce. Strukturovaný zápis se používá i pro dílčí obnovitelné chyby veřejného blogu, detailu článku, vyhledávání, sitemapy, veřejných formulářů, chatu, kontaktu, stažení souboru a newsletterových potvrzovacích akcí, kde má stránka pokračovat ve vykreslení, ale log musí ukázat selhaný zdroj nebo sekci. Stejný bezpečný zápis používají i vybrané administrační přehledy, například media picker/content reference search, formuláře a statistiky, bez ukládání hledaných výrazů, obsahu zpráv nebo tokenů do kontextu logu. Sdílené helpery pro zámky obsahu, revize, widgety, použití médií, formulářové webhooky, e-mailové notifikace, souborové operace a cron cleanup logují jen technický kontext typu operace, entity, zóny, interní tabulky, webhook eventu, hostu endpointu, HTTP stavu, domény příjemce, SMTP fáze, hashe cesty nebo přípony souboru; celé webhook URL, tělo odpovědi protistrany, celé e-mailové adresy, surové SMTP odpovědi ani fyzické cesty k souborům se do logu neukládají.
- `health.php` kromě databáze, privátního úložiště a orientačního stavu záloh uvádí i čas poslední nalezené SQL zálohy a čerstvost posledního běhu cronu. Podporuje jen `GET` a `HEAD`; ostatní metody vrací sdílenou JSON `405` odpověď s `Allow: GET, HEAD`, bezpečnostními/no-store hlavičkami a `request_id`. Cron při každém běhu uloží `cron_last_run_at`; health check ho hlásí jako `ok`, `stale` nebo `unknown`, aniž by čerstvá instalace bez prvního cronu hned spadla do chyby. Monitoring odpověď dostává s `Cache-Control: no-store`, aby se nevyhodnocoval starý stav z cache.
- CSP se na veřejných odpovědích posílá i v režimu `Content-Security-Policy-Report-Only`. Prohlížeče tak mohou hlásit podezřelé nebo chybějící zdroje na `csp-report.php`, aniž by se návštěvníkovi rozbil legitimní obsah; běžné inline styly jsou v politice výslovně povolené přes `style-src-elem` a `style-src-attr` a starší inline-style reporty endpoint přijme bez zápisu do JSONL, aby log neplnil očekávaný šum z historických admin šablon. Endpoint přijímá jen `POST`, nepovolené metody odmítá sdílenou JSON `405` odpovědí s `Allow: POST`, chybové JSON odpovědi doplňuje o `request_id`, neposílá cacheovatelný obsah, ukládá jen očištěné JSONL záznamy do privátního úložiště `logs/csp_reports-YYYY-MM-DD.jsonl`, má vlastní rate limit proti zahlcení logů a cron čistí report soubory starší než 30 dní.
- JSON provozní endpointy pro monitoring a CSP reporty posílají vedle `Content-Type: application/json` také `X-Content-Type-Options: nosniff`, aby prohlížeč ani mezilehlá vrstva nehádaly jiný typ obsahu.
- CSP allowlist výslovně obsahuje jen externí zdroje, které CMS samo vkládá pro Google Analytics a volitelný Quill editor. Externí GA/Quill skripty dostávají nonce a runtime audit hlídá, aby se nové CDN skripty v administraci nepřidávaly bez nonce.
- Veřejné theme CSS proměnné se vykreslují přes `<style>` blok s CSP nonce; runtime audit hlídá jak helper v `lib/theme.php`, tak reálný výstup homepage.
- Sdílené UI helpery v `lib/ui.php` používají pro cookie lištu, veřejný admin bar a klávesnicový fallback řazení CSS třídy a `hidden` místo lokálních `style` atributů nebo JS `element.style` mutací; veřejný admin bar je zároveň pojmenovaný skutečným skrytým nadpisem přes `aria-labelledby`. Runtime audit hlídá, aby se starý vzor nevrátil.
- Logo v hlavičce default šablony má název webu jako skutečný skrytý text přímo uvnitř odkazu, ne jako samostatný `aria-label`; čtečky obrazovky tak dostávají stejný název odkazu z DOM obsahu a runtime audit hlídá návrat starého vzoru.
- Pomocné navigace v administraci, například dashboardové rychlé odkazy, sekce nastavení, filtry komentářů, kontaktních a chatových zpráv, odpovědí formulářů, newsletteru, fronty ke schválení a stránkování rezervací, používají heading-backed `aria-labelledby` místo samostatného `aria-label` na navigačním landmarku.
- Číselné odznaky v levé administrátorské navigaci používají viditelné číslo jen vizuálně a úplný stav jako skrytý text uvnitř odznaku, například `3 nových chat zpráv`; nepoužívají samostatný `aria-label`, takže je popisek ověřitelný jako běžný DOM obsah.
- Skupinové prvky mimo navigační landmarky, například jídelní taby, rezervační časové sloty, výsledky anket, veřejný chat a souhrny návštěvnosti, používají `aria-labelledby` napojené na skutečný nadpis nebo legendu místo samostatného `aria-label`.
- Grafy návštěvnosti a provozních statistik v administraci používají nativní `<progress>` prvky pojmenované přes `aria-labelledby` na skutečný text dne/měsíce a hodnoty; pokud je ve sloupci vidět jen číslo, kontext typu `rezervací` nebo `komentářů` je doplněný skrytým textem uvnitř stejné hodnoty.
- Živý náhled šablony používá text v banneru jako název stavové oblasti a veřejný rezervační kalendář má skrytý `<caption>`, takže i tyto výstupy mají skutečný textový popisek místo samostatného `aria-label`.
- Stavová potvrzení ve veřejné autentizační, účtové, newsletterové, formulářové, komentářové, anketní a rezervační části používají `role="status"` nebo `role="alert"` s `aria-atomic="true"` a vlastním textovým uzlem přes `aria-labelledby`, aby potvrzení, chyby a informační stavy nebyly pro čtečky obrazovky anonymní.
- Veřejné chybové alerty u přihlášení, registrace, obnovy hesla, profilu, komentářů, chatu, kontaktu a Form Builderu mají skrytý textový nadpis přímo uvnitř hlášky. U vložitelných formulářů se ID hlášky odvozuje od konkrétního formuláře, aby se při více embedech na jedné stránce neduplikovalo.
- Sdílené veřejné stavové stránky, například potvrzení e-mailu nebo newsletterové potvrzení a odhlášení, používají stejný princip: pokud mají být oznámeny čtečce obrazovky, stavová zpráva má `aria-atomic="true"` a `aria-labelledby` napojené na první textový odstavec zprávy.
- Dostupné dny ve veřejném rezervačním kalendáři mají plný text data a dostupnosti jako skrytý text přímo uvnitř odkazu; viditelné číslo dne a stav zůstávají vizuálně stejné, ale odkaz už nespoléhá na samostatný `aria-label`.
- Pole `Max. rezervací` v editoru rezervačních zdrojů má skutečný skrytý `label` i pro dynamicky přidané sloty; nepoužívá tooltipový `title` jako náhradu popisku pole.
- V přehledu fotografií galerie vypnutá tlačítka `Nahoru` / `Dolů` doplňují důvod vypnutí skrytým textem přímo uvnitř tlačítka; nespoléhají na tooltipový `title`.
- V knihovně médií vypnuté tlačítko `Smazat` u použitého média doplňuje důvod blokace skrytým textem přímo uvnitř tlačítka; nespoléhá na tooltipový `title`.
- Slug pole ve správě blogů a v editoru statických stránek popisují pravidla povolených znaků viditelnou nápovědou přes `aria-describedby`; nepoužívají tooltipový `title` jako jediný nosič instrukcí.
- Časy poslední aktivity na dashboardu zobrazují relativní čas viditelně a přesný čas jako skrytý text přímo uvnitř `<time>`; přesný čas není dostupný jen přes tooltipový `title`.
- Runtime audit plošně hlídá administrační PHP výstupy proti návratu tooltipových `title` atributů; nápovědy, důvody stavů a kontext ovládání patří do viditelného textu, skutečných labelů nebo skrytého textu přímo uvnitř ovládání.
- Hromadné checkboxy `Vybrat vše` i jednotlivé řádkové checkboxy v administračních tabulkách používají skrytý `label` navázaný přes `for` / `id` místo samostatného `aria-label`; runtime audit hlídá zachování těchto popisků, jedinečné řádkové identifikátory i to, aby se starý aria-only vzor nevrátil.
- Ikonová, řadicí a dialogová akční tlačítka v administraci používají viditelný text doplněný skrytým kontextem přímo uvnitř tlačítka: dialogová tlačítka zavření mají skrytý text `Zavřít dialog`, tlačítko `Upravit` v přehledu blogů přidává název konkrétního blogu a tlačítka `Nahoru` / `Dolů` v řazení přidávají název řazené položky bez samostatného `aria-label`. Stejný vzor používají i dynamicky vytvářená tlačítka v content/media pickeru, fallback šipky pro řazení a tlačítka `Odebrat` u voleb anket; fallback šipky zároveň nepoužívají tooltipový `title` jako náhradu názvu tlačítka.
- Seznam widgetů v administraci popisuje každou řazenou položku přes její skutečný název a metadata pomocí `aria-labelledby` / `aria-describedby`; tlačítka `Nastavení` a `Odebrat` mají kontext konkrétního widgetu jako skrytý text uvnitř tlačítka, takže se přístupný název neliší od viditelného ovládání.
- Dynamická tlačítka `Odebrat` ve formuláři anket a v editoru zdrojů rezervací mají kontext akce jako skrytý text uvnitř tlačítka, takže nově přidané možnosti, sloty i blokované dny zůstávají čitelné pro čtečky obrazovky bez samostatného `aria-label`.
- Editor zdrojů rezervací používá skutečné skryté `label` prvky i pro checkboxy zavřených dnů v otevírací době a pro existující i dynamicky přidané blokované dny; čtečky obrazovky tak dostanou stabilní název pole bez samostatného `aria-label`.
- Content/media picker v administraci načítá sdílený statický stylesheet `admin/assets/content-reference-picker.css` místo generovaného inline `<style>` helperu v `lib/ui.php`; runtime audit hlídá návrat lokálních stylů i zachování tříd pro dialog, překryv a výsledky vyhledávání.
- Veřejný layout default šablony používá pro fallback kopírování odkazu a obsahu do schránky sdílenou třídu `.clipboard-fallback-control` místo JS `element.style` mutací; runtime audit hlídá návrat inline stylování do layoutu.
- Tlačítka `Kopírovat odkaz` na veřejných detailových stránkách včetně detailu fotografie galerie používají viditelný text doplněný skrytým kontextem uvnitř tlačítka místo samostatného `aria-label`; layoutový clipboard skript si před potvrzením ukládá původní HTML a po oznámení `Zkopírováno!` ho obnoví, aby skrytý kontext zůstal zachovaný.
- Veřejné šablony načítají styly pro skip link, `.sr-only`, cookie lištu a veřejný admin bar ze sdíleného `assets/public-core.css` místo generovaného inline a11y `<style>` helperu.
- Samostatné systémové obrazovky `install.php`, `migrate.php` a `maintenance.php` načítají společný statický stylesheet `assets/standalone.css` místo lokálních inline `<style>` bloků; maintenance stránka má zároveň skip link na hlavní obsah.
- Nouzová chybová stránka načítá `assets/error.css` a antispamový honeypot používá sdílenou třídu `.honeypot-field` ve veřejném i administračním CSS, takže ani tyto pomocné výstupy nepotřebují lokální inline styly.
- Přihlašovací obrazovky administrace včetně 2FA načítají sdílený statický stylesheet `admin/assets/login.css` místo generovaného inline `<style>` helperu v `lib/ui.php`; runtime audit hlídá návrat lokálních stylů i zachování tříd pro skip link, focus stav, TOTP pole a sekundární akci. Kontrastní tokeny loginu a administračního layoutu jsou zároveň měřené přes `contrast_focus_guardrails`, včetně focusu, skip linku, informačních panelů, inline štítků, hranic inputů/tlačítek, forced-colors fallbacku a disabled ovládacích prvků bez průhlednostního dimmingu.
- Runtime audit `text_spacing_guardrails` hlídá core CSS proti vzorům, které mohou při uživatelském zvětšení řádkování, mezer mezi písmeny a mezer mezi slovy schovat text: záporné `letter-spacing`, `text-overflow: ellipsis`, line clamp a `!important` zámky na text-spacing vlastnostech. SEO preview v administraci proto dlouhé titulky a popisy zalamuje místo ořezu.
- Sdílený admin stylesheet má mobilní baseline pro 320 px / 400 % scénáře: navigace se skládá nad obsah, hlavní obsah má menší padding, tabulky používají lokální horizontální scroll přes `.table-responsive`, komplexní gridy v médiích, statistikách, Form Builderu, odpovědích formulářů, správě šablon, checkbox skupinách a souhrnných kartách padají do jednoho sloupce, flex řádky s daty/volbami se na malé šířce skládají pod sebe, dlouhé fieldsety nesmí roztahovat hlavní obsah přes min-content šířku a malé řadicí ovladače, běžná tlačítka, checkbox/radio ovladače, sekundární action odkazy i přímé akční odkazy v odstavcích mají minimální target size. Browser ověření při 320 px pokrylo media, widgets, statistics, Form Builder, přehled formulářů, comments, contact, chat, reservations, food, downloads, gallery, importy, content picker, reprezentativní dlouhé formuláře a podcastové přehledy; tabulkové moduly mají doplněné responzivní wrappery tabulek a skryté datové tabulky grafů jsou schované přes wrapper, aby neroztahovaly stránku. Runtime audit `admin_mobile_reflow_guardrails` hlídá, aby se tento baseline neztratil.
- Administrace profilu používá pro sekce hesla, TOTP 2FA, veřejného autora, avatar a odesílací akci sdílené utility třídy a atribut `hidden` místo lokálních `style` atributů nebo JS `element.style` mutací; runtime audit hlídá, aby se starý vzor nevrátil.
- Správa vzhledu a šablon v administraci používá sdílené utility třídy pro katalog šablon, barevné náhledy, theme settings a import/export balíčků místo lokálního `<style>` bloku a inline `style` atributů; barevné tečky se vykreslují přes SVG `fill` bez inline CSS.
- Import portable ZIP balíčku šablony používá sdílenou upload validaci pro stav PHP uploadu, ověření dočasného souboru a prázdný soubor ještě před tím, než začne vlastní package kontrola manifestu, povolených statických assetů a velikostních limitů.
- Statistiky v administraci používají sdílené utility třídy pro filtr období, souhrnné karty a grafy místo lokálních `style` atributů; grafy se vykreslují přes sémantický `<progress>`, takže runtime audit hlídá, aby se nevrátily staré inline výšky sloupců.
- Souhrnné karty v administraci používají stejné popisky jako veřejný widget statistik: `Online`, `Dnes`, `Měsíc` a `Celkem`, vždy s popiskem před hodnotou.
- Podrobné statistiky obsahují blok `Nejčtenější statické stránky`. Ukazuje globální statické stránky i statické stránky blogů za zvolené období z raw návštěvnických dat, tedy jen v rámci nastavené retence statistik.
- Podrobné statistiky obsahují blok `Výkon obsahu`. Ten kombinuje dlouhodobé denní agregace a dnešní živá raw data, ukazuje souhrn podle modulů, nejčtenější obsah, největší nárůsty proti předchozímu stejně dlouhému období a filtr podle modulu. Agregace neukládají IP hashe, user-agenty ani raw referrery, takže mohou zůstat dostupné i po vyčištění krátkodobých raw návštěv. Lazy agregace dopočítává konkrétní nesouladné dny v omezené dávce, aby první otevření statistik po nových historických návštěvách nepřepočítávalo zbytečně velký souvislý rozsah.
- CSV export v bloku `Výkon obsahu` je pouze pro přihlášenou administraci a před stažením vyžaduje review interních metrik, období, filtru modulu, počtu řádků a potvrzení oprávnění. Potvrzený export posílá bezpečné no-store/noindex/nosniff hlavičky a obsahuje jen agregovaná pole: modul, název obsahu, typ, veřejnou URL, zobrazení, unikátní návštěvníky, předchozí období a změnu.
- Podrobné statistiky obsahují blok `Odkud návštěvníci přišli`. Zobrazuje externí odkazující stránky za zvolené období z raw návštěvnických dat, tedy jen v rámci nastavené retence. Referrer se ukládá bez query stringu a fragmentu, aby se do statistik zbytečně nedostaly tokeny nebo jiné citlivé parametry URL; interní přechody v rámci vlastního hostu se nezobrazují.
- Dashboard administrace používá sdílené panely, souhrnné karty, metadata tabulek a sémantický `<progress>` pro mini graf návštěvnosti místo lokálních `style` atributů; runtime audit hlídá, aby se do přehledu nevrátily inline barvy, výšky nebo starý vizuální graf.
- JSON-LD strukturovaná data pro veřejné moduly se vykreslují přes sdílený helper s CSP nonce. Runtime audit hlídá, aby se structured-data výstupy nevracely k surovým non-nonced `<script type="application/ld+json">` blokům.
- Veřejná default šablona obsluhuje potvrzení akcí přes `data-confirm` a tisk přes `js-print-page` v nonce skriptu layoutu. Runtime audit zároveň hlídá, aby se do veřejných view souborů nevracely inline `onclick` handlery.
- Jednoduché destruktivní formuláře v administraci používají `data-confirm` i na úrovni formuláře; globální nonce skript poslouchá událost `submit`, takže potvrzení zůstává funkční i při klávesnicovém odeslání a nemusí se psát jako inline `onsubmit`.
- Dlouho běžící administrační formuláře používají `data-submit-once`, aby se po odeslání změnil text tlačítka a zabránilo se opakovanému kliknutí bez inline `onclick` handlerů.
- Editor anket v administraci používá pro přidávání a odebírání možností odpovědi datové atributy a delegovaný listener v nonce skriptu formuláře, ne inline `onclick` handlery.
- Editor formulářů v administraci používá sdílené admin CSS třídy pro základní nastavení, potvrzovací e-mail, webhooky, editor polí a náhled potvrzení místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do form builderu nevracely. Checkboxy v části pro přidání nového pole používají přímo svůj viditelný label a kontext skupiny z legendy; checkboxy existujících polí přidávají kontext konkrétního pole skrytým textem uvnitř stejného labelu, aby se přístupný název nelišil od textu, který vidí uživatel.
- Formulář anket v administraci používá sdílené utility třídy pro časová pole, editor možností, SEO sekci, akční odkazy a výsledky ankety místo lokálních `style` atributů. Výsledky jsou vykreslené nativním `<progress>`, takže dynamická šířka proužku už není uložená v inline CSS.
- Veřejné výsledky anket v default šabloně používají nativní `<progress>` pojmenovaný přes `aria-labelledby` na viditelný text odpovědi a viditelný výsledek hlasování místo vnořeného prvku s dynamickým inline `width`; runtime audit hlídá, aby se starý CSS-only proužek ani samostatný `aria-label` nevrátil.
- Veřejný přehrávač podcastové epizody je pojmenovaný skrytým textem v DOM přes `aria-labelledby`, ne samostatným `aria-label`, takže čtečky obrazovky dostanou stabilní název a audit může ověřit existující cílový prvek.
- Nastavení webu v administraci používá sdílené admin CSS třídy pro navigaci sekcí, profil webu, komentáře, notifikace, vlastní kód, náhled loga a favicon i akční tlačítko místo lokálního `<style>` bloku a lokálních `style` atributů; runtime audit hlídá návrat starého vzoru.
- Rezervační formuláře v administraci používají datové atributy a nonce skripty pro přepínání polí, práci se sloty a blokovanými dny. Runtime audit zároveň hlídá, aby se do admin PHP souborů nevracely inline `onclick`, `onchange`, `onsubmit` ani `oninput` atributy.
- WordPress/eStránky importy v administraci používají sdílené panelové a formulářové utility pro výsledky, náhled importu, informační boxy, výběr cílového blogu a downloader fotografií místo lokálních `style` atributů; runtime audit hlídá, aby se tyto styly do importních nástrojů nevracely.
- Záloha databáze, hlavní JSON export, CSV export odpovědí Form Builderu, CSV export výsledků anket, hromadné akce galerie, sdílené generic bulk mazání a Koš v administraci používají sdílené utility třídy pro popisy a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto obrazovek nevracely. Koš zároveň slouží jako review krok pro WCAG `3.3.4`: trvalé smazání zobrazuje konkrétní typ, název a datum smazání, vyžaduje potvrzovací checkbox u položky a server odmítne purge bez potvrzení. Ruční SQL záloha, JSON export, CSV export odpovědí, CSV export výsledků anket i ZIP export galerie mají obdobný review krok pro citlivý nebo interně dopadající export, vyžadují potvrzení oprávnění ke stažení a server odmítne nepotvrzený download bez attachmentu i audit logu. Sdílené `bulkActions()` mazání vyžaduje `confirm_bulk_delete` a server odmítne nepotvrzenou `delete` akci před cleanupem, smazáním nebo audit logem.
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
- Přehled rezervací, ruční vytvoření rezervace, detail rezervace a editor zdrojů rezervací v administraci používají sdílené admin CSS třídy a atribut `hidden` pro přepínané části formulářů místo lokálního `<style>` bloku, lokálních `style` atributů nebo JS `element.style` mutací; runtime audit hlídá návrat starého vzoru i u dynamicky přidávaných slotů a blokovaných dnů. Detail rezervace zároveň používá pro stavové změny review text a serverově ověřený potvrzovací checkbox, aby nepotvrzený POST nezměnil stav ani historii.
- Knihovna médií v administraci používá sdílené admin CSS třídy pro upload, filtry, grid médií, hromadné akce, individuální delete review, detail metadat a přehled použití místo lokálních `style` atributů; runtime audit hlídá návrat starého vzoru.
- Kategorie a štítky blogu v administraci používají sdílené utility třídy pro výběr blogu, inline editaci taxonomií, tlačítka a mazací formuláře místo lokálních `style` atributů. Stejný směr drží i sdílený helper hromadných akcí, který používá `admin-fieldset-card` a `field-help--flush`.
- Pořadí stránek a odkazů blogu v administraci používá sdílené utility třídy pro popis, řadicí seznam, stavové poznámky, formulář externího odkazu a tlačítkový řádek místo lokálních `style` atributů. Stav přetahování se přepíná CSS třídou a runtime audit hlídá, aby se inline styly do obrazovky nevracely.
- Přehledy chatu, kontaktních zpráv a komentářů v administraci používají sdílené utility třídy pro filtry, hledání, hromadné akce, stavové poznámky a akční formuláře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto přehledů nevracely.
- Audit log v administraci používá sdílené utility třídy pro popis stránky, filtry a zalomení dlouhých detailů akce místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do přehledu nevracely. CSV export audit logu je dostupný přes kontrolní obrazovku s aktuálním filtrem, povinným potvrzením oprávnění a bezpečnými no-store/noindex/nosniff download hlavičkami.
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
- Blogové administrační obrazovky, základní obsahové administrace pro vývěsku, novinky, události a znalostní bázi, galerie, jídelní či nápojové lístky, statické stránky, podcasty, ankety, zajímavá místa, rezervace, chat, dashboard, profily autorů, fronta ke schválení, soubory ke stažení, knihovna médií a formuláře u odkazů otevíraných v novém okně nepřepisují viditelný text přes `aria-label`; informaci o novém okně přidávají jako skutečný skrytý text uvnitř odkazu, takže čtečky i audity pracují se stejným názvem jako vidící uživatelé.
- Editor fotografií galerie v administraci používá sdílené utility třídy pro náhled fotografie, viditelnost, pomocný text hromadného uploadu a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor zajímavých míst v administraci používá sdílené utility třídy pro popis formuláře, WYSIWYG detail, souřadnice, náhled obrázku, checkboxy, stav publikace a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editor uživatelských účtů v administraci používá sdílené utility třídy pro heslo, veřejného autora, avatar, checkboxy, veřejný profil a akční odkazy místo lokálních `style` atributů nebo JS `style` mutací; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Editory podcastového pořadu a epizody v administraci používají sdílené utility třídy pro popisy formulářů, gridy metadat, checkboxy, WYSIWYG popis, náhledy artworku, stav publikace a akční odkazy místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do těchto formulářů nevracely.
- Editor newsletterové rozesílky v administraci používá sdílené utility třídy pro WYSIWYG wrapper místo JS `style` mutací; runtime audit hlídá, aby se čistě prezentační inline styly do tohoto formuláře nevracely.
- Fronta ke schválení v administraci používá sdílené utility třídy pro filtrační navigaci, rychlý přehled, souhrnné karty, inline akční formuláře a náhled komentáře místo lokálních `style` atributů; runtime audit hlídá, aby se čistě prezentační inline styly do této obrazovky nevracely.
- Společný layout administrace používá statický stylesheet `admin/assets/layout.css` s verzovanou URL podle `filemtime()` pro navigaci, informaci o přihlášeném uživateli, autosave banner, počítadlo editoru, SEO náhled, patičku a sdílené utility třídy místo velkého generovaného inline `<style>` bloku v `admin/layout.php`. Runtime audit hlídá link, cache-busting i návrat starých inline fragmentů, aby se CSP postupně méně opírala o historický inline fallback a aby se nové přístupnostní CSS neblokovalo starou cache prohlížeče.
- Hlavní administrativní navigace ve společném layoutu je pojmenovaná skutečným nadpisem `Administrace` přes `aria-labelledby` a klientský live region už nekopíruje ani nemaže serverem vyrenderované stavové nebo chybové hlášky. Veřejná administrační lišta je také pojmenovaná skrytým nadpisem a dekorativní ikony v jejích odkazech jsou skryté před čtečkami obrazovky.
- `robots.txt` se generuje přes `robots.php`, podporuje jen `GET` a `HEAD`, zakazuje indexaci administrace a citlivých upload adresářů a odkazuje na aktuální sitemapu. Stejné čtecí omezení metod používají také XML sitemapa, globální, blogové i podcastové RSS feedy, ICS export událostí, veřejné souborové/media endpointy a read-only administrační endpointy včetně agregovaných statistik CSV, příloh formulářů a vyhledávání obsahu pro media picker. Hlavní JSON export CMS, CSV export odpovědí Form Builderu a CSV export výsledků anket používají samostatný GET review krok a potvrzený POST download. Discovery výstupy jako `robots.txt`, sitemapa, RSS feedy a ICS posílají `Content-Type`, `X-Content-Type-Options: nosniff`, případný `Content-Disposition` a `HEAD` odpovědi přes sdílený helper, aby je prohlížeč neinterpretoval mimo deklarovaný textový, XML, RSS nebo kalendářový typ. Statické inline soubory, například thumby, galerie, místa a podcastové obrázky nebo obaly, používají sdílený souborový helper pro MIME typ, cache, `Content-Disposition` s ASCII fallbackem i UTF-8 `filename*`, `ETag`, `Last-Modified`, podmíněné `304 Not Modified`, `nosniff` a `HEAD` odpovědi; chráněné souborové downloady i PDF preview posílají stejný formát názvu souboru a `nosniff` přes download helper. Podcastové audio zůstává specializované kvůli podpoře `Range`, ale používá stejný UTF-8 `Content-Disposition`, `ETag`, `Last-Modified`, `nosniff`, `HEAD` odpovědi a pro nerange veřejné požadavky také podmíněné `304 Not Modified`. U souborů `HEAD` posílá jen hlavičky, bez těla souboru. Nepodporované metody u těchto read-only endpointů procházejí stejným `requireHttpMethods()` jako citlivé tokenové akce a vrací jednotnou `405` odpověď s `Allow: GET, HEAD`, `Cache-Control: no-store, max-age=0`, `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff` a textovým typem odpovědi. Canonical URL v SEO metadatech přijímají jen bezpečné interní cesty nebo platné `http://` / `https://` adresy bez přihlašovacích údajů, řídicích znaků a protocol-relative tvaru.
- Pokud read-only discovery endpoint nemůže vrátit požadovaný výstup, například chybějící blogový RSS feed, podcastový RSS feed nebo ICS export události, používá sdílený `sendReadOnlyNotFoundResponse()`. Odpověď zůstává textová, necacheovaná, neindexovaná, neposílá referrer, má `nosniff` a u `HEAD` vrací jen hlavičky.
- Chybějící nebo nepřístupné soubory z veřejných souborových a media endpointů vrací textovou `404` odpověď se stejnými `no-store`, `noindex`, `no-referrer` a `nosniff` hlavičkami, aby se podvržené nebo soukromé souborové URL neukládaly v cache ani indexu.
- Chybějící nebo nepřístupné přílohy formulářových odpovědí v administraci používají stejný bezpečný souborový fallback: odpověď zůstává textová, necacheovatelná, neindexovaná, neposílá referrer, má `nosniff` a u `HEAD` vrací jen hlavičky. Stažení přílohy zároveň sdílí UTF-8 `Content-Disposition` helper s ostatními downloady, takže české názvy souborů zůstávají čitelné i v administračních exportech.
- Běžné veřejné HTML 404 stránky pro chybějící obsah používají sdílený `renderPublicNotFoundPage()`. Detailové moduly tak posílají jednotné `Content-Type: text/html; charset=UTF-8`, `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff` a přístupný nadpis z veřejné `not-found` šablony.
- Interní administrační JSON akce, které mění stav přes AJAX, jsou POST-only. Při jiné metodě procházejí sdíleným `requireJsonHttpMethods()` a vrací `405` s `Allow: POST`, JSON tělem, `request_id` a stejnými `Content-Type`, `no-store`, `noindex`, `no-referrer` a `nosniff` hlavičkami jako běžné administrační JSON odpovědi. Vlastní JSON odpovědi provozních a administračních endpointů používají sdílený `sendJsonResponse()`, takže status code, `request_id`, UTF-8 bezpečné kódování a ukončení odpovědi zůstávají konzistentní. Malé AJAX akce pro obnovu zámku obsahu a řazení obsahu tak zůstávají dohledatelné a nechávají chybu v administraci spárovat s technickým logem.
- Read-only JSON endpoint pro vyhledávání obsahu v media pickeru používá stejný administrační JSON helper a vrací stejné diagnostické `request_id` i u prázdných výsledků, takže lze dohledat konkrétní hledání bez ukládání hledaného textu do logového kontextu.
- Provozní JSON endpointy `health.php` a `csp-report.php` posílají přes sdílené helpery vedle `Cache-Control: no-store` a `X-Content-Type-Options: nosniff` také `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer` a jednotné JSON `405` odpovědi s `request_id`, aby monitoring a CSP reporty neputovaly přes cache, index nebo referrer a pravidla se časem nerozjela.
- Session vrstva používá strict mode, cookies-only režim a vypnuté session ID v URL. Přihlašovací flow regeneruje session ID a runtime audit hlídá i bezpečné cookie atributy `HttpOnly` a `SameSite=Strict` včetně sladěného mazání cookie při odhlášení, aby se snížilo riziko session fixation.
- Dlouhé administrační formuláře používají lokální autosave a při odeslání ukládají také recovery kopii konceptu. Pokud během editace vyprší session, content-lock heartbeat vrátí JSON 401 a zapíše do live regionu textové upozornění, že rozepsaný obsah se dál ukládá lokálně a před odesláním je potřeba znovu se přihlásit. Standalone admin login při návratu z chráněné administrační URL zobrazí status navázaný na formulář, po přihlášení vrátí uživatele zpět a připomene možnost obnovit lokální záložní koncept.
- Běžné administrační HTML odpovědi včetně loginu, 2FA a potvrzení migrace posílají `Cache-Control: no-store, max-age=0`, `Pragma: no-cache`, `Expires: 0`, `X-Robots-Tag: noindex, nofollow, noarchive` a `Referrer-Policy: no-referrer`, aby se citlivé administrační obrazovky zbytečně nevracely z cache po odhlášení nebo na sdíleném počítači, neměly se indexovat a jejich URL se neposílala dál jako HTTP referer.
- Citlivé veřejné tokenové a odhlašovací endpointy, například potvrzení e-mailu, potvrzení nebo odhlášení newsletteru, reset hesla, zrušení rezervace přes e-mailový token a veřejné odhlášení, posílají stejné `no-store`, `noindex` a `Referrer-Policy: no-referrer` hlavičky. Sociální crawler cache výjimka pro sdílený obsah se u těchto URL výslovně nepoužije; běžné sociální náhledy dostávají krátce cacheovatelnou odpověď s `Vary: User-Agent`, aby sdílená cache nemíchala crawler variantu s běžným návštěvníkem. Rezervační token se nepropíše do SEO metadat a tokenová URL se neposílá dál jako HTTP referer. Nepovolené metody u těchto citlivých endpointů procházejí sdíleným `requireHttpMethods()` a vrací jednotnou `405` odpověď s přesným `Allow`, `Cache-Control: no-store, max-age=0`, `X-Robots-Tag`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff` a textovým typem odpovědi.
- Stejnou necacheovatelnou ochranu používá také historický endpoint newsletter widgetu. Kvůli kompatibilitě zůstává dostupný, ale už neukládá odběratele ani neposílá potvrzovací e-mail; pouze přesměruje na zabezpečenou stránku odběru s captchou.
- Odhlášení posílá `Clear-Site-Data: "cache"`, takže prohlížeč po ukončení session zahodí cache webu. CMS záměrně nemaže storage ani všechny cookies, aby se neztratily neuložené autosave koncepty nebo cookie preference; session cookie se maže cíleně.
- Veřejné i administrační odpovědi posílají `Permissions-Policy`, `Cross-Origin-Opener-Policy: same-origin`, `Origin-Agent-Cluster: ?1`, `X-XSS-Protection: 0`, `X-Download-Options: noopen` a `X-Permitted-Cross-Domain-Policies: none`, takže CMS zakazuje nepoužívaná prohlížečová API, izoluje top-level okna i runtime podle originu, vypíná zastaralý XSS auditor ve starších prohlížečích, omezuje otevírání stažených souborů v kontextu webu a odmítá staré cross-domain policy soubory. CMS záměrně neblokuje clipboard, fullscreen ani legitimní externí iframe/audio/video embedy, proto nezavádí COEP/CORP.
- Administrační stažení citlivějších exportů, například potvrzený JSON export CMS, potvrzený CSV export odpovědí formulářů, potvrzený CSV export výsledků anket, přílohy formulářových odpovědí, SQL záloha databáze, potvrzený ZIP export galerie nebo potvrzený ZIP export šablony, posílají přes sdílený attachment/download helper `Cache-Control: no-store, max-age=0`, `Pragma: no-cache`, `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff` a jednotný `Content-Disposition` s ASCII fallbackem i UTF-8 `filename*`, aby se exporty zbytečně necachovaly, neindexovaly, neposílaly administrační URL jako referrer, české názvy souborů zůstaly čitelné a prohlížeč je neinterpretoval mimo deklarovaný typ. JSON export, CSV export odpovědí Form Builderu, CSV export výsledků anket, SQL záloha, ZIP export galerie a ZIP export šablony navíc před odesláním attachmentu vyžadují review citlivosti nebo dopadu exportu a potvrzení oprávnění.
- Ruční SQL záloha v administraci a automatická denní záloha z cronu používají stejný exportní helper. Ten exportuje jen CMS tabulky s bezpečným názvem, ověřuje výsledek `SHOW CREATE TABLE` a čte data explicitně jako asociativní řádky, aby výstup nebyl závislý na globálním nastavení PDO. Ruční administrační stažení navíc před odesláním attachmentu vyžaduje potvrzení review textu o citlivosti exportu. Hlavní JSON export používá obdobný review text, který upozorňuje na obsah, nastavení, metadata, zprávy, komentáře, odběratele a tokeny odběru; CSV export odpovědí Form Builderu stejným způsobem upozorňuje na osobní údaje, interní poznámky, štítky, přiřazení a aktuální filtr. ZIP export galerie upozorňuje na fotografie a názvy alb nebo souborů a sdílí potvrzení s hromadným mazáním alb; ZIP export šablony upozorňuje na přenos manifestu, uložené vizuální konfigurace a statických assetů.
- `php build/runtime_audit.php` ověřuje runtime guardrails včetně release ZIP pravidel, rate limitingu a přístupnosti; u veřejných vyhledávacích formulářů, filtračních navigací, drobečkové navigace, stránkování, obsahových embed bloků a dalších pomocných navigací hlídá i skutečné nadpisy napojené přes `aria-labelledby`, přes `contrast_focus_guardrails` měří baseline kontrast textu, focusu, skip linku a hranic ovládacích prvků, přes `text_spacing_guardrails` hlídá CSS proti text-spacing blokátorům a přes `admin_mobile_reflow_guardrails` hlídá mobilní baseline administrace
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
| Newsletter | Odkaz na zabezpečenou stránku pro přihlášení k odběru novinek |
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

Veřejné widgety v sidebaru a footeru používají skutečné viditelné nadpisy jako název oblasti. Náhled galerie se zároveň styluje přes šablonové CSS třídy, takže se do HTML nevkládají inline layout styly. Widget `Vyhledávání` má svůj `role="search"` formulář pojmenovaný přes skrytý `legend`, takže ho čtečky obrazovky najdou jako samostatný search landmark. Widget `Newsletter` zobrazuje úvodní text a odkaz na `/subscribe.php`; samotné zadání e-mailu probíhá až na zabezpečené stránce odběru s CSRF ochranou, honeypotem, rate limitingem a serverově ověřenou captchou. Widget `Sociální sítě` u odkazů otevíraných v novém okně doplňuje bezpečné `rel="noopener noreferrer"` a informaci „otevře se v novém okně“ vkládá jako skrytý text přímo do odkazu.

Dialog `Nastavení` u widgetu nově používá skutečné `fieldset` a `legend` pro základní i typově specifická nastavení. Skrytá pole se při změně typu widgetu zároveň deaktivují, takže se nedostanou ani do tab orderu, ani do odeslaného formuláře. Prezentační styly přehledu widgetů, dialogu a drag stavu jsou uložené ve sdílené admin CSS vrstvě, ne v lokálním `<style>` bloku ani v lokálních `style` atributech.

Zároveň ale platí, že i aktivní widget se může dočasně nevykreslit. Typické důvody:

- vypnutý modul
- vypnuté sledování návštěvnosti u widgetu Statistiky návštěvnosti
- chybějící obsah, například žádné veřejné články, fotky nebo události
- prázdná konfigurace, například žádné odkazy v widgetu Sociální sítě
- neplatná vazba na formulář, album, pořad nebo blog

Správa widgetů tyto stavy nově ukazuje přímo v přehledu textem `Na webu se teď nezobrazí: ...`, takže správce nemusí zkoušet metodou pokus–omyl, proč je blok aktivní, ale na webu se neukazuje.

Odebrání widgetu nejdřív popíše konkrétní název, typ, zónu a dopad na veřejné zobrazení. Akce vyžaduje potvrzovací checkbox `confirm_widget_delete_<id>`; chybějící potvrzení se vrátí na přehled s textovým alertem a field-level chybou u checkboxu, aniž by se widget nebo audit log změnil.

Praktická poznámka k footeru:

- odkazy na sociální sítě už se nenastavují v `Obecných nastaveních`, ale přímo ve widgetu `Sociální sítě`; odkazy se otevírají bezpečně v novém okně a čtečkám obrazovky tuto skutečnost oznamují
- odkaz `Vyhledávání` je nahrazen widgetem s vlastním hledacím polem
- odkaz `Odběr novinek` je nahrazen widgetem, který vede na zabezpečenou stránku odběru newsletteru s captchou
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
- `[video src="/uploads/media/video.mp4" captions="/uploads/media/video.cs.vtt" srclang="cs" descriptions="/uploads/media/video-popis.cs.vtt"][/video]` pro přímé video s WebVTT titulky a zvukovým popisem
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
- Přímý video snippet umí atribut `captions` pro WebVTT soubor (`.vtt`), `srclang` pro jazyk titulků a `caption_label` pro název stopy. Pro zvukový popis důležitých vizuálních informací umí také `descriptions`, `description_lang` a `description_label`. Audio i video snippet umí atribut `transcript` s odkazem na přepis a `transcript_label` pro text odkazu.
- V čistém HTML editoru použijte nástroj `Jazyk části textu`, pokud je vybraný úsek v jiném jazyce než stránka. Helper vloží například `<span lang="en">open source</span>`. Stejný helper je dostupný i u veřejně renderovaných taxonomických popisů, například u kategorií/štítků/sérií blogu, kategorií vývěsky, typů akcí a kategorií/sérií Ke stažení.
- URL ve snippetech pro audio, video, titulky, popisové stopy, přepisy a PDF musí být buď úplná `http://` / `https://` adresa bez přihlašovacích údajů, nebo interní absolutní cesta začínající jedním lomítkem, například `/uploads/media/soubor.pdf`. Protocol-relative adresy `//example.com/...` se z bezpečnostních důvodů odmítají.
- Shortcode `[code]...[/code]` je určený pro kopírovatelný obsah, například příkazy, konfiguraci, kód nebo jiné krátké texty; na veřejném webu zobrazí blok s tlačítkem `Kopírovat do schránky`.
- Obsahové karty a vložené bloky ze snippetů mají skrytý nadpis napojený přes `aria-labelledby`, takže je uživatel čtečky obrazovky najde i navigací po nadpisech.
- Při vložení obrázku z knihovny médií picker zachová `alt` atribut, ale nevkládá automatický `figcaption` z názvu média. Pokud médium nemá vyplněný alternativní text, vloží se `alt=""`, který lze v editoru ručně upravit.
- Externí iframe a externí audio/video embedy ve veřejném HTML obsahu jsou podporované přes CSP, pokud je cílový zdroj sám dovolí.
- Dialog content/media pickeru používá pro otevření a zavření atribut `hidden`, sdílené CSS třídy a `admin-modal-open` na těle stránky. Nevkládá lokální `style` atributy ani nemění `element.style`, takže méně zatěžuje CSP reporty a drží konzistentní fokusové chování.

---

## Ankety – režimy hlasování, výsledky, plánování a SEO

Modul ankety podporuje jednoduché hlasování jednou odpovědí i vícevýběrové ankety. Výsledky už nemusí být veřejné hned po hlasování; správce může rozhodnout, zda se ukážou vždy, po hlasování, až po uzavření nebo vůbec ne. Modul zároveň drží konzistentní veřejnou viditelnost s widgety, sitemapou a vyhledáváním, má revize a základní SEO workflow.

### Co se nastavuje u ankety

Každá anketa může mít:

- otázku a slug
- volitelný popis
- stav `Aktivní` nebo `Uzavřená`
- časové okno `Začátek` a `Konec`
- typ hlasování `Jedna možnost` nebo `Více možností`
- limit vybraných odpovědí u vícevýběru
- viditelnost výsledků `Po hlasování`, `Vždy`, `Až po uzavření` nebo `Neveřejné`
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
- Editor obsahuje fieldset `Nastavení hlasování`, kde se volí režim, limit vícevýběru a viditelnost výsledků.
- Editor nově obsahuje SEO pole `Meta titulek` a `Meta popis`.
- Revize zachycují otázku, slug, popis, stav, termíny, režim hlasování, viditelnost výsledků, možnosti i SEO metadata.
- Detail/editace ankety zobrazuje počet hlasujících, počet vybraných odpovědí a odkaz `Přejít na kontrolu CSV exportu výsledků`. CSV export obsahuje jen agregované možnosti, počty a procenta, ne IP hashe ani voter hashe; stažení vyžaduje review tématu ankety a potvrzení oprávnění.

### Veřejný výpis a detail

Veřejná stránka anket nově podporuje:

- přepínání `Aktivní ankety / Archiv`
- fulltextové hledání v otázce a popisu
- stránkování i při aktivním dotazu
- zachování filtru a vyhledávacího dotazu při listování

Detail ankety respektuje zvolený režim hlasování:

- u jedné možnosti vykreslí rádio tlačítka
- u vícevýběru vykreslí checkboxy a jasně oznámí limit výběru
- limit se ověřuje i serverově, takže podvržený formulář neuloží více možností
- výsledky se zobrazí jen podle nastavené viditelnosti
- neexportují se jednotlivé hlasy
- ochrana proti opakovanému hlasování používá anonymní hlasovací session nad stávajícím hash modelem

### Export a import

Export/import anket přenáší:

- samotnou anketu
- její možnosti odpovědí
- typ hlasování, limit vícevýběru a viditelnost výsledků
- stav a časové okno
- SEO pole

Nepřenáší se:

- jednotlivé hlasy
- hlasovací sessions
- agregované výsledky hlasování v CSV exportu; ten se stahuje samostatně po kontrole a potvrzení oprávnění

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že ankety podporují jedno- i vícevýběrové hlasování, plánování, veřejné hledání, slug URL, SEO fallbacky, CSV export a revize.
- Tento dokument popisuje konkrétní redakční workflow, chování výsledků, veřejnou viditelnost, časování a pravidla exportu/importu.

---

## Knihovna médií – veřejné a soukromé soubory

Knihovna médií už není jen jednoduchý seznam uploadů. Nově funguje jako bezpečnější centrální správa souborů pro editor, content picker i interní staff workflow.

### Co se u média nově spravuje

Každé médium může mít:

- `alt text`
- `caption`
- delší popis
- `credit`
- licenci a licenční URL
- zařazení do kolekce médií
- viditelnost `Veřejné / Soukromé`

Veřejná média dál mohou používat veřejné stránky a content picker. Soukromá média jsou určená pro interní workflow a nepůjčují se do veřejného pickeru.

### Kolekce médií

Kolekce slouží jako archivní vrstva nad starším polem `folder`. Správce může u kolekce nastavit:

- název, slug a popis
- výchozí viditelnost nových uploadů
- výchozí kredit, licenci a licenční URL
- pořadí kolekce v administraci

Když se nové médium nahraje do kolekce, převezme její výchozí viditelnost, kredit a licenci. Existující média se tím zpětně nepřepisují. Smazání kolekce nesmaže žádné soubory; média se jen od kolekce odpojí.

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

Pokud médium použité není, individuální smazání vyžaduje review text a checkbox `confirm_media_delete_<id>`. Bez potvrzení se soubor ani databázový záznam nesmaže a audit log se nezapíše; po potvrzení CMS odstraní také odvozené miniatury.

### Co je nové v administraci

- seznam médií má filtry podle typu, viditelnosti, uploadera a stavu použití
- seznam médií umí filtrovat podle kolekce a podle kontroly metadat: `Chybí alt text`, `Chybí kredit/licence` nebo `Neúplná metadata`
- fulltext prochází jméno souboru, `alt text`, `caption`, popis, `credit`, licenci i název kolekce
- po uploadu, úpravě, náhradě souboru i mazání se používá PRG návrat, takže refresh neopakuje POST
- médium lze nahradit novým souborem ve stejné MIME rodině bez rozbití existujících referencí
- knihovna médií přijímá WebVTT soubory (`.vtt`) pro titulky a zvukový popis ve video shortcodu
- bulk akce umí přepnout na `Veřejné`, `Soukromé`, přiřadit kolekci, doplnit výchozí kredit/licenci z kolekce a smazat nepoužitá média
- společný upload helper při přípravě adresáře, nahrazení existujícího cíle a finálním přesunu uploadu zapisuje případné selhání do strukturovaného logu s hashem cesty
- pokud při přesunu, náhradě nebo úklidu originálu, miniatury či WebP varianty selže souborová operace, CMS ji zapíše do strukturovaného logu s hashem cesty a příponou souboru, ne s plnou fyzickou cestou

Content/media picker dál nabízí jen veřejná média. Výsledky vyhledávání ale nově ukazují i kolekci, delší popis a licenční metadata, aby editor poznal správný soubor ještě před vložením. Vložené HTML obrázku se tím nemění: picker dál zachovává `alt` atribut a nevkládá automatický `figcaption` z názvu souboru.

### Co patří do README a co sem

- [README.md](../README.md) stručně říká, že knihovna médií podporuje `public/private`, kolekce, metadata, licence, canonical media helpery, blokaci mazání používaných souborů a náhradu souboru.
- Tento dokument popisuje konkrétní redakční workflow, kolekce, bezpečnostní pravidla a chování správy médií v administraci.
