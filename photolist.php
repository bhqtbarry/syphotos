<?php
require 'db_connect.php';
session_start();

$iataCode = isset($_GET['iatacode']) ? strtoupper(trim($_GET['iatacode'])) : '';
$userId = isset($_GET['userid']) && is_numeric($_GET['userid']) ? (int) $_GET['userid'] : 0;
$photos = [];
$errorMessage = '';

$sql = "SELECT p.*, u.username
        FROM photos p
        INNER JOIN users u ON p.user_id = u.id
        WHERE p.approved = 1";
$params = [];

if ($iataCode !== '') {
    $sql .= " AND p.`拍摄地点` = :iatacode";
    $params[':iatacode'] = $iataCode;
}

if ($userId > 0) {
    $sql .= " AND p.user_id = :userid";
    $params[':userid'] = $userId;
}

$sql .= " ORDER BY p.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':userid' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = '获取图片失败: ' . $e->getMessage();
}

$pageTitleParts = ['SY Photos 图库'];
if ($iataCode !== '') {
    $pageTitleParts[] = $iataCode;
}
if ($userId > 0) {
    $pageTitleParts[] = '用户 ' . $userId;
}
$pageTitle = implode(' - ', $pageTitleParts);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        .photolist-page {
            background: #f3f6fb;
            min-height: 100vh;
        }

        .photolist-header {
            padding: 18px 16px 14px;
            background: #ffffff;
            border-bottom: 1px solid #e6ebf2;
        }

        .photolist-title {
            margin: 0;
            font-size: 1.15rem;
            color: #1d2129;
        }

        .photolist-meta {
            margin-top: 6px;
            color: #4e5969;
            font-size: 0.92rem;
        }

        .photolist-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            background: #d9dee7;
        }

        .photolist-card {
            position: relative;
            display: block;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            background: #d9dee7;
        }

        .photolist-card img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            transition: transform 0.25s ease;
        }

        .photolist-card:hover img {
            transform: scale(1.03);
        }

        .photolist-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.18), rgba(0, 0, 0, 0));
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .photolist-card:hover::after {
            opacity: 1;
        }

        .photolist-empty,
        .photolist-error {
            margin: 20px 16px;
            padding: 14px 16px;
            border-radius: 10px;
            background: #ffffff;
            color: #4e5969;
        }

        .photolist-error {
            color: #b42318;
            background: #fff1f3;
        }

        @media (min-width: 768px) {
            .photolist-header {
                padding: 24px 24px 18px;
            }

            .photolist-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        @media (min-width: 1200px) {
            .photolist-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/src/nav.php'; ?>

    <main class="photolist-page">
        <section class="photolist-header">
            <h1 class="photolist-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="photolist-meta">
                共 <?php echo count($photos); ?> 张图片
                <?php if ($iataCode !== ''): ?>
                    ，拍摄地点 <?php echo htmlspecialchars($iataCode, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
                <?php if ($userId > 0): ?>
                    ，用户 ID <?php echo $userId; ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($errorMessage !== ''): ?>
            <div class="photolist-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php elseif (empty($photos)): ?>
            <div class="photolist-empty">没有找到符合条件的图片。</div>
        <?php else: ?>
            <section class="photolist-grid">
                <?php foreach ($photos as $photo): ?>
                    <a class="photolist-card" href="photo_detail.php?id=<?php echo (int) $photo['id']; ?>" title="<?php echo htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="uploads/<?php echo htmlspecialchars($photo['filename'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/src/footer.php'; ?>
</body>
</html>
