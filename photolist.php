<?php
require 'db_connect.php';
require 'src/photo_feed_service.php';
session_start();

$filters = photo_feed_normalize_filters($_GET);
$iataCode = $filters['iatacode'];
$userId = $filters['user_id'];
$airline = $filters['airline'];
$aircraftModel = $filters['aircraft_model'];
$cam = $filters['cam'];
$lens = $filters['lens'];
$page = $filters['page'];

$photos = [];
$errorMessage = '';
$totalPhotos = 0;
$hasMore = false;

try {
    $totalPhotos = photo_feed_fetch_total($pdo, $filters);
    $photos = photo_feed_fetch_page($pdo, $filters);
    $offset = ($filters['page'] - 1) * $filters['per_page'];
    $hasMore = ($offset + count($photos)) < $totalPhotos;
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
$filterSummaryParts = [];
if ($airline !== '') {
    $pageTitleParts[] = '航司 ' . $airline;
    $filterSummaryParts[] = '航司 ' . $airline;
}
if ($aircraftModel !== '') {
    $pageTitleParts[] = '机型 ' . $aircraftModel;
    $filterSummaryParts[] = '机型 ' . $aircraftModel;
}
if ($cam !== '') {
    $pageTitleParts[] = '相机 ' . $cam;
    $filterSummaryParts[] = '相机 ' . $cam;
}
if ($lens !== '') {
    $pageTitleParts[] = '镜头 ' . $lens;
    $filterSummaryParts[] = '镜头 ' . $lens;
}
if ($iataCode !== '') {
    $filterSummaryParts[] = '拍摄地点 ' . $iataCode;
}
if ($userId > 0) {
    $filterSummaryParts[] = '用户 ID ' . $userId;
}
$pageTitle = implode(' - ', $pageTitleParts);
$apiAccess = photo_feed_issue_access_signature($filters);
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
            aspect-ratio: 16 / 9;
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
                aspect-ratio: 16 / 9;
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
                <?php if (!empty($filterSummaryParts)): ?>
                    ，<?php echo htmlspecialchars(implode(' / ', $filterSummaryParts), ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($errorMessage !== ''): ?>
            <div class="photolist-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php elseif (empty($photos)): ?>
            <div class="photolist-empty">没有找到符合条件的图片。</div>
        <?php else: ?>
            <section class="photolist-grid" id="photolistGrid"><?php echo photo_feed_render_cards($photos); ?></section>
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
                const apiUrl = new URL('api/photo_feed.php', window.location.href);
                apiUrl.searchParams.set('iatacode', '<?php echo h($iataCode); ?>');
                apiUrl.searchParams.set('userid', '<?php echo $userId; ?>');
                apiUrl.searchParams.set('airline', '<?php echo h($airline); ?>');
                apiUrl.searchParams.set('aircraft_model', '<?php echo h($aircraftModel); ?>');
                apiUrl.searchParams.set('cam', '<?php echo h($cam); ?>');
                apiUrl.searchParams.set('lens', '<?php echo h($lens); ?>');
                apiUrl.searchParams.set('per_page', '<?php echo $filters['per_page']; ?>');
                apiUrl.searchParams.set('expires', '<?php echo $apiAccess['expires']; ?>');
                apiUrl.searchParams.set('sig', '<?php echo h($apiAccess['signature']); ?>');

                function setState() {
                    loading.hidden = !isLoading;
                    action.disabled = isLoading;
                    action.textContent = hasMore ? (isLoading ? '正在加载...' : '继续加载') : '已经到底了，点击回到顶部';
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

                    const nextUrl = new URL(apiUrl);
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
