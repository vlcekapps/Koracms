<?php

function settingsFlashPull(): array
{
    $flash = $_SESSION['settings_flash'] ?? [];
    unset($_SESSION['settings_flash']);
    return is_array($flash) ? $flash : [];
}

function settingsFlashSet(array $flash): void
{
    $_SESSION['settings_flash'] = $flash;
}

function settingsDefaultFormState(): array
{
    return [
        'site_name' => getSetting('site_name'),
        'site_description' => getSetting('site_description'),
        'contact_email' => getSetting('contact_email'),
        'site_profile' => currentSiteProfileKey(),
        'board_public_label' => getSetting('board_public_label', boardModulePublicLabel()),
        'public_registration_enabled' => getSetting('public_registration_enabled', '1'),
        'github_issues_enabled' => getSetting('github_issues_enabled', '0'),
        'github_issues_repository' => getSetting('github_issues_repository', ''),
        'news_per_page' => getSetting('news_per_page', '10'),
        'blog_per_page' => getSetting('blog_per_page', '10'),
        'events_per_page' => getSetting('events_per_page', '10'),
        'blog_authors_index_enabled' => getSetting('blog_authors_index_enabled', '0'),
        'comments_enabled' => getSetting('comments_enabled', '1'),
        'comment_moderation_mode' => commentModerationMode(),
        'comment_close_days' => getSetting('comment_close_days', '0'),
        'comment_notify_admin' => getSetting('comment_notify_admin', '1'),
        'comment_notify_author_approve' => getSetting('comment_notify_author_approve', '0'),
        'comment_notify_email' => getSetting('comment_notify_email', ''),
        'comment_blocked_emails' => getSetting('comment_blocked_emails', ''),
        'comment_spam_words' => getSetting('comment_spam_words', ''),
        'notify_form_submission' => getSetting('notify_form_submission', '1'),
        'notify_pending_content' => getSetting('notify_pending_content', '1'),
        'notify_chat_message' => getSetting('notify_chat_message', '0'),
        'chat_retention_days' => getSetting('chat_retention_days', '0'),
        'content_editor' => getSetting('content_editor', 'html'),
        'ga4_measurement_id' => getSetting('ga4_measurement_id', ''),
        'custom_head_code' => getSetting('custom_head_code', ''),
        'custom_footer_code' => getSetting('custom_footer_code', ''),
        'og_image_default' => getSetting('og_image_default', ''),
        'home_intro' => getSetting('home_intro', ''),
        'cookie_consent_enabled' => getSetting('cookie_consent_enabled', '0'),
        'cookie_consent_text' => getSetting(
            'cookie_consent_text',
            'Tento web používá soubory cookies ke zlepšení vašeho zážitku z prohlížení.'
        ),
        'maintenance_mode' => getSetting('maintenance_mode', '0'),
        'maintenance_text' => getSetting(
            'maintenance_text',
            'Právě probíhá údržba webu. Brzy budeme zpět, děkujeme za trpělivost.'
        ),
        'apply_site_profile' => '0',
    ];
}

function settingsFieldErrorMessages(): array
{
    return [
        'site_name' => 'Název webu je povinný.',
        'contact_email' => 'Zadejte platnou e-mailovou adresu pro kontakt.',
        'board_public_label' => 'Veřejný název sekce vývěsky může mít nejvýše 60 znaků.',
        'github_issues_repository' => 'Výchozí repozitář musí být ve formátu owner/repo.',
        'comment_notify_email' => 'Zadejte platnou e-mailovou adresu pro upozornění na komentáře.',
        'site_favicon' => 'Nahrajte faviconu ve formátu ICO nebo PNG o velikosti nejvýše 256 KB.',
        'site_logo' => 'Nahrajte logo ve formátu JPEG, PNG, GIF nebo WebP o velikosti nejvýše 2 MB.',
    ];
}
