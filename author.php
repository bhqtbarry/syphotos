<?php
require 'db_connect.php';
require 'src/photo_feed_service.php';
session_start();

$userId = isset($_GET['userid']) && is_numeric($_GET['userid']) ? (int) $_GET['userid'] : 0;
$errorMessage = '';
$author = null;
$photos = [];
$totalPhotos = 0;
$hasMore = false;
$topAirlines = [];
$topAircraftModels = [];
$topLocations = [];
$topCameras = [];
$topLenses = [];
$filters = photo_feed_normalize_filters([
    'userid' => $userId,
    'page' => 1,
    'per_page' => 30,
]);

if ($userId <= 0) {
    $errorMessage = '缺少有效的用户 ID。';
} else {
    try {
        $author = photo_feed_fetch_user_profile($pdo, $userId);

        if (!$author) {
            $errorMessage = '作者不存在。';
        } else {
            $totalPhotos = photo_feed_fetch_total($pdo, $filters);
            $photos = photo_feed_fetch_page($pdo, $filters);
            $hasMore = count($photos) < $totalPhotos;
            $topAirlines = photo_feed_fetch_top_values($pdo, $userId, 'airline', 'airline');
            $topAircraftModels = photo_feed_fetch_top_values($pdo, $userId, 'aircraft_model', 'aircraft_model');
            $topLocations = photo_feed_fetch_top_values($pdo, $userId, 'location', 'iatacode');
            $topCameras = photo_feed_fetch_top_values($pdo, $userId, 'camera', 'cam');
            $topLenses = photo_feed_fetch_top_values($pdo, $userId, 'lens', 'lens');
        }
    } catch (PDOException $e) {
        $errorMessage = '获取作者信息失败: ' . $e->getMessage();
    }
}

$apiAccess = photo_feed_issue_access_signature($filters);
$pageTitle = $author ? ($author['username'] . ' 的主页 - SY Photos') : '作者主页 - SY Photos';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <style>
        .author-page {
            background: #f3f6fb;
            min-height: 100vh;
        }

        .author-hero {
            padding: 24px 16px 18px;
            background: linear-gradient(135deg, #165dff, #69b1ff);
            color: #ffffff;
        }

        .author-name {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .author-subtitle {
            margin-top: 8px;
            font-size: 0.95rem;
            opacity: 0.92;
        }

        .author-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            padding: 16px;
        }

        .author-card {
            padding: 14px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(22, 93, 255, 0.08);
        }

        .author-card-label {
            color: #4e5969;
            font-size: 0.86rem;
        }

        .author-card-value {
            margin-top: 6px;
            color: #1d2129;
            font-size: 1rem;
            font-weight: 600;
            word-break: break-word;
        }

        .author-top-list {
            margin: 8px 0 0;
            padding: 0;
            list-style: none;
        }

        .author-top-item + .author-top-item {
            margin-top: 8px;
        }

        .author-top-link {
            color: #165dff;
            text-decoration: none;
            font-weight: 600;
        }

        .author-top-link:hover {
            text-decoration: underline;
        }

        .author-top-meta {
            margin-left: 6px;
            color: #4e5969;
            font-size: 0.88rem;
            font-weight: 400;
        }

        .author-section-title {
            padding: 0 16px 12px;
            color: #1d2129;
            font-size: 1.02rem;
            font-weight: 700;
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
            .author-hero {
                padding: 34px 24px 24px;
            }

            .author-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                padding: 24px;
                gap: 16px;
            }

            .author-section-title {
                padding: 0 24px 14px;
            }

            .photolist-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        @media (min-width: 1200px) {
            .author-stats {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }

            .photolist-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/src/nav.php'; ?>

    <main class="author-page">
        <?php if ($errorMessage !== ''): ?>
            <div class="photolist-error"><?php echo h($errorMessage); ?></div>
        <?php else: ?>
            <section class="author-hero">
                <h1 class="author-name"><?php echo h($author['username']); ?></h1>
                <div class="author-subtitle">公开作品 <?php echo (int) $totalPhotos; ?> 张</div>
            </section>

            <section class="author-stats">
                <div class="author-card">
                    <div class="author-card-label">上传图片数</div>
                    <div class="author-card-value"><?php echo (int) ($author['photo_count'] ?? 0); ?></div>
                </div>
                <div class="author-card">
                    <div class="author-card-label">注册时间</div>
                    <div class="author-card-value"><?php echo !empty($author['created_at']) ? h(date('Y-m-d H:i', strtotime($author['created_at']))) : '未知'; ?></div>
                </div>
                <div class="author-card">
                    <div class="author-card-label">最后活跃时间</div>
                    <div class="author-card-value"><?php echo !empty($author['last_active']) ? h(date('Y-m-d H:i', strtotime($author['last_active']))) : '未知'; ?></div>
                </div>
                <div class="author-card">
                    <div class="author-card-label">最常拍的航司</div>
                    <div class="author-card-value">
                        <?php if (empty($topAirlines)): ?>
                            暂无
                        <?php else: ?>
                            <ul class="author-top-list">
                                <?php foreach ($topAirlines as $item): ?>
                                    <li class="author-top-item">
                                        <a class="author-top-link" href="<?php echo h($item['url']); ?>"><?php echo h($item['label']); ?></a>
                                        <span class="author-top-meta"><?php echo $item['count']; ?> 次 / <?php echo rtrim(rtrim(number_format($item['percentage'], 1), '0'), '.'); ?>%</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="author-card">
                    <div class="author-card-label">最常拍的机型</div>
                    <div class="author-card-value">
                        <?php if (empty($topAircraftModels)): ?>
                            暂无
                        <?php else: ?>
                            <ul class="author-top-list">
                                <?php foreach ($topAircraftModels as $item): ?>
                                    <li class="author-top-item">
                                        <a class="author-top-link" href="<?php echo h($item['url']); ?>"><?php echo h($item['label']); ?></a>
                                        <span class="author-top-meta"><?php echo $item['count']; ?> 次 / <?php echo rtrim(rtrim(number_format($item['percentage'], 1), '0'), '.'); ?>%</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="author-card">
                    <div class="author-card-label">最常出现的地点</div>
                    <div class="author-card-value">
                        <?php if (empty($topLocations)): ?>
                            暂无
                        <?php else: ?>
                            <ul class="author-top-list">
                                <?php foreach ($topLocations as $item): ?>
                                    <li class="author-top-item">
                                        <a class="author-top-link" href="<?php echo h($item['url']); ?>"><?php echo h($item['label']); ?></a>
                                        <span class="author-top-meta"><?php echo $item['count']; ?> 次 / <?php echo rtrim(rtrim(number_format($item['percentage'], 1), '0'), '.'); ?>%</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="author-card">
                    <div class="author-card-label">最常用的相机</div>
                    <div class="author-card-value">
                        <?php if (empty($topCameras)): ?>
                            暂无
                        <?php else: ?>
                            <ul class="author-top-list">
                                <?php foreach ($topCameras as $item): ?>
                                    <li class="author-top-item">
                                        <a class="author-top-link" href="<?php echo h($item['url']); ?>"><?php echo h($item['label']); ?></a>
                                        <span class="author-top-meta"><?php echo $item['count']; ?> 次 / <?php echo rtrim(rtrim(number_format($item['percentage'], 1), '0'), '.'); ?>%</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="author-card">
                    <div class="author-card-label">最常用的镜头</div>
                    <div class="author-card-value">
                        <?php if (empty($topLenses)): ?>
                            暂无
                        <?php else: ?>
                            <ul class="author-top-list">
                                <?php foreach ($topLenses as $item): ?>
                                    <li class="author-top-item">
                                        <a class="author-top-link" href="<?php echo h($item['url']); ?>"><?php echo h($item['label']); ?></a>
                                        <span class="author-top-meta"><?php echo $item['count']; ?> 次 / <?php echo rtrim(rtrim(number_format($item['percentage'], 1), '0'), '.'); ?>%</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="author-section-title"><?php echo h($author['username']); ?> 的照片</div>

            <?php if (empty($photos)): ?>
                <div class="photolist-empty">这个作者还没有公开图片。</div>
            <?php else: ?>
                <section class="photolist-grid" id="photolistGrid"><?php echo photo_feed_render_cards($photos); ?></section>
                <div class="photolist-loading" id="photolistLoading" hidden>正在加载更多图片...</div>
                <button class="photolist-action <?php echo $hasMore ? '' : 'is-end'; ?>" id="photolistAction" type="button"><?php echo $hasMore ? '继续加载' : '已经到底了'; ?></button>
                <div class="photolist-sentinel" id="photolistSentinel"></div>
            <?php endif; ?>
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

                if (!grid || !sentinel || !loading || !action) {
                    return;
                }

                let currentPage = 1;
                let isLoading = false;
                let hasMore = <?php echo $hasMore ? 'true' : 'false'; ?>;
                let loadFailed = false;
                const apiUrl = new URL('api/photo_feed.php', window.location.href);
                apiUrl.searchParams.set('userid', '<?php echo $userId; ?>');
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
                    setState();
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
                setState();
            })();
        </script>
    <?php endif; ?>
</body>
</html>
