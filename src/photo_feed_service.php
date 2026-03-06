<?php
require_once __DIR__ . '/helpers.php';

function photo_feed_config(): array
{
    static $config;

    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }

    return $config;
}

function photo_feed_signing_key(): string
{
    $config = photo_feed_config();
    $envKey = getenv('PHOTO_FEED_SIGNING_KEY');

    if (is_string($envKey) && trim($envKey) !== '') {
        return trim($envKey);
    }

    $seed = ($config['db']['password'] ?? '') . '|' . ($config['base_url'] ?? 'https://www.syphotos.cn') . '|photo-feed-v1';
    return hash('sha256', $seed);
}

function photo_feed_normalize_filters(array $input): array
{
    $page = isset($input['page']) && is_numeric($input['page']) && (int) $input['page'] > 0 ? (int) $input['page'] : 1;
    $perPage = isset($input['per_page']) && is_numeric($input['per_page']) ? (int) $input['per_page'] : 30;
    $perPage = max(1, min(60, $perPage));

    return [
        'iatacode' => isset($input['iatacode']) ? strtoupper(trim((string) $input['iatacode'])) : '',
        'user_id' => isset($input['userid']) && is_numeric($input['userid']) ? (int) $input['userid'] : 0,
        'airline' => isset($input['airline']) ? trim((string) $input['airline']) : '',
        'aircraft_model' => isset($input['aircraft_model']) ? trim((string) $input['aircraft_model']) : '',
        'cam' => isset($input['cam']) ? trim((string) $input['cam']) : '',
        'lens' => isset($input['lens']) ? trim((string) $input['lens']) : '',
        'page' => $page,
        'per_page' => $perPage,
    ];
}

function photo_feed_build_where_clause(array $filters, array &$params): string
{
    $iataCode = (string) ($filters['iatacode'] ?? '');
    $userId = (int) ($filters['user_id'] ?? ($filters['userid'] ?? 0));
    $airline = (string) ($filters['airline'] ?? '');
    $aircraftModel = (string) ($filters['aircraft_model'] ?? '');
    $cam = (string) ($filters['cam'] ?? '');
    $lens = (string) ($filters['lens'] ?? '');

    $where = " WHERE p.approved = 1";

    if ($iataCode !== '') {
        $where .= " AND p.`拍摄地点` = :iatacode";
        $params[':iatacode'] = $iataCode;
    }

    if ($userId > 0) {
        $where .= " AND p.user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    if ($airline !== '') {
        $where .= " AND p.category = :airline";
        $params[':airline'] = $airline;
    }

    if ($aircraftModel !== '') {
        $where .= " AND p.aircraft_model = :aircraft_model";
        $params[':aircraft_model'] = $aircraftModel;
    }

    if ($cam !== '') {
        $where .= " AND p.Cam = :cam";
        $params[':cam'] = $cam;
    }

    if ($lens !== '') {
        $where .= " AND p.Lens = :lens";
        $params[':lens'] = $lens;
    }

    return $where;
}

function photo_feed_fetch_total(PDO $pdo, array $filters): int
{
    $params = [];
    $where = photo_feed_build_where_clause($filters, $params);
    $sql = "SELECT COUNT(*)
            FROM photos p
            INNER JOIN users u ON p.user_id = u.id" . $where;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function photo_feed_fetch_page(PDO $pdo, array $filters): array
{
    $params = [];
    $where = photo_feed_build_where_clause($filters, $params);
    $offset = ($filters['page'] - 1) * $filters['per_page'];
    $sql = "SELECT p.*, u.username
            FROM photos p
            INNER JOIN users u ON p.user_id = u.id" . $where . "
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $filters['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function photo_feed_sign_payload(string $payload): string
{
    return hash_hmac('sha256', $payload, photo_feed_signing_key());
}

function photo_feed_build_scope(array $filters): string
{
    $normalized = photo_feed_normalize_filters($filters);

    return implode('|', [
        'photo-feed',
        'iatacode=' . $normalized['iatacode'],
        'user_id=' . $normalized['user_id'],
        'airline=' . $normalized['airline'],
        'aircraft_model=' . $normalized['aircraft_model'],
        'cam=' . $normalized['cam'],
        'lens=' . $normalized['lens'],
        'per_page=' . $normalized['per_page'],
    ]);
}

function photo_feed_issue_access_signature(array $filters, int $ttl = 3600): array
{
    $expires = time() + $ttl;
    $scope = photo_feed_build_scope($filters);
    $signature = photo_feed_sign_payload($scope . '|' . $expires);

    return [
        'expires' => $expires,
        'signature' => $signature,
    ];
}

function photo_feed_verify_access_signature(array $filters, int $expires, string $signature): bool
{
    if ($expires < time()) {
        return false;
    }

    $scope = photo_feed_build_scope($filters);
    $expected = photo_feed_sign_payload($scope . '|' . $expires);

    return hash_equals($expected, $signature);
}

function photo_feed_build_asset_signature(string $filename, string $variant, int $expires): string
{
    return photo_feed_sign_payload('photo-asset|' . $filename . '|' . $variant . '|' . $expires);
}

function photo_feed_build_asset_url(array $photo, string $variant = 'thumb', int $ttl = 3600): string
{
    $filename = (string) ($photo['filename'] ?? '');
    $expires = time() + $ttl;
    $signature = photo_feed_build_asset_signature($filename, $variant, $expires);

    return 'photo_asset.php?file=' . rawurlencode($filename)
        . '&variant=' . rawurlencode($variant)
        . '&expires=' . $expires
        . '&sig=' . rawurlencode($signature);
}

function photo_feed_build_photo_item(array $photo): array
{
    return [
        'id' => (int) $photo['id'],
        'title' => (string) ($photo['title'] ?? ''),
        'username' => (string) ($photo['username'] ?? ''),
        'user_id' => (int) ($photo['user_id'] ?? 0),
        'location' => (string) ($photo['拍摄地点'] ?? ''),
        'airline' => (string) ($photo['category'] ?? ''),
        'aircraft_model' => (string) ($photo['aircraft_model'] ?? ''),
        'cam' => (string) ($photo['Cam'] ?? ''),
        'lens' => (string) ($photo['Lens'] ?? ''),
        'detail_url' => 'photo_detail.php?id=' . (int) $photo['id'],
        'author_url' => 'author.php?userid=' . (int) ($photo['user_id'] ?? 0),
        'thumb_url' => photo_feed_build_asset_url($photo, 'thumb'),
        'original_url' => photo_feed_build_asset_url($photo, 'original'),
    ];
}

function photo_feed_prepare_items(array $photos): array
{
    return array_map('photo_feed_build_photo_item', $photos);
}

function photo_feed_render_cards(array $photos): string
{
    $items = photo_feed_prepare_items($photos);
    ob_start();
    foreach ($items as $item):
        ?>
        <a class="photolist-card" href="<?php echo h($item['detail_url']); ?>" title="<?php echo h($item['title']); ?>" target="_blank" rel="noopener noreferrer">
            <img src="<?php echo h($item['thumb_url']); ?>" alt="<?php echo h($item['title']); ?>" loading="lazy">
        </a>
        <?php
    endforeach;

    return (string) ob_get_clean();
}

function photo_feed_fetch_user_profile(PDO $pdo, int $userId): ?array
{
    $sql = "SELECT
                u.id,
                u.username,
                u.created_at,
                u.last_active,
                (SELECT COUNT(*) FROM photos p WHERE p.user_id = u.id AND p.approved = 1) AS photo_count
            FROM users u
            WHERE u.id = :user_id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    return $profile ?: null;
}

function photo_feed_fetch_top_values(PDO $pdo, int $userId, string $column, string $filterKey, int $limit = 3): array
{
    $allowed = [
        'location' => ['column' => 'p.`拍摄地点`', 'filter_key' => 'iatacode'],
        'airline' => ['column' => 'p.category', 'filter_key' => 'airline'],
        'aircraft_model' => ['column' => 'p.aircraft_model', 'filter_key' => 'aircraft_model'],
        'camera' => ['column' => 'p.Cam', 'filter_key' => 'cam'],
        'lens' => ['column' => 'p.Lens', 'filter_key' => 'lens'],
    ];

    if (!isset($allowed[$column])) {
        return [];
    }

    $columnSql = $allowed[$column]['column'];
    $filterParam = $allowed[$column]['filter_key'];

    $totalStmt = $pdo->prepare("SELECT COUNT(*)
        FROM photos p
        WHERE p.user_id = :user_id
          AND p.approved = 1
          AND COALESCE({$columnSql}, '') <> ''");
    $totalStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $totalStmt->execute();
    $total = (int) $totalStmt->fetchColumn();

    if ($total === 0) {
        return [];
    }

    $sql = "SELECT {$columnSql} AS label, COUNT(*) AS item_count
        FROM photos p
        WHERE p.user_id = :user_id
          AND p.approved = 1
          AND COALESCE({$columnSql}, '') <> ''
        GROUP BY {$columnSql}
        ORDER BY item_count DESC, label ASC
        LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static function (array $row) use ($total, $userId, $filterParam): array {
        $label = (string) ($row['label'] ?? '');
        $count = (int) ($row['item_count'] ?? 0);
        $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0.0;

        return [
            'label' => $label,
            'count' => $count,
            'percentage' => $percentage,
            'url' => 'photolist.php?' . $filterParam . '=' . urlencode($label),
        ];
    }, $rows);
}

function photo_feed_fetch_user_basic(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, created_at, last_active FROM users WHERE id = :user_id LIMIT 1');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function photo_feed_filters_without_field(array $filters, string $field): array
{
    $normalized = photo_feed_normalize_filters($filters);

    if (array_key_exists($field, $normalized)) {
        $normalized[$field] = is_int($normalized[$field]) ? 0 : '';
    }

    return $normalized;
}

function photo_feed_fetch_filter_suggestions(PDO $pdo, string $field, string $query = '', array $filters = [], int $limit = 10): array
{
    $normalized = photo_feed_filters_without_field($filters, $field);
    $params = [];
    $where = photo_feed_build_where_clause($normalized, $params);
    $limit = max(1, min(10, $limit));
    $query = trim($query);

    if ($field === 'userid') {
        $sql = "SELECT u.id AS value, u.username AS label, COUNT(*) AS item_count
                FROM photos p
                INNER JOIN users u ON p.user_id = u.id" . $where;

        if ($query !== '') {
            $sql .= " AND u.username LIKE :keyword";
            $params[':keyword'] = '%' . $query . '%';
        }

        $sql .= " GROUP BY u.id, u.username
                  ORDER BY item_count DESC, u.username ASC
                  LIMIT :limit";
    } else {
        $fieldMap = [
            'airline' => 'p.category',
            'aircraft_model' => 'p.aircraft_model',
            'cam' => 'p.Cam',
            'lens' => 'p.Lens',
            'iatacode' => 'p.`拍摄地点`',
        ];

        if (!isset($fieldMap[$field])) {
            return [];
        }

        $column = $fieldMap[$field];
        $sql = "SELECT {$column} AS value, {$column} AS label, COUNT(*) AS item_count
                FROM photos p
                INNER JOIN users u ON p.user_id = u.id" . $where . "
                AND COALESCE({$column}, '') <> ''";

        if ($query !== '') {
            $sql .= " AND {$column} LIKE :keyword";
            $params[':keyword'] = '%' . $query . '%';
        }

        $sql .= " GROUP BY {$column}
                  ORDER BY item_count DESC, label ASC
                  LIMIT :limit";
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map(static function (array $row): array {
        return [
            'value' => (string) ($row['value'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'count' => (int) ($row['item_count'] ?? 0),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
