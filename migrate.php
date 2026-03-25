<?php
/**
 * migrate.php – bezpečná aktualizace databáze
 * Vytvoří chybějící tabulky a doplní chybějící sloupce i nastavení.
 * Existující data ani nastavení NEPŘEPISUJE.
 * Po dokončení tento soubor smažte nebo přejmenujte.
 */
require_once __DIR__ . '/db.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    requireSuperAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Potvrzení migrace databáze</title>
<?= publicA11yStyleTag() ?>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
    .warning { background: #fff4e5; border: 1px solid #c77700; color: #7a4300; padding: .9rem 1rem; border-radius: 6px; }
    form { margin-top: 1.5rem; }
    button { padding: .6rem 1.2rem; }
  </style>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<main id="obsah">
  <h1>Migrace databáze</h1>
  <p class="warning"><strong>Tato akce upraví databázové schéma a výchozí nastavení.</strong> Spouštějte ji jen po aktualizaci systému a pouze jako superadmin.</p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <button type="submit">Spustit migraci</button>
  </form>
  <p><a href="<?= BASE_URL ?>/admin/index.php">Zpět do administrace</a></p>
</main>
</body>
</html>
        <?php
        exit;
    }

    verifyCsrf();
}

$pdo = db_connect();
$log = [];

// ── 1. Tabulky (CREATE TABLE IF NOT EXISTS = bezpečné, existující přeskočí) ──

$tables = [

    'cms_settings' => "CREATE TABLE IF NOT EXISTS cms_settings (
        id    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(100) NOT NULL UNIQUE,
        value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_categories' => "CREATE TABLE IF NOT EXISTS cms_categories (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_articles' => "CREATE TABLE IF NOT EXISTS cms_articles (
        id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title            VARCHAR(255) NOT NULL,
        slug             VARCHAR(255) NOT NULL UNIQUE,
        perex            TEXT,
        content          TEXT,
        comments_enabled TINYINT(1)   NOT NULL DEFAULT 1,
        category_id      INT,
        author_id        INT          NULL DEFAULT NULL,
        image_file       VARCHAR(255) NOT NULL DEFAULT '',
        meta_title       VARCHAR(160) NOT NULL DEFAULT '',
        meta_description TEXT,
        preview_token    VARCHAR(32)  NOT NULL DEFAULT '',
        status           ENUM('pending','published') NOT NULL DEFAULT 'published',
        publish_at       DATETIME     NULL DEFAULT NULL,
        created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_news' => "CREATE TABLE IF NOT EXISTS cms_news (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title      VARCHAR(255) NOT NULL,
        slug       VARCHAR(255) NOT NULL,
        content    TEXT         NOT NULL,
        author_id  INT          NULL DEFAULT NULL,
        status     ENUM('pending','published') NOT NULL DEFAULT 'published',
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_news_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_chat' => "CREATE TABLE IF NOT EXISTS cms_chat (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        email      VARCHAR(255) NOT NULL DEFAULT '',
        web        VARCHAR(255) NOT NULL DEFAULT '',
        message    TEXT         NOT NULL,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_contact' => "CREATE TABLE IF NOT EXISTS cms_contact (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        sender_email VARCHAR(255) NOT NULL,
        subject      VARCHAR(255) NOT NULL,
        message      TEXT         NOT NULL,
        is_read      TINYINT(1)   NOT NULL DEFAULT 0,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_users' => "CREATE TABLE IF NOT EXISTS cms_users (
        id                    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email                 VARCHAR(255) NOT NULL UNIQUE,
        password              VARCHAR(255) NOT NULL,
        first_name            VARCHAR(100) NOT NULL DEFAULT '',
        last_name             VARCHAR(100) NOT NULL DEFAULT '',
        nickname              VARCHAR(100) NOT NULL DEFAULT '',
        phone                 VARCHAR(30)  NOT NULL DEFAULT '',
        role                  ENUM('admin','collaborator','author','editor','moderator','booking_manager','public') NOT NULL DEFAULT 'collaborator',
        is_superadmin         TINYINT(1)   NOT NULL DEFAULT 0,
        is_confirmed          TINYINT(1)   NOT NULL DEFAULT 1,
        confirmation_token    VARCHAR(64)  NOT NULL DEFAULT '',
        confirmation_expires  DATETIME     NULL DEFAULT NULL,
        reset_token           VARCHAR(64)  NOT NULL DEFAULT '',
        reset_expires         DATETIME     NULL DEFAULT NULL,
        author_public_enabled TINYINT(1)   NOT NULL DEFAULT 0,
        author_slug           VARCHAR(255) NULL DEFAULT NULL,
        author_bio            TEXT,
        author_avatar         VARCHAR(255) NOT NULL DEFAULT '',
        author_website        VARCHAR(255) NOT NULL DEFAULT '',
        created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_users_author_slug (author_slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_tags' => "CREATE TABLE IF NOT EXISTS cms_tags (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        slug       VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_article_tags' => "CREATE TABLE IF NOT EXISTS cms_article_tags (
        article_id INT NOT NULL,
        tag_id     INT NOT NULL,
        PRIMARY KEY (article_id, tag_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_comments' => "CREATE TABLE IF NOT EXISTS cms_comments (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        article_id   INT          NOT NULL,
        author_name  VARCHAR(100) NOT NULL,
        author_email VARCHAR(255) NOT NULL DEFAULT '',
        content      TEXT         NOT NULL,
        status       ENUM('pending','approved','spam','trash') NOT NULL DEFAULT 'pending',
        is_approved  TINYINT(1)   NOT NULL DEFAULT 0,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_pages' => "CREATE TABLE IF NOT EXISTS cms_pages (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title        VARCHAR(255) NOT NULL,
        slug         VARCHAR(255) NOT NULL UNIQUE,
        content      TEXT,
        show_in_nav  TINYINT(1)   NOT NULL DEFAULT 0,
        nav_order    INT          NOT NULL DEFAULT 0,
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        status       ENUM('pending','published') NOT NULL DEFAULT 'published',
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_events' => "CREATE TABLE IF NOT EXISTS cms_events (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title        VARCHAR(255) NOT NULL,
        slug         VARCHAR(255) NOT NULL,
        description  TEXT,
        location     VARCHAR(255) NOT NULL DEFAULT '',
        event_date   DATETIME     NOT NULL,
        event_end    DATETIME     NULL DEFAULT NULL,
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        status       ENUM('pending','published') NOT NULL DEFAULT 'published',
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_events_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_subscribers' => "CREATE TABLE IF NOT EXISTS cms_subscribers (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(255) NOT NULL UNIQUE,
        token      VARCHAR(64)  NOT NULL UNIQUE,
        confirmed  TINYINT(1)   NOT NULL DEFAULT 0,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_newsletters' => "CREATE TABLE IF NOT EXISTS cms_newsletters (
        id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        subject         VARCHAR(255) NOT NULL,
        body            TEXT         NOT NULL,
        recipient_count INT          NOT NULL DEFAULT 0,
        sent_at         DATETIME     NULL DEFAULT NULL,
        created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_podcast_shows' => "CREATE TABLE IF NOT EXISTS cms_podcast_shows (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title       VARCHAR(255) NOT NULL,
        slug        VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        author      VARCHAR(255) NOT NULL DEFAULT '',
        cover_image VARCHAR(255) NOT NULL DEFAULT '',
        language    VARCHAR(10)  NOT NULL DEFAULT 'cs',
        category    VARCHAR(100) NOT NULL DEFAULT '',
        website_url VARCHAR(500) NOT NULL DEFAULT '',
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_podcasts' => "CREATE TABLE IF NOT EXISTS cms_podcasts (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        show_id     INT          NOT NULL DEFAULT 1,
        title       VARCHAR(255) NOT NULL,
        slug        VARCHAR(255) NOT NULL,
        description TEXT,
        audio_file  VARCHAR(255) NOT NULL DEFAULT '',
        audio_url   VARCHAR(500) NOT NULL DEFAULT '',
        duration    VARCHAR(20)  NOT NULL DEFAULT '',
        episode_num INT          NULL DEFAULT NULL,
        publish_at  DATETIME     NULL DEFAULT NULL,
        status      ENUM('pending','published') NOT NULL DEFAULT 'published',
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_podcasts_show_slug (show_id, slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_places' => "CREATE TABLE IF NOT EXISTS cms_places (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(255) NOT NULL,
        slug         VARCHAR(255) NOT NULL,
        place_kind   VARCHAR(50)  NOT NULL DEFAULT 'sight',
        excerpt      TEXT,
        description  TEXT,
        url          VARCHAR(500) NOT NULL DEFAULT '',
        image_file   VARCHAR(255) NOT NULL DEFAULT '',
        category     VARCHAR(100) NOT NULL DEFAULT '',
        address      VARCHAR(255) NOT NULL DEFAULT '',
        locality     VARCHAR(255) NOT NULL DEFAULT '',
        latitude     DECIMAL(10,7) NULL DEFAULT NULL,
        longitude    DECIMAL(10,7) NULL DEFAULT NULL,
        contact_phone VARCHAR(100) NOT NULL DEFAULT '',
        contact_email VARCHAR(255) NOT NULL DEFAULT '',
        opening_hours TEXT,
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        status       ENUM('pending','published') NOT NULL DEFAULT 'published',
        sort_order   INT          NOT NULL DEFAULT 0,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_places_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_dl_categories' => "CREATE TABLE IF NOT EXISTS cms_dl_categories (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_downloads' => "CREATE TABLE IF NOT EXISTS cms_downloads (
        id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title          VARCHAR(255) NOT NULL,
        slug           VARCHAR(255) NOT NULL,
        download_type  VARCHAR(50)  NOT NULL DEFAULT 'document',
        dl_category_id INT          NULL DEFAULT NULL,
        excerpt        TEXT,
        description    TEXT,
        image_file     VARCHAR(255) NOT NULL DEFAULT '',
        version_label  VARCHAR(100) NOT NULL DEFAULT '',
        platform_label VARCHAR(100) NOT NULL DEFAULT '',
        license_label  VARCHAR(100) NOT NULL DEFAULT '',
        external_url   VARCHAR(255) NOT NULL DEFAULT '',
        filename       VARCHAR(255) NOT NULL DEFAULT '',
        original_name  VARCHAR(255) NOT NULL DEFAULT '',
        file_size      INT          NOT NULL DEFAULT 0,
        sort_order     INT          NOT NULL DEFAULT 0,
        is_published   TINYINT(1)   NOT NULL DEFAULT 1,
        status         ENUM('pending','published') NOT NULL DEFAULT 'published',
        author_id      INT          NULL DEFAULT NULL,
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_downloads_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_gallery_albums' => "CREATE TABLE IF NOT EXISTS cms_gallery_albums (
        id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        parent_id      INT          DEFAULT NULL,
        name           VARCHAR(255) NOT NULL,
        description    TEXT,
        cover_photo_id INT          DEFAULT NULL,
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_gallery_photos' => "CREATE TABLE IF NOT EXISTS cms_gallery_photos (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        album_id   INT          NOT NULL,
        filename   VARCHAR(255) NOT NULL,
        title      VARCHAR(255) NOT NULL DEFAULT '',
        sort_order INT          NOT NULL DEFAULT 0,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_food_cards' => "CREATE TABLE IF NOT EXISTS cms_food_cards (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        type         ENUM('food','beverage') NOT NULL DEFAULT 'food',
        title        VARCHAR(255) NOT NULL,
        description  TEXT,
        content      MEDIUMTEXT,
        valid_from   DATE         NULL DEFAULT NULL,
        valid_to     DATE         NULL DEFAULT NULL,
        is_current   TINYINT(1)   NOT NULL DEFAULT 0,
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        status       ENUM('pending','published') NOT NULL DEFAULT 'published',
        author_id    INT          NULL DEFAULT NULL,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_rate_limit' => "CREATE TABLE IF NOT EXISTS cms_rate_limit (
        id           VARCHAR(64) NOT NULL PRIMARY KEY,
        attempts     INT         NOT NULL DEFAULT 1,
        window_start DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_log' => "CREATE TABLE IF NOT EXISTS cms_log (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        action     VARCHAR(100) NOT NULL,
        detail     TEXT,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_polls' => "CREATE TABLE IF NOT EXISTS cms_polls (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        question    VARCHAR(500) NOT NULL,
        slug        VARCHAR(255) NOT NULL,
        description TEXT,
        status      ENUM('active','closed') NOT NULL DEFAULT 'active',
        start_date  DATETIME     NULL DEFAULT NULL,
        end_date    DATETIME     NULL DEFAULT NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_polls_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_poll_options' => "CREATE TABLE IF NOT EXISTS cms_poll_options (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        poll_id     INT          NOT NULL,
        option_text VARCHAR(500) NOT NULL,
        sort_order  INT          NOT NULL DEFAULT 0,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_poll_votes' => "CREATE TABLE IF NOT EXISTS cms_poll_votes (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        poll_id     INT          NOT NULL,
        option_id   INT          NOT NULL,
        ip_hash     VARCHAR(64)  NOT NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_poll_ip (poll_id, ip_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_faq_categories' => "CREATE TABLE IF NOT EXISTS cms_faq_categories (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        sort_order INT          NOT NULL DEFAULT 0,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_faqs' => "CREATE TABLE IF NOT EXISTS cms_faqs (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category_id  INT          NULL DEFAULT NULL,
        question     VARCHAR(500) NOT NULL,
        slug         VARCHAR(255) NOT NULL,
        excerpt      TEXT,
        answer       TEXT         NOT NULL,
        sort_order   INT          NOT NULL DEFAULT 0,
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        status       ENUM('pending','published') NOT NULL DEFAULT 'published',
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_faqs_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_board_categories' => "CREATE TABLE IF NOT EXISTS cms_board_categories (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        sort_order INT          NOT NULL DEFAULT 0,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_board' => "CREATE TABLE IF NOT EXISTS cms_board (
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
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_board_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── Rezervační systém ──

    'cms_res_categories' => "CREATE TABLE IF NOT EXISTS cms_res_categories (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        sort_order INT          NOT NULL DEFAULT 0,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_res_resources' => "CREATE TABLE IF NOT EXISTS cms_res_resources (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_res_hours' => "CREATE TABLE IF NOT EXISTS cms_res_hours (
        id          INT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
        resource_id INT        NOT NULL,
        day_of_week TINYINT    NOT NULL,
        open_time   TIME       NOT NULL DEFAULT '09:00:00',
        close_time  TIME       NOT NULL DEFAULT '17:00:00',
        is_closed   TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY uq_res_day (resource_id, day_of_week)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_res_slots' => "CREATE TABLE IF NOT EXISTS cms_res_slots (
        id           INT     NOT NULL AUTO_INCREMENT PRIMARY KEY,
        resource_id  INT     NOT NULL,
        day_of_week  TINYINT NOT NULL,
        start_time   TIME    NOT NULL,
        end_time     TIME    NOT NULL,
        max_bookings INT     NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_res_blocked' => "CREATE TABLE IF NOT EXISTS cms_res_blocked (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        resource_id  INT          NOT NULL,
        blocked_date DATE         NOT NULL,
        reason       VARCHAR(255) NOT NULL DEFAULT '',
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_res_blocked (resource_id, blocked_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_res_locations' => "CREATE TABLE IF NOT EXISTS cms_res_locations (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        address    VARCHAR(500) NOT NULL DEFAULT '',
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_res_resource_locations' => "CREATE TABLE IF NOT EXISTS cms_res_resource_locations (
        resource_id INT NOT NULL,
        location_id INT NOT NULL,
        PRIMARY KEY (resource_id, location_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_res_bookings' => "CREATE TABLE IF NOT EXISTS cms_res_bookings (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── Statistiky ──

    'cms_page_views' => "CREATE TABLE IF NOT EXISTS cms_page_views (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_stats_daily' => "CREATE TABLE IF NOT EXISTS cms_stats_daily (
        id              INT  NOT NULL AUTO_INCREMENT PRIMARY KEY,
        stat_date       DATE NOT NULL UNIQUE,
        total_views     INT  NOT NULL DEFAULT 0,
        unique_visitors INT  NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        $log[] = "✓ Tabulka <code>{$name}</code> – OK";
    } catch (\PDOException $e) {
        $log[] = "✗ Tabulka <code>{$name}</code> – CHYBA: " . h($e->getMessage());
    }
}

// ── 2. Sloupce přidané v novějších verzích ────────────────────────────────────
// Přidáme jen pokud ještě neexistují (bezpečné pro existující instalace).

$addColumns = [
    // cms_articles
    'cms_articles.image_file'        => "ALTER TABLE cms_articles ADD COLUMN image_file VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_articles.publish_at'        => "ALTER TABLE cms_articles ADD COLUMN publish_at DATETIME NULL DEFAULT NULL",
    'cms_articles.meta_title'        => "ALTER TABLE cms_articles ADD COLUMN meta_title VARCHAR(160) NOT NULL DEFAULT ''",
    'cms_articles.meta_description'  => "ALTER TABLE cms_articles ADD COLUMN meta_description TEXT",
    'cms_articles.preview_token'     => "ALTER TABLE cms_articles ADD COLUMN preview_token VARCHAR(32) NOT NULL DEFAULT ''",
    'cms_articles.slug'              => "ALTER TABLE cms_articles ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_articles.comments_enabled'  => "ALTER TABLE cms_articles ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 1",
    'cms_articles.author_id'         => "ALTER TABLE cms_articles ADD COLUMN author_id INT NULL DEFAULT NULL",
    'cms_articles.status'            => "ALTER TABLE cms_articles ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_articles.updated_at'        => "ALTER TABLE cms_articles ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // cms_news
    'cms_news.title'                 => "ALTER TABLE cms_news ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_news.slug'                  => "ALTER TABLE cms_news ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_news.author_id'             => "ALTER TABLE cms_news ADD COLUMN author_id INT NULL DEFAULT NULL",
    'cms_news.status'                => "ALTER TABLE cms_news ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_news.updated_at'            => "ALTER TABLE cms_news ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // cms_faqs
    'cms_faqs.slug'                  => "ALTER TABLE cms_faqs ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_faqs.excerpt'               => "ALTER TABLE cms_faqs ADD COLUMN excerpt TEXT",
    'cms_faqs.status'                => "ALTER TABLE cms_faqs ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_podcasts
    'cms_podcasts.show_id'           => "ALTER TABLE cms_podcasts ADD COLUMN show_id INT NOT NULL DEFAULT 1",
    'cms_podcasts.slug'              => "ALTER TABLE cms_podcasts ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_podcasts.status'            => "ALTER TABLE cms_podcasts ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_events
    'cms_events.slug'                => "ALTER TABLE cms_events ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_events.status'              => "ALTER TABLE cms_events ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_places
    'cms_places.slug'                => "ALTER TABLE cms_places ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_places.place_kind'          => "ALTER TABLE cms_places ADD COLUMN place_kind VARCHAR(50) NOT NULL DEFAULT 'sight'",
    'cms_places.excerpt'             => "ALTER TABLE cms_places ADD COLUMN excerpt TEXT",
    'cms_places.image_file'          => "ALTER TABLE cms_places ADD COLUMN image_file VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_places.address'             => "ALTER TABLE cms_places ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_places.locality'            => "ALTER TABLE cms_places ADD COLUMN locality VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_places.latitude'            => "ALTER TABLE cms_places ADD COLUMN latitude DECIMAL(10,7) NULL DEFAULT NULL",
    'cms_places.longitude'           => "ALTER TABLE cms_places ADD COLUMN longitude DECIMAL(10,7) NULL DEFAULT NULL",
    'cms_places.contact_phone'       => "ALTER TABLE cms_places ADD COLUMN contact_phone VARCHAR(100) NOT NULL DEFAULT ''",
    'cms_places.contact_email'       => "ALTER TABLE cms_places ADD COLUMN contact_email VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_places.opening_hours'       => "ALTER TABLE cms_places ADD COLUMN opening_hours TEXT",
    'cms_places.status'              => "ALTER TABLE cms_places ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_places.updated_at'          => "ALTER TABLE cms_places ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // cms_pages
    'cms_pages.status'               => "ALTER TABLE cms_pages ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_board
    'cms_board.slug'                 => "ALTER TABLE cms_board ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_board.board_type'           => "ALTER TABLE cms_board ADD COLUMN board_type VARCHAR(50) NOT NULL DEFAULT 'document'",
    'cms_board.excerpt'              => "ALTER TABLE cms_board ADD COLUMN excerpt TEXT",
    'cms_board.image_file'           => "ALTER TABLE cms_board ADD COLUMN image_file VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_board.contact_name'         => "ALTER TABLE cms_board ADD COLUMN contact_name VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_board.contact_phone'        => "ALTER TABLE cms_board ADD COLUMN contact_phone VARCHAR(100) NOT NULL DEFAULT ''",
    'cms_board.contact_email'        => "ALTER TABLE cms_board ADD COLUMN contact_email VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_board.is_pinned'            => "ALTER TABLE cms_board ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0",
    // cms_downloads
    'cms_downloads.slug'             => "ALTER TABLE cms_downloads ADD COLUMN slug VARCHAR(255) NOT NULL DEFAULT '' AFTER title",
    'cms_downloads.download_type'    => "ALTER TABLE cms_downloads ADD COLUMN download_type VARCHAR(50) NOT NULL DEFAULT 'document' AFTER slug",
    'cms_downloads.dl_category_id'   => "ALTER TABLE cms_downloads ADD COLUMN dl_category_id INT NULL DEFAULT NULL",
    'cms_downloads.excerpt'          => "ALTER TABLE cms_downloads ADD COLUMN excerpt TEXT NULL AFTER dl_category_id",
    'cms_downloads.image_file'       => "ALTER TABLE cms_downloads ADD COLUMN image_file VARCHAR(255) NOT NULL DEFAULT '' AFTER description",
    'cms_downloads.version_label'    => "ALTER TABLE cms_downloads ADD COLUMN version_label VARCHAR(100) NOT NULL DEFAULT '' AFTER image_file",
    'cms_downloads.platform_label'   => "ALTER TABLE cms_downloads ADD COLUMN platform_label VARCHAR(100) NOT NULL DEFAULT '' AFTER version_label",
    'cms_downloads.license_label'    => "ALTER TABLE cms_downloads ADD COLUMN license_label VARCHAR(100) NOT NULL DEFAULT '' AFTER platform_label",
    'cms_downloads.external_url'     => "ALTER TABLE cms_downloads ADD COLUMN external_url VARCHAR(255) NOT NULL DEFAULT '' AFTER license_label",
    'cms_downloads.updated_at'       => "ALTER TABLE cms_downloads ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    // cms_polls
    'cms_polls.slug'                 => "ALTER TABLE cms_polls ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL AFTER question",
    // cms_users – rozšíření pro veřejné uživatele a role
    'cms_users.role'                 => "ALTER TABLE cms_users ADD COLUMN role ENUM('admin','collaborator','author','editor','moderator','booking_manager','public') NOT NULL DEFAULT 'collaborator'",
    'cms_users.phone'                => "ALTER TABLE cms_users ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT ''",
    'cms_users.is_confirmed'         => "ALTER TABLE cms_users ADD COLUMN is_confirmed TINYINT(1) NOT NULL DEFAULT 1",
    'cms_users.confirmation_token'   => "ALTER TABLE cms_users ADD COLUMN confirmation_token VARCHAR(64) NOT NULL DEFAULT ''",
    'cms_users.confirmation_expires' => "ALTER TABLE cms_users ADD COLUMN confirmation_expires DATETIME NULL DEFAULT NULL",
    'cms_users.reset_token'          => "ALTER TABLE cms_users ADD COLUMN reset_token VARCHAR(64) NOT NULL DEFAULT ''",
    'cms_users.reset_expires'        => "ALTER TABLE cms_users ADD COLUMN reset_expires DATETIME NULL DEFAULT NULL",
    'cms_users.author_public_enabled'=> "ALTER TABLE cms_users ADD COLUMN author_public_enabled TINYINT(1) NOT NULL DEFAULT 0",
    'cms_users.author_slug'          => "ALTER TABLE cms_users ADD COLUMN author_slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_users.author_bio'           => "ALTER TABLE cms_users ADD COLUMN author_bio TEXT",
    'cms_users.author_avatar'        => "ALTER TABLE cms_users ADD COLUMN author_avatar VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_users.author_website'       => "ALTER TABLE cms_users ADD COLUMN author_website VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_users.updated_at'           => "ALTER TABLE cms_users ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // cms_res_resources – povolení hostů
    'cms_res_resources.allow_guests' => "ALTER TABLE cms_res_resources ADD COLUMN allow_guests TINYINT(1) NOT NULL DEFAULT 0",
    // cms_articles – počítadlo zobrazení
    'cms_articles.view_count'        => "ALTER TABLE cms_articles ADD COLUMN view_count INT NOT NULL DEFAULT 0",
    // cms_comments – stavový model moderace
    'cms_comments.status'            => "ALTER TABLE cms_comments ADD COLUMN status ENUM('pending','approved','spam','trash') NOT NULL DEFAULT 'pending'",
];

foreach ($addColumns as $tableCol => $sql) {
    [$tbl, $col] = explode('.', $tableCol, 2);
    try {
        $exists = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $exists->execute([$tbl, $col]);
        if ((int)$exists->fetchColumn() === 0) {
            $pdo->exec($sql);
            $log[] = "✓ Sloupec <code>{$tableCol}</code> přidán – OK";
        } else {
            $log[] = "· Sloupec <code>{$tableCol}</code> již existuje – přeskočeno";
        }
    } catch (\PDOException $e) {
        $log[] = "✗ Sloupec <code>{$tableCol}</code> – CHYBA: " . h($e->getMessage());
    }
}

// ── 3. Migrace textových kategorií ke stažení → cms_dl_categories ─────────────
// Převede existující hodnoty sloupce `category` na záznamy v cms_dl_categories
// a nastaví dl_category_id. Bezpečné – spouští se jen pokud dl_category_id je NULL
// a category není prázdný řetězec.

try {
    // Zkontroluje, zda sloupec `category` ještě existuje (starší instalace)
    $colCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_downloads' AND COLUMN_NAME = 'category'"
    );
    $colCheck->execute();
    $hasCatCol = (int)$colCheck->fetchColumn();

    if ($hasCatCol) {
        // Načte unikátní neprázdné textové kategorie bez přiřazeného dl_category_id
        $rows = $pdo->query(
            "SELECT DISTINCT category FROM cms_downloads
             WHERE category != '' AND dl_category_id IS NULL
             ORDER BY category"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $insertCat = $pdo->prepare("INSERT IGNORE INTO cms_dl_categories (name) VALUES (?)");
        $getCatId  = $pdo->prepare("SELECT id FROM cms_dl_categories WHERE name = ? LIMIT 1");
        $updDl     = $pdo->prepare(
            "UPDATE cms_downloads SET dl_category_id = ? WHERE category = ? AND dl_category_id IS NULL"
        );

        foreach ($rows as $catName) {
            $insertCat->execute([$catName]);
            $getCatId->execute([$catName]);
            $catId = $getCatId->fetchColumn();
            if ($catId) {
                $updDl->execute([$catId, $catName]);
                $log[] = '✓ Kategorie ke stažení <code>' . h($catName) . '</code> migrována (id=' . $catId . ') – OK';
            }
        }

        if (empty($rows)) {
            $log[] = "· Migrace kategorií ke stažení – žádné textové kategorie k převodu";
        }
    } else {
        $log[] = "· Migrace kategorií ke stažení – sloupec <code>category</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Migrace kategorií ke stažení – CHYBA: " . h($e->getMessage());
}

// ── 4. Migrace rolí stávajících uživatelů ────────────────────────────────────
// Nastaví role='admin' pro superadminy a role='collaborator' pro ostatní.

try {
    $colCheck = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_users' AND COLUMN_NAME = 'role'"
    );
    $colCheck->execute();
    $roleColumnType = (string)$colCheck->fetchColumn();
    if ($roleColumnType !== '') {
        if (strpos($roleColumnType, "'author'") === false || strpos($roleColumnType, "'editor'") === false || strpos($roleColumnType, "'moderator'") === false || strpos($roleColumnType, "'booking_manager'") === false) {
            $pdo->exec(
                "ALTER TABLE cms_users MODIFY COLUMN role
                 ENUM('admin','collaborator','author','editor','moderator','booking_manager','public')
                 NOT NULL DEFAULT 'collaborator'"
            );
            $log[] = "✓ ENUM <code>cms_users.role</code> rozšířen o nové role – OK";
        } else {
            $log[] = "· ENUM <code>cms_users.role</code> již obsahuje nové role – přeskočeno";
        }

        $updated = $pdo->exec(
            "UPDATE cms_users SET role = 'admin' WHERE is_superadmin = 1 AND role = 'collaborator'"
        );
        if ($updated > 0) {
            $log[] = "✓ Role superadminů nastavena na 'admin' – OK ({$updated} uživatelů)";
        } else {
            $log[] = "· Migrace rolí – žádní superadmini k aktualizaci";
        }
    }
} catch (\PDOException $e) {
    $log[] = "✗ Migrace rolí – CHYBA: " . h($e->getMessage());
}

// ── 4b. Rozšíření ENUM stavu rezervací o 'no_show' ──────────────────────────

try {
    $colCheck = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_res_bookings' AND COLUMN_NAME = 'status'"
    );
    $colCheck->execute();
    $colType = (string)$colCheck->fetchColumn();

    if ($colType !== '' && strpos($colType, 'no_show') === false) {
        $pdo->exec(
            "ALTER TABLE cms_res_bookings MODIFY COLUMN status
             ENUM('pending','confirmed','cancelled','rejected','completed','no_show')
             NOT NULL DEFAULT 'pending'"
        );
        $log[] = "✓ ENUM <code>cms_res_bookings.status</code> rozšířen o 'no_show' – OK";
    } else {
        $log[] = "· ENUM <code>cms_res_bookings.status</code> již obsahuje 'no_show' – přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Rozšíření ENUM stavu rezervací – CHYBA: " . h($e->getMessage());
}

// ── 5. Výchozí podcast show (zachová zpětnou kompatibilitu) ───────────────────

try {
    $showCount = (int)$pdo->query("SELECT COUNT(*) FROM cms_podcast_shows")->fetchColumn();
    if ($showCount === 0) {
        $siteName = getSetting('site_name', 'Podcast');
        $pdo->prepare(
            "INSERT INTO cms_podcast_shows (id, title, slug, description, language)
             VALUES (1, ?, 'hlavni-podcast', '', 'cs')"
        )->execute([$siteName . ' – podcast']);
        $log[] = "✓ Výchozí podcast show vytvořen – OK";
    } else {
        $log[] = "· Podcast show již existuje – přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Výchozí podcast show – CHYBA: " . h($e->getMessage());
}

// ── 6. Chybějící nastavení (existující hodnoty NEPŘEPISUJE) ───────────────────

try {
    $slugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_articles' AND COLUMN_NAME = 'slug'"
    );
    $slugColumnCheck->execute();

    if ((int)$slugColumnCheck->fetchColumn() > 0) {
        $articleRows = $pdo->query("SELECT id, title, slug FROM cms_articles ORDER BY id")->fetchAll();
        $updateSlugStmt = $pdo->prepare("UPDATE cms_articles SET slug = ? WHERE id = ?");

        foreach ($articleRows as $articleRow) {
            $existingSlug = trim((string)($articleRow['slug'] ?? ''));
            $resolvedSlug = uniqueArticleSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : (string)$articleRow['title'],
                (int)$articleRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updateSlugStmt->execute([$resolvedSlug, (int)$articleRow['id']]);
            }
        }

        $slugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_articles' AND COLUMN_NAME = 'slug'"
        );
        $slugNullabilityCheck->execute();
        if (($slugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_articles MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_articles.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_articles.slug</code> už je NOT NULL – přeskočeno";
        }

        $slugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_articles'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $slugIndexCheck->execute();
        if ((int)$slugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_articles ADD UNIQUE KEY uq_cms_articles_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_articles_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_articles.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy článků – sloupec <code>cms_articles.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy článků – CHYBA: " . h($e->getMessage());
}

try {
    $authorSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_users' AND COLUMN_NAME = 'author_slug'"
    );
    $authorSlugColumnCheck->execute();

    if ((int)$authorSlugColumnCheck->fetchColumn() > 0) {
        $userRows = $pdo->query(
            "SELECT id, email, first_name, last_name, nickname, role, author_slug
             FROM cms_users
             WHERE role != 'public'
             ORDER BY is_superadmin DESC, id ASC"
        )->fetchAll();
        $updateAuthorSlugStmt = $pdo->prepare("UPDATE cms_users SET author_slug = ? WHERE id = ?");

        foreach ($userRows as $userRow) {
            $existingSlug = trim((string)($userRow['author_slug'] ?? ''));
            $resolvedSlug = uniqueAuthorSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : authorSlugCandidate($userRow),
                (int)$userRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updateAuthorSlugStmt->execute([$resolvedSlug, (int)$userRow['id']]);
            }
        }

        $authorSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_users'
               AND COLUMN_NAME = 'author_slug' AND NON_UNIQUE = 0"
        );
        $authorSlugIndexCheck->execute();
        if ((int)$authorSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_users ADD UNIQUE KEY uq_cms_users_author_slug (author_slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_users_author_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_users.author_slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy autorů – sloupec <code>cms_users.author_slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy autorů – CHYBA: " . h($e->getMessage());
}

try {
    $newsTitleColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_news' AND COLUMN_NAME = 'title'"
    );
    $newsSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_news' AND COLUMN_NAME = 'slug'"
    );
    $newsTitleColumnCheck->execute();
    $newsSlugColumnCheck->execute();

    if ((int)$newsTitleColumnCheck->fetchColumn() > 0 && (int)$newsSlugColumnCheck->fetchColumn() > 0) {
        $newsRows = $pdo->query("SELECT id, title, slug, content FROM cms_news ORDER BY id")->fetchAll();
        $updateNewsStmt = $pdo->prepare("UPDATE cms_news SET title = ?, slug = ? WHERE id = ?");

        foreach ($newsRows as $newsRow) {
            $resolvedTitle = newsTitleCandidate((string)($newsRow['title'] ?? ''), (string)($newsRow['content'] ?? ''));
            $existingSlug = trim((string)($newsRow['slug'] ?? ''));
            $resolvedSlug = uniqueNewsSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : $resolvedTitle,
                (int)$newsRow['id']
            );

            if ((string)($newsRow['title'] ?? '') !== $resolvedTitle || $existingSlug !== $resolvedSlug) {
                $updateNewsStmt->execute([$resolvedTitle, $resolvedSlug, (int)$newsRow['id']]);
            }
        }

        $newsSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_news' AND COLUMN_NAME = 'slug'"
        );
        $newsSlugNullabilityCheck->execute();
        if (($newsSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_news MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_news.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_news.slug</code> už je NOT NULL – přeskočeno";
        }

        $newsSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_news'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $newsSlugIndexCheck->execute();
        if ((int)$newsSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_news ADD UNIQUE KEY uq_cms_news_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_news_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_news.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy novinek – potřebné sloupce v <code>cms_news</code> neexistují, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy novinek – CHYBA: " . h($e->getMessage());
}

try {
    $faqSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_faqs' AND COLUMN_NAME = 'slug'"
    );
    $faqSlugColumnCheck->execute();

    if ((int)$faqSlugColumnCheck->fetchColumn() > 0) {
        $faqRows = $pdo->query("SELECT id, question, slug FROM cms_faqs ORDER BY id")->fetchAll();
        $updateFaqSlugStmt = $pdo->prepare("UPDATE cms_faqs SET slug = ? WHERE id = ?");

        foreach ($faqRows as $faqRow) {
            $existingSlug = trim((string)($faqRow['slug'] ?? ''));
            $resolvedSlug = uniqueFaqSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : (string)$faqRow['question'],
                (int)$faqRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updateFaqSlugStmt->execute([$resolvedSlug, (int)$faqRow['id']]);
            }
        }

        $faqSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_faqs' AND COLUMN_NAME = 'slug'"
        );
        $faqSlugNullabilityCheck->execute();
        if (($faqSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_faqs MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_faqs.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_faqs.slug</code> už je NOT NULL – přeskočeno";
        }

        $faqSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_faqs'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $faqSlugIndexCheck->execute();
        if ((int)$faqSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_faqs ADD UNIQUE KEY uq_cms_faqs_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_faqs_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_faqs.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy FAQ – sloupec <code>cms_faqs.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy FAQ – CHYBA: " . h($e->getMessage());
}

try {
    $podcastSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_podcasts' AND COLUMN_NAME = 'slug'"
    );
    $podcastSlugColumnCheck->execute();

    if ((int)$podcastSlugColumnCheck->fetchColumn() > 0) {
        $podcastRows = $pdo->query("SELECT id, show_id, title, slug FROM cms_podcasts ORDER BY id")->fetchAll();
        $updatePodcastSlugStmt = $pdo->prepare("UPDATE cms_podcasts SET slug = ? WHERE id = ?");

        foreach ($podcastRows as $podcastRow) {
            $podcastShowId = max(1, (int)($podcastRow['show_id'] ?? 1));
            $existingSlug = trim((string)($podcastRow['slug'] ?? ''));
            $resolvedSlug = uniquePodcastEpisodeSlug(
                $pdo,
                $podcastShowId,
                $existingSlug !== '' ? $existingSlug : (string)$podcastRow['title'],
                (int)$podcastRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updatePodcastSlugStmt->execute([$resolvedSlug, (int)$podcastRow['id']]);
            }
        }

        $podcastSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_podcasts' AND COLUMN_NAME = 'slug'"
        );
        $podcastSlugNullabilityCheck->execute();
        if (($podcastSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_podcasts MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_podcasts.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_podcasts.slug</code> už je NOT NULL – přeskočeno";
        }

        $podcastSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_podcasts'
               AND INDEX_NAME = 'uq_cms_podcasts_show_slug'"
        );
        $podcastSlugIndexCheck->execute();
        if ((int)$podcastSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_podcasts ADD UNIQUE KEY uq_cms_podcasts_show_slug (show_id, slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_podcasts_show_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_podcasts(show_id, slug)</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy podcastových epizod – sloupec <code>cms_podcasts.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy podcastových epizod – CHYBA: " . h($e->getMessage());
}

try {
    $eventSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_events' AND COLUMN_NAME = 'slug'"
    );
    $eventSlugColumnCheck->execute();

    if ((int)$eventSlugColumnCheck->fetchColumn() > 0) {
        $eventRows = $pdo->query("SELECT id, title, slug FROM cms_events ORDER BY id")->fetchAll();
        $updateEventSlugStmt = $pdo->prepare("UPDATE cms_events SET slug = ? WHERE id = ?");

        foreach ($eventRows as $eventRow) {
            $existingSlug = trim((string)($eventRow['slug'] ?? ''));
            $resolvedSlug = uniqueEventSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : (string)$eventRow['title'],
                (int)$eventRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updateEventSlugStmt->execute([$resolvedSlug, (int)$eventRow['id']]);
            }
        }

        $eventSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_events' AND COLUMN_NAME = 'slug'"
        );
        $eventSlugNullabilityCheck->execute();
        if (($eventSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_events MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_events.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_events.slug</code> už je NOT NULL – přeskočeno";
        }

        $eventSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_events'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $eventSlugIndexCheck->execute();
        if ((int)$eventSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_events ADD UNIQUE KEY uq_cms_events_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_events_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_events.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy událostí – sloupec <code>cms_events.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy událostí – CHYBA: " . h($e->getMessage());
}

try {
    $downloadSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_downloads' AND COLUMN_NAME = 'slug'"
    );
    $downloadSlugColumnCheck->execute();

    if ((int)$downloadSlugColumnCheck->fetchColumn() > 0) {
        $downloadRows = $pdo->query("SELECT id, title, slug FROM cms_downloads ORDER BY id")->fetchAll();
        $updateDownloadSlugStmt = $pdo->prepare("UPDATE cms_downloads SET slug = ? WHERE id = ?");

        foreach ($downloadRows as $downloadRow) {
            $existingSlug = trim((string)($downloadRow['slug'] ?? ''));
            $resolvedSlug = uniqueDownloadSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : (string)$downloadRow['title'],
                (int)$downloadRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updateDownloadSlugStmt->execute([$resolvedSlug, (int)$downloadRow['id']]);
            }
        }

        $downloadSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_downloads' AND COLUMN_NAME = 'slug'"
        );
        $downloadSlugNullabilityCheck->execute();
        if (($downloadSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_downloads MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_downloads.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_downloads.slug</code> už je NOT NULL – přeskočeno";
        }

        $downloadSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_downloads'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $downloadSlugIndexCheck->execute();
        if ((int)$downloadSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_downloads ADD UNIQUE KEY uq_cms_downloads_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_downloads_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_downloads.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy souborů ke stažení – sloupec <code>cms_downloads.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy souborů ke stažení – CHYBA: " . h($e->getMessage());
}

try {
    $pollSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_polls' AND COLUMN_NAME = 'slug'"
    );
    $pollSlugColumnCheck->execute();

    if ((int)$pollSlugColumnCheck->fetchColumn() > 0) {
        $pollRows = $pdo->query("SELECT id, question, slug FROM cms_polls ORDER BY id")->fetchAll();
        $updatePollSlugStmt = $pdo->prepare("UPDATE cms_polls SET slug = ? WHERE id = ?");

        foreach ($pollRows as $pollRow) {
            $existingSlug = trim((string)($pollRow['slug'] ?? ''));
            $resolvedSlug = uniquePollSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : (string)$pollRow['question'],
                (int)$pollRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updatePollSlugStmt->execute([$resolvedSlug, (int)$pollRow['id']]);
            }
        }

        $pollSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_polls' AND COLUMN_NAME = 'slug'"
        );
        $pollSlugNullabilityCheck->execute();
        if (($pollSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_polls MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_polls.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_polls.slug</code> už je NOT NULL – přeskočeno";
        }

        $pollSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_polls'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $pollSlugIndexCheck->execute();
        if ((int)$pollSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_polls ADD UNIQUE KEY uq_cms_polls_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_polls_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_polls.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy anket – sloupec <code>cms_polls.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy anket – CHYBA: " . h($e->getMessage());
}

try {
    $placeSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_places' AND COLUMN_NAME = 'slug'"
    );
    $placeSlugColumnCheck->execute();

    if ((int)$placeSlugColumnCheck->fetchColumn() > 0) {
        $placeRows = $pdo->query("SELECT id, name, slug FROM cms_places ORDER BY id")->fetchAll();
        $updatePlaceSlugStmt = $pdo->prepare("UPDATE cms_places SET slug = ? WHERE id = ?");

        foreach ($placeRows as $placeRow) {
            $existingSlug = trim((string)($placeRow['slug'] ?? ''));
            $resolvedSlug = uniquePlaceSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : (string)$placeRow['name'],
                (int)$placeRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updatePlaceSlugStmt->execute([$resolvedSlug, (int)$placeRow['id']]);
            }
        }

        $placeSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_places' AND COLUMN_NAME = 'slug'"
        );
        $placeSlugNullabilityCheck->execute();
        if (($placeSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_places MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_places.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_places.slug</code> už je NOT NULL – přeskočeno";
        }

        $placeSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_places'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $placeSlugIndexCheck->execute();
        if ((int)$placeSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_places ADD UNIQUE KEY uq_cms_places_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_places_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_places.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy míst – sloupec <code>cms_places.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy míst – CHYBA: " . h($e->getMessage());
}

try {
    $boardSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_board' AND COLUMN_NAME = 'slug'"
    );
    $boardSlugColumnCheck->execute();

    if ((int)$boardSlugColumnCheck->fetchColumn() > 0) {
        $boardRows = $pdo->query("SELECT id, title, slug FROM cms_board ORDER BY id")->fetchAll();
        $updateBoardSlugStmt = $pdo->prepare("UPDATE cms_board SET slug = ? WHERE id = ?");

        foreach ($boardRows as $boardRow) {
            $existingSlug = trim((string)($boardRow['slug'] ?? ''));
            $resolvedSlug = uniqueBoardSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : (string)$boardRow['title'],
                (int)$boardRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updateBoardSlugStmt->execute([$resolvedSlug, (int)$boardRow['id']]);
            }
        }

        $boardSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_board' AND COLUMN_NAME = 'slug'"
        );
        $boardSlugNullabilityCheck->execute();
        if (($boardSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_board MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_board.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_board.slug</code> už je NOT NULL – přeskočeno";
        }

        $boardSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'cms_board'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $boardSlugIndexCheck->execute();
        if ((int)$boardSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_board ADD UNIQUE KEY uq_cms_board_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_board_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_board.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy úřední desky – sloupec <code>cms_board.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy úřední desky – CHYBA: " . h($e->getMessage());
}

$newSettings = [
    // Moduly
    'module_blog'             => '1',
    'module_news'             => '1',
    'module_chat'             => '1',
    'module_contact'          => '1',
    'module_gallery'          => '1',
    'module_events'           => '1',
    'module_podcast'          => '1',
    'module_places'           => '1',
    'module_newsletter'       => '1',
    'module_downloads'        => '1',
    'module_food'             => '1',
    'module_polls'            => '0',
    'module_faq'              => '0',
    'module_board'            => '0',
    'module_reservations'     => '0',
    'module_statistics'       => '0',
    'visitor_tracking_enabled' => '0',
    'visitor_counter_enabled'  => '0',
    'stats_retention_days'     => '90',
    // Počty a stránkování
    'home_blog_count'         => '5',
    'home_news_count'         => '5',
    'home_board_count'        => '5',
    'board_public_label'      => defaultBoardPublicLabelForProfile(guessSiteProfileKey()),
    'news_per_page'           => '10',
    'blog_per_page'           => '10',
    'events_per_page'         => '10',
    'comments_enabled'        => '1',
    'comment_moderation_mode' => 'always',
    'comment_close_days'      => '0',
    'comment_notify_admin'    => '1',
    'comment_notify_author_approve' => '0',
    'comment_notify_email'    => '',
    'comment_blocked_emails'  => '',
    'comment_spam_words'      => '',
    'home_author_user_id'     => '',
    // Editor
    'content_editor'          => 'html',
    // Sociální sítě
    'social_facebook'         => '',
    'social_youtube'          => '',
    'social_instagram'        => '',
    'social_twitter'          => '',
    // Vzhled
    'og_image_default'        => '',
    'site_favicon'            => '',
    'site_logo'               => '',
    'site_profile'            => guessSiteProfileKey(),
    'active_theme'            => defaultThemeName(),
    // Cookie lišta
    'cookie_consent_enabled'  => '0',
    'cookie_consent_text'     => 'Tento web používá soubory cookies ke zlepšení vašeho zážitku z prohlížení.',
    // Údržba
    'maintenance_mode'        => '0',
    'maintenance_text'        => 'Právě probíhá údržba webu. Brzy budeme zpět, děkujeme za trpělivost.',
    // Úvodní stránka
    'home_intro'              => '',
    // Pořadí modulů v navigaci
    'nav_module_order'        => '',
];

$stmt = $pdo->prepare(
    "INSERT INTO cms_settings (`key`, value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE id = id"  // no-op = neprepisuje existující hodnoty
);
foreach ($newSettings as $k => $v) {
    try {
        $stmt->execute([$k, $v]);
        $log[] = "· Nastavení <code>{$k}</code> – OK";
    } catch (\PDOException $e) {
        $log[] = "✗ Nastavení <code>{$k}</code> – CHYBA: " . h($e->getMessage());
    }
}

// ── 7. Migrace superadmina z cms_settings → cms_users ────────────────────────

try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM cms_users")->fetchColumn();
    if ($count === 0) {
        $adminEmail = getSetting('admin_email', '');
        $adminHash  = getSetting('admin_password', '');
        if ($adminEmail !== '' && $adminHash !== '') {
            $pdo->prepare(
                "INSERT INTO cms_users (email, password, role, is_superadmin, is_confirmed, author_slug)
                 VALUES (?, ?, 'admin', 1, 1, ?)"
            )->execute([
                $adminEmail,
                $adminHash,
                uniqueAuthorSlug($pdo, strstr($adminEmail, '@', true) ?: 'autor'),
            ]);
            $log[] = "✓ Superadmin <code>{$adminEmail}</code> migrován do cms_users – OK";
        } else {
            $log[] = "⚠ Superadmin nebyl migrován – admin_email nebo admin_password v nastavení chybí.";
        }
    } else {
        $log[] = "· cms_users již obsahuje uživatele – migrace superadmina přeskočena";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Migrace superadmina – CHYBA: " . h($e->getMessage());
}

// ── 8. Adresáře pro nahrávání souborů ────────────────────────────────────────

$uploadDirs = [
    'uploads/site',
    'uploads/authors',
    'uploads/articles',
    'uploads/articles/thumbs',
    'uploads/gallery',
    'uploads/gallery/thumbs',
    'uploads/downloads',
    'uploads/downloads/images',
    'uploads/places',
    'uploads/board',
    'uploads/board/images',
    'uploads/podcasts',
    'uploads/podcasts/covers',
];
foreach ($uploadDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            $log[] = "✓ Adresář <code>{$dir}</code> vytvořen – OK";
        } else {
            $log[] = "✗ Adresář <code>{$dir}</code> – nepodařilo se vytvořit";
        }
    } else {
        $log[] = "· Adresář <code>{$dir}</code> již existuje – přeskočeno";
    }
}
try {
    $statusExists = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND COLUMN_NAME = 'status'"
    );
    $statusExists->execute();
    if ((int)$statusExists->fetchColumn() > 0) {
        $updatedStatuses = $pdo->exec(
            "UPDATE cms_comments
             SET status = CASE WHEN is_approved = 1 THEN 'approved' ELSE 'pending' END
             WHERE status NOT IN ('approved','spam','trash')"
        );
        $updatedApprovals = $pdo->exec(
            "UPDATE cms_comments
             SET is_approved = CASE WHEN status = 'approved' THEN 1 ELSE 0 END"
        );
        $log[] = "✓ Komentáře migrovány na stavový model – OK ({$updatedStatuses} stavů, {$updatedApprovals} synchronizací)";
    } else {
        $log[] = "· Migrace komentářů na stavový model – sloupec <code>status</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Migrace komentářů na stavový model – CHYBA: " . h($e->getMessage());
}

if ($isCli) {
    foreach ($log as $line) {
        echo trim(strip_tags(html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) . PHP_EOL;
    }
    echo 'Hotovo. Smazte nebo prejmenujte soubor migrate.php.' . PHP_EOL;
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Migrace databáze</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
    li { margin: .2rem 0; font-size: .9rem; }
    h2 { margin-top: 1.5rem; font-size: 1rem; color: #555; text-transform: uppercase; letter-spacing: .05em; }
  </style>
</head>
<body>
<h1>Migrace databáze</h1>
<ul>
  <?php foreach ($log as $line): ?>
    <li><?= $line ?></li>
  <?php endforeach; ?>
</ul>
<p><strong>Hotovo. Smažte nebo přejmenujte soubor <code>migrate.php</code>.</strong></p>
<p><a href="<?= BASE_URL ?>/admin/index.php">Přejít do administrace →</a></p>
</body>
</html>
