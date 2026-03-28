<?php
// E-mailové odesílání – SMTP s podporou AUTH/TLS – extrahováno z db.php

/**
 * Vrátí absolutní URL včetně schématu a domény – pro použití v e-mailech.
 */
function siteUrl(string $path = ''): string
{
    $base = BASE_URL;
    if ($base === '' || !str_starts_with($base, 'http')) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host . $base;
    }
    return $base . $path;
}

function sendNewsletterSubscriptionConfirmation(string $recipient, string $token): bool
{
    $siteName = getSetting('site_name', 'Kora CMS');
    $confirmUrl = siteUrl('/subscribe_confirm.php?token=' . $token);
    $subject = 'Potvrďte přihlášení k odběru – ' . $siteName;
    $body = "Dobrý den,\n\n"
        . "pro potvrzení odběru novinek webu {$siteName} klikněte na odkaz:\n"
        . $confirmUrl . "\n\n"
        . "Pokud jste se k odběru nepřihlásili, tento email ignorujte.\n\n"
        . "— " . $siteName;

    return sendMail($recipient, $subject, $body);
}

/**
 * Odešle e-mail v UTF-8 přes SMTP. Vrátí true při úspěchu.
 *
 * Konfigurace přes konstanty v config.php (SMTP_HOST, SMTP_PORT,
 * SMTP_USER, SMTP_PASS, SMTP_SECURE). Pokud nejsou definovány,
 * použije se localhost:25 bez autentizace.
 */
function sendMail(string $to, string $subject, string $body): bool
{
    $from        = getSetting('contact_email', 'noreply@localhost');
    $safeSubject = preg_replace('/[\r\n]/', '', $subject);
    $safeFrom    = preg_replace('/[\r\n]/', '', $from);
    $safeTo      = preg_replace('/[\r\n]/', '', $to);

    $smtpHost   = defined('SMTP_HOST')   ? SMTP_HOST   : (ini_get('SMTP') ?: 'localhost');
    $smtpPort   = defined('SMTP_PORT')   ? (int) SMTP_PORT : (int)(ini_get('smtp_port') ?: 25);
    $smtpUser   = defined('SMTP_USER')   ? SMTP_USER   : '';
    $smtpPass   = defined('SMTP_PASS')   ? SMTP_PASS   : '';
    $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : '';

    // SSL – připojení přes ssl:// wrapper
    $target = ($smtpSecure === 'ssl') ? 'ssl://' . $smtpHost : $smtpHost;
    $smtp = @fsockopen($target, $smtpPort, $errno, $errstr, 5);
    if (!$smtp) {
        error_log("sendMail FAILED: connect {$target}:{$smtpPort} – {$errstr}");
        return false;
    }

    // Čtení SMTP odpovědi (včetně víceřádkových)
    $read = function () use ($smtp): string {
        $response = '';
        while (($line = fgets($smtp, 512)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    };

    $expect = function (string $prefix) use ($read, &$smtp): ?string {
        $resp = $read();
        if (!str_starts_with(trim($resp), $prefix)) {
            error_log("sendMail FAILED: expected {$prefix}, got: {$resp}");
            fwrite($smtp, "QUIT\r\n");
            fclose($smtp);
            return null;
        }
        return $resp;
    };

    if ($expect('220') === null) return false;

    fwrite($smtp, "EHLO localhost\r\n");
    if ($expect('250') === null) return false;

    // STARTTLS
    if ($smtpSecure === 'tls') {
        fwrite($smtp, "STARTTLS\r\n");
        if ($expect('220') === null) return false;
        if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            error_log("sendMail FAILED: STARTTLS handshake selhalo");
            fclose($smtp);
            return false;
        }
        // Po STARTTLS je nutný nový EHLO
        fwrite($smtp, "EHLO localhost\r\n");
        if ($expect('250') === null) return false;
    }

    // Autentizace (AUTH LOGIN)
    if ($smtpUser !== '' && $smtpPass !== '') {
        fwrite($smtp, "AUTH LOGIN\r\n");
        if ($expect('334') === null) return false;
        fwrite($smtp, base64_encode($smtpUser) . "\r\n");
        if ($expect('334') === null) return false;
        fwrite($smtp, base64_encode($smtpPass) . "\r\n");
        if ($expect('235') === null) return false;
    }

    fwrite($smtp, "MAIL FROM:<{$safeFrom}>\r\n");
    if ($expect('250') === null) return false;
    fwrite($smtp, "RCPT TO:<{$safeTo}>\r\n");
    if ($expect('250') === null) return false;
    fwrite($smtp, "DATA\r\n");
    if ($expect('354') === null) return false;

    $msg = "From: {$safeFrom}\r\n"
         . "To: {$safeTo}\r\n"
         . "Subject: {$safeSubject}\r\n"
         . "Reply-To: {$safeFrom}\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "MIME-Version: 1.0\r\n"
         . "\r\n"
         . str_replace("\n.", "\n..", $body) . "\r\n.\r\n";

    fwrite($smtp, $msg);
    $dataResp = $read();
    fwrite($smtp, "QUIT\r\n");
    fclose($smtp);

    $ok = str_starts_with(trim($dataResp), '250');
    if (!$ok) {
        error_log("sendMail FAILED: SMTP said: {$dataResp}");
    }
    return $ok;
}

// ──────────────── Notifikační e-mail pro správce ─────────────────────────────

/**
 * Vrátí e-mail příjemce notifikací (admin_email → contact_email fallback).
 */
function notificationRecipient(): string
{
    $email = trim(getSetting('admin_email', ''));
    if ($email === '') {
        $email = trim(getSetting('contact_email', ''));
    }
    return $email;
}

/**
 * Notifikace: nový formulář odeslán.
 */
function notifyFormSubmission(string $formTitle, array $data, string $recipientOverride = '', string $subjectOverride = ''): void
{
    if (getSetting('notify_form_submission', '1') !== '1') {
        return;
    }
    $recipient = trim($recipientOverride);
    if ($recipient === '') {
        $recipient = notificationRecipient();
    }
    if ($recipient === '') {
        return;
    }

    $siteName = getSetting('site_name', 'Kora CMS');
    $adminUrl = siteUrl('/admin/forms.php');
    $body = 'Na webu ' . $siteName . ' byl odeslán formulář „' . $formTitle . "\".\n\n";

    foreach ($data as $key => $value) {
        $body .= $key . ': ' . $value . "\n";
    }

    $body .= "\nSprávce formulářů: " . $adminUrl . "\n";

    $subject = trim($subjectOverride);
    if ($subject === '') {
        $subject = 'Nové odeslání formuláře „' . $formTitle . '" – ' . $siteName;
    }

    if (!sendMail($recipient, $subject, $body)) {
        error_log("sendMail FAILED: notifikace formuláře pro {$recipient}");
    }
}

function sendFormSubmitterConfirmation(array $form, array $fieldsByName, array $submissionData): bool
{
    if ((int)($form['submitter_confirmation_enabled'] ?? 0) !== 1) {
        return false;
    }

    $emailField = trim((string)($form['submitter_email_field'] ?? ''));
    if ($emailField === '') {
        return false;
    }

    $recipient = trim((string)($submissionData[$emailField] ?? ''));
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $siteName = getSetting('site_name', 'Kora CMS');
    $subject = trim((string)($form['submitter_confirmation_subject'] ?? ''));
    if ($subject === '') {
        $subject = 'Potvrzení odeslání formuláře „' . trim((string)($form['title'] ?? 'Formulář')) . '” – ' . $siteName;
    }

    $bodyTemplate = trim((string)($form['submitter_confirmation_message'] ?? ''));
    if ($bodyTemplate === '') {
        $bodyTemplate = "Děkujeme za odeslání formuláře „{{form_title}}\".\n\nVaše zpráva byla úspěšně přijata.\n\n— {{site_name}}";
    }

    $body = formRenderTemplate($bodyTemplate, formTemplatePlaceholderMap($form, $fieldsByName, $submissionData));

    if (!sendMail($recipient, $subject, $body)) {
        error_log("sendMail FAILED: potvrzení odesílateli formuláře pro {$recipient}");
        return false;
    }

    return true;
}

/**
 * Notifikace: obsah čeká na schválení.
 */
function notifyPendingContent(string $moduleLabel, string $title, string $adminPath): void
{
    if (getSetting('notify_pending_content', '1') !== '1') {
        return;
    }
    $recipient = notificationRecipient();
    if ($recipient === '') {
        return;
    }

    $siteName = getSetting('site_name', 'Kora CMS');
    $adminUrl = siteUrl($adminPath);
    $body = "Na webu {$siteName} čeká nový obsah na schválení.\n\n"
        . "Typ: {$moduleLabel}\n"
        . "Název: {$title}\n\n"
        . "Ke schválení: {$adminUrl}\n";

    if (!sendMail($recipient, "Obsah čeká na schválení: {$title} – {$siteName}", $body)) {
        error_log("sendMail FAILED: notifikace pending obsahu pro {$recipient}");
    }
}

/**
 * Notifikace: nová zpráva v chatu (volitelné, výchozí vypnuto).
 */
function notifyChatMessage(string $authorName, string $message): void
{
    if (getSetting('notify_chat_message', '0') !== '1') {
        return;
    }
    $recipient = notificationRecipient();
    if ($recipient === '') {
        return;
    }

    $siteName = getSetting('site_name', 'Kora CMS');
    $adminUrl = siteUrl('/admin/chat.php');
    $preview = mb_substr($message, 0, 200, 'UTF-8');
    $body = "Na webu {$siteName} přibyla nová zpráva v chatu.\n\n"
        . "Autor: {$authorName}\n"
        . "Zpráva: {$preview}\n\n"
        . "Správa chatu: {$adminUrl}\n";

    if (!sendMail($recipient, "Nová zpráva v chatu – {$siteName}", $body)) {
        error_log("sendMail FAILED: notifikace chatu pro {$recipient}");
    }
}
