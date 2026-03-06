<?php
require 'db_connect.php';
require 'stats_functions.php';
require 'src/helpers.php';
require 'src/i18n.php';
session_start();

$onlineAdminNames = getOnlineAdminNames();
$locale = current_locale();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SY Photos - <?php echo h(t('stats_page_title')); ?></title>
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
            <h1 class="stats-title"><?php echo h(t('stats_page_title')); ?></h1>
            <div class="stats-subtitle"><?php echo h(t('stats_page_subtitle')); ?></div>
        </section>

        <section class="stats-grid">
            <div class="stats-card">
                <div class="stats-label"><?php echo h(t('stats_registered_users')); ?></div>
                <div class="stats-value"><?php echo number_format(getTotalUsers()); ?></div>
            </div>
            <div class="stats-card">
                <div class="stats-label"><?php echo h(t('stats_pending_reviews')); ?></div>
                <div class="stats-value"><?php echo number_format(getPendingReviews()); ?></div>
            </div>
            <div class="stats-card">
                <div class="stats-label"><?php echo h(t('stats_approved_photos')); ?></div>
                <div class="stats-value"><?php echo number_format(getApprovedPhotosCount()); ?></div>
            </div>
            <div class="stats-card">
                <div class="stats-label"><?php echo h(t('stats_online_admins')); ?></div>
                <div class="stats-value"><?php echo number_format(getOnlineAdmins()); ?></div>
                <div class="stats-extra"><?php echo !empty($onlineAdminNames) ? htmlspecialchars(implode('、', $onlineAdminNames)) : h(t('stats_no_online_admins')); ?></div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/src/footer.php'; ?>
</body>
</html>
