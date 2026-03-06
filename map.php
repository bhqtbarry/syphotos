<?php
require 'db_connect.php';
require 'src/i18n.php';
session_start();

$locale = current_locale();
$isChineseMap = $locale === 'zh';

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
<html lang="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars(t('map_page_title'), ENT_QUOTES, 'UTF-8'); ?></title>
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

    .leaflet-popup-content {
        font-size: 16px;
        line-height: 1.6;
    }

    .leaflet-control-zoom a {
        font-size: 20px;
        width: 36px;
        height: 36px;
        line-height: 36px;
    }
}

.map-wrapper {
    position: relative;
    width: 100%;
    height: calc(100vh - 120px);
}

.map-pin-wrap {
    position: relative;
    width: 34px;
    height: 44px;
}

.map-pin-count {
    position: absolute;
    top: -16px;
    left: 50%;
    transform: translateX(-50%);
    min-width: 22px;
    padding: 1px 7px;
    border-radius: 999px;
    background: #ffffff;
    color: #d93025;
    border: 1px solid #f2b4b0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    font-size: 12px;
    font-weight: 700;
    line-height: 18px;
    text-align: center;
}

.map-pin-dot {
    position: absolute;
    left: 50%;
    bottom: 0;
    width: 18px;
    height: 18px;
    transform: translateX(-50%);
    border-radius: 50% 50% 50% 0;
    transform-origin: center;
    rotate: -45deg;
    background: #d93025;
    border: 2px solid #ffffff;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.22);
}
</style>

<?php if ($isChineseMap): ?>
<script src="https://api.map.baidu.com/api?v=3.0&ak=HuxBSph2GlKLMGP3R9sWWw34nAh72iTU"></script>
<?php else: ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
</head>

<body>

<?php include __DIR__ . '/src/nav.php'; ?>

<div class="map-wrapper">
    <div id="map"></div>
</div>

<?php include __DIR__ . '/src/footer.php'; ?>

<script>
const airportData = <?= json_encode($airports, JSON_UNESCAPED_UNICODE); ?>;
const mapIataLabel = <?= json_encode(t('map_iata'), JSON_UNESCAPED_UNICODE); ?>;
const mapPhotoCountLabel = <?= json_encode(t('map_photo_count'), JSON_UNESCAPED_UNICODE); ?>;
const mapViewPhotosLabel = <?= json_encode(t('map_view_photos'), JSON_UNESCAPED_UNICODE); ?>;

<?php if ($isChineseMap): ?>
const map = new BMap.Map("map");
map.centerAndZoom(new BMap.Point(105, 35), 5);
map.enableScrollWheelZoom(true);

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
            ${mapIataLabel}：${item.iata_code}<br>
            ${mapPhotoCountLabel}：${item.photoCount}<br>
            <a href="photolist.php?iatacode=${item.iata_code}" style="display:inline-block;margin-top:6px;color:#0066cc">
               ${mapViewPhotosLabel} →
            </a>
        </div>
    `;

    const infoWindow = new BMap.InfoWindow(html);
    marker.addEventListener("click", function () {
        map.openInfoWindow(infoWindow, point);
    });
});
<?php else: ?>
const map = L.map('map', {
    zoomControl: true,
    tap: true
}).setView([20, 0], 2);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18
}).addTo(map);

airportData.forEach(item => {
    const lng = parseFloat(item.longitude_deg);
    const lat = parseFloat(item.latitude_deg);
    if (isNaN(lat) || isNaN(lng)) return;

    const icon = L.divIcon({
        className: '',
        html: `
            <div class="map-pin-wrap">
                <div class="map-pin-count">${item.photoCount}</div>
                <div class="map-pin-dot"></div>
            </div>
        `,
        iconSize: [34, 44],
        iconAnchor: [17, 38],
        popupAnchor: [0, -34]
    });

    const popupHtml = `
        <div style="font-size:15px">
            <strong>${item.iata_code} - ${item.name}</strong><br>
            ${mapIataLabel}: ${item.iata_code}<br>
            ${mapPhotoCountLabel}: ${item.photoCount}<br>
            <a href="photolist.php?iatacode=${item.iata_code}" style="display:inline-block;margin-top:6px;color:#0066cc">
                ${mapViewPhotosLabel} →
            </a>
        </div>
    `;

    L.marker([lat, lng], { icon }).addTo(map).bindPopup(popupHtml);
});
<?php endif; ?>
</script>

</body>
</html>
