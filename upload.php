<?php
require 'db_connect.php';
session_start();

// 检查GD库是否可用
if (!extension_loaded('gd') || !function_exists('imagecreatefrompng')) {
    die('错误：服务器未安装或未启用GD库，无法处理图片水印功能。请联系管理员解决。');
}

// 检查是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$max_upload_size = 45 * 1024 * 1024; // 45MB

$watermarkColorOptions = ['white', 'black'];
$watermarkAuthorStyleOptions = ['default', 'simple', 'bold'];
$currentWatermarkColor = (isset($_POST['watermark_color']) && in_array($_POST['watermark_color'], $watermarkColorOptions, true))
    ? $_POST['watermark_color']
    : 'white';
$currentWatermarkAuthorStyle = (isset($_POST['watermark_author_style']) && in_array($_POST['watermark_author_style'], $watermarkAuthorStyleOptions, true))
    ? $_POST['watermark_author_style']
    : 'default';

// 确保uploads目录可写
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
$watermark_dir = $upload_dir . 'watermarks/';
if (!is_dir($watermark_dir)) {
    mkdir($watermark_dir, 0755, true);
}
if (!is_writable($upload_dir)) {
    $error = '上传目录不可写，请检查权限设置';
}

/**
 * 计算图片压缩后的尺寸（前后端共用算法）
 */
function calculateCompressedSize($originalWidth, $originalHeight, $originalSize, $maxSize = 5242880)
{
    // 如果原始大小符合要求，不改变尺寸
    if ($originalSize <= $maxSize) {
        return [
            'width' => $originalWidth,
            'height' => $originalHeight,
            'scale' => 1.0
        ];
    }

    // 计算需要压缩的比例（前后端必须使用相同算法）
    $scale = sqrt($maxSize / $originalSize) * 0.9;
    $newWidth = max(400, intval(round($originalWidth * $scale)));
    $newHeight = max(300, intval(round($originalHeight * $scale)));

    // 确保宽高比例不变
    $aspectRatio = $originalWidth / $originalHeight;
    $newHeight = intval(round($newWidth / $aspectRatio));

    return [
        'width' => $newWidth,
        'height' => $newHeight,
        'scale' => $newWidth / $originalWidth // 精确计算实际缩放比例
    ];
}

/**
 * 图片压缩函数
 */


function getExifFromService(string $filePath): ?array
{
    $ch = curl_init('http://127.0.0.1/srv/exif-service/public/');

    $post = [
        'file' => new CURLFile($filePath)
    ];

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log('EXIF service curl error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    $json = json_decode($response, true);
    if (!is_array($json) || isset($json['error'])) {
        error_log('EXIF service invalid response: ' . $response);
        return null;
    }

    return $json;
}
function compressImage($sourcePath, $destPath, &$compressedInfo, $maxSize = 5242880)
{
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;

    list($width, $height, $type) = $imageInfo;
    $originalSize = filesize($sourcePath);

    // 计算压缩尺寸（使用共用算法）
    $compressedInfo = calculateCompressedSize($width, $height, $originalSize, $maxSize);

    // 存储原始尺寸用于水印计算
    $result = [
        'success' => false,
        'path' => $destPath,
        'original_width' => $width,
        'original_height' => $height,
        'final_width' => $compressedInfo['width'],
        'final_height' => $compressedInfo['height'],
        'scale' => $compressedInfo['scale']
    ];

    // 原始大小符合要求直接复制
    if ($originalSize <= $maxSize) {
        $copyResult = copy($sourcePath, $destPath);
        $result['success'] = $copyResult;
        return $result;
    }

    // 创建图像资源
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($sourcePath);
            imagesavealpha($image, true);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($sourcePath);
            break;
        default:
            return $result;
    }

    // 创建调整大小后的图像
    $newImage = imagecreatetruecolor($compressedInfo['width'], $compressedInfo['height']);

    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
        imagesavealpha($newImage, true);
    }

    // 高质量缩放
    imagecopyresampled(
        $newImage,
        $image,
        0,
        0,
        0,
        0,
        $compressedInfo['width'],
        $compressedInfo['height'],
        $width,
        $height
    );
    imagedestroy($image);

    // 根据格式保存图像
    $saveResult = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $saveResult = imagejpeg($newImage, $destPath, 85);
            break;
        case IMAGETYPE_PNG:
            $saveResult = imagepng($newImage, $destPath, 6);
            break;
        case IMAGETYPE_GIF:
            $saveResult = imagegif($newImage, $destPath);
            break;
    }

    imagedestroy($newImage);

    // 二次检查文件大小，如果仍然过大则降低质量
    if ($saveResult && filesize($destPath) > $maxSize) {
        // 重新创建图像资源进行二次压缩
        switch ($type) {
            case IMAGETYPE_JPEG:
                $newImage2 = imagecreatefromjpeg($destPath);
                $saveResult = imagejpeg($newImage2, $destPath, 70);
                imagedestroy($newImage2);
                break;
            case IMAGETYPE_PNG:
                $newImage2 = imagecreatefrompng($destPath);
                imagesavealpha($newImage2, true);
                $saveResult = imagepng($newImage2, $destPath, 9);
                imagedestroy($newImage2);
                break;
        }
    }

    $result['success'] = $saveResult;
    return $result;
}

/**
 * 添加图片水印函数（使用up1.php同款文字水印：syphotos + @username）
 */
function addWatermark(
    $sourcePath,
    $destPath,
    $originalWidth,
    $finalWidth,
    $watermarkSize = 15,
    $opacity = 80,
    $position = 'bottom-right',
    $xRatio = null,
    $yRatio = null,
    $color = 'white',
    $authorStyle = 'default'
) {
    error_log("开始添加文字/图标水印 - 源: $sourcePath, 目标: $destPath, 大小: $watermarkSize%, 透明度: $opacity%, 位置: $position");

    $scale = $originalWidth > 0 ? ($finalWidth / $originalWidth) : 1;

    if (!file_exists($sourcePath)) {
        return ['status' => false, 'error' => '源图片文件未找到', 'watermark_size_used' => 0];
    }

    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return ['status' => false, 'error' => '无法解析图片信息', 'watermark_size_used' => 0];
    }

    list($width, $height, $type) = $imageInfo;

    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($sourcePath);
            imagesavealpha($image, true);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($sourcePath);
            imagesavealpha($image, true);
            break;
        default:
            return ['status' => false, 'error' => '不支持的图片格式', 'watermark_size_used' => 0];
    }
    imagealphablending($image, true);

    $username = trim((string)($_SESSION['username'] ?? 'photographer')) ?: 'photographer';
    $line1 = 'syphotos';
    $line2 = '@' . $username;

    // 尺寸计算（与前端预览算法保持一致）
    $targetBlockWidth = max(20, min(intval(round($finalWidth * ($watermarkSize / 100))), intval(round($finalWidth * 0.5))));
    $mainFontSize = max(10, (int)round($targetBlockWidth / 4.2));
    $iconFontSize = max(8, (int)round($mainFontSize * 0.8));
    $authorFontSize = max(8, (int)round($mainFontSize * 0.45));
    $lineGap = max(2, (int)round($mainFontSize * 0.25));
    $gapBetween = max(8, (int)round($mainFontSize * 0.18));
    $margin = 20;

    $alpha = (int)max(0, min(127, round(127 * (100 - $opacity) / 100)));
    // 根据用户选择的颜色决定主色与阴影色（白/黑）
    if (isset($color) && $color === 'black') {
        $colorMain = imagecolorallocatealpha($image, 0, 0, 0, $alpha);
        $colorShadow = imagecolorallocatealpha($image, 255, 255, 255, min(127, $alpha + 30));
    } else {
        $colorMain = imagecolorallocatealpha($image, 255, 255, 255, $alpha);
        $colorShadow = imagecolorallocatealpha($image, 0, 0, 0, min(127, $alpha + 30));
    }

    // 字体查找（优先使用仓库内 TTF，否则回退到系统字体）
    $fontPath = null;
    $fontCandidates = [
        __DIR__ . '/fonts/Montserrat-ExtraBold.ttf',
        
        __DIR__ . '/fonts/arial.ttf',
        __DIR__ . '/fonts/msyh.ttf',

        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/msyh.ttc',
        'C:/Windows/Fonts/seguisym.ttf'
    ];
    foreach ($fontCandidates as $c) {
        if (file_exists($c) && is_readable($c)) {
            $fontPath = $c;
            break;
        }
    }

    // 用于飞机图标的候选字体（尝试包含 U+2708 的字体）
    $planeChar = "✈";
        $planeChar = "";
    $iconFontPath = null;
    $iconCandidates = [
        __DIR__ . '/fonts/Montserrat-ExtraBold.ttf',
    ];
    if ($fontPath) {
        $iconCandidates[] = $fontPath; // 优先复用主文字字体，避免字符集不一致
    }
    $iconCandidates = array_merge(
        $iconCandidates,
        [
            __DIR__ . '/fonts/fa-solid-900.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ]
    );
    $checkedFonts = [];
    foreach ($iconCandidates as $c) {
        if (!$c || isset($checkedFonts[$c])) {
            continue;
        }
        $checkedFonts[$c] = true;
        if (file_exists($c) && is_readable($c)) {
            $iconFontPath = $c;
            break;
        }
    }

    // 测量文本尺寸
    if ($fontPath && function_exists('imagettfbbox')) {
        $bboxMain = imagettfbbox($mainFontSize, 0, $fontPath, $line1);
        $bboxAuth = imagettfbbox($authorFontSize, 0, $fontPath, $line2);
        $mainW = max(1, abs($bboxMain[2] - $bboxMain[0]));
        $mainH = max(1, abs($bboxMain[7] - $bboxMain[1]));
        $authW = max(1, abs($bboxAuth[2] - $bboxAuth[0]));
        $authH = max(1, abs($bboxAuth[7] - $bboxAuth[1]));
    } else {
        $mainW = imagefontwidth(5) * strlen($line1);
        $mainH = imagefontheight(5);
        $authW = imagefontwidth(3) * strlen($line2);
        $authH = imagefontheight(3);
    }

    // 飞机图标宽高估算或测量
    if ($iconFontPath && function_exists('imagettfbbox')) {
        $bboxIcon = imagettfbbox($iconFontSize, 0, $iconFontPath, $planeChar);
        $iconW = max(1, abs($bboxIcon[2] - $bboxIcon[0]));
        $iconH = max(1, abs($bboxIcon[7] - $bboxIcon[1]));
    } else {
        $iconW = (int)round($iconFontSize * 0.9);
        $iconH = $iconFontSize;
        $iconFontPath = null; // 标记为无字体图标
    }

    $blockW = $iconW + $gapBetween + max($mainW, $authW);
    $blockH = $mainH + $lineGap + $authH;

    // 计算位置
    $hasCustomRatio = is_numeric($xRatio) && is_numeric($yRatio);
    if ($hasCustomRatio) {
        $xRatio = max(0, min(1, (float)$xRatio));
        $yRatio = max(0, min(1, (float)$yRatio));
        $x = (int)round($xRatio * max(0, ($width - $blockW)));
        $y = (int)round($yRatio * max(0, ($height - $blockH)));
    } else {
        switch ($position) {
            case 'top-left':
                $x = $margin;
                $y = $margin;
                break;
            case 'top-center':
                $x = (int)(($width - $blockW) / 2);
                $y = $margin;
                break;
            case 'top-right':
                $x = $width - $blockW - $margin;
                $y = $margin;
                break;
            case 'middle-left':
                $x = $margin;
                $y = (int)(($height - $blockH) / 2);
                break;
            case 'middle-center':
                $x = (int)(($width - $blockW) / 2);
                $y = (int)(($height - $blockH) / 2);
                break;
            case 'middle-right':
                $x = $width - $blockW - $margin;
                $y = (int)(($height - $blockH) / 2);
                break;
            case 'bottom-left':
                $x = $margin;
                $y = $height - $blockH - $margin;
                break;
            case 'bottom-center':
                $x = (int)(($width - $blockW) / 2);
                $y = $height - $blockH - $margin;
                break;
            case 'bottom-right':
            default:
                $x = $width - $blockW - $margin;
                $y = $height - $blockH - $margin;
                break;
        }
    }

    $x = max(0, min((int)$x, max(0, $width - $blockW)));
    $y = max(0, min((int)$y, max(0, $height - $blockH)));

    // 计算各元素绘制坐标（基于图像坐标）
    $iconX = $x;
    $iconY = $y + $iconH; // baseline-ish
    $mainX = $x + $iconW + $gapBetween;
    $mainY = $y + $mainH; // baseline for main text
    $authX = $x + (int)(($blockW - $authW) / 2);
    $authY = $mainY + $lineGap + $authH;

    // 在源图上绘制（先阴影，再主色）
    if ($fontPath && function_exists('imagettftext')) {
        // 飞机图标：先阴影后文字；若找不到图标字体则绘制替代多边形
        if ($iconFontPath) {
            imagettftext($image, $iconFontSize, 0, $iconX + 1, $iconY + 1, $colorShadow, $iconFontPath, $planeChar);
            imagettftext($image, $iconFontSize, 0, $iconX, $iconY, $colorMain, $iconFontPath, $planeChar);
        } else {
            $triShadow = [$iconX + 1, $y + (int)($blockH / 2) + 1, $iconX + (int)($iconW * 0.7) + 1, $y + 1, $iconX + (int)($iconW * 0.7) + 1, $y + $blockH - 1];
            imagefilledpolygon($image, $triShadow, 3, $colorShadow);
            $tri = [$iconX, $y + (int)($blockH / 2), $iconX + (int)($iconW * 0.7), $y, $iconX + (int)($iconW * 0.7), $y + $blockH];
            imagefilledpolygon($image, $tri, 3, $colorMain);
        }

        // 主文本与作者
        imagettftext($image, $mainFontSize, 0, $mainX + 1, $mainY + 1, $colorShadow, $fontPath, $line1);
        imagettftext($image, $mainFontSize, 0, $mainX, $mainY, $colorMain, $fontPath, $line1);
        // 作者文字：阴影 + 主色，若选择粗体则额外再绘制一次以模拟加粗
        imagettftext($image, $authorFontSize, 0, $authX + 1, $authY + 1, $colorShadow, $fontPath, $line2);
        imagettftext($image, $authorFontSize, 0, $authX, $authY, $colorMain, $fontPath, $line2);
        if (isset($authorStyle) && $authorStyle === 'bold') {
            imagettftext($image, $authorFontSize, 0, $authX + 1, $authY, $colorMain, $fontPath, $line2);
        }
    } else {
        imagestring($image, 5, $mainX + 1, $y + 1, $line1, $colorShadow);
        imagestring($image, 5, $mainX, $y, $line1, $colorMain);
        imagestring($image, 3, $authX + 1, $y + $mainH + $lineGap + 1, $line2, $colorShadow);
        imagestring($image, 3, $authX, $y + $mainH + $lineGap, $line2, $colorMain);
        if (isset($authorStyle) && $authorStyle === 'bold') {
            imagestring($image, 3, $authX + 1, $y + $mainH + $lineGap, $line2, $colorMain);
        }
    }

    // 保存带水印的图片
    $saveResult = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $saveResult = imagejpeg($image, $destPath, 90);
            break;
        case IMAGETYPE_PNG:
            $saveResult = imagepng($image, $destPath);
            break;
        case IMAGETYPE_GIF:
            $saveResult = imagegif($image, $destPath);
            break;
    }

    if (!$saveResult) {
        imagedestroy($image);
        return ['status' => false, 'error' => '保存水印图片失败', 'watermark_size_used' => 0];
    }

    // 生成单独的透明背景水印 PNG（与预览一致）
    $wm = imagecreatetruecolor($blockW, $blockH);
    imagesavealpha($wm, true);
    $trans = imagecolorallocatealpha($wm, 0, 0, 0, 127);
    imagefill($wm, 0, 0, $trans);

    if (isset($color) && $color === 'black') {
        $wmMain = imagecolorallocatealpha($wm, 0, 0, 0, $alpha);
        $wmShadow = imagecolorallocatealpha($wm, 255, 255, 255, min(127, $alpha + 30));
    } else {
        $wmMain = imagecolorallocatealpha($wm, 255, 255, 255, $alpha);
        $wmShadow = imagecolorallocatealpha($wm, 0, 0, 0, min(127, $alpha + 30));
    }

    // 绘制到水印图（相对坐标）
    if ($fontPath && function_exists('imagettftext')) {
        if ($iconFontPath) {
            imagettftext($wm, $iconFontSize, 0, 0 + 1, $iconH + 1, $wmShadow, $iconFontPath, $planeChar);
            imagettftext($wm, $iconFontSize, 0, 0, $iconH, $wmMain, $iconFontPath, $planeChar);
        } else {
            $triShadow = [1, (int)($blockH / 2) + 1, (int)($iconW * 0.7) + 1, 1, (int)($iconW * 0.7) + 1, $blockH - 1];
            imagefilledpolygon($wm, $triShadow, 3, $wmShadow);
            $tri = [0, (int)($blockH / 2), (int)($iconW * 0.7), 0, (int)($iconW * 0.7), $blockH];
            imagefilledpolygon($wm, $tri, 3, $wmMain);
        }

        imagettftext($wm, $mainFontSize, 0, $iconW + $gapBetween + 1, $mainH + 1, $wmShadow, $fontPath, $line1);
        imagettftext($wm, $mainFontSize, 0, $iconW + $gapBetween, $mainH, $wmMain, $fontPath, $line1);
        imagettftext($wm, $authorFontSize, 0, (int)(($blockW - $authW) / 2) + 1, $mainH + $lineGap + $authH + 1, $wmShadow, $fontPath, $line2);
        imagettftext($wm, $authorFontSize, 0, (int)(($blockW - $authW) / 2), $mainH + $lineGap + $authH, $wmMain, $fontPath, $line2);
        if (isset($authorStyle) && $authorStyle === 'bold') {
            imagettftext($wm, $authorFontSize, 0, (int)(($blockW - $authW) / 2) + 1, $mainH + $lineGap + $authH, $wmMain, $fontPath, $line2);
        }
    } else {
        imagestring($wm, 5, $iconW + $gapBetween + 1, 1, $line1, $wmShadow);
        imagestring($wm, 5, $iconW + $gapBetween, 0, $line1, $wmMain);
        imagestring($wm, 3, (int)(($blockW - $authW) / 2) + 1, $mainH + $lineGap + 1, $line2, $wmShadow);
        imagestring($wm, 3, (int)(($blockW - $authW) / 2), $mainH + $lineGap, $line2, $wmMain);
        if (isset($authorStyle) && $authorStyle === 'bold') {
            imagestring($wm, 3, (int)(($blockW - $authW) / 2) + 1, $mainH + $lineGap, $line2, $wmMain);
        }
    }

    // 保存水印单独文件
    $wmDir = dirname($destPath) . '/watermarks/';
    if (!is_dir($wmDir)) @mkdir($wmDir, 0755, true);
    $wmPath = $wmDir . pathinfo($destPath, PATHINFO_FILENAME) . '_preview_wm.png';
    imagepng($wm, $wmPath);
    imagedestroy($wm);

    imagedestroy($image);

    return [
        'status' => true,
        'watermark_size_used' => $watermarkSize,
        'watermark_position_used' => $position,
        'adjusted_width' => $blockW,
        'adjusted_height' => $blockH,
        'scale_used' => $scale,
        'preview_watermark_path' => $wmPath
    ];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        if (($_FILES['photo']['size'] ?? 0) > $max_upload_size) {
            $error = '文件过大，最大允许上传 45MB';
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['photo']['type'];

        if (empty($error) && !in_array($file_type, $allowed_types)) {
            $error = '不支持的文件类型，仅支持JPEG、PNG和GIF';
        }

        if (empty($error) && !isset($_POST['allow_use'])) {
            $error = '请同意平台使用条款才能上传图片';
        }

        if (empty($error)) {
            // 水印参数处理
            $watermarkSize = max(5, min(50, intval($_POST['watermark_size'] ?? 15))); // 5-50%
            $watermarkOpacity = max(10, min(100, intval($_POST['watermark_opacity'] ?? 80)));
            $watermarkPosition = in_array(
                $_POST['watermark_position'] ?? '',
                [
                    'top-left',
                    'top-center',
                    'top-right',
                    'middle-left',
                    'middle-center',
                    'middle-right',
                    'bottom-left',
                    'bottom-center',
                    'bottom-right'
                ]
            )
                ? $_POST['watermark_position'] : 'bottom-right';
            $watermarkXRatio = null;
            $watermarkYRatio = null;
            if (isset($_POST['watermark_x_ratio']) && is_numeric($_POST['watermark_x_ratio'])) {
                $watermarkXRatio = max(0, min(1, (float)$_POST['watermark_x_ratio']));
            }
            if (isset($_POST['watermark_y_ratio']) && is_numeric($_POST['watermark_y_ratio'])) {
                $watermarkYRatio = max(0, min(1, (float)$_POST['watermark_y_ratio']));
            }

            // 新增：颜色与作者文字样式
            $watermarkColor = (isset($_POST['watermark_color']) && in_array($_POST['watermark_color'], ['white', 'black'])) ? $_POST['watermark_color'] : 'white';
            $watermarkAuthorStyle = (isset($_POST['watermark_author_style']) && in_array($_POST['watermark_author_style'], ['default', 'simple', 'bold'])) ? $_POST['watermark_author_style'] : 'default';

            $filename = uniqid() . '_' . basename($_FILES['photo']['name']);
            $target_path = $upload_dir . $filename;
            $temp_path = $upload_dir . 'temp_' . $filename;

            // 1. 先压缩图片（获取原始和最终尺寸）
            $compressedInfo = [];
            $compressResult = compressImage($_FILES['photo']['tmp_name'], $temp_path, $compressedInfo);
            if (!$compressResult['success']) {
                $error = '图片压缩失败，请尝试更换图片';
                @unlink($temp_path);
            }
            // 检查临时文件是否可读取
            elseif (!is_readable($temp_path)) {
                $error = '临时文件无法读取，可能是权限问题';
                @unlink($temp_path);
            }
            // 2. 再添加水印
            else {
                $exif = getExifFromService($temp_path);

                $watermarkResult = addWatermark(
                    $temp_path,
                    $target_path,
                    $compressResult['original_width'],
                    $compressResult['final_width'],
                    $watermarkSize,
                    $watermarkOpacity,
                    $watermarkPosition,
                    $watermarkXRatio,
                    $watermarkYRatio,
                    $watermarkColor,
                    $watermarkAuthorStyle
                );

                if (!$watermarkResult['status']) {
                    $error = '添加水印失败: ' . $watermarkResult['error'];
                    @unlink($temp_path);
                    @unlink($target_path);
                }
                // 3. 验证最终文件
                elseif (!file_exists($target_path)) {
                    $error = '文件保存失败，请重试';
                    @unlink($temp_path);
                } else {
                    // 清理临时文件
                    @unlink($temp_path);

                    // ========= 通用清洗函数 =========
                    function extract_number($value, $type = 'int')
                    {
                        if ($value === null) {
                            return null;
                        }

                        // 转字符串 & 去空格
                        $value = trim((string)$value);

                        // 提取数字（允许小数）
                        if (!preg_match('/\d+(\.\d+)?/', $value, $matches)) {
                            return null;
                        }

                        return $type === 'float'
                            ? (float)$matches[0]
                            : (int)$matches[0];
                    }

                    // ========= 焦距 Focal Length（INT） =========
                    $focal_length = extract_number($_POST['FocalLength'] ?? null, 'int');

                    // ========= ISO（INT） =========
                    $iso_value = extract_number($_POST['ISO'] ?? null, 'int');

                    // ========= 光圈 Aperture（FLOAT） =========
                    $aperture_value = extract_number($_POST['F'] ?? null, 'float');

                    // 插入数据库

                    try {
                        $stmt = $pdo->prepare("INSERT INTO photos (
                            user_id, title, category, aircraft_model, 
                            registration_number, `拍摄时间`, `拍摄地点`, 
                            filename, approved, allow_use, created_at,
                            watermark_size, watermark_opacity, watermark_position,
                            original_width, original_height, final_width, final_height, Cam, Lens,
                            FocalLength, ISO, F, Shutter
                        ) VALUES (
                            :user_id, :title, :category, :aircraft_model, 
                            :registration_number, :shooting_time, :shooting_location, 
                            :filename, 0, :allow_use, NOW(),
                            :watermark_size, :watermark_opacity, :watermark_position,
                            :original_width, :original_height, :final_width, :final_height,
                            :Cam, :Lens,
                            :FocalLength, :ISO, :F, :Shutter

                        )");
                        // Focal Length
                        if ($focal_length === null) {
                            $stmt->bindValue(':FocalLength', null, PDO::PARAM_NULL);
                        } else {
                            $stmt->bindValue(':FocalLength', $focal_length, PDO::PARAM_INT);
                        }

                        // ISO
                        if ($iso_value === null) {
                            $stmt->bindValue(':ISO', null, PDO::PARAM_NULL);
                        } else {
                            $stmt->bindValue(':ISO', $iso_value, PDO::PARAM_INT);
                        }

                        // Aperture
                        if ($aperture_value === null) {
                            $stmt->bindValue(':F', null, PDO::PARAM_NULL);
                        } else {
                            $stmt->bindValue(':F', $aperture_value);
                        }

                        $stmt->execute([
                            ':user_id' => $_SESSION['user_id'],
                            ':title' => $_POST['title'],
                            ':category' => $_POST['category'],
                            ':aircraft_model' => $_POST['aircraft_model'],
                            ':registration_number' => $_POST['registration_number'],
                            ':shooting_time' => $_POST['shooting_time'],
                            ':shooting_location' => $_POST['shooting_location'],
                            ':filename' => $filename,
                            ':allow_use' => $_POST['allow_use'],
                            ':watermark_size' => $watermarkSize,
                            ':watermark_opacity' => $watermarkOpacity,
                            ':watermark_position' => $watermarkPosition,
                            ':original_width' => $compressResult['original_width'],
                            ':original_height' => $compressResult['original_height'],
                            ':final_width' => $compressResult['final_width'],
                            ':final_height' => $compressResult['final_height'],
                            ':Cam' => $_POST['cameraModel'] ?? '',
                            ':Lens' => $_POST['lensModel'] ?? '',
                            ':FocalLength' => $focal_length,
                            ':ISO' => $iso_value,
                            ':F' => $aperture_value,
                            ':Shutter' => $_POST['Shutter'] ?? '',
                        ]);

                        $success = '图片上传成功，已添加水印（大小: ' . $watermarkSize . '%, 位置: ' .
                            getPositionText($watermarkPosition) . '），等待审核';

                        if (!empty($watermarkResult['preview_watermark_path'])) {
                            $wmPath = $watermarkResult['preview_watermark_path'];
                            // 把服务器相对路径用于显示（uploads/...）
                            $wmUrl = $wmPath;
                            $success .= ' 已生成预览水印文件：<a href="' . htmlspecialchars($wmUrl) . '" target="_blank">预览水印</a>';
                        }

                        $_POST = [];
                    } catch (PDOException $e) {
                        $error = "数据库保存失败: " . $e->getMessage();
                        @unlink($target_path);
                        error_log("数据库错误: " . $e->getMessage());
                    }
                }
            }
        }
    } else {
        $error = '请选择要上传的图片（错误码：' . ($_FILES['photo']['error'] ?? '未知') . '）';
    }
}

/**
 * 将位置代码转换为中文显示
 */
function getPositionText($positionCode)
{
    $positions = [
        'top-left' => '左上角',
        'top-center' => '上中',
        'top-right' => '右上角',
        'middle-left' => '左中',
        'middle-center' => '居中',
        'middle-right' => '右中',
        'bottom-left' => '左下角',
        'bottom-center' => '下中',
        'bottom-right' => '右下角'
    ];
    return $positions[$positionCode] ?? '右下角';
}

?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horizon Photos - 上传图片</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- <script src="https://cdn.jsdelivr.net/npm/exif-js"></script> -->
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <style>
        @font-face {
            font-family: 'DingLie';
            src: local('DingLie'), url('./fonts/Montserrat-ExtraBold.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --primary: #165DFF;
            --primary-light: #4080FF;
            --primary-lighter: #E8F3FF;
            --success: #00B42A;
            --success-light: #E6FFED;
            --danger: #F53F3F;
            --danger-light: #FFECE8;
            --gray-100: #F2F3F5;
            --gray-200: #E5E6EB;
            --gray-400: #86909C;
            --gray-500: #4E5969;
            --white: #FFFFFF;
            --radius-lg: 8px;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --transition: all 0.25s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Roboto, sans-serif;
            background-color: #F7F8FA;
            color: #1D2129;
            line-height: 1.5;
        }

        .nav {
            background-color: var(--primary);
            padding: 14px 20px;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links {
            display: flex;
            gap: 4px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: var(--radius-lg);
            font-size: 14px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav a:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .nav a.active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 500;
        }

        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .page-title {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .page-desc {
            color: var(--gray-500);
            font-size: 14px;
            max-width: 600px;
            margin: 0 auto;
        }

        .alert {
            padding: 14px 16px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error {
            background-color: var(--danger-light);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-success {
            background-color: var(--success-light);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .upload-form {
            background-color: var(--white);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1D2129;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        label i {
            color: var(--primary-light);
        }

        .form-hint {
            font-size: 12px;
            color: var(--gray-400);
            margin-top: 4px;
        }

        input,
        select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-family: inherit;
            font-size: 14px;
            transition: var(--transition);
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22, 93, 255, 0.1);
        }

        /* 水印设置样式 */
        .watermark-settings {
            background-color: var(--primary-lighter);
            padding: 20px;
            border-radius: var(--radius-lg);
            margin: 20px 0;
        }

        .watermark-settings h3 {
            margin-top: 0;
            margin-bottom: 16px;
            color: var(--primary);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .slider-group {
            margin-bottom: 16px;
        }

        .slider-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        input[type="range"] {
            flex: 1;
            padding: 0;
            height: 6px;
            appearance: none;
            background: var(--gray-200);
            border-radius: 3px;
        }

        input[type="range"]::-webkit-slider-thumb {
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
        }

        .slider-value {
            width: 50px;
            text-align: center;
            font-weight: 500;
            padding: 6px;
            background-color: var(--white);
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
        }

        /* 水印位置选择 */
        .position-selector {
            margin-top: 20px;
        }

        .position-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .position-option {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .position-option.selected {
            border-color: var(--primary);
            background-color: rgba(22, 93, 255, 0.05);
        }

        .position-option input {
            display: none;
        }

        .position-option span {
            font-size: 13px;
        }

        .position-option::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .position-top-left::before {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2386909C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='3' width='7' height='7'/%3E%3C/svg%3E") no-repeat top 5px left 5px;
        }

        .position-top-center::before {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2386909C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='8.5' y='3' width='7' height='7'/%3E%3C/svg%3E") no-repeat top 5px center;
        }

        .position-top-right::before {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2386909C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='14' y='3' width='7' height='7'/%3E%3C/svg%3E") no-repeat top 5px right 5px;
        }

        .position-middle-left::before {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2386909C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='8.5' width='7' height='7'/%3E%3C/svg%3E") no-repeat center left 5px;
        }

        .position-middle-center::before {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2386909C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='8.5' y='8.5' width='7' height='7'/%3E%3C/svg%3E") no-repeat center center;
        }

        .position-middle-right::before {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2386909C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='14' y='8.5' width='7' height='7'/%3E%3C/svg%3E") no-repeat center right 5px;
        }

        .position-bottom-left::before {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2386909C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='14' width='7' height='7'/%3E%3C/svg%3E") no-repeat bottom 5px left 5px;
        }

        .position-bottom-center::before {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2386909C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='8.5' y='14' width='7' height='7'/%3E%3C/svg%3E") no-repeat bottom 5px center;
        }

        .position-bottom-right::before {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2386909C' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='14' y='14' width='7' height='7'/%3E%3C/svg%3E") no-repeat bottom 5px right 5px;
        }

        /* 图片预览和水印 */
        .image-preview {
            margin-top: 20px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            display: none;
        }

        .preview-wrapper {
            position: relative;
            display: inline-block;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            overflow: hidden;
            background-color: white;
        }

        .preview-image {
            max-width: 100%;
            height: auto;
            display: block;
        }

        /* 预览图上的水印 */
        .preview-watermark {
            position: absolute;
            pointer-events: none;
        }

        .image-upload {
            border: 2px dashed var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .image-upload:hover {
            border-color: var(--primary-light);
            background-color: var(--primary-lighter);
        }

        .image-upload input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary-light);
            margin-bottom: 16px;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 16px;
            background-color: var(--gray-100);
            border-radius: var(--radius-lg);
            margin-top: 16px;
        }

        .checkbox-group input {
            width: auto;
            margin-top: 3px;
        }

        .form-actions {
            text-align: center;
            margin-top: 30px;
        }

        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background-color: var(--primary-light);
        }

        .btn-secondary {
            background-color: var(--gray-100);
            color: var(--gray-500);
        }

        .preview-info {
            font-size: 12px;
            color: var(--gray-400);
            margin-top: 8px;
            text-align: right;
        }

        /* 图片处理信息显示 */
        .processing-info {
            font-size: 12px;
            color: var(--primary);
            margin-top: 8px;
            text-align: left;
        }

        /* 加载指示器 */
        .loading-indicator {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            vertical-align: middle;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* API请求状态指示器 */
        .api-status {
            margin-left: 8px;
            font-size: 12px;
            color: var(--gray-400);
        }

        .drag-tool-layout {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .drag-preview-column,
        .drag-controls-column {
            min-width: 0;
        }

        .drag-preview {
            display: none;
            margin-top: 14px;
        }

        .image-container-wrapper {
            position: relative;
            width: 100%;
            min-height: 320px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            background: #f7f8fb;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .image-container {
            position: relative;
            max-width: 100%;
            max-height: 100%;
        }

        #watermarkElement {
            position: absolute;
            left: 20px;
            top: 20px;
            z-index: 5;
            cursor: move;
            user-select: none;
            border: 2px dashed transparent;
            padding: 0;
            line-height: 1.2;
            text-align: center;
            white-space: nowrap;
            display: flex;
            flex-direction: column;
            align-items: center;
            pointer-events: auto;
        }

        #watermarkElement.dragging,
        #watermarkElement:hover {
            border-color: rgba(22, 93, 255, 0.55);
        }

        .watermark-text {
            color: #fff;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.45);
        }

        .watermark-icon-text {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 12px;
            pointer-events: none;
            /* allow dragging the container */
        }

        .watermark-icon {
            font-size: 36px;
            /* JS 会根据设置动态调整 */
            line-height: 1;
            pointer-events: none;
            font-family: 'Segoe UI Symbol', 'Noto Sans Symbols 2', 'Arial Unicode MS', 'Segoe UI', sans-serif;
        }

        .watermark-syphotos {
            font-family: 'DingLie', 'Segoe UI', Roboto, sans-serif;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: 1px;
            pointer-events: none;
        }

        .watermark-author {
            font-family: 'DingLie', 'Segoe UI', Roboto, sans-serif;
            font-size: 18px;
            font-weight: 500;
            opacity: 0.95;
            margin-top: 8px;
            pointer-events: none;
        }

        /* 颜色选择与作者样式按钮 */
        .color-options {
            display: flex;
            gap: 10px;
            margin-top: 6px;
        }

        .color-option {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 3px solid transparent;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .color-option.active {
            transform: scale(1.05);
        }

        .color-option[data-color="white"].active {
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
            border-color: rgba(0, 0, 0, 0.12);
        }

        .color-option[data-color="black"].active {
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.95);
        }

        .watermark-style-controls {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .style-btn {
            padding: 6px 12px;
            border-radius: 14px;
            border: 1px solid #ddd;
            background: #fff;
            cursor: pointer;
            font-size: 13px;
        }

        .style-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .style-btn:focus {
            outline: none;
        }

        .drag-hint {
            margin-top: 8px;
            font-size: 12px;
            color: var(--gray-500);
        }

        .quick-position-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 14px;
        }

        .quick-position-btn {
            border: 1px solid var(--gray-200);
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 8px 6px;
            font-size: 12px;
            cursor: pointer;
            color: var(--gray-500);
            transition: var(--transition);
        }

        .quick-position-btn:hover,
        .quick-position-btn.active {
            border-color: var(--primary);
            color: var(--primary);
            background-color: rgba(22, 93, 255, 0.06);
        }

        .hidden-position-radios {
            display: none;
        }

        @media (max-width: 980px) {
            .drag-tool-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/src/nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-cloud-upload-alt"></i>
                上传航空摄影作品
            </h1>
            <p class="page-desc">请填写图片信息并上传，所有带 <span style="color:var(--danger)">*</span> 的字段为必填项</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>






        <form method="post" action="upload.php" enctype="multipart/form-data" id="uploadForm">

            <div class="upload-form">

                <div class="form-group full-width">
                    <div class="drag-tool-layout">
                        <div class="drag-preview-column">
                            <label for="photo">
                                <i class="fas fa-image"></i>
                                选择图片 <span style="color:var(--danger)">*</span>
                            </label>
                            <div class="image-upload" id="imageUploadArea">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div>点击或拖拽图片到此处上传</div>
                                <div class="form-hint">支持 JPG、PNG、GIF 格式；最大 45MB，超过5MB将自动压缩</div>
                                <input type="file" id="photo" name="photo" accept="image/*" required>
                            </div>

                            <div class="image-preview drag-preview" id="imagePreview">
                                <div class="image-container-wrapper">
                                    <div class="image-container" id="imageContainer">
                                        <img id="previewImage" class="preview-image" src="" alt="图片预览">
                                        <div id="watermarkElement">
                                            <div class="watermark-icon-text">
                                                <span class="watermark-icon" aria-hidden="true">✈</span>
                                                <div class="watermark-text watermark-syphotos">syphotos</div>
                                            </div>
                                            <div class="watermark-author" id="authorNameDisplay">@<?php echo htmlspecialchars($_SESSION['username'] ?? 'photographer'); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="drag-hint">
                                    <i class="fas fa-hand-pointer"></i> 提示：可直接拖动水印，水印会限制在图片范围内
                                </div>
                                <div class="processing-info" id="processingInfo">
                                    原始尺寸: <span id="originalDimensions">-- x --</span> |
                                    处理后尺寸: <span id="processedDimensions">-- x --</span>
                                </div>
                                <div class="preview-info" id="previewInfo">
                                    水印大小: <span id="displayedWatermarkSize">15%</span> |
                                    位置: <span id="displayedWatermarkPosition">右下角</span>
                                </div>
                                <div style="margin-top:10px; text-align:right;">
                                    <button type="button" class="btn btn-secondary" id="removePreview">
                                        <i class="fas fa-times"></i> 移除图片
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="drag-controls-column">
                            <div class="watermark-settings form-group full-width">
                                <h3>
                                    <i class="fas fa-images"></i>
                                    图片水印设置
                                </h3>
                                <div class="slider-group">
                                    <label for="watermark_size">水印大小（占原图比例）</label>
                                    <div class="slider-container">
                                        <input type="range" id="watermark_size" name="watermark_size"
                                            min="5" max="50" step="1" value="<?php echo isset($_POST['watermark_size']) ? intval($_POST['watermark_size']) : 15; ?>">
                                        <span class="slider-value" id="watermark_size_value">
                                            <?php echo isset($_POST['watermark_size']) ? intval($_POST['watermark_size']) : 15; ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="slider-group">
                                    <label for="watermark_opacity">水印透明度</label>
                                    <div class="slider-container">
                                        <input type="range" id="watermark_opacity" name="watermark_opacity"
                                            min="10" max="100" step="5" value="<?php echo isset($_POST['watermark_opacity']) ? intval($_POST['watermark_opacity']) : 80; ?>">
                                        <span class="slider-value" id="watermark_opacity_value">
                                            <?php echo isset($_POST['watermark_opacity']) ? intval($_POST['watermark_opacity']) : 80; ?>%
                                        </span>
                                    </div>
                                </div>

                                <!-- 水印颜色 -->
                                <div class="control-group">
                                    <div class="control-title"><i class="fas fa-palette"></i> 水印颜色</div>
                                    <div class="color-options">
                                        <div class="color-option <?php echo $currentWatermarkColor === 'white' ? 'active' : ''; ?>" data-color="white" title="白色" style="background:#ffffff;"></div>
                                        <div class="color-option <?php echo $currentWatermarkColor === 'black' ? 'active' : ''; ?>" data-color="black" title="黑色" style="background:#000000;"></div>
                                    </div>
                                    <input type="hidden" id="watermark_color" name="watermark_color" value="<?php echo htmlspecialchars($currentWatermarkColor, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <!-- 作者文字样式 -->
                                <div class="control-group">
                                    <div class="control-title"><i class="fas fa-layer-group"></i> 作者文字样式</div>
                                    <div class="watermark-style-controls">
                                        <button type="button" class="style-btn <?php echo $currentWatermarkAuthorStyle === 'default' ? 'active' : ''; ?>" data-style="default">默认样式</button>
                                        <button type="button" class="style-btn <?php echo $currentWatermarkAuthorStyle === 'simple' ? 'active' : ''; ?>" data-style="simple">简洁样式</button>
                                        <button type="button" class="style-btn <?php echo $currentWatermarkAuthorStyle === 'bold' ? 'active' : ''; ?>" data-style="bold">粗体样式</button>
                                    </div>
                                    <input type="hidden" id="watermark_author_style" name="watermark_author_style" value="<?php echo htmlspecialchars($currentWatermarkAuthorStyle, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="quick-position-grid">
                                    <button type="button" class="quick-position-btn" data-position="top-left">左上</button>
                                    <button type="button" class="quick-position-btn" data-position="top-center">上中</button>
                                    <button type="button" class="quick-position-btn" data-position="top-right">右上</button>
                                    <button type="button" class="quick-position-btn" data-position="middle-left">左中</button>
                                    <button type="button" class="quick-position-btn" data-position="middle-center">居中</button>
                                    <button type="button" class="quick-position-btn" data-position="middle-right">右中</button>
                                    <button type="button" class="quick-position-btn" data-position="bottom-left">左下</button>
                                    <button type="button" class="quick-position-btn" data-position="bottom-center">下中</button>
                                    <button type="button" class="quick-position-btn active" data-position="bottom-right">右下</button>
                                </div>
                                <div class="position-selector hidden-position-radios">
                                    <label class="position-option <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'top-left' ? 'selected' : ''; ?>">
                                        <input type="radio" name="watermark_position" value="top-left" <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'top-left' ? 'checked' : ''; ?>>
                                        <span>左上角</span>
                                    </label>
                                    <label class="position-option <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'top-center' ? 'selected' : ''; ?>">
                                        <input type="radio" name="watermark_position" value="top-center" <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'top-center' ? 'checked' : ''; ?>>
                                        <span>上中</span>
                                    </label>
                                    <label class="position-option <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'top-right' ? 'selected' : ''; ?>">
                                        <input type="radio" name="watermark_position" value="top-right" <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'top-right' ? 'checked' : ''; ?>>
                                        <span>右上角</span>
                                    </label>
                                    <label class="position-option <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'middle-left' ? 'selected' : ''; ?>">
                                        <input type="radio" name="watermark_position" value="middle-left" <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'middle-left' ? 'checked' : ''; ?>>
                                        <span>左中</span>
                                    </label>
                                    <label class="position-option <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'middle-center' ? 'selected' : ''; ?>">
                                        <input type="radio" name="watermark_position" value="middle-center" <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'middle-center' ? 'checked' : ''; ?>>
                                        <span>居中</span>
                                    </label>
                                    <label class="position-option <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'middle-right' ? 'selected' : ''; ?>">
                                        <input type="radio" name="watermark_position" value="middle-right" <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'middle-right' ? 'checked' : ''; ?>>
                                        <span>右中</span>
                                    </label>
                                    <label class="position-option <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'bottom-left' ? 'selected' : ''; ?>">
                                        <input type="radio" name="watermark_position" value="bottom-left" <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'bottom-left' ? 'checked' : ''; ?>>
                                        <span>左下角</span>
                                    </label>
                                    <label class="position-option <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'bottom-center' ? 'selected' : ''; ?>">
                                        <input type="radio" name="watermark_position" value="bottom-center" <?php echo isset($_POST['watermark_position']) && $_POST['watermark_position'] == 'bottom-center' ? 'checked' : ''; ?>>
                                        <span>下中</span>
                                    </label>
                                    <label class="position-option <?php echo (!isset($_POST['watermark_position']) || $_POST['watermark_position'] == 'bottom-right') ? 'selected' : ''; ?>">
                                        <input type="radio" name="watermark_position" value="bottom-right" <?php echo (!isset($_POST['watermark_position']) || $_POST['watermark_position'] == 'bottom-right') ? 'checked' : ''; ?>>
                                        <span>右下角</span>
                                    </label>
                                </div>
                                <input type="hidden" id="watermark_x_ratio" name="watermark_x_ratio" value="<?php echo isset($_POST['watermark_x_ratio']) ? htmlspecialchars($_POST['watermark_x_ratio']) : ''; ?>">
                                <input type="hidden" id="watermark_y_ratio" name="watermark_y_ratio" value="<?php echo isset($_POST['watermark_y_ratio']) ? htmlspecialchars($_POST['watermark_y_ratio']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">
                                <i class="fas fa-heading"></i>
                                图片标题 <span style="color:var(--danger)">*</span>
                            </label>
                            <input type="text" id="title" name="title" required
                                value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                                placeholder="请输入图片标题">
                        </div>

                        <div class="form-group">
                            <label for="registration_number">
                                <i class="fas fa-id-card-alt"></i>
                                飞机注册号 <span style="color:var(--danger)">*</span>
                                <span class="api-status" id="regApiStatus"></span>
                            </label>
                            <input type="text" id="registration_number" name="registration_number" required
                                value="<?php echo isset($_POST['registration_number']) ? htmlspecialchars($_POST['registration_number']) : ''; ?>"
                                placeholder="例如：B-1234">
                            <span class="form-hint">输入后将自动获取并填充飞机信息</span>
                        </div>

                        <div class="form-group">
                            <label for="aircraft_model">
                                <i class="fas fa-plane-departure"></i>
                                飞机型号 <span style="color:var(--danger)">*</span>
                            </label>
                            <input type="text" id="aircraft_model" name="aircraft_model" required
                                value="<?php echo isset($_POST['aircraft_model']) ? htmlspecialchars($_POST['aircraft_model']) : ''; ?>"
                                placeholder="例如：B738">
                        </div>

                        <div class="form-group">
                            <label for="category">
                                <i class="fas fa-building"></i>
                                航空公司 <span style="color:var(--danger)">*</span>
                            </label>
                            <input type="text" id="category" name="category" required
                                value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : ''; ?>"
                                placeholder="例如：Air China">
                        </div>





                        <div class="form-group">
                            <label for="shooting_time">
                                <i class="fas fa-clock"></i>
                                拍摄时间 <span style="color:var(--danger)">*</span>
                            </label>
                            <input type="datetime-local" id="shooting_time" name="shooting_time" required
                                value="<?php echo isset($_POST['shooting_time']) ? htmlspecialchars($_POST['shooting_time']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="shooting_location">
                                <i class="fas fa-map-marker-alt"></i>
                                拍摄地点 <span style="color:var(--danger)">*</span>
                            </label>
                            <input type="text" id="shooting_location" name="shooting_location" required
                                value="<?php echo isset($_POST['shooting_location']) ? htmlspecialchars($_POST['shooting_location']) : ''; ?>"
                                placeholder="例如：PEK">
                        </div>


                        <div class="form-group">
                            <div class="cameraModel">
                                <label for="cameraModel">
                                    <i class="fas fa-cameraModel"></i>
                                    相机型号
                                </label>
                                <input type="text" id="cameraModel" name="cameraModel"
                                    value="<?php echo isset($_POST['cameraModel']) ? htmlspecialchars($_POST['cameraModel']) : ''; ?>"
                                    placeholder="例如：Canon EOS R5">
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="lensModel">
                                <label for="lensModel">
                                    <i class="fas fa-lensModel"></i>
                                    镜头型号
                                </label>
                                <input type="text" id="lensModel" name="lensModel"
                                    value="<?php echo isset($_POST['lensModel']) ? htmlspecialchars($_POST['lensModel']) : ''; ?>"
                                    placeholder="例如：EF 70-200mm f/2.8L IS III USM">
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="FocalLength">
                                <label for="FocalLength">
                                    <i class="fas fa-ruler-combined"></i>
                                    焦距
                                </label>
                                <input type="text" id="FocalLength" name="FocalLength"
                                    value="<?php echo isset($_POST['FocalLength']) ? htmlspecialchars($_POST['FocalLength']) : ''; ?>"
                                    placeholder="例如：200mm">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="ISO">
                                <label for="ISO">
                                    <i class="fas fa-sigma"></i>
                                    ISO
                                </label>
                                <input type="text" id="ISO" name="ISO"
                                    value="<?php echo isset($_POST['ISO']) ? htmlspecialchars($_POST['ISO']) : ''; ?>"
                                    placeholder="例如：100">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="F">
                                <label for="F">
                                    <i class="fas fa-ruler-horizontal"></i>
                                    光圈
                                </label>
                                <input type="text" id="F" name="F"
                                    value="<?php echo isset($_POST['F']) ? htmlspecialchars($_POST['F']) : ''; ?>"
                                    placeholder="例如：f/2.8">
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="Shutter">
                                <label for="Shutter">
                                    <i class="fas fa-stopwatch"></i>
                                    快门速度
                                </label>
                                <input type="text" id="Shutter" name="Shutter"
                                    value="<?php echo isset($_POST['Shutter']) ? htmlspecialchars($_POST['Shutter']) : ''; ?>"
                                    placeholder="例如：1/1000s">
                            </div>
                        </div>
                    </div>


                    <div class="checkbox-group">
                        <input type="checkbox" id="allow_use" name="allow_use" value="1"
                            <?php echo isset($_POST['allow_use']) ? 'checked' : ''; ?>>
                        <div>
                            <p><strong>允许平台在不另行通知的情况下使用此图片</strong></p>
                            <small>用途包括但不限于：网站首页展示、专题合集、社交媒体宣传等（将保留图片作者信息）。不同意此条款将无法完成上传。</small>
                        </div>
                    </div>
                </div>

                <div class="rules-info">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        <a href="OurRule.pdf" target="_blank" style="color: var(--primary);">上传规则和要求</a>
                    </h3>


                    <div class="form-actions">
                        <button type="submit" class="btn">
                            <i class="fas fa-paper-plane"></i> 提交上传
                        </button>
                    </div>
        </form>
    </div>
    </div>

    <script>
        // 防抖函数实现（避免频繁API请求）
        function debounce(func, wait = 500) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        // 水印预览控制（up1 风格：可拖动文字水印）
        const sizeSlider = document.getElementById('watermark_size');
        const sizeValue = document.getElementById('watermark_size_value');
        const opacitySlider = document.getElementById('watermark_opacity');
        const opacityValue = document.getElementById('watermark_opacity_value');
        const positionRadios = document.querySelectorAll('input[name="watermark_position"]');
        const quickPositionButtons = document.querySelectorAll('.quick-position-btn');
        const previewImage = document.getElementById('previewImage');
        const displayedWatermarkSize = document.getElementById('displayedWatermarkSize');
        const displayedWatermarkPosition = document.getElementById('displayedWatermarkPosition');
        const watermarkElement = document.getElementById('watermarkElement');
        const imageContainer = document.getElementById('imageContainer');
        const watermarkTitle = document.querySelector('.watermark-syphotos');
        const watermarkAuthor = document.querySelector('.watermark-author');
        const watermarkIcon = document.querySelector('.watermark-icon');
        const maxUploadSize = 45 * 1024 * 1024;
        const watermarkXRatioInput = document.getElementById('watermark_x_ratio');
        const watermarkYRatioInput = document.getElementById('watermark_y_ratio');

        let originalImageWidth = 0;
        let originalImageHeight = 0;
        let originalFileSize = 0;
        let predictedWidth = 0;
        let predictedHeight = 0;
        let scaleRatio = 1.0;
        let isDragging = false;
        let startX = 0;
        let startY = 0;
        let initialLeft = 0;
        let initialTop = 0;
        let imageBounds = {
            left: 0,
            top: 0,
            right: 0,
            bottom: 0
        };

        const positionTextMap = {
            'top-left': '左上角',
            'top-center': '上中',
            'top-right': '右上角',
            'middle-left': '左中',
            'middle-center': '居中',
            'middle-right': '右中',
            'bottom-left': '左下角',
            'bottom-center': '下中',
            'bottom-right': '右下角'
        };

        function calculateCompressedSize(originalWidth, originalHeight, originalSize, maxSize = 5242880) {
            if (originalSize <= maxSize) {
                return {
                    width: originalWidth,
                    height: originalHeight,
                    scale: 1.0
                };
            }
            const scale = Math.sqrt(maxSize / originalSize) * 0.9;
            let newWidth = Math.max(400, Math.round(originalWidth * scale));
            const aspectRatio = originalWidth / originalHeight;
            let newHeight = Math.round(newWidth / aspectRatio);
            if (newHeight < 300) {
                newHeight = 300;
                newWidth = Math.round(newHeight * aspectRatio);
            }
            return {
                width: newWidth,
                height: newHeight,
                scale: newWidth / originalWidth
            };
        }

        function getSelectedPosition() {
            for (const radio of positionRadios) {
                if (radio.checked) {
                    return radio.value;
                }
            }
            return 'bottom-right';
        }

        function setSelectedPosition(position) {
            positionRadios.forEach((radio) => {
                radio.checked = radio.value === position;
            });
            quickPositionButtons.forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.position === position);
            });
            displayedWatermarkPosition.textContent = positionTextMap[position] || position;
        }

        function updateImageBounds() {
            const containerRect = imageContainer.getBoundingClientRect();
            const imgRect = previewImage.getBoundingClientRect();
            imageBounds.left = imgRect.left - containerRect.left;
            imageBounds.top = imgRect.top - containerRect.top;
            imageBounds.right = imageBounds.left + imgRect.width;
            imageBounds.bottom = imageBounds.top + imgRect.height;
        }

        function updateWatermarkSettings() {
            const size = parseInt(sizeSlider.value, 10);
            const opacity = parseInt(opacitySlider.value, 10);
            const position = getSelectedPosition();

            sizeValue.textContent = `${size}%`;
            opacityValue.textContent = `${opacity}%`;
            displayedWatermarkSize.textContent = `${size}%`;
            setSelectedPosition(position);

            // 与后端算法保持一致：先计算目标块宽度，再由块宽派生字体大小
            const baseFinalWidth = (predictedWidth > 0) ? predictedWidth : Math.max(800, originalImageWidth || 800);
            let targetBlockWidth = Math.max(20, Math.min(Math.round(baseFinalWidth * (size / 100)), Math.round(baseFinalWidth * 0.5)));

            const mainFontFinal = Math.max(10, Math.round(targetBlockWidth / 4.2));
            const authorFontFinal = Math.max(8, Math.round(mainFontFinal * 0.45));
            const iconFontFinal = Math.max(8, Math.round(mainFontFinal * 0.8));

            // 将“最终图片的字体大小（像素）”转换为当前预览的显示像素
            const displayScale = (baseFinalWidth > 0 && previewImage.clientWidth > 0) ? (previewImage.clientWidth / baseFinalWidth) : 1;
            const mainSizeOnScreen = Math.max(12, Math.round(mainFontFinal * displayScale));
            const authorSizeOnScreen = Math.max(8, Math.round(authorFontFinal * displayScale));
            const iconSizeOnScreen = Math.max(8, Math.round(iconFontFinal * displayScale));

            watermarkTitle.style.fontSize = `${mainSizeOnScreen}px`;
            watermarkAuthor.style.fontSize = `${authorSizeOnScreen}px`;
            if (watermarkIcon) watermarkIcon.style.fontSize = `${iconSizeOnScreen}px`;

            // 应用颜色与作者样式（从隐藏字段或 UI 状态）
            const selectedColor = document.getElementById('watermark_color') ? document.getElementById('watermark_color').value : 'white';
            const selectedAuthorStyle = document.getElementById('watermark_author_style') ? document.getElementById('watermark_author_style').value : 'default';

            const textColor = selectedColor === 'black' ? '#000000' : '#ffffff';
            watermarkTitle.style.color = textColor;
            watermarkAuthor.style.color = textColor;
            if (watermarkIcon) watermarkIcon.style.color = textColor;

            // 根据颜色选择使用对比阴影，提升可读性
            const shadowOpacity = Math.max(0.12, (opacity / 100) * 0.6);
            const shadowColor = selectedColor === 'black' ? `rgba(255,255,255,${shadowOpacity})` : `rgba(0,0,0,${shadowOpacity})`;
            const textShadow = `1px 1px 3px ${shadowColor}`;
            watermarkTitle.style.textShadow = textShadow;
            watermarkAuthor.style.textShadow = textShadow;
            if (watermarkIcon) watermarkIcon.style.textShadow = textShadow;

            // 强制预览使用仓库字体（若可用）
            const dingFont = 'DingLie, "Segoe UI", Roboto, sans-serif';
            watermarkTitle.style.fontFamily = dingFont;
            watermarkAuthor.style.fontFamily = dingFont;

            // 作者文字样式（对应 up3 预览）
            switch (selectedAuthorStyle) {
                case 'simple':
                    watermarkAuthor.style.fontWeight = '400';
                    watermarkAuthor.style.letterSpacing = 'normal';
                    break;
                case 'bold':
                    watermarkAuthor.style.fontWeight = '700';
                    watermarkAuthor.style.letterSpacing = '1px';
                    break;
                case 'default':
                default:
                    watermarkAuthor.style.fontWeight = '500';
                    watermarkAuthor.style.letterSpacing = '0.5px';
                    break;
            }

            watermarkElement.style.opacity = opacity / 100;
            setWatermarkToPosition(position);
        }

        function setWatermarkToPosition(position) {
            if (!previewImage.src) return;
            updateImageBounds();
            const wmRect = watermarkElement.getBoundingClientRect();
            const wmWidth = wmRect.width;
            const wmHeight = wmRect.height;
            const margin = 20;
            let x = 0;
            let y = 0;

            switch (position) {
                case 'top-left':
                    x = imageBounds.left + margin;
                    y = imageBounds.top + margin;
                    break;
                case 'top-center':
                    x = imageBounds.left + (imageBounds.right - imageBounds.left - wmWidth) / 2;
                    y = imageBounds.top + margin;
                    break;
                case 'top-right':
                    x = imageBounds.right - wmWidth - margin;
                    y = imageBounds.top + margin;
                    break;
                case 'middle-left':
                    x = imageBounds.left + margin;
                    y = imageBounds.top + (imageBounds.bottom - imageBounds.top - wmHeight) / 2;
                    break;
                case 'middle-center':
                    x = imageBounds.left + (imageBounds.right - imageBounds.left - wmWidth) / 2;
                    y = imageBounds.top + (imageBounds.bottom - imageBounds.top - wmHeight) / 2;
                    break;
                case 'middle-right':
                    x = imageBounds.right - wmWidth - margin;
                    y = imageBounds.top + (imageBounds.bottom - imageBounds.top - wmHeight) / 2;
                    break;
                case 'bottom-left':
                    x = imageBounds.left + margin;
                    y = imageBounds.bottom - wmHeight - margin;
                    break;
                case 'bottom-center':
                    x = imageBounds.left + (imageBounds.right - imageBounds.left - wmWidth) / 2;
                    y = imageBounds.bottom - wmHeight - margin;
                    break;
                case 'bottom-right':
                default:
                    x = imageBounds.right - wmWidth - margin;
                    y = imageBounds.bottom - wmHeight - margin;
                    break;
            }

            watermarkElement.style.left = `${Math.round(x)}px`;
            watermarkElement.style.top = `${Math.round(y)}px`;
            constrainWatermarkToImage();
        }

        function constrainWatermarkToImage() {
            if (!previewImage.src) return;
            updateImageBounds();
            const wmRect = watermarkElement.getBoundingClientRect();
            const wmWidth = wmRect.width;
            const wmHeight = wmRect.height;
            let x = parseFloat(watermarkElement.style.left) || 0;
            let y = parseFloat(watermarkElement.style.top) || 0;

            const minX = imageBounds.left;
            const maxX = imageBounds.right - wmWidth;
            const minY = imageBounds.top;
            const maxY = imageBounds.bottom - wmHeight;

            x = Math.max(minX, Math.min(x, maxX));
            y = Math.max(minY, Math.min(y, maxY));
            watermarkElement.style.left = `${Math.round(x)}px`;
            watermarkElement.style.top = `${Math.round(y)}px`;
            updateWatermarkRatioInputs();
        }

        function updateWatermarkRatioInputs() {
            if (!previewImage.src) return;
            updateImageBounds();
            const wmRect = watermarkElement.getBoundingClientRect();
            const wmWidth = wmRect.width;
            const wmHeight = wmRect.height;
            const x = parseFloat(watermarkElement.style.left) || imageBounds.left;
            const y = parseFloat(watermarkElement.style.top) || imageBounds.top;
            const usableWidth = Math.max(1, imageBounds.right - imageBounds.left - wmWidth);
            const usableHeight = Math.max(1, imageBounds.bottom - imageBounds.top - wmHeight);
            const xRatio = (x - imageBounds.left) / usableWidth;
            const yRatio = (y - imageBounds.top) / usableHeight;
            watermarkXRatioInput.value = Math.max(0, Math.min(1, xRatio)).toFixed(6);
            watermarkYRatioInput.value = Math.max(0, Math.min(1, yRatio)).toFixed(6);
        }

        function detectNearestPosition() {
            updateImageBounds();
            const wmRect = watermarkElement.getBoundingClientRect();
            const wmWidth = wmRect.width;
            const wmHeight = wmRect.height;
            const x = parseFloat(watermarkElement.style.left) || imageBounds.left;
            const y = parseFloat(watermarkElement.style.top) || imageBounds.top;

            const usableWidth = Math.max(1, imageBounds.right - imageBounds.left - wmWidth);
            const usableHeight = Math.max(1, imageBounds.bottom - imageBounds.top - wmHeight);
            const xRatio = (x - imageBounds.left) / usableWidth;
            const yRatio = (y - imageBounds.top) / usableHeight;
            const col = xRatio < 0.33 ? 'left' : (xRatio > 0.66 ? 'right' : 'center');
            const row = yRatio < 0.33 ? 'top' : (yRatio > 0.66 ? 'bottom' : 'middle');
            return `${row}-${col}`;
        }

        // 图片预览功能
        const photoInput = document.getElementById('photo');

        photoInput.addEventListener('change', async function() {
            if (!this.files || !this.files[0]) return;

            const file = this.files[0];
            // 调用 exif-service 自动填充
            const formData = new FormData();
            formData.append('file', file); // ⚠️ 名字必须是 file

            try {
                const resp = await fetch('/srv/exif-service/public/', {
                    method: 'POST',
                    body: formData
                });

                const respose = await resp.json();
                const data = respose[0];

                if (data.error) {
                    console.warn('EXIF 识别失败:', data.error);
                    return;
                }


                // ===== 3️⃣ 自动填表单 =====
                fillIfEmpty('cameraModel', data.Model);
                fillIfEmpty('lensModel', data.LensID);
                fillIfEmpty('FocalLength', data.FocalLength);
                fillIfEmpty('ISO', data.ISO);
                fillIfEmpty('F', data.Aperture);
                fillIfEmpty('Shutter', data.ShutterSpeed);
                // fillIfEmpty('shooting_time', data.DateTimeOriginal ? data.DateTimeOriginal.replace(' ', 'T') : '');
                if (data.DateTimeOriginal) {
                    const takenDate = data.DateTimeOriginal.trim();

                    const parts = takenDate.split(' ');
                    if (parts.length === 2) {
                        const dateParts = parts[0].split(':');
                        const timeParts = parts[1].split(':');

                        if (dateParts.length === 3 && timeParts.length >= 2) {
                            const formattedDate =
                                `${dateParts[0]}-${dateParts[1]}-${dateParts[2]}T` +
                                `${timeParts[0]}:${timeParts[1]}`;


                            fillIfEmpty('shooting_time', formattedDate);
                        }
                    }
                }


            } catch (e) {
                console.error('EXIF 服务调用失败', e);
            }
        });

        function fillIfEmpty(id, value) {
            if (!value) return;
            const el = document.getElementById(id);
            if (!el) return;

            // ⭐ 不覆盖用户已经手填的内容
            if (!el.value || el.value.trim() === '') {
                el.value = value;
            }
        }
        const imagePreview = document.getElementById('imagePreview');
        const removePreview = document.getElementById('removePreview');
        const originalDimensions = document.getElementById('originalDimensions');
        const processedDimensions = document.getElementById('processedDimensions');
        const exifInfo = document.getElementById('exifInfo');

        function handleImageUpload(files) {
            if (files && files[0]) {
                const reader = new FileReader();
                const file = files[0];
                if (file.size > maxUploadSize) {
                    alert('文件过大，最大允许上传 45MB');
                    photoInput.value = '';
                    return;
                }
                originalFileSize = file.size;
                const img = new Image();
                img.onload = function() {
                    originalImageWidth = this.width;
                    originalImageHeight = this.height;

                    const compressedInfo = calculateCompressedSize(
                        originalImageWidth,
                        originalImageHeight,
                        originalFileSize
                    );

                    predictedWidth = compressedInfo.width;
                    predictedHeight = compressedInfo.height;
                    scaleRatio = compressedInfo.scale;

                    originalDimensions.textContent = `${originalImageWidth} x ${originalImageHeight}`;
                    processedDimensions.textContent = `${predictedWidth} x ${predictedHeight}`;
                };
                img.src = URL.createObjectURL(file);

                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    imagePreview.style.display = 'block';
                    previewImage.onload = function() {
                        updateWatermarkSettings();
                        setWatermarkToPosition(getSelectedPosition());
                    };
                };

                reader.readAsDataURL(file);
            }
        }


        photoInput.addEventListener('change', function(e) {
            handleImageUpload(e.target.files);
        });

        removePreview.addEventListener('click', function() {
            previewImage.src = '';
            imagePreview.style.display = 'none';
            photoInput.value = '';
            originalImageWidth = 0;
            originalImageHeight = 0;
            originalFileSize = 0;
            predictedWidth = 0;
            predictedHeight = 0;
            scaleRatio = 1.0;
        });

        // 拖拽上传
        const imageUploadArea = document.getElementById('imageUploadArea');
        imageUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            imageUploadArea.style.borderColor = 'var(--primary)';
        });

        imageUploadArea.addEventListener('dragleave', function() {
            imageUploadArea.style.borderColor = 'var(--gray-200)';
        });

        imageUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            imageUploadArea.style.borderColor = 'var(--gray-200)';
            handleImageUpload(e.dataTransfer.files);
        });

        // 监听水印设置变化
        const colorOptions = document.querySelectorAll('.color-option');
        const styleButtons = document.querySelectorAll('.style-btn');
        const watermarkColorInput = document.getElementById('watermark_color');
        const watermarkAuthorStyleInput = document.getElementById('watermark_author_style');

        // 颜色选择逻辑（事件委托 + 立即生效）
        document.querySelectorAll('.color-options').forEach(container => {
            container.addEventListener('click', function(e) {
                const opt = e.target.closest('.color-option');
                if (!opt) return;
                // 切换 UI 状态
                container.querySelectorAll('.color-option').forEach(o => o.classList.remove('active'));
                opt.classList.add('active');

                const c = opt.getAttribute('data-color') || 'white';
                if (watermarkColorInput) watermarkColorInput.value = c;

                // 立即应用到预览（确保即时反馈）
                const textColor = c === 'black' ? '#000000' : '#ffffff';
                if (typeof watermarkTitle !== 'undefined' && watermarkTitle) watermarkTitle.style.color = textColor;
                if (typeof watermarkAuthor !== 'undefined' && watermarkAuthor) watermarkAuthor.style.color = textColor;
                if (typeof watermarkIcon !== 'undefined' && watermarkIcon) watermarkIcon.style.color = textColor;

                // 更新其余样式（阴影 / 大小 等）
                updateWatermarkSettings();
            });
        });

        // 作者样式（事件委托 + 立即生效）
        document.querySelectorAll('.watermark-style-controls').forEach(container => {
            container.addEventListener('click', function(e) {
                const btn = e.target.closest('.style-btn');
                if (!btn) return;
                container.querySelectorAll('.style-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const s = btn.getAttribute('data-style') || 'default';
                if (watermarkAuthorStyleInput) watermarkAuthorStyleInput.value = s;

                // 立即应用到预览
                if (typeof watermarkAuthor !== 'undefined' && watermarkAuthor) {
                    switch (s) {
                        case 'simple':
                            watermarkAuthor.style.fontWeight = '400';
                            watermarkAuthor.style.letterSpacing = 'normal';
                            break;
                        case 'bold':
                            watermarkAuthor.style.fontWeight = '700';
                            watermarkAuthor.style.letterSpacing = '1px';
                            break;
                        default:
                            watermarkAuthor.style.fontWeight = '500';
                            watermarkAuthor.style.letterSpacing = '0.5px';
                            break;
                    }
                }

                updateWatermarkSettings();
            });
        });

        sizeSlider.addEventListener('input', updateWatermarkSettings);
        opacitySlider.addEventListener('input', updateWatermarkSettings);
        positionRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                setSelectedPosition(this.value);
                setWatermarkToPosition(this.value);
            });
        });
        quickPositionButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const pos = this.dataset.position;
                setSelectedPosition(pos);
                setWatermarkToPosition(pos);
            });
        });

        watermarkElement.addEventListener('mousedown', function(e) {
            e.preventDefault();
            if (!previewImage.src) return;
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            initialLeft = parseFloat(watermarkElement.style.left) || 0;
            initialTop = parseFloat(watermarkElement.style.top) || 0;
            watermarkElement.classList.add('dragging');
        });

        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            watermarkElement.style.left = `${initialLeft + (e.clientX - startX)}px`;
            watermarkElement.style.top = `${initialTop + (e.clientY - startY)}px`;
            constrainWatermarkToImage();
        });

        document.addEventListener('mouseup', function() {
            if (!isDragging) return;
            isDragging = false;
            watermarkElement.classList.remove('dragging');
            setSelectedPosition(detectNearestPosition());
        });

        watermarkElement.addEventListener('touchstart', function(e) {
            const touch = e.touches[0];
            if (!touch || !previewImage.src) return;
            e.preventDefault();
            isDragging = true;
            startX = touch.clientX;
            startY = touch.clientY;
            initialLeft = parseFloat(watermarkElement.style.left) || 0;
            initialTop = parseFloat(watermarkElement.style.top) || 0;
            watermarkElement.classList.add('dragging');
        }, {
            passive: false
        });

        document.addEventListener('touchmove', function(e) {
            if (!isDragging) return;
            const touch = e.touches[0];
            if (!touch) return;
            e.preventDefault();
            watermarkElement.style.left = `${initialLeft + (touch.clientX - startX)}px`;
            watermarkElement.style.top = `${initialTop + (touch.clientY - startY)}px`;
            constrainWatermarkToImage();
        }, {
            passive: false
        });

        document.addEventListener('touchend', function() {
            if (!isDragging) return;
            isDragging = false;
            watermarkElement.classList.remove('dragging');
            setSelectedPosition(detectNearestPosition());
        });

        window.addEventListener('resize', function() {
            if (previewImage.complete && originalImageWidth > 0) {
                constrainWatermarkToImage();
            }
        });

        // 飞机信息自动填充功能
        const registrationInput = document.getElementById('registration_number');
        const aircraftModelInput = document.getElementById('aircraft_model');
        const airlineInput = document.getElementById('category');
        const regApiStatus = document.getElementById('regApiStatus');

        // 防抖处理的API请求函数
        const debounceRegCheck = debounce(async function(registration) {
            regApiStatus.textContent = '';

            // 输入长度至少3位才发起请求
            if (registration.length < 3) {
                return;
            }

            try {
                // 显示加载状态
                regApiStatus.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> 加载中...';

                // 调用飞机信息API - 使用HTTPS且不带端口
                const apiUrl = `/api/plane-info.php?registration=${encodeURIComponent(registration)}`;
                const response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    mode: 'cors' // 确保跨域请求正常
                });

                if (!response.ok) {
                    throw new Error(`HTTP错误: ${response.status}`);
                }

                const result = await response.json();

                // 处理API返回结果
                if (result.status === 'success' && result.data) {
                    // 自动填充表单字段
                    if (result.data.运营机构) {
                        airlineInput.value = result.data.运营机构;
                    }
                    if (result.data.机型) {
                        aircraftModelInput.value = result.data.机型;
                    }

                    regApiStatus.innerHTML = '<i class="fas fa-check" style="color: var(--success);"></i> 已自动填充';
                } else {
                    regApiStatus.innerHTML = '<i class="fas fa-info-circle" style="color: #FF7D00;"></i> 未找到该飞机信息';
                }

            } catch (error) {
                console.error('API请求失败:', error);
                regApiStatus.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> 获取信息失败';
            }
        }, 600);

        // 监听注册号输入事件
        registrationInput.addEventListener('input', function() {
            const registration = this.value.trim();
            debounceRegCheck(registration);
        });

        // 失去焦点时再次触发检查
        registrationInput.addEventListener('blur', function() {
            const registration = this.value.trim();
            if (registration.length >= 3) {
                debounceRegCheck(registration);
            }
        });

        // 初始化水印设置
        setSelectedPosition(getSelectedPosition());
        updateWatermarkSettings();

        // 表单提交处理
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            // 检查是否同意使用条款
            if (!document.getElementById('allow_use').checked) {
                e.preventDefault();
                alert('请同意平台使用条款才能上传图片');
                return;
            }

            // 检查是否选择了图片
            if (!photoInput.value) {
                e.preventDefault();
                alert('请选择要上传的图片');
                return;
            }

            // 显示提交中状态
            updateWatermarkRatioInputs();
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 上传中...';

            // 防止重复提交
            this.addEventListener('submit', function(e) {
                e.preventDefault();
            }, {
                once: true
            });
        });
    </script>
    <div id="exifInfo" class="text-sm text-gray-500 mt-2"></div>
    <?php include __DIR__ . '/src/footer.php'; ?>
</body>

</html>