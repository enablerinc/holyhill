<?php
if (!defined('_GNUBOARD_')) exit;

// 업로드 사진 자동 축소
// - 긴 변이 HOLYHILL_IMG_MAX_SIDE 보다 크면 축소 (JPEG/PNG/WebP)
// - 휴대폰 사진의 회전 정보(EXIF Orientation)를 반영
// - JPEG 는 다시 저장하면서 촬영 정보(GPS 위치 등)를 제거
// - GIF(움직이는 이미지 가능), 동영상, 문서는 건드리지 않음
// - 처리에 실패하거나 메모리가 부족하면 원본을 그대로 둠
define('HOLYHILL_IMG_MAX_SIDE', 1920);
define('HOLYHILL_IMG_JPEG_QUALITY', 85);

// 처리했으면 array('w','h','type','size'), 아무것도 안 했으면 false
function holyhill_resize_uploaded_image($path)
{
    if (!is_file($path) || !function_exists('imagecreatetruecolor')) return false;

    $info = @getimagesize($path);
    if (!$info) return false;
    list($w, $h, $type) = $info;
    if (!in_array($type, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP))) return false;
    if ($type === IMAGETYPE_WEBP && (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp'))) return false;

    $orientation = 1;
    $has_gps = false;
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        if (!empty($exif['Orientation'])) $orientation = (int)$exif['Orientation'];
        $has_gps = !empty($exif['GPSLatitude']) || !empty($exif['GPSLongitude']);
    }

    $too_big = max($w, $h) > HOLYHILL_IMG_MAX_SIDE;
    $rotated = ($orientation >= 2 && $orientation <= 8);
    if (!$too_big && !$rotated && !$has_gps) return false;

    // 메모리 확인: 원본 + 결과 이미지를 함께 올릴 여유가 없으면 원본 유지
    $need = (int)($w * $h * 5 * 1.8) + 8 * 1024 * 1024;
    $limit = holyhill_memory_limit_bytes();
    if ($limit > 0 && memory_get_usage(true) + $need > $limit) {
        // 이번 요청에서만 한도를 올려 보고(최대 1GB), 안 되면 원본 유지
        $want = min(1024 * 1024 * 1024, memory_get_usage(true) + $need);
        @ini_set('memory_limit', (string)ceil($want / 1048576).'M');
        $limit = holyhill_memory_limit_bytes();
        if ($limit > 0 && memory_get_usage(true) + $need > $limit) return false;
    }

    switch ($type) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($path); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($path); break;
        default:             $src = @imagecreatefromwebp($path); break;
    }
    if (!$src) return false;

    // 회전/반전 정보 반영
    if ($rotated) {
        if (in_array($orientation, array(2, 4, 5, 7)) && function_exists('imageflip')) {
            imageflip($src, ($orientation == 4) ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }
        $angle = 0;
        if (in_array($orientation, array(3, 4))) $angle = 180;
        else if (in_array($orientation, array(5, 6))) $angle = -90;
        else if (in_array($orientation, array(7, 8))) $angle = 90;
        if ($angle) {
            $r = imagerotate($src, $angle, 0);
            if ($r) { imagedestroy($src); $src = $r; }
        }
        $w = imagesx($src);
        $h = imagesy($src);
    }

    // 축소
    $scale = min(1, HOLYHILL_IMG_MAX_SIDE / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    if ($scale < 1) {
        $dst = imagecreatetruecolor($nw, $nh);
        if ($type !== IMAGETYPE_JPEG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    } else if ($type !== IMAGETYPE_JPEG) {
        imagesavealpha($src, true);
    }

    // 같은 폴더의 임시 파일에 저장한 뒤 교체
    $tmp = $path.'.resize-'.getmypid().'.tmp';
    switch ($type) {
        case IMAGETYPE_JPEG: $ok = imagejpeg($src, $tmp, HOLYHILL_IMG_JPEG_QUALITY); break;
        case IMAGETYPE_PNG:  $ok = imagepng($src, $tmp, 6); break;
        default:             $ok = imagewebp($src, $tmp, HOLYHILL_IMG_JPEG_QUALITY); break;
    }
    imagedestroy($src);

    clearstatcache();
    if (!$ok || !is_file($tmp) || filesize($tmp) < 1) { @unlink($tmp); return false; }

    // 회전·GPS 때문이 아니라 크기 때문에만 다시 저장했는데 오히려 커졌으면 원본 유지
    if (!$rotated && !$has_gps && filesize($tmp) >= filesize($path)) { @unlink($tmp); return false; }

    $perm = @fileperms($path);
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    if ($perm) @chmod($path, $perm & 0777);

    clearstatcache();
    return array('w' => $nw, 'h' => $nh, 'type' => $type, 'size' => filesize($path));
}

function holyhill_memory_limit_bytes()
{
    $v = trim((string)ini_get('memory_limit'));
    if ($v === '' || $v === '-1') return 0;
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return $n;
}

// write_update.php: 파일을 옮긴 직후 축소 → 배열의 크기 정보도 새 값으로 맞춤
$GLOBALS['holyhill_resized'] = array();

add_replace('write_update_upload_file', function ($dest_file) {
    $r = holyhill_resize_uploaded_image($dest_file);
    if ($r) $GLOBALS['holyhill_resized'][$dest_file] = $r;
    return $dest_file;
}, G5_HOOK_DEFAULT_PRIORITY, 1);

add_replace('write_update_upload_array', function ($upload, $dest_file) {
    if (isset($GLOBALS['holyhill_resized'][$dest_file])) {
        $r = $GLOBALS['holyhill_resized'][$dest_file];
        $upload['image'][0] = $r['w'];
        $upload['image'][1] = $r['h'];
        $upload['image'][2] = $r['type'];
        $upload['filesize'] = $r['size'];
    }
    return $upload;
}, G5_HOOK_DEFAULT_PRIORITY, 2);
