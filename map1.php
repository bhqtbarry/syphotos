<?php
require 'db_connect.php';

$sql = "
SELECT DISTINCT 
    photos.拍摄地点 AS iata_code,
    airport.latitude_deg,
    airport.longitude_deg,
    airport.name,
    COUNT(*) AS photoCount
FROM photos
INNER JOIN airport ON photos.拍摄地点 = airport.iata_code
where photos.approved = 1
GROUP BY photos.拍摄地点
";

$stmt = $pdo->query($sql);
$airports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include __DIR__ . '/src/nav.php'; ?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>机场照片地图</title>

<!-- Leaflet -->
<link
  rel="stylesheet"
  href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
/>

<style>
html, body {
    margin: 0;
    padding: 0;
}

/* nav 和 footer 之间自动撑满 */
.map-wrapper {
    position: relative;
    width: 100%;
    height: calc(100vh - 120px); /* 120px 给 nav + footer 兜底 */
}

/* 地图本体 */
#map {
    width: 100%;
    height: 100%;
}

/* 全屏状态 */
.map-wrapper.fullscreen {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: #000;
}

/* 全屏按钮 */
#fullscreenBtn {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 1000;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 6px 10px;
    cursor: pointer;
    font-size: 14px;
}


/* ===== 手机端 Leaflet 显示优化 ===== */
@media (max-width: 768px) {

    /* Popup 字体整体放大 */
    .leaflet-popup-content {
        font-size: 16px;
        line-height: 1.5;
    }

    /* Popup 外壳 */
    .leaflet-popup-content-wrapper {
        border-radius: 10px;
    }

    /* 放大关闭按钮 */
    .leaflet-popup-close-button {
        font-size: 18px;
        width: 28px;
        height: 28px;
    }

    /* 放大缩放按钮 */
    .leaflet-control-zoom a {
        font-size: 20px;
        width: 36px;
        height: 36px;
        line-height: 36px;
    }

    /* Marker 提示气泡里的蓝字 */
    .leaflet-popup-content span {
        font-size: 15px;
    }

    /* 全屏按钮再大一点 */
    #fullscreenBtn {
        font-size: 18px;
        padding: 10px 14px;
    }
}
</style>
</head>

<body>

<div class="map-wrapper" id="mapWrapper">
    <button id="fullscreenBtn">全屏</button>
    <div id="map"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const airportData = <?php echo json_encode($airports, JSON_UNESCAPED_UNICODE); ?>;

// 初始化地图
const map = L.map('map', {
    zoomControl: true,
    tap: true
}).setView([20, 0], 2);

// 底图
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18
}).addTo(map);

// Marker
airportData.forEach(a => {
    if (!a.latitude_deg || !a.longitude_deg) return;

    const popupHtml = `
        <strong>${a.iata_code} - ${a.name}</strong><br>
        照片数：${a.photoCount}<br>
        <span style="color:#007bff;"><a href="photolist.php?iatacode=${a.iata_code}">点击查看照片</a></span>
    `;

    L.marker([a.latitude_deg, a.longitude_deg])
        .addTo(map)
        .bindPopup(popupHtml);
});

// ===== 全屏按钮逻辑 =====
const btn = document.getElementById('fullscreenBtn');
const wrapper = document.getElementById('mapWrapper');

btn.addEventListener('click', () => {
    wrapper.classList.toggle('fullscreen');

    btn.textContent =
        wrapper.classList.contains('fullscreen') ? '退出全屏' : '全屏';

    // 必须通知 Leaflet 尺寸变了
    setTimeout(() => {
        map.invalidateSize();
    }, 300);
});
</script>

</body>
</html>

<?php include __DIR__ . '/src/footer.php'; ?>