<?php
/**
 * migrate.php – bezpečná aktualizace databáze
 * Vytvoří chybějící tabulky a doplní chybějící sloupce i nastavení.
 * Existující data ani nastavení NEPŘEPISUJE.
 * Po dokončení tento soubor smažte nebo přejmenujte.
 */
require_once __DIR__ . '/db.php';

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
        perex            TEXT,
        content          TEXT,
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
        id         INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
        content    TEXT     NOT NULL,
        author_id  INT      NULL DEFAULT NULL,
        status     ENUM('pending','published') NOT NULL DEFAULT 'published',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
        id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email         VARCHAR(255) NOT NULL UNIQUE,
        password      VARCHAR(255) NOT NULL,
        first_name    VARCHAR(100) NOT NULL DEFAULT '',
        last_name     VARCHAR(100) NOT NULL DEFAULT '',
        nickname      VARCHAR(100) NOT NULL DEFAULT '',
        is_superadmin TINYINT(1)   NOT NULL DEFAULT 0,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
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
        description  TEXT,
        location     VARCHAR(255) NOT NULL DEFAULT '',
        event_date   DATETIME     NOT NULL,
        event_end    DATETIME     NULL DEFAULT NULL,
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        status       ENUM('pending','published') NOT NULL DEFAULT 'published',
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
        description TEXT,
        audio_file  VARCHAR(255) NOT NULL DEFAULT '',
        audio_url   VARCHAR(500) NOT NULL DEFAULT '',
        duration    VARCHAR(20)  NOT NULL DEFAULT '',
        episode_num INT          NULL DEFAULT NULL,
        publish_at  DATETIME     NULL DEFAULT NULL,
        status      ENUM('pending','published') NOT NULL DEFAULT 'published',
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_places' => "CREATE TABLE IF NOT EXISTS cms_places (
        id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(255) NOT NULL,
        description  TEXT,
        url          VARCHAR(500) NOT NULL DEFAULT '',
        category     VARCHAR(100) NOT NULL DEFAULT '',
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        status       ENUM('pending','published') NOT NULL DEFAULT 'published',
        sort_order   INT          NOT NULL DEFAULT 0,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_dl_categories' => "CREATE TABLE IF NOT EXISTS cms_dl_categories (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_downloads' => "CREATE TABLE IF NOT EXISTS cms_downloads (
        id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title          VARCHAR(255) NOT NULL,
        dl_category_id INT          NULL DEFAULT NULL,
        description    TEXT,
        filename       VARCHAR(255) NOT NULL DEFAULT '',
        original_name  VARCHAR(255) NOT NULL DEFAULT '',
        file_size      INT          NOT NULL DEFAULT 0,
        sort_order     INT          NOT NULL DEFAULT 0,
        is_published   TINYINT(1)   NOT NULL DEFAULT 1,
        status         ENUM('pending','published') NOT NULL DEFAULT 'published',
        author_id      INT          NULL DEFAULT NULL,
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
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
        description TEXT,
        status      ENUM('active','closed') NOT NULL DEFAULT 'active',
        start_date  DATETIME     NULL DEFAULT NULL,
        end_date    DATETIME     NULL DEFAULT NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
        answer       TEXT         NOT NULL,
        sort_order   INT          NOT NULL DEFAULT 0,
        is_published TINYINT(1)   NOT NULL DEFAULT 1,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
        description    TEXT,
        category_id    INT          NULL DEFAULT NULL,
        posted_date    DATE         NOT NULL,
        removal_date   DATE         NULL DEFAULT NULL,
        filename       VARCHAR(255) NOT NULL DEFAULT '',
        original_name  VARCHAR(255) NOT NULL DEFAULT '',
        file_size      INT          NOT NULL DEFAULT 0,
        sort_order     INT          NOT NULL DEFAULT 0,
        is_published   TINYINT(1)   NOT NULL DEFAULT 1,
        status         ENUM('pending','published') NOT NULL DEFAULT 'published',
        author_id      INT          NULL DEFAULT NULL,
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    'cms_articles.author_id'         => "ALTER TABLE cms_articles ADD COLUMN author_id INT NULL DEFAULT NULL",
    'cms_articles.status'            => "ALTER TABLE cms_articles ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    'cms_articles.updated_at'        => "ALTER TABLE cms_articles ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // cms_news
    'cms_news.author_id'             => "ALTER TABLE cms_news ADD COLUMN author_id INT NULL DEFAULT NULL",
    'cms_news.status'                => "ALTER TABLE cms_news ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_podcasts
    'cms_podcasts.show_id'           => "ALTER TABLE cms_podcasts ADD COLUMN show_id INT NOT NULL DEFAULT 1",
    'cms_podcasts.status'            => "ALTER TABLE cms_podcasts ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_events
    'cms_events.status'              => "ALTER TABLE cms_events ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_places
    'cms_places.status'              => "ALTER TABLE cms_places ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_pages
    'cms_pages.status'               => "ALTER TABLE cms_pages ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'published'",
    // cms_downloads
    'cms_downloads.dl_category_id'   => "ALTER TABLE cms_downloads ADD COLUMN dl_category_id INT NULL DEFAULT NULL",
    // cms_users – rozšíření pro veřejné uživatele a role
    'cms_users.role'                 => "ALTER TABLE cms_users ADD COLUMN role ENUM('admin','collaborator','public') NOT NULL DEFAULT 'collaborator'",
    'cms_users.phone'                => "ALTER TABLE cms_users ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT ''",
    'cms_users.is_confirmed'         => "ALTER TABLE cms_users ADD COLUMN is_confirmed TINYINT(1) NOT NULL DEFAULT 1",
    'cms_users.confirmation_token'   => "ALTER TABLE cms_users ADD COLUMN confirmation_token VARCHAR(64) NOT NULL DEFAULT ''",
    'cms_users.reset_token'          => "ALTER TABLE cms_users ADD COLUMN reset_token VARCHAR(64) NOT NULL DEFAULT ''",
    'cms_users.reset_expires'        => "ALTER TABLE cms_users ADD COLUMN reset_expires DATETIME NULL DEFAULT NULL",
    'cms_users.updated_at'           => "ALTER TABLE cms_users ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // cms_res_resources – povolení hostů
    'cms_res_resources.allow_guests' => "ALTER TABLE cms_res_resources ADD COLUMN allow_guests TINYINT(1) NOT NULL DEFAULT 0",
    // cms_articles – počítadlo zobrazení
    'cms_articles.view_count'        => "ALTER TABLE cms_articles ADD COLUMN view_count INT NOT NULL DEFAULT 0",
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
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_users' AND COLUMN_NAME = 'role'"
    );
    $colCheck->execute();
    if ((int)$colCheck->fetchColumn() > 0) {
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
    'news_per_page'           => '10',
    'blog_per_page'           => '10',
    'events_per_page'         => '10',
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
                "INSERT INTO cms_users (email, password, role, is_superadmin, is_confirmed) VALUES (?, ?, 'admin', 1, 1)"
            )->execute([$adminEmail, $adminHash]);
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
    'uploads/articles',
    'uploads/articles/thumbs',
    'uploads/gallery',
    'uploads/gallery/thumbs',
    'uploads/downloads',
    'uploads/board',
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
