<?php
require 'db_connect.php';
require 'src/helpers.php';
require 'src/i18n.php';
require_once __DIR__ . '/src/photo_feed_service.php';
session_start();

// 初始化变量
$photo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$photo = null;
$is_liked = false;
$reviewer_name = t('photo_detail_unreviewed');
// 确定用户权限状态
$is_sys_admin = isset($_SESSION['sys_admin']) && $_SESSION['sys_admin'];
$is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) || $is_sys_admin;
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

function normalize_photo_detail_field(string $fieldKey, ?string $rawValue): array
{
    $value = trim((string) $rawValue);

    switch ($fieldKey) {
        case 'category':
            return ['category', $value, PDO::PARAM_STR];
        case 'aircraft_model':
            return ['aircraft_model', $value, PDO::PARAM_STR];
        case 'registration_number':
            return ['registration_number', $value, PDO::PARAM_STR];
        case 'shooting_time':
            return ['`拍摄时间`', $value, PDO::PARAM_STR];
        case 'shooting_location':
            return ['`拍摄地点`', strtoupper($value), PDO::PARAM_STR];
        case 'Cam':
            return ['Cam', $value, PDO::PARAM_STR];
        case 'Lens':
            return ['Lens', $value, PDO::PARAM_STR];
        case 'FocalLength':
            return ['FocalLength', $value === '' ? null : (int) $value, $value === '' ? PDO::PARAM_NULL : PDO::PARAM_INT];
        case 'ISO':
            return ['ISO', $value === '' ? null : (int) $value, $value === '' ? PDO::PARAM_NULL : PDO::PARAM_INT];
        case 'F':
            return ['F', $value === '' ? null : (float) $value, $value === '' ? PDO::PARAM_NULL : PDO::PARAM_STR];
        case 'Shutter':
            return ['Shutter', $value, PDO::PARAM_STR];
        case 'score':
            $score = $value === '' ? null : (int) $value;
            if ($score !== null && ($score < 1 || $score > 5)) {
                throw new InvalidArgumentException('Invalid score value.');
            }
            return ['score', $score, $score === null ? PDO::PARAM_NULL : PDO::PARAM_INT];
        default:
            throw new InvalidArgumentException('Invalid field key.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_photo_field']) && $photo_id > 0) {
    if (!$is_sys_admin) {
        $_SESSION['like_error'] = t('photo_detail_details_permission_denied');
    } else {
        try {
            $fieldKey = (string) ($_POST['field_key'] ?? '');
            [$column, $fieldValue, $pdoType] = normalize_photo_detail_field($fieldKey, $_POST['field_value'] ?? null);
            $stmt = $pdo->prepare("UPDATE photos SET {$column} = :field_value WHERE id = :id");
            $stmt->bindValue(':field_value', $fieldValue, $pdoType);
            $stmt->bindValue(':id', $photo_id, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['like_success'] = t('photo_detail_info_saved');
        } catch (InvalidArgumentException $e) {
            $_SESSION['like_error'] = t('photo_detail_invalid_value');
        } catch (PDOException $e) {
            $_SESSION['like_error'] = t('photo_detail_action_failed_prefix') . $e->getMessage();
        }
    }

    header("Location: photo_detail.php?id=" . $photo_id);
    exit;
}

// 处理点赞请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like']) && $photo_id > 0) {
    // 检查用户是否登录
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['like_error'] = t('photo_detail_like_login_required');
    } else {
        try {
            // 检查是否已点赞
            $stmt = $pdo->prepare("SELECT id FROM photo_likes WHERE user_id = :user_id AND photo_id = :photo_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':photo_id', $photo_id);
            $stmt->execute();

            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                // 取消点赞
                $delete_stmt = $pdo->prepare("DELETE FROM photo_likes WHERE user_id = :user_id AND photo_id = :photo_id");
                $delete_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $delete_stmt->bindParam(':photo_id', $photo_id);
                $delete_stmt->execute();

                // 减少点赞数
                $update_stmt = $pdo->prepare("UPDATE photos SET likes = likes - 1 WHERE id = :id");
                $update_stmt->bindParam(':id', $photo_id);
                $update_stmt->execute();

                $_SESSION['like_success'] = t('photo_detail_like_removed');
            } else {
                // 执行点赞
                $insert_stmt = $pdo->prepare("INSERT INTO photo_likes (user_id, photo_id, created_at) VALUES (:user_id, :photo_id, NOW())");
                $insert_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $insert_stmt->bindParam(':photo_id', $photo_id);
                $insert_stmt->execute();

                // 增加点赞数
                $update_stmt = $pdo->prepare("UPDATE photos SET likes = likes + 1 WHERE id = :id");
                $update_stmt->bindParam(':id', $photo_id);
                $update_stmt->execute();

                $_SESSION['like_success'] = t('photo_detail_like_success');
            }
        } catch (PDOException $e) {
            $_SESSION['like_error'] = t('photo_detail_action_failed_prefix') . $e->getMessage();
        }
    }

    // 重定向防止表单重复提交
    header("Location: photo_detail.php?id=" . $photo_id);
    exit;
}

// 获取图片详情并更新浏览量（限制同一用户1小时内只增一次）
if ($photo_id > 0) {
    try {
        // 检查是否已浏览过（1小时内）
        $view_key = "viewed_photo_" . $photo_id;
        if (!isset($_COOKIE[$view_key])) {
            // 根据用户权限更新浏览量
            if ($is_admin) {
                // 管理员：更新所有图片浏览量
                $update_views_sql = "UPDATE photos SET views = views + 1 WHERE id = :id";
            } elseif ($current_user_id > 0) {
                // 登录用户：更新自己的图片或已通过的图片
                $update_views_sql = "UPDATE photos SET views = views + 1 WHERE id = :id AND (approved = 1 OR user_id = :user_id)";
            } else {
                // 未登录：只更新已通过的图片
                $update_views_sql = "UPDATE photos SET views = views + 1 WHERE id = :id AND approved = 1";
            }

            $stmt = $pdo->prepare($update_views_sql);
            $stmt->bindParam(':id', $photo_id);
            if (!$is_admin && $current_user_id > 0) {
                $stmt->bindParam(':user_id', $current_user_id);
            }
            $stmt->execute();

            // 设置Cookie（1小时内不再计数）
            setcookie($view_key, '1', time() + 3600, '/');
        }

        // 根据用户权限获取图片详情（包含审核员信息）
        if ($is_admin) {
            // 管理员：查看所有图片，包含审核员信息
            $sql = "SELECT p.*, u.username as author_name, r.username as reviewer_name 
                    FROM photos p 
                    JOIN users u ON p.user_id = u.id 
                    LEFT JOIN users r ON p.reviewer_id = r.id 
                    WHERE p.id = :id";
        } elseif ($current_user_id > 0) {
            // 登录用户：查看自己的图片或已通过的图片
            $sql = "SELECT p.*, u.username as author_name, r.username as reviewer_name 
                    FROM photos p 
                    JOIN users u ON p.user_id = u.id 
                    LEFT JOIN users r ON p.reviewer_id = r.id 
                    WHERE p.id = :id AND (p.approved = 1 OR p.user_id = :user_id)";
        } else {
            // 未登录：只看已通过的图片
            $sql = "SELECT p.*, u.username as author_name, r.username as reviewer_name 
                    FROM photos p 
                    JOIN users u ON p.user_id = u.id 
                    LEFT JOIN users r ON p.reviewer_id = r.id 
                    WHERE p.id = :id AND p.approved = 1";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $photo_id);
        if (!$is_admin && $current_user_id > 0) {
            $stmt->bindParam(':user_id', $current_user_id);
        }
        $stmt->execute();

        $photo = $stmt->fetch(PDO::FETCH_ASSOC);

        // 获取审核员名称
        if ($photo && !empty($photo['reviewer_name'])) {
            $reviewer_name = $photo['reviewer_name'];
        } elseif ($photo && $photo['approved'] == 1) {
            $reviewer_name = t('photo_detail_auto_reviewed');
        }

        // 检查是否已点赞
        if (isset($_SESSION['user_id']) && $photo) {
            $stmt = $pdo->prepare("SELECT id FROM photo_likes WHERE user_id = :user_id AND photo_id = :photo_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':photo_id', $photo_id);
            $stmt->execute();

            $is_liked = $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
        }
    } catch (PDOException $e) {
        echo "<div class='error-message'>" . h(t('photo_detail_load_failed_prefix')) . h($e->getMessage()) . "</div>";
    }
}

// 处理图片不存在或无权查看的情况
if (!$photo) {
    echo "<div class='error-container'>";
    echo "<div class='error-icon'><i class='fas fa-exclamation-triangle'></i></div>";
        echo "<h2>" . h(t('photo_detail_not_found')) . "</h2>";
        echo "<a href='index.php' class='back-btn'>" . h(t('photo_detail_back_home')) . "</a>";
    echo "</div>";
    exit;
}

// 获取当前页面URL（用于分享）
$current_url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$authorProfileUrl = 'author.php?userid=' . (int) ($photo['user_id'] ?? 0);
$reviewerProfileUrl = !empty($photo['reviewer_id']) ? 'author.php?userid=' . (int) $photo['reviewer_id'] : '';
$airlineFilterUrl = 'photolist.php?airline=' . urlencode((string) ($photo['category'] ?? ''));
$aircraftFilterUrl = 'photolist.php?aircraft_model=' . urlencode((string) ($photo['aircraft_model'] ?? ''));
$camFilterUrl = 'photolist.php?cam=' . urlencode((string) ($photo['Cam'] ?? ''));
$lensFilterUrl = 'photolist.php?lens=' . urlencode((string) ($photo['Lens'] ?? ''));
$registrationFilterUrl = 'photolist.php?registration_number=' . urlencode((string) ($photo['registration_number'] ?? ''));
$locationFilterUrl = 'photolist.php?iatacode=' . urlencode((string) ($photo['拍摄地点'] ?? ''));

$editableInfoRows = [
    [
        'key' => 'category',
        'icon' => 'fas fa-folder',
        'label' => t('photo_detail_airline'),
        'value' => (string) ($photo['category'] ?? ''),
        'display_html' => '<a href="' . htmlspecialchars($airlineFilterUrl) . '">' . htmlspecialchars((string) ($photo['category'] ?? '')) . '</a>',
        'input_type' => 'text',
    ],
    [
        'key' => 'aircraft_model',
        'icon' => 'fas fa-plane',
        'label' => t('photo_detail_model'),
        'value' => (string) ($photo['aircraft_model'] ?? ''),
        'display_html' => '<a href="' . htmlspecialchars($aircraftFilterUrl) . '">' . htmlspecialchars((string) ($photo['aircraft_model'] ?? '')) . '</a>',
        'input_type' => 'text',
    ],
    [
        'key' => 'registration_number',
        'icon' => 'fas fa-hashtag',
        'label' => t('photo_detail_registration'),
        'value' => (string) ($photo['registration_number'] ?? ''),
        'display_html' => '<a href="' . htmlspecialchars($registrationFilterUrl) . '">' . htmlspecialchars((string) ($photo['registration_number'] ?? '')) . '</a>',
        'input_type' => 'text',
    ],
    [
        'key' => 'shooting_time',
        'icon' => 'fas fa-clock',
        'label' => t('photo_detail_shot_time'),
        'value' => (string) ($photo['拍摄时间'] ?? ''),
        'display_html' => htmlspecialchars((string) ($photo['拍摄时间'] ?? '')),
        'input_type' => 'text',
    ],
    [
        'key' => 'shooting_location',
        'icon' => 'fas fa-map-marker-alt',
        'label' => t('photo_detail_location'),
        'value' => (string) ($photo['拍摄地点'] ?? ''),
        'display_html' => '<a href="' . htmlspecialchars($locationFilterUrl) . '">' . htmlspecialchars((string) ($photo['拍摄地点'] ?? '')) . '</a>',
        'input_type' => 'text',
    ],
    [
        'key' => 'Cam',
        'icon' => 'fas fa-camera',
        'label' => t('photo_detail_camera'),
        'value' => (string) ($photo['Cam'] ?? ''),
        'display_html' => !empty($photo['Cam']) ? '<a href="' . htmlspecialchars($camFilterUrl) . '">' . htmlspecialchars((string) $photo['Cam']) . '</a>' : h(t('photo_detail_not_filled')),
        'input_type' => 'text',
    ],
    [
        'key' => 'Lens',
        'icon' => 'fas fa-camera',
        'label' => t('photo_detail_lens'),
        'value' => (string) ($photo['Lens'] ?? ''),
        'display_html' => !empty($photo['Lens']) ? '<a href="' . htmlspecialchars($lensFilterUrl) . '">' . htmlspecialchars((string) $photo['Lens']) . '</a>' : h(t('photo_detail_not_filled')),
        'input_type' => 'text',
    ],
    [
        'key' => 'FocalLength',
        'icon' => 'fas fa-camera',
        'label' => t('photo_detail_focal_length'),
        'value' => (string) ($photo['FocalLength'] ?? ''),
        'display_html' => $photo['FocalLength'] !== null && $photo['FocalLength'] !== '' ? htmlspecialchars((string) $photo['FocalLength']) . ' mm' : h(t('photo_detail_not_filled')),
        'input_type' => 'number',
        'input_attrs' => 'step="1"',
    ],
    [
        'key' => 'ISO',
        'icon' => 'fas fa-camera',
        'label' => 'ISO',
        'value' => (string) ($photo['ISO'] ?? ''),
        'display_html' => $photo['ISO'] !== null && $photo['ISO'] !== '' ? htmlspecialchars((string) $photo['ISO']) : h(t('photo_detail_not_filled')),
        'input_type' => 'number',
        'input_attrs' => 'step="1"',
    ],
    [
        'key' => 'F',
        'icon' => 'fas fa-camera',
        'label' => t('photo_detail_aperture'),
        'value' => (string) ($photo['F'] ?? ''),
        'display_html' => $photo['F'] !== null && $photo['F'] !== '' ? 'f/' . htmlspecialchars((string) $photo['F']) : h(t('photo_detail_not_filled')),
        'input_type' => 'number',
        'input_attrs' => 'step="0.1"',
    ],
    [
        'key' => 'Shutter',
        'icon' => 'fas fa-camera',
        'label' => t('photo_detail_shutter'),
        'value' => (string) ($photo['Shutter'] ?? ''),
        'display_html' => $photo['Shutter'] !== '' ? htmlspecialchars((string) $photo['Shutter']) : h(t('photo_detail_not_filled')),
        'input_type' => 'text',
    ],
    [
        'key' => 'score',
        'icon' => 'fas fa-star',
        'label' => t('photo_detail_score'),
        'value' => (string) ($photo['score'] ?? ''),
        'display_html' => $photo['score'] !== null && $photo['score'] !== '' ? htmlspecialchars((string) $photo['score']) : h(t('photo_detail_not_filled')),
        'input_type' => 'select',
        'options' => ['' => '-', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'],
    ],
];

function render_photo_detail_editor_input(array $row): string
{
    $value = (string) ($row['value'] ?? '');
    $attrs = trim((string) ($row['input_attrs'] ?? ''));

    if (($row['input_type'] ?? 'text') === 'select') {
        $html = '<select name="field_value" class="info-edit-input">';
        foreach (($row['options'] ?? []) as $optionValue => $optionLabel) {
            $selected = $value === (string) $optionValue ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string) $optionValue) . '"' . $selected . '>' . htmlspecialchars((string) $optionLabel) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    return '<input type="' . htmlspecialchars((string) ($row['input_type'] ?? 'text')) . '" name="field_value" class="info-edit-input" value="' . htmlspecialchars($value) . '"' . ($attrs !== '' ? ' ' . $attrs : '') . '>';
}

function fetch_photo_detail_related_items(PDO $pdo, string $field, string|int $value, int $photoId): array
{
    if ($value === '' || $value === 0) {
        return [];
    }

    $allowedFields = [
        'registration_number' => 'p.registration_number',
        'location' => 'p.`拍摄地点`',
        'user_id' => 'p.user_id',
    ];

    if (!isset($allowedFields[$field])) {
        return [];
    }

    $sql = "SELECT p.id, p.title, p.filename, u.username
            FROM photos p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.approved = 1
              AND p.id <> :photo_id
              AND {$allowedFields[$field]} = :field_value
            ORDER BY p.score DESC, p.`拍摄时间` DESC, p.created_at DESC
            LIMIT 3";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':photo_id', $photoId, PDO::PARAM_INT);
    if ($field === 'user_id') {
        $stmt->bindValue(':field_value', (int) $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':field_value', (string) $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $item['thumb_url'] = photo_feed_build_asset_url($item, 'thumb');
        $items[] = $item;
    }

    return $items;
}

$relatedColumns = [
    [
        'title' => t('photo_detail_same_registration'),
        'link' => $registrationFilterUrl,
        'items' => fetch_photo_detail_related_items($pdo, 'registration_number', (string) ($photo['registration_number'] ?? ''), $photo_id),
    ],
    [
        'title' => t('photo_detail_same_airport'),
        'link' => $locationFilterUrl,
        'items' => fetch_photo_detail_related_items($pdo, 'location', (string) ($photo['拍摄地点'] ?? ''), $photo_id),
    ],
    [
        'title' => t('photo_detail_same_author'),
        'link' => 'photolist.php?userid=' . (int) ($photo['user_id'] ?? 0),
        'items' => fetch_photo_detail_related_items($pdo, 'user_id', (int) ($photo['user_id'] ?? 0), $photo_id),
    ],
];
?>
<!DOCTYPE html>
<html lang="<?php echo h(current_locale()); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta property="og:title" content="<?php echo htmlspecialchars($photo['title']); ?> - SY Photos">
    <meta property="og:image"
        content="http://<?php echo $_SERVER['HTTP_HOST']; ?>/uploads/o/<?php echo htmlspecialchars($photo['filename']); ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <title><?php echo htmlspecialchars($photo['title']); ?> - SY Photos</title>
    <style>
        :root {
            --primary: #165DFF;
            --primary-light: #4080FF;
            --primary-dark: #0E42D2;
            --accent: #FF7D00;
            --light-bg: #f0f7ff;
            --text-dark: #1d2129;
            --text-medium: #4e5969;
            --text-light: #86909c;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.6;
            padding-bottom: 50px;
        }

        .nav {
            background-color: var(--primary);
            padding: 15px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: var(--transition);
        }

        .nav.scrolled {
            padding: 10px 0;
            background-color: rgba(22, 93, 255, 0.95);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo-icon {
            font-size: 1.8rem;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 4px;
            transition: var(--transition);
            font-weight: 500;
            position: relative;
        }

        .nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: white;
            transition: var(--transition);
        }

        .nav a:hover {
            background-color: var(--primary-dark);
        }

        .nav a:hover::after {
            width: 100%;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .page-title {
            margin: 30px 0 20px;
            font-size: 1.8rem;
            color: var(--primary-dark);
            position: relative;
            padding-left: 15px;
        }

        .page-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 5px;
            bottom: 5px;
            width: 4px;
            background-color: var(--accent);
            border-radius: 2px;
        }

        .photo-detail {
            background-color: #f7fbff;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin: 0 auto 30px;
            max-width: 1200px;
            transition: var(--transition);
        }

        .detail-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
            gap: 18px;
            padding: 20px;
            align-items: start;
        }

        .summary-card,
        .info-card,
        .related-column {
            background-color: white;
            border: 1px solid #e6edff;
            box-shadow: 0 6px 18px rgba(22, 93, 255, 0.06);
        }

        .summary-card {
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .photo-header {
            padding: 24px 24px 18px;
            border-bottom: 1px solid #f0f2f5;
            background-color: white;
            position: relative;
        }

        .photo-title {
            font-size: 1.8rem;
            color: var(--primary-dark);
            margin-bottom: 12px;
            line-height: 1.25;
            position: relative;
            padding-right: 120px;
        }

        .status-tag {
            position: absolute;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-approved {
            background-color: #f6ffed;
            color: #007e33;
            border: 1px solid #b7eb8f;
        }

        .status-unapproved {
            background-color: #fff2f2;
            color: #cc0000;
            border: 1px solid #ffccc7;
        }

        .photo-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 16px;
            color: var(--text-medium);
            font-size: 0.92rem;
            line-height: 1.4;
            padding: 8px 0 10px;
            border-bottom: 1px dashed #eee;
            margin-bottom: 10px;
        }

        .photo-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .photo-meta span:hover {
            color: var(--primary);
        }

        .photo-meta i {
            color: var(--primary-light);
            width: 18px;
            text-align: center;
        }

        .info-edit-input {
            width: 100%;
            border: 1px solid #c9d8ff;
            border-radius: 8px;
            padding: 8px 10px;
            font: inherit;
            line-height: 1.4;
            color: var(--text-dark);
            box-sizing: border-box;
            background-color: #fff;
        }

        .info-edit-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22, 93, 255, 0.12);
        }

        .info-item.is-editable .info-value-wrap {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            width: 100%;
        }

        .info-item.is-editable .info-value {
            flex: 1;
        }

        .info-inline-editor {
            flex: 1;
        }

        .info-inline-actions {
            margin-top: 8px;
            display: flex;
            gap: 8px;
            justify-content: flex-start;
        }

        .details-save-btn {
            border: none;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border-radius: 999px;
            padding: 10px 18px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .details-save-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(22, 93, 255, 0.16);
        }

        .details-save-btn:disabled {
            cursor: not-allowed;
            opacity: 0.55;
            transform: none;
            box-shadow: none;
        }

        .info-edit-toggle,
        .info-cancel-btn {
            border: 1px solid #c9d8ff;
            background: #fff;
            color: var(--primary-dark);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .info-edit-toggle:hover,
        .info-cancel-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #f4f8ff;
        }

        .info-item.is-editing .info-value,
        .info-item.is-editing .info-edit-toggle {
            display: none;
        }

        .info-item.is-editing .info-inline-editor[hidden] {
            display: block;
        }

        .info-inline-editor[hidden] {
            display: none;
        }

        .photo-content {
            padding: 30px;
            display: grid;
            /* grid-template-columns: 1fr 350px; */
            gap: 30px;
            justify-items: center;
        }

        @media (max-width: 1400px) {
            .photo-content {
                grid-template-columns: 1fr;
                justify-items: center;
            }
        }

        .photo-image-container {
            margin: 0 auto;
            max-width: 100%;
            width: fit-content;
            border-radius: var(--border-radius);
            overflow: hidden;
            background-color: #f8f9fa;
            position: relative;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .photo-image-container:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .photo-image {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.6s ease;
        }

        .photo-image-container:hover .photo-image {
            transform: scale(1.03);
        }

        .image-zoom-indicator {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
            opacity: 0;
            transition: var(--transition);
        }

        .photo-image-container:hover .image-zoom-indicator {
            opacity: 1;
        }

        .photo-sidebar {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .info-card {
            border-radius: var(--border-radius);
            padding: 20px;
            transition: var(--transition);
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(22, 93, 255, 0.08);
        }

        .info-title {
            font-size: 1.2rem;
            color: var(--primary-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-title i {
            color: var(--primary);
        }

        .info-list {
            list-style: none;
        }

        .info-list li {
            margin-bottom: 0;
            padding: 9px 0;
            border-bottom: 1px dashed #e0e6ff;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .info-list li:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-medium);
            flex: 0 0 112px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.92rem;
        }

        .info-label i {
            color: var(--primary-light);
            font-size: 0.9rem;
        }

        .info-value {
            flex: 1;
            word-break: break-word;
            padding-left: 10px;
            position: relative;
            font-size: 0.95rem;
            line-height: 1.45;
        }

        .info-value::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background-color: var(--primary-light);
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 18px 24px 22px;
        }

        .like-btn {
            background-color: #f7fbff;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 12px 16px;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .like-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(22, 93, 255, 0.1), transparent);
            transition: 0.5s;
        }

        .like-btn:hover::before {
            left: 100%;
        }

        .like-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(22, 93, 255, 0.2);
        }

        .like-btn.liked {
            background-color: var(--primary);
            color: white;
        }

        .like-btn.liked:hover {
            background-color: #0a47cc;
        }

        .like-btn i {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .like-btn:hover i {
            transform: scale(1.2);
        }

        .share-title {
            font-size: 0.9rem;
            color: var(--text-medium);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .share-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(128px, 1fr));
            gap: 10px;
        }

        .share-btn {
            width: 100%;
            min-height: 42px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.92rem;
            font-weight: 600;
            gap: 8px;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            border: none;
            cursor: pointer;
            padding: 0 14px;
        }

        .share-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: 0.3s;
        }

        .share-btn:hover::before {
            left: 100%;
        }

        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.15);
        }

        .share-weixin {
            background-color: #07C160;
        }

        .share-facebook {
            background-color: #1877F2;
        }

        .share-x {
            background-color: #111111;
        }

        .share-whatsapp {
            background-color: #25D366;
        }

        .share-telegram {
            background-color: #229ED9;
        }

        .share-linkedin {
            background-color: #0A66C2;
        }

        .share-reddit {
            background-color: #FF4500;
        }

        .share-weibo {
            background-color: #E6162D;
        }

        .share-qq {
            background-color: #12B7F5;
        }

        .share-link {
            background-color: var(--primary);
        }

        .operation-buttons {
            display: flex;
            gap: 10px;
        }

        .edit-btn,
        .delete-btn {
            flex: 1;
            padding: 12px;
            border-radius: var(--border-radius);
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .edit-btn {
            background-color: #f0f7ff;
            color: var(--primary);
        }

        .edit-btn:hover {
            background-color: #e6f7ff;
            transform: translateY(-2px);
        }

        .delete-btn {
            background-color: #fff1f0;
            color: #f5222d;
        }

        .delete-btn:hover {
            background-color: #fff2f0;
            transform: translateY(-2px);
        }

        .related-section {
            margin: 60px 0 40px;
            padding-top: 30px;
            border-top: 1px solid #f0f2f5;
        }

        .section-title {
            font-size: 1.6rem;
            color: var(--primary-dark);
            margin-bottom: 25px;
            padding-bottom: 10px;
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--accent);
        }

        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 3px;
            background-color: var(--primary);
            border-radius: 2px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 25px;
        }

        .related-item {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            background-color: white;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .related-item:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }

        .related-img-container {
            height: 180px;
            overflow: hidden;
            position: relative;
        }

        .related-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .related-item:hover .related-img {
            transform: scale(1.1);
        }

        .related-category {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: rgba(22, 93, 255, 0.8);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .related-info {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .related-title {
            font-size: 1.05rem;
            color: var(--text-dark);
            text-decoration: none;
            margin-bottom: 10px;
            line-height: 1.4;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: var(--transition);
        }

        .related-item:hover .related-title {
            color: var(--primary);
        }

        .related-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .related-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .related-columns {
            max-width: 1200px;
            margin: 20px auto 0;
            padding: 0 20px;
        }

        .related-columns-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .related-column {
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .related-column-header {
            display: block;
            padding: 14px 18px;
            font-weight: 700;
            color: var(--primary-dark);
            text-decoration: none;
            border-bottom: 1px solid #e5edf8;
            background: linear-gradient(180deg, #f7fbff 0%, #edf5ff 100%);
        }

        .related-column-header:hover {
            color: var(--primary);
        }

        .related-column-list {
            display: grid;
            gap: 12px;
            padding: 14px;
        }

        .related-thumb-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .related-thumb-card {
            background: #eaf4ff;
            border-radius: 10px;
            overflow: hidden;
        }

        .related-thumb-card img {
            display: block;
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            background: #d9ecff;
        }

        .related-empty {
            padding: 18px 16px;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .alert-message {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin: 0 30px 15px;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
            transform: translateY(0);
            opacity: 1;
        }

        .alert-message.hide {
            transform: translateY(-20px);
            opacity: 0;
            height: 0;
            padding: 0;
            margin: 0;
            overflow: hidden;
        }

        .alert-success {
            background-color: #f0fff4;
            color: #007e33;
            border: 1px solid #c8e6c9;
        }

        .alert-error {
            background-color: #fff4f4;
            color: #cc0000;
            border: 1px solid #ffdddd;
        }

        .error-container {
            max-width: 600px;
            margin: 100px auto;
            text-align: center;
            padding: 40px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .error-icon {
            font-size: 4rem;
            color: var(--accent);
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            background-color: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .back-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(22, 93, 255, 0.2);
        }

        footer {
            background-color: var(--primary-dark);
            color: white;
            padding: 40px 0 20px;
            margin-top: 60px;
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        /* 微信分享弹窗样式 */
        #weixinModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #weixinModal.active {
            opacity: 1;
        }

        #weixinModal .modal-content {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            text-align: center;
            width: 90%;
            max-width: 300px;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        #weixinModal.active .modal-content {
            transform: scale(1);
        }

        #weixinModal h3 {
            margin-bottom: 15px;
            color: var(--primary-dark);
            font-size: 1.2rem;
        }

        #weixinModal .qrcode-container {
            margin: 0 auto;
            width: 200px;
            height: 200px;
            background: white;
            padding: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        #weixinModal img {
            width: 100%;
            height: 100%;
        }

        #weixinModal p {
            margin-top: 15px;
            color: var(--text-medium);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        #weixinModal .close-btn {
            margin-top: 20px;
            padding: 8px 25px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        #weixinModal .close-btn:hover {
            background-color: var(--primary-dark);
        }

        @media (max-width: 900px) {
            .detail-layout {
                grid-template-columns: 1fr;
                padding: 16px;
            }

            .photo-header,
            .action-buttons,
            .info-card {
                padding-left: 16px;
                padding-right: 16px;
            }

            .photo-title {
                font-size: 1.45rem;
                padding-right: 0;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .status-tag {
                position: static;
                transform: none;
                width: fit-content;
            }

            .operation-buttons {
                flex-direction: column;
            }

            .related-columns-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <!-- 引入Font Awesome图标库 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include __DIR__ . '/src/nav.php'; ?>

    <!-- 图片内容和详情 -->
    <div class="photo-content">
        <div class="photo-image-container">
            <a href="uploads/o/<?php echo htmlspecialchars($photo['filename']); ?>" target="_blank"
                title="<?php echo h(t('photo_detail_view_original')); ?>">
                <img src="uploads/o/<?php echo htmlspecialchars($photo['filename']); ?>"
                    alt="<?php echo htmlspecialchars($photo['title']); ?>" class="photo-image">
            </a>
            <div class="image-zoom-indicator">
                <i class="fas fa-search-plus"></i> <?php echo h(t('photo_detail_view_original')); ?>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- <h1 class="page-title">图片详情</h1> -->

        <div class="photo-detail">
            <!-- 提示信息 -->
            <?php if (isset($_SESSION['like_success'])): ?>
                <div class="alert-message alert-success" id="alertMessage">
                    <?php echo $_SESSION['like_success'];
                    unset($_SESSION['like_success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['like_error'])): ?>
                <div class="alert-message alert-error" id="alertMessage">
                    <?php echo $_SESSION['like_error'];
                    unset($_SESSION['like_error']); ?>
                </div>
            <?php endif; ?>

            <!-- 图片标题和基本信息 -->





            <div class="detail-layout">
                <div class="summary-card">
                    <div class="photo-header">
                    <h1 class="photo-title">
                        <?php echo htmlspecialchars($photo['title']); ?>
                        <span
                            class="status-tag <?php echo $photo['approved'] == 1 ? 'status-approved' : 'status-unapproved'; ?>">
                            <?php echo $photo['approved'] == 1 ? h(t('photo_detail_status_approved')) : h(t('photo_detail_status_unapproved')); ?>
                        </span>
                    </h1>

                    <div class="photo-meta">
                        <span><i class="fas fa-user"></i> <?php echo h(t('photo_detail_author')); ?>:
                            <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>">
                                <?php echo htmlspecialchars($photo['author_name']); ?>
                            </a></span>
                        <span><i class="fas fa-user-check"></i> <?php echo h(t('photo_detail_reviewer')); ?>:
                            <?php if ($reviewerProfileUrl !== '' && !empty($photo['reviewer_name'])): ?>
                                <a href="<?php echo htmlspecialchars($reviewerProfileUrl); ?>">
                                    <?php echo htmlspecialchars($reviewer_name); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($reviewer_name); ?>
                            <?php endif; ?></span>
                        <span><i class="fas fa-eye"></i> <?php echo h(t('photo_detail_views')); ?>: <?php echo $photo['views'] ?? 0; ?></span>
                        <span><i class="fas fa-heart"></i> <?php echo h(t('photo_detail_likes')); ?>: <?php echo $photo['likes'] ?? 0; ?></span>
                        <span><i class="fas fa-calendar"></i> <?php echo h(t('photo_detail_uploaded_at')); ?>:
                            <?php echo date('Y-m-d H:i', strtotime($photo['created_at'])); ?></span>
                    </div>
                </div>
                    <div class="action-buttons">
                        <form method="post">
                            <button type="submit" name="like" class="like-btn <?php echo $is_liked ? 'liked' : ''; ?>">
                                <i class="fas fa-heart"></i>
                                <?php echo $is_liked ? h(t('photo_detail_liked')) : h(t('photo_detail_like')); ?>
                                (<?php echo $photo['likes'] ?? 0; ?>)
                            </button>
                        </form>

                        <?php if (isset($_SESSION['user_id']) && $photo['user_id'] == $_SESSION['user_id']): ?>
                            <div class="operation-buttons">
                                <a href="edit_photo.php?id=<?php echo $photo_id; ?>" class="edit-btn">
                                    <i class="fas fa-edit"></i> <?php echo h(t('photo_detail_edit')); ?>
                                </a>
                                <a href="delete_photo.php?id=<?php echo $photo_id; ?>" class="delete-btn"
                                    onclick="return confirm('<?php echo h(t('photo_detail_delete_confirm')); ?>')">
                                    <i class="fas fa-trash"></i> <?php echo h(t('photo_detail_delete')); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div>
                            <div class="share-title"><?php echo h(t('photo_detail_share_to')); ?></div>
                            <div class="share-buttons">
                                <button type="button" class="share-btn share-weixin" data-share="wechat"
                                    title="<?php echo h(t('photo_detail_share_wechat')); ?>">
                                    <i class="fab fa-weixin"></i>
                                    <span><?php echo h(t('photo_detail_share_wechat')); ?></span>
                                </button>
                                <button type="button" class="share-btn share-facebook" data-share="facebook"
                                    title="<?php echo h(t('photo_detail_share_facebook')); ?>">
                                    <i class="fab fa-facebook-f"></i>
                                    <span><?php echo h(t('photo_detail_share_facebook')); ?></span>
                                </button>
                                <button type="button" class="share-btn share-x" data-share="x"
                                    title="<?php echo h(t('photo_detail_share_x')); ?>">
                                    <i class="fab fa-x-twitter"></i>
                                    <span><?php echo h(t('photo_detail_share_x')); ?></span>
                                </button>
                                <button type="button" class="share-btn share-whatsapp" data-share="whatsapp"
                                    title="<?php echo h(t('photo_detail_share_whatsapp')); ?>">
                                    <i class="fab fa-whatsapp"></i>
                                    <span><?php echo h(t('photo_detail_share_whatsapp')); ?></span>
                                </button>
                                <button type="button" class="share-btn share-telegram" data-share="telegram"
                                    title="<?php echo h(t('photo_detail_share_telegram')); ?>">
                                    <i class="fab fa-telegram-plane"></i>
                                    <span><?php echo h(t('photo_detail_share_telegram')); ?></span>
                                </button>
                                <button type="button" class="share-btn share-linkedin" data-share="linkedin"
                                    title="<?php echo h(t('photo_detail_share_linkedin')); ?>">
                                    <i class="fab fa-linkedin-in"></i>
                                    <span><?php echo h(t('photo_detail_share_linkedin')); ?></span>
                                </button>
                                <button type="button" class="share-btn share-reddit" data-share="reddit"
                                    title="<?php echo h(t('photo_detail_share_reddit')); ?>">
                                    <i class="fab fa-reddit-alien"></i>
                                    <span><?php echo h(t('photo_detail_share_reddit')); ?></span>
                                </button>
                                <button type="button" class="share-btn share-weibo" data-share="weibo"
                                    title="<?php echo h(t('photo_detail_share_weibo')); ?>">
                                    <i class="fab fa-weibo"></i>
                                    <span><?php echo h(t('photo_detail_share_weibo')); ?></span>
                                </button>
                                <button type="button" class="share-btn share-qq" data-share="qq"
                                    title="<?php echo h(t('photo_detail_share_qq')); ?>">
                                    <i class="fab fa-qq"></i>
                                    <span><?php echo h(t('photo_detail_share_qq')); ?></span>
                                </button>
                                <button type="button" class="share-btn share-link" data-share="copy"
                                    title="<?php echo h(t('photo_detail_copy_link')); ?>">
                                    <i class="fas fa-link"></i>
                                    <span><?php echo h(t('photo_detail_copy_link')); ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="photo-sidebar">
                    <!-- 图片详细信息 -->
                    <div class="info-card">
                        <h3 class="info-title"><i class="fas fa-info-circle"></i> <?php echo h(t('photo_detail_info')); ?></h3>
                        <ul class="info-list">
                            <?php foreach ($editableInfoRows as $row): ?>
                                <li class="info-item<?php echo $is_sys_admin ? ' is-editable' : ''; ?>" data-field-row="<?php echo h($row['key']); ?>">
                                    <span class="info-label"><i class="<?php echo h($row['icon']); ?>"></i> <?php echo h($row['label']); ?></span>
                                    <div class="info-value-wrap">
                                        <span class="info-value"><?php echo $row['display_html']; ?></span>
                                        <?php if ($is_sys_admin): ?>
                                            <form method="post" class="info-inline-editor" data-inline-editor hidden>
                                                <input type="hidden" name="field_key" value="<?php echo h($row['key']); ?>">
                                                <?php echo render_photo_detail_editor_input($row); ?>
                                                <div class="info-inline-actions">
                                                    <button type="submit" name="save_photo_field" class="details-save-btn">
                                                        <?php echo h(t('photo_detail_save_info')); ?>
                                                    </button>
                                                    <button type="button" class="info-cancel-btn" data-inline-cancel>
                                                        <?php echo h(t('photo_detail_cancel_edit')); ?>
                                                    </button>
                                                </div>
                                            </form>
                                            <button type="button" class="info-edit-toggle" data-inline-edit>
                                                <i class="fas fa-pen"></i> <?php echo h(t('photo_detail_edit')); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            <li>
                                <span class="info-label">
                                    <i class="fas fa-hashtag"></i> <?php echo h(t('photo_detail_location_fr24')); ?>
                                </span>
                                <span class="info-value">
                                    <a href="https://www.flightradar24.com/data/airports/<?php echo urlencode($photo['拍摄地点']); ?>"
                                        target="_blank" rel="noopener noreferrer">
                                        <?php echo htmlspecialchars($photo['拍摄地点']); ?>
                                    </a>
                                </span>
                            </li>
                            <li>
                                <span class="info-label">
                                    <i class="fas fa-hashtag"></i> <?php echo h(t('photo_detail_recent_fr24')); ?>
                                </span>
                                <span class="info-value">
                                    <a href="https://www.flightradar24.com/data/aircraft/<?php echo urlencode($photo['registration_number']); ?>"
                                        target="_blank" rel="noopener noreferrer">
                                        <?php echo htmlspecialchars($photo['registration_number']); ?>
                                    </a>
                                </span>
                            </li>
                            <li>
                                <span class="info-label">
                                    <i class="fas fa-hashtag"></i> <?php echo h(t('photo_detail_jetphotos')); ?>
                                </span>
                                <span class="info-value">
                                    <a href="https://www.jetphotos.com/registration/<?php echo urlencode($photo['registration_number']); ?>"
                                        target="_blank" rel="noopener noreferrer">
                                        <?php echo htmlspecialchars($photo['registration_number']); ?>
                                    </a>
                                </span>
                            </li>
                        </ul>
                    </div>


                </div>
            </div>
        </div>
    </div>

    <section class="related-columns">
        <div class="related-columns-grid">
            <?php foreach ($relatedColumns as $column): ?>
                <div class="related-column">
                    <a class="related-column-header" href="<?php echo h($column['link']); ?>" target="_blank"
                        rel="noopener noreferrer">
                        <?php echo h($column['title']); ?>
                    </a>
                    <?php if (!empty($column['items'])): ?>
                        <div class="related-column-list">
                            <?php foreach ($column['items'] as $item): ?>
                                <a class="related-thumb-link" href="photo_detail.php?id=<?php echo (int) $item['id']; ?>" target="_blank"
                                    rel="noopener noreferrer" title="<?php echo h($item['title']); ?>">
                                    <div class="related-thumb-card">
                                        <img src="<?php echo h($item['thumb_url']); ?>" alt="<?php echo h($item['title']); ?>" loading="lazy">
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="related-empty"><?php echo h(t('photo_detail_no_related')); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>



    <!-- 微信分享二维码弹窗 -->
    <div id="weixinModal">
        <div class="modal-content">
            <h3><?php echo h(t('photo_detail_wechat_share')); ?></h3>
            <div class="qrcode-container">
                <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($current_url); ?>&size=180x180"
                    alt="<?php echo h(t('photo_detail_wechat_share')); ?>">
            </div>
            <p><?php echo nl2br(h(t('photo_detail_wechat_hint'))); ?></p>
            <button class="close-btn" onclick="hideWeixinQrcode()"><?php echo h(t('photo_detail_close')); ?></button>
        </div>
    </div>

    <script>
        const shareUrl = <?php echo json_encode($current_url, JSON_UNESCAPED_UNICODE); ?>;
        const shareTitle = <?php echo json_encode($photo['title'], JSON_UNESCAPED_UNICODE); ?>;
        const shareText = `${shareTitle} - SY Photos`;
        const isMobileBrowser = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const shareTargets = {
            weibo: {
                web: `https://service.weibo.com/share/share.php?url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareTitle)}`
            },
            qq: {
                web: `https://connect.qq.com/widget/shareqq/index.html?url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareTitle)}`
            },
            facebook: {
                web: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`,
                app: `fb://facewebmodal/f?href=${encodeURIComponent(shareUrl)}`
            },
            x: {
                web: `https://x.com/intent/tweet?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(shareText)}`,
                app: `twitter://post?message=${encodeURIComponent(`${shareText} ${shareUrl}`)}`
            },
            whatsapp: {
                web: `https://api.whatsapp.com/send?text=${encodeURIComponent(`${shareText} ${shareUrl}`)}`,
                app: `whatsapp://send?text=${encodeURIComponent(`${shareText} ${shareUrl}`)}`
            },
            telegram: {
                web: `https://t.me/share/url?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(shareText)}`,
                app: `tg://msg_url?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(shareText)}`
            },
            linkedin: {
                web: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(shareUrl)}`
            },
            reddit: {
                web: `https://www.reddit.com/submit?url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareTitle)}`,
                app: `reddit://submit?url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareTitle)}`
            }
        };

        // 导航栏滚动效果
        window.addEventListener('scroll', function () {
            const nav = document.getElementById('mainNav');
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });

        // 自动隐藏提示信息
        setTimeout(() => {
            const alert = document.getElementById('alertMessage');
            if (alert) {
                alert.classList.add('hide');
                // 300ms后完全移除元素
                setTimeout(() => alert.remove(), 300);
            }
        }, 3000);

        // 复制链接功能
        function copyLink() {
            const showCopiedToast = () => {
                const tempAlert = document.createElement('div');
                tempAlert.className = 'alert-message alert-success';
                tempAlert.textContent = <?php echo json_encode(t('photo_detail_link_copied'), JSON_UNESCAPED_UNICODE); ?>;
                document.querySelector('.photo-detail').prepend(tempAlert);

                setTimeout(() => {
                    tempAlert.classList.add('hide');
                    setTimeout(() => tempAlert.remove(), 300);
                }, 2000);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(shareUrl).then(showCopiedToast).catch(() => fallbackCopyLink());
                return;
            }

            fallbackCopyLink();
        }

        function fallbackCopyLink() {
            const textarea = document.createElement('textarea');
            textarea.value = shareUrl;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                document.body.removeChild(textarea);
                const tempAlert = document.createElement('div');
                tempAlert.className = 'alert-message alert-success';
                tempAlert.textContent = <?php echo json_encode(t('photo_detail_link_copied'), JSON_UNESCAPED_UNICODE); ?>;
                document.querySelector('.photo-detail').prepend(tempAlert);
                setTimeout(() => {
                    tempAlert.classList.add('hide');
                    setTimeout(() => tempAlert.remove(), 300);
                }, 2000);
            } catch (err) {
                document.body.removeChild(textarea);
                alert(<?php echo json_encode(t('photo_detail_copy_failed_prefix'), JSON_UNESCAPED_UNICODE); ?> + shareUrl);
            }
        }

        function openShareLink(target) {
            if (target === 'copy') {
                copyLink();
                return;
            }

            if (target === 'wechat') {
                if (isMobileBrowser && navigator.share) {
                    navigator.share({
                        title: shareTitle,
                        text: shareText,
                        url: shareUrl
                    }).catch(() => {
                    });
                    return;
                }

                showWeixinQrcode();
                return;
            }

            const shareTarget = shareTargets[target];
            if (!shareTarget || !shareTarget.web) {
                return;
            }

            if (isMobileBrowser && shareTarget.app) {
                const fallbackTimer = window.setTimeout(() => {
                    window.open(shareTarget.web, '_blank', 'noopener,noreferrer');
                }, 700);

                window.location.href = shareTarget.app;
                window.setTimeout(() => window.clearTimeout(fallbackTimer), 400);
                return;
            }

            window.open(shareTarget.web, '_blank', 'noopener,noreferrer');
        }

        // 显示微信分享二维码
        function showWeixinQrcode() {
            const modal = document.getElementById('weixinModal');
            modal.classList.add('active');
            // 阻止页面滚动
            document.body.style.overflow = 'hidden';
        }

        // 隐藏微信分享二维码
        function hideWeixinQrcode() {
            const modal = document.getElementById('weixinModal');
            modal.classList.remove('active');
            // 恢复页面滚动
            document.body.style.overflow = '';
        }

        // 点击二维码外部关闭弹窗
        document.getElementById('weixinModal').addEventListener('click', function (e) {
            if (e.target === this) {
                hideWeixinQrcode();
            }
        });

        document.querySelectorAll('[data-share]').forEach((button) => {
            button.addEventListener('click', () => openShareLink(button.dataset.share));
        });

        const inlineRows = Array.from(document.querySelectorAll('[data-field-row]'));
        const closeInlineEditor = (row) => {
            row.classList.remove('is-editing');
            const form = row.querySelector('[data-inline-editor]');
            if (form) {
                form.hidden = true;
                form.reset();
            }
        };

        const openInlineEditor = (row) => {
            inlineRows.forEach((item) => {
                if (item !== row) {
                    closeInlineEditor(item);
                }
            });

            row.classList.add('is-editing');
            const form = row.querySelector('[data-inline-editor]');
            if (form) {
                form.hidden = false;
                const input = form.querySelector('input[name="field_value"], select[name="field_value"]');
                if (input) {
                    input.focus();
                    if (typeof input.select === 'function') {
                        input.select();
                    }
                }
            }
        };

        document.querySelectorAll('[data-inline-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('[data-field-row]');
                if (row) {
                    openInlineEditor(row);
                }
            });
        });

        document.querySelectorAll('[data-inline-cancel]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('[data-field-row]');
                if (row) {
                    closeInlineEditor(row);
                }
            });
        });
    </script>
    <?php include __DIR__ . '/src/footer.php'; ?>
</body>

</html>
