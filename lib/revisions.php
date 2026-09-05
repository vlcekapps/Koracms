<?php

// Revize obsahu – ukládá snapshoty textových polí před každou úpravou

/** @return array<string,array{table:string,label:string,title_col:string,back:string,module:string,capability:string}> */
function revisionEntityDefinitions(): array
{
    $shared = 'content_manage_shared';
    $definitions = [
        'article' => ['cms_articles', 'Článek', 'title', 'blog_form.php', 'blog', 'blog_manage_own'],
        'news' => ['cms_news', 'Novinka', 'title', 'news_form.php', 'news', 'news_manage_own'],
        'page' => ['cms_pages', 'Stránka', 'title', 'page_form.php', '', $shared],
        'event' => ['cms_events', 'Událost', 'title', 'event_form.php', 'events', $shared],
        'faq' => ['cms_faqs', 'FAQ', 'question', 'faq_form.php', 'faq', $shared],
        'board' => ['cms_board', 'Položka vývěsky', 'title', 'board_form.php', 'board', $shared],
        'download' => ['cms_downloads', 'Položka ke stažení', 'title', 'download_form.php', 'downloads', $shared],
        'food' => ['cms_food_cards', 'Jídelní nebo nápojový lístek', 'title', 'food_form.php', 'food', $shared],
        'recipe' => ['cms_recipes', 'Recept', 'title', 'recipe_form.php', 'recipes', $shared],
        'place' => ['cms_places', 'Místo', 'name', 'place_form.php', 'places', $shared],
        'poll' => ['cms_polls', 'Anketa', 'question', 'polls_form.php', 'polls', $shared],
        'podcast_show' => ['cms_podcast_shows', 'Podcastový pořad', 'title', 'podcast_show_form.php', 'podcast', $shared],
        'podcast_episode' => ['cms_podcasts', 'Podcastová epizoda', 'title', 'podcast_form.php', 'podcast', $shared],
        'gallery_album' => ['cms_gallery_albums', 'Album galerie', 'name', 'gallery_album_form.php', 'gallery', $shared],
        'gallery_photo' => ['cms_gallery_photos', 'Fotografie', 'title', 'gallery_photo_form.php', 'gallery', $shared],
    ];
    $result = [];
    foreach ($definitions as $type => [$table, $label, $title, $back, $module, $capability]) {
        $result[$type] = ['table' => $table, 'label' => $label, 'title_col' => $title,
            'back' => $back, 'module' => $module, 'capability' => $capability];
    }
    return $result;
}

/** @param array<string,mixed> $entity */
function canReadEntityRevisions(string $entityType, array $entity): bool
{
    $definition = revisionEntityDefinitions()[$entityType] ?? null;
    if ($definition === null || !currentUserHasCapability($definition['capability'])) {
        return false;
    }
    $module = $entityType === 'page' && !empty($entity['blog_id']) ? 'blog' : $definition['module'];
    if ($module !== '' && !isModuleEnabled($module)) {
        return false;
    }
    if ($entityType === 'article' && canManageOwnBlogOnly()) {
        return currentUserId() !== null
            && (int)($entity['author_id'] ?? 0) === currentUserId()
            && canCurrentUserWriteToBlog((int)($entity['blog_id'] ?? 0));
    }
    if ($entityType === 'news' && canManageOwnNewsOnly()) {
        return currentUserId() !== null && (int)($entity['author_id'] ?? 0) === currentUserId();
    }
    return true;
}

/**
 * Zapíše technickou chybu revizí bez ukládání samotného obsahu polí.
 */
function revisionLogError(string $operation, string $entityType, int $entityId, \Throwable $e): void
{
    koraLog('warning', 'revision operation failed', [
        'operation' => $operation,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'exception' => $e,
    ]);
}

/**
 * Uloží revizi (snapshot starého obsahu) před uložením změn.
 *
 * @param string $entityType Typ entity (article, news, page, event, faq, board, download, food, place)
 * @param int $entityId ID entity
 * @param array<string,mixed> $oldValues Asociativní pole [field => old_value] – ukládají se jen změněná pole
 * @param array<string,mixed> $newValues Asociativní pole [field => new_value]
 */
function saveRevision(PDO $pdo, string $entityType, int $entityId, array $oldValues, array $newValues): void
{
    $userId = (int)(currentUserId() ?? 0);

    foreach ($oldValues as $field => $oldValue) {
        $newValue = $newValues[$field] ?? '';
        $oldStr = trim((string)$oldValue);
        $newStr = trim((string)$newValue);

        if ($oldStr === $newStr) {
            continue;
        }

        try {
            $pdo->prepare(
                "INSERT INTO cms_revisions (entity_type, entity_id, field_name, old_value, new_value, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            )->execute([$entityType, $entityId, $field, $oldStr, $newStr, $userId ?: null]);
        } catch (\PDOException $e) {
            revisionLogError('save', $entityType, $entityId, $e);
        }
    }
}

/**
 * Načte historii revizí pro danou entitu.
 *
 * @return list<array{
 *   id:int,
 *   field_name:string,
 *   old_value:string,
 *   new_value:string,
 *   created_at:string,
 *   user_name:string
 * }> Pole revizí seřazených od nejnovější
 */
function loadRevisions(PDO $pdo, string $entityType, int $entityId, int $limit = 50): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT r.id, r.field_name, r.old_value, r.new_value, r.created_at,
                    COALESCE(NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.email, 'Systém') AS user_name
             FROM cms_revisions r
             LEFT JOIN cms_users u ON u.id = r.user_id
             WHERE r.entity_type = ? AND r.entity_id = ?
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ?"
        );
        $stmt->execute([$entityType, $entityId, $limit]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        revisionLogError('load', $entityType, $entityId, $e);
        return [];
    }
}

/**
 * Vrátí český label pro název pole revize.
 */
function revisionFieldLabel(string $entityType, string $fieldName): string
{
    $labels = [
        'title'       => 'Název',
        'question'    => 'Otázka',
        'name'        => 'Název',
        'content'     => 'Obsah',
        'perex'       => 'Perex',
        'excerpt'     => 'Shrnutí',
        'answer'      => 'Odpověď',
        'description' => 'Popis',
        'slug'        => 'Slug (URL)',
        'meta_title'  => 'Meta titulek',
        'meta_description' => 'Meta popis',
        'status'      => 'Workflow stav',
        'start_date'  => 'Začátek ankety',
        'end_date'    => 'Konec ankety',
        'options'     => 'Možnosti odpovědi',
        'type'        => 'Typ',
        'place_kind'  => 'Typ místa',
        'location'    => 'Místo konání',
        'event_kind'  => 'Typ akce',
        'event_date'  => 'Začátek akce',
        'event_end'   => 'Konec akce',
        'organizer_name' => 'Pořadatel',
        'organizer_email' => 'E-mail pořadatele',
        'registration_url' => 'Registrační odkaz',
        'price_note'  => 'Cena / vstupné',
        'accessibility_note' => 'Přístupnost',
        'program_note' => 'Program a doplňující informace',
        'unpublish_at' => 'Plánované zrušení publikace',
        'admin_note'  => 'Interní poznámka',
        'board_type'  => 'Typ položky',
        'category'    => 'Kategorie',
        'blog'        => 'Blog',
        'tags'        => 'Štítky',
        'author'      => 'Autor / vydavatel',
        'subtitle'    => 'Podtitul',
        'language'    => 'Jazyk',
        'owner_name'  => 'Vlastník feedu',
        'owner_email' => 'E-mail vlastníka feedu',
        'explicit_mode' => 'Explicitní obsah',
        'show_type'   => 'Typ pořadu',
        'feed_complete' => 'Feed dokončen',
        'feed_episode_limit' => 'Počet epizod v RSS feedu',
        'website_url' => 'Web pořadu',
        'duration'    => 'Délka',
        'episode_num' => 'Číslo epizody',
        'season_num'  => 'Číslo série',
        'episode_type' => 'Typ epizody',
        'block_from_feed' => 'Skrýt z RSS feedu',
        'publish_at'  => 'Plánované zveřejnění',
        'download_type' => 'Typ položky',
        'version_label' => 'Verze',
        'platform_label' => 'Platforma',
        'license_label' => 'Licence',
        'project_url' => 'Domovská stránka projektu',
        'release_date' => 'Datum vydání',
        'requirements' => 'Požadavky a kompatibilita',
        'checksum_sha256' => 'SHA-256 checksum',
        'series_key' => 'Skupina verzí',
        'external_url' => 'Externí odkaz ke stažení',
        'is_featured' => 'Doporučená položka',
        'posted_date' => 'Datum vyvěšení',
        'removal_date' => 'Datum sejmutí',
        'contact_name' => 'Kontaktní osoba',
        'contact_phone' => 'Telefon',
        'contact_email' => 'E-mail',
        'address'     => 'Adresa',
        'locality'    => 'Lokalita / obec',
        'opening_hours' => 'Otevírací doba / poznámky',
        'url'         => 'Web / externí odkaz',
        'latitude'    => 'Zeměpisná šířka',
        'longitude'   => 'Zeměpisná délka',
        'is_pinned'   => 'Připnuto mezi důležité',
        'is_published' => 'Zveřejnění na webu',
        'valid_from'  => 'Platí od',
        'valid_to'    => 'Platí do',
        'is_current'  => 'Použít jako aktuální lístek',
        'category_id' => 'Kategorie',
        'summary'     => 'Krátké shrnutí',
        'notes'       => 'Poznámky a tipy',
        'servings'    => 'Počet porcí',
        'prep_minutes' => 'Doba přípravy',
        'cook_minutes' => 'Doba tepelné úpravy',
        'difficulty'  => 'Náročnost',
        'dietary_flags' => 'Dietní vlastnosti',
        'allergens'   => 'Alergeny',
        'calories_kcal' => 'Energie na porci',
        'media_id'    => 'Hlavní obrázek',
        'image_alt_text' => 'Alternativní text obrázku',
        'source_name' => 'Název zdroje',
        'source_url'  => 'Odkaz na zdroj',
        'parent_album' => 'Nadřazené album',
        'cover_photo' => 'Náhledová fotka alba',
        'album' => 'Album',
        'sort_order' => 'Pořadí',
    ];

    return $labels[$fieldName] ?? $fieldName;
}
