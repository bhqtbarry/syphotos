<?php
require 'db_connect.php';
require 'src/i18n.php';
session_start();

if (current_locale() !== 'zh') {
    include __DIR__ . '/map1.php';
    exit;
}

$sql = "
SELECT 
    photos.拍摄地点 AS iata_code,
    airport.latitude_deg,
    airport.longitude_deg,
    airport.name,
    COUNT(*) AS photoCount
FROM photos
INNER JOIN airport ON photos.拍摄地点 = airport.iata_code
WHERE photos.approved = 1
GROUP BY photos.拍摄地点
";

$stmt = $pdo->query($sql);
$airports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>机场分布地图</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
}

#map {
    width: 100%;
    height: calc(100vh - 120px);
}

/* 手机端字体优化 */
@media (max-width: 768px) {
    .BMap_bubble_content {
        font-size: 16px !important;
        line-height: 1.6;
    }
}
</style>

<!-- 百度地图 JS API -->
<script src="https://api.map.baidu.com/api?v=3.0&ak=HuxBSph2GlKLMGP3R9sWWw34nAh72iTU"></script>
</head>

<body>

<?php include __DIR__ . '/src/nav.php'; ?>

<div id="map"></div>

<?php include __DIR__ . '/src/footer.php'; ?>

<script>
const airportData = <?= json_encode($airports, JSON_UNESCAPED_UNICODE); ?>;

// 初始化地图
const map = new BMap.Map("map");
map.centerAndZoom(new BMap.Point(105, 35), 5);
map.enableScrollWheelZoom(true);

// 添加标注
airportData.forEach(item => {
    const lng = parseFloat(item.longitude_deg);
    const lat = parseFloat(item.latitude_deg);

    if (isNaN(lat) || isNaN(lng)) return;

    const point = new BMap.Point(lng, lat);
    const marker = new BMap.Marker(point);
    map.addOverlay(marker);

    const label = new BMap.Label(String(item.photoCount), {
        position: point,
        offset: new BMap.Size(-10, -28)
    });
    label.setStyle({
        color: '#d93025',
        backgroundColor: '#ffffff',
        border: '1px solid #f3b2ae',
        borderRadius: '12px',
        padding: '2px 7px',
        fontSize: '12px',
        fontWeight: '700',
        lineHeight: '18px',
        boxShadow: '0 2px 6px rgba(0,0,0,0.12)'
    });
    map.addOverlay(label);

    const html = `
        <div style="font-size:15px">
            <strong>${item.name}</strong><br>
            IATA：${item.iata_code}<br>
            照片数量：${item.photoCount}<br>
            <a href="photolist.php?iatacode=${item.iata_code}"
               style="display:inline-block;margin-top:6px;color:#0066cc">
               查看照片 →
            </a>
        </div>
    `;

    const infoWindow = new BMap.InfoWindow(html);

    marker.addEventListener("click", function () {
        map.openInfoWindow(infoWindow, point);
    });
});
</script>

</body>
</html>
