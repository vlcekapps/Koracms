# Manual Accessibility Test Protocol

Tento protokol popisuje ruční ověření pro `wcag-22-aa-conformance.md`. Ruční test je povinný pro kritéria, která nejde spolehlivě uzavřít čistě statickým auditem.

Aktuální ruční evidence:

- 2026-07-04: auth flow pro WCAG 2.2 `3.3.8 Accessible Authentication (Minimum)` potvrzen jako funkční se správcem hesel, TOTP jednorázovým kódem, tokenovým resetem a chybovými stavy; opakovat při změnách auth/session chování.
- 2026-07-04: command centrum, widget dialog a content/media picker potvrzené bez regrese při ručním keyboard-only/NVDA průchodu; opakovat při změnách JS dialogů, focus trapu nebo admin layoutu.

## Prostředí

- Windows + Firefox + NVDA.
- Windows + Chrome nebo Edge bez čtečky pro keyboard-only a zoom.
- Zoom 200 % a 400 %.
- Viewport 320 px šířky a běžný desktop.
- Vypnutá myš nebo test bez použití myši.

## Obecná pravidla průchodu

- Testovat čistou instalaci i reprezentativní web s obsahem.
- U každého nálezu uložit URL, krok, očekávané chování, skutečné chování a dotčené WCAG kritérium.
- Pokud je chyba v obsahu autora, označit ji jako author-content issue, ne jako core CMS defect.
- Pokud chyba vzniká v core UI nebo default šabloně, založit opravnou položku v `a11y-remediation-backlog.md`.
- Při návrhu nového modulu projít tento protokol ještě před implementací a označit scénáře, které modul rozšiřuje nebo které bude potřeba doplnit.

## Scénáře veřejného webu

1. Otevřít homepage a přes skip link přejít na obsah.
2. Projít hlavní navigaci, vyhledávání, footer widgety a sociální odkazy jen klávesnicí.
3. Otevřít blog index, článek s osnovou, kategorii, štítek a sérii.
4. Odeslat komentář se správnými i chybnými hodnotami.
5. Otevřít Form Builder formulář, způsobit chybu, opravit ji a odeslat.
6. Otevřít galerie album a detail fotografie, ověřit alt text, figcaption a metadata.
7. Otevřít media/PDF/audio/video snippet a ověřit názvy iframe/playerů.
8. Otevřít ankety, FAQ feedback, chat, kontakt a newsletter subscribe.
9. Otevřít board, downloads, events, places, reservations a food detail.
10. Ověřit 404, 429, potvrzení e-mailu, odhlášení newsletteru a maintenance stránku.

## Scénáře administrace

1. Přihlášení, registrace, 2FA, tokenový reset hesla a odhlášení bez myši; registrace a žádost o reset nesmí vyžadovat matematickou CAPTCHA ani jiný kognitivní test.
2. Dashboard: Moje zkratky, fronta ke schválení, poslední aktivita a statistiky.
3. Levá navigace a command centrum přes `Ctrl+K`.
4. Media/content picker: otevření, hledání, změna typu výsledku, vložení a zavření.
5. Widget dialog: otevření, změna typu widgetu, focus trap, uložení, návrat fokusu.
6. Editor blogového článku: dlouhá editace, content lock heartbeat, uložení a chybové stavy.
7. Form Builder: přidání pole, změna typu pole, chybové stavy a náhled.
8. Admin tabulky s hromadnými akcemi: media, comments, contact, chat, statistics.
9. Upload a editace média včetně alt textu, licence a kolekce.
10. Nastavení webu, nastavení modulů, import/export a migrace.

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
- Stavové hlášky se oznámí jednou a neruší při každém napsaném znaku.
- Každé pole má srozumitelný label a chyba je spojena s polem.
- Autentizační flow nevyžaduje řešení hádanek, opisování CAPTCHA ani jiný kognitivní test bez alternativy.
- Správce hesel nabídne vyplnění veřejného i administračního přihlášení a rozpozná vytvoření nebo změnu hesla bez ručního přepisování.
- TOTP pole je použitelné jen klávesnicí, rozpoznatelné jako jednorázový kód a jeho chyba se oznámí jako jeden alert.
- Tokenový reset hesla jde dokončit bez znovuzadávání údajů, které už CMS zná, a chybový token má srozumitelnou textovou zpětnou vazbu.
- Tabulky mají caption nebo pojmenování přes skutečný nadpis.
- Odkazy otevírané v novém okně to oznamují ve svém přístupném názvu.
- Při zoomu 200 % a 400 % nedochází ke ztrátě obsahu nebo vodorovnému scrollu mimo povolené výjimky.
- Na 320 px jsou primární akce dosažitelné a použitelné.
- Kontrast textu, ikon, hranic inputů, focusu a stavových prvků splňuje AA.

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
