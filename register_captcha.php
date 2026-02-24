<?php
session_start();

$codeLength = 5;
$characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$code = '';
for ($i = 0; $i < $codeLength; $i++) {
    $code .= $characters[random_int(0, strlen($characters) - 1)];
}

$_SESSION['register_code'] = $code;

$width = 140;
$height = 45;
$image = imagecreatetruecolor($width, $height);

$bgColor = imagecolorallocate($image, 240, 247, 255);
$textColor = imagecolorallocate($image, 30, 30, 30);
$noiseColor = imagecolorallocate($image, 180, 200, 230);

imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

// Add noise lines
for ($i = 0; $i < 4; $i++) {
    imageline(
        $image,
        random_int(0, $width),
        random_int(0, $height),
        random_int(0, $width),
        random_int(0, $height),
        $noiseColor
    );
}

// Add noise dots
for ($i = 0; $i < 80; $i++) {
    imagesetpixel($image, random_int(0, $width), random_int(0, $height), $noiseColor);
}

$x = 15;
$y = ($height / 2) - 8;
for ($i = 0; $i < strlen($code); $i++) {
    $fontSize = 5; // Built-in font
    $offsetY = $y + random_int(-4, 4);
    imagestring($image, $fontSize, $x, $offsetY, $code[$i], $textColor);
    $x += 22;
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

imagepng($image);
imagedestroy($image);
