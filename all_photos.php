<?php
require 'db_connect.php';
session_start();

// 如果用户已登录，更新活动时间
if (isset($_SESSION['user_id'])) {
    // 假设这个函数在stats_functions.php中定义，如果没有可以注释掉
    // updateUserActivity($_SESSION['user_id']);
}

// 获取搜索关键词
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// 构建查询语句
$sql = "SELECT p.*, u.username FROM photos p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.approved = 1";
if ($search_keyword != '') {
    $sql .= " AND (p.title LIKE :keyword OR u.username LIKE :keyword OR p.aircraft_model LIKE :keyword )";
}
$sql .= " ORDER BY p.created_at DESC";
try {
    $stmt = $pdo->prepare($sql);
    if ($search_keyword != '') {
        $stmt->bindValue(':keyword', '%' . $search_keyword . '%');
    }
    $stmt->debugDumpParams();
    $stmt->execute();
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "获取图片失败: " . $e->getMessage();
    exit;
}


?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SY Photos - 全部图片</title>
    <style>
        :root {
            --primary: #165DFF;
            --primary-light: #4080FF;
            --primary-dark: #0E42D2;
            --secondary: #69b1ff;
            --accent: #FF7D00;
            --light-bg: #f0f7ff;
            --light-gray: #f5f7fa;
            --medium-gray: #e5e9f2;
            --text-dark: #1d2129;
            --text-medium: #4e5969;
            --text-light: #86909c;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            --border-radius: 8px;
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
            padding: 0;
            overflow-x: hidden;
        }

        /* 滚动行为平滑 */
        html {
            scroll-behavior: smooth;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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

        /* 滚动时导航栏变化 */
        .nav.scrolled {
            padding: 10px 0;
            background-color: rgba(22, 93, 255, 0.95);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .nav-container {
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

        .nav a:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* 导航链接下划线动画 */
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

        .nav a:hover::after {
            width: 100%;
        }

        .page-header {
            padding: 40px 0;
            background: linear-gradient(135deg, var(--primary-light), var(--primary-dark));
            color: white;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2.2rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .page-description {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 800px;
        }

        .filter-container {
            background-color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 40px;
            transition: var(--transition);
        }

        .filter-container:hover {
            box-shadow: var(--hover-shadow);
        }

        .filter-header {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filter-title {
            font-size: 1.2rem;
            color: var(--primary-dark);
            font-weight: 600;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-medium);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22, 93, 255, 0.1);
        }

        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 50px;
            transition: var(--transition);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(22, 93, 255, 0.3);
            white-space: nowrap;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(22, 93, 255, 0.4);
        }

        .btn-reset {
            background-color: var(--light-gray);
            color: var(--text-medium);
            box-shadow: none;
        }

        .btn-reset:hover {
            background-color: var(--medium-gray);
            color: var(--text-dark);
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }

        .photo-item {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .photo-item:hover {
            transform: translateY(-10px);
            box-shadow: var(--hover-shadow);
        }

        .photo-category {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: rgba(22, 93, 255, 0.9);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            z-index: 10;
            transition: var(--transition);
        }

        .photo-item:hover .photo-category {
            background-color: var(--accent);
            transform: scale(1.1);
        }

        .photo-img-container {
            height: 220px;
            overflow: hidden;
            position: relative;
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease, filter 0.5s ease;
        }

        .photo-item:hover img {
            transform: scale(1.1);
            filter: brightness(1.05);
        }

        .photo-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .photo-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--primary-dark);
            transition: var(--transition);
            line-height: 1.4;
        }

        .photo-item:hover .photo-title {
            color: var(--primary);
        }

        .photo-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.9rem;
            color: var(--text-medium);
            margin-top: auto;
        }

        .photo-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .photo-meta i {
            color: var(--primary-light);
            width: 16px;
            text-align: center;
        }

        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .no-results i {
            font-size: 4rem;
            color: var(--text-light);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-results h3 {
            font-size: 1.5rem;
            color: var(--text-medium);
            margin-bottom: 15px;
        }

        .no-results p {
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 50px 0;
        }

        .pagination-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: white;
            color: var(--text-medium);
            text-decoration: none;
            transition: var(--transition);
            box-shadow: var(--card-shadow);
        }

        .pagination-link:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
        }

        .pagination-link.active {
            background-color: var(--primary);
            color: white;
            font-weight: bold;
        }

        .footer {
            background-color: var(--primary-dark);
            color: white;
            padding: 60px 0 30px;
            margin-top: 50px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-logo {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-logo i {
            font-size: 2rem;
        }

        .footer-desc {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .footer-title {
            font-size: 1.2rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 40px;
            height: 3px;
            background-color: var(--accent);
            border-radius: 2px;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            transition: var(--transition);
        }

        .social-links a:hover {
            background-color: var(--accent);
            transform: translateY(-3px) rotate(10deg);
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        /* 返回顶部按钮 */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: var(--transition);
            opacity: 0;
            visibility: hidden;
            z-index: 99;
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .back-to-top {
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
        }

        .results-count {
            margin-bottom: 20px;
            color: var(--text-medium);
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .form-group {
                width: 100%;
            }

            .photo-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-title::after {
                left: 50%;
                transform: translateX(-50%);
            }

            .social-links {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 30px 0;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .page-description {
                font-size: 1rem;
            }

            .filter-container {
                padding: 15px;
            }

            .photo-grid {
                grid-template-columns: 1fr;
            }

            .pagination-link {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
        }

        /* 动画效果 */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
    <!-- 引入Font Awesome图标库 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php include __DIR__ . '/src/nav.php'; ?>

    <div class="page-header">
        <div class="container header-content">


        </div>
    </div>

    <div class="container">
        <div class="search-container fade-in">
            <!-- 搜索框上方添加标题 -->
            <div class="search-header">SY Photos - 收藏每片云端照片</div>

            <form action="all_photos.php" method="GET" class="search-form">
                <input type="text" name="search" class="search-input"
                    placeholder="搜索图片（支持标题、作者、机型、拍摄地点）"
                    value="<?php echo htmlspecialchars($search_keyword ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> 搜索
                </button>
            </form>
            <p class="search-hint">示例：748、PEK、摄影师小李</p>
        </div>
    </div>


    <div class="container">


        <?php
        try {

            // 构建查询
            $sql = "
SELECT p.*, u.username
FROM photos p
JOIN users u ON p.user_id = u.id
WHERE p.approved = 1
";

            $params = [];

            if ($search_keyword !== '') {
                $sql .= " AND (
        p.title LIKE :keyword
        OR u.username LIKE :keyword
        OR p.aircraft_model LIKE :keyword
    )";
                $params[':keyword'] = '%' . $search_keyword . '%';
            }

            $sql .= " ORDER BY p.created_at DESC";

            // 查询图片
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 查询总数（去掉 ORDER BY）
            $countSql = "
SELECT COUNT(*)
FROM photos p
JOIN users u ON p.user_id = u.id
WHERE p.approved = 1
";

            if ($search_keyword !== '') {
                $countSql .= " AND (
        p.title LIKE :keyword
        OR u.username LIKE :keyword
        OR p.aircraft_model LIKE :keyword
    )";
            }

            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalPhotos = $countStmt->fetchColumn();

            echo '<div class="results-count fade-in">找到 ' . $totalPhotos . ' 张符合条件的照片</div>';


            if (count($photos) > 0) {
                echo '<div class="photo-grid">';

                $counter = 0;
                foreach ($photos as $photo) {
                    $counter++;
                    // 为每个图片项添加延迟出现的效果
                    $delay = ($counter % 6) * 0.1;
                    echo '<div class="photo-item fade-in" style="transition-delay: ' . $delay . 's">';
                    echo '<span class="photo-category">' . htmlspecialchars($photo['category']) . '</span>';
                    echo '<a href="photo_detail.php?id=' . $photo['id'] . '">';
                    echo '<div class="photo-img-container">';
                    echo '<img src="uploads/' . htmlspecialchars($photo['filename']) . '" alt="' . htmlspecialchars($photo['title']) . '" loading="lazy">';
                    echo '</div>';
                    echo '</a>';
                    echo '<div class="photo-info">';
                    echo '<h3 class="photo-title">' . htmlspecialchars($photo['title']) . '</h3>';
                    echo '<div class="photo-meta">';
                    echo '<span><i class="fas fa-user"></i> 作者: ' . htmlspecialchars($photo['username']) . '</span>';
                    echo '<span><i class="fas fa-plane"></i> 型号: ' . htmlspecialchars($photo['aircraft_model']) . '</span>';
                    echo '<span><i class="fas fa-calendar-alt"></i> 日期: ' . date('Y-m-d', strtotime($photo['created_at'])) . '</span>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }

                echo '</div>';
            } else {
                // 没有找到结果
                echo '<div class="no-results fade-in">';
                echo '<i class="fas fa-search"></i>';
                echo '<h3>未找到符合条件的图片</h3>';
                echo '<p>尝试调整筛选条件，或者浏览其他类别的航空摄影作品。</p>';
                echo '<a href="all_photos.php" class="btn" style="margin-top: 20px;"><i class="fas fa-th"></i> 查看全部图片</a>';
                echo '</div>';
            }
        } catch (PDOException $e) {
            echo '<div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: var(--border-radius); margin-bottom: 30px;">';
            echo '获取图片失败: ' . $e->getMessage();
            echo '</div>';
        }
        ?>

        
    </div>

    <?php include __DIR__ . '/src/footer.php'; ?>

    <!-- 返回顶部按钮 -->
    <div class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </div>

    <script>
        // 导航栏滚动效果
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('mainNav');
            const backToTop = document.getElementById('backToTop');

            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
                backToTop.classList.add('show');
            } else {
                nav.classList.remove('scrolled');
                backToTop.classList.remove('show');
            }

            // 滚动时触发元素淡入效果
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('visible');
                }
            });
        });

        // 返回顶部功能
        document.getElementById('backToTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // 页面加载完成后初始化动画
        window.addEventListener('load', function() {
            // 触发初始滚动检查以显示可见元素
            window.dispatchEvent(new Event('scroll'));
        });
    </script>
</body>

</html>