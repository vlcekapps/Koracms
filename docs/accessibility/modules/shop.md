# Digitální obchod: modulový accessibility conformance pass

## Rozsah a stav

Cíl je WCAG 2.2 AA. Příloha pokrývá katalog, detail produktu, košík, dvoukrokovou objednávku, osobní přehled, tokenový detail, chráněné stažení, právní informace, faktury a administraci. Není právní certifikací ani tvrzením PDF/UA. Ruční NVDA/Firefox a mobilní ověření konkrétního hostingu zůstává otevřené.

## Rozhodnutí a důkazy

| Kritérium | Implementace a automatizovaný důkaz | Zbývající ověření |
|---|---|---|
| 1.1.1 Netextový obsah | QR má přesný alt `qr kód k platbě`; účet, částka a variabilní symbol jsou také textem. `build/shop_invoice_selftest.php` kontroluje HTML a PDF Figure Alt. | NVDA a PDF čtečka, skutečná bankovní aplikace. |
| 1.3.1, 1.3.2 Informace a pořadí | Nadpisy, pojmenované oblasti, fieldset/legend, labely, tabulky s caption. Textové ceny a stavy nejsou spojovány pouze CSS mezerou. Theme audit a `shopHttpChecks()` kontrolují veřejný DOM. | Pořadí čtení delší objednávky a PDF. |
| 1.3.5 Účel vstupu | Checkout používá autocomplete pro jméno, e-mail a fakturační adresu; registrace není povinná. | Automatické doplňování v reálném prohlížeči. |
| 1.4.1, 1.4.3 Barva a kontrast | Stávající veřejná/admin témata, textové stavy, žádná nová barevná signalizace bez textu. | Kontrast vlastní šablony a 200–400% zoom. |
| 1.4.10, 1.4.12 Reflow a text | Nativní jednoduché formuláře a již existující responsive styly; bez povinných grafických komponent. | 320 CSS px, text spacing a dlouhé názvy produktů. |
| 2.1.1, 2.1.2 Klávesnice | Bez drag-and-drop, JavaScriptu nebo časovaného dialogu nutného k nákupu. | Kompletní nákup jen klávesnicí. |
| 2.4.1–2.4.7, 2.4.11 Navigace a focus | Běžný layout zachovává skip link, nadpis a focus. Platba a stažení mají popisné odkazy; potvrzení objednávky není nečekaný externí redirect. | Focus po PRG, zvětšení a sticky prvky vlastní šablony. |
| 2.5.3, 2.5.8 Ovládání | Viditelné názvy odpovídají přístupným názvům; běžná tlačítka a checkboxy. | Rozměry cíle na mobilu. |
| 3.2.1, 3.2.2 Předvídatelnost | Úprava košíku, rekapitulace a závazné odeslání jsou výslovné POST akce. | Čtení stavových zpráv při návratu. |
| 3.3.1–3.3.4 Chyby a finanční akce | Field-level chyby s existujícími ARIA cíli, zachování běžných polí, samostatná serverová rekapitulace konečné ceny. Změna ceny vyžaduje nové potvrzení. Souhlasy `confirm_terms` a `confirm_digital` nikdy nejsou obnovený koncept. Unit/MySQL/HTTP testy chrání cenovou kontrolu a duplicity. | NVDA čtení souhrnu chyb a potvrzení ceny. |
| 3.3.7 Opakované zadávání | Účet předvyplní jméno/e-mail; nákup je možný i bez účtu. Fakturační pole se uchovají při chybě, nikoli captcha/souhlas. | Pokus se špatnou captchou a oprava jedné adresní položky. |
| 3.3.8 Přístupná autentizace | Stávající účet a přihlášení CMS; obchod přihlášení nevynucuje. | Správce hesel a veřejný login. |
| 4.1.2, 4.1.3 Název a stav | Nativní HTML ovládací prvky, pojmenované search oblasti, chyby/stavy bez rušivého oznamování po každém znaku. Theme audit a HTTP testy. | Stav po odeslání a po doručení, NVDA. |

## Finanční a bezpečnostní hranice

- Objednávka má dvě fáze: údaje a rekapitulaci; pouze poslední tlačítko `Objednávka zavazující k platbě` vytváří závazek.
- Captcha chrání skutečné odeslání, společně s CSRF, honeypotem a rate-limitem. Neúspěch nesmí smazat fakturační údaje ani automaticky zaškrtnout právní souhlas.
- Zahraniční objednávka jasně uvádí, že nejprve čeká na kontrolu země a daně, nikoli na platbu. Není to další matoucí veřejný stavový systém.
- Neplátce, plátce a obě varianty OSS jsou nastavení provozovatele, nikoli automatické právní rozhodnutí CMS. Tuzemské plátcovství a OSS se neztotožňují. Právní texty, sazby, licence a kompatibilita produktů jsou odpovědností prodejce.
- Tokenové stránky a přílohy mají `no-store`, `noindex`, `no-referrer`; data hosta se nespojují s účtem jen podle e-mailu.
- Faktury mají rovnocennou HTML a textovou alternativu. PDF obsahuje český font, textovou mapu a značky, ale shoda PDF/UA ani čitelnost všech čteček se bez ručního testu netvrdí.
- Omezení písma PDF nesmí ztratit znaky ani zablokovat dodání: úplná HTML/textová příloha zůstává povinná a při nepodporovaných znacích nahrazuje PDF. MySQL self-test ověřuje dodání zákazníkovi s ne-latinkovým jménem.
- Import nepřenáší objednávky, soukromé soubory, bankovní klíče ani platební historii. Importované produkty a daňová pravidla jsou neaktivní; prodej vyžaduje nové ověření.
- Online odstoupení před digitálním dodáním má samostatnou rekapitulaci, čerstvé `confirm_withdrawal` a tlačítko `Potvrdit odstoupení`. Identita a číslo smlouvy se nepřepisují znovu; vlastník účtu nebo držitel soukromého tokenu potvrzuje uložené údaje. Idempotentní zrušení nepředstírá refundaci a zákazník dostane trvalou HTML/textovou kopii prohlášení s UTC časem. Chrání se 3.3.4 i 3.3.7 a práva z vad zůstávají vysvětlená.
- Před odesláním platebního oznámení se stav objednávky znovu ověří pod databázovým zámkem, který chrání i samotné SMTP odeslání proti souběžnému stornu/refundaci. Čas odesílacího zámku, opakování i autorizace odkazu se posuzuje databázovým časem; odlišné pásmo PHP nezpůsobí dvojí oznámení. MySQL test chrání aktivní zámek a pořadí finančních akcí.
- Platební volba `Aktivní pro nové objednávky` má vysvětlení navázané přes `aria-describedby`: vypnutí metody neblokuje úhrady dřívějších objednávek. Fio je dál kontroluje, dokud čekají na platbu; odstranění tokenu kontrolu zastaví. MySQL test ověřuje zákaz nového nákupu, výběr účtu pro cron, časový limit, úhradu i deduplikaci pohybu.

## Ruční protokol před nasazením

1. Klávesnicí najít produkt, upravit košík a zkontrolovat cenu; nevyžaduje se účet.
2. Zkusit neplatný e-mail/captchu a nepotvrzené podmínky. Ověřit souhrn i konkrétní pole a zachované hodnoty.
3. V rekapitulaci změnit katalogovou cenu v druhém okně; staré potvrzení musí být odmítnuté bez objednávky.
4. Odeslat objednávku a obnovit stránku; nesmí vzniknout další objednávka.
5. Zkusit stejnou e-mailovou adresu hosta a účtu; cizí objednávka se do účtu nesmí připojit.
6. Ověřit platební výzvu, fakturu, opravu a celý přiložený smluvní text v HTML, NVDA a PDF čtečce. QR číst jako `qr kód k platbě` a dekódovat v bankovní aplikaci bez odeslání platby.
7. Na 320 CSS px a 400% zoom prověřit dlouhé názvy, účet, podmínky, tabulky a focus.
8. V administraci ověřit daňové potvrzení, ruční úhradu a refundaci; kritická potvrzení se neobnovují po chybě.
9. Klávesnicí odstoupit od dosud nedodané objednávky jako host i přihlášený vlastník. Ověřit rekapitulaci, potvrzení, nevynucené opakování údajů, e-mailový doklad a nemožnost následného dodání; refundaci nezaměnit za samotné prohlášení.

Automatizované důkazy: `build/shop_selftest.php`, `build/shop_mysql_selftest.php`, `build/shop_http.php`, `build/shop_invoice_selftest.php`, `build/runtime_audit.php`, schema parity, theme audit a `composer ci:module-ready`.

## Ověřený výsledek implementačního bloku

Lokálně na PHP 8.4.12 prošel celý `composer ci:module-ready`: lint, statická analýza obchodu na levelu 6, schema/theme/ACR guardrails, runtime audit a HTTP integrace včetně `digital_shop_http`. Samostatné důkazy obchodu zahrnují 94 offline kontrol, 428 MySQL kontrol a fakturační test s 13 dokumentovými scénáři a 176 QR maticemi. Jediná přijatá výjimka runtime auditu je `smtp_connectivity = SKIP`, protože produkční SMTP není z lokálního prostředí dosažitelné.

Tento výsledek nepotvrzuje reálné doručení, skutečný bankovní pohyb ani ruční NVDA/PDF/zoom testy. Ty zůstávají v protokolu před spuštěním prodeje; neoznačují se jako provedené automatickým testem.
