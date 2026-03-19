<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$errors  = [];
$success = false;
$summary = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (empty($_FILES['import_file']['name'])) {
        $errors[] = 'Vyberte soubor pro import.';
    } else {
        $tmp  = $_FILES['import_file']['tmp_name'];
        $json = @file_get_contents($tmp);
        $data = $json !== false ? @json_decode($json, true) : null;

        if (!is_array($data) || ($data['site'] ?? '') !== 'cms') {
            $errors[] = 'Neplatný nebo poškozený exportní soubor.';
        } else {
            $pdo = db_connect();
            $pdo->beginTransaction();
            try {
                // Nastavení (přepíše jen existující klíče, heslo neimportujeme)
                if (!empty($data['settings']) && is_array($data['settings'])) {
                    $s = $pdo->prepare(
                        "INSERT INTO cms_settings (`key`, value) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE value = VALUES(value)"
                    );
                    $skip = ['admin_password'];
                    foreach ($data['settings'] as $row) {
                        if (in_array($row['key'], $skip, true)) continue;
                        $s->execute([$row['key'], $row['value']]);
                    }
                    $summary[] = 'Nastavení importována.';
                }

                // Kategorie (přidá jen chybějící podle jména)
                if (!empty($data['categories']) && is_array($data['categories'])) {
                    $ins = $pdo->prepare("INSERT IGNORE INTO cms_categories (id, name) VALUES (?,?)");
                    foreach ($data['categories'] as $row) {
                        $ins->execute([(int)$row['id'], $row['name']]);
                    }
                    $summary[] = 'Kategorie importovány.';
                }

                // Tagy
                if (!empty($data['tags']) && is_array($data['tags'])) {
                    $ins = $pdo->prepare("INSERT IGNORE INTO cms_tags (id, name, slug) VALUES (?,?,?)");
                    foreach ($data['tags'] as $row) {
                        $ins->execute([(int)$row['id'], $row['name'], $row['slug']]);
                    }
                    $summary[] = 'Tagy importovány.';
                }

                // Články
                if (!empty($data['articles']) && is_array($data['articles'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_articles
                         (id, title, perex, content, category_id, image_file,
                          meta_title, meta_description, publish_at, status, created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['articles'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['title'], $row['perex'] ?? '',
                            $row['content'] ?? '', $row['category_id'] ?: null,
                            $row['image_file'] ?? '',
                            $row['meta_title'] ?? '', $row['meta_description'] ?? '',
                            $row['publish_at'] ?: null,
                            $row['status'] ?? 'published', $row['created_at'],
                        ]);
                    }
                    $summary[] = 'Články importovány.';
                }

                // Statické stránky
                if (!empty($data['pages']) && is_array($data['pages'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_pages
                         (id, title, slug, content, show_in_nav, nav_order, is_published, status)
                         VALUES (?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['pages'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['title'], $row['slug'],
                            $row['content'] ?? '', (int)$row['show_in_nav'],
                            (int)$row['nav_order'], (int)$row['is_published'],
                            $row['status'] ?? 'published',
                        ]);
                    }
                    $summary[] = 'Stránky importovány.';
                }

                // Novinky
                if (!empty($data['news']) && is_array($data['news'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_news (id, content, status, created_at)
                         VALUES (?,?,?,?)"
                    );
                    foreach ($data['news'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['content'] ?? '',
                            $row['status'] ?? 'published', $row['created_at'],
                        ]);
                    }
                    $summary[] = 'Novinky importovány.';
                }

                // Události
                if (!empty($data['events']) && is_array($data['events'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_events
                         (id, title, description, location, event_date, event_end, is_published, status)
                         VALUES (?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['events'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['title'], $row['description'] ?? '',
                            $row['location'] ?? '', $row['event_date'],
                            $row['event_end'] ?: null, (int)$row['is_published'],
                            $row['status'] ?? 'published',
                        ]);
                    }
                    $summary[] = 'Události importovány.';
                }

                // Místa
                if (!empty($data['places']) && is_array($data['places'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_places
                         (id, name, description, url, category, is_published, sort_order, status)
                         VALUES (?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['places'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['name'], $row['description'] ?? '',
                            $row['url'] ?? '', $row['category'] ?? '',
                            (int)$row['is_published'], (int)$row['sort_order'],
                            $row['status'] ?? 'published',
                        ]);
                    }
                    $summary[] = 'Zajímavá místa importována.';
                }

                // Galerie – alba (nejdřív, fotky odkazují na album_id)
                if (!empty($data['gallery_albums']) && is_array($data['gallery_albums'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_gallery_albums (id, parent_id, name, description)
                         VALUES (?,?,?,?)"
                    );
                    foreach ($data['gallery_albums'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['parent_id'] ?: null,
                            $row['name'], $row['description'] ?? '',
                        ]);
                    }
                    $summary[] = 'Galerie – alba importována.';
                }

                // Galerie – fotografie
                if (!empty($data['gallery_photos']) && is_array($data['gallery_photos'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_gallery_photos
                         (id, album_id, filename, title, sort_order)
                         VALUES (?,?,?,?,?)"
                    );
                    foreach ($data['gallery_photos'] as $row) {
                        $ins->execute([
                            (int)$row['id'], (int)$row['album_id'],
                            $row['filename'], $row['title'] ?? '',
                            (int)$row['sort_order'],
                        ]);
                    }
                    $summary[] = 'Galerie – fotografie importovány.';
                }

                // Kategorie ke stažení
                if (!empty($data['dl_categories']) && is_array($data['dl_categories'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_dl_categories (id, name) VALUES (?,?)"
                    );
                    foreach ($data['dl_categories'] as $row) {
                        $ins->execute([(int)$row['id'], $row['name']]);
                    }
                    $summary[] = 'Kategorie ke stažení importovány.';
                }

                // Soubory ke stažení
                if (!empty($data['downloads']) && is_array($data['downloads'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_downloads
                         (id, title, dl_category_id, description, filename, original_name,
                          file_size, sort_order, is_published, status)
                         VALUES (?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['downloads'] as $row) {
                        $catId = $row['dl_category_id'] ?? null;
                        $ins->execute([
                            (int)$row['id'], $row['title'], $catId !== '' ? $catId : null,
                            $row['description'] ?? '', $row['filename'] ?? '',
                            $row['original_name'] ?? '', (int)($row['file_size'] ?? 0),
                            (int)($row['sort_order'] ?? 0), (int)($row['is_published'] ?? 1),
                            $row['status'] ?? 'published',
                        ]);
                    }
                    $summary[] = 'Soubory ke stažení importovány.';
                }

                // Jídelní / nápojové lístky
                if (!empty($data['food_cards']) && is_array($data['food_cards'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_food_cards
                         (id, type, title, description, content, valid_from, valid_to,
                          is_current, is_published, status)
                         VALUES (?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['food_cards'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['type'] ?? 'food',
                            $row['title'], $row['description'] ?? '',
                            $row['content'] ?? '', $row['valid_from'] ?: null,
                            $row['valid_to'] ?: null, (int)$row['is_current'],
                            (int)$row['is_published'], $row['status'] ?? 'published',
                        ]);
                    }
                    $summary[] = 'Jídelní lístky importovány.';
                }

                // Podcast shows (nejdřív – epizody na ně odkazují přes show_id)
                if (!empty($data['podcast_shows']) && is_array($data['podcast_shows'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_podcast_shows
                         (id, title, slug, description, author, cover_image, language, category, website_url)
                         VALUES (?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['podcast_shows'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['title'], $row['slug'],
                            $row['description'] ?? '', $row['author'] ?? '',
                            $row['cover_image'] ?? '', $row['language'] ?? 'cs',
                            $row['category'] ?? '', $row['website_url'] ?? '',
                        ]);
                    }
                    $summary[] = 'Podcast shows importovány.';
                }

                // Epizody podcastů
                if (!empty($data['podcasts']) && is_array($data['podcasts'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_podcasts
                         (id, show_id, title, description, audio_file, audio_url,
                          duration, episode_num, publish_at, status)
                         VALUES (?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['podcasts'] as $row) {
                        $ins->execute([
                            (int)$row['id'], (int)($row['show_id'] ?? 1),
                            $row['title'], $row['description'] ?? '',
                            $row['audio_file'] ?? '', $row['audio_url'] ?? '',
                            $row['duration'] ?? '', $row['episode_num'] ?: null,
                            $row['publish_at'] ?: null, $row['status'] ?? 'published',
                        ]);
                    }
                    $summary[] = 'Epizody podcastů importovány.';
                }

                $pdo->commit();
                clearSettingsCache();
                logAction('import_json');
                $success = true;
            } catch (\PDOException $e) {
                $pdo->rollBack();
                $errors[] = 'Chyba databáze: ' . h($e->getMessage());
            }
        }
    }
}

adminHeader('Import / Export dat');
?>

<h2>Export dat</h2>
<p>Stáhne veškerý obsah (články, novinky, stránky, události, galerie, místa, soubory ke stažení,
   jídelní lístky, podcasty, nastavení) jako JSON soubor.
   Heslo administrátora se neexportuje.
   Nahrané soubory (obrázky, audio, přílohy) je třeba přenést ručně ze složky <code>uploads/</code>.</p>
<p>
  <a href="export.php" class="btn">Stáhnout zálohu (JSON)</a>
</p>

<h2>Import dat</h2>
<p>Importuje obsah z dříve staženého JSON souboru.
   Existující záznamy (stejné ID) jsou přeskočeny – data nebudou přepsána.</p>

<?php if ($success): ?>
  <p class="success" role="status"><strong>Import proběhl úspěšně.</strong>
    <?= implode(' ', array_map('h', $summary)) ?></p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <ul class="error" role="alert">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <label for="import_file">JSON soubor (export z tohoto CMS)</label>
  <input type="file" id="import_file" name="import_file" accept=".json,application/json" required aria-required="true">
  <button type="submit" class="btn" style="margin-top:1rem">Importovat</button>
</form>

<?php adminFooter(); ?>
