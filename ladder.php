<?php
// 数据库连接
require 'db_connect.php';

$sql = "
SELECT
    t1.username,
    t1.ct AS approved,
    t2.ct AS overall,
    t1.ct / NULLIF(t2.ct, 0) AS ratio
FROM (
    SELECT u.username, COUNT(*) AS ct
    FROM www_syphotos_cn.photos p
    JOIN www_syphotos_cn.users u ON p.user_id = u.id
    WHERE p.approved = 1
    GROUP BY u.username
) t1
LEFT JOIN (
    SELECT u.username, COUNT(*) AS ct
    FROM www_syphotos_cn.photos p
    JOIN www_syphotos_cn.users u ON p.user_id = u.id
    GROUP BY u.username
) t2 ON t1.username = t2.username
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
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <!-- 标题居中显示 -->
 
        <title>SY Photos 排行榜</title>     


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



<h1 align="center">排行榜</h1>

<div class="container">

<?php if (!empty($result)): ?>
<table>
    <tr>
        <th>排名</th>
        <th>用户名</th>
        <th>已批准</th>
        <th>总数</th>
        <th>通过率</th>
    </tr>

    <?php $rank = 1; ?>
    <?php foreach ($result as $row): ?>
        <tr class="<?= $rank <= 3 ? 'top3' : '' ?>">
            <td><?= $rank ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
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
    <p>无结果</p>
<?php endif; ?>

</div>

<?php include __DIR__ . '/src/footer.php'; ?>

</body>
</html>
