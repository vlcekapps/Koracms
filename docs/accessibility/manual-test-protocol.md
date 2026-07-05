# Manual Accessibility Test Protocol

Tento protokol popisuje ruční ověření pro `wcag-22-aa-conformance.md`. Ruční test je povinný pro kritéria, která nejde spolehlivě uzavřít čistě statickým auditem.

Aktuální ruční evidence:

- 2026-07-04: čistý HTML editor dostal helper `Jazyk části textu` pro vložení `<span lang="…">` kolem vybraného úseku. Runtime audit hlídá zdrojový markup, JS a CSS; ručně zbývá ověřit použití helperu s klávesnicí/NVDA a kvalitu skutečného author contentu.
- 2026-07-04: vznikl `author-content-checklist.md` pro redakční kontrolu alt textů, médií, titulků, přepisů, jazyka částí, odkazů, nadpisů, tabulek, barev a externích embedů. Ručně zbývá projít reprezentativní publikovaný obsah a potvrdit, že nálezy jsou správně tříděné na core CMS defect, theme defect a author-content issue.
- 2026-07-04: browser průchod při 320 px ověřil admin stránky media, widgets, statistics, Form Builder, přehled formulářů, comments, contact, chat, reservations, food, downloads, gallery, importy, content picker a reprezentativní dlouhé formuláře page/blog/news/event/download/gallery/food/board/FAQ/place/polls/reservations/podcast. Nalezený horizontální scroll rootu ve statistikách, přehledu formulářů, food/downloads/gallery a podcastech i main-level scroll u contact/chat a dlouhých fieldsetů byl opravený přes `.table-responsive`, posílené CSS containment, wrapper skrytých datových tabulek grafů a sdílené CSS pro fieldset/form controls; admin hledání mělo viditelný focus a ověřené samostatné cíle neměly pod 24 px. Zbylý ruční průchod se týká hlavně 400 % zoomu, custom modulů, sticky/anchor skoků a keyboard-only/NVDA kombinací.
- 2026-07-05: audio/video snippety podporují odkaz na přepis přes `transcript`; přímý video shortcode podporuje WebVTT titulky přes `captions`, `srclang` a `caption_label` i audio-description stopu přes `descriptions` a `description_label`; knihovna médií přijímá `.vtt` soubory. Unit a runtime testy hlídají HTML výstup; ručně zbývá ověřit timing, jazyk, kvalitu titulků/přepisů/popisu a chování v prohlížeči se čtečkou.
- 2026-07-04: podcastové epizody mají modelované pole `transcript` pro textovou alternativu audia; unit testy helperů a runtime veřejného detailu hlídají, že se přepis vykreslí jako samostatná sekce. Ručně zbývá ověřit kvalitu skutečných přepisů a další média mimo podcastový modul.
- 2026-07-04: runtime `contrast_focus_guardrails` automaticky měří baseline kontrast pro default theme, admin layout a standalone login: textové páry, stavové hlášky, skip link, focus tokeny a hranice inputů/tlačítek. Ruční kontrastní průchod zůstává povinný pro custom theme settings, hover/disabled stavy, ikony a progress bary.
- 2026-07-04: auth flow pro WCAG 2.2 `3.3.8 Accessible Authentication (Minimum)` potvrzen jako funkční se správcem hesel, TOTP jednorázovým kódem, tokenovým resetem a chybovými stavy; opakovat při změnách auth/session chování.
- 2026-07-04: command centrum, widget dialog a content/media picker potvrzené bez regrese při ručním keyboard-only/NVDA průchodu; opakovat při změnách JS dialogů, focus trapu nebo admin layoutu.
- 2026-07-05: administrace má jednotnou stránku `Nápověda a podpora` ve stabilní spodní navigaci. Runtime `admin_consistent_help_guardrails` a HTTP `admin_consistent_help_http` hlídají link order, read-only route guard, nadpisové sekce a inventář podpory; ručně zbývá potvrdit průchod s klávesnicí/NVDA a veřejné/theme help mechanismy.
- 2026-07-05: výchozí veřejná šablona má ve footeru navigaci `Pomoc a kontakt` pro Kontakt a Chat podle zapnutých modulů. Runtime `public_consistent_help_guardrails` a HTTP `public_consistent_help_http` hlídají default public baseline; ručně zbývá potvrdit čtení a focus ve footeru a projít custom theme varianty.
- 2026-07-04: automatizované guardraily pro WCAG 2.2 `1.3.5 Identify Input Purpose` pokrývají auth flow, veřejný kontakt, food objednávky, guest rezervace a Form Builder renderer. Ručně zbývá ověřit, že Firefox/Chrome a používaný správce hesel nebo autofill tato metadata skutečně nabízejí bez matoucích návrhů.
- 2026-07-04: automatizovaný guardrail pro WCAG 2.2 `3.3.7 Redundant Entry` pokrývá veřejný kontakt a Food objednávku: `currentUserContactDefaults()` čte jméno, e-mail a telefon z profilu, HTTP integrace ověřuje předvyplnění v renderu a POST chybové stavy dál zachovávají ručně upravené hodnoty. Ručně zbývá projít širší auth/rezervační/objednávková/custom flow a potvrdit, že předvyplnění není matoucí na sdíleném zařízení.
- 2026-07-04: runtime `text_spacing_guardrails` hlídá core CSS proti zápornému `letter-spacing`, textovému ořezu přes ellipsis/line clamp a `!important` zámkům na text-spacing vlastnostech. Ručně zbývá browser průchod s text-spacing override.
- 2026-07-04: runtime `public_error_suggestion_guardrails` a HTTP integrace hlídají, že veřejná matematická ověřovací otázka v kontaktu, newsletteru, odběru vývěsky, Food objednávkách, guest rezervacích a Form Builderu vrací field-level chybu s konkrétním návrhem opravy. Guest rezervace navíc mapují chyby jména, e-mailu, telefonu, počtu osob a slotu/času na konkrétní ovládací prvky. Ručně zbývá širší copy pass administračních a custom validačních hlášek.
- 2026-07-04: unit testy Form Builder error suggestions, runtime `public_forms_http_guardrails` a HTTP `public_form_submit_http` hlídají, že custom povinná, e-mailová, URL, výběrová a upload pole Form Builderu vrací konkrétní field-level návrhy oprav a po neúspěšném odeslání nezanechají uloženou neplatnou přílohu.
- 2026-07-04: runtime `admin_field_error_guardrails` hlídá, že URL chyby v administraci míst a podcastů radí použít http/https adresu, doménu bez schématu nebo prázdné volitelné pole místo obecného „platný formát“.
- 2026-07-04: runtime `admin_field_error_guardrails` hlídá také plánovací datum/čas chyby u stránek, článků, novinek, událostí, anket, rezervačních zdrojů a podcastových epizod. Text musí poradit výběr platné hodnoty v poli datum/čas, prázdné volitelné plánování, odstranění prázdného řádku nebo opravu pořadí začátku/konce.
- 2026-07-04: runtime `admin_field_error_guardrails` hlídá také vybrané administrační e-mailové chyby. Text musí poradit úplnou adresu ve tvaru `jmeno@example.cz`, prázdné volitelné pole nebo jedinečnost přihlašovací adresy.
- 2026-07-04: runtime `admin_field_error_guardrails` hlídá také běžné datumové chyby ve vývěsce, jídelních lístcích a položkách ke stažení. Text musí poradit výběr kalendářního data, prázdné volitelné pole nebo opravu pořadí od/do; datum vydání u downloadů musí mít field-level chybu přes `aria-describedby`.
- 2026-07-04: runtime `admin_field_error_guardrails` hlídá také zdrojové chyby položek ke stažení. Text musí poradit lokální soubor nebo externí odkaz, http/https nebo doménový tvar URL, prázdné volitelné URL pole a přesný 64znakový SHA-256 checksum; field-level chyby musí být přes `aria-describedby` navázané na existující prvky.
- 2026-07-05: editor rezervačního zdroje už pro klientské chyby hromadného generátoru slotů a přidání blokovaného dne nepoužívá prohlížečový `alert()`. Runtime `admin_field_error_guardrails` hlídá inline textové `role="alert"` prvky, konkrétní návrhy oprav, focus na dotčené pole a zákaz návratu starých alertů.
- 2026-07-05: sdílené administrační AJAX řazení už při uložení nebo selhání nevyvolává prohlížečový `alert()`. Runtime `admin_field_error_guardrails` hlídá vloženou textovou hlášku s `role="status"` / `role="alert"`, `aria-atomic="true"`, konkrétní retry text při selhání a zákaz návratu starého alertu.
- 2026-07-05: runtime `admin_field_error_guardrails` hlídá také reply formuláře v detailech kontaktu, chatu a odpovědi Form Builderu. Při prázdném předmětu nebo textu odpovědi musí být souhrnný alert doplněný field-level chybou u obou polí přes existující `aria-describedby`, `aria-invalid` a konkrétní návrh opravy.
- 2026-07-05: runtime `admin_field_error_guardrails` a HTTP `form_issue_preset_http` hlídají také GitHub issue bridge v detailu odpovědi Form Builderu. Chyba vytvoření issue musí navázat repozitář, název a tělo issue na field-level text; chyba ručního napojení musí navázat URL existující issue. Všechny odkazy přes `aria-describedby` musí mířit na existující prvky.
- 2026-07-05: runtime `admin_field_error_guardrails` a HTTP `form_issue_preset_http` hlídají také hlavní chybové stavy editoru Form Builderu. Název, slug, notifikační e-mail, pole pro potvrzovací e-mail odesílateli a webhook URL musí mít souhrnný alert, `aria-invalid`, existující `aria-describedby` a konkrétní návrh opravy.
- 2026-07-05: runtime `admin_field_error_guardrails` a HTTP scénář blogových statických stránek hlídají také hlavní chybové stavy editoru statických stránek. Název, slug, přiřazený blog, plánované publikování a plánované zrušení publikace musí mít souhrnný alert, `aria-invalid`, existující `aria-describedby` a konkrétní návrh opravy.
- 2026-07-05: runtime `admin_field_error_guardrails` a HTTP `faq_categories_feedback_http` hlídají také hlavní chybové stavy editoru FAQ. Otázka, odpověď a slug veřejné stránky musí mít souhrnný atomický alert, field-level text a konkrétní návrh opravy; odkazy přes `aria-describedby` musí mířit na existující prvky.
- 2026-07-05: runtime `admin_field_error_guardrails` a HTTP `faq_categories_feedback_http` hlídají také hlavní chybové stavy editoru FAQ kategorií. Název, slug a meta title musí mít souhrnný atomický alert, field-level text a konkrétní návrh opravy; odkazy přes `aria-describedby` musí mířit na existující prvky.
- 2026-07-05: runtime `admin_field_error_guardrails` a HTTP `blog_taxonomy_landing_http` hlídají také hlavní chybové stavy blogových kategorií a štítků. Povinný název a duplicitní slug musí mít souhrnný atomický alert, field-level text a konkrétní návrh opravy; odkazy přes `aria-describedby` musí mířit na existující prvky.
- 2026-07-05: runtime `admin_field_error_guardrails` a HTTP `downloads_catalog_versions_http` hlídají také hlavní chybové stavy kategorií a sérií ke stažení. Prázdný název, nepoužitelný nebo duplicitní slug a příliš dlouhý meta title kategorie musí mít souhrnný atomický alert, field-level text a konkrétní návrh opravy; odkazy přes `aria-describedby` musí mířit na existující prvky.
- 2026-07-05: runtime `admin_field_error_guardrails` a HTTP scénáře `board_save_http`, `events_types_places_recurrence_http`, `contact_topics_and_reply_http`, `chat_topics_threads_support_http` a `reservations_http` hlídají také kategorie vývěsky, typy akcí, témata kontaktu/chatu a rezervační kategorie/místa. Povinné názvy, nepoužitelné nebo duplicitní slugy a příliš dlouhý meta title musí mít souhrnný atomický alert, field-level text, existující `aria-describedby` cíle a zachované hodnoty tam, kde se po chybě formulář znovu vykreslí.
- 2026-07-04: runtime `admin_field_error_guardrails` hlídá také vybrané obrazové upload chyby v článcích, vývěsce, událostech, místech a downloadech. Text musí poradit JPEG/PNG/GIF/WebP, zákaz SVG a prázdné volitelné pole; download náhled musí mít field-level chybu přes existující `aria-describedby`.
- 2026-07-04: runtime `admin_field_error_guardrails` hlídá také neobrazové upload chyby u přílohy vývěsky a audio souboru podcastové epizody. Text musí poradit povolené dokumentové/audio formáty, prázdné volitelné pole a u podcastu alternativu externího audio odkazu.
- 2026-07-04: runtime `media_library_guardrails` hlídá, že hromadný upload a náhrada souboru v knihovně médií po redirectu nezůstávají jen ve flash alertu. File input musí dostat field-level chybu přes existující `aria-describedby` a text musí poradit podporovaný formát do 10 MB, zákaz SVG, u náhrady stejnou MIME rodinu a u veřejných souborů stejnou příponu.
- 2026-07-04: runtime `admin_field_error_guardrails` a HTTP `admin_import_error_suggestions_http` hlídají importní formuláře. JSON import, WordPress WXR, eStránky XML a downloader fotografií musí po chybě zobrazit alert, field-level chybu přes existující `aria-describedby` a konkrétní opravu pro exportní soubor, UTF-8 nebo URL webu.

## Prostředí

- Windows + Firefox + NVDA.
- Windows + Chrome nebo Edge bez čtečky pro keyboard-only a zoom.
- Zoom 200 % a 400 %.
- Viewport 320 px šířky a běžný desktop.
- Vypnutá myš nebo test bez použití myši.

Pro WCAG `1.4.12 Text Spacing` použít bookmarklet, devtools nebo uživatelský stylesheet s ekvivalentem:

```css
* {
  line-height: 1.5 !important;
  letter-spacing: 0.12em !important;
  word-spacing: 0.16em !important;
}

p {
  margin-bottom: 2em !important;
}
```

## Obecná pravidla průchodu

- Testovat čistou instalaci i reprezentativní web s obsahem.
- U každého nálezu uložit URL, krok, očekávané chování, skutečné chování a dotčené WCAG kritérium.
- Pokud je chyba v obsahu autora, označit ji jako author-content issue, ne jako core CMS defect.
- Author-content issue posuzovat podle `author-content-checklist.md`: alt text, obrázek s textem, přepis, titulky, jazyk části, text odkazu, tabulka, barva, vlastní HTML, PDF nebo externí embed.
- Pokud chyba vzniká v core UI nebo default šabloně, založit opravnou položku v `a11y-remediation-backlog.md`.
- Při návrhu nového modulu projít tento protokol ještě před implementací a označit scénáře, které modul rozšiřuje nebo které bude potřeba doplnit.

## Scénáře veřejného webu

1. Otevřít homepage a přes skip link přejít na obsah.
2. Projít hlavní navigaci, vyhledávání, footer widgety a sociální odkazy jen klávesnicí.
3. Ve footeru ověřit navigaci `Pomoc a kontakt`: čtečka má ohlásit nadpis, odkazy Kontakt a Chat mají být ve stejném pořadí na homepage i na jiné veřejné stránce a nemají se zobrazit, pokud jsou oba moduly vypnuté.
4. Otevřít blog index, článek s osnovou, kategorii, štítek a sérii.
5. Odeslat komentář se správnými i chybnými hodnotami.
6. Otevřít Form Builder formulář, způsobit chybu, opravit ji a odeslat; u polí pro jméno, e-mail, telefon, URL a firmu ověřit, že prohlížeč nebo správce hesel nabídne odpovídající autofill. U prázdného povinného textu, výběru, souhlasu a uploadu, neplatného e-mailu, neplatné URL, nepovolené výběrové hodnoty, chybné přílohy a chybné ověřovací otázky ověřit, že čtečka oznámí field-level text s konkrétním návrhem opravy.
7. Otevřít galerie album a detail fotografie, ověřit alt text, figcaption a metadata.
8. Otevřít media/PDF/audio/video snippet a ověřit názvy iframe/playerů. U přímého videa s WebVTT titulky a audio-description stopou ověřit, že prohlížeč nabízí stopy se správným jazykem a názvem; u audio/video snippetu s `transcript` ověřit dosažitelný odkaz na přepis.
9. Otevřít podcastovou epizodu s vyplněným přepisem a ověřit, že čtečka oznámí sekci `Přepis epizody` jako textovou alternativu audia; u externích video embedů ověřit titulky nebo popsanou odpovědnost autora.
10. Projít reprezentativní článek nebo stránku podle `author-content-checklist.md`: obrázky s alt textem, obrázky s textem, cizojazyčný úsek s `lang`, srozumitelné odkazy, nadpisy, tabulky, barvu, vlastní HTML a externí embed.
11. Otevřít ankety, FAQ feedback, chat, kontakt a newsletter subscribe; jako přihlášený veřejný uživatel ověřit, že kontaktní formulář předvyplní známé jméno a e-mail, ale dovolí hodnoty upravit a po validační chybě je nepřepíše zpět profilem.
12. Otevřít board, downloads, events, places, reservations a food detail; u guest rezervace vyvolat chybnou ověřovací otázku a ověřit field-level text s návrhem opravy u pole captcha. U Food objednávky jako přihlášený veřejný uživatel ověřit předvyplněné jméno, e-mail a telefon, ruční úpravu a zachování upravených hodnot po chybě.
13. Ověřit 404, 429, potvrzení e-mailu, odhlášení newsletteru a maintenance stránku.

## Scénáře administrace

1. Přihlášení, registrace, 2FA, tokenový reset hesla a odhlášení bez myši; registrace a žádost o reset nesmí vyžadovat matematickou CAPTCHA ani jiný kognitivní test.
2. Dashboard: Moje zkratky, fronta ke schválení, poslední aktivita a statistiky.
3. Levá navigace a command centrum přes `Ctrl+K`.
4. Otevřít `Nápověda a podpora` ze spodní části administrační navigace, ověřit stabilní pořadí před odkazy `Web` a `Odhlásit se`, dosažitelnost jen klávesnicí, smysluplné nadpisy sekcí a odkazy na command centrum, profil, kontaktní workflow, nastavení a dokumentaci.
5. Media/content picker: otevření, hledání, změna typu výsledku, vložení a zavření; v čistém HTML editoru vybrat cizojazyčný text, použít helper `Jazyk části textu`, ověřit vložení `<span lang="en">…</span>`, live oznámení a zachování fokusu.
6. Widget dialog: otevření, změna typu widgetu, focus trap, uložení, návrat fokusu.
7. Editor blogového článku, blogových kategorií/štítků, statické stránky, FAQ a FAQ kategorií: dlouhá editace, content lock heartbeat, uložení a chybové stavy. U statické stránky ověřit chyby názvu, slugu, přiřazeného blogu, plánovaného publikování a plánovaného zrušení publikace; u blogových kategorií a štítků ověřit prázdný název a duplicitní slug; u FAQ ověřit prázdnou otázku/odpověď a neplatný nebo duplicitní slug; u FAQ kategorií ověřit prázdný název, slug bez použitelného znaku, duplicitní slug a příliš dlouhý meta title. Souhrnný `role="alert"` má upozornit na dotčené pole, pole má mít `aria-invalid`, `aria-describedby` na existující nápovědu/chybu a text má radit konkrétní opravu.
8. Form Builder: přidání pole, změna typu pole, chybové stavy a náhled. U editoru formuláře ověřit chyby názvu, slugu, notifikačního e-mailu, potvrzovacího e-mailového pole a webhook URL: souhrnný `role="alert"` má upozornit na dotčené pole, pole má mít `aria-invalid`, `aria-describedby` na existující nápovědu/chybu a text má radit konkrétní opravu.
9. Admin tabulky s hromadnými akcemi: media, comments, contact, chat, statistics.
10. V detailu kontaktní zprávy, chat zprávy a odpovědi Form Builderu zkusit odeslat e-mailovou odpověď bez předmětu nebo bez textu. Ověřit souhrnný `role="alert"`, field-level chybu u předmětu i textu, `aria-invalid`, `aria-describedby` na existující prvky a konkrétní radu doplnit předmět nebo text odpovědi. V detailu odpovědi Form Builderu navíc vyvolat chybu vytvoření GitHub issue a chybu napojení existující issue; ověřit stejný pattern u repozitáře, názvu issue, těla issue a URL existující issue.
11. Upload a editace média včetně alt textu, licence a kolekce.
12. Nastavení webu, nastavení modulů, import/export a migrace.
13. U míst a podcastů zadat neplatnou URL do volitelného URL pole a ověřit, že field-level chyba vysvětluje povolený http/https nebo doménový tvar a možnost pole vynechat.
14. U administračních e-mailových polí zadat neúplnou hodnotu bez domény nebo zavináče a ověřit, že chyba radí úplnou adresu ve tvaru `jmeno@example.cz`; u volitelných polí možnost pole vynechat a u přihlašovacího e-mailu jedinečnost adresy.
15. U běžných datumových polí ve vývěsce, jídelních lístcích a downloadech zadat neplatné datum a ověřit, že chyba radí kalendářní datum, prázdné volitelné pole nebo opravu pořadí od/do; u data vydání ověřit i field-level chybu a `aria-describedby`.
16. U položek ke stažení ověřit chybějící zdroj, neplatný externí odkaz, neplatnou domovskou stránku projektu, neplatný SHA-256 checksum a nepovolený upload. Každá chyba musí mít srozumitelný form-level alert i field-level text, `aria-describedby` na existující prvek, zachovanou hodnotu pole a konkrétní opravu: lokální soubor nebo externí URL, http/https nebo doména bez schématu, prázdné volitelné URL pole, případně přesně 64 znaků `0-9` a `a-f`. V administraci kategorií a sérií ke stažení navíc ověřit prázdný název, nepoužitelný nebo duplicitní slug a příliš dlouhý meta title kategorie; souhrnný alert má být atomický a čtečka má oznámit konkrétní field-level návrh opravy. Stejný ruční průchod zopakovat u kategorií vývěsky, typů akcí, témat kontaktu/chatu a rezervačních kategorií/míst: zkontrolovat povinný název, slugové chyby, existující `aria-describedby` cíle a zachování rozepsaných hodnot po chybě.
17. U obrazových uploadů v článku, vývěsce, události, místě a downloadu zkusit nepovolený formát včetně SVG. Ověřit form-level alert i field-level text, zachované hodnoty, existující `aria-describedby`, radu JPEG/PNG/GIF/WebP, výslovné odmítnutí SVG a možnost volitelné pole nechat prázdné; u download náhledu ověřit i novou lokální chybu u pole.
18. U přílohy vývěsky a audio souboru podcastové epizody zkusit nepovolený formát. Ověřit form-level alert i field-level text, existující `aria-describedby`, radu povolených formátů, zachované hodnoty a možnost nechat volitelné pole prázdné; u podcastu ověřit i srozumitelnou alternativu externího audio odkazu.
19. V knihovně médií odeslat prázdný hromadný upload, SVG nebo nepodporovaný soubor a ověřit, že po redirectu existuje form-level alert i field-level chyba u `media_files` s `aria-invalid` a `aria-describedby` na existující text. V detailu média zkusit náhradu souborem mimo MIME rodinu nebo s jinou příponou veřejného souboru a ověřit stejný pattern u `replacement_file`.
20. U importů odeslat prázdný JSON import, prázdný WordPress WXR import, prázdný eStránky XML import a u downloaderu fotek kombinaci prázdného XML/neplatné URL. Ověřit `role="alert"`, field-level text u konkrétního pole, existující `aria-describedby`, zachování zadané URL u downloaderu a srozumitelnou opravu: exportní soubor, platné UTF-8 nebo http/https/doménový tvar URL.
21. U plánování publikace, ukončení publikace, rezervační dostupnosti a časových rozsahů zadat neplatnou datum/čas hodnotu a ověřit, že chyba radí použít ovladač datum/čas, nechat volitelné pole prázdné, odstranit prázdný řádek nebo opravit pořadí začátku a konce. V editoru rezervačního zdroje navíc vyvolat klientskou chybu hromadného generátoru slotů a přidání blokovaného dne bez data; ověřit, že se nezobrazí prohlížečový alert, ale inline `role="alert"` text s návrhem opravy a focus zůstane na poli, které je potřeba opravit.
22. U řaditelných administračních seznamů, například blogů, použít tlačítka Nahoru/Dolů, klávesovou zkratku Ctrl+šipka i drag/drop. Ověřit, že úspěšné uložení pořadí vytvoří textovou status hlášku, simulované selhání sítě nebo serveru vytvoří inline `role="alert"` s retry textem a nikde se nezobrazí prohlížečový alert.
23. Při 320 px šířce a 400 % zoomu ověřit, že admin navigace nepřekrývá hlavní obsah, tabulky rolují jen ve své ose, dlouhé fieldsety nevyvolávají horizontální scroll hlavního obsahu, action rows zůstávají ovladatelné a focus není schovaný mimo viditelný scroll; po 320px ověření hlavních hustých tabulek a reprezentativních dlouhých formulářů pokračovat hlavně přes 400 % zoom, custom moduly, sticky/anchor skoky a keyboard-only/NVDA kombinace.

## Scénáře pro nové moduly

Před větší implementací modulu zapsat, zda přidává nebo mění:

- veřejný výpis, detail, filtr, widget, sitemapu, RSS/ICS/CSV/PDF export nebo tokenový endpoint;
- administrační přehled, hromadné akce, dialog, picker, upload, rich text editor nebo dlouhý formulář;
- autorem dodávaný obsah, média, embedy, přílohy, alt texty, titulky, transcript nebo vlastní HTML;
- captcha/auth flow, časový limit, rate-limit, automatické přesměrování, live region nebo stavové hlášky;
- tabulky, drag/drop, ovládání pouze myší, nové barvy, nové ikony nebo význam nesený vizuálním stylem.

Každá kladná odpověď musí mít ruční testovací scénář, automatizovatelný guardrail nebo záznam v `a11y-remediation-backlog.md`.

## Kontrolní checklist

- Viditelný focus je stále vidět a není zakrytý sticky prvkem.
- Pořadí tabulátoru odpovídá vizuální a logické hierarchii.
- Žádný dialog neuzamkne klávesnici bez možnosti zavřít ho.
- Čtečka hlásí název, roli, stav a hodnotu ovládacích prvků.
- Stavové hlášky se oznámí jednou, neruší při každém napsaném znaku a klientské validační chyby nepoužívají prohlížečový alert tam, kde lze zobrazit textový inline alert.
- Každé pole má srozumitelný label a chyba je spojena s polem.
- Chybové hlášky, kde je známý opravný krok, neříkají jen „chybná hodnota“, ale stručně radí, jak pokračovat.
- Ručně vložený obsah prošel `author-content-checklist.md` a každý nález je označený jako core CMS defect, theme defect nebo author-content issue.
- Cizojazyčný úsek v author contentu má odpovídající `lang`; helper `Jazyk části textu` je použitelný bez myši a nevytváří neexistující ARIA vazby.
- Autentizační flow nevyžaduje řešení hádanek, opisování CAPTCHA ani jiný kognitivní test bez alternativy.
- Správce hesel nabídne vyplnění veřejného i administračního přihlášení a rozpozná vytvoření nebo změnu hesla bez ručního přepisování.
- Autofill u veřejného kontaktu, objednávkové poptávky, guest rezervace a Form Builder formuláře nenabízí zavádějící hodnoty a u běžných osobních polí rozpozná jméno, e-mail, telefon, URL nebo organizaci.
- Veřejný kontakt a Food objednávka nepřinutí přihlášeného veřejného uživatele znovu zadat kontaktní údaje, které CMS bezpečně zná z profilu, a po ruční úpravě nezahodí zadanou hodnotu při validační chybě.
- TOTP pole je použitelné jen klávesnicí, rozpoznatelné jako jednorázový kód a jeho chyba se oznámí jako jeden alert.
- Tokenový reset hesla jde dokončit bez znovuzadávání údajů, které už CMS zná, a chybový token má srozumitelnou textovou zpětnou vazbu.
- Admin nápověda je dostupná ze stabilního místa v navigaci, má smysluplné nadpisy a nevyžaduje hledání odlišné podpory na každé obrazovce.
- Veřejný footer default šablony má stabilní `Pomoc a kontakt` navigaci a custom theme nezavádí odlišný, hůře dostupný help/contact vzor.
- Tabulky mají caption nebo pojmenování přes skutečný nadpis.
- Odkazy otevírané v novém okně to oznamují ve svém přístupném názvu.
- Při zoomu 200 % a 400 % nedochází ke ztrátě obsahu nebo vodorovnému scrollu mimo povolené výjimky.
- Při text spacing override se text neztrácí, nepřekrývá sousední obsah, není zkrácený ellipsis/line clampem a ovládací prvky zůstávají použitelné.
- Na 320 px jsou primární akce dosažitelné a použitelné.
- Runtime audit `admin_mobile_reflow_guardrails` prochází pro admin mobilní baseline, datové tabulky, media grid, Form Builder gridy, statistics gridy, forms overview tabulku, husté moduly comments/contact/chat/reservations/food/downloads/gallery/podcast, dlouhé form controls a rezervační otevírací dobu, widget řazení, cache-busting admin stylesheetu a minimální rozměr řadicích ovladačů, sekundárních action odkazů i přímých action odkazů v odstavcích.
- Datové tabulky mohou mít horizontální scroll, ale nesmí rozšířit celou stránku spolu se sidebar navigací ani schovat focusovaný prvek bez možnosti ho dorolovat.
- Runtime audit `contrast_focus_guardrails` prochází pro default/admin/login text, skip link, focus, hranice inputů a hranice tlačítek.
- Runtime audit `text_spacing_guardrails` prochází pro core CSS bez záporného `letter-spacing`, textového ořezu a text-spacing `!important` zámků.
- Ručně změřené hover, active, disabled, ikony, progress bary a custom theme settings splňují AA; u textu alespoň 4.5:1, u focusu a hranic ovládacích prvků alespoň 3:1.
- Focus prstenec je při klávesnici viditelný na světlém i tmavém pozadí a nezaniká pod outline/box-shadow jiného prvku.

## Zápis výsledků

Pro každý nález použít formát:

```text
ID:
Datum:
Testovací prostředí:
URL / obrazovka:
Kroky:
Očekáváno:
Skutečnost:
WCAG:
Priorita:
Poznámka:
```

Po každém ručním kole aktualizovat `wcag-22-aa-conformance.md` a podle potřeby `acr-vpat-wcag-draft.md`.
