<?php
// Revize obsahu – ukládá snapshoty textových polí před každou úpravou

/**
 * Uloží revizi (snapshot starého obsahu) před uložením změn.
 *
 * @param string $entityType  Typ entity (article, news, page, event, faq, board, download, food, place)
 * @param int    $entityId    ID entity
 * @param array  $oldValues   Asociativní pole [field => old_value] – ukládají se jen změněná pole
 * @param array  $newValues   Asociativní pole [field => new_value]
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
            error_log('saveRevision: ' . $e->getMessage());
        }
    }
}

/**
 * Načte historii revizí pro danou entitu.
 *
 * @return array Pole revizí seřazených od nejnovější
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
        error_log('loadRevisions: ' . $e->getMessage());
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
        'removal_date'=> 'Datum sejmutí',
        'contact_name'=> 'Kontaktní osoba',
        'contact_phone' => 'Telefon',
        'contact_email' => 'E-mail',
        'address'     => 'Adresa',
        'locality'    => 'Lokalita / obec',
        'opening_hours' => 'Otevírací doba / poznámky',
        'url'         => 'Web / externí odkaz',
        'latitude'    => 'Zeměpisná šířka',
        'longitude'   => 'Zeměpisná délka',
        'is_pinned'   => 'Připnuto mezi důležité',
        'is_published'=> 'Zveřejnění na webu',
        'valid_from'  => 'Platí od',
        'valid_to'    => 'Platí do',
        'is_current'  => 'Použít jako aktuální lístek',
        'parent_album' => 'Nadřazené album',
        'cover_photo' => 'Náhledová fotka alba',
        'album' => 'Album',
        'sort_order' => 'Pořadí',
    ];

    return $labels[$fieldName] ?? $fieldName;
}
