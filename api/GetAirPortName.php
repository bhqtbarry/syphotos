<?php
require '../db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$code = $_GET['code'] ?? null;

if (!$code) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing parameter: code'
    ]);
    exit;
}

class GetAirPortName
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getByIataCode(string $iata_code): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT name FROM airport WHERE iata_code = ? LIMIT 1"
        );
        $stmt->execute([$iata_code]);
        return $stmt->fetchColumn() ?: null;
    }
}

try {
    // db_connect.php 里创建的 PDO
    global $pdo;

    $service = new GetAirPortName($pdo);
    $name = $service->getByIataCode($code);

    echo json_encode([
        'iata_code' => $code,
        'airport_name' => $name
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}