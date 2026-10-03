# Digitální obchod: implementační kontrakt

Nový modul `shop`, veřejně `/shop/`, prodává pouze soubory jednoho provozovatele. Není tržištěm více prodejců. Výchozí stav je vypnutý a prodej nelze spustit bez identity prodejce, obchodních podmínek, informací o reklamacích a odstoupení, ochrany soukromí, bankovního účtu a výslovného potvrzení provozovatele, že nastavení ověřil. Software neposkytuje právní ani daňovou certifikaci; zahraniční režim a sazby musí potvrdit odborník pro konkrétní provoz.

OSS je oddělený od tuzemského plátcovství: `non_vat` = neplátce, `vat` = plátce, `oss` = plátce v OSS a `non_vat_oss` = neplátce v tuzemsku / identifikovaná osoba v OSS. Poslední varianta vyžaduje DIČ, nulovou tuzemskou sazbu a ověřená pravidla pro zahraničí. [Finanční správa: registrace do OSS](https://financnisprava.gov.cz/cs/mezinarodni-spoluprace/mezinarodni-spoluprace-a-dph/one-stop-shop-oss/rezim-eu/registrace).

Soukromý odkaz ke stažení platí 365 dní od prvního zpřístupnění; opakování e-mailu jej neprodlužuje. Omezení odkazu nemění licenci již staženého souboru. Celková cena je omezená na 9 999 999,99 Kč podle podporovaného formátu QR platby.

## Rozsah a bezpečné výchozí volby

- Nákup hosta i přihlášeného uživatele využívá stejné účty `cms_users` jako rezervace. Registrace není podmínkou koupě; hostovi se objednávka nikdy nepřiřazuje pouze podle shody e-mailu.
- Jedna měna v první verzi: CZK, také pro zahraniční kupující. Prodej do země bez výslovně aktivního daňového pravidla se odmítne. Režimy prodejce `non_vat`, `vat`, `oss`, `non_vat_oss`; pravidla zemí určují sazby podle tříd `general` a `publication` a daňové vysvětlení. B2B reverse charge ani automatické posouzení nároků na osvobození nejsou podporovány; jde o prodej spotřebitelům.
- Zahraniční objednávka vyžaduje kontrolu před dodáním: dvě různé, neprotiřečící si kategorie důkazů země (fakturační adresa, bankovní podklady, geolokace nebo jiné ověřitelné obchodní údaje), poznámku správce a potvrzení daňového režimu. Dvě pouhá prohlášení zákazníka nejsou dva nezávislé důkazy. Modul nepředstírá, že neplátce má automaticky všude nulovou daň.
- Pouze stavy `accepted`, `awaiting_payment`, `paid`, `fulfilled`, `cancelled`, `refunded`. Chyba e-mailu, kontrola daně a chyba API jsou samostatné provozní informace, nikoli další objednávkové stavy.
- Konečná cena včetně daně je zkontrolována na serveru před přijetím; částky v haléřích, sazby v setinách procent, nikdy účetní výpočty s float. Snapshot identity, ceny, souboru, právních textů, souhlasu a banky se pozdější editací katalogu nezmění.
- Dva samostatné, výchozím stavem nezaškrtnuté souhlasy: obchodní podmínky / elektronický doklad a výslovné dodání digitálního obsahu před uplynutím lhůty s potvrzením vědomí ztráty práva na odstoupení až při zpřístupnění. Práva z vad zůstávají zachovaná. Jména `confirm_terms`, `confirm_digital`; obnova konceptu nepřebírá souhlas. Tlačítko `Objednávka zavazující k platbě`.
- Platební výzva je označená jako nedaňový doklad. Finální faktura obsahuje jedinečné číslo, identitu a adresu obou stran, IČO/DIČ prodejce podle režimu, rozsah plnění, data vystavení a přijetí úplaty, základ, sazbu, daň a celkem. Přijetí platby se nevydává za datum skutečného zpřístupnění; případné datum plnění se uvádí jen tehdy, pokud je skutečně známé. Neplátce neoznačuje tuzemský dokument jako daňový doklad. Refundace znamená výslovně zaznamenanou již provedenou refundaci, ne automatický bankovní příkaz, a vyžaduje opravný doklad.
- QR používá SPAYD s IBAN, přesnou částkou, CZK a číselným VS shodným s číslem objednávky (rok + šestimístné pořadí). Obrázek má přesný alt `qr kód k platbě`; údaje jsou také čitelným textem. Stejná alternativa je v tagu Figure PDF. Faktura má vždy i přístupnou HTML/textovou podobu; PDF/UA ani úplná WCAG shoda se bez ověření netvrdí.
- Soubory, Fio klíč a kryptografický klíč mimo webroot. Žádné přímé URL uploadů; endpoint znovu kontroluje objednávku, dodání, souhlas, revokaci a expiraci. Unikátní náhodný token, v DB hash a šifrovaná hodnota jen pro opakování e-mailu. Tokenové stránky `no-store`, `noindex`, `no-referrer`, žádná analytika.
- Fio: pouze pevný HTTPS host `fioapi.fio.cz`, read-only token, omezené velikosti a timeouty, kontrola účtu výpisu, měny, přesné kladné částky, VS, data a unikátního ID pohybu. Žádné načítání requestem dodané URL; token/odpověď/e-mail se nelogují. Periodové API s překryvem a lokální deduplikací, ne destruktivní bankovní kurzor. Nesprávná částka nebo neznámý VS nikdy neuvolní soubor.
- Deaktivace bankovní metody platí pro nové nákupy. Pokud má metoda Fio token a dřívější objednávku čekající na platbu, cron ji dál kontroluje; po poslední úhradě přestane. Odstranění tokenu zastaví automatickou kontrolu i dřívějších objednávek. Účet přijatých objednávek nelze zpětně přepsat.
- E-mail přes existující `sendMail()`, přílohy PDF, HTML a text včetně trvalé kopie podmínek/souhlasu. Pokud vložené písmo neumí jméno zákazníka či jiné znaky, úplná HTML/textová faktura nahradí PDF; jazyk nesmí zablokovat dodání zaplaceného obsahu. Platba zůstává zaplacená i při chybě e-mailu; cron opakuje omezeně, správce může odeslání znovu vyvolat. SMTP přijetí není zárukou doručení a při havárii po SMTP může být oznámení zopakované, nikdy nová platba či jiný nárok.
- Konfigurace prodejce se při závazném odeslání znovu načte pod databázovým zámkem. Změna právních textů nebo pozastavení obchodu zneplatní starou rekapitulaci, nikoli rozepsané údaje. Zahraniční objednávka nemá platební výzvu, dokud správce nepotvrdí daňovou kontrolu.
- Příprava příloh probíhá mimo zámek objednávky; bezprostředně před SMTP se znovu zamkne objednávka a ověří nezměněný stav i vlastnictví odesílacího claimu. Zámek zůstane do zápisu výsledku, takže souběžné storno nemůže pustit zastaralou platební výzvu. Expirace claimu a retry se počítá v MySQL, nikoli porovnáním SQL času s odlišným pásmem PHP.
- Online odstoupení: soukromý detail objednávky nabízí funkci `Odstoupit od smlouvy` před zpřístupněním obsahu, samostatnou rekapitulaci a potvrzovací POST. Identita a smlouva se neopisují znovu. Nezaplacená i zaplacená nedodaná objednávka se zruší; u zaplacené musí provozovatel vrátit peníze a až poté zaznamenat refundaci. Původní prohlášení s UTC časem se nemění a potvrzuje se trvalou HTML/textovou přílohou. Opakování nesmí vytvořit další událost. Zpřístupněné digitální plnění s čerstvým souhlasem nepřijímá běžné odstoupení, ale neomezuje zákonná práva z vad. Funkce není omezena 14denním softwarovým limitem u dosud nedodaného obsahu.

## Databázový kontrakt

Všechny tabulky InnoDB/UTF-8 v `install.php` i `migrate.php`, bez FK kvůli existujícímu stylu CMS.

- `cms_shop_categories`: id, name, slug, description, is_active, sort_order.
- `cms_shop_products`: id, category_id, title, slug, description, requirements, license_text, update_policy, price_cents, tax_class, file_storage_name, file_original_name, file_size, file_sha256, is_active, created_at, updated_at.
- `cms_shop_payment_methods`: id, name, account_number, iban, is_active, fio_token_encrypted, fio_last_polled_at, fio_last_error, created_at.
- `cms_shop_tax_rules`: id, country_code, country_name, general_rate_bp, publication_rate_bp, tax_note, is_active; unikátní country_code.
- `cms_shop_sequences`: sequence_key (PK), sequence_value.
- `cms_shop_orders`: id, order_number (unikátní 10 číslic), user_id nullable, status, customer_name, email, address, city, postal_code, country_code, payment_method_id, total_cents, tax_cents, currency, seller_snapshot, legal_snapshot, payment_snapshot, token_hash (unikátní), token_encrypted, token_expires_at, consent_at, tax_verified_at nullable, tax_evidence nullable, created_at, paid_at nullable, fulfilled_at nullable, cancelled_at nullable, confirmation_sent_at nullable, delivery_sent_at nullable, mail_claim_until nullable, mail_attempts, mail_retry_at nullable, mail_last_error, mail_sent_status, mail_claim_token nullable.
- `cms_shop_order_items`: id, order_id, product_id, title, quantity, unit_price_cents, total_cents, tax_rate_bp, tax_cents, file_storage_name, file_original_name, file_size, file_sha256, product_snapshot (`MEDIUMTEXT`, více textových údajů produktu v jednom neměnném snapshotu).
- `cms_shop_payments`: id, payment_method_id, bank_transaction_id, order_id, amount_cents, currency, received_at; unikát payment_method_id + bank_transaction_id.
- `cms_shop_invoices`: id, order_id, kind (`proforma`/`final`/`credit`), invoice_number, snapshot_json, created_at; unikát order_id + kind i invoice_number. Neměnné snapshoty, žádné přepisování historie.
- `cms_shop_order_events`: id, order_id, event_type, note, user_id nullable, created_at; žádné logování tokenů nebo bankovních výpisů.

## Sdílené API pro paralelní implementaci

`lib/shop.php` (integrátor):

- `shopSchema(): array<string,string>` je DDL helper pro lokální testy; instalační/migrační SQL jsou také doslova v jejich souborech kvůli existujícím auditům.
- `shopSettings(): array<string,string>` klíče `seller_name`, `seller_address`, `seller_ico`, `seller_dic`, `seller_email`, `seller_phone`, `seller_register`, `vat_mode`, `terms`, `privacy`, `complaints`, `withdrawal`, `ready` z `shop_*` nastavení.
- `shopReady(): bool`, `shopMoney(int $cents): string`, `shopRateTax(int $grossCents,int $rateBp): int`, `shopAmountCents(string $amount): ?int`, `shopSlug(string): string`, `shopStates(): array<string,string>`.
- `shopPrivatePath(string $name): string`, `shopStoreUpload(array $file): array` vrací `file_storage_name`, `file_original_name`, `file_size`, `file_sha256` nebo vyhodí DomainException. Soubory jsou immutable hash `.bin` v `shop/files` mimo webroot.
- `shopSeal(string): string`, `shopOpen(string): string`, `shopToken(array $order): string`, `shopOrderUrl(array $order): string`, `shopOrderByToken(PDO,string): ?array`, `shopOrderItems(PDO,int): array`.
- `shopCreateOrder(PDO,array $customer,array $cart,int $paymentId): array` customer: `customer_name,email,address,city,postal_code,country_code,confirm_terms,confirm_digital,quote_signature`; cart mapa product_id => množství. Uživatel jen `currentUserId()` serverově, cena/daň/soubor jen DB. Vrací objednávku po commitu. Volající zachová chybný vstup, nikoli souhlas.
- `shopRecordPayment(PDO,int $orderId,string $reference,?string $receivedAt=null,?int $methodId=null): bool` atomický zápis; `shopChangeOrder(PDO,int,string,string=''): void` akce `cancel`,`refund`.
- `shopVerifyTax(PDO,int,array $evidence): void` evidence: `country`, `type_one`,`type_two`,`note`, `confirm_tax`.
- `shopDispatchOrder(PDO,int): bool`, `shopCron(PDO): void`, `shopCanDownload(array): bool`, `shopInvoice(PDO,array,string): array` řádka s `snapshot_json`, `invoice_number`, `kind`; snapshot `order`, `items`, `settings`, `legal`, `payment`, `issued_at`, `kind`, `invoice_number`.
- `shopSafeHeaders(): void` (no-store/noindex/no-referrer/nosniff), `shopFindOwnedOrder(PDO,int): ?array`.

`lib/shop_invoice.php` (faktury): `shopInvoiceHtml(array $snapshot): string` kompletní samostatný HTML dokument s nadpisy a caption, `shopInvoicePdf(array $snapshot): string` bytes, `shopPaymentSpayd(array $snapshot): string`, `shopQrPng(string $spd): string` PNG bytes, `shopNormalizeIban(string $account): string` validní CZ nebo zahraniční IBAN, `shopInvoiceText(array $snapshot): string`. QR encoder/font pokud převzaté mají vlastní licence a NOTICE; pluginový kód nepřebírat.

`admin/shop.php`, `admin/shop_product.php`, `admin/shop_categories.php`, `admin/shop_settings.php`, `admin/shop_orders.php`, `admin/shop_order.php`: katalog a nastavení spravuje `requireSuperAdmin()`; všechny používají modulový guard. Produktový editor jednoduchý PRG/flash, zachované údaje a field-level chyby. Objednávky přímo volají sdílené API, nemají vlastní platební stavovou logiku.

`shop/index.php`, `shop/product.php`, `shop/cart.php`, `shop/checkout.php`, `shop/order.php`, `shop/download.php`, `shop/invoice.php`, `shop/my.php`, `shop/legal.php`: veřejný katalog a tokenové endpointy. Vždy společný bootstrap `db.php`; HTML přes `renderPublicPage()` a view v `themes/default/views/modules/shop-*.php`. Checkout bere serverový košík `$_SESSION['shop_cart']`, CSRF/captcha/honeypot/rate-limit a fresh souhlasy; žádné JS vyžadované. Cart bezpečný POST, PRG a textový stav. Předvyplnění přes `currentUserContactDefaults()`. Žádné veřejné připojení hosta k účtu e-mailem.

## Podklady

- [ČOI: digitální obsah a odstoupení](https://coi.gov.cz/faq/c-v-jakych-pripadech-nemohu-od-smlouvy-odstoupit-2/), [ČOI: objednávkové tlačítko](https://coi.gov.cz/tlacitko/).
- [Finanční správa: OSS](https://financnisprava.gov.cz/cs/dane/dane-elektronicky/danovy-portal/zvlastni-rezim-jednoho-spravniho-mista-moss-oss), [EU 282/2011, zejména čl. 24b a 24f](https://eur-lex.europa.eu/eli/reg_impl/2011/282/2021-07-01/eng).
- [Fio oficiální API](https://www.fio.cz/docs/cz/API_Bankovnictvi.pdf), [QR Platba specifikace](https://qr-platba.cz/pro-vyvojare/).
- [Ministerstvo financí: online odstoupení od 19. června 2026](https://mf.gov.cz/cs/ministerstvo/media/tiskove-zpravy/2026/prehledne-ktere-zmeny-pripravilo-ministerstvo-fina-62343), [směrnice EU 2023/2673, čl. 11a](https://eur-lex.europa.eu/eli/dir/2023/2673/oj/eng).

Právní texty nesmějí být automaticky vydávané za univerzálně platné obchodní podmínky. Zahraniční daňové povinnosti a doklady kontroluje provozovatel s účetním/daňovým poradcem; pravidla mimo EU mohou vyžadovat další registrace a lokální dokumentaci.
