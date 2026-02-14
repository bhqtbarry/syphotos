<?php

class ExifService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
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
            '%s -json -n %s',
            escapeshellcmd($this->config['exiftool']),
            escapeshellarg($file['tmp_name'])
        );

        $output = shell_exec($cmd);
        if ($output === null) {
            $this->error(500, 'ExifTool execution failed');
        }

        header('Content-Type: application/json; charset=utf-8');
        echo $output;
        exit;
    }

    private function error(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
