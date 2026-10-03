# Digitální obchod: doklady a QR platba

## Rozsah a stav

Technická příloha pro čisté generátory v `lib/shop_invoice.php`; cílem HTML je
WCAG 2.2 AA. Dokument není právní ani daňovou certifikací a netvrdí úplnou WCAG
shodu nebo PDF/UA. Přístupnost administračních/public endpointů, autentizace,
hlaviček, e-mailů a zpřístupnění souborů řeší integrátor mimo tento rozsah.

## Dotčená kritéria

Volitelný PDF layout má mez 512 000 bajtů vstupního snapshotu a 250 stran,
aby dlouhé licenční texty nevyčerpaly paměť hostingu. Při překročení se vydá
výslovná chyba s odkazem na úplný HTML/TXT doklad; přílohy a uložený snapshot
se nekrátí. Selftest ověřuje zachování celé velké textové alternativy.

| Kritérium | Rozhodnutí a automatizovaný důkaz |
|---|---|
| 1.1.1 Netextový obsah | QR PNG je vložen lokálně s přesným alt `qr kód k platbě`. PDF používá Figure se stejným `/Alt`. Účet, IBAN, částka, měna a VS mají textový ekvivalent; samostatný text obsahuje SPAYD. Selftest kontroluje alt; volitelný ZXing dekóduje PNG i PDF obrázek. |
| 1.3.1 Informace a vztahy | HTML obsahuje skutečné nadpisy, main a tabulky s caption a scope. PDF obsahuje H1/H2/H3/P/Figure, StructTreeRoot a ParentTree. PDF položky jsou záměrně označené nadpisy a odstavce, nikoli vizuální tabulkou bez vztahů. |
| 1.3.2 Smysluplné pořadí | Společný model snapshotu zachovává stejné pořadí ve třech formátech. Python test ověřuje vazby každého MCR/MCID, pořadí tagů přes stránky a úplnost extrahovaného textu. Číslování stránek je Artifact. PDF před generováním odmítá nepodporovaná písma/tvarování i řízení směru pomocí DomainException, místo aby vydal nesprávné pořadí nebo nespojené glyphy; povinné HTML/TXT kopie zachovávají Unicode. |
| 1.4.1 Použití barvy | Uhrazení, zákaz platby, čekání na daňové ověření i opravný doklad jsou vysvětlené textem, nikoli jen barvou. |
| 1.4.3 Kontrast | HTML i PDF používají tmavý text na bílém pozadí; HTML text #17202a má kontrast přes 16:1, focus/odkaz #003b70 přes 10:1. Tabulkové hranice #59636e mají kontrast přes 6:1. |
| 1.4.4, 1.4.10, 1.4.12 | HTML neblokuje zoom, umožňuje zalamování dlouhého obsahu a posun datových tabulek. Právní text nemá pevnou výšku ani ořez. PDF je pevné A4; HTML/text jsou alternativa. Ruční zoom 200/400 %, šířka 320 px a text spacing zůstávají nutné. |
| 2.1.1, 2.4.1, 2.4.7, 2.4.11 | Samostatné HTML zachovává viditelný skip link na focusovatelný main a kontrastní focus outline. Posouvatelné datové tabulky jsou focusovatelné pojmenované regiony s aria-labelledby na skutečný caption. Selftest ověřuje každou ARIA vazbu; nejsou zde skriptové ovládací prvky ani překryvy. |
| 2.4.2, 2.4.6, 3.1.1 | Titulek obsahuje typ a číslo dokladu. Jazyk je cs / cs-CZ, datum a platební údaje mají textové popisky. |
| 3.3.4 Prevence finanční chyby | Platební výzva není daňový doklad. Finální/opravný doklad `non_vat` a tuzemský `non_vat_oss` jsou nedaňové; zahraniční `non_vat_oss` výslovně uvádí zahraniční DPH/OSS, nikoli tuzemské plátcovství. CZ/SK fixtures ověřují všechny tři druhy dokladu i zachování částek. Uhrazené, zrušené, nulové, kreditní a daňově neověřené zahraniční objednávky negenerují QR platbu. Částky jsou v celočíselných haléřích, kontrolují se součty a VS odpovídá číslu objednávky. |

## Kontrakt snapshotu

Veřejné API zůstává přesně `shopInvoiceHtml(array): string`, `shopInvoicePdf(array): string`,
`shopInvoiceText(array): string`, `shopPaymentSpayd(array): string`, `shopQrPng(string): string`
a `shopNormalizeIban(string): string`. PDF/PNG vracejí bytes, ostatní UTF-8 text.
Normalizace účtu vrací `''` při neplatném vstupu pro core/form validátory.
Žádná funkce nenačítá DB nebo aktuální nastavení. Neplatná data dokladu/platby vyvolávají
DomainException; překročení kapacity QR vyvolává LengthException.

PDF je volitelná příloha. Jeho samostatný preflight dovoluje pouze latinku,
řečtinu, cyrilici a neutrální/zděděné Unicode znaky s ověřením glyphů v písmu.
Ostatní písma konzervativně odmítá, protože generátor nemá shaper ani bidi engine.
Odmítá také Unicode format controls (včetně joiner/non-joiner, bidi override
a isolate) a arabské značky/tatweel, které mohou mít Script Common/Inherited.
Kontroluje celý viditelný obsah i titulek: identitu, položky, produktové a právní
kopie. Pouhá přítomnost glyphu v DejaVu Sans není důkaz správného tvarování.
DomainException se propaguje volajícímu s výslovným doporučením HTML/TXT;
core dispatch ji zachytává pouze pro volitelné PDF. Povinné HTML+TXT kopie se
generují nezávisle, bez tohoto omezení, a neúspěch PDF nemění snapshot ani jejich
obsah. Selhání PDF samo nesmí blokovat doručení objednávky.

Snapshot: order, items, settings, legal, payment, issued_at, kind, invoice_number.
Kontakt dodavatele zahrnuje uložený seller_phone i seller_email.
Právní kopie včetně tax_note a consent se vypisuje doslova jako bezpečný text,
bez interpretace HTML. `product_snapshot` položky může být JSON nebo asociativní
pole s description/requirements/license_text/update_policy; kopie je součástí
HTML, textu i PDF. Interní tokeny a názvy privátního úložiště se nezveřejňují.

Kredit přijímá původní kladné částky a vykreslí je záporně (již záporné částky
neobrací). `original_invoice_number` nebo pole original_document/original_invoice
určuje původní doklad; standardní fallback je `FV-` + order_number. Dobropis
nenabízí bankovní příkaz. Datum plnění bere první neprázdné explicitní
performance_at/performed_at, order.performance_at nebo order.fulfilled_at.
Neznámé datum je `Neuvedeno`, nikdy datum platby ani dnešní datum.
`paid_at` se označuje pouze `Datum přijetí úplaty`. Finální doklad vzniká při
záznamu platby před SMTP/dodáním; chybí-li známé datum plnění, doklad výslovně
uvádí, že čas dodání digitálního obsahu se eviduje samostatně a přijetí úplaty
nepotvrzuje dodání. Immutable doklad nesyntetizuje čas dodání z času platby;
pozdější samostatná evidence plnění nepřepisuje jeho snapshot.
Country jiné než CZ s prázdným tax_verified_at zakazuje nabídku platby.

### Daňový režim dodavatele a typ dokladu

`settings.vat_mode` přijímá `non_vat`, `vat`, `oss` a `non_vat_oss`.
Režim `oss` nadále znamená tuzemského plátce DPH v OSS, s dosavadním označením
`plátce DPH, režim OSS`. Registrace do režimu EU OSS je však možná pro plátce
i identifikovanou osobu, jak uvádí [Finanční správa: registrace do režimu EU OSS](https://financnisprava.gov.cz/cs/mezinarodni-spoluprace/mezinarodni-spoluprace-a-dph/one-stop-shop-oss/rezim-eu/registrace).
Samotná registrace OSS proto není důkazem tuzemského plátcovství.

`non_vat_oss` má přesný popisek `Neplátce DPH v tuzemsku, identifikovaná osoba v OSS`.
DIČ zůstává zobrazené ze snapshotu. Pro `order.country_code = CZ` je finální
FV `Faktura (nedaňový doklad)` a opravný OD `Opravný doklad`, stejně jako u
`non_vat`. Zahraniční FV/OD jsou označené jako daňové doklady se specifikací
`zahraniční DPH, OSS` a odstavcem určujícím zemi plnění a zahraniční DPH.
Neobsahují paušální tvrzení, že tento doklad není daňovým dokladem, ani označení
dodavatele za tuzemského plátce. Platební výzva zůstává nedaňová ve všech režimech.

Země se pro klasifikaci porovnává bez rozlišení velikosti písmen. Režim nemění
uložené sazby, základ, daň, celkové částky ani právní `legal.tax_note`;
tuzemskou nulovou daň a ověřená zahraniční pravidla dodává integrátor ve snapshotu.
SK fixture používá syntetickou sazbu 23 % pouze pro důkaz vykreslení jiné než
tuzemské daně, nikoli jako potvrzení správné sazby konkrétního produktu.

`shopPaymentSpayd()` vrátí prázdný řetězec, není-li platba přípustná; jinak přesné
ACC/AM/CC/X-VS. Limit AM je dle SPAYD 9 999 999,99 Kč. `shopNormalizeIban()` přijímá
kontrolované české číslo účtu včetně předčíslí nebo známý zahraniční IBAN s mod-97,
zemí a národní délkou. Neověřuje existenci účtu či banky. Statický seznam národních
délek je třeba aktualizovat při změně registru IBAN.

## Automatizovaný důkaz

`php build/shop_invoice_selftest.php` je izolovaný test bez DB. Vytváří pouze
ignorované `dist/shop-invoice-selftest/` fixture doklady: platební výzva, neplátce,
OSS, kredit, neověřená zahraniční objednávka a dlouhý vícestránkový doklad,
navíc `non_vat_oss` CZ/SK ve variantách finální FV, opravný OD a platební výzva.
Fixture `paid-delivery-pending` ověřuje samostatné datum přijetí úplaty,
neznámé datum plnění a vysvětlení oddělené evidence dodání ve všech formátech.
Regresní datumové testy pokrývají dvoudenní zpoždění SMTP, explicitní datum
plnění a prázdné explicitní pole před známým fulfilled_at.
Nové fixtures ověřují přesný popisek identifikované osoby, DIČ, tuzemské
nedaňové označení, zahraniční základ/sazbu/daň/celkem, záporné opravné částky,
původní FV, zákazy opakované/neověřené platby a neměnnost snapshotu.
Regrese potvrzuje, že původní režim `oss` zůstává tuzemským plátcem v OSS.
Kontroluje bezpečné HTML, tabulky, focus/skip link, licence/product kopie, varování,
SPAYD, IBAN, konzistenci částek a tagy PDF. Produkční font a QR knihovna s licencemi
jsou v `lib/third-party/shop/`, mimo vyloučený Composer vendor.

Selftest navíc prokazuje, že arabské glyphy skutečně existují ve vloženém písmu,
ale jejich PDF přesto končí přesnou DomainException kvůli nepodporovanému
vykreslení. Pokrývá arabštinu, hebrejské bidi, syrské a indické skupiny písem,
thajštinu, laoštinu, tibetštinu, Myanmar, Khmer a join/bidi controls včetně
smíšeného latinsko-arabského textu a arabských Common/Inherited značek.
Kontroluje zachování všech původních Unicode řetězců v HTML/TXT před selháním
PDF i po něm, neměnnost snapshotu a preflight všech textových zdrojů dokladu.
Stejný fallback je testovaný pro CJK; latinka/řečtina/cyrilice zůstávají úspěšným
PDF scénářem. HTML/TXT fallback fixtures jsou ve stejném ignorovaném dist adresáři.

`php build/shop_invoice_selftest.php --python=/absolute/path/to/python` navíc
spouští `lib/third-party/shop/verify.py` s pypdf a Pillow: porovnání 176 matic QR
s připnutým oficiálním Nayuki v1.8.0, všechny verze 1–40/ECC/masky,
ECI hranice, kombinace numeric/byte/alphanumeric/Kanji, extrakce celého českého
textu, skutečného fontu, pořadí tagů a geometrických hranic každé
textové řádky. Nezávislé dekódování používá volitelný zxing-cpp; absence se hlásí
jako SKIPPED, nikoli úspěch dekódování. Poppler slouží k následné vizuální kontrole.

Selftest je zapojený do společného CI spolu s runtime, schema parity a HTTP
důkazy obchodu. Runtime/MySQL/HTTP kontroly používají databázi a musí běžet
sekvenčně, nikoli souběžně proti stejné instalaci. Závěrečný lokální Python QA
ověřil všechny matice, text, font, tagy a hranice PDF; volitelný zxing-cpp v tomto
prostředí není instalovaný, takže tento běh netvrdí nezávislé dekódování.

## Limity a ruční ověření

- Čtečka obrazovky: ověřit HTML klávesnicí/NVDA a PDF tagovou navigaci/pořadí v konkrétní čtečce. Samotné tagy nepředstavují PDF/UA validaci.
- PDF neprovádí pokročilé tvarování ani Unicode bidi. Písma mimo povolenou latinku/řečtinu/cyrilici a řídicí znaky směru/spojování proto výslovně odmítne DomainException i při dostupném glyphu. Chybějící glyph se také nenahrazuje otazníkem. HTML/TXT zachovávají veškerý platný Unicode a jsou povinným fallbackem; český Unicode a podporovaná evropská písma jsou součástí PDF testů. Zobrazování fallbacku závisí na fontu a shaperu prohlížeče/čtečky.
- Ověřit tisk QR na reálné tiskárně a platební aplikaci; dekódování QR neprokazuje podporu SPAYD zahraniční bankou.
- Doklad zobrazuje dodanou identitu, data, režim a právní kopie; jejich pravdivost a daňovou úplnost ověřuje provozovatel. Generátor nevytváří ani nepotvrzuje chybějící souhlas.
