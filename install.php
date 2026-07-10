<?php
require_once __DIR__ . '/db.php';

// Pokud je systém již nainstalován, přesměruj
try {
    db_connect()->query("SELECT 1 FROM cms_settings LIMIT 1");
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
} catch (\PDOException $e) {
    // Tabulky ještě neexistují – pokračujeme v instalaci
}

$errors = [];
$success = false;
$siteProfiles = siteProfileDefinitions();
$selectedSiteProfile = defaultSiteProfileKey();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('install', 5, 300);
    verifyCsrf();

    $siteName = trim($_POST['site_name'] ?? '');
    $siteDesc = trim($_POST['site_desc'] ?? '');
    $siteProfile = trim($_POST['site_profile'] ?? $selectedSiteProfile);
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass = $_POST['admin_pass'] ?? '';
    $adminPass2 = $_POST['admin_pass2'] ?? '';

    if (!isset($siteProfiles[$siteProfile])) {
        $errors[] = 'Vyberte platný profil webu.';
        $siteProfile = defaultSiteProfileKey();
    }
    $selectedSiteProfile = $siteProfile;

    if ($siteName === '')   $errors[] = 'Název webu je povinný.';
    if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Zadejte platnou e-mailovou adresu administrátora.';
    if (strlen($adminPass) < 8) $errors[] = 'Heslo musí mít alespoň 8 znaků.';
    if ($adminPass !== $adminPass2)  $errors[] = 'Hesla se neshodují.';

    if (empty($errors)) {
        $pdo = db_connect();

        // ── Tabulky ──────────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_settings (
            id    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(100) NOT NULL UNIQUE,
            value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_blogs (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            slug        VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            intro_content TEXT,
            logo_file   VARCHAR(255) NOT NULL DEFAULT '',
            logo_alt_text VARCHAR(255) NOT NULL DEFAULT '',
            meta_title  VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            rss_subtitle VARCHAR(255) NOT NULL DEFAULT '',
            comments_default TINYINT(1) NOT NULL DEFAULT 1,
            feed_item_limit INT NOT NULL DEFAULT 20,
            sort_order  INT          NOT NULL DEFAULT 0,
            show_in_nav TINYINT(1)   NOT NULL DEFAULT 1,
            created_by_user_id INT NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("INSERT IGNORE INTO cms_blogs (id, name, slug, sort_order) VALUES (1, 'Blog', 'blog', 0)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_categories (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(255) NOT NULL,
            slug       VARCHAR(150) NOT NULL,
            blog_id    INT          NOT NULL DEFAULT 1,
            parent_id  INT          NULL DEFAULT NULL,
            description TEXT         NULL,
            meta_title VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT    NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_categories_blog_slug (blog_id, slug),
            INDEX idx_categories_blog_id (blog_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_blog_members (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            blog_id      INT         NOT NULL,
            user_id      INT         NOT NULL,
            member_role  ENUM('author','manager') NOT NULL DEFAULT 'author',
            created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_blog_members (blog_id, user_id),
            INDEX idx_cms_blog_members_user (user_id),
            INDEX idx_cms_blog_members_blog (blog_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_blog_slug_redirects (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            blog_id      INT         NOT NULL,
            old_slug     VARCHAR(100) NOT NULL,
            created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_blog_slug_redirects_old_slug (old_slug),
            INDEX idx_cms_blog_slug_redirects_blog (blog_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_articles (
            id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title            VARCHAR(255) NOT NULL,
            slug             VARCHAR(255) NOT NULL,
            blog_id          INT          NOT NULL DEFAULT 1,
            perex            TEXT,
            content          TEXT,
            comments_enabled TINYINT(1)   NOT NULL DEFAULT 1,
            category_id      INT,
            author_id        INT          NULL DEFAULT NULL,
            image_file       VARCHAR(255) NOT NULL DEFAULT '',
            meta_title       VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            preview_token    VARCHAR(32)  NOT NULL DEFAULT '',
            status           ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            publish_at       DATETIME     NULL DEFAULT NULL,
            unpublish_at     DATETIME     NULL DEFAULT NULL,
            admin_note       TEXT,
            view_count       INT          NOT NULL DEFAULT 0,
            is_featured_in_blog TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at       DATETIME     NULL DEFAULT NULL,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FULLTEXT INDEX ft_articles_search (title, perex, content),
            UNIQUE KEY uq_articles_blog_slug (blog_id, slug),
            INDEX idx_articles_blog_id (blog_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_news (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title      VARCHAR(255) NOT NULL,
            slug       VARCHAR(255) NOT NULL,
            content    TEXT         NOT NULL,
            author_id  INT          NULL DEFAULT NULL,
            status     ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            publish_at DATETIME     NULL DEFAULT NULL,
            unpublish_at DATETIME   NULL DEFAULT NULL,
            admin_note TEXT,
            meta_title VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            preview_token VARCHAR(32) NOT NULL DEFAULT '',
            deleted_at DATETIME     NULL DEFAULT NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_news_slug (slug),
            FULLTEXT INDEX ft_news_search (title, content)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_chat_topics (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            slug        VARCHAR(150) NOT NULL,
            description TEXT,
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            sort_order  INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_chat_topics_slug (slug),
            KEY idx_cms_chat_topics_active_order (is_active, sort_order, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_chat (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            topic_id   INT          NULL DEFAULT NULL,
            topic_label VARCHAR(255) NOT NULL DEFAULT '',
            conversation_type ENUM('public','support') NOT NULL DEFAULT 'public',
            reference_code VARCHAR(32) NOT NULL DEFAULT '',
            name       VARCHAR(100) NOT NULL,
            email      VARCHAR(255) NOT NULL DEFAULT '',
            web        VARCHAR(255) NOT NULL DEFAULT '',
            message    TEXT         NOT NULL,
            status     ENUM('new','read','handled') NOT NULL DEFAULT 'new',
            public_visibility ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
            is_pinned  TINYINT(1)   NOT NULL DEFAULT 0,
            pinned_until DATETIME NULL DEFAULT NULL,
            pinned_at DATETIME NULL DEFAULT NULL,
            pinned_by_user_id INT NULL DEFAULT NULL,
            approved_at DATETIME NULL DEFAULT NULL,
            approved_by_user_id INT NULL DEFAULT NULL,
            internal_note TEXT,
            replied_at DATETIME NULL DEFAULT NULL,
            replied_by_user_id INT NULL DEFAULT NULL,
            replied_subject VARCHAR(255) NOT NULL DEFAULT '',
            replied_to_email VARCHAR(255) NOT NULL DEFAULT '',
            replied_body TEXT,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cms_chat_public (conversation_type, public_visibility, topic_id, created_at),
            KEY idx_cms_chat_reference (reference_code),
            KEY idx_cms_chat_pinned (is_pinned, pinned_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_chat_replies (
            id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
            chat_id     INT          NOT NULL,
            name        VARCHAR(100) NOT NULL,
            email       VARCHAR(255) NOT NULL DEFAULT '',
            message     TEXT         NOT NULL,
            status      ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
            approved_at DATETIME     NULL DEFAULT NULL,
            approved_by_user_id INT  NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cms_chat_replies_chat_status (chat_id, status, created_at),
            KEY idx_cms_chat_replies_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_chat_history (
            id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
            chat_id       INT          NOT NULL,
            actor_user_id INT          NULL DEFAULT NULL,
            event_type    VARCHAR(50)  NOT NULL DEFAULT 'workflow',
            message       TEXT         NOT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_contact_topics (
            id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name            VARCHAR(255) NOT NULL,
            slug            VARCHAR(150) NOT NULL,
            description     TEXT,
            recipient_email VARCHAR(255) NOT NULL DEFAULT '',
            is_active       TINYINT(1)   NOT NULL DEFAULT 1,
            sort_order      INT          NOT NULL DEFAULT 0,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_contact_topics_slug (slug),
            KEY idx_cms_contact_topics_active_order (is_active, sort_order, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_contact (
            id                 INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sender_name        VARCHAR(255) NOT NULL DEFAULT '',
            sender_email       VARCHAR(255) NOT NULL,
            topic_id           INT          NULL DEFAULT NULL,
            topic_label        VARCHAR(255) NOT NULL DEFAULT '',
            reference_code     VARCHAR(32)  NOT NULL DEFAULT '',
            subject            VARCHAR(255) NOT NULL,
            message            TEXT         NOT NULL,
            is_read            TINYINT(1)   NOT NULL DEFAULT 0,
            status             ENUM('new','read','handled') NOT NULL DEFAULT 'new',
            replied_at         DATETIME     NULL DEFAULT NULL,
            replied_by_user_id INT          NULL DEFAULT NULL,
            reply_subject      VARCHAR(255) NOT NULL DEFAULT '',
            reply_body         TEXT,
            created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cms_contact_reference_code (reference_code),
            KEY idx_cms_contact_topic_status (topic_id, status),
            KEY idx_cms_contact_replied_by (replied_by_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_gallery_albums (
            id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            parent_id      INT          DEFAULT NULL,
            name           VARCHAR(255) NOT NULL,
            slug           VARCHAR(255) NOT NULL,
            description    TEXT,
            cover_photo_id INT          DEFAULT NULL,
            default_credit VARCHAR(255) NOT NULL DEFAULT '',
            default_license_label VARCHAR(100) NOT NULL DEFAULT '',
            default_license_url VARCHAR(255) NOT NULL DEFAULT '',
            status         ENUM('pending','published') NOT NULL DEFAULT 'published',
            is_published   TINYINT(1)   NOT NULL DEFAULT 1,
            deleted_at     DATETIME     NULL DEFAULT NULL,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_gallery_albums_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_comments (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            article_id   INT          NOT NULL,
            author_name  VARCHAR(100) NOT NULL,
            author_email VARCHAR(255) NOT NULL DEFAULT '',
            content      TEXT         NOT NULL,
            status       ENUM('pending','approved','spam','trash') NOT NULL DEFAULT 'pending',
            is_approved  TINYINT(1)   NOT NULL DEFAULT 0,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_pages (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title        VARCHAR(255) NOT NULL,
            slug         VARCHAR(255) NOT NULL,
            content      TEXT,
            blog_id      INT          NULL DEFAULT NULL,
            slug_scope_id INT GENERATED ALWAYS AS (IFNULL(blog_id, 0)) STORED,
            blog_nav_order INT        NOT NULL DEFAULT 0,
            show_in_nav  TINYINT(1)   NOT NULL DEFAULT 0,
            nav_order    INT          NOT NULL DEFAULT 0,
            is_published TINYINT(1)   NOT NULL DEFAULT 1,
            status       ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            publish_at   DATETIME     NULL DEFAULT NULL,
            unpublish_at DATETIME     NULL DEFAULT NULL,
            admin_note   TEXT,
            preview_token VARCHAR(32) NOT NULL DEFAULT '',
            deleted_at   DATETIME     NULL DEFAULT NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pages_scope_slug (slug_scope_id, slug),
            INDEX idx_pages_blog_nav (blog_id, blog_nav_order),
            FULLTEXT INDEX ft_pages_search (title, content)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_nav_links (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            blog_id      INT          NULL DEFAULT NULL,
            title        VARCHAR(255) NOT NULL,
            url          VARCHAR(1000) NOT NULL,
            alt_text     VARCHAR(255) NOT NULL DEFAULT '',
            target_blank TINYINT(1)   NOT NULL DEFAULT 0,
            is_active    TINYINT(1)   NOT NULL DEFAULT 1,
            nav_order    INT          NOT NULL DEFAULT 0,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_nav_links_scope (blog_id, nav_order),
            INDEX idx_nav_links_active (blog_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_tags (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            slug       VARCHAR(100) NOT NULL,
            blog_id    INT          NOT NULL DEFAULT 1,
            description TEXT         NULL,
            meta_title VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT    NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tags_blog_slug (blog_id, slug),
            INDEX idx_tags_blog_id (blog_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_article_tags (
            article_id INT NOT NULL,
            tag_id     INT NOT NULL,
            PRIMARY KEY (article_id, tag_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_article_related (
            article_id         INT      NOT NULL,
            related_article_id INT      NOT NULL,
            sort_order         INT      NOT NULL DEFAULT 0,
            created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (article_id, related_article_id),
            INDEX idx_article_related_order (article_id, sort_order),
            INDEX idx_article_related_target (related_article_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_blog_series (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            blog_id     INT          NOT NULL,
            title       VARCHAR(255) NOT NULL,
            slug        VARCHAR(255) NOT NULL,
            description TEXT,
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            sort_order  INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_blog_series_blog_slug (blog_id, slug),
            INDEX idx_blog_series_blog_order (blog_id, sort_order),
            INDEX idx_blog_series_active (blog_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_blog_series_items (
            series_id  INT      NOT NULL,
            article_id INT      NOT NULL,
            sort_order INT      NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (series_id, article_id),
            INDEX idx_blog_series_items_article (article_id),
            INDEX idx_blog_series_items_order (series_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_rate_limit (
            id           VARCHAR(64) NOT NULL PRIMARY KEY,
            attempts     INT         NOT NULL DEFAULT 1,
            window_start DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_log (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            action     VARCHAR(100) NOT NULL,
            detail     TEXT,
            user_id    INT          NULL DEFAULT NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_log_action (action),
            INDEX idx_log_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_event_types (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            legacy_key  VARCHAR(50)  NULL DEFAULT NULL,
            title       VARCHAR(255) NOT NULL,
            slug        VARCHAR(255) NOT NULL,
            description TEXT,
            meta_title  VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            sort_order  INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_event_types_slug (slug),
            UNIQUE KEY uq_cms_event_types_legacy (legacy_key),
            INDEX idx_cms_event_types_active_order (is_active, sort_order, title)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        seedDefaultEventTypes($pdo);

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_events (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(255) NOT NULL,
            slug        VARCHAR(255) NOT NULL,
            event_kind  VARCHAR(50)  NOT NULL DEFAULT 'general',
            event_type_id INT        NULL DEFAULT NULL,
            place_id    INT          NULL DEFAULT NULL,
            recurrence_group_id VARCHAR(64) NOT NULL DEFAULT '',
            excerpt     TEXT,
            description TEXT,
            program_note TEXT,
            location    VARCHAR(255) NOT NULL DEFAULT '',
            organizer_name VARCHAR(255) NOT NULL DEFAULT '',
            organizer_email VARCHAR(255) NOT NULL DEFAULT '',
            registration_url VARCHAR(500) NOT NULL DEFAULT '',
            price_note  VARCHAR(255) NOT NULL DEFAULT '',
            accessibility_note TEXT,
            image_file  VARCHAR(255) NOT NULL DEFAULT '',
            event_date  DATETIME     NOT NULL,
            event_end   DATETIME     NULL DEFAULT NULL,
            is_published TINYINT(1)  NOT NULL DEFAULT 1,
            status      ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            unpublish_at DATETIME    NULL DEFAULT NULL,
            publish_at   DATETIME    NULL DEFAULT NULL,
            admin_note   TEXT,
            preview_token VARCHAR(32) NOT NULL DEFAULT '',
            deleted_at   DATETIME    NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_events_slug (slug),
            INDEX idx_cms_events_type (event_type_id),
            INDEX idx_cms_events_place (place_id),
            INDEX idx_cms_events_recurrence (recurrence_group_id, event_date),
            FULLTEXT INDEX ft_events_search (title, excerpt, description, program_note, location, organizer_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_subscribers (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email      VARCHAR(255) NOT NULL UNIQUE,
            token      VARCHAR(64)  NOT NULL UNIQUE,
            confirmed  TINYINT(1)   NOT NULL DEFAULT 0,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_newsletters (
            id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            subject          VARCHAR(255) NOT NULL,
            body             TEXT         NOT NULL,
            recipient_count  INT          NOT NULL DEFAULT 0,
            sent_at          DATETIME     NULL DEFAULT NULL,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_podcast_shows (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(255) NOT NULL,
            slug        VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            author      VARCHAR(255) NOT NULL DEFAULT '',
            subtitle    VARCHAR(255) NOT NULL DEFAULT '',
            cover_image VARCHAR(255) NOT NULL DEFAULT '',
            language    VARCHAR(10)  NOT NULL DEFAULT 'cs',
            category    VARCHAR(100) NOT NULL DEFAULT '',
            owner_name  VARCHAR(255) NOT NULL DEFAULT '',
            owner_email VARCHAR(255) NOT NULL DEFAULT '',
            explicit_mode ENUM('no','clean','yes') NOT NULL DEFAULT 'no',
            show_type   ENUM('episodic','serial') NOT NULL DEFAULT 'episodic',
            feed_complete TINYINT(1) NOT NULL DEFAULT 0,
            feed_episode_limit INT NOT NULL DEFAULT 100,
            feed_guid   VARCHAR(255) NULL DEFAULT NULL,
            website_url VARCHAR(500) NOT NULL DEFAULT '',
            is_published TINYINT(1)   NOT NULL DEFAULT 1,
            status       ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            deleted_at   DATETIME     NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_podcast_shows_feed_guid (feed_guid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_podcasts (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            show_id      INT          NOT NULL DEFAULT 1,
            title        VARCHAR(255) NOT NULL,
            slug         VARCHAR(255) NOT NULL,
            description  TEXT,
            transcript   TEXT,
            audio_file   VARCHAR(255) NOT NULL DEFAULT '',
            image_file   VARCHAR(255) NOT NULL DEFAULT '',
            audio_url    VARCHAR(500) NOT NULL DEFAULT '',
            audio_mime_type VARCHAR(100) NOT NULL DEFAULT '',
            audio_file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            feed_guid    VARCHAR(255) NULL DEFAULT NULL,
            subtitle     VARCHAR(255) NOT NULL DEFAULT '',
            duration     VARCHAR(20)  NOT NULL DEFAULT '',
            episode_num  INT          NULL DEFAULT NULL,
            season_num   INT          NULL DEFAULT NULL,
            episode_type ENUM('full','trailer','bonus') NOT NULL DEFAULT 'full',
            explicit_mode ENUM('inherit','no','clean','yes') NOT NULL DEFAULT 'inherit',
            block_from_feed TINYINT(1) NOT NULL DEFAULT 0,
            publish_at   DATETIME     NULL DEFAULT NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status       ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            deleted_at   DATETIME     NULL DEFAULT NULL,
            UNIQUE KEY uq_cms_podcasts_show_slug (show_id, slug),
            UNIQUE KEY uq_podcasts_feed_guid (feed_guid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_users (
            id                 INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email              VARCHAR(255) NOT NULL UNIQUE,
            password           VARCHAR(255) NOT NULL,
            first_name         VARCHAR(100) NOT NULL DEFAULT '',
            last_name          VARCHAR(100) NOT NULL DEFAULT '',
            nickname           VARCHAR(100) NOT NULL DEFAULT '',
            phone              VARCHAR(30)  NOT NULL DEFAULT '',
            role               ENUM('admin','collaborator','author','editor','moderator','booking_manager','public') NOT NULL DEFAULT 'collaborator',
            is_superadmin      TINYINT(1)   NOT NULL DEFAULT 0,
            is_confirmed       TINYINT(1)   NOT NULL DEFAULT 1,
            confirmation_token VARCHAR(64)  NOT NULL DEFAULT '',
            confirmation_expires DATETIME   NULL DEFAULT NULL,
            reset_token        VARCHAR(64)  NOT NULL DEFAULT '',
            reset_expires      DATETIME     NULL DEFAULT NULL,
            author_public_enabled TINYINT(1) NOT NULL DEFAULT 0,
            author_slug        VARCHAR(255) NULL DEFAULT NULL,
            author_bio         TEXT,
            author_avatar      VARCHAR(255) NOT NULL DEFAULT '',
            author_website     VARCHAR(255) NOT NULL DEFAULT '',
            totp_secret        VARCHAR(64)  NULL DEFAULT NULL,
            passkey_credentials TEXT,
            created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_users_author_slug (author_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_dl_categories (
            id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name             VARCHAR(255) NOT NULL,
            slug             VARCHAR(150) NOT NULL DEFAULT '',
            description      TEXT,
            meta_title       VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_dl_categories_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_podcast_chapters (
            id                 INT            NOT NULL AUTO_INCREMENT PRIMARY KEY,
            episode_id         INT            NOT NULL,
            start_time_seconds DECIMAL(12,3)  NOT NULL DEFAULT 0,
            title              VARCHAR(255)   NOT NULL,
            url                VARCHAR(500)   NOT NULL DEFAULT '',
            image_url          VARCHAR(500)   NOT NULL DEFAULT '',
            created_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_podcast_chapter_start (episode_id, start_time_seconds),
            KEY idx_podcast_chapters_episode (episode_id, start_time_seconds, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_podcast_people (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            show_id     INT          NOT NULL,
            episode_id  INT          NULL DEFAULT NULL,
            name        VARCHAR(255) NOT NULL,
            role_key    VARCHAR(50)  NOT NULL DEFAULT 'guest',
            group_key   VARCHAR(30)  NOT NULL DEFAULT 'cast',
            profile_url VARCHAR(500) NOT NULL DEFAULT '',
            image_url   VARCHAR(500) NOT NULL DEFAULT '',
            sort_order  INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_podcast_people_show (show_id, episode_id, sort_order, id),
            KEY idx_podcast_people_episode (episode_id, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_podcast_platform_links (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            show_id      INT          NOT NULL,
            platform_key VARCHAR(50)  NOT NULL,
            label        VARCHAR(100) NOT NULL DEFAULT '',
            url          VARCHAR(500) NOT NULL,
            sort_order   INT          NOT NULL DEFAULT 0,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_podcast_platform_show_key (show_id, platform_key),
            KEY idx_podcast_platform_show_order (show_id, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_download_series (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(255) NOT NULL,
            slug        VARCHAR(150) NOT NULL,
            description TEXT,
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            sort_order  INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_download_series_slug (slug),
            KEY idx_cms_download_series_active_order (is_active, sort_order, title)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_downloads (
            id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title           VARCHAR(255) NOT NULL,
            slug            VARCHAR(255) NOT NULL,
            download_type   VARCHAR(50)  NOT NULL DEFAULT 'document',
            dl_category_id  INT          NULL DEFAULT NULL,
            excerpt         TEXT,
            description     TEXT,
            image_file      VARCHAR(255) NOT NULL DEFAULT '',
            version_label   VARCHAR(100) NOT NULL DEFAULT '',
            platform_label  VARCHAR(100) NOT NULL DEFAULT '',
            license_label   VARCHAR(100) NOT NULL DEFAULT '',
            project_url     VARCHAR(255) NOT NULL DEFAULT '',
            release_date    DATE         NULL DEFAULT NULL,
            requirements    TEXT,
            checksum_sha256 CHAR(64)     NOT NULL DEFAULT '',
            series_key      VARCHAR(150) NOT NULL DEFAULT '',
            download_series_id INT        NULL DEFAULT NULL,
            is_current_version TINYINT(1) NOT NULL DEFAULT 0,
            external_url    VARCHAR(255) NOT NULL DEFAULT '',
            filename        VARCHAR(255) NOT NULL DEFAULT '',
            original_name   VARCHAR(255) NOT NULL DEFAULT '',
            file_size       INT          NOT NULL DEFAULT 0,
            download_count  INT          NOT NULL DEFAULT 0,
            is_featured     TINYINT(1)   NOT NULL DEFAULT 0,
            sort_order      INT          NOT NULL DEFAULT 0,
            is_published    TINYINT(1)   NOT NULL DEFAULT 1,
            status          ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            author_id       INT          NULL DEFAULT NULL,
            deleted_at      DATETIME     NULL DEFAULT NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_downloads_slug (slug),
            KEY idx_cms_downloads_series_current (download_series_id, is_current_version),
            FULLTEXT INDEX ft_downloads_search (title, excerpt, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_places (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            slug        VARCHAR(255) NOT NULL,
            place_kind  VARCHAR(50)  NOT NULL DEFAULT 'sight',
            excerpt     TEXT,
            description TEXT,
            url         VARCHAR(500) NOT NULL DEFAULT '',
            image_file  VARCHAR(255) NOT NULL DEFAULT '',
            category    VARCHAR(100) NOT NULL DEFAULT '',
            address     VARCHAR(255) NOT NULL DEFAULT '',
            locality    VARCHAR(255) NOT NULL DEFAULT '',
            latitude    DECIMAL(10,7) NULL DEFAULT NULL,
            longitude   DECIMAL(10,7) NULL DEFAULT NULL,
            contact_phone VARCHAR(100) NOT NULL DEFAULT '',
            contact_email VARCHAR(255) NOT NULL DEFAULT '',
            opening_hours TEXT,
            meta_title  VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            is_published TINYINT(1)  NOT NULL DEFAULT 1,
            status      ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            sort_order  INT          NOT NULL DEFAULT 0,
            deleted_at  DATETIME     NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_places_slug (slug),
            FULLTEXT INDEX ft_places_search (name, excerpt, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_gallery_photos (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            album_id   INT          NOT NULL,
            filename   VARCHAR(255) NOT NULL,
            title      VARCHAR(255) NOT NULL DEFAULT '',
            slug       VARCHAR(255) NOT NULL,
            alt_text   VARCHAR(255) NOT NULL DEFAULT '',
            caption    TEXT,
            description TEXT,
            credit     VARCHAR(255) NOT NULL DEFAULT '',
            license_label VARCHAR(100) NOT NULL DEFAULT '',
            license_url VARCHAR(255) NOT NULL DEFAULT '',
            taken_at   DATE         NULL DEFAULT NULL,
            location_label VARCHAR(255) NOT NULL DEFAULT '',
            sort_order   INT          NOT NULL DEFAULT 0,
            status       ENUM('pending','published') NOT NULL DEFAULT 'published',
            is_published TINYINT(1)   NOT NULL DEFAULT 1,
            deleted_at   DATETIME     NULL DEFAULT NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_gallery_photos_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_food_cards (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            type         ENUM('food','beverage') NOT NULL DEFAULT 'food',
            title        VARCHAR(255) NOT NULL,
            slug         VARCHAR(255) NOT NULL,
            description  TEXT,
            content      MEDIUMTEXT,
            valid_from   DATE         NULL DEFAULT NULL,
            valid_to     DATE         NULL DEFAULT NULL,
            orders_enabled TINYINT(1) NOT NULL DEFAULT 0,
            order_email  VARCHAR(255) NOT NULL DEFAULT '',
            order_instructions TEXT,
            is_current   TINYINT(1)   NOT NULL DEFAULT 0,
            is_published TINYINT(1)   NOT NULL DEFAULT 1,
            status       ENUM('pending','published') NOT NULL DEFAULT 'published',
            author_id    INT          NULL DEFAULT NULL,
            deleted_at   DATETIME     NULL DEFAULT NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_food_cards_slug (slug),
            FULLTEXT INDEX ft_food_search (title, description, content)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_food_sections (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            card_id     INT          NOT NULL,
            title       VARCHAR(255) NOT NULL,
            description TEXT,
            serving_date DATE         NULL DEFAULT NULL,
            serving_time_from TIME    NULL DEFAULT NULL,
            serving_time_to TIME      NULL DEFAULT NULL,
            serving_note VARCHAR(255) NOT NULL DEFAULT '',
            sort_order  INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_food_sections_card_order (card_id, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_food_items (
            id             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
            card_id        INT           NOT NULL,
            section_id     INT           NOT NULL,
            title          VARCHAR(255)  NOT NULL,
            description    TEXT,
            price_amount   DECIMAL(10,2) NULL DEFAULT NULL,
            price_currency VARCHAR(3)    NOT NULL DEFAULT 'CZK',
            price_note     VARCHAR(255)  NOT NULL DEFAULT '',
            portion_label   VARCHAR(80)   NOT NULL DEFAULT '',
            energy_kj       INT           NULL DEFAULT NULL,
            energy_kcal     INT           NULL DEFAULT NULL,
            protein_g       DECIMAL(8,2)  NULL DEFAULT NULL,
            carbs_g         DECIMAL(8,2)  NULL DEFAULT NULL,
            fat_g           DECIMAL(8,2)  NULL DEFAULT NULL,
            salt_g          DECIMAL(8,2)  NULL DEFAULT NULL,
            media_id       INT           NULL DEFAULT NULL,
            image_alt_text VARCHAR(255)  NOT NULL DEFAULT '',
            allergens      VARCHAR(100)  NOT NULL DEFAULT '',
            dietary_flags  VARCHAR(255)  NOT NULL DEFAULT '',
            is_available   TINYINT(1)    NOT NULL DEFAULT 1,
            sort_order     INT           NOT NULL DEFAULT 0,
            created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_food_items_card_order (card_id, sort_order, id),
            INDEX idx_food_items_section_order (section_id, sort_order, id),
            INDEX idx_food_items_media (media_id),
            FULLTEXT INDEX ft_food_items_search (title, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_food_orders (
            id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            card_id         INT          NOT NULL,
            card_title      VARCHAR(255) NOT NULL,
            reference_code  VARCHAR(32)  NOT NULL,
            customer_name   VARCHAR(255) NOT NULL,
            customer_email  VARCHAR(255) NOT NULL,
            customer_phone  VARCHAR(80)  NOT NULL DEFAULT '',
            customer_note   TEXT,
            status          ENUM('new','confirmed','rejected','completed','cancelled') NOT NULL DEFAULT 'new',
            total_amount    DECIMAL(10,2) NULL DEFAULT NULL,
            price_currency  VARCHAR(3)   NOT NULL DEFAULT 'CZK',
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_food_orders_reference (reference_code),
            INDEX idx_food_orders_card_status (card_id, status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_food_order_items (
            id                INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id          INT          NOT NULL,
            item_id           INT          NULL DEFAULT NULL,
            item_title        VARCHAR(255) NOT NULL,
            quantity          INT          NOT NULL DEFAULT 1,
            unit_price_amount DECIMAL(10,2) NULL DEFAULT NULL,
            price_currency    VARCHAR(3)   NOT NULL DEFAULT 'CZK',
            price_note        VARCHAR(255) NOT NULL DEFAULT '',
            sort_order        INT          NOT NULL DEFAULT 0,
            INDEX idx_food_order_items_order (order_id, sort_order, id),
            INDEX idx_food_order_items_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_polls (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            question    VARCHAR(500) NOT NULL,
            slug        VARCHAR(255) NOT NULL,
            description TEXT,
            meta_title  VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            vote_mode   ENUM('single','multiple') NOT NULL DEFAULT 'single',
            max_choices INT          NULL DEFAULT NULL,
            results_visibility ENUM('after_vote','always','closed','hidden') NOT NULL DEFAULT 'after_vote',
            status      ENUM('active','closed') NOT NULL DEFAULT 'active',
            start_date  DATETIME     NULL DEFAULT NULL,
            end_date    DATETIME     NULL DEFAULT NULL,
            deleted_at  DATETIME     NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_polls_slug (slug),
            FULLTEXT INDEX ft_polls_search (question, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_poll_options (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            poll_id     INT          NOT NULL,
            option_text VARCHAR(500) NOT NULL,
            sort_order  INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_poll_vote_sessions (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            poll_id     INT          NOT NULL,
            voter_hash  VARCHAR(64)  NOT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_poll_vote_session (poll_id, voter_hash),
            INDEX idx_poll_vote_sessions_poll (poll_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_poll_votes (
            id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            poll_id         INT          NOT NULL,
            option_id       INT          NOT NULL,
            vote_session_id INT          NULL DEFAULT NULL,
            ip_hash         VARCHAR(64)  NOT NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_poll_vote_option_hash (poll_id, option_id, ip_hash),
            INDEX idx_poll_votes_session (vote_session_id),
            INDEX idx_poll_votes_poll_option (poll_id, option_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_faq_categories (
            id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            parent_id        INT          NULL DEFAULT NULL,
            name             VARCHAR(255) NOT NULL,
            slug             VARCHAR(150) NOT NULL DEFAULT '',
            description      TEXT,
            meta_title       VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            sort_order       INT          NOT NULL DEFAULT 0,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_faq_categories_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_faqs (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            category_id  INT          NULL DEFAULT NULL,
            question     VARCHAR(500) NOT NULL,
            slug         VARCHAR(255) NOT NULL,
            excerpt      TEXT,
            answer       TEXT         NOT NULL,
            meta_title   VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            sort_order   INT          NOT NULL DEFAULT 0,
            is_published TINYINT(1)   NOT NULL DEFAULT 1,
            status       ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            deleted_at   DATETIME     NULL DEFAULT NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_faqs_slug (slug),
            FULLTEXT INDEX ft_faqs_search (question, excerpt, answer)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_faq_feedback (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            faq_id       INT          NOT NULL,
            vote         ENUM('helpful','not_helpful') NOT NULL,
            note         TEXT,
            visitor_hash VARCHAR(64)  NOT NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_faq_feedback_visitor (faq_id, visitor_hash),
            INDEX idx_cms_faq_feedback_faq_vote (faq_id, vote, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_board_categories (
            id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name             VARCHAR(255) NOT NULL,
            slug             VARCHAR(150) NOT NULL DEFAULT '',
            description      TEXT,
            meta_title       VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
            sort_order       INT          NOT NULL DEFAULT 0,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_board_categories_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_board (
            id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title          VARCHAR(255) NOT NULL,
            slug           VARCHAR(255) NOT NULL,
            board_type     VARCHAR(50)  NOT NULL DEFAULT 'document',
            excerpt        TEXT,
            description    TEXT,
            category_id    INT          NULL DEFAULT NULL,
            posted_date    DATE         NOT NULL,
            removal_date   DATE         NULL DEFAULT NULL,
            image_file     VARCHAR(255) NOT NULL DEFAULT '',
            contact_name   VARCHAR(255) NOT NULL DEFAULT '',
            contact_phone  VARCHAR(100) NOT NULL DEFAULT '',
            contact_email  VARCHAR(255) NOT NULL DEFAULT '',
            filename       VARCHAR(255) NOT NULL DEFAULT '',
            original_name  VARCHAR(255) NOT NULL DEFAULT '',
            file_size      INT          NOT NULL DEFAULT 0,
            sort_order     INT          NOT NULL DEFAULT 0,
            is_pinned      TINYINT(1)   NOT NULL DEFAULT 0,
            is_published   TINYINT(1)   NOT NULL DEFAULT 1,
            status         ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
            publish_at     DATETIME     NULL DEFAULT NULL,
            unpublish_at   DATETIME     NULL DEFAULT NULL,
            author_id      INT          NULL DEFAULT NULL,
            preview_token  VARCHAR(32)  NOT NULL DEFAULT '',
            deleted_at     DATETIME     NULL DEFAULT NULL,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_board_slug (slug),
            FULLTEXT INDEX ft_board_search (title, excerpt, description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_board_publication_events (
            id                  INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            board_id            INT          NOT NULL,
            event_type          VARCHAR(50)  NOT NULL,
            event_date          DATETIME     NOT NULL,
            actor_user_id       INT          NULL DEFAULT NULL,
            public_path         VARCHAR(500) NOT NULL DEFAULT '',
            attachment_name     VARCHAR(255) NOT NULL DEFAULT '',
            attachment_size     INT          NOT NULL DEFAULT 0,
            attachment_checksum CHAR(64)     NOT NULL DEFAULT '',
            created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_board_publication_events_board (board_id, created_at),
            INDEX idx_board_publication_events_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_board_subscribers (
            id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email          VARCHAR(255) NOT NULL,
            token          VARCHAR(64)  NOT NULL,
            confirmed      TINYINT(1)   NOT NULL DEFAULT 0,
            all_categories TINYINT(1)   NOT NULL DEFAULT 1,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_at   DATETIME     NULL DEFAULT NULL,
            UNIQUE KEY uq_board_subscribers_email (email),
            UNIQUE KEY uq_board_subscribers_token (token),
            INDEX idx_board_subscribers_confirmed (confirmed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_board_subscriber_categories (
            subscriber_id INT NOT NULL,
            category_id   INT NOT NULL,
            PRIMARY KEY (subscriber_id, category_id),
            INDEX idx_board_subscriber_categories_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── Rezervační systém ───────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_res_categories (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(255) NOT NULL,
            sort_order INT          NOT NULL DEFAULT 0,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_res_resources (
            id                 INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            category_id        INT          NULL DEFAULT NULL,
            name               VARCHAR(255) NOT NULL,
            slug               VARCHAR(255) NOT NULL UNIQUE,
            description        TEXT,
            capacity           INT          NOT NULL DEFAULT 1,
            location           VARCHAR(255) NOT NULL DEFAULT '',
            slot_mode          ENUM('slots','range','duration') NOT NULL DEFAULT 'slots',
            slot_duration_min  INT          NOT NULL DEFAULT 60,
            min_advance_hours  INT          NOT NULL DEFAULT 1,
            max_advance_days   INT          NOT NULL DEFAULT 30,
            cancellation_hours INT          NOT NULL DEFAULT 24,
            requires_approval  TINYINT(1)   NOT NULL DEFAULT 0,
            allow_guests       TINYINT(1)   NOT NULL DEFAULT 0,
            reminders_enabled  TINYINT(1)   NOT NULL DEFAULT 0,
            reminder_hours_before INT       NOT NULL DEFAULT 24,
            reminder_message   TEXT,
            calendar_invite_enabled TINYINT(1) NOT NULL DEFAULT 1,
            max_concurrent     INT          NOT NULL DEFAULT 1,
            is_active          TINYINT(1)   NOT NULL DEFAULT 1,
            created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_res_hours (
            id          INT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
            resource_id INT        NOT NULL,
            day_of_week TINYINT    NOT NULL,
            open_time   TIME       NOT NULL DEFAULT '09:00:00',
            close_time  TIME       NOT NULL DEFAULT '17:00:00',
            is_closed   TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_res_day (resource_id, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_res_slots (
            id           INT     NOT NULL AUTO_INCREMENT PRIMARY KEY,
            resource_id  INT     NOT NULL,
            day_of_week  TINYINT NOT NULL,
            start_time   TIME    NOT NULL,
            end_time     TIME    NOT NULL,
            max_bookings INT     NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_res_blocked (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            resource_id  INT          NOT NULL,
            blocked_date DATE         NOT NULL,
            reason       VARCHAR(255) NOT NULL DEFAULT '',
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_res_blocked (resource_id, blocked_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_res_locations (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(255) NOT NULL,
            address    VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_res_resource_locations (
            resource_id INT NOT NULL,
            location_id INT NOT NULL,
            PRIMARY KEY (resource_id, location_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_res_bookings (
            id                 INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            resource_id        INT          NOT NULL,
            user_id            INT          NULL DEFAULT NULL,
            guest_name         VARCHAR(255) NOT NULL DEFAULT '',
            guest_email        VARCHAR(255) NOT NULL DEFAULT '',
            guest_phone        VARCHAR(30)  NOT NULL DEFAULT '',
            booking_date       DATE         NOT NULL,
            start_time         TIME         NOT NULL,
            end_time           TIME         NOT NULL,
            party_size         INT          NOT NULL DEFAULT 1,
            notes              TEXT,
            status             ENUM('pending','confirmed','cancelled','rejected','completed','no_show') NOT NULL DEFAULT 'pending',
            admin_note         TEXT,
            confirmation_token VARCHAR(64)  NOT NULL DEFAULT '',
            calendar_token     VARCHAR(64)  NULL DEFAULT NULL,
            reminder_sent_at   DATETIME     NULL DEFAULT NULL,
            reminder_last_error VARCHAR(500) NOT NULL DEFAULT '',
            cancelled_at       DATETIME     NULL DEFAULT NULL,
            created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_res_date (resource_id, booking_date, status),
            INDEX idx_user (user_id, status),
            UNIQUE KEY uq_res_calendar_token (calendar_token),
            INDEX idx_res_reminders (status, reminder_sent_at, booking_date, start_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_res_booking_events (
            id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            booking_id    INT          NOT NULL,
            event_type    VARCHAR(50)  NOT NULL,
            description   VARCHAR(500) NOT NULL DEFAULT '',
            actor_user_id INT          NULL DEFAULT NULL,
            metadata_json TEXT,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_res_booking_events_booking (booking_id, created_at),
            INDEX idx_res_booking_events_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── Statistiky ────────────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_page_views (
            id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
            page_url    VARCHAR(500) NOT NULL,
            page_type   VARCHAR(50)  NOT NULL DEFAULT '',
            page_ref_id INT          NULL DEFAULT NULL,
            ip_hash     VARCHAR(64)  NOT NULL,
            user_agent  VARCHAR(500) NOT NULL DEFAULT '',
            referrer    VARCHAR(500) NOT NULL DEFAULT '',
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_ip_created (ip_hash, created_at),
            INDEX idx_page_type_ref (page_type, page_ref_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_stats_daily (
            id              INT  NOT NULL AUTO_INCREMENT PRIMARY KEY,
            stat_date       DATE NOT NULL UNIQUE,
            total_views     INT  NOT NULL DEFAULT 0,
            unique_visitors INT  NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_stats_content_daily (
            id              BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
            stat_date       DATE         NOT NULL,
            page_type       VARCHAR(50)  NOT NULL DEFAULT '',
            page_ref_id     INT          NOT NULL DEFAULT 0,
            normalized_path VARCHAR(500) NOT NULL,
            path_hash       CHAR(64)     NOT NULL,
            module_key      VARCHAR(50)  NOT NULL DEFAULT '',
            title_snapshot  VARCHAR(255) NOT NULL DEFAULT '',
            total_views     INT          NOT NULL DEFAULT 0,
            unique_visitors INT          NOT NULL DEFAULT 0,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_stats_content_daily (stat_date, page_type, page_ref_id, path_hash),
            INDEX idx_stats_content_module_date (module_key, stat_date),
            INDEX idx_stats_content_path_hash (path_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── Formuláře ──

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_forms (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(255) NOT NULL,
            slug        VARCHAR(255) NOT NULL,
            description TEXT,
            success_message TEXT,
            submit_label VARCHAR(100) NOT NULL DEFAULT 'Odeslat formulář',
            notification_email VARCHAR(255) NOT NULL DEFAULT '',
            notification_subject VARCHAR(255) NOT NULL DEFAULT '',
            redirect_url VARCHAR(500) NOT NULL DEFAULT '',
            success_behavior VARCHAR(20) NOT NULL DEFAULT '',
            success_primary_label VARCHAR(120) NOT NULL DEFAULT '',
            success_primary_url VARCHAR(500) NOT NULL DEFAULT '',
            success_secondary_label VARCHAR(120) NOT NULL DEFAULT '',
            success_secondary_url VARCHAR(500) NOT NULL DEFAULT '',
            webhook_enabled TINYINT(1) NOT NULL DEFAULT 0,
            webhook_url VARCHAR(500) NOT NULL DEFAULT '',
            webhook_secret VARCHAR(255) NOT NULL DEFAULT '',
            webhook_events VARCHAR(255) NOT NULL DEFAULT '',
            use_honeypot TINYINT(1) NOT NULL DEFAULT 1,
            submitter_confirmation_enabled TINYINT(1) NOT NULL DEFAULT 0,
            submitter_email_field VARCHAR(100) NOT NULL DEFAULT '',
            submitter_confirmation_subject VARCHAR(255) NOT NULL DEFAULT '',
            submitter_confirmation_message TEXT,
            show_in_nav TINYINT(1) NOT NULL DEFAULT 0,
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_forms_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_form_fields (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            form_id     INT          NOT NULL,
            field_type  VARCHAR(30)  NOT NULL DEFAULT 'text',
            label       VARCHAR(255) NOT NULL,
            name        VARCHAR(100) NOT NULL,
            placeholder VARCHAR(255) NOT NULL DEFAULT '',
            default_value VARCHAR(500) NOT NULL DEFAULT '',
            help_text   TEXT,
            options     TEXT,
            accept_types VARCHAR(255) NOT NULL DEFAULT '',
            max_file_size_mb INT     NOT NULL DEFAULT 10,
            allow_multiple TINYINT(1) NOT NULL DEFAULT 0,
            layout_width VARCHAR(20) NOT NULL DEFAULT 'full',
            start_new_row TINYINT(1) NOT NULL DEFAULT 0,
            show_if_field VARCHAR(100) NOT NULL DEFAULT '',
            show_if_operator VARCHAR(20) NOT NULL DEFAULT '',
            show_if_value VARCHAR(255) NOT NULL DEFAULT '',
            is_required TINYINT(1)   NOT NULL DEFAULT 0,
            sort_order  INT          NOT NULL DEFAULT 0,
            INDEX idx_form (form_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_form_submissions (
            id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
            form_id     INT          NOT NULL,
            reference_code VARCHAR(50) NOT NULL DEFAULT '',
            status      VARCHAR(20)  NOT NULL DEFAULT 'new',
            priority    VARCHAR(20)  NOT NULL DEFAULT 'medium',
            labels      VARCHAR(500) NOT NULL DEFAULT '',
            assigned_user_id INT     NULL DEFAULT NULL,
            internal_note TEXT,
            github_issue_repository VARCHAR(255) NOT NULL DEFAULT '',
            github_issue_number INT NULL DEFAULT NULL,
            github_issue_url VARCHAR(500) NOT NULL DEFAULT '',
            data        JSON         NOT NULL,
            ip_hash     VARCHAR(64)  NOT NULL DEFAULT '',
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_form_date (form_id, created_at),
            INDEX idx_form_status (form_id, status, created_at),
            INDEX idx_form_assignee (assigned_user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_form_submission_history (
            id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
            submission_id BIGINT       NOT NULL,
            actor_user_id INT          NULL DEFAULT NULL,
            event_type    VARCHAR(50)  NOT NULL DEFAULT 'note',
            message       TEXT         NOT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_submission_created (submission_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_revisions (
            id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(50)  NOT NULL,
            entity_id   INT          NOT NULL,
            field_name  VARCHAR(100) NOT NULL,
            old_value   LONGTEXT,
            new_value   LONGTEXT,
            user_id     INT          NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_entity (entity_type, entity_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_redirects (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            old_path    VARCHAR(500) NOT NULL,
            new_path    VARCHAR(500) NOT NULL,
            status_code SMALLINT     NOT NULL DEFAULT 301,
            hit_count   INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_redirects_old_path (old_path(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_content_locks (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(50)  NOT NULL,
            entity_id   INT          NOT NULL,
            user_id     INT          NOT NULL,
            locked_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at  DATETIME     NOT NULL,
            UNIQUE KEY uq_lock (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_media_collections (
            id                    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name                  VARCHAR(160) NOT NULL,
            slug                  VARCHAR(180) NOT NULL,
            description           TEXT,
            default_visibility    ENUM('public','private') NOT NULL DEFAULT 'public',
            default_credit        VARCHAR(255) NOT NULL DEFAULT '',
            default_license_label VARCHAR(120) NOT NULL DEFAULT '',
            default_license_url   VARCHAR(255) NOT NULL DEFAULT '',
            sort_order            INT          NOT NULL DEFAULT 0,
            created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_media_collections_slug (slug),
            INDEX idx_media_collections_order (sort_order, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_media (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            filename    VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL DEFAULT '',
            mime_type   VARCHAR(100) NOT NULL DEFAULT '',
            file_size   INT          NOT NULL DEFAULT 0,
            folder      VARCHAR(100) NOT NULL DEFAULT 'media',
            collection_id INT        NULL DEFAULT NULL,
            alt_text    VARCHAR(500) NOT NULL DEFAULT '',
            caption     TEXT,
            description TEXT,
            credit      VARCHAR(255) NOT NULL DEFAULT '',
            license_label VARCHAR(120) NOT NULL DEFAULT '',
            license_url VARCHAR(255) NOT NULL DEFAULT '',
            visibility  ENUM('public','private') NOT NULL DEFAULT 'public',
            uploaded_by INT          NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_media_folder (folder),
            INDEX idx_media_mime (mime_type),
            INDEX idx_media_visibility (visibility),
            INDEX idx_media_collection (collection_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_widgets (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            zone        VARCHAR(50)  NOT NULL DEFAULT 'homepage',
            widget_type VARCHAR(50)  NOT NULL,
            title       VARCHAR(255) NOT NULL DEFAULT '',
            settings    JSON,
            sort_order  INT          NOT NULL DEFAULT 0,
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_widgets_zone (zone, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_admin_shortcuts (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id     INT          NOT NULL,
            item_type   VARCHAR(50)  NOT NULL,
            item_key    VARCHAR(120) NOT NULL,
            label       VARCHAR(255) NOT NULL DEFAULT '',
            url         VARCHAR(500) NOT NULL DEFAULT '',
            sort_order  INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_admin_shortcut_user_item (user_id, item_type, item_key),
            INDEX idx_admin_shortcut_user_order (user_id, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── Výchozí nastavení ────────────────────────────────────────────────
        $defaults = array_merge([
            'site_name'       => $siteName,
            'site_description'=> $siteDesc,
            'site_profile'    => $siteProfile,
            'admin_email'     => $adminEmail,
            'contact_email'   => $adminEmail,
            'admin_password'  => password_hash($adminPass, PASSWORD_BCRYPT),
        ], moduleDefaultSettings(), [
            'home_blog_count' => '5',
            'home_news_count' => '5',
            'home_board_count' => '5',
            'board_public_label' => defaultBoardPublicLabelForProfile($siteProfile),
            'news_per_page'   => '10',
            'blog_per_page'   => '10',
            'events_per_page' => '10',
            'blog_authors_index_enabled' => '0',
            'public_registration_enabled' => '1',
            'comments_enabled' => '1',
            'comment_moderation_mode' => 'always',
            'comment_close_days' => '0',
            'comment_notify_admin' => '1',
            'comment_notify_author_approve' => '0',
            'comment_notify_email' => '',
            'github_issues_enabled' => '0',
            'github_issues_repository' => '',
            'notify_form_submission' => '1',
            'notify_pending_content' => '1',
            'notify_chat_message' => '0',
            'chat_retention_days' => '0',
            'upload_max_size_mb' => '10',
            'comment_blocked_emails' => '',
            'comment_spam_words' => '',
            'home_author_user_id' => '',
            'content_editor'  => 'html',
            'visitor_tracking_enabled' => '0',
            'visitor_counter_enabled'  => '0',
            'stats_retention_days'     => '90',
            'social_facebook'          => '',
            'social_youtube'           => '',
            'social_instagram'         => '',
            'social_twitter'           => '',
            'cookie_consent_enabled'   => '0',
            'cookie_consent_text'      => 'Tento web používá soubory cookies ke zlepšení vašeho zážitku z prohlížení.',
            'maintenance_mode'         => '0',
            'maintenance_text'         => 'Právě probíhá údržba webu. Brzy budeme zpět, děkujeme za trpělivost.',
            'og_image_default'         => '',
            'site_favicon'             => '',
            'site_logo'                => '',
            'active_theme'             => defaultThemeName(),
            'home_intro'               => '',
            'nav_module_order'         => '',
        ]);
        $stmt = $pdo->prepare("INSERT INTO cms_settings (`key`, value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE value = VALUES(value)");
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // Hlavní administrátor
        $adminPasswordHash = password_hash($adminPass, PASSWORD_BCRYPT);
        $pdo->prepare(
            "INSERT INTO cms_users (email, password, role, is_superadmin, author_slug)
             VALUES (?, ?, 'admin', 1, ?)
             ON DUPLICATE KEY UPDATE password = VALUES(password), role = 'admin', is_superadmin = 1"
        )->execute([
            $adminEmail,
            $adminPasswordHash,
            uniqueAuthorSlug($pdo, strstr($adminEmail, '@', true) ?: 'autor'),
        ]);
        applySiteProfilePreset($siteProfile);

        // ── Adresáře pro nahrávání souborů ──────────────────────────────────
        $uploadDirs = [
            'uploads/site',
            'uploads/authors',
            'uploads/articles',
            'uploads/articles/thumbs',
            'uploads/gallery',
            'uploads/gallery/thumbs',
            'uploads/downloads',
            'uploads/downloads/images',
            'uploads/media',
            'uploads/media/thumbs',
            'uploads/places',
            'uploads/board',
            'uploads/board/images',
            'uploads/podcasts',
            'uploads/podcasts/covers',
            'uploads/podcasts/images',
        ];
        foreach ($uploadDirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalace Kora CMS</title>
<?= standaloneStylesheetTag() ?>
</head>
<body class="standalone-page standalone-page--install">
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<main id="obsah">
  <h1>Instalace Kora CMS</h1>

  <?php if ($success): ?>
    <p class="standalone-success" role="status"><strong>Instalace proběhla úspěšně.</strong><br>
    Smažte nebo přejmenujte soubor <code>install.php</code>.</p>
    <p><a href="<?= BASE_URL ?>/admin/login.php">Přejít do administrace →</a></p>
  <?php else: ?>

    <?php if (!empty($errors)): ?>
      <ul id="install-errors" class="standalone-error" role="alert">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" novalidate<?php if (!empty($errors)): ?> aria-describedby="install-errors"<?php endif; ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <label for="site_name">Název webu <span aria-hidden="true">*</span></label>
      <input type="text" id="site_name" name="site_name" required
             value="<?= h($_POST['site_name'] ?? '') ?>">

      <label for="site_desc">Popis webu</label>
      <input type="text" id="site_desc" name="site_desc"
             value="<?= h($_POST['site_desc'] ?? '') ?>">
      <fieldset>
        <legend>Profil webu</legend>
        <p>Vyberte výchozí směr webu. Instalace podle něj přednastaví moduly, domovskou stránku i doporučenou šablonu.</p>
        <?php foreach ($siteProfiles as $profileKey => $profile): ?>
          <div class="install-profile-option">
            <input type="radio" id="site_profile_<?= h($profileKey) ?>" name="site_profile" value="<?= h($profileKey) ?>"
                   <?= $selectedSiteProfile === $profileKey ? 'checked' : '' ?>>
            <label for="site_profile_<?= h($profileKey) ?>">
              <?= h($profile['label']) ?><?= $profileKey === defaultSiteProfileKey() ? ' (Doporučeno)' : '' ?>
            </label>
            <p><?= h($profile['description']) ?></p>
          </div>
        <?php endforeach; ?>
      </fieldset>

      <label for="admin_email">E-mail administrátora <span aria-hidden="true">*</span></label>
      <input type="email" id="admin_email" name="admin_email" required
             value="<?= h($_POST['admin_email'] ?? '') ?>">

      <label for="admin_pass">Heslo administrátora (min. 8 znaků) <span aria-hidden="true">*</span></label>
      <input type="password" id="admin_pass" name="admin_pass" required
             minlength="8" autocomplete="new-password">

      <label for="admin_pass2">Heslo znovu <span aria-hidden="true">*</span></label>
      <input type="password" id="admin_pass2" name="admin_pass2" required
             minlength="8" autocomplete="new-password">

      <button type="submit">Nainstalovat</button>
    </form>

  <?php endif; ?>
</main>
</body>
</html>
