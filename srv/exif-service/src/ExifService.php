<?php
require '../../../db_connect.php';

class ExifService
{
    private array $config;
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->config = $config;

        // the PDO instance is created in db_connect.php
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void
    {



        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error(405, 'Only POST allowed');
        }

        if (!isset($_FILES['file'])) {
            $this->error(400, 'No file uploaded');
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->error(400, 'Upload error');
        }

        if ($file['size'] > $this->config['max_file_size']) {
            $this->error(413, 'File too large');
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $this->config['allowed_mime'], true)) {
            $this->error(415, 'Unsupported file type');
        }

        $cmd = sprintf(
            '%s -json %s',
            escapeshellcmd($this->config['exiftool']),
            escapeshellarg($file['tmp_name'])
        );

        $output = shell_exec($cmd);
        if ($output === null) {
            $this->error(500, 'ExifTool execution failed');
        }

        header('Content-Type: application/json; charset=utf-8');
        // 检查是否带有"GPSPosition"字段，如果有则将其转换为"GPSLatitude"和"GPSLongitude"
        $data = json_decode($output, true);

        $lat = null;
        $lon = null;

        if (isset($data[0]) && is_array($data[0])) {

            // 1️⃣ 优先使用 GPSPosition
            if (!empty($data[0]['GPSPosition']) && is_string($data[0]['GPSPosition'])) {
                $gps = $data[0]['GPSPosition'];

                // 2️⃣ 兜底：拼 GPSLatitude + GPSLongitude
            } elseif (
                !empty($data[0]['GPSLatitude']) && !empty($data[0]['GPSLongitude'])
                && is_string($data[0]['GPSLatitude']) && is_string($data[0]['GPSLongitude'])
            ) {

                $gps = $data[0]['GPSLatitude'] . ', ' . $data[0]['GPSLongitude'];
            } else {
                $gps = null;
            }

            // 3️⃣ 解析 DMS → 十进制度
            if ($gps && preg_match(
                '/(\d+)\s*deg\s*(\d+)\'\s*([\d.]+)"\s*([NS]),\s*(\d+)\s*deg\s*(\d+)\'\s*([\d.]+)"\s*([EW])/',
                $gps,
                $m
            )) {

                // 纬度
                $lat = $m[1] + $m[2] / 60 + $m[3] / 3600;
                if ($m[4] === 'S') {
                    $lat = -$lat;
                }

                // 经度
                $lon = $m[5] + $m[6] / 60 + $m[7] / 3600;
                if ($m[8] === 'W') {
                    $lon = -$lon;
                }
            }
        }

        // 4️⃣ 根据经纬度查最近机场
        if ($lat !== null && $lon !== null) {            
            $airport = $this->findNearestAirport($lat, $lon);      
            if ($airport) {
                $data[0]['NearestAirport'] = $airport;
            }else {
                $data[0]['NearestAirport'] = null;
            }
        }


        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    //经纬度获得最近机场，
    private function findNearestAirport(float $lat, float $lon): ?string
    {
        // 地球半径（km）
        $earthRadius = 6371;

        // 先用方框粗过滤，防止全表扫描
        // 6km 对应的近似度数
        $delta = 0.155;

        $sql = "
        SELECT
            iata_code,
            (
                :R * 2 * ASIN(
                    SQRT(
                        POWER(SIN(RADIANS(latitude_deg - :lat) / 2), 2) +
                        COS(RADIANS(:lat)) *
                        COS(RADIANS(latitude_deg)) *
                        POWER(SIN(RADIANS(longitude_deg - :lon) / 2), 2)
                    )
                )
            ) AS distance_km
        FROM www_syphotos_cn.airport
        WHERE latitude_deg BETWEEN :lat_min AND :lat_max
          AND longitude_deg BETWEEN :lon_min AND :lon_max
          AND iata_code IS NOT NULL
        ORDER BY distance_km ASC
        LIMIT 1
    ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':R'       => $earthRadius,
            ':lat'     => $lat,
            ':lon'     => $lon,
            ':lat_min' => $lat - $delta,
            ':lat_max' => $lat + $delta,
            ':lon_min' => $lon - $delta,
            ':lon_max' => $lon + $delta,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['iata_code'] : null;
    }

    private function error(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
