<?php
/**
 * 프로필 이미지 업로드 처리 (AJAX)
 * 고화질 이미지 저장 지원
 */

include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

// 로그인 체크
if (!$is_member) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// 파일 업로드 체크
if (!isset($_FILES['profile_image']) || !is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => '파일이 업로드되지 않았습니다.']);
    exit;
}

$file = $_FILES['profile_image'];
$mb_id = $member['mb_id'];

// 파일 크기 체크 (10MB)
$max_size = 10 * 1024 * 1024;
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => '파일 크기는 10MB 이하로 선택해주세요.']);
    exit;
}

// 이미지 타입 체크
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'JPG, PNG, GIF 이미지만 업로드 가능합니다.']);
    exit;
}

// 디렉토리 생성
$mb_dir = G5_DATA_PATH . '/member_image/' . substr($mb_id, 0, 2);
if (!is_dir(G5_DATA_PATH . '/member_image')) {
    @mkdir(G5_DATA_PATH . '/member_image', G5_DIR_PERMISSION);
    @chmod(G5_DATA_PATH . '/member_image', G5_DIR_PERMISSION);
}
if (!is_dir($mb_dir)) {
    @mkdir($mb_dir, G5_DIR_PERMISSION);
    @chmod($mb_dir, G5_DIR_PERMISSION);
}

// 파일명 (Gnuboard 표준: .gif 확장자 사용하지만 실제 내용은 JPG로 저장)
$dest_file = $mb_dir . '/' . $mb_id . '.gif';

// 원본 이미지 정보
$img_info = getimagesize($file['tmp_name']);
if (!$img_info) {
    echo json_encode(['success' => false, 'message' => '이미지 정보를 읽을 수 없습니다.']);
    exit;
}

$orig_width = $img_info[0];
$orig_height = $img_info[1];
$img_type = $img_info[2];

// 최대 크기 설정 (고화질 유지)
$max_width = 500;
$max_height = 500;

// 리사이즈 필요 여부 확인
$need_resize = ($orig_width > $max_width || $orig_height > $max_height);

// 원본 이미지 로드
switch ($img_type) {
    case IMAGETYPE_JPEG:
        $src_image = imagecreatefromjpeg($file['tmp_name']);
        break;
    case IMAGETYPE_PNG:
        $src_image = imagecreatefrompng($file['tmp_name']);
        break;
    case IMAGETYPE_GIF:
        $src_image = imagecreatefromgif($file['tmp_name']);
        break;
    default:
        echo json_encode(['success' => false, 'message' => '지원하지 않는 이미지 형식입니다.']);
        exit;
}

if (!$src_image) {
    echo json_encode(['success' => false, 'message' => '이미지를 처리할 수 없습니다.']);
    exit;
}

// 리사이즈 처리
if ($need_resize) {
    // 비율 유지하며 크기 계산
    $ratio = min($max_width / $orig_width, $max_height / $orig_height);
    $new_width = round($orig_width * $ratio);
    $new_height = round($orig_height * $ratio);

    // 새 이미지 생성
    $dst_image = imagecreatetruecolor($new_width, $new_height);

    // PNG 투명도 유지
    if ($img_type == IMAGETYPE_PNG) {
        imagealphablending($dst_image, false);
        imagesavealpha($dst_image, true);
        $transparent = imagecolorallocatealpha($dst_image, 255, 255, 255, 127);
        imagefilledrectangle($dst_image, 0, 0, $new_width, $new_height, $transparent);
    }

    // 고품질 리샘플링
    imagecopyresampled($dst_image, $src_image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
    imagedestroy($src_image);
    $final_image = $dst_image;
} else {
    $final_image = $src_image;
}

// 고화질 JPEG로 저장 (품질 95%)
// Gnuboard는 .gif 확장자를 사용하지만 브라우저는 실제 내용 기준으로 표시
$quality = 95;
$saved = imagejpeg($final_image, $dest_file, $quality);
imagedestroy($final_image);

if (!$saved) {
    echo json_encode(['success' => false, 'message' => '이미지 저장에 실패했습니다.']);
    exit;
}

// 파일 권한 설정
@chmod($dest_file, G5_FILE_PERMISSION);

// 이미지 URL 생성
$image_url = G5_DATA_URL . '/member_image/' . substr($mb_id, 0, 2) . '/' . $mb_id . '.gif';

echo json_encode([
    'success' => true,
    'message' => '프로필 사진이 변경되었습니다.',
    'image_url' => $image_url
]);
