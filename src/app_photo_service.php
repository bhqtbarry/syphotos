<?php
require_once __DIR__ . '/app_api.php';

function app_photo_status_from_approved($approved): string
{
    $approved = (int) $approved;
    if ($approved === 1) {
        return 'approved';
    }
    if ($approved === 2) {
        return 'rejected';
    }
    return 'pending';
}

function app_fetch_liked_photo_ids(PDO $pdo, int $userId, array $photoIds): array
{
    if ($userId <= 0 || $photoIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $stmt = $pdo->prepare("SELECT photo_id FROM photo_likes WHERE user_id = ? AND photo_id IN ({$placeholders})");
    $stmt->execute(array_merge([$userId], $photoIds));

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
}

function app_photo_payload(array $photo, bool $liked = false): array
{
    $item = photo_feed_build_photo_item($photo);
    $config = app_config();

    return $item + [
        'status' => app_photo_status_from_approved($photo['approved'] ?? 0),
        'approved_code' => (int) ($photo['approved'] ?? 0),
        'score' => (int) ($photo['score'] ?? 0),
        'views' => (int) ($photo['views'] ?? 0),
        'likes' => (int) ($photo['likes'] ?? 0),
        'liked' => $liked,
        'author_name' => (string) ($photo['author_name'] ?? $photo['username'] ?? ''),
        'reviewer_name' => (string) ($photo['reviewer_name'] ?? ''),
        'created_at' => (string) ($photo['created_at'] ?? ''),
        'shooting_time' => (string) ($photo['shooting_time'] ?? $photo['拍摄时间'] ?? ''),
        'shooting_location' => (string) ($photo['shooting_location'] ?? $photo['拍摄地点'] ?? ''),
        'camera' => (string) ($photo['Cam'] ?? ''),
        'lens_model' => (string) ($photo['Lens'] ?? ''),
        'focal_length' => $photo['FocalLength'] !== null ? (int) $photo['FocalLength'] : null,
        'iso' => $photo['ISO'] !== null ? (int) $photo['ISO'] : null,
        'aperture' => $photo['F'] !== null ? (float) $photo['F'] : null,
        'shutter' => (string) ($photo['Shutter'] ?? ''),
        'rejection_reason' => $photo['rejection_reason'] !== null ? (string) $photo['rejection_reason'] : null,
        'admin_comment' => $photo['admin_comment'] !== null ? (string) $photo['admin_comment'] : null,
        'share_url' => rtrim((string) ($config['base_url'] ?? 'https://www.syphotos.cn'), '/') . '/photo_detail.php?id=' . (int) $photo['id'],
        'allow_use' => (int) ($photo['allow_use'] ?? 0),
        'is_featured' => (int) ($photo['is_featured'] ?? 0),
        'dimensions' => [
            'original_width' => (int) ($photo['original_width'] ?? 0),
            'original_height' => (int) ($photo['original_height'] ?? 0),
            'final_width' => (int) ($photo['final_width'] ?? 0),
            'final_height' => (int) ($photo['final_height'] ?? 0),
        ],
    ];
}

function app_fetch_feed(PDO $pdo, array $filters, int $viewerUserId = 0): array
{
    $total = photo_feed_fetch_total($pdo, $filters);
    $rows = photo_feed_fetch_page($pdo, $filters);
    $offset = ($filters['page'] - 1) * $filters['per_page'];
    $likedIds = app_fetch_liked_photo_ids($pdo, $viewerUserId, array_column($rows, 'id'));

    return [
        'page' => $filters['page'],
        'per_page' => $filters['per_page'],
        'total' => $total,
        'has_more' => ($offset + count($rows)) < $total,
        'items' => array_map(static function (array $row) use ($likedIds): array {
            return app_photo_payload($row, in_array((int) $row['id'], $likedIds, true));
        }, $rows),
    ];
}

function app_fetch_photo_detail(PDO $pdo, int $photoId, int $viewerUserId = 0): ?array
{
    $stmt = $pdo->prepare('SELECT p.*, u.username, u.username AS author_name, r.username AS reviewer_name
        FROM photos p
        INNER JOIN users u ON p.user_id = u.id
        LEFT JOIN users r ON p.reviewer_id = r.id
        WHERE p.id = :id AND (p.approved = 1 OR p.user_id = :viewer_user_id)
        LIMIT 1');
    $stmt->execute([
        ':id' => $photoId,
        ':viewer_user_id' => $viewerUserId,
    ]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        return null;
    }

    $liked = false;
    if ($viewerUserId > 0) {
        $check = $pdo->prepare('SELECT 1 FROM photo_likes WHERE user_id = :user_id AND photo_id = :photo_id LIMIT 1');
        $check->execute([
            ':user_id' => $viewerUserId,
            ':photo_id' => $photoId,
        ]);
        $liked = (bool) $check->fetchColumn();
    }

    return app_photo_payload($photo, $liked);
}

function app_toggle_like(PDO $pdo, int $userId, int $photoId): array
{
    $stmt = $pdo->prepare('SELECT id, likes, approved, user_id FROM photos WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $photoId, PDO::PARAM_INT);
    $stmt->execute();
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$photo || ((int) $photo['approved'] !== 1 && (int) $photo['user_id'] !== $userId)) {
        app_fail('图片不存在或无权操作', 404);
    }

    $check = $pdo->prepare('SELECT id FROM photo_likes WHERE user_id = :user_id AND photo_id = :photo_id LIMIT 1');
    $check->execute([
        ':user_id' => $userId,
        ':photo_id' => $photoId,
    ]);
    $likedRow = $check->fetch(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();
    try {
        if ($likedRow) {
            $delete = $pdo->prepare('DELETE FROM photo_likes WHERE id = :id');
            $delete->bindValue(':id', (int) $likedRow['id'], PDO::PARAM_INT);
            $delete->execute();

            $update = $pdo->prepare('UPDATE photos SET likes = GREATEST(likes - 1, 0) WHERE id = :id');
            $update->bindValue(':id', $photoId, PDO::PARAM_INT);
            $update->execute();
            $liked = false;
        } else {
            $insert = $pdo->prepare('INSERT INTO photo_likes (user_id, photo_id, created_at) VALUES (:user_id, :photo_id, NOW())');
            $insert->execute([
                ':user_id' => $userId,
                ':photo_id' => $photoId,
            ]);

            $update = $pdo->prepare('UPDATE photos SET likes = likes + 1 WHERE id = :id');
            $update->bindValue(':id', $photoId, PDO::PARAM_INT);
            $update->execute();
            $liked = true;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_fail('点赞操作失败: ' . $e->getMessage(), 500);
    }

    $countStmt = $pdo->prepare('SELECT likes FROM photos WHERE id = :id');
    $countStmt->bindValue(':id', $photoId, PDO::PARAM_INT);
    $countStmt->execute();

    return [
        'liked' => $liked,
        'likes' => (int) $countStmt->fetchColumn(),
    ];
}

function app_build_user_photo_status_where(string $status): string
{
    if ($status === 'approved') {
        return 'p.approved = 1';
    }
    if ($status === 'rejected') {
        return 'p.approved = 2';
    }
    if ($status === 'pending') {
        return 'p.approved IN (0, 3)';
    }
    return '1 = 1';
}

function app_fetch_user_photos(PDO $pdo, int $userId, string $status, int $page, int $perPage): array
{
    $page = max(1, $page);
    $perPage = max(1, min(60, $perPage));
    $offset = ($page - 1) * $perPage;
    $whereStatus = app_build_user_photo_status_where($status);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM photos p WHERE p.user_id = :user_id AND {$whereStatus}");
    $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT p.*, u.username, r.username AS reviewer_name
        FROM photos p
        INNER JOIN users u ON p.user_id = u.id
        LEFT JOIN users r ON p.reviewer_id = r.id
        WHERE p.user_id = :user_id AND {$whereStatus}
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'has_more' => ($offset + count($rows)) < $total,
        'items' => array_map(static function (array $row): array {
            return app_photo_payload($row, false);
        }, $rows),
    ];
}

function app_fetch_user_likes(PDO $pdo, int $userId, int $page, int $perPage): array
{
    $page = max(1, $page);
    $perPage = max(1, min(60, $perPage));
    $offset = ($page - 1) * $perPage;

    $countStmt = $pdo->prepare('SELECT COUNT(*)
        FROM photo_likes l
        INNER JOIN photos p ON l.photo_id = p.id
        WHERE l.user_id = :user_id AND p.approved = 1');
    $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT p.*, u.username
        FROM photo_likes l
        INNER JOIN photos p ON l.photo_id = p.id
        INNER JOIN users u ON p.user_id = u.id
        WHERE l.user_id = :user_id AND p.approved = 1
        ORDER BY l.created_at DESC
        LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'has_more' => ($offset + count($rows)) < $total,
        'items' => array_map(static function (array $row): array {
            return app_photo_payload($row, true);
        }, $rows),
    ];
}

function app_normalize_pending_photo_updates(array $input): array
{
    $fields = [];
    $map = [
        'title' => 'title',
        'category' => 'category',
        'aircraft_model' => 'aircraft_model',
        'registration_number' => 'registration_number',
        'shooting_time' => '`拍摄时间`',
        'shooting_location' => '`拍摄地点`',
        'camera' => 'Cam',
        'lens_model' => 'Lens',
        'shutter' => 'Shutter',
    ];

    foreach ($map as $key => $column) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $value = trim((string) $input[$key]);
        if ($key === 'registration_number' || $key === 'shooting_location') {
            $value = strtoupper($value);
        }
        $fields[$column] = $value;
    }

    foreach (['allow_use', 'FocalLength', 'ISO'] as $key) {
        if (array_key_exists($key, $input)) {
            $fields[$key] = trim((string) $input[$key]) === '' ? null : (int) $input[$key];
        }
    }
    if (array_key_exists('aperture', $input)) {
        $fields['F'] = trim((string) $input['aperture']) === '' ? null : (float) $input['aperture'];
    }

    return $fields;
}

function app_update_pending_photo(PDO $pdo, int $userId, int $photoId, array $input): array
{
    $stmt = $pdo->prepare('SELECT id, approved FROM photos WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute([
        ':id' => $photoId,
        ':user_id' => $userId,
    ]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        app_fail('图片不存在', 404);
    }
    if ((int) $photo['approved'] !== 0) {
        app_fail('只有待审核图片可以修改', 422);
    }

    $updates = app_normalize_pending_photo_updates($input);
    if ($updates === []) {
        app_fail('没有可更新的字段', 422);
    }

    $setParts = [];
    $params = [':id' => $photoId, ':user_id' => $userId];
    $index = 0;
    foreach ($updates as $column => $value) {
        $param = ':v' . $index++;
        $setParts[] = "{$column} = {$param}";
        $params[$param] = $value;
    }

    $sql = 'UPDATE photos SET ' . implode(', ', $setParts) . ' WHERE id = :id AND user_id = :user_id';
    $update = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $update->bindValue($key, $value, PDO::PARAM_INT);
        } elseif ($value === null) {
            $update->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $update->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $update->execute();

    return app_fetch_photo_detail($pdo, $photoId, $userId) ?? [];
}

function app_delete_photo_files(string $filename): void
{
    if ($filename === '') {
        return;
    }

    foreach ([__DIR__ . '/../uploads/' . $filename, __DIR__ . '/../uploads/o/' . $filename] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function app_delete_user_photo(PDO $pdo, int $userId, int $photoId, string $titleConfirm): void
{
    $stmt = $pdo->prepare('SELECT id, title, filename FROM photos WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute([
        ':id' => $photoId,
        ':user_id' => $userId,
    ]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        app_fail('图片不存在', 404);
    }
    if (trim((string) $photo['title']) !== trim($titleConfirm)) {
        app_fail('确认标题不匹配', 422);
    }

    $pdo->beginTransaction();
    try {
        $deleteLikes = $pdo->prepare('DELETE FROM photo_likes WHERE photo_id = :photo_id');
        $deleteLikes->bindValue(':photo_id', $photoId, PDO::PARAM_INT);
        $deleteLikes->execute();

        $deleteAppeals = $pdo->prepare('DELETE FROM appeals WHERE photo_id = :photo_id');
        $deleteAppeals->bindValue(':photo_id', $photoId, PDO::PARAM_INT);
        $deleteAppeals->execute();

        $deletePhoto = $pdo->prepare('DELETE FROM photos WHERE id = :id AND user_id = :user_id');
        $deletePhoto->execute([
            ':id' => $photoId,
            ':user_id' => $userId,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_fail('删除图片失败: ' . $e->getMessage(), 500);
    }

    app_delete_photo_files((string) $photo['filename']);
}

function app_fetch_category_counts(PDO $pdo, string $type, int $page, int $perPage): array
{
    $column = $type === 'aircraft_model' ? 'modes' : 'operator';
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $countStmt = $pdo->query("SELECT COUNT(*) FROM (SELECT {$column} FROM airplane WHERE COALESCE({$column}, '') <> '' GROUP BY {$column}) t");
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT {$column} AS label, COUNT(*) AS item_count
        FROM airplane
        WHERE COALESCE({$column}, '') <> ''
        GROUP BY {$column}
        ORDER BY item_count DESC, label ASC
        LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'has_more' => ($offset + count($rows)) < $total,
        'items' => array_map(static function (array $row): array {
            return [
                'label' => (string) $row['label'],
                'count' => (int) $row['item_count'],
            ];
        }, $rows),
    ];
}

function app_fetch_map_clusters(PDO $pdo, string $level, array $filters): array
{
    $params = [];
    $where = photo_feed_build_where_clause($filters, $params);
    $groupMap = [
        'country' => ['group' => 'a.iso_country', 'name' => 'a.iso_country'],
        'province' => ['group' => 'a.iso_region', 'name' => 'a.iso_region'],
        'city' => ['group' => 'a.municipality', 'name' => 'a.municipality'],
    ];
    if (!isset($groupMap[$level])) {
        app_fail('不支持的地图聚合级别', 422);
    }

    $groupBy = $groupMap[$level]['group'];
    $nameExpr = $groupMap[$level]['name'];
    $sql = "SELECT {$groupBy} AS cluster_key, {$nameExpr} AS cluster_name,
            AVG(a.latitude_deg) AS latitude_deg, AVG(a.longitude_deg) AS longitude_deg,
            COUNT(*) AS photo_count
        FROM photos p
        INNER JOIN users u ON p.user_id = u.id
        INNER JOIN airport a ON p.`拍摄地点` = a.iata_code" . $where . "
        AND COALESCE({$groupBy}, '') <> ''
        GROUP BY {$groupBy}, {$nameExpr}
        ORDER BY photo_count DESC, cluster_name ASC";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return array_map(static function (array $row) use ($level): array {
        return [
            'level' => $level,
            'key' => (string) $row['cluster_key'],
            'name' => (string) $row['cluster_name'],
            'latitude' => $row['latitude_deg'] !== null ? (float) $row['latitude_deg'] : null,
            'longitude' => $row['longitude_deg'] !== null ? (float) $row['longitude_deg'] : null,
            'photo_count' => (int) $row['photo_count'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
