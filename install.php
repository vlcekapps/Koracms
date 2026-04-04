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
            blog_id    INT          NOT NULL DEFAULT 1,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_chat (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            email      VARCHAR(255) NOT NULL DEFAULT '',
            web        VARCHAR(255) NOT NULL DEFAULT '',
            message    TEXT         NOT NULL,
            status     ENUM('new','read','handled') NOT NULL DEFAULT 'new',
            public_visibility ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
            approved_at DATETIME NULL DEFAULT NULL,
            approved_by_user_id INT NULL DEFAULT NULL,
            internal_note TEXT,
            replied_at DATETIME NULL DEFAULT NULL,
            replied_by_user_id INT NULL DEFAULT NULL,
            replied_subject VARCHAR(255) NOT NULL DEFAULT '',
            replied_to_email VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_chat_history (
            id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
            chat_id       INT          NOT NULL,
            actor_user_id INT          NULL DEFAULT NULL,
            event_type    VARCHAR(50)  NOT NULL DEFAULT 'workflow',
            message       TEXT         NOT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_contact (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sender_email VARCHAR(255) NOT NULL,
            subject      VARCHAR(255) NOT NULL,
            message      TEXT         NOT NULL,
            is_read      TINYINT(1)   NOT NULL DEFAULT 0,
            status       ENUM('new','read','handled') NOT NULL DEFAULT 'new',
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_gallery_albums (
            id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            parent_id      INT          DEFAULT NULL,
            name           VARCHAR(255) NOT NULL,
            slug           VARCHAR(255) NOT NULL,
            description    TEXT,
            cover_photo_id INT          DEFAULT NULL,
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
            slug         VARCHAR(255) NOT NULL UNIQUE,
            content      TEXT,
            blog_id      INT          NULL DEFAULT NULL,
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
            INDEX idx_pages_blog_nav (blog_id, blog_nav_order),
            FULLTEXT INDEX ft_pages_search (title, content)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_tags (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            slug       VARCHAR(100) NOT NULL,
            blog_id    INT          NOT NULL DEFAULT 1,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tags_blog_slug (blog_id, slug),
            INDEX idx_tags_blog_id (blog_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_article_tags (
            article_id INT NOT NULL,
            tag_id     INT NOT NULL,
            PRIMARY KEY (article_id, tag_id)
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_events (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(255) NOT NULL,
            slug        VARCHAR(255) NOT NULL,
            event_kind  VARCHAR(50)  NOT NULL DEFAULT 'general',
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
            status      ENUM('pending','published') NOT NULL DEFAULT 'published',
            unpublish_at DATETIME    NULL DEFAULT NULL,
            admin_note   TEXT,
            deleted_at   DATETIME    NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_events_slug (slug),
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
            website_url VARCHAR(500) NOT NULL DEFAULT '',
            is_published TINYINT(1)   NOT NULL DEFAULT 1,
            status       ENUM('pending','published') NOT NULL DEFAULT 'published',
            deleted_at   DATETIME     NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_podcasts (
            id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            show_id      INT          NOT NULL DEFAULT 1,
            title        VARCHAR(255) NOT NULL,
            slug         VARCHAR(255) NOT NULL,
            description  TEXT,
            audio_file   VARCHAR(255) NOT NULL DEFAULT '',
            image_file   VARCHAR(255) NOT NULL DEFAULT '',
            audio_url    VARCHAR(500) NOT NULL DEFAULT '',
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
            status       ENUM('pending','published') NOT NULL DEFAULT 'published',
            deleted_at   DATETIME     NULL DEFAULT NULL,
            UNIQUE KEY uq_cms_podcasts_show_slug (show_id, slug)
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
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(255) NOT NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
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
            external_url    VARCHAR(255) NOT NULL DEFAULT '',
            filename        VARCHAR(255) NOT NULL DEFAULT '',
            original_name   VARCHAR(255) NOT NULL DEFAULT '',
            file_size       INT          NOT NULL DEFAULT 0,
            download_count  INT          NOT NULL DEFAULT 0,
            is_featured     TINYINT(1)   NOT NULL DEFAULT 0,
            sort_order      INT          NOT NULL DEFAULT 0,
            is_published    TINYINT(1)   NOT NULL DEFAULT 1,
            status          ENUM('pending','published') NOT NULL DEFAULT 'published',
            author_id       INT          NULL DEFAULT NULL,
            deleted_at      DATETIME     NULL DEFAULT NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_downloads_slug (slug),
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
            status      ENUM('pending','published') NOT NULL DEFAULT 'published',
            sort_order  INT          NOT NULL DEFAULT 0,
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_polls (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            question    VARCHAR(500) NOT NULL,
            slug        VARCHAR(255) NOT NULL,
            description TEXT,
            meta_title  VARCHAR(160) NOT NULL DEFAULT '',
            meta_description TEXT,
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_poll_votes (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            poll_id     INT          NOT NULL,
            option_id   INT          NOT NULL,
            ip_hash     VARCHAR(64)  NOT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_poll_ip (poll_id, ip_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_faq_categories (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            parent_id  INT          NULL DEFAULT NULL,
            name       VARCHAR(255) NOT NULL,
            sort_order INT          NOT NULL DEFAULT 0,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
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
            status       ENUM('pending','published') NOT NULL DEFAULT 'published',
            deleted_at   DATETIME     NULL DEFAULT NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_faqs_slug (slug),
            FULLTEXT INDEX ft_faqs_search (question, excerpt, answer)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_board_categories (
            id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(255) NOT NULL,
            sort_order INT          NOT NULL DEFAULT 0,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
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
            status         ENUM('pending','published') NOT NULL DEFAULT 'published',
            author_id      INT          NULL DEFAULT NULL,
            deleted_at     DATETIME     NULL DEFAULT NULL,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cms_board_slug (slug),
            FULLTEXT INDEX ft_board_search (title, excerpt, description)
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
            cancelled_at       DATETIME     NULL DEFAULT NULL,
            created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_res_date (resource_id, booking_date, status),
            INDEX idx_user (user_id, status)
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_media (
            id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            filename    VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL DEFAULT '',
            mime_type   VARCHAR(100) NOT NULL DEFAULT '',
            file_size   INT          NOT NULL DEFAULT 0,
            folder      VARCHAR(100) NOT NULL DEFAULT 'media',
            alt_text    VARCHAR(500) NOT NULL DEFAULT '',
            caption     TEXT,
            credit      VARCHAR(255) NOT NULL DEFAULT '',
            visibility  ENUM('public','private') NOT NULL DEFAULT 'public',
            uploaded_by INT          NULL DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_media_folder (folder),
            INDEX idx_media_mime (mime_type),
            INDEX idx_media_visibility (visibility)
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

        // ── Výchozí nastavení ────────────────────────────────────────────────
        $defaults = [
            'site_name'       => $siteName,
            'site_description'=> $siteDesc,
            'site_profile'    => $siteProfile,
            'admin_email'     => $adminEmail,
            'contact_email'   => $adminEmail,
            'admin_password'  => password_hash($adminPass, PASSWORD_BCRYPT),
            'module_blog'     => '1',
            'module_news'     => '1',
            'module_chat'     => '1',
            'module_contact'  => '1',
            'module_gallery'  => '1',
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
            'comment_blocked_emails' => '',
            'comment_spam_words' => '',
            'home_author_user_id' => '',
            'content_editor'  => 'html',
            'module_events'   => '1',
            'module_podcast'  => '1',
            'module_places'   => '1',
            'module_newsletter' => '1',
            'module_downloads'  => '1',
            'module_food'       => '1',
            'module_polls'      => '0',
            'module_faq'        => '0',
            'module_board'      => '0',
            'module_reservations' => '0',
            'module_forms'        => '0',
            'module_statistics'   => '0',
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
        ];
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
<?= publicA11yStyleTag() ?>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 520px; margin: 2rem auto; padding: 0 1rem; }
    label { display: block; margin-top: 1rem; font-weight: bold; }
    input, textarea { width: 100%; box-sizing: border-box; padding: .4rem; margin-top: .25rem; }
    button { margin-top: 1.5rem; padding: .5rem 1.5rem; }
    .error { color: #c00; }
    .success { color: #060; }
    .profile-option { margin-top: .9rem; padding: .85rem 1rem; border: 1px solid #d0d7de; border-radius: 10px; }
    .profile-option input { width: auto; margin-right: .5rem; }
    .profile-option label { display: inline; margin-top: 0; }
    .profile-option p { margin: .4rem 0 0 1.8rem; color: #444; font-size: .95rem; }
  </style>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<main id="obsah">
  <h1>Instalace Kora CMS</h1>

  <?php if ($success): ?>
    <p class="success" role="status"><strong>Instalace proběhla úspěšně.</strong><br>
    Smažte nebo přejmenujte soubor <code>install.php</code>.</p>
    <p><a href="<?= BASE_URL ?>/admin/login.php">Přejít do administrace →</a></p>
  <?php else: ?>

    <?php if (!empty($errors)): ?>
      <ul id="install-errors" class="error" role="alert">
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
          <div class="profile-option">
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
