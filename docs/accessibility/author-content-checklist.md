# Redakční checklist přístupného obsahu

Tento checklist odděluje odpovědnost Kora CMS od odpovědnosti autora obsahu. CMS poskytuje pole, snippety, guardraily a bezpečné výchozí chování, ale nemůže automaticky zaručit kvalitu ručně vloženého textu, médií, titulků, přepisů, odkazů nebo HTML.

Použijte ho před publikací nového nebo výrazně upraveného obsahu. Pokud nález vzniká ručně vloženým obsahem autora, zapisuje se jako author-content issue, ne jako chyba core CMS nebo výchozí šablony.

## Rychlá kontrola před publikací

- Obrázky, galerie a média mají smysluplný alt text, pokud neslouží jen jako dekorace.
- Obrázky s viditelným textem nejsou jediným nositelem důležité informace.
- Audio má přepis: u podcastové epizody pole `Přepis epizody`, u audio shortcodu atribut `transcript`.
- Předtočené video má titulky: u přímého video shortcodu atribut `captions` s WebVTT `.vtt` souborem, u externí platformy ověřené titulky v cílové službě.
- Video nebo audio má textovou alternativu, pokud zvuk nebo obraz nese důležitou informaci, kterou nelze získat z okolního textu.
- Cizojazyčná část textu má v HTML editoru vyznačený jazyk, například `<span lang="en">open source</span>`, pokud výslovnost nebo porozumění závisí na jazyce.
- Nadpisy jdou po logické osnově a nepřeskakují úroveň jen kvůli vzhledu.
- Odkazy dávají smysl i mimo okolní odstavec; nepoužívejte samotné „zde“, „více“ nebo „klikněte“ bez kontextu.
- Tabulky jsou používané pro data, ne pro rozložení textu; mají jasný nadpis nebo popis v okolí.
- Význam není sdělený jen barvou, polohou, ikonou nebo velikostí.
- Vložené externí iframe, mapy, sociální sítě, video platformy a formuláře mají vlastní přístupný název, titulky nebo textovou alternativu podle toho, co zobrazují.
- Stažitelné dokumenty mají srozumitelný název, typ souboru a pokud je to možné, přístupný obsah i mimo PDF nebo kancelářský formát.

## Obrázky a galerie

Alt text popisuje účel obrázku v kontextu stránky. Fotografie starosty v článku může mít alt `Starosta Jan Novák při podpisu smlouvy`, dekorativní ilustrační grafika může mít prázdný alt. Nepište „obrázek“ nebo „fotografie“, pokud to nepřidává význam.

U galerie vyplňte také titulek, delší popis, kredit a licenci, pokud pomáhají pochopit obsah nebo původ média. Pokud obrázek obsahuje text, důležitý text zopakujte v HTML obsahu stránky.

## Audio, video a přepisy

Podcastové epizody mají samostatné pole `Přepis epizody`; vyplňte ho u vlastního audio obsahu, který nemá plnohodnotnou textovou alternativu jinde na stránce.

U audio shortcodu lze doplnit odkaz na přepis:

```text
[audio src="/uploads/media/rozhovor.mp3" transcript="/uploads/media/rozhovor-prepis.html"][/audio]
```

U přímého video shortcodu lze doplnit WebVTT titulky a přepis:

```text
[video src="/uploads/media/video.mp4" captions="/uploads/media/video.cs.vtt" srclang="cs" caption_label="České titulky" transcript="/uploads/media/video-prepis.html"][/video]
```

Titulky musí být synchronizované s řečí a mají zahrnovat podstatné zvuky, pokud nesou význam. Přepis má zachytit mluvené slovo a důležité zvukové nebo vizuální informace. U externích video platforem ověřte titulky přímo v cílové službě; CMS nemůže garantovat jejich dostupnost ani kvalitu.

## Jazyk částí

Stránka Kora CMS je ve výchozím stavu česky. Delší nebo významné cizojazyčné části označte atributem `lang`, aby je čtečka obrazovky četla správným hlasem.

Příklady:

```html
<p>Projekt používá licenci <span lang="en">Creative Commons Attribution</span>.</p>
<blockquote lang="en">
  <p>Accessibility is essential for people with disabilities and useful for everyone.</p>
</blockquote>
```

Jednotlivé běžně zdomácnělé výrazy není nutné značit pokaždé. Značte hlavně názvy, citace, věty, odstavce, tlačítka nebo odkazy, kde špatná výslovnost mění porozumění.

## Odkazy, nadpisy a tabulky

Text odkazu má říkat cíl nebo akci. Lepší je `Stáhnout zápis z jednání v PDF` než `Klikněte zde`. U odkazu do nového okna neodebírejte skrytý dovětek, který CMS přidává automaticky.

Nadpisy používejte jako osnovu obsahu. Nepoužívejte nadpis jen proto, že má větší písmo; pro zvýraznění použijte běžný textový styl. Tabulky používejte pro srovnání dat a držte krátké, jasné záhlaví sloupců a řádků.

## Barva, vložené HTML a externí embedy

Nepřenášejte význam jen barvou. Pokud píšete „červeně označené položky jsou povinné“, doplňte i textový štítek nebo vysvětlení u každé položky.

Vlastní HTML vkládejte střídmě. Nepřidávejte inline styly, skryté texty, vlastní ARIA atributy nebo skripty, pokud nemáte jistotu, že nepoškodí klávesnici, čtečku obrazovky, kontrast nebo mobilní reflow. U externích embedů ověřte, že cílová služba má popisek, titulky, ovládání klávesnicí a nezobrazuje automaticky rušivý pohyb nebo zvuk.

## Evidence pro conformance report

Při ručním testu uveďte, jestli je nález:

- **Core CMS defect**: chyba ve výchozím UI, šabloně, helperu, validaci nebo guardrailu.
- **Theme defect**: chyba konkrétní šablony nebo custom theme nastavení.
- **Author-content issue**: chybějící alt text, přepis, titulky, `lang`, srozumitelný odkaz, kvalita PDF nebo problém externího embedu.

Core a theme defekty patří do `docs/accessibility/a11y-remediation-backlog.md`. Author-content issue opravte v obsahu a při opakovaném výskytu doplňte redakční pravidlo nebo školení editorů.
