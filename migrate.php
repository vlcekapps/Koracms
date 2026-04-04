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

// ── Verzování migrace ──────────────────────────────────────────────────────
// Ukládá do cms_settings klíč 'migration_version'. Pokud je aktuální verze
// shodná s cílovou, migrace se přeskočí. Díky tomu je opakované spuštění
// bezpečné a zbytečné re-runy se neprovedou.
$migrationTargetVersion = KORA_VERSION;
$migrationCurrentVersion = '';
try {
    $vStmt = $pdo->query("SELECT value FROM cms_settings WHERE `key` = 'migration_version'");
    $vRow  = $vStmt ? $vStmt->fetch() : false;
    $migrationCurrentVersion = $vRow ? (string)$vRow['value'] : '';
} catch (\PDOException $e) {
    // Tabulka cms_settings ještě neexistuje – první spuštění
}

if ($migrationCurrentVersion === $migrationTargetVersion) {
    $msg = "Databáze je již na verzi {$migrationTargetVersion} – migrace není potřeba.";
    if ($isCli) {
        echo $msg . PHP_EOL;
        exit;
    }
    ?>
<!DOCTYPE html>
<html lang="cs">
<head><meta charset="utf-8"><title>Migrace</title>
<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem}</style>
</head><body>
<h1>Migrace databáze</h1>
<p><?= h($msg) ?></p>
<p><a href="<?= BASE_URL ?>/admin/index.php">Přejít do administrace →</a></p>
</body></html>
    <?php
    exit;
}

$log[] = '· Aktuální verze migrace: ' . h($migrationCurrentVersion ?: '(žádná)') . ' → cíl: ' . h($migrationTargetVersion);

// ── 1. Tabulky (CREATE TABLE IF NOT EXISTS = bezpečné, existující přeskočí) ──

$tables = [

    'cms_settings' => "CREATE TABLE IF NOT EXISTS cms_settings (
        id    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(100) NOT NULL UNIQUE,
        value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_blogs' => "CREATE TABLE IF NOT EXISTS cms_blogs (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_blog_members' => "CREATE TABLE IF NOT EXISTS cms_blog_members (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        blog_id      INT         NOT NULL,
        user_id      INT         NOT NULL,
        member_role  ENUM('author','manager') NOT NULL DEFAULT 'author',
        created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_blog_members (blog_id, user_id),
        INDEX idx_cms_blog_members_user (user_id),
        INDEX idx_cms_blog_members_blog (blog_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_blog_slug_redirects' => "CREATE TABLE IF NOT EXISTS cms_blog_slug_redirects (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        blog_id      INT         NOT NULL,
        old_slug     VARCHAR(100) NOT NULL,
        created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_blog_slug_redirects_old_slug (old_slug),
        INDEX idx_cms_blog_slug_redirects_blog (blog_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_categories' => "CREATE TABLE IF NOT EXISTS cms_categories (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        blog_id    INT          NOT NULL DEFAULT 1,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_categories_blog_id (blog_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_articles' => "CREATE TABLE IF NOT EXISTS cms_articles (
        id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title            VARCHAR(255) NOT NULL,
        slug             VARCHAR(255) NOT NULL UNIQUE,
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
        updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_news' => "CREATE TABLE IF NOT EXISTS cms_news (
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
        deleted_at DATETIME     NULL DEFAULT NULL,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_news_slug (slug),
        FULLTEXT INDEX ft_news_search (title, content)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_chat' => "CREATE TABLE IF NOT EXISTS cms_chat (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_chat_history' => "CREATE TABLE IF NOT EXISTS cms_chat_history (
        id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
        chat_id       INT          NOT NULL,
        actor_user_id INT          NULL DEFAULT NULL,
        event_type    VARCHAR(50)  NOT NULL DEFAULT 'workflow',
        message       TEXT         NOT NULL,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_contact' => "CREATE TABLE IF NOT EXISTS cms_contact (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        sender_email VARCHAR(255) NOT NULL,
        subject      VARCHAR(255) NOT NULL,
        message      TEXT         NOT NULL,
        is_read      TINYINT(1)   NOT NULL DEFAULT 0,
        status       ENUM('new','read','handled') NOT NULL DEFAULT 'new',
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
        blog_id      INT          NULL DEFAULT NULL,
        blog_nav_order INT        NOT NULL DEFAULT 0,
        show_in_nav  TINYINT(1)   NOT NULL DEFAULT 0,
        nav_order    INT          NOT NULL DEFAULT 0,
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        status       ENUM('draft','pending','published') NOT NULL DEFAULT 'published',
        publish_at   DATETIME     NULL DEFAULT NULL,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_pages_blog_nav (blog_id, blog_nav_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_events' => "CREATE TABLE IF NOT EXISTS cms_events (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title        VARCHAR(255) NOT NULL,
        slug         VARCHAR(255) NOT NULL,
        event_kind   VARCHAR(50)  NOT NULL DEFAULT 'general',
        excerpt      TEXT,
        description  TEXT,
        program_note TEXT,
        location     VARCHAR(255) NOT NULL DEFAULT '',
        organizer_name VARCHAR(255) NOT NULL DEFAULT '',
        organizer_email VARCHAR(255) NOT NULL DEFAULT '',
        registration_url VARCHAR(500) NOT NULL DEFAULT '',
        price_note   VARCHAR(255) NOT NULL DEFAULT '',
        accessibility_note TEXT,
        image_file   VARCHAR(255) NOT NULL DEFAULT '',
        event_date   DATETIME     NOT NULL,
        event_end    DATETIME     NULL DEFAULT NULL,
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        status       ENUM('pending','published') NOT NULL DEFAULT 'published',
        preview_token VARCHAR(32) NOT NULL DEFAULT '',
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_podcasts' => "CREATE TABLE IF NOT EXISTS cms_podcasts (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        show_id     INT          NOT NULL DEFAULT 1,
        title       VARCHAR(255) NOT NULL,
        slug        VARCHAR(255) NOT NULL,
        description TEXT,
        audio_file  VARCHAR(255) NOT NULL DEFAULT '',
        image_file  VARCHAR(255) NOT NULL DEFAULT '',
        audio_url   VARCHAR(500) NOT NULL DEFAULT '',
        subtitle    VARCHAR(255) NOT NULL DEFAULT '',
        duration    VARCHAR(20)  NOT NULL DEFAULT '',
        episode_num INT          NULL DEFAULT NULL,
        season_num  INT          NULL DEFAULT NULL,
        episode_type ENUM('full','trailer','bonus') NOT NULL DEFAULT 'full',
        explicit_mode ENUM('inherit','no','clean','yes') NOT NULL DEFAULT 'inherit',
        block_from_feed TINYINT(1) NOT NULL DEFAULT 0,
        publish_at  DATETIME     NULL DEFAULT NULL,
        status      ENUM('pending','published') NOT NULL DEFAULT 'published',
        deleted_at  DATETIME     NULL DEFAULT NULL,
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
        meta_title   VARCHAR(160) NOT NULL DEFAULT '',
        meta_description TEXT,
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
        project_url    VARCHAR(255) NOT NULL DEFAULT '',
        release_date   DATE         NULL DEFAULT NULL,
        requirements   TEXT,
        checksum_sha256 CHAR(64)    NOT NULL DEFAULT '',
        series_key     VARCHAR(150) NOT NULL DEFAULT '',
        external_url   VARCHAR(255) NOT NULL DEFAULT '',
        filename       VARCHAR(255) NOT NULL DEFAULT '',
        original_name  VARCHAR(255) NOT NULL DEFAULT '',
        file_size      INT          NOT NULL DEFAULT 0,
        download_count INT          NOT NULL DEFAULT 0,
        is_featured    TINYINT(1)   NOT NULL DEFAULT 0,
        sort_order     INT          NOT NULL DEFAULT 0,
        is_published   TINYINT(1)   NOT NULL DEFAULT 1,
        status         ENUM('pending','published') NOT NULL DEFAULT 'published',
        author_id      INT          NULL DEFAULT NULL,
        deleted_at     DATETIME     NULL DEFAULT NULL,
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_downloads_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_gallery_albums' => "CREATE TABLE IF NOT EXISTS cms_gallery_albums (
        id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        parent_id      INT          DEFAULT NULL,
        name           VARCHAR(255) NOT NULL,
        slug           VARCHAR(255) NOT NULL,
        description    TEXT,
        cover_photo_id INT          DEFAULT NULL,
        deleted_at     DATETIME     NULL DEFAULT NULL,
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_gallery_albums_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_gallery_photos' => "CREATE TABLE IF NOT EXISTS cms_gallery_photos (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        album_id   INT          NOT NULL,
        filename   VARCHAR(255) NOT NULL,
        title      VARCHAR(255) NOT NULL DEFAULT '',
        slug       VARCHAR(255) NOT NULL,
        sort_order INT          NOT NULL DEFAULT 0,
        deleted_at DATETIME     NULL DEFAULT NULL,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_gallery_photos_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_food_cards' => "CREATE TABLE IF NOT EXISTS cms_food_cards (
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
        UNIQUE KEY uq_cms_food_cards_slug (slug)
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
        deleted_at  DATETIME     NULL DEFAULT NULL,
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
        parent_id  INT          NULL DEFAULT NULL,
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
        preview_token  VARCHAR(32)  NOT NULL DEFAULT '',
        deleted_at     DATETIME     NULL DEFAULT NULL,
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

    // ── Formuláře ──

    'cms_forms' => "CREATE TABLE IF NOT EXISTS cms_forms (
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
        is_active   TINYINT(1)   NOT NULL DEFAULT 1,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_forms_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_form_fields' => "CREATE TABLE IF NOT EXISTS cms_form_fields (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_form_submissions' => "CREATE TABLE IF NOT EXISTS cms_form_submissions (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_form_submission_history' => "CREATE TABLE IF NOT EXISTS cms_form_submission_history (
        id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
        submission_id BIGINT       NOT NULL,
        actor_user_id INT          NULL DEFAULT NULL,
        event_type    VARCHAR(50)  NOT NULL DEFAULT 'note',
        message       TEXT         NOT NULL,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_submission_created (submission_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── Revize obsahu ──

    'cms_revisions' => "CREATE TABLE IF NOT EXISTS cms_revisions (
        id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(50)  NOT NULL,
        entity_id   INT          NOT NULL,
        field_name  VARCHAR(100) NOT NULL,
        old_value   LONGTEXT,
        new_value   LONGTEXT,
        user_id     INT          NULL DEFAULT NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_entity (entity_type, entity_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_media' => "CREATE TABLE IF NOT EXISTS cms_media (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_widgets' => "CREATE TABLE IF NOT EXISTS cms_widgets (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        zone        VARCHAR(50)  NOT NULL DEFAULT 'homepage',
        widget_type VARCHAR(50)  NOT NULL,
        title       VARCHAR(255) NOT NULL DEFAULT '',
        settings    JSON,
        sort_order  INT          NOT NULL DEFAULT 0,
        is_active   TINYINT(1)   NOT NULL DEFAULT 1,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_widgets_zone (zone, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_redirects' => "CREATE TABLE IF NOT EXISTS cms_redirects (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        old_path    VARCHAR(500) NOT NULL,
        new_path    VARCHAR(500) NOT NULL,
        status_code SMALLINT     NOT NULL DEFAULT 301,
        hit_count   INT          NOT NULL DEFAULT 0,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_redirects_old_path (old_path(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_content_locks' => "CREATE TABLE IF NOT EXISTS cms_content_locks (
        id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(50)  NOT NULL,
        entity_id   INT          NOT NULL,
        user_id     INT          NOT NULL,
        locked_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at  DATETIME     NOT NULL,
        UNIQUE KEY uq_lock (entity_type, entity_id)
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

$columnExists = static function (string $tableName, string $columnName) use ($pdo): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$tableName, $columnName]);
    return (int)$stmt->fetchColumn() > 0;
};

$contactStatusWasMissing = !$columnExists('cms_contact', 'status');
$chatStatusWasMissing = !$columnExists('cms_chat', 'status');
$chatPublicVisibilityWasMissing = !$columnExists('cms_chat', 'public_visibility');

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
    'cms_news.meta_title'            => "ALTER TABLE cms_news ADD COLUMN meta_title VARCHAR(160) NOT NULL DEFAULT ''",
    'cms_news.meta_description'      => "ALTER TABLE cms_news ADD COLUMN meta_description TEXT",
    'cms_news.publish_at'            => "ALTER TABLE cms_news ADD COLUMN publish_at DATETIME NULL DEFAULT NULL",
    'cms_news.updated_at'            => "ALTER TABLE cms_news ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    'cms_news.preview_token'         => "ALTER TABLE cms_news ADD COLUMN preview_token VARCHAR(32) NOT NULL DEFAULT ''",
    // cms_polls
    'cms_polls.meta_title'           => "ALTER TABLE cms_polls ADD COLUMN meta_title VARCHAR(160) NOT NULL DEFAULT ''",
    'cms_polls.meta_description'     => "ALTER TABLE cms_polls ADD COLUMN meta_description TEXT",
    // cms_chat
    'cms_chat.status'                => "ALTER TABLE cms_chat ADD COLUMN status ENUM('new','read','handled') NOT NULL DEFAULT 'new'",
    'cms_chat.public_visibility'     => "ALTER TABLE cms_chat ADD COLUMN public_visibility ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending'",
    'cms_chat.approved_at'           => "ALTER TABLE cms_chat ADD COLUMN approved_at DATETIME NULL DEFAULT NULL",
    'cms_chat.approved_by_user_id'   => "ALTER TABLE cms_chat ADD COLUMN approved_by_user_id INT NULL DEFAULT NULL",
    'cms_chat.internal_note'         => "ALTER TABLE cms_chat ADD COLUMN internal_note TEXT",
    'cms_chat.replied_at'            => "ALTER TABLE cms_chat ADD COLUMN replied_at DATETIME NULL DEFAULT NULL",
    'cms_chat.replied_by_user_id'    => "ALTER TABLE cms_chat ADD COLUMN replied_by_user_id INT NULL DEFAULT NULL",
    'cms_chat.replied_subject'       => "ALTER TABLE cms_chat ADD COLUMN replied_subject VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_chat.replied_to_email'      => "ALTER TABLE cms_chat ADD COLUMN replied_to_email VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_chat.updated_at'            => "ALTER TABLE cms_chat ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    'cms_chat_history.id'            => "CREATE TABLE IF NOT EXISTS cms_chat_history (
        id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
        chat_id       INT          NOT NULL,
        actor_user_id INT          NULL DEFAULT NULL,
        event_type    VARCHAR(50)  NOT NULL DEFAULT 'workflow',
        message       TEXT         NOT NULL,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // cms_contact
    'cms_contact.status'             => "ALTER TABLE cms_contact ADD COLUMN status ENUM('new','read','handled') NOT NULL DEFAULT 'new'",
    'cms_contact.updated_at'         => "ALTER TABLE cms_contact ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // cms_faq_categories
    'cms_faq_categories.parent_id'   => "ALTER TABLE cms_faq_categories ADD COLUMN parent_id INT NULL DEFAULT NULL",
    // cms_faqs
    'cms_faqs.slug'                  => "ALTER TABLE cms_faqs ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_faqs.excerpt'               => "ALTER TABLE cms_faqs ADD COLUMN excerpt TEXT",
    'cms_faqs.status'                => "ALTER TABLE cms_faqs ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_faqs.meta_title'            => "ALTER TABLE cms_faqs ADD COLUMN meta_title VARCHAR(160) NOT NULL DEFAULT ''",
    'cms_faqs.meta_description'      => "ALTER TABLE cms_faqs ADD COLUMN meta_description TEXT",
    // cms_podcast_shows
    'cms_podcast_shows.subtitle'          => "ALTER TABLE cms_podcast_shows ADD COLUMN subtitle VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_podcast_shows.owner_name'        => "ALTER TABLE cms_podcast_shows ADD COLUMN owner_name VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_podcast_shows.owner_email'       => "ALTER TABLE cms_podcast_shows ADD COLUMN owner_email VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_podcast_shows.explicit_mode'     => "ALTER TABLE cms_podcast_shows ADD COLUMN explicit_mode ENUM('no','clean','yes') NOT NULL DEFAULT 'no'",
    'cms_podcast_shows.show_type'         => "ALTER TABLE cms_podcast_shows ADD COLUMN show_type ENUM('episodic','serial') NOT NULL DEFAULT 'episodic'",
    'cms_podcast_shows.feed_complete'     => "ALTER TABLE cms_podcast_shows ADD COLUMN feed_complete TINYINT(1) NOT NULL DEFAULT 0",
    'cms_podcast_shows.feed_episode_limit'=> "ALTER TABLE cms_podcast_shows ADD COLUMN feed_episode_limit INT NOT NULL DEFAULT 100",
    'cms_podcast_shows.is_published'      => "ALTER TABLE cms_podcast_shows ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1",
    'cms_podcast_shows.status'            => "ALTER TABLE cms_podcast_shows ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_podcasts
    'cms_podcasts.show_id'           => "ALTER TABLE cms_podcasts ADD COLUMN show_id INT NOT NULL DEFAULT 1",
    'cms_podcasts.slug'              => "ALTER TABLE cms_podcasts ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL",
    'cms_podcasts.image_file'        => "ALTER TABLE cms_podcasts ADD COLUMN image_file VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_podcasts.subtitle'          => "ALTER TABLE cms_podcasts ADD COLUMN subtitle VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_podcasts.status'            => "ALTER TABLE cms_podcasts ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_podcasts.season_num'        => "ALTER TABLE cms_podcasts ADD COLUMN season_num INT NULL DEFAULT NULL",
    'cms_podcasts.episode_type'      => "ALTER TABLE cms_podcasts ADD COLUMN episode_type ENUM('full','trailer','bonus') NOT NULL DEFAULT 'full'",
    'cms_podcasts.explicit_mode'     => "ALTER TABLE cms_podcasts ADD COLUMN explicit_mode ENUM('inherit','no','clean','yes') NOT NULL DEFAULT 'inherit'",
    'cms_podcasts.block_from_feed'   => "ALTER TABLE cms_podcasts ADD COLUMN block_from_feed TINYINT(1) NOT NULL DEFAULT 0",
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
    'cms_places.meta_title'          => "ALTER TABLE cms_places ADD COLUMN meta_title VARCHAR(160) NOT NULL DEFAULT ''",
    'cms_places.meta_description'    => "ALTER TABLE cms_places ADD COLUMN meta_description TEXT",
    'cms_places.status'              => "ALTER TABLE cms_places ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_places.updated_at'          => "ALTER TABLE cms_places ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // cms_gallery
    'cms_gallery_albums.slug'        => "ALTER TABLE cms_gallery_albums ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL AFTER name",
    'cms_gallery_albums.status'      => "ALTER TABLE cms_gallery_albums ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_gallery_albums.is_published' => "ALTER TABLE cms_gallery_albums ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1",
    'cms_gallery_photos.slug'        => "ALTER TABLE cms_gallery_photos ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL AFTER title",
    'cms_gallery_photos.status'      => "ALTER TABLE cms_gallery_photos ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_gallery_photos.is_published' => "ALTER TABLE cms_gallery_photos ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1",
    // cms_food_cards
    'cms_food_cards.slug'            => "ALTER TABLE cms_food_cards ADD COLUMN slug VARCHAR(255) NULL DEFAULT NULL AFTER title",
    // cms_blogs
    'cms_blogs.logo_file'            => "ALTER TABLE cms_blogs ADD COLUMN logo_file VARCHAR(255) NOT NULL DEFAULT '' AFTER description",
    'cms_blogs.intro_content'        => "ALTER TABLE cms_blogs ADD COLUMN intro_content TEXT AFTER description",
    'cms_blogs.logo_alt_text'        => "ALTER TABLE cms_blogs ADD COLUMN logo_alt_text VARCHAR(255) NOT NULL DEFAULT '' AFTER logo_file",
    'cms_blogs.meta_title'           => "ALTER TABLE cms_blogs ADD COLUMN meta_title VARCHAR(160) NOT NULL DEFAULT '' AFTER logo_alt_text",
    'cms_blogs.meta_description'     => "ALTER TABLE cms_blogs ADD COLUMN meta_description TEXT AFTER meta_title",
    'cms_blogs.rss_subtitle'         => "ALTER TABLE cms_blogs ADD COLUMN rss_subtitle VARCHAR(255) NOT NULL DEFAULT '' AFTER meta_description",
    'cms_blogs.comments_default'     => "ALTER TABLE cms_blogs ADD COLUMN comments_default TINYINT(1) NOT NULL DEFAULT 1 AFTER rss_subtitle",
    'cms_blogs.feed_item_limit'      => "ALTER TABLE cms_blogs ADD COLUMN feed_item_limit INT NOT NULL DEFAULT 20 AFTER comments_default",
    'cms_blogs.show_in_nav'          => "ALTER TABLE cms_blogs ADD COLUMN show_in_nav TINYINT(1) NOT NULL DEFAULT 1",
    'cms_blogs.created_by_user_id'   => "ALTER TABLE cms_blogs ADD COLUMN created_by_user_id INT NULL DEFAULT NULL AFTER show_in_nav",
    // cms_pages
    'cms_pages.status'               => "ALTER TABLE cms_pages ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_pages.publish_at'           => "ALTER TABLE cms_pages ADD COLUMN publish_at DATETIME NULL DEFAULT NULL",
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
    'cms_downloads.project_url'      => "ALTER TABLE cms_downloads ADD COLUMN project_url VARCHAR(255) NOT NULL DEFAULT '' AFTER license_label",
    'cms_downloads.release_date'     => "ALTER TABLE cms_downloads ADD COLUMN release_date DATE NULL DEFAULT NULL AFTER project_url",
    'cms_downloads.requirements'     => "ALTER TABLE cms_downloads ADD COLUMN requirements TEXT NULL AFTER release_date",
    'cms_downloads.checksum_sha256'  => "ALTER TABLE cms_downloads ADD COLUMN checksum_sha256 CHAR(64) NOT NULL DEFAULT '' AFTER requirements",
    'cms_downloads.series_key'       => "ALTER TABLE cms_downloads ADD COLUMN series_key VARCHAR(150) NOT NULL DEFAULT '' AFTER checksum_sha256",
    'cms_downloads.external_url'     => "ALTER TABLE cms_downloads ADD COLUMN external_url VARCHAR(255) NOT NULL DEFAULT '' AFTER series_key",
    'cms_downloads.download_count'   => "ALTER TABLE cms_downloads ADD COLUMN download_count INT NOT NULL DEFAULT 0 AFTER file_size",
    'cms_downloads.is_featured'      => "ALTER TABLE cms_downloads ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER download_count",
    'cms_downloads.updated_at'       => "ALTER TABLE cms_downloads ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    // cms_media
    'cms_media.caption'              => "ALTER TABLE cms_media ADD COLUMN caption TEXT AFTER alt_text",
    'cms_media.credit'               => "ALTER TABLE cms_media ADD COLUMN credit VARCHAR(255) NOT NULL DEFAULT '' AFTER caption",
    'cms_media.visibility'           => "ALTER TABLE cms_media ADD COLUMN visibility ENUM('public','private') NOT NULL DEFAULT 'public' AFTER credit",
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
    'cms_articles.is_featured_in_blog' => "ALTER TABLE cms_articles ADD COLUMN is_featured_in_blog TINYINT(1) NOT NULL DEFAULT 0",
    // cms_forms
    'cms_forms.submit_label'         => "ALTER TABLE cms_forms ADD COLUMN submit_label VARCHAR(100) NOT NULL DEFAULT 'Odeslat formulář'",
    'cms_forms.notification_email'   => "ALTER TABLE cms_forms ADD COLUMN notification_email VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_forms.notification_subject' => "ALTER TABLE cms_forms ADD COLUMN notification_subject VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_forms.redirect_url'         => "ALTER TABLE cms_forms ADD COLUMN redirect_url VARCHAR(500) NOT NULL DEFAULT ''",
    'cms_forms.success_behavior'     => "ALTER TABLE cms_forms ADD COLUMN success_behavior VARCHAR(20) NOT NULL DEFAULT ''",
    'cms_forms.success_primary_label' => "ALTER TABLE cms_forms ADD COLUMN success_primary_label VARCHAR(120) NOT NULL DEFAULT ''",
    'cms_forms.success_primary_url'  => "ALTER TABLE cms_forms ADD COLUMN success_primary_url VARCHAR(500) NOT NULL DEFAULT ''",
    'cms_forms.success_secondary_label' => "ALTER TABLE cms_forms ADD COLUMN success_secondary_label VARCHAR(120) NOT NULL DEFAULT ''",
    'cms_forms.success_secondary_url' => "ALTER TABLE cms_forms ADD COLUMN success_secondary_url VARCHAR(500) NOT NULL DEFAULT ''",
    'cms_forms.webhook_enabled'     => "ALTER TABLE cms_forms ADD COLUMN webhook_enabled TINYINT(1) NOT NULL DEFAULT 0",
    'cms_forms.webhook_url'         => "ALTER TABLE cms_forms ADD COLUMN webhook_url VARCHAR(500) NOT NULL DEFAULT ''",
    'cms_forms.webhook_secret'      => "ALTER TABLE cms_forms ADD COLUMN webhook_secret VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_forms.webhook_events'      => "ALTER TABLE cms_forms ADD COLUMN webhook_events VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_forms.use_honeypot'         => "ALTER TABLE cms_forms ADD COLUMN use_honeypot TINYINT(1) NOT NULL DEFAULT 1",
    'cms_forms.submitter_confirmation_enabled' => "ALTER TABLE cms_forms ADD COLUMN submitter_confirmation_enabled TINYINT(1) NOT NULL DEFAULT 0",
    'cms_forms.submitter_email_field' => "ALTER TABLE cms_forms ADD COLUMN submitter_email_field VARCHAR(100) NOT NULL DEFAULT ''",
    'cms_forms.submitter_confirmation_subject' => "ALTER TABLE cms_forms ADD COLUMN submitter_confirmation_subject VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_forms.submitter_confirmation_message' => "ALTER TABLE cms_forms ADD COLUMN submitter_confirmation_message TEXT",
    'cms_forms.show_in_nav'          => "ALTER TABLE cms_forms ADD COLUMN show_in_nav TINYINT(1) NOT NULL DEFAULT 0",
    // cms_form_fields
    'cms_form_fields.default_value'  => "ALTER TABLE cms_form_fields ADD COLUMN default_value VARCHAR(500) NOT NULL DEFAULT ''",
    'cms_form_fields.help_text'      => "ALTER TABLE cms_form_fields ADD COLUMN help_text TEXT",
    'cms_form_fields.accept_types'   => "ALTER TABLE cms_form_fields ADD COLUMN accept_types VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_form_fields.max_file_size_mb' => "ALTER TABLE cms_form_fields ADD COLUMN max_file_size_mb INT NOT NULL DEFAULT 10",
    'cms_form_fields.allow_multiple' => "ALTER TABLE cms_form_fields ADD COLUMN allow_multiple TINYINT(1) NOT NULL DEFAULT 0",
    'cms_form_fields.layout_width'   => "ALTER TABLE cms_form_fields ADD COLUMN layout_width VARCHAR(20) NOT NULL DEFAULT 'full'",
    'cms_form_fields.start_new_row'  => "ALTER TABLE cms_form_fields ADD COLUMN start_new_row TINYINT(1) NOT NULL DEFAULT 0",
    'cms_form_fields.show_if_field'  => "ALTER TABLE cms_form_fields ADD COLUMN show_if_field VARCHAR(100) NOT NULL DEFAULT ''",
    'cms_form_fields.show_if_operator' => "ALTER TABLE cms_form_fields ADD COLUMN show_if_operator VARCHAR(20) NOT NULL DEFAULT ''",
    'cms_form_fields.show_if_value'  => "ALTER TABLE cms_form_fields ADD COLUMN show_if_value VARCHAR(255) NOT NULL DEFAULT ''",
    // cms_form_submissions
    'cms_form_submissions.reference_code' => "ALTER TABLE cms_form_submissions ADD COLUMN reference_code VARCHAR(50) NOT NULL DEFAULT ''",
    'cms_form_submissions.status' => "ALTER TABLE cms_form_submissions ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'new'",
    'cms_form_submissions.priority' => "ALTER TABLE cms_form_submissions ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'medium'",
    'cms_form_submissions.labels' => "ALTER TABLE cms_form_submissions ADD COLUMN labels VARCHAR(500) NOT NULL DEFAULT ''",
    'cms_form_submissions.assigned_user_id' => "ALTER TABLE cms_form_submissions ADD COLUMN assigned_user_id INT NULL DEFAULT NULL",
    'cms_form_submissions.internal_note' => "ALTER TABLE cms_form_submissions ADD COLUMN internal_note TEXT",
    'cms_form_submissions.github_issue_repository' => "ALTER TABLE cms_form_submissions ADD COLUMN github_issue_repository VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_form_submissions.github_issue_number' => "ALTER TABLE cms_form_submissions ADD COLUMN github_issue_number INT NULL DEFAULT NULL",
    'cms_form_submissions.github_issue_url' => "ALTER TABLE cms_form_submissions ADD COLUMN github_issue_url VARCHAR(500) NOT NULL DEFAULT ''",
    'cms_form_submissions.updated_at' => "ALTER TABLE cms_form_submissions ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    'cms_form_submission_history.id' => "CREATE TABLE IF NOT EXISTS cms_form_submission_history (
        id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
        submission_id BIGINT       NOT NULL,
        actor_user_id INT          NULL DEFAULT NULL,
        event_type    VARCHAR(50)  NOT NULL DEFAULT 'note',
        message       TEXT         NOT NULL,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_submission_created (submission_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // Multiblog – blog_id sloupce
    'cms_articles.blog_id'           => "ALTER TABLE cms_articles ADD COLUMN blog_id INT NOT NULL DEFAULT 1",
    'cms_categories.blog_id'         => "ALTER TABLE cms_categories ADD COLUMN blog_id INT NOT NULL DEFAULT 1",
    'cms_tags.blog_id'               => "ALTER TABLE cms_tags ADD COLUMN blog_id INT NOT NULL DEFAULT 1",
    // cms_comments – stavový model moderace
    'cms_comments.status'            => "ALTER TABLE cms_comments ADD COLUMN status ENUM('pending','approved','spam','trash') NOT NULL DEFAULT 'pending'",
    // cms_log – user_id pro audit log
    'cms_log.user_id'                => "ALTER TABLE cms_log ADD COLUMN user_id INT NULL DEFAULT NULL",
    // Koš (soft delete)
    'cms_articles.deleted_at'        => "ALTER TABLE cms_articles ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_news.deleted_at'            => "ALTER TABLE cms_news ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_pages.deleted_at'           => "ALTER TABLE cms_pages ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_pages.blog_id'              => "ALTER TABLE cms_pages ADD COLUMN blog_id INT NULL DEFAULT NULL AFTER content",
    'cms_pages.blog_nav_order'       => "ALTER TABLE cms_pages ADD COLUMN blog_nav_order INT NOT NULL DEFAULT 0 AFTER blog_id",
    'cms_events.deleted_at'          => "ALTER TABLE cms_events ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_events.event_kind'          => "ALTER TABLE cms_events ADD COLUMN event_kind VARCHAR(50) NOT NULL DEFAULT 'general'",
    'cms_events.excerpt'             => "ALTER TABLE cms_events ADD COLUMN excerpt TEXT",
    'cms_events.program_note'        => "ALTER TABLE cms_events ADD COLUMN program_note TEXT",
    'cms_events.organizer_name'      => "ALTER TABLE cms_events ADD COLUMN organizer_name VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_events.organizer_email'     => "ALTER TABLE cms_events ADD COLUMN organizer_email VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_events.registration_url'    => "ALTER TABLE cms_events ADD COLUMN registration_url VARCHAR(500) NOT NULL DEFAULT ''",
    'cms_events.price_note'          => "ALTER TABLE cms_events ADD COLUMN price_note VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_events.accessibility_note'  => "ALTER TABLE cms_events ADD COLUMN accessibility_note TEXT",
    'cms_events.image_file'          => "ALTER TABLE cms_events ADD COLUMN image_file VARCHAR(255) NOT NULL DEFAULT ''",
    'cms_faqs.deleted_at'            => "ALTER TABLE cms_faqs ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    // Koš – další moduly
    'cms_board.deleted_at'           => "ALTER TABLE cms_board ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_downloads.deleted_at'       => "ALTER TABLE cms_downloads ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_food_cards.deleted_at'      => "ALTER TABLE cms_food_cards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_podcasts.deleted_at'        => "ALTER TABLE cms_podcasts ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_podcast_shows.deleted_at'   => "ALTER TABLE cms_podcast_shows ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_gallery_albums.deleted_at'  => "ALTER TABLE cms_gallery_albums ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_gallery_photos.deleted_at'  => "ALTER TABLE cms_gallery_photos ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    'cms_polls.deleted_at'           => "ALTER TABLE cms_polls ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL",
    // 2FA
    'cms_users.totp_secret'          => "ALTER TABLE cms_users ADD COLUMN totp_secret VARCHAR(64) NULL DEFAULT NULL",
    'cms_users.passkey_credentials'  => "ALTER TABLE cms_users ADD COLUMN passkey_credentials TEXT",
    // Interní poznámky
    'cms_articles.admin_note'        => "ALTER TABLE cms_articles ADD COLUMN admin_note TEXT",
    'cms_news.admin_note'            => "ALTER TABLE cms_news ADD COLUMN admin_note TEXT",
    'cms_pages.admin_note'           => "ALTER TABLE cms_pages ADD COLUMN admin_note TEXT",
    'cms_events.admin_note'          => "ALTER TABLE cms_events ADD COLUMN admin_note TEXT",
    // Plánované zrušení publikace
    'cms_articles.unpublish_at'      => "ALTER TABLE cms_articles ADD COLUMN unpublish_at DATETIME NULL DEFAULT NULL",
    'cms_news.unpublish_at'          => "ALTER TABLE cms_news ADD COLUMN unpublish_at DATETIME NULL DEFAULT NULL",
    'cms_pages.unpublish_at'         => "ALTER TABLE cms_pages ADD COLUMN unpublish_at DATETIME NULL DEFAULT NULL",
    'cms_pages.preview_token'        => "ALTER TABLE cms_pages ADD COLUMN preview_token VARCHAR(32) NOT NULL DEFAULT ''",
    'cms_events.unpublish_at'        => "ALTER TABLE cms_events ADD COLUMN unpublish_at DATETIME NULL DEFAULT NULL",
    'cms_events.preview_token'       => "ALTER TABLE cms_events ADD COLUMN preview_token VARCHAR(32) NOT NULL DEFAULT ''",
    'cms_board.preview_token'        => "ALTER TABLE cms_board ADD COLUMN preview_token VARCHAR(32) NOT NULL DEFAULT ''",
    // Hierarchické kategorie blogu
    'cms_categories.parent_id'       => "ALTER TABLE cms_categories ADD COLUMN parent_id INT NULL DEFAULT NULL",
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

// ── 4c. Rozšíření ENUM statusu o 'draft' (články, novinky, stránky) ─────────

$draftEnumTables = ['cms_articles', 'cms_news', 'cms_pages'];
foreach ($draftEnumTables as $draftTable) {
    try {
        $colCheck = $pdo->prepare(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'status'"
        );
        $colCheck->execute([$draftTable]);
        $colType = (string)$colCheck->fetchColumn();

        if ($colType !== '' && strpos($colType, "'draft'") === false) {
            $pdo->exec(
                "ALTER TABLE {$draftTable} MODIFY COLUMN status
                 ENUM('draft','pending','published')
                 NOT NULL DEFAULT 'published'"
            );
            $log[] = "✓ ENUM <code>{$draftTable}.status</code> rozšířen o 'draft' – OK";
        } else {
            $log[] = "· ENUM <code>{$draftTable}.status</code> již obsahuje 'draft' – přeskočeno";
        }
    } catch (\PDOException $e) {
        $log[] = "✗ Rozšíření ENUM <code>{$draftTable}.status</code> – CHYBA: " . h($e->getMessage());
    }
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
    $galleryAlbumSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_gallery_albums' AND COLUMN_NAME = 'slug'"
    );
    $galleryAlbumSlugColumnCheck->execute();

    if ((int)$galleryAlbumSlugColumnCheck->fetchColumn() > 0) {
        $galleryAlbumRows = $pdo->query("SELECT id, name, slug FROM cms_gallery_albums ORDER BY id")->fetchAll();
        $updateGalleryAlbumSlugStmt = $pdo->prepare("UPDATE cms_gallery_albums SET slug = ? WHERE id = ?");

        foreach ($galleryAlbumRows as $galleryAlbumRow) {
            $existingSlug = trim((string)($galleryAlbumRow['slug'] ?? ''));
            $resolvedSlug = uniqueGalleryAlbumSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : (string)$galleryAlbumRow['name'],
                (int)$galleryAlbumRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updateGalleryAlbumSlugStmt->execute([$resolvedSlug, (int)$galleryAlbumRow['id']]);
            }
        }

        $galleryAlbumSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_gallery_albums' AND COLUMN_NAME = 'slug'"
        );
        $galleryAlbumSlugNullabilityCheck->execute();
        if (($galleryAlbumSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_gallery_albums MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_gallery_albums.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_gallery_albums.slug</code> už je NOT NULL – přeskočeno";
        }

        $galleryAlbumSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_gallery_albums'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $galleryAlbumSlugIndexCheck->execute();
        if ((int)$galleryAlbumSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_gallery_albums ADD UNIQUE KEY uq_cms_gallery_albums_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_gallery_albums_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_gallery_albums.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy galerií – sloupec <code>cms_gallery_albums.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy galerií – CHYBA: " . h($e->getMessage());
}

try {
    $galleryPhotoSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_gallery_photos' AND COLUMN_NAME = 'slug'"
    );
    $galleryPhotoSlugColumnCheck->execute();

    if ((int)$galleryPhotoSlugColumnCheck->fetchColumn() > 0) {
        $galleryPhotoRows = $pdo->query("SELECT id, title, filename, slug FROM cms_gallery_photos ORDER BY id")->fetchAll();
        $updateGalleryPhotoSlugStmt = $pdo->prepare("UPDATE cms_gallery_photos SET slug = ? WHERE id = ?");

        foreach ($galleryPhotoRows as $galleryPhotoRow) {
            $existingSlug = trim((string)($galleryPhotoRow['slug'] ?? ''));
            $slugCandidate = $existingSlug !== ''
                ? $existingSlug
                : ((string)($galleryPhotoRow['title'] ?? '') !== ''
                    ? (string)$galleryPhotoRow['title']
                    : pathinfo((string)($galleryPhotoRow['filename'] ?? ''), PATHINFO_FILENAME));
            $resolvedSlug = uniqueGalleryPhotoSlug(
                $pdo,
                $slugCandidate,
                (int)$galleryPhotoRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updateGalleryPhotoSlugStmt->execute([$resolvedSlug, (int)$galleryPhotoRow['id']]);
            }
        }

        $galleryPhotoSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_gallery_photos' AND COLUMN_NAME = 'slug'"
        );
        $galleryPhotoSlugNullabilityCheck->execute();
        if (($galleryPhotoSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_gallery_photos MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_gallery_photos.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_gallery_photos.slug</code> už je NOT NULL – přeskočeno";
        }

        $galleryPhotoSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_gallery_photos'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $galleryPhotoSlugIndexCheck->execute();
        if ((int)$galleryPhotoSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_gallery_photos ADD UNIQUE KEY uq_cms_gallery_photos_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_gallery_photos_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_gallery_photos.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy fotografií – sloupec <code>cms_gallery_photos.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy fotografií – CHYBA: " . h($e->getMessage());
}

try {
    $foodSlugColumnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_food_cards' AND COLUMN_NAME = 'slug'"
    );
    $foodSlugColumnCheck->execute();

    if ((int)$foodSlugColumnCheck->fetchColumn() > 0) {
        $foodRows = $pdo->query("SELECT id, title, slug FROM cms_food_cards ORDER BY id")->fetchAll();
        $updateFoodSlugStmt = $pdo->prepare("UPDATE cms_food_cards SET slug = ? WHERE id = ?");

        foreach ($foodRows as $foodRow) {
            $existingSlug = trim((string)($foodRow['slug'] ?? ''));
            $resolvedSlug = uniqueFoodCardSlug(
                $pdo,
                $existingSlug !== '' ? $existingSlug : (string)$foodRow['title'],
                (int)$foodRow['id']
            );
            if ($existingSlug !== $resolvedSlug) {
                $updateFoodSlugStmt->execute([$resolvedSlug, (int)$foodRow['id']]);
            }
        }

        $foodSlugNullabilityCheck = $pdo->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_food_cards' AND COLUMN_NAME = 'slug'"
        );
        $foodSlugNullabilityCheck->execute();
        if (($foodSlugNullabilityCheck->fetchColumn() ?? 'NO') === 'YES') {
            $pdo->exec("ALTER TABLE cms_food_cards MODIFY slug VARCHAR(255) NOT NULL");
            $log[] = "✓ Sloupec <code>cms_food_cards.slug</code> je nyní NOT NULL – OK";
        } else {
            $log[] = "· Sloupec <code>cms_food_cards.slug</code> už je NOT NULL – přeskočeno";
        }

        $foodSlugIndexCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_food_cards'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $foodSlugIndexCheck->execute();
        if ((int)$foodSlugIndexCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cms_food_cards ADD UNIQUE KEY uq_cms_food_cards_slug (slug)");
            $log[] = "✓ Unikátní index <code>uq_cms_food_cards_slug</code> přidán – OK";
        } else {
            $log[] = "· Unikátní index pro <code>cms_food_cards.slug</code> již existuje – přeskočeno";
        }
    } else {
        $log[] = "· Slugy jídelních lístků – sloupec <code>cms_food_cards.slug</code> neexistuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Slugy jídelních lístků – CHYBA: " . h($e->getMessage());
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
    'module_forms'            => '0',
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
    'blog_authors_index_enabled' => '0',
    'public_registration_enabled' => '1',
    'comments_enabled'        => '1',
    'comment_moderation_mode' => 'always',
    'comment_close_days'      => '0',
    'comment_notify_admin'    => '1',
    'comment_notify_author_approve' => '0',
    'comment_notify_email'    => '',
    'github_issues_enabled'   => '0',
    'github_issues_repository' => '',
    'notify_form_submission'  => '1',
    'notify_pending_content'  => '1',
    'notify_chat_message'     => '0',
    'chat_retention_days'     => '0',
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
    'site_profile'            => 'custom',
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

try {
    if ($contactStatusWasMissing) {
        $updatedContacts = $pdo->exec(
            "UPDATE cms_contact
             SET status = CASE
                 WHEN status = 'handled' THEN 'handled'
                 WHEN is_read = 1 THEN 'read'
                 ELSE 'new'
             END"
        );
        $log[] = "✓ Stav kontaktů sjednocen podle existujících dat – OK ({$updatedContacts} záznamů)";
    } else {
        $log[] = "· Migrace stavů kontaktů – stavový model již existuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Migrace stavů kontaktů – CHYBA: " . h($e->getMessage());
}

try {
    if ($chatStatusWasMissing) {
        $updatedChat = $pdo->exec(
            "UPDATE cms_chat
             SET status = 'read'
             WHERE status = 'new'"
        );
        $log[] = "✓ Stav chat zpráv sjednocen pro starší instalace – OK ({$updatedChat} záznamů)";
    } else {
        $log[] = "· Migrace stavů chat zpráv – stavový model již existuje, přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Migrace stavů chat zpráv – CHYBA: " . h($e->getMessage());
}

try {
    if ($chatPublicVisibilityWasMissing) {
        $approvedChat = $pdo->exec(
            "UPDATE cms_chat
             SET public_visibility = 'approved',
                 approved_at = COALESCE(approved_at, created_at)
             WHERE public_visibility <> 'approved' OR approved_at IS NULL"
        );
        $log[] = "✓ Veřejná viditelnost chat zpráv nastavena pro starší instalace – OK ({$approvedChat} záznamů)";
    } else {
        $syncedApprovedChat = $pdo->exec(
            "UPDATE cms_chat
             SET approved_at = COALESCE(approved_at, created_at)
             WHERE public_visibility = 'approved' AND approved_at IS NULL"
        );
        if ($syncedApprovedChat > 0) {
            $log[] = "✓ Doplněno datum schválení u {$syncedApprovedChat} chat zpráv";
        } else {
            $log[] = "· Migrace veřejné viditelnosti chat zpráv – stavový model již existuje, přeskočeno";
        }
    }
} catch (\PDOException $e) {
    $log[] = "✗ Migrace veřejné viditelnosti chat zpráv – CHYBA: " . h($e->getMessage());
}

try {
    $formSubmissionRows = $pdo->query(
        "SELECT fs.id, fs.form_id, fs.reference_code, fs.status, fs.priority, fs.labels, fs.data, fs.created_at,
                f.title AS form_title, f.slug AS form_slug
         FROM cms_form_submissions fs
         LEFT JOIN cms_forms f ON f.id = fs.form_id
         ORDER BY fs.id"
    )->fetchAll();

    $updateFormSubmissionStmt = $pdo->prepare(
        "UPDATE cms_form_submissions
         SET reference_code = ?, status = ?, priority = ?, labels = ?
         WHERE id = ?"
    );
    $normalizedSubmissionCount = 0;
    $historySeededCount = 0;

    foreach ($formSubmissionRows as $formSubmissionRow) {
        $formMeta = [
            'title' => (string)($formSubmissionRow['form_title'] ?? ''),
            'slug' => (string)($formSubmissionRow['form_slug'] ?? ''),
        ];
        $resolvedReference = trim((string)($formSubmissionRow['reference_code'] ?? ''));
        if ($resolvedReference === '') {
            $resolvedReference = formSubmissionBuildReference(
                $formMeta,
                (int)$formSubmissionRow['id'],
                (string)($formSubmissionRow['created_at'] ?? '')
            );
        }

        $resolvedStatus = normalizeFormSubmissionStatus((string)($formSubmissionRow['status'] ?? 'new'));
        $submissionData = json_decode((string)($formSubmissionRow['data'] ?? ''), true) ?: [];
        $resolvedPriority = normalizeFormSubmissionPriority((string)($formSubmissionRow['priority'] ?? ''));
        if (trim((string)($formSubmissionRow['priority'] ?? '')) === '') {
            $resolvedPriority = formSubmissionInferPriority([], $submissionData);
        }
        $resolvedLabels = formSubmissionNormalizeLabels((string)($formSubmissionRow['labels'] ?? ''));
        if (
            $resolvedReference !== (string)($formSubmissionRow['reference_code'] ?? '')
            || $resolvedStatus !== (string)($formSubmissionRow['status'] ?? 'new')
            || $resolvedPriority !== normalizeFormSubmissionPriority((string)($formSubmissionRow['priority'] ?? ''))
            || $resolvedLabels !== (string)($formSubmissionRow['labels'] ?? '')
        ) {
            $updateFormSubmissionStmt->execute([
                $resolvedReference,
                $resolvedStatus,
                $resolvedPriority,
                $resolvedLabels,
                (int)$formSubmissionRow['id'],
            ]);
            $normalizedSubmissionCount++;
        }

        $historyCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_form_submission_history WHERE submission_id = ?");
        $historyCheckStmt->execute([(int)$formSubmissionRow['id']]);
        if ((int)$historyCheckStmt->fetchColumn() === 0) {
            formSubmissionHistoryCreate(
                $pdo,
                (int)$formSubmissionRow['id'],
                null,
                'created',
                'Historie byla inicializována pro dříve uloženou odpověď formuláře.'
            );
            $historySeededCount++;
        }
    }

    $formSubmissionStatusIndexCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'cms_form_submissions'
           AND INDEX_NAME = 'idx_form_status'"
    );
    $formSubmissionStatusIndexCheck->execute();
    if ((int)$formSubmissionStatusIndexCheck->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE cms_form_submissions ADD INDEX idx_form_status (form_id, status, created_at)");
        $log[] = "✓ Index <code>idx_form_status</code> pro odpovědi formulářů přidán – OK";
    } else {
        $log[] = "· Index <code>idx_form_status</code> pro odpovědi formulářů již existuje – přeskočeno";
    }

    $formSubmissionAssigneeIndexCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'cms_form_submissions'
           AND INDEX_NAME = 'idx_form_assignee'"
    );
    $formSubmissionAssigneeIndexCheck->execute();
    if ((int)$formSubmissionAssigneeIndexCheck->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE cms_form_submissions ADD INDEX idx_form_assignee (assigned_user_id, status)");
        $log[] = "✓ Index <code>idx_form_assignee</code> pro odpovědi formulářů přidán – OK";
    } else {
        $log[] = "· Index <code>idx_form_assignee</code> pro odpovědi formulářů již existuje – přeskočeno";
    }

    $log[] = "✓ Workflow odpovědí formulářů sjednocen – OK ({$normalizedSubmissionCount} upravených záznamů, {$historySeededCount} inicializovaných historií)";
} catch (\PDOException $e) {
    $log[] = "✗ Workflow odpovědí formulářů – CHYBA: " . h($e->getMessage());
}

// ── 6. Widgety – migrace existujících homepage nastavení ─────────────────────

try {
    $widgetCount = (int)$pdo->query("SELECT COUNT(*) FROM cms_widgets")->fetchColumn();
    if ($widgetCount === 0) {
        $wOrder = 1;
        $wInsert = $pdo->prepare("INSERT INTO cms_widgets (zone, widget_type, title, settings, sort_order) VALUES ('homepage', ?, ?, ?, ?)");

        $homeIntro = trim(getSetting('home_intro', ''));
        if ($homeIntro !== '') {
            $wInsert->execute(['intro', 'Úvodní text', json_encode(['text' => $homeIntro]), $wOrder++]);
        }

        $featuredModule = getSetting('home_featured_module', 'auto');
        if ($featuredModule === '') { $featuredModule = 'auto'; }
        if ($featuredModule === 'auto') { $featuredModule = 'blog'; }
        if ($featuredModule !== 'none' && isModuleEnabled($featuredModule === 'blog' ? 'blog' : ($featuredModule === 'board' ? 'board' : ($featuredModule === 'poll' ? 'polls' : 'newsletter')))) {
            $wInsert->execute(['featured_article', 'Doporučený obsah', json_encode(['source' => $featuredModule]), $wOrder++]);
        }

        $homeNewsCount = (int)getSetting('home_news_count', '5');
        if ($homeNewsCount > 0 && isModuleEnabled('news')) {
            $wInsert->execute(['latest_news', 'Nejnovější novinky', json_encode(['count' => $homeNewsCount]), $wOrder++]);
        }

        $homeBlogCount = (int)getSetting('home_blog_count', '5');
        if ($homeBlogCount > 0 && isModuleEnabled('blog')) {
            $wInsert->execute(['latest_articles', 'Nejnovější články', json_encode(['count' => $homeBlogCount]), $wOrder++]);
        }

        $homeBoardCount = (int)getSetting('home_board_count', '5');
        if ($homeBoardCount > 0 && isModuleEnabled('board')) {
            $wInsert->execute(['board', boardModulePublicLabel(), json_encode(['count' => $homeBoardCount]), $wOrder++]);
        }

        if (isModuleEnabled('polls')) {
            $wInsert->execute(['poll', 'Aktuální anketa', '{}', $wOrder++]);
        }

        if (isModuleEnabled('newsletter')) {
            $wInsert->execute(['newsletter', 'Newsletter', '{}', $wOrder++]);
        }

        $log[] = '· Vytvořeno ' . ($wOrder - 1) . ' výchozích widgetů z aktuálního nastavení homepage';
    }
} catch (\PDOException $e) {
    $log[] = '· Widgety – přeskočeno: ' . h($e->getMessage());
}

try {
    $homeIntro = trim(getSetting('home_intro', ''));
    if ($homeIntro !== '') {
        $introWidgets = $pdo->query(
            "SELECT id, zone, sort_order, settings
             FROM cms_widgets
             WHERE widget_type = 'intro'
             ORDER BY sort_order, id"
        )->fetchAll();

        if ($introWidgets === []) {
            $homepageIntroSortOrder = (int)$pdo->query(
                "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cms_widgets WHERE zone = 'homepage'"
            )->fetchColumn();
            $insertIntroWidget = $pdo->prepare(
                "INSERT INTO cms_widgets (zone, widget_type, title, settings, sort_order)
                 VALUES ('homepage', 'intro', 'Úvodní text', ?, ?)"
            );
            $insertIntroWidget->execute([
                json_encode(['content' => $homeIntro], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $homepageIntroSortOrder,
            ]);
            $log[] = '· Legacy úvodní text domovské stránky byl převeden do nového intro widgetu na homepage';
        } else {
            $updateIntroWidget = $pdo->prepare("UPDATE cms_widgets SET settings = ? WHERE id = ?");
            $updatedIntroWidgets = 0;
            foreach ($introWidgets as $introWidget) {
                $introSettings = json_decode((string)($introWidget['settings'] ?? '{}'), true);
                if (!is_array($introSettings)) {
                    $introSettings = [];
                }
                $introContent = trim((string)($introSettings['content'] ?? ($introSettings['text'] ?? '')));
                if ($introContent !== '') {
                    continue;
                }
                $introSettings['content'] = $homeIntro;
                unset($introSettings['text']);
                $updateIntroWidget->execute([
                    json_encode($introSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    (int)$introWidget['id'],
                ]);
                $updatedIntroWidgets++;
            }
            if ($updatedIntroWidgets > 0) {
                $log[] = '· Legacy úvodní text domovské stránky byl doplněn do prázdných intro widgetů';
            }
        }
    }
} catch (\PDOException $e) {
    $log[] = '· Převod legacy úvodního textu do widgetu – přeskočeno: ' . h($e->getMessage());
}

try {
    if (getSetting('visitor_counter_enabled', '0') === '1') {
        $hasVisitorStatsWidget = (int)$pdo->query(
            "SELECT COUNT(*) FROM cms_widgets WHERE widget_type = 'visitor_stats'"
        )->fetchColumn() > 0;

        if (!$hasVisitorStatsWidget) {
            $footerSortOrder = (int)$pdo->query(
                "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cms_widgets WHERE zone = 'footer'"
            )->fetchColumn();

            $insertVisitorStatsWidget = $pdo->prepare(
                "INSERT INTO cms_widgets (zone, widget_type, title, settings, sort_order)
                 VALUES ('footer', 'visitor_stats', 'Statistiky návštěvnosti', '{}', ?)"
            );
            $insertVisitorStatsWidget->execute([$footerSortOrder]);
            $log[] = '· Ve footer zóně byl doplněn widget „Statistiky návštěvnosti“ pro dříve zapnuté veřejné počítadlo';
        }
    }
} catch (\PDOException $e) {
    $log[] = '· Veřejné statistiky – přeskočeno: ' . h($e->getMessage());
}

// ── 7. Multiblog – výchozí blog a přeindexování ──────────────────────────────

try {
    $hasSocialLinksWidget = (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_widgets WHERE widget_type = 'social_links'"
    )->fetchColumn() > 0;

    if (!$hasSocialLinksWidget) {
        $socialLinksSettings = [];
        foreach ([
            'social_facebook',
            'social_youtube',
            'social_instagram',
            'social_twitter',
        ] as $socialSettingKey) {
            $rawSocialUrl = trim(getSetting($socialSettingKey, ''));
            if ($rawSocialUrl === '') {
                continue;
            }

            if (!preg_match('#^https?://#i', $rawSocialUrl)) {
                $rawSocialUrl = 'https://' . ltrim($rawSocialUrl, '/');
            }

            $validatedSocialUrl = filter_var($rawSocialUrl, FILTER_VALIDATE_URL);
            if (!is_string($validatedSocialUrl) || !preg_match('#^https?://#i', $validatedSocialUrl)) {
                continue;
            }

            $socialLinksSettings[$socialSettingKey] = $validatedSocialUrl;
        }

        if ($socialLinksSettings !== []) {
            $footerSortOrder = (int)$pdo->query(
                "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cms_widgets WHERE zone = 'footer'"
            )->fetchColumn();

            $insertSocialLinksWidget = $pdo->prepare(
                "INSERT INTO cms_widgets (zone, widget_type, title, settings, sort_order)
                 VALUES ('footer', 'social_links', 'Sociální sítě', ?, ?)"
            );
            $insertSocialLinksWidget->execute([
                json_encode($socialLinksSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $footerSortOrder,
            ]);
            $log[] = '· Ve footer zóně byl doplněn widget „Sociální sítě“ z původních odkazů uložených v nastavení webu';
        }
    }
} catch (\PDOException $e) {
    $log[] = '· Sociální sítě – přeskočeno: ' . h($e->getMessage());
}

try {
    $repairSocialLinksWidgetTitle = $pdo->prepare(
        "UPDATE cms_widgets
         SET title = 'Sociální sítě'
         WHERE widget_type = 'social_links'
           AND title = 'SociĂˇlnĂ­ sĂ­tÄ›'"
    );
    $repairSocialLinksWidgetTitle->execute();
    if ($repairSocialLinksWidgetTitle->rowCount() > 0) {
        $log[] = '· U stávajících widgetů sociálních sítí byl opraven zkomolený výchozí název';
    }
} catch (\PDOException $e) {
    $log[] = '· Oprava názvu widgetu sociálních sítí – přeskočeno: ' . h($e->getMessage());
}

try {
    $hasFooterSearchWidget = (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_widgets WHERE widget_type = 'search' AND zone = 'footer'"
    )->fetchColumn() > 0;

    if (!$hasFooterSearchWidget) {
        $footerSortOrder = (int)$pdo->query(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cms_widgets WHERE zone = 'footer'"
        )->fetchColumn();

        $insertSearchWidget = $pdo->prepare(
            "INSERT INTO cms_widgets (zone, widget_type, title, settings, sort_order)
             VALUES ('footer', 'search', 'Vyhledávání', ?, ?)"
        );
        $insertSearchWidget->execute([
            json_encode([
                'cta_text' => 'Najděte články, novinky, stránky a další obsah napříč celým webem.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $footerSortOrder,
        ]);
        $log[] = '· Ve footer zóně byl doplněn widget „Vyhledávání“ místo dřívějšího natvrdo vloženého odkazu';
    }
} catch (\PDOException $e) {
    $log[] = '· Vyhledávací widget ve footeru – přeskočeno: ' . h($e->getMessage());
}

try {
    if (isModuleEnabled('newsletter')) {
        $hasFooterNewsletterWidget = (int)$pdo->query(
            "SELECT COUNT(*) FROM cms_widgets WHERE widget_type = 'newsletter' AND zone = 'footer'"
        )->fetchColumn() > 0;

        if (!$hasFooterNewsletterWidget) {
            $footerSortOrder = (int)$pdo->query(
                "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cms_widgets WHERE zone = 'footer'"
            )->fetchColumn();

            $insertNewsletterWidget = $pdo->prepare(
                "INSERT INTO cms_widgets (zone, widget_type, title, settings, sort_order)
                 VALUES ('footer', 'newsletter', 'Odběr novinek', ?, ?)"
            );
            $insertNewsletterWidget->execute([
                json_encode([
                    'cta_text' => 'Získejte novinky z webu přímo do e-mailu. Po odeslání formuláře odběr potvrdíte kliknutím na odkaz v potvrzovacím e-mailu.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $footerSortOrder,
            ]);
            $log[] = '· Ve footer zóně byl doplněn widget „Odběr novinek“ místo dřívějšího natvrdo vloženého odkazu';
        }
    }
} catch (\PDOException $e) {
    $log[] = '· Newsletter widget ve footeru – přeskočeno: ' . h($e->getMessage());
}

try {
    $blogCount = (int)$pdo->query("SELECT COUNT(*) FROM cms_blogs")->fetchColumn();
    if ($blogCount === 0) {
        $pdo->exec("INSERT INTO cms_blogs (id, name, slug, sort_order) VALUES (1, 'Blog', 'blog', 0)");
        $log[] = '· Vytvořen výchozí blog „Blog" (slug: blog)';
    }
} catch (\PDOException $e) {
    $log[] = '· Tabulka cms_blogs – přeskočeno: ' . h($e->getMessage());
}

// Přeindexování: slug unikátní v rámci blogu (ne globálně)
try {
    $idxCheck = $pdo->query("SHOW INDEX FROM cms_articles WHERE Key_name = 'uq_articles_blog_slug'")->fetch();
    if (!$idxCheck) {
        try { $pdo->exec("ALTER TABLE cms_articles DROP INDEX slug"); } catch (\PDOException $e) {}
        try { $pdo->exec("ALTER TABLE cms_articles DROP INDEX uq_cms_articles_slug"); } catch (\PDOException $e) {}
        $pdo->exec("ALTER TABLE cms_articles ADD UNIQUE KEY uq_articles_blog_slug (blog_id, slug)");
        $log[] = '· cms_articles: UNIQUE index změněn na (blog_id, slug)';
    }
} catch (\PDOException $e) {
    $log[] = '· cms_articles index – přeskočeno: ' . h($e->getMessage());
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM cms_tags WHERE Key_name = 'uq_tags_blog_slug'")->fetch();
    if (!$idxCheck) {
        try { $pdo->exec("ALTER TABLE cms_tags DROP INDEX slug"); } catch (\PDOException $e) {}
        try { $pdo->exec("ALTER TABLE cms_tags DROP INDEX uq_cms_tags_slug"); } catch (\PDOException $e) {}
        $pdo->exec("ALTER TABLE cms_tags ADD UNIQUE KEY uq_tags_blog_slug (blog_id, slug)");
        $log[] = '· cms_tags: UNIQUE index změněn na (blog_id, slug)';
    }
} catch (\PDOException $e) {
    $log[] = '· cms_tags index – přeskočeno: ' . h($e->getMessage());
}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM cms_articles WHERE Key_name = 'idx_articles_blog_id'")->fetch();
    if (!$idxCheck) {
        $pdo->exec("ALTER TABLE cms_articles ADD INDEX idx_articles_blog_id (blog_id)");
        $log[] = '· cms_articles: přidán index idx_articles_blog_id';
    }
} catch (\PDOException $e) {}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM cms_categories WHERE Key_name = 'idx_categories_blog_id'")->fetch();
    if (!$idxCheck) {
        $pdo->exec("ALTER TABLE cms_categories ADD INDEX idx_categories_blog_id (blog_id)");
        $log[] = '· cms_categories: přidán index idx_categories_blog_id';
    }
} catch (\PDOException $e) {}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM cms_tags WHERE Key_name = 'idx_tags_blog_id'")->fetch();
    if (!$idxCheck) {
        $pdo->exec("ALTER TABLE cms_tags ADD INDEX idx_tags_blog_id (blog_id)");
        $log[] = '· cms_tags: přidán index idx_tags_blog_id';
    }
} catch (\PDOException $e) {}

try {
    $idxCheck = $pdo->query("SHOW INDEX FROM cms_pages WHERE Key_name = 'idx_pages_blog_nav'")->fetch();
    if (!$idxCheck) {
        $pdo->exec("ALTER TABLE cms_pages ADD INDEX idx_pages_blog_nav (blog_id, blog_nav_order)");
        $log[] = '· cms_pages: přidán index idx_pages_blog_nav';
    }
} catch (\PDOException $e) {}

// ── 7. FULLTEXT indexy pro vyhledávání ────────────────────────────────────────

$indexExists = static function (string $tableName, string $indexName) use ($pdo): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->execute([$tableName, $indexName]);
    return (int)$stmt->fetchColumn() > 0;
};

try {
    if (!$indexExists('cms_media', 'idx_media_visibility')) {
        $pdo->exec("ALTER TABLE cms_media ADD INDEX idx_media_visibility (visibility)");
        $log[] = "✓ Index <code>idx_media_visibility</code> pro knihovnu médií přidán – OK";
    } else {
        $log[] = "· Index <code>idx_media_visibility</code> pro knihovnu médií již existuje – přeskočeno";
    }
} catch (\PDOException $e) {
    $log[] = "✗ Index <code>idx_media_visibility</code> pro knihovnu médií – CHYBA: " . h($e->getMessage());
}

$fulltextIndexes = [
    ['cms_articles',   'ft_articles_search',   '(title, perex, content)'],
    ['cms_news',       'ft_news_search',       '(title, content)'],
    ['cms_pages',      'ft_pages_search',      '(title, content)'],
    ['cms_events',     'ft_events_search',     '(title, excerpt, description, program_note, location, organizer_name)'],
    ['cms_faqs',       'ft_faqs_search',       '(question, excerpt, answer)'],
    ['cms_board',      'ft_board_search',      '(title, excerpt, description)'],
    ['cms_downloads',  'ft_downloads_search',  '(title, excerpt, description)'],
    ['cms_places',     'ft_places_search',     '(name, excerpt, description)'],
    ['cms_polls',      'ft_polls_search',      '(question, description)'],
    ['cms_food_cards', 'ft_food_search',       '(title, description, content)'],
];

foreach ($fulltextIndexes as [$ftTable, $ftIndex, $ftColumns]) {
    try {
        if ($indexExists($ftTable, $ftIndex)) {
            $log[] = "· FULLTEXT index <code>{$ftIndex}</code> již existuje – přeskočeno";
            continue;
        }
        $pdo->exec("ALTER TABLE {$ftTable} ADD FULLTEXT INDEX {$ftIndex} {$ftColumns}");
        $log[] = "✓ FULLTEXT index <code>{$ftIndex}</code> přidán – OK";
    } catch (\PDOException $e) {
        $log[] = "✗ FULLTEXT index <code>{$ftIndex}</code> – CHYBA: " . h($e->getMessage());
    }
}

// Přesun citlivých souborů do privátního úložiště mimo webroot
try {
    try {
        $eventFtColumns = $pdo->query(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'cms_events'
               AND INDEX_NAME = 'ft_events_search'
             ORDER BY SEQ_IN_INDEX"
        )->fetchAll(PDO::FETCH_COLUMN);
        $expectedEventFtColumns = ['title', 'excerpt', 'description', 'program_note', 'location', 'organizer_name'];

        if ($eventFtColumns !== [] && $eventFtColumns !== $expectedEventFtColumns) {
            $pdo->exec("ALTER TABLE cms_events DROP INDEX ft_events_search");
            $pdo->exec("ALTER TABLE cms_events ADD FULLTEXT INDEX ft_events_search (title, excerpt, description, program_note, location, organizer_name)");
            $log[] = '✅ FULLTEXT index <code>ft_events_search</code> aktualizován na nové sloupce pro vyhledávání událostí – OK';
        }
    } catch (\PDOException $e) {
        $log[] = '✗ FULLTEXT index <code>ft_events_search</code> – CHYBA při aktualizaci: ' . h($e->getMessage());
    }

    $legacyFormDir = __DIR__ . '/uploads/forms/';
    $privateFormDir = rtrim(koraStoragePath('forms'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $movedFormFiles = 0;

    if (is_dir($legacyFormDir) && koraEnsureDirectory($privateFormDir)) {
        foreach (glob($legacyFormDir . '*') ?: [] as $legacyFormFile) {
            if (!is_file($legacyFormFile)) {
                continue;
            }

            $targetFile = $privateFormDir . basename($legacyFormFile);
            if (is_file($targetFile)) {
                if (@unlink($legacyFormFile)) {
                    $movedFormFiles++;
                }
                continue;
            }

            if (@rename($legacyFormFile, $targetFile) || (@copy($legacyFormFile, $targetFile) && @unlink($legacyFormFile))) {
                $movedFormFiles++;
            }
        }
    }

    if ($movedFormFiles > 0) {
        $log[] = "✓ Přesunuto {$movedFormFiles} příloh formulářů do privátního úložiště – OK";
    } else {
        $log[] = '· Přílohy formulářů již používají privátní úložiště – přeskočeno';
    }
} catch (\Throwable $e) {
    $log[] = '✗ Přesun příloh formulářů – CHYBA: ' . h($e->getMessage());
}

try {
    $legacyBackupDir = __DIR__ . '/uploads/backups/';
    $privateBackupDir = rtrim(koraStoragePath('backups'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $movedBackupFiles = 0;

    if (is_dir($legacyBackupDir) && koraEnsureDirectory($privateBackupDir)) {
        foreach (glob($legacyBackupDir . '*') ?: [] as $legacyBackupFile) {
            if (!is_file($legacyBackupFile)) {
                continue;
            }

            $targetFile = $privateBackupDir . basename($legacyBackupFile);
            if (is_file($targetFile)) {
                if (@unlink($legacyBackupFile)) {
                    $movedBackupFiles++;
                }
                continue;
            }

            if (@rename($legacyBackupFile, $targetFile) || (@copy($legacyBackupFile, $targetFile) && @unlink($legacyBackupFile))) {
                $movedBackupFiles++;
            }
        }
    }

    if ($movedBackupFiles > 0) {
        $log[] = "✓ Přesunuto {$movedBackupFiles} SQL záloh do privátního úložiště – OK";
    } else {
        $log[] = '· SQL zálohy již používají privátní úložiště – přeskočeno';
    }
} catch (\Throwable $e) {
    $log[] = '✗ Přesun SQL záloh – CHYBA: ' . h($e->getMessage());
}

// ── Uložení verze migrace ───────────────────────────────────────────────────
try {
    $pdo->prepare(
        "INSERT INTO cms_settings (`key`, value) VALUES ('migration_version', ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)"
    )->execute([$migrationTargetVersion]);
    $log[] = '✓ Verze migrace nastavena na ' . h($migrationTargetVersion);
} catch (\Throwable $e) {
    $log[] = '✗ Uložení verze migrace – CHYBA: ' . h($e->getMessage());
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
