<?php

return [
    // exiftool 路径（确认 which exiftool）
    'exiftool' => '/usr/local/bin/exiftool',

    // 单个文件最大尺寸（字节）
    'max_file_size' => 50 * 1024 * 1024, // 50MB

    // 允许的 MIME
    'allowed_mime' => [
        'image/jpeg',
        'image/png',
        'image/heic',
        'image/tiff',
        'image/webp',
        'image/gif',
        'image/cr3',
        'image/raw',
    ],
];
