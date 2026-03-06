<?php
require 'db_connect.php';
require 'src/helpers.php';
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

.map-pin-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 9px;
    border-radius: 999px;
    background: #ffffff;
    color: #d93025;
    border: 1px solid #f2b4b0;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.16);
    font-size: 13px;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
}

.map-count-marker {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transform: translate(-50%, -50%);
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
    const label = new BMap.Label(String(item.photoCount), {
        position: point,
        offset: new BMap.Size(-16, -14)
    });
    label.setStyle({
        color: '#d93025',
        backgroundColor: '#ffffff',
        border: '1px solid #f3b2ae',
        borderRadius: '999px',
        padding: '4px 10px',
        fontSize: '13px',
        fontWeight: '700',
        lineHeight: '18px',
        boxShadow: '0 3px 10px rgba(0,0,0,0.16)',
        cursor: 'pointer'
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
    label.addEventListener("click", function () {
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
        className: 'map-count-marker',
        html: `<div class="map-pin-count">${item.photoCount}</div>`,
        iconSize: [36, 28],
        iconAnchor: [18, 14],
        popupAnchor: [0, -12]
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
