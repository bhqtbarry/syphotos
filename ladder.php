<?php
// 数据库连接
require 'db_connect.php';
require 'src/i18n.php';
session_start();

$locale = current_locale();

$sql = "
SELECT
    t1.user_id,
    t1.username,
    t1.ct AS approved,
    t2.ct AS overall,
    t1.ct / NULLIF(t2.ct, 0) AS ratio
FROM (
    SELECT u.id AS user_id, u.username, COUNT(*) AS ct
    FROM www_syphotos_cn.photos p
    JOIN www_syphotos_cn.users u ON p.user_id = u.id
    WHERE p.approved = 1
    GROUP BY u.id, u.username
) t1
LEFT JOIN (
    SELECT u.id AS user_id, u.username, COUNT(*) AS ct
    FROM www_syphotos_cn.photos p
    JOIN www_syphotos_cn.users u ON p.user_id = u.id
    GROUP BY u.id, u.username
) t2 ON t1.user_id = t2.user_id
 where t1.ct > 30
ORDER BY t1.ct DESC
";

$result = [];
try {
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // 建议写日志，不直接 echo
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <!-- 标题居中显示 -->
 
        <title>SY Photos <?php echo h(t('ladder_page_title')); ?></title>     


    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        .top3 {
            font-weight: bold;
            background-color: #fff6d9;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/src/nav.php'; ?>



<h1 align="center"><?php echo h(t('ladder_page_title')); ?></h1>

<div class="container">

<?php if (!empty($result)): ?>
<table>
    <tr>
        <th><?php echo h(t('ladder_rank')); ?></th>
        <th><?php echo h(t('ladder_username')); ?></th>
        <th><?php echo h(t('ladder_approved')); ?></th>
        <th><?php echo h(t('ladder_total')); ?></th>
        <th><?php echo h(t('ladder_pass_rate')); ?></th>
    </tr>

    <?php $rank = 1; ?>
    <?php foreach ($result as $row): ?>
        <tr class="<?= $rank <= 3 ? 'top3' : '' ?>">
            <td><?= $rank ?></td>
            <td><a href="author.php?userid=<?= (int) $row['user_id'] ?>"><?= htmlspecialchars($row['username']) ?></a></td>
            <td><?= $row['approved'] ?></td>
            <td><?= $row['overall'] ?></td>
            <td>
                <?= $row['ratio'] !== null
                    ? round($row['ratio'] * 100, 2) . '%'
                    : '0%' ?>
            </td>
        </tr>
        <?php $rank++; ?>
    <?php endforeach; ?>

</table>
<?php else: ?>
    <p><?php echo h(t('ladder_no_results')); ?></p>
<?php endif; ?>

</div>

<?php include __DIR__ . '/src/footer.php'; ?>

</body>
</html>
