# RC Audit Kora CMS, 2026-09-05

## Rozsah a metoda

Výchozí revize `1903413ffc0cf4198320439d342750900bbcf8a4`, větev `main`, verze `5.0.0-beta.2`. Před auditem byl strom čistý a poslední GitHub CI úspěšné. Audit kombinuje čtení kódu, izolované reprodukce, testy skutečných PHP/JS helperů a HTTP workflow na localhostu. Nálezy jsou číslované stejně jako průběžná sdělení správci.

Prošly kontrolou autentizace a oprávnění, rate-limity, instalace/migrace, import/export, publikační podmínky, Blog/Stránky/Novinky/Události, Autoři, FAQ, Chat/Kontakt, Podcasty, Média/Galerie/Ke stažení/Appmarket, Rezervace, Ankety, Newsletter/Vývěska, Food/Recepty a související šablony, widgety a společné helpery. Hloubka není u každého modulu stejná: prioritou byly soukromí, ztráta dat, neplatné stavy a přístupné chybové workflow. Nejde o záruku absence všech chyb ani bezpečnostní certifikaci.

## Registr potvrzených nálezů

| ID | Priorita | Nález | Dotčené části | Stav a důkaz |
|---|---|---|---|---|
| RC-01 | P1 | Krátký rate-limit a hodinový úklid resetují delší ochranu. | auth.php, cron.php, install.php, migrate.php | Opraveno; 10 izolovaných MySQL scénářů a 18 expiry testů. |
| RC-02 | P1 | Relace zachová odebranou roli nebo smazaný účet. | auth.php, db.php | Opraveno a aktivováno se souhlasem; 70 unit kontrol, 127 legacy PDO kontrol a skutečné HTTP revokace/2FA prošly. |
| RC-03 | P1 | Rozpracované 2FA neexpiruje a používá starou identitu. | admin/login.php, admin/login_2fa.php | Opraveno a aktivováno se souhlasem; 70 unit kontrol, 127 legacy PDO kontrol a skutečné HTTP revokace/2FA prošly. |
| RC-04 | P1 | Rezervace přijímá nenabízené nebo neplatné termíny. | reservations/book.php | Opraveno; 186 izolovaných kontrol rezervací, včetně konfliktů a souběhu. |
| RC-05 | P1 | Staré schválení může obnovit mezitím zrušenou rezervaci. | admin/res_booking_save.php | Opraveno; 186 izolovaných kontrol rezervací, včetně konfliktů a souběhu. |
| RC-06 | P2 | Kapacita intervalových rezervací počítá součet místo souběhu. | reservations/book.php | Opraveno; 186 izolovaných kontrol rezervací, včetně konfliktů a souběhu. |
| RC-07 | P2 | Ruční přidání rezervace odmítá obsazenost i pod kapacitou. | admin/res_booking_add.php | Opraveno; 186 izolovaných kontrol rezervací, včetně konfliktů a souběhu. |
| RC-08 | P2 | Tokenové storno dovolí zrušit probíhající rezervaci. | reservations/cancel_booking.php | Opraveno; 186 izolovaných kontrol rezervací, včetně konfliktů a souběhu. |
| RC-09 | P1 | Koš podcastu neblokuje veřejné mediální endpointy. | lib/presentation.php, podcast/ | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-10 | P1 | Odmítnutá editace podcastu může smazat původní audio. | admin/podcast_save.php | Opraveno; 334 kontrol editorů a skutečný JS autosave. |
| RC-11 | P1 | Autosave vizuálního editoru zamění obsah za perex/excerpt. | admin/layout.php | Opraveno; 334 kontrol editorů a skutečný JS autosave. |
| RC-12 | P1 | Blogová stránka je dostupná před datem publikace. | blog/page.php | Opraveno; veřejná HTTP matice prošla. |
| RC-13 | P2 | Expirace článků a stránek závisí na dalším běhu cronu. | blog/, page.php, feed.php, sitemap.php, lib/widgets.php | Opraveno; veřejná HTTP matice prošla. |
| RC-14 | P2 | Náhledům stránek, novinek a událostí chybí soukromé hlavičky. | page.php, news/article.php, events/event.php | Opraveno; 334 kontrol editorů a skutečný JS autosave. |
| RC-15 | P2 | Náhled novinky funguje i po přesunu do koše. | news/article.php | Opraveno; 334 kontrol editorů a skutečný JS autosave. |
| RC-16 | P2 | Náhled konceptu blogové stránky nefunguje. | blog/page.php | Opraveno; 334 kontrol editorů a skutečný JS autosave. |
| RC-17 | P2 | Canonical přesměrování náhledu zahodí token. | news/article.php, events/event.php | Opraveno; 334 kontrol editorů a skutečný JS autosave. |
| RC-18 | P2 | Chyba data publikace novinky nemá field-level zpětnou vazbu. | admin/news_form.php | Opraveno; 334 kontrol editorů a skutečný JS autosave. |
| RC-19 | P2 | Obnova konceptu zahodí vícenásobný výběr souvisejících článků. | admin/layout.php | Opraveno; 334 kontrol editorů a skutečný JS autosave. |
| RC-20 | P2 | Odmítnuté editory bez JS ztrácejí rozepsané hodnoty. | admin/news_*, admin/event_*, admin/faq_*, admin/podcast_* | Opraveno; 334 kontrol editorů a skutečný JS autosave. |
| RC-21 | P1 | Médium rozpoznané jako text může zachovat aktivní HTML příponu. | lib/media_library.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-22 | P1 | Webhook následuje nevalidované redirecty na interní cíle. | lib/webhooks.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-23 | P1 | Fotografie nebo album v koši zůstávají dostupné přes obrazový endpoint. | gallery/image.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-24 | P1 | Přímá URL souboru obchází viditelnost modulu Ke stažení. | .htaccess, uploads/.htaccess, build/http_server_router.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-25 | P1 | Obnova odvozených souborů smaže WebP originál. | lib/media_library.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-26 | P1 | Neplatná metadata mohou přesunout soukromé médium na veřejné místo. | admin/media.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-27 | P1 | Vadná náhrada obrázku může zničit původní bajty. | lib/media_library.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-28 | P2 | Webhook přijímá interní IPv4-mapped IPv6 adresy. | lib/webhooks.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-29 | P2 | Přesun média hlásí úspěch i při neodstraněné veřejné kopii. | lib/media_library.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-30 | P2 | Odmítnuté odstranění jediného download zdroje smaže blob. | admin/download_save.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-31 | P2 | Kontrola použití médií přehlíží Appmarket a PDF reference. | lib/media_library.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-32 | P2 | Veřejné TXT médium má nefunkční blokovanou URL. | lib/media_library.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-33 | P2 | Mazání download obrázku ponechá WebP derivát. | lib/presentation.php | Opraveno; 164 mediálních kontrol a 34 GET/HEAD kontrol na Apache i routeru. |
| RC-34 | P2 | Připomínky rezervací mohou hladovět za limitem první stovky. | cron.php | Opraveno; izolované provozní regrese s odchyceným e-mailem. |
| RC-35 | P2 | Parametr voted v URL předstírá hlas a odhalí výsledky. | polls/index.php | Opraveno; izolované provozní regrese s odchyceným e-mailem. |
| RC-36 | P2 | Výsledky ankety nastavené na vždy nejsou vedle formuláře. | themes/default/views/modules/polls-index.php | Opraveno; izolované provozní regrese s odchyceným e-mailem. |
| RC-37 | P2 | Selhání potvrzovacího e-mailu newsletteru nemá funkční retry. | subscribe.php | Opraveno; izolované provozní regrese s odchyceným e-mailem. |
| RC-38 | P2 | Plánované zveřejnění vývěsky obchází evidenci a upozornění. | cron.php | Opraveno; izolované provozní regrese s odchyceným e-mailem. |
| RC-39 | P2 | Strukturální změna či obnova historie může ponechat publikovaný neúplný recept. | admin/recipe_content.php, admin/recipe_history.php, lib/recipes.php | Opraveno; 442 provozních kontrol zahrnuje i rollback a přístupnou chybu obnovy historie. |
| RC-40 | P3 | Kopírování může ohlásit úspěch při selhání schránky. | themes/default/layouts/base.php | Opraveno; skutečný JS ověřen včetně selhání a návratu fokusu. |
| RC-41 | P2 | Hledání, sitemap a navigace nedodržují viditelnost a blogový kontext stránek. | search.php, sitemap.php, lib/stats.php | Opraveno; veřejná HTTP matice prošla. |
| RC-42 | P1 | Apache přepíše soukromou cache náhledu podle podvrhnutelného User-Agentu. | .htaccess, auth.php | Opraveno; skutečný Apache potvrzuje no-store náhledu a veřejnou cache homepage, doplněná HTTP regrese. |
| RC-43 | P2 | Zpřísnění přihlášení může zablokovat migraci staré tabulky účtů bez is_confirmed. | lib/session_security.php, admin/login.php, admin/login_2fa.php, public_login.php | Opraveno v závěrečném review; 70 unit a 127 izolovaných PDO kontrol. Chybějící historický sloupec není explicitní nepotvrzení; veřejné a neznámé role výjimku nedostanou. |

## Ověření a podmínky RC

- Závěrečný `composer ci:module-ready` prošel s exit kódem 0 na PHP 8.4.12. Zahrnuje lint, PHPStan, formát, schema/module/ACR a release-package kontroly, 1531 stávajících unit testů, nové RC regrese, runtime audit a úplnou HTTP integraci.
- RC regrese zahrnují 18 expiry kontrol, 70 session kontrol, 127 izolovaných legacy PDO kontrol, 186 kontrol rezervací, 442 provozních kontrol modulů, 334 kontrol editorů a 164 mediálních kontrol. Skutečný JavaScript autosave a schránky je také ověřený. HTTP pokrývá revokaci přihlášení, 2FA, publikační okna a soukromé náhledy.
- Jedinou výjimkou runtime auditu je `smtp_connectivity = SKIP`: nakonfigurovaný SMTP server není z tohoto prostředí dosažitelný. Doručitelnost produkčních e-mailů tak není tímto během potvrzená.
- `composer audit --locked` nehlásí známá zranitelnostní upozornění vývojových závislostí; tato kontrola nenahrazuje audit vloženého knihovního kódu.
- Souběh rezervací má navíc 26 kontrol na skutečném MySQL/InnoDB: dva PHP procesy, čekání na zámku zdroje před prvním vložením a následné odmítnutí přeplněného termínu po commitu. Test uklidí vlastní data a neodesílá e-maily.
- `git diff --check` prošel. Samotná zelená statická kontrola není důkaz, že chyba runtime neexistuje.
- RC se v tomto kroku nevydává. Aktivace opravy relací/2FA byla výslovně schválena; po nasazení se dosavadní relace znovu přihlásí. Rozpracovaný druhý faktor platí deset minut, nikoli celý editor. Neuzavřený P1 je release blocker.
- Před nasazením uložit rozepsané změny a zálohovat DB i soubory. Po nahrání balíčku spustit `migrate.php` (nová expirace rate-limitů), ověřit přihlášení, chyby editorů, nové i existující soubory, náhledy, e-maily a cron na hostingu. Staré sdílené přímé odkazy do `uploads/downloads/` nahradit kontrolovaným endpointem.
- Hosting lze v tomto provozu ověřit až po nahrání. Tento smoke test není podmínkou commitu ani sestavení kandidáta, ale podmínkou přijetí nasazení; bez něj se hosting neoznačuje za ověřený. Při závažném selhání vrátit soubory i databázi z odpovídající zálohy, nikoli jen starý PHP kód nad nově změněnými daty.
- Zbývá ruční NVDA/Firefox a klávesnicový průchod, skutečný zoom 200–400 %, reflow, kontrast custom theme a ověření hostingu. V tomto auditu se takové testy nevydávají za provedené.
- Souborové HTTP ochrany jsou ověřené na Apache a vývojovém routeru; Nginx ukázka byla upravena, ale zde nebyla spuštěna. Starší aktivní soubory zůstávají na disku, přímé URL jsou blokované; před nasazením zkontrolovat inventář a odkazy. Při neobnovitelné chybě souborového rollbacku se zachová soukromá záchranná kopie a diagnostika pro správce; pád procesu uprostřed operace nebyl simulovaný.

## Přístupnost

Opravy chrání před ztrátou zadání a souborů, zpřesňují chybové hlášky a zachovávají focus. Relevantní jsou hlavně WCAG 2.2 `2.1.1`, `2.2.1`, `2.4.3`, `3.3.1`, `3.3.3`, `3.3.4`, `3.3.7`, `4.1.2` a `4.1.3`. Report nesměšuje chování jádra s autorským obsahem a nezvyšuje globální ACR stav na Supports jen na základě automatických testů. Další podmínky viz [ACR backlog](accessibility/a11y-remediation-backlog.md).
