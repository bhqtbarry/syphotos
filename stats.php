<?php
require 'db_connect.php';
require 'stats_functions.php';
require 'src/helpers.php';
require 'src/i18n.php';
session_start();

$onlineAdminNames = getOnlineAdminNames();
$locale = current_locale();

$isAdmin = isset($_SESSION['user_id']) && !empty($_SESSION['is_admin']);
$adminScoreSummary = [];
$adminScoreSummaryError = null;
if ($isAdmin) {
    $sql = "
SELECT
    adminname,
    SUM(CASE WHEN score = 0 THEN 1 ELSE 0 END) AS `0`,
    SUM(CASE WHEN score = 1 THEN 1 ELSE 0 END) AS `1`,
    SUM(CASE WHEN score = 2 THEN 1 ELSE 0 END) AS `2`,
    SUM(CASE WHEN score = 3 THEN 1 ELSE 0 END) AS `3`,
    SUM(CASE WHEN score = 4 THEN 1 ELSE 0 END) AS `4`,
    SUM(CASE WHEN score = 5 THEN 1 ELSE 0 END) AS `5`,
    count(*) as total
FROM v_all
GROUP BY adminname
order by count(*) desc;
    ";

    try {
        $stmt = $pdo->query($sql);
        $adminScoreSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $adminScoreSummaryError = 'Failed to load admin score summary.';
        $adminScoreSummary = [];
    }
}
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

        .admin-score-section {
            padding: 18px 16px 28px;
        }

        .admin-score-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 18px 16px;
            box-shadow: 0 10px 28px rgba(22, 93, 255, 0.08);
        }

        .admin-score-title {
            margin: 0 0 14px;
            font-size: 1.1rem;
            color: #1d2129;
        }

        .admin-score-note {
            margin: 8px 0 0;
            color: #4e5969;
            font-size: 0.9rem;
        }

        .admin-score-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .admin-score-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }

        .admin-score-table th,
        .admin-score-table td {
            padding: 10px 10px;
            text-align: center;
            border-bottom: 1px solid #e5e9f2;
            white-space: nowrap;
        }

        .admin-score-table th {
            font-size: 0.85rem;
            color: #4e5969;
            font-weight: 700;
            background: #f7faff;
        }

        .admin-score-table td {
            font-size: 0.95rem;
            color: #1d2129;
        }

        .admin-score-table td:first-child,
        .admin-score-table th:first-child {
            text-align: left;
        }

        @media (min-width: 768px) {
            .admin-score-section {
                padding: 24px 24px 36px;
            }

            .admin-score-card {
                padding: 20px 18px;
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

        <?php if ($isAdmin): ?>
            <section class="admin-score-section">
                <div class="admin-score-card">
                    <h2 class="admin-score-title">Admin Score Summary</h2>
                    <?php if (!empty($adminScoreSummaryError)): ?>
                        <div class="admin-score-note"><?php echo h($adminScoreSummaryError); ?></div>
                    <?php elseif (empty($adminScoreSummary)): ?>
                        <div class="admin-score-note">No data.</div>
                    <?php else: ?>
                        <div class="admin-score-table-wrap">
                            <table class="admin-score-table">
                                <thead>
                                    <tr>
                                        <th>Admin</th>
                                        <th>0</th>
                                        <th>1</th>
                                        <th>2</th>
                                        <th>3</th>
                                        <th>4</th>
                                        <th>5</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adminScoreSummary as $row): ?>
                                        <tr>
                                            <td><?php echo h((string)($row['adminname'] ?? '')); ?></td>
                                            <td><?php echo number_format((int)($row['0'] ?? 0)); ?></td>
                                            <td><?php echo number_format((int)($row['1'] ?? 0)); ?></td>
                                            <td><?php echo number_format((int)($row['2'] ?? 0)); ?></td>
                                            <td><?php echo number_format((int)($row['3'] ?? 0)); ?></td>
                                            <td><?php echo number_format((int)($row['4'] ?? 0)); ?></td>
                                            <td><?php echo number_format((int)($row['5'] ?? 0)); ?></td>
                                            <td><?php echo number_format((int)($row['total'] ?? 0)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/src/footer.php'; ?>
</body>
</html>
