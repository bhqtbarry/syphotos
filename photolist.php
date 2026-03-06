<?php
require 'db_connect.php';
session_start();

function buildPhotoListWhereClause(string $iataCode, int $userId, array &$params): string
{
    $where = " WHERE p.approved = 1";

    if ($iataCode !== '') {
        $where .= " AND p.`拍摄地点` = :iatacode";
        $params[':iatacode'] = $iataCode;
    }

    if ($userId > 0) {
        $where .= " AND p.user_id = :userid";
        $params[':userid'] = $userId;
    }

    return $where;
}

function fetchPhotoPage(PDO $pdo, string $iataCode, int $userId, int $limit, int $offset): array
{
    $params = [];
    $where = buildPhotoListWhereClause($iataCode, $userId, $params);
    $sql = "SELECT p.*, u.username
            FROM photos p
            INNER JOIN users u ON p.user_id = u.id" . $where . "
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':userid' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchPhotoTotal(PDO $pdo, string $iataCode, int $userId): int
{
    $params = [];
    $where = buildPhotoListWhereClause($iataCode, $userId, $params);
    $sql = "SELECT COUNT(*)
            FROM photos p
            INNER JOIN users u ON p.user_id = u.id" . $where;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':userid' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function renderPhotoCards(array $photos): string
{
    ob_start();
    foreach ($photos as $photo):
        ?>
        <a class="photolist-card" href="photo_detail.php?id=<?php echo (int) $photo['id']; ?>" title="<?php echo htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
            <img src="uploads/<?php echo htmlspecialchars($photo['filename'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
        </a>
        <?php
    endforeach;

    return (string) ob_get_clean();
}

$iataCode = isset($_GET['iatacode']) ? strtoupper(trim($_GET['iatacode'])) : '';
$userId = isset($_GET['userid']) && is_numeric($_GET['userid']) ? (int) $_GET['userid'] : 0;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && (int) $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$photosPerPage = 30;
$offset = ($page - 1) * $photosPerPage;

$photos = [];
$errorMessage = '';
$totalPhotos = 0;
$hasMore = false;

try {
    $totalPhotos = fetchPhotoTotal($pdo, $iataCode, $userId);
    $photos = fetchPhotoPage($pdo, $iataCode, $userId, $photosPerPage, $offset);
    $hasMore = ($offset + count($photos)) < $totalPhotos;
} catch (PDOException $e) {
    $errorMessage = '获取图片失败: ' . $e->getMessage();
}

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => $errorMessage === '',
        'html' => $errorMessage === '' ? renderPhotoCards($photos) : '',
        'hasMore' => $errorMessage === '' ? $hasMore : false,
        'count' => count($photos),
        'error' => $errorMessage,
    ], JSON_UNESCAPED_UNICODE);
    exit;
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
            align-items: start;
            background: #dfeeff;
        }

        .photolist-card {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 16 / 10;
            overflow: hidden;
            background: #dfeeff;
        }

        .photolist-card img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
            transition: transform 0.25s ease;
        }

        .photolist-card:hover img {
            transform: scale(1.02);
        }

        .photolist-empty,
        .photolist-error,
        .photolist-loading {
            margin: 20px 16px;
            padding: 14px 16px;
            border-radius: 10px;
            background: #ffffff;
            color: #4e5969;
            text-align: center;
        }

        .photolist-error {
            color: #b42318;
            background: #fff1f3;
        }

        .photolist-loading {
            margin-top: 12px;
            margin-bottom: 0;
        }

        .photolist-loading[hidden] {
            display: none;
        }

        .photolist-action {
            display: block;
            width: calc(100% - 32px);
            margin: 12px 16px 0;
            padding: 14px 16px;
            border: 0;
            border-radius: 10px;
            background: #165dff;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .photolist-action:hover {
            background: #0e42d2;
            transform: translateY(-1px);
        }

        .photolist-action.is-end {
            background: #7aa7e8;
        }

        .photolist-sentinel {
            height: 1px;
        }

        @media (min-width: 768px) {
            .photolist-header {
                padding: 24px 24px 18px;
            }

            .photolist-grid {
                grid-template-columns: repeat(5, 1fr);
            }

            .photolist-card {
                aspect-ratio: 16 / 10;
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
                共 <?php echo $totalPhotos; ?> 张图片
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
            <section class="photolist-grid" id="photolistGrid"><?php echo renderPhotoCards($photos); ?></section>
            <div class="photolist-loading" id="photolistLoading" hidden>正在加载更多图片...</div>
            <button class="photolist-action <?php echo $hasMore ? '' : 'is-end'; ?>" id="photolistAction" type="button"><?php echo $hasMore ? '继续加载' : '已经到底了，点击回到顶部'; ?></button>
            <div class="photolist-sentinel" id="photolistSentinel"></div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/src/footer.php'; ?>

    <?php if ($errorMessage === '' && !empty($photos)): ?>
        <script>
            (function () {
                const grid = document.getElementById('photolistGrid');
                const sentinel = document.getElementById('photolistSentinel');
                const loading = document.getElementById('photolistLoading');
                const action = document.getElementById('photolistAction');

                if (!grid || !sentinel || !action) {
                    return;
                }

                let currentPage = <?php echo $page; ?>;
                let isLoading = false;
                let hasMore = <?php echo $hasMore ? 'true' : 'false'; ?>;
                let loadFailed = false;
                const baseUrl = new URL(window.location.href);

                function setState() {
                    loading.hidden = !isLoading;
                    action.disabled = isLoading;
                    action.textContent = hasMore ? (isLoading ? '正在加载...' : '继续加载') : '已经到底了';
                    if (loadFailed && !isLoading && hasMore) {
                        action.textContent = '继续加载';
                    }
                    action.classList.toggle('is-end', !hasMore);
                }

                async function loadMore() {
                    if (isLoading || !hasMore) {
                        return;
                    }

                    isLoading = true;
                    loadFailed = false;
                    setState();

                    const nextUrl = new URL(baseUrl);
                    nextUrl.searchParams.set('ajax', '1');
                    nextUrl.searchParams.set('page', String(currentPage + 1));

                    try {
                        const response = await fetch(nextUrl.toString(), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.error || '加载失败');
                        }

                        if (data.html) {
                            grid.insertAdjacentHTML('beforeend', data.html);
                            currentPage += 1;
                        }

                        hasMore = Boolean(data.hasMore);
                        loadFailed = false;
                    } catch (error) {
                        loadFailed = true;
                        action.textContent = error.message || '继续加载';
                    } finally {
                        isLoading = false;
                        setState();
                    }
                }

                setState();

                action.addEventListener('click', () => {
                    if (hasMore) {
                        loadMore();
                        return;
                    }

                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });

                if (!hasMore) {
                    return;
                }

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            loadMore();
                        }
                    });
                }, {
                    rootMargin: '600px 0px'
                });

                observer.observe(sentinel);
            })();
        </script>
    <?php endif; ?>
</body>
</html>
