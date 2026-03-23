# Repository Guardrails

Tento projekt cílí na stabilní provoz, bezpečnost a přístupnost. Při každé úpravě dodržujte tato pravidla:

- Všechny české texty zapisujte v UTF-8 se správnou diakritikou.
- V souborech, které načítají `db.php` nebo `config.php`, nepoužívejte lokální proměnné `$user`, `$pass`, `$server`, `$database` pro jiný účel než DB připojení.
- Redirecty odvozené z requestu nebo formuláře vždy validujte přes `internalRedirectTarget()`.
- U formulářů používejte `label`, `fieldset` a `legend`; `aria-describedby` a `aria-labelledby` smí odkazovat jen na reálně existující elementy.
- U textu a stavových indikací držte kontrast alespoň na úrovni WCAG 2.2 AA; nepřenášejte význam pouze barvou.
- Na veřejných i admin stránkách zachovejte skip link a viditelný focus stav.
- Před odevzdáním změn spusťte PHP lint a runtime audit: `php build/runtime_audit.php`.
