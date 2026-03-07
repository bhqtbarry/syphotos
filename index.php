<?php
require 'db_connect.php';
require 'stats_functions.php';
require 'src/helpers.php';
require 'src/photo_feed_service.php';
require 'src/i18n.php';
session_start();

// 获取最新启用的公告
$announcements = [];
try {
    $stmt = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // 公告查询失败不影响页面主体功能
}

// 如果用户已登录，更新活动时间
if (isset($_SESSION['user_id'])) {
    updateUserActivity($_SESSION['user_id']);
}

// 获取精选图片（用于轮播）
$featured_photos = [];
try {
    $stmt = $pdo->prepare("with t as 
(SELECT p.*, u.username 
                          FROM photos p 
                          INNER JOIN users u ON p.user_id = u.id 
                          WHERE p.approved = 1 AND (p.is_featured = 1 or score =5)
                          ORDER BY p.created_at desc
                          LIMIT 2)
                          select * from t
                          union all(
SELECT p.*, u.username 
                          FROM photos p 
                          INNER JOIN users u ON p.user_id = u.id 
                          WHERE p.approved = 1 AND (p.is_featured = 1 or score =5) and p.id not in (select id from t)
                          ORDER BY RAND()
                          LIMIT 3)"); // 最多5张精选图片用于轮播
    $stmt->execute();
    $featured_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "获取精选图片失败: " . $e->getMessage();
    $featured_photos = [];
}

// 获取最新通过审核的图片（用于首页图片展示区）
$display_photos = [];
$total_display = 12; // 首页展示12张图片
$search_keyword = '';
$search_error = '';

// 处理搜索请求（支持标题、作者、机型、拍摄地点搜索）
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_keyword = trim($_GET['search']);
    try {
        $search_stmt = $pdo->prepare("
(SELECT p.*, u.username 
                                    FROM photos p 
                                    INNER JOIN users u ON p.user_id = u.id 
                                    WHERE p.approved = 1 and score =4                          
                                    ORDER BY p.created_at DESC 
                                    LIMIT 9)
                                    union all
                                    (SELECT p.*, u.username 
                                    FROM photos p 
                                    INNER JOIN users u ON p.user_id = u.id 
                                    WHERE p.approved = 1 and score =3                        
                                    ORDER BY rand()
                                    LIMIT 3 )");

        $search_stmt->execute();
        $display_photos = $search_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 搜索无结果提示
        if (empty($display_photos)) {
            $search_error = "未找到包含「{$search_keyword}」的图片，建议尝试其他关键词";
        }
    } catch (PDOException $e) {
        $error = "搜索图片失败: " . $e->getMessage();
        $display_photos = [];
    }
} else {
    // 无搜索时，默认加载最新通过的图片
    try {
        $stmt = $pdo->prepare("(SELECT p.*, u.username 
                                    FROM photos p 
                                    INNER JOIN users u ON p.user_id = u.id 
                                    WHERE p.approved = 1 and score =4                          
                                    ORDER BY p.created_at DESC 
                                    LIMIT 9)
                                    union all
                                    (SELECT p.*, u.username 
                                    FROM photos p 
                                    INNER JOIN users u ON p.user_id = u.id 
                                    WHERE p.approved = 1 and score =3                        
                                    ORDER BY rand()
                                    LIMIT 3 )");

        $stmt->execute();
        $display_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "获取图片失败: " . $e->getMessage();
        $display_photos = [];
    }
}

?>
<!DOCTYPE html>
<html lang="<?php echo h(current_locale()); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SY Photos - 首页</title>
    <!-- 引入Font Awesome图标库 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .photolist-grid-home {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0;
            align-items: start;
            background: #dfeeff;
        }

        .photolist-grid-home .photolist-card {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            aspect-ratio: 2 / 1;
            overflow: hidden;
            background: #dfeeff;
        }

        .photolist-grid-home .photolist-card img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            object-position: center;
            transition: transform 0.25s ease;
        }

        .photolist-grid-home .photolist-card:hover img {
            transform: scale(1.02);
        }

        @media (min-width: 768px) {
            .photolist-grid-home {
                grid-template-columns: repeat(6, minmax(0, 1fr));
                max-width: 2196px;
                margin: 0 auto;
            }
        }
    </style>
</head>

<body>
    <!-- 弹出式公告 -->
    <?php if (!empty($announcements)): ?>
        <div class="announcement-modal" id="announcementModal">
            <div class="announcement-content">
                <button class="announcement-close" id="announcementClose">&times;</button>

                <div class="announcement-header">
                    <h3 class="announcement-title"><i class="fas fa-bullhorn"></i> <?php echo h(t('index_announcement')); ?></h3>
                    <div class="announcement-date" id="modalAnnouncementDate">
                        <?php echo date('Y-m-d', strtotime($announcements[0]['created_at'])); ?>
                    </div>
                </div>

                <div class="announcement-slider" id="modalAnnouncementSlider">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-slide">
                            <div class="announcement-text">
                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                            </div>
                            <div class="announcement-date">
                                <?php echo date('Y-m-d', strtotime($announcement['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($announcements) > 1): ?>
                    <div class="announcement-pagination" id="announcementPagination">
                        <?php for ($i = 0; $i < count($announcements); $i++): ?>
                            <div class="announcement-dot <?php echo $i == 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></div>
                        <?php endfor; ?>
                    </div>

                    <div class="announcement-actions">
                        <button class="announcement-btn" id="prevAnnouncement">上一条</button>
                        <button class="announcement-btn announcement-btn-primary" id="nextAnnouncement">下一条</button>
                    </div>
                <?php endif; ?>

                <div class="announcement-actions" style="justify-content: center;">
                    <button class="announcement-btn announcement-btn-primary" id="confirmAnnouncement"><?php echo h(t('index_acknowledge')); ?></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 导航栏 -->
    <?php include __DIR__ . '/src/nav.php'; ?>

    <!-- Hero区域（轮播图） -->
    <div class="hero">
        <div class="container">
            <!-- 轮播图容器（使用精选图片） -->
            <?php if (!empty($featured_photos)): ?>
                <div class="featured-carousel fade-in">
                    <div class="carousel-wrapper" id="carouselWrapper">
                        <?php foreach ($featured_photos as $photo): ?>
                            <div class="carousel-slide">
                                <a href="photo_detail.php?id=<?php echo $photo['id']; ?>">
                                    <img src="uploads/o/<?php echo htmlspecialchars($photo['filename']); ?>"
                                        alt="<?php echo htmlspecialchars($photo['title']); ?>">
                                    <span class="featured-badge">本站精选</span>
                                </a>
                                <div class="carousel-caption">
                                    <h3 class="carousel-title"><?php echo htmlspecialchars($photo['title']); ?></h3>
                                    <div class="carousel-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($photo['username']); ?></span>
                                        <span><i class="fas fa-plane"></i> <?php echo htmlspecialchars($photo['aircraft_model']); ?></span>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($photo['拍摄地点']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 轮播控制按钮 -->
                    <button class="carousel-control prev" id="carouselPrev">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="carousel-control next" id="carouselNext">
                        <i class="fas fa-chevron-right"></i>
                    </button>

                    <!-- 轮播指示器 -->
                    <div class="carousel-indicators" id="carouselIndicators">
                        <?php for ($i = 0; $i < count($featured_photos); $i++): ?>
                            <div class="carousel-dot <?php echo $i == 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 搜索框（悬浮在Hero区域下方） -->
    <div class="container">
        <div class="search-container fade-in">
            <!-- 搜索框上方添加标题 -->
            <div class="search-header">SY Photos - 收藏每片云端照片</div>

            <form action="photolist.php" method="GET" class="search-form">
                <input type="text" name="keyword" class="search-input"
                    placeholder="<?php echo h(t('index_search_placeholder')); ?>"
                    value="<?php echo htmlspecialchars($search_keyword ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> <?php echo h(t('index_search_button')); ?>
                </button>
            </form>
            <p class="search-hint"><?php echo h(t('index_search_hint')); ?></p>
        </div>
    </div>

    <!-- 主体内容 -->
    <div class="container">
        <!-- 最新图片区域 -->
        <h2 class="section-title fade-in">
            <i class="fas fa-clock-rotate-left" style="color: var(--primary);"></i> <?php echo h(t('index_latest_works')); ?>
        </h2>

        <div class="photo-grid photolist-grid-home">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php else: ?>
                <?php if (!empty($search_error)): ?>
                    <div class="search-empty">
                        <i class="fas fa-search"></i>
                        <h3><?php echo $search_error; ?></h3>
                        <a href="index.php" style="color: var(--primary); text-decoration: underline; margin-top: 10px; display: inline-block;">
                            返回查看全部最新图片
                        </a>
                    </div>
                <?php elseif (empty($display_photos)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 50px 20px;">
                        <i class="fas fa-images" style="font-size: 3rem; color: var(--text-light); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--text-medium); margin-bottom: 15px;"><?php echo h(t('index_no_photos')); ?></h3>
                        <p style="color: var(--text-light);"><?php echo h(t('index_no_photos_hint')); ?></p>
                    </div>
                <?php else: ?>
                    <?php echo photo_feed_render_cards($display_photos); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 查看更多按钮 -->
        <div class="view-more fade-in">
            <a href="photolist.php">
                <button class="btn"><?php echo h(t('index_view_more')); ?> <i class="fas fa-arrow-right"></i></button>
            </a>
        </div>
    </div>

    <!-- 页脚 -->
    <?php include __DIR__ . '/src/footer.php'; ?>

    <!-- 返回顶部按钮 -->
    <div class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- 手机端快速上传按钮 -->
    <a href="upload.php" class="mobile-upload-btn" title="快速上传图片">
        <i class="fas fa-cloud-upload-alt"></i>
    </a>

    <script>
        (function() {
            const backToTop = document.getElementById('backToTop');
            if (backToTop) {
                backToTop.addEventListener('click', function() {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }

            window.addEventListener('load', function() {
                window.dispatchEvent(new Event('scroll'));

                if (typeof initAnnouncementModal === 'function') {
                    initAnnouncementModal();
                }

                if (typeof initCarousel === 'function') {
                    initCarousel();
                }
            });
        })();
    </script>
</body>

</html>
