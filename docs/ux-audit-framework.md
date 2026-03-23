# UX Audit Framework

Tento dokument zavádí praktický UX audit pro Kora CMS. Cílem není nahradit lidské posouzení automatem, ale rozdělit UX kontrolu na tři vrstvy:

1. Automatické guardrails v `build/runtime_audit.php`
2. Ruční heuristický checklist
3. Scénářové průchody nad konkrétními úlohami návštěvníka

## Co hlídá automat

Automatický audit má chytat regresní chyby, které se dají poznat z HTML a z běžícího webu:

- stránka má skip link na `#obsah`
- stránka má jeden hlavní landmark `<main id="obsah">`
- stránka má právě jeden `h1`
- nevyrenderují se prázdné nadpisy a prázdné titulkové podtexty
- homepage má buď skutečný obsah, nebo bezpečný fallback
- homepage negeneruje duplicitní CTA blok
- na veřejný web se nevrací technické nebo matoucí texty, které už jsme vědomě vyřadili

Automat nehodnotí estetiku ani “pocit”, ale dobře hlídá, aby se do veřejné vrstvy nevrátil:

- interní jazyk
- adminové formulace
- rozbitá informační hierarchie
- prázdné nebo duplicitní bloky

## Co musí dělat člověk

Ruční UX audit má vždy ověřit, že web působí srozumitelně i bez technických znalostí.

Kontrolní otázky:

- Chápe nový návštěvník během pár sekund, o jaký web jde?
- Je z homepage jasné, co je hlavní obsah a co je jen doplněk?
- Nepůsobí texty příliš technicky, interně nebo patronizujícím dojmem?
- Neopakují se stejné informace v kickeru, nadpisu a perexu bez přidané hodnoty?
- Nejsou výzvy k akci příliš obecné nebo naopak příliš direktivní?
- Dává navigace smysl i člověku, který nezná strukturu CMS?
- Nezobrazují se bloky pro vypnuté moduly nebo moduly bez obsahu?
- Je mobilní verze přehledná i bez dlouhého skrolování přes šum?

## Heuristický checklist

### Homepage

- Hero nebo úvod jasně vysvětluje, co návštěvník na webu najde.
- Zvýrazněný blok přináší skutečnou prioritu, ne jen náhodný obsah.
- Doplňkové bloky nepřebíjejí hlavní obsah.
- CTA blok je volitelný a má smysl jen tehdy, když návštěvníkovi opravdu pomáhá.
- Texty sekcí nejsou generické typu „další kroky“, pokud není zřejmé, jaké kroky to jsou.

### Navigace

- Názvy položek odpovídají jazyku návštěvníka, ne interní terminologii CMS.
- Položky se neopakují mezi hlavním obsahem a pomocnými bloky bez důvodu.
- Vypnutý modul zmizí nejen funkčně, ale i z mentální mapy uživatele.

### Formuláře

- Nadpis formuláře vysvětluje účel bez nutnosti číst celé okolí.
- Popisky polí jsou jednoznačné.
- Chybové a úspěšné hlášky popisují, co se stalo a co dělat dál.
- Formulář nevyžaduje čtení interních nebo technických výrazů.

### Obsahové moduly

- Blog, novinky, ankety, kontakty a rezervace mají vlastní jazyk odpovídající typu modulu.
- Veřejná část nepoužívá interní slova jako „featured“, „modul“, „composer“, „layout“.
- Přednost má význam pro návštěvníka, ne význam pro správce systému.

### Mobil a responzivita

- Důležité akce jsou dostupné bez zbytečného scrollování.
- Tlačítka se nehromadí do nepřehledných shluků.
- Dlouhé titulky a CTA se na úzké obrazovce nelámou do nečitelných tvarů.

## Scénářové průchody

Každá větší změna by měla projít alespoň těmito scénáři:

1. Nový návštěvník přijde na homepage a má pochopit účel webu.
2. Návštěvník hledá kontakt nebo možnost napsat zprávu.
3. Návštěvník chce najít článek, novinku nebo jiný obsah.
4. Návštěvník chce začít odebírat novinky.
5. Přihlášený veřejný uživatel chce přejít do svého profilu nebo rezervací.
6. Návštěvník používá mobil a nezná strukturu webu.

U každého scénáře sledujeme:

- počet kroků
- počet rozhodovacích míst
- výskyt matoucích nebo interních slov
- výskyt zbytečných duplicit
- srozumitelnost hlavní výzvy k akci

## Kdy je UX změna hotová

UX změnu považujeme za hotovou teprve tehdy, když platí vše:

- `php build/runtime_audit.php` projde bez nálezů
- ruční heuristický checklist neobsahuje blocker
- hlavní scénáře jdou projít bez vysvětlování „co tím systém myslel“
- veřejný text používá jazyk návštěvníka, ne autora CMS

## Praktické pravidlo pro další vývoj

Když si nejsme jistí textem nebo blokem na homepage, ptáme se:

„Pomáhá to návštěvníkovi něco pochopit nebo udělat?“

Pokud ne, je lepší to skrýt, zkrátit nebo přepsat.
