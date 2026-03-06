<?php
require 'db_connect.php';
require 'stats_functions.php';
session_start();

$onlineAdminNames = getOnlineAdminNames();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SY Photos - 本站数据统计</title>
    <style>
        .stats-page {
            min-height: 100vh;
            background: #f3f6fb;
        }

        .stats-hero {
            padding: 30px 16px 20px;
            background: linear-gradient(135deg, #165dff, #69b1ff);
            color: #ffffff;
        }

        .stats-title {
            margin: 0;
            font-size: 1.6rem;
        }

        .stats-subtitle {
            margin-top: 8px;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            padding: 18px 16px 0;
        }

        .stats-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 18px 16px;
            box-shadow: 0 10px 28px rgba(22, 93, 255, 0.08);
        }

        .stats-label {
            color: #4e5969;
            font-size: 0.9rem;
        }

        .stats-value {
            margin-top: 8px;
            color: #165dff;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .stats-extra {
            margin-top: 8px;
            color: #4e5969;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        @media (min-width: 768px) {
            .stats-hero {
                padding: 36px 24px 24px;
            }

            .stats-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                padding: 24px 24px 0;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/src/nav.php'; ?>

    <main class="stats-page">
        <section class="stats-hero">
            <h1 class="stats-title">本站数据统计</h1>
            <div class="stats-subtitle">查看注册用户、审核进度、通过图片和在线管理员情况。</div>
        </section>

        <section class="stats-grid">
            <div class="stats-card">
                <div class="stats-label">注册用户</div>
                <div class="stats-value"><?php echo number_format(getTotalUsers()); ?></div>
            </div>
            <div class="stats-card">
                <div class="stats-label">剩余审核张数</div>
                <div class="stats-value"><?php echo number_format(getPendingReviews()); ?></div>
            </div>
            <div class="stats-card">
                <div class="stats-label">通过图片数</div>
                <div class="stats-value"><?php echo number_format(getApprovedPhotosCount()); ?></div>
            </div>
            <div class="stats-card">
                <div class="stats-label">在线管理员</div>
                <div class="stats-value"><?php echo number_format(getOnlineAdmins()); ?></div>
                <div class="stats-extra"><?php echo !empty($onlineAdminNames) ? htmlspecialchars(implode('、', $onlineAdminNames)) : '暂无在线管理员'; ?></div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/src/footer.php'; ?>
</body>
</html>
