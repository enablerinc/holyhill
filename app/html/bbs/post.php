<?php
include_once('./_common.php');

// 게시판 및 글 ID 받기
$bo_table = isset($_GET['bo_table']) ? clean_xss_tags($_GET['bo_table']) : '';
$wr_id = isset($_GET['wr_id']) ? (int)$_GET['wr_id'] : 0;

if (!$bo_table || !$wr_id) {
    alert('잘못된 접근입니다.', G5_BBS_URL.'/index.php');
}

// 게시판 정보 가져오기
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");
if (!$board) {
    alert('존재하지 않는 게시판입니다.', G5_BBS_URL.'/index.php');
}

// 글 테이블
$write_table = $g5['write_prefix'] . $bo_table;

// 글 정보 가져오기
$write = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$wr_id}'");
if (!$write) {
    alert('존재하지 않는 게시글입니다.', G5_BBS_URL.'/feed.php');
}

// 권한 체크
if ($member['mb_level'] < $board['bo_read_level']) {
    if ($member['mb_id'])
        alert('글을 읽을 권한이 없습니다.', G5_BBS_URL.'/feed.php');
    else
        alert('글을 읽을 권한이 없습니다.\\n\\n회원이시라면 로그인 후 이용해 보십시오.', G5_BBS_URL.'/login.php?url='.urlencode($_SERVER['REQUEST_URI']));
}

// 비밀글 체크
if (strstr($write['wr_option'], 'secret')) {
    $is_owner = false;
    if ($write['mb_id'] && $write['mb_id'] === $member['mb_id']) {
        $is_owner = true;
    }

    if (!$is_owner && !$is_admin) {
        alert('비밀글입니다. 작성자만 볼 수 있습니다.', G5_BBS_URL.'/feed.php');
    }
}

// 조회수 증가 (한 번만)
$ss_name = 'ss_view_'.$bo_table.'_'.$wr_id;
if (!get_session($ss_name)) {
    sql_query("UPDATE {$write_table} SET wr_hit = wr_hit + 1 WHERE wr_id = '{$wr_id}'");
    set_session($ss_name, TRUE);
}

$g5['title'] = strip_tags($write['wr_subject']);

// 시간 표시 함수
function get_time_ago($datetime) {
    $time_diff = time() - strtotime($datetime);

    if ($time_diff < 60) {
        return '방금 전';
    } elseif ($time_diff < 3600) {
        return floor($time_diff / 60) . '분 전';
    } elseif ($time_diff < 86400) {
        return floor($time_diff / 3600) . '시간 전';
    } elseif ($time_diff < 2592000) { // 30일
        return floor($time_diff / 86400) . '일 전';
    } else {
        return date('Y-m-d', strtotime($datetime));
    }
}

// 댓글 내용에서 유튜브 URL을 iframe으로 변환하는 함수
function process_comment_content($content) {
    // YouTube URL 패턴
    $patterns = array(
        '/https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)(?:&[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i'
    );

    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, function($matches) {
            $video_id = $matches[1];
            $iframe_html = '<div class="youtube-container my-2" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 0.5rem;"><iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 0.5rem;" src="https://www.youtube.com/embed/' . $video_id . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>';
            return $iframe_html;
        }, $content);
    }

    return $content;
}

// 수정, 삭제 링크
$update_href = $delete_href = '';
if (($member['mb_id'] && ($member['mb_id'] === $write['mb_id'])) || $is_admin) {
    $update_href = G5_BBS_URL.'/write.php?w=u&bo_table='.$bo_table.'&wr_id='.$wr_id;
    set_session('ss_delete_token', $token = uniqid(time()));
    $delete_href = G5_BBS_URL.'/delete.php?bo_table='.$bo_table.'&wr_id='.$wr_id.'&token='.$token;
}

// 작성자 정보
$mb_nick = $write['wr_name'] ? $write['wr_name'] : '알 수 없음';
$mb_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';

if ($write['mb_id']) {
    $mb_result = sql_query("SELECT mb_name FROM {$g5['member_table']} WHERE mb_id = '{$write['mb_id']}'");
    if ($mb_result && $row = sql_fetch_array($mb_result)) {
        $mb_nick = $row['mb_name'];  // 이름 사용
    }

    // 프로필 이미지
    $profile_path = G5_DATA_PATH.'/member_image/'.substr($write['mb_id'], 0, 2).'/'.$write['mb_id'].'.gif';
    if (file_exists($profile_path)) {
        $mb_photo = G5_DATA_URL.'/member_image/'.substr($write['mb_id'], 0, 2).'/'.$write['mb_id'].'.gif';
    }
}

// 미디어 파일 (이미지 + 동영상)
$media_files = array();
$images = array();
$videos = array();

$file_result = sql_query("SELECT bf_file, bf_type FROM {$g5['board_file_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' ORDER BY bf_no");
while ($file = sql_fetch_array($file_result)) {
    $file_ext = strtolower(pathinfo($file['bf_file'], PATHINFO_EXTENSION));
    $video_exts = array('mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv');

    $media_files[] = array(
        'file' => $file['bf_file'],
        'type' => in_array($file_ext, $video_exts) ? 'video' : 'image'
    );

    if (in_array($file_ext, $video_exts)) {
        $videos[] = $file['bf_file'];
    } else if ($file['bf_type'] >= 1 && $file['bf_type'] <= 3) {
        $images[] = $file['bf_file'];
    }
}

// 좋아요 체크
$is_good = false;
if ($is_member) {
    $good_result = sql_query("SELECT COUNT(*) as cnt FROM {$g5['board_good_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND mb_id = '{$member['mb_id']}'");
    $good_row = sql_fetch_array($good_result);
    $is_good = $good_row['cnt'] > 0;
}

// 댓글 토큰 생성
$comment_token = '';
if ($is_member) {
    $comment_token = get_random_token_string();
    set_session('ss_comment_token', $comment_token);
}

// 댓글 작성자 프로필 사진 (답글 입력창에서 사용)
$comment_profile_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
if ($is_member) {
    $comment_profile_path = G5_DATA_PATH.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif';
    if (file_exists($comment_profile_path)) {
        $comment_profile_photo = G5_DATA_URL.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif';
    }
}

// 본문에 사용된 미디어 인덱스 추출 (이미지 + 동영상)
$used_media_indices = array();

// 이미지 인덱스
preg_match_all('/\[이미지(\d+)\]/', $write['wr_content'], $matches);
if (!empty($matches[1])) {
    foreach ($matches[1] as $num) {
        $used_media_indices[] = intval($num) - 1;
    }
}

// 동영상 인덱스
preg_match_all('/\[동영상(\d+)\]/', $write['wr_content'], $matches);
if (!empty($matches[1])) {
    foreach ($matches[1] as $num) {
        $used_media_indices[] = intval($num) - 1;
    }
}

// YouTube URL을 플레이스홀더로 변환하고 iframe 저장
$youtube_iframes = array();
$youtube_placeholder_index = 0;

function extract_youtube_urls($content) {
    global $youtube_iframes, $youtube_placeholder_index;

    $patterns = array(
        '/https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)(?:&[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i'
    );

    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, function($matches) use (&$youtube_placeholder_index) {
            global $youtube_iframes;

            $video_id = $matches[1];
            $iframe_html = '<div class="youtube-container my-4" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 0.5rem;"><iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 0.5rem;" src="https://www.youtube.com/embed/' . $video_id . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>';

            $placeholder = '[YOUTUBE_PLACEHOLDER_' . $youtube_placeholder_index . ']';
            $youtube_iframes[$placeholder] = $iframe_html;
            $youtube_placeholder_index++;

            return $placeholder;
        }, $content);
    }

    return $content;
}

// 플레이스홀더를 실제 iframe으로 복원
function restore_youtube_iframes($content) {
    global $youtube_iframes;

    foreach ($youtube_iframes as $placeholder => $iframe) {
        $content = str_replace($placeholder, $iframe, $content);
    }

    return $content;
}

// 본문에서 [이미지N]을 실제 이미지로 변환
function replace_image_placeholders($content, $media_files, $bo_table) {
    $content = preg_replace_callback('/\[이미지(\d+)\]/', function($matches) use ($media_files, $bo_table) {
        $index = intval($matches[1]) - 1;
        if (isset($media_files[$index]) && $media_files[$index]['type'] === 'image') {
            $image_url = G5_DATA_URL.'/file/'.$bo_table.'/'.$media_files[$index]['file'];
            return '<div class="my-4"><img src="'.$image_url.'" class="w-full rounded-lg cursor-pointer image-viewable" alt="이미지'.($index+1).'" onclick="openImageViewer(\''.$image_url.'\')"></div>';
        }
        return $matches[0];
    }, $content);
    return $content;
}

// 본문에서 [동영상N]을 실제 비디오 플레이어로 변환
function replace_video_placeholders($content, $media_files, $bo_table) {
    $content = preg_replace_callback('/\[동영상(\d+)\]/', function($matches) use ($media_files, $bo_table) {
        $index = intval($matches[1]) - 1;
        if (isset($media_files[$index]) && $media_files[$index]['type'] === 'video') {
            $video_url = G5_DATA_URL.'/file/'.$bo_table.'/'.$media_files[$index]['file'];
            return '<div class="video-container my-4" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 0.5rem;">
                <video style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 0.5rem; object-fit: contain; background: #000;" controls controlsList="nodownload">
                    <source src="'.$video_url.'" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>';
        }
        return $matches[0];
    }, $content);
    return $content;
}

// 본문 내용 처리
$raw_content = $write['wr_content'];
// 1. YouTube URL을 플레이스홀더로 변환
$raw_content = extract_youtube_urls($raw_content);
// 2. 텍스트 정리
$processed_content = get_text($raw_content);
// 3. 이미지 변환
$processed_content = replace_image_placeholders($processed_content, $media_files, $bo_table);
// 4. 동영상 변환
$processed_content = replace_video_placeholders($processed_content, $media_files, $bo_table);
// 5. 줄바꿈 처리
$processed_content = nl2br($processed_content);
// 6. 플레이스홀더를 실제 iframe으로 복원
$processed_content = restore_youtube_iframes($processed_content);

// 상단 갤러리용 미디어 (본문에 사용되지 않은 이미지와 동영상)
$gallery_media = array();
foreach ($media_files as $idx => $media) {
    if (!in_array($idx, $used_media_indices)) {
        $gallery_media[] = $media;
    }
}

// 이전/다음 게시글 가져오기
$prev_post = sql_fetch("SELECT wr_id, wr_subject FROM {$write_table} WHERE wr_id < '{$wr_id}' AND wr_is_comment = 0 ORDER BY wr_id DESC LIMIT 1");
$next_post = sql_fetch("SELECT wr_id, wr_subject FROM {$write_table} WHERE wr_id > '{$wr_id}' AND wr_is_comment = 0 ORDER BY wr_id ASC LIMIT 1");

// 이전/다음 게시글 썸네일 가져오기 함수
function get_post_thumbnail($wr_id, $bo_table, $g5) {
    // 첨부파일에서 이미지 찾기
    $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND bf_type BETWEEN 1 AND 3 ORDER BY bf_no LIMIT 1");
    if ($img = sql_fetch_array($img_result)) {
        return G5_DATA_URL.'/file/'.$bo_table.'/'.$img['bf_file'];
    }

    // 본문에서 이미지 찾기
    $write_table = $g5['write_prefix'] . $bo_table;
    $content = sql_fetch("SELECT wr_content FROM {$write_table} WHERE wr_id = '{$wr_id}'");
    if ($content && preg_match('/<img[^>]+src=["\']?([^"\'>\s]+)["\']?[^>]*>/i', $content['wr_content'], $img_match)) {
        return $img_match[1];
    }

    return '';
}

$prev_thumbnail = $prev_post ? get_post_thumbnail($prev_post['wr_id'], $bo_table, $g5) : '';
$next_thumbnail = $next_post ? get_post_thumbnail($next_post['wr_id'], $bo_table, $g5) : '';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    <link rel="icon" type="image/png" href="<?php echo G5_IMG_URL; ?>/logo.png">
    <link rel="apple-touch-icon" href="<?php echo G5_IMG_URL; ?>/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script> window.FontAwesomeConfig = { autoReplaceSvg: 'nest'};</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { display: none; }
        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f9fafb;
            margin: 0;
            padding: 0;
        }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="bg-gray-50">

<div class="max-w-2xl mx-auto bg-white min-h-screen">

    <!-- 헤더 -->
    <header class="fixed top-0 left-0 right-0 bg-white border-b z-50" style="max-width: 640px; margin: 0 auto;">
        <div class="flex items-center justify-between px-4 py-3">
            <button onclick="history.back()" class="w-8 flex justify-start"><i class="fa-solid fa-arrow-left text-xl"></i></button>

            <!-- 성산교회 로고 (클릭 시 홈으로 이동) -->
            <a href="<?php echo G5_BBS_URL; ?>/index.php" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                <img src="<?php echo G5_URL; ?>/img/logo.png" alt="성산교회" class="w-7 h-7 rounded-lg object-cover">
                <span class="text-sm font-semibold text-gray-700">성산교회</span>
            </a>

            <?php if ($delete_href) { ?>
            <div class="relative w-8 flex justify-end">
                <button onclick="toggleMenu()" id="menuBtn"><i class="fa-solid fa-ellipsis-vertical text-xl"></i></button>
                <div id="menuDropdown" class="hidden absolute right-0 top-8 bg-white border rounded-lg shadow-lg py-2 w-32 z-50">
                    <a href="#" onclick="confirmDelete(event)" class="block px-4 py-2 hover:bg-gray-100 text-sm text-red-600">
                        <i class="fa-solid fa-trash mr-2"></i>삭제
                    </a>
                </div>
            </div>
            <?php } else { ?>
            <div class="w-8"></div>
            <?php } ?>
        </div>
    </header>

    <!-- 본문 -->
    <main style="padding-top: 64px; padding-bottom: 200px;">
        <article>
            <!-- 제목 -->
            <div class="p-4">
                <h2 class="text-xl font-bold"><?php echo get_text($write['wr_subject']); ?></h2>
            </div>

            <!-- 구분선 -->
            <hr class="border-gray-200">

            <!-- 작성자 -->
            <div class="p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="<?php echo $mb_photo; ?>" class="w-10 h-10 rounded-full" alt="">
                    <div class="font-semibold"><?php echo $mb_nick; ?></div>
                </div>
                <div class="text-sm text-gray-500"><?php echo get_time_ago($write['wr_datetime']); ?></div>
            </div>

            <!-- 구분선 -->
            <hr class="border-gray-200">

            <!-- 내용 -->
            <div class="p-4">
                <!-- 미디어 갤러리 (본문에 삽입되지 않은 이미지와 동영상 표시) - 세로 스크롤 방식 -->
                <?php if (count($gallery_media) > 0) { ?>
                <div class="space-y-4 mb-4">
                    <?php foreach ($gallery_media as $media) {
                        $media_url = G5_DATA_URL.'/file/'.$bo_table.'/'.$media['file'];
                    ?>
                    <div class="w-full">
                        <?php if ($media['type'] === 'video') { ?>
                            <div class="relative w-full bg-black rounded-lg overflow-hidden" style="aspect-ratio: 16/9;">
                                <video class="w-full h-full object-contain" controls controlsList="nodownload">
                                    <source src="<?php echo $media_url; ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        <?php } else { ?>
                            <!-- 이미지: 원본 비율 유지, 가로/세로 모두 잘리지 않게 -->
                            <img src="<?php echo $media_url; ?>"
                                 class="w-full h-auto rounded-lg cursor-pointer image-viewable"
                                 style="max-height: 80vh; object-fit: contain;"
                                 alt="갤러리 이미지"
                                 onclick="openImageViewer('<?php echo $media_url; ?>')">
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>

                <!-- 본문 내용 -->
                <div><?php echo $processed_content; ?></div>
            </div>

            <!-- 구분선 -->
            <hr class="border-gray-200">

            <!-- 좋아요(하트) -->
            <div class="p-4">
                <button onclick="toggleGood()" class="flex items-center gap-2">
                    <i class="<?php echo $is_good ? 'fa-solid' : 'fa-regular'; ?> fa-heart text-red-500 text-2xl" id="heartIcon"></i>
                    <span id="goodCount" class="font-semibold text-sm">좋아요 <?php echo $write['wr_good']; ?>개</span>
                </button>
            </div>

            <!-- 구분선 -->
            <hr class="border-gray-200">

            <!-- 이전/다음 게시글 -->
            <?php if ($prev_post || $next_post) { ?>
            <div class="p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">
                    <i class="fa-solid fa-arrows-left-right text-purple-500 mr-1"></i> 다른 게시글
                </h3>
                <div class="grid grid-cols-2 gap-3">
                    <?php if ($prev_post) { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $prev_post['wr_id']; ?>"
                       class="block bg-gray-50 rounded-xl overflow-hidden hover:bg-gray-100 transition-colors">
                        <?php if ($prev_thumbnail) { ?>
                        <div class="aspect-video relative">
                            <img src="<?php echo $prev_thumbnail; ?>" alt="이전글" class="w-full h-full object-cover">
                            <div class="absolute top-2 left-2 bg-black/50 text-white text-xs px-2 py-0.5 rounded">
                                <i class="fa-solid fa-chevron-left mr-1"></i>이전
                            </div>
                        </div>
                        <?php } else { ?>
                        <div class="aspect-video bg-gradient-to-br from-purple-50 to-pink-50 flex items-center justify-center">
                            <div class="text-center">
                                <i class="fa-solid fa-chevron-left text-purple-400 text-lg"></i>
                                <p class="text-xs text-purple-500 mt-1">이전글</p>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="p-2">
                            <p class="text-xs text-gray-700 line-clamp-1 font-medium"><?php echo cut_str(get_text($prev_post['wr_subject']), 20); ?></p>
                        </div>
                    </a>
                    <?php } else { ?>
                    <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-center opacity-50">
                        <p class="text-xs text-gray-400">이전 글 없음</p>
                    </div>
                    <?php } ?>

                    <?php if ($next_post) { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $next_post['wr_id']; ?>"
                       class="block bg-gray-50 rounded-xl overflow-hidden hover:bg-gray-100 transition-colors">
                        <?php if ($next_thumbnail) { ?>
                        <div class="aspect-video relative">
                            <img src="<?php echo $next_thumbnail; ?>" alt="다음글" class="w-full h-full object-cover">
                            <div class="absolute top-2 right-2 bg-black/50 text-white text-xs px-2 py-0.5 rounded">
                                다음<i class="fa-solid fa-chevron-right ml-1"></i>
                            </div>
                        </div>
                        <?php } else { ?>
                        <div class="aspect-video bg-gradient-to-br from-blue-50 to-purple-50 flex items-center justify-center">
                            <div class="text-center">
                                <i class="fa-solid fa-chevron-right text-blue-400 text-lg"></i>
                                <p class="text-xs text-blue-500 mt-1">다음글</p>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="p-2">
                            <p class="text-xs text-gray-700 line-clamp-1 font-medium"><?php echo cut_str(get_text($next_post['wr_subject']), 20); ?></p>
                        </div>
                    </a>
                    <?php } else { ?>
                    <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-center opacity-50">
                        <p class="text-xs text-gray-400">다음 글 없음</p>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <!-- 구분선 -->
            <hr class="border-gray-200">
            <?php } ?>

            <!-- 댓글 -->
            <div class="p-4">
                <?php
                // 댓글 페이지네이션 설정
                $comments_per_page = 50;
                $total_comments_count = $write['wr_comment'];
                $total_comment_pages = max(1, ceil($total_comments_count / $comments_per_page));

                // 기본값: 마지막 페이지 (최신 댓글)
                $comment_page = isset($_GET['comment_page']) ? (int)$_GET['comment_page'] : $total_comment_pages;
                if ($comment_page < 1) $comment_page = 1;
                if ($comment_page > $total_comment_pages) $comment_page = $total_comment_pages;

                $comment_offset = ($comment_page - 1) * $comments_per_page;
                ?>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">댓글 <?php echo $write['wr_comment']; ?>개</h3>
                    <?php if ($total_comment_pages > 1) { ?>
                    <span class="text-xs text-gray-500"><?php echo $comment_page; ?> / <?php echo $total_comment_pages; ?> 페이지</span>
                    <?php } ?>
                </div>

                <?php if ($total_comment_pages > 1) { ?>
                <!-- 댓글 페이지네이션 (상단) -->
                <div class="flex items-center justify-center gap-1 mb-4 flex-wrap">
                    <?php if ($comment_page > 1) { ?>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=1"
                       class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">«</a>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=<?php echo $comment_page - 1; ?>"
                       class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">‹ 이전</a>
                    <?php } ?>

                    <?php
                    // 페이지 번호 표시 (최대 5개)
                    $start_page = max(1, $comment_page - 2);
                    $end_page = min($total_comment_pages, $start_page + 4);
                    if ($end_page - $start_page < 4) {
                        $start_page = max(1, $end_page - 4);
                    }
                    for ($p = $start_page; $p <= $end_page; $p++) {
                        $active_class = ($p == $comment_page) ? 'bg-purple-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700';
                    ?>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=<?php echo $p; ?>"
                       class="px-3 py-1 text-xs <?php echo $active_class; ?> rounded"><?php echo $p; ?></a>
                    <?php } ?>

                    <?php if ($comment_page < $total_comment_pages) { ?>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=<?php echo $comment_page + 1; ?>"
                       class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">다음 ›</a>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=<?php echo $total_comment_pages; ?>"
                       class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">»</a>
                    <?php } ?>
                </div>
                <?php } ?>

                <div id="comment-list">
                <?php
                // 페이지에 해당하는 댓글 가져오기
                $comment_result = sql_query("SELECT * FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1 ORDER BY wr_id ASC LIMIT {$comment_offset}, {$comments_per_page}");

                // 댓글을 배열로 변환하고 계층 구조 생성
                $all_comments = array();
                $children_map = array(); // 부모ID => 자식 댓글 배열

                while ($c = sql_fetch_array($comment_result)) {
                    $all_comments[$c['wr_id']] = $c;
                    $parent_id = (int)$c['wr_comment_parent'];

                    if (!isset($children_map[$parent_id])) {
                        $children_map[$parent_id] = array();
                    }
                    $children_map[$parent_id][] = $c;
                }

                // 재귀적으로 댓글 렌더링하는 함수
                function render_comment($comment, $depth, $children_map, $g5, $is_member, $is_admin, $member, $comment_profile_photo) {
                    // 최대 들여쓰기 깊이 (4단계까지만 들여쓰기)
                    $max_indent = 4;
                    $indent_level = min($depth, $max_indent);

                    // 들여쓰기 클래스 계산 (ml-8씩 증가, 최대 ml-32)
                    $indent_class = $depth > 0 ? 'ml-' . ($indent_level * 8) : '';

                    $c = $comment;
                    $c_nick = $c['wr_name'] ? $c['wr_name'] : '알 수 없음';
                    $c_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';

                    if ($c['mb_id']) {
                        $c_mb = sql_fetch("SELECT mb_name FROM {$g5['member_table']} WHERE mb_id = '{$c['mb_id']}'");
                        if ($c_mb) {
                            $c_nick = $c_mb['mb_name'];
                        }

                        $c_profile_path = G5_DATA_PATH.'/member_image/'.substr($c['mb_id'], 0, 2).'/'.$c['mb_id'].'.gif';
                        if (file_exists($c_profile_path)) {
                            $c_photo = G5_DATA_URL.'/member_image/'.substr($c['mb_id'], 0, 2).'/'.$c['mb_id'].'.gif';
                        }
                    }
                    ?>
                    <div id="c_<?php echo $c['wr_id']; ?>" class="mb-3 <?php echo $indent_class; ?>" data-depth="<?php echo $depth; ?>">
                        <div class="flex gap-3">
                            <img src="<?php echo $c_photo; ?>" class="w-8 h-8 rounded-full flex-shrink-0">
                            <div class="flex-1">
                                <div class="bg-gray-50 rounded-2xl px-3 py-2">
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="font-semibold text-xs"><?php echo $c_nick; ?></div>
                                        <div class="text-xs text-gray-400"><?php echo get_time_ago($c['wr_datetime']); ?></div>
                                    </div>
                                    <div class="text-sm comment-content-<?php echo $c['wr_id']; ?>"><?php echo process_comment_content(nl2br(get_text($c['wr_content']))); ?></div>
                                </div>
                                <div class="flex gap-2 mt-1 ml-3">
                                    <?php if ($is_member) { ?>
                                    <button onclick="toggleReplyForm(<?php echo $c['wr_id']; ?>)" class="text-xs text-gray-500">답글</button>
                                    <?php } ?>
                                    <?php if (($is_member && $member['mb_id'] === $c['mb_id']) || $is_admin) { ?>
                                    <button onclick="editComment(<?php echo $c['wr_id']; ?>, '<?php echo addslashes(str_replace("\n", "\\n", get_text($c['wr_content']))); ?>')" class="text-xs text-gray-500">수정</button>
                                    <button onclick="deleteComment(<?php echo $c['wr_id']; ?>)" class="text-xs text-red-500">삭제</button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <!-- 답글 입력창 -->
                        <?php if ($is_member) { ?>
                        <div id="reply-form-<?php echo $c['wr_id']; ?>" class="hidden mt-2 ml-11">
                            <div class="flex gap-2 items-center">
                                <img src="<?php echo $comment_profile_photo; ?>" class="w-7 h-7 rounded-full flex-shrink-0" alt="프로필">
                                <div class="flex-1 flex gap-2 bg-gray-100 rounded-full px-3 py-2 items-center">
                                    <input
                                        type="text"
                                        class="reply-input flex-1 bg-transparent border-none outline-none text-sm"
                                        placeholder="답글 입력..."
                                        data-parent-id="<?php echo $c['wr_id']; ?>"
                                        onkeypress="if(event.key==='Enter'){submitReply(<?php echo $c['wr_id']; ?>)}">
                                    <button onclick="submitReply(<?php echo $c['wr_id']; ?>)" class="bg-transparent border-none cursor-pointer p-1">
                                        <i class="fa-solid fa-paper-plane text-purple-600 text-base"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php

                    // 자식 댓글들 재귀적으로 렌더링
                    if (isset($children_map[$c['wr_id']])) {
                        foreach ($children_map[$c['wr_id']] as $child) {
                            render_comment($child, $depth + 1, $children_map, $g5, $is_member, $is_admin, $member, $comment_profile_photo);
                        }
                    }
                }

                // 최상위 댓글들 (wr_comment_parent = 0)
                $root_comments = isset($children_map[0]) ? $children_map[0] : array();

                if (count($root_comments) > 0) {
                    foreach ($root_comments as $comment) {
                        render_comment($comment, 0, $children_map, $g5, $is_member, $is_admin, $member, $comment_profile_photo);
                    }
                } else {
                    echo '<div class="text-center text-gray-500 py-4">첫 댓글을 남겨보세요!</div>';
                }
                ?>
                </div>

                <?php if ($total_comment_pages > 1) { ?>
                <!-- 댓글 페이지네이션 (하단) -->
                <div class="flex items-center justify-center gap-1 mt-4 flex-wrap">
                    <?php if ($comment_page > 1) { ?>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=1"
                       class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">«</a>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=<?php echo $comment_page - 1; ?>"
                       class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">‹ 이전</a>
                    <?php } ?>

                    <?php
                    for ($p = $start_page; $p <= $end_page; $p++) {
                        $active_class = ($p == $comment_page) ? 'bg-purple-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700';
                    ?>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=<?php echo $p; ?>"
                       class="px-3 py-1 text-xs <?php echo $active_class; ?> rounded"><?php echo $p; ?></a>
                    <?php } ?>

                    <?php if ($comment_page < $total_comment_pages) { ?>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=<?php echo $comment_page + 1; ?>"
                       class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">다음 ›</a>
                    <a href="?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&comment_page=<?php echo $total_comment_pages; ?>"
                       class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">»</a>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </article>
    </main>

</div>

<!-- 댓글 입력창 -->
<div id="commentFormWrapper" style="position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 2px solid #e5e7eb; z-index: 99999; box-shadow: 0 -4px 12px rgba(0,0,0,0.1);">
    <div style="max-width: 640px; margin: 0 auto;">
        <?php if ($is_member) { ?>
        <form id="commentForm" method="post" action="<?php echo G5_BBS_URL; ?>/comment_write_ajax.php" style="padding: 16px;" novalidate>
            <input type="hidden" name="w" value="c">
            <input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
            <input type="hidden" name="wr_id" value="<?php echo $wr_id; ?>">
            <input type="hidden" name="comment_id" value="">
            <input type="hidden" name="token" value="<?php echo $comment_token; ?>">

            <div style="display: flex; gap: 12px; align-items: center;">
                <img src="<?php echo $comment_profile_photo; ?>" style="width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;" alt="프로필">
                <div style="flex: 1; display: flex; gap: 8px; background: #f3f4f6; border-radius: 9999px; padding: 8px 16px; align-items: center;">
                    <input
                        type="text"
                        name="wr_content"
                        id="commentInput"
                        placeholder="댓글 입력..."
                        style="flex: 1; background: transparent; border: none; outline: none; font-size: 14px;"
                        required>
                    <button type="submit" style="background: none; border: none; cursor: pointer; padding: 4px;">
                        <i class="fa-solid fa-paper-plane" style="color: #9333ea; font-size: 18px;"></i>
                    </button>
                </div>
            </div>
        </form>
        <?php } else { ?>
        <div style="padding: 16px; text-align: center;">
            <a href="<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" style="color: #9333ea; text-decoration: none;">
                로그인하고 댓글 남기기
            </a>
        </div>
        <?php } ?>
    </div>
</div>

<script>
// 메뉴 토글
function toggleMenu() {
    const dropdown = document.getElementById('menuDropdown');
    dropdown.classList.toggle('hidden');
}

// 메뉴 외부 클릭시 닫기
document.addEventListener('click', function(e) {
    const menuBtn = document.getElementById('menuBtn');
    const dropdown = document.getElementById('menuDropdown');
    if (menuBtn && dropdown && !menuBtn.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

// 삭제 확인
function confirmDelete(e) {
    e.preventDefault();
    if (confirm('정말로 이 게시글을 삭제하시겠습니까?')) {
        location.href = '<?php echo $delete_href; ?>';
    }
}

// 답글 폼 토글
function toggleReplyForm(commentId) {
    // 모든 답글 폼 닫기
    document.querySelectorAll('[id^="reply-form-"]').forEach(form => {
        if (form.id !== 'reply-form-' + commentId) {
            form.classList.add('hidden');
        }
    });

    // 현재 답글 폼 토글
    const replyForm = document.getElementById('reply-form-' + commentId);
    if (replyForm) {
        replyForm.classList.toggle('hidden');
        if (!replyForm.classList.contains('hidden')) {
            const input = replyForm.querySelector('.reply-input');
            if (input) input.focus();
        }
    }
}

// 답글 제출
function submitReply(parentCommentId) {
    const replyForm = document.getElementById('reply-form-' + parentCommentId);
    if (!replyForm) return;

    const input = replyForm.querySelector('.reply-input');
    if (!input || !input.value.trim()) {
        alert('답글 내용을 입력해주세요.');
        return;
    }

    const commentForm = document.getElementById('commentForm');
    if (!commentForm) return;

    const tokenInput = commentForm.querySelector('input[name="token"]');
    if (!tokenInput || !tokenInput.value) {
        alert('토큰 오류가 발생했습니다. 페이지를 새로고침해주세요.');
        return;
    }

    const formData = new FormData();
    formData.append('w', 'c');
    formData.append('bo_table', '<?php echo $bo_table; ?>');
    formData.append('wr_id', '<?php echo $wr_id; ?>');
    formData.append('wr_content', input.value.trim());
    formData.append('parent_comment_id', parentCommentId);
    formData.append('token', tokenInput.value);

    const submitBtn = replyForm.querySelector('button');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    fetch('<?php echo G5_BBS_URL; ?>/comment_write_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.new_token && tokenInput) {
                tokenInput.value = data.new_token;
            }

            if (data.comment.id) {
                window.lastCommentId = Math.max(window.lastCommentId, data.comment.id);
            }

            // 답글을 부모 댓글 아래에 추가
            const parentComment = document.getElementById('c_' + parentCommentId);
            if (parentComment) {
                // 부모 댓글의 깊이 확인
                const parentDepth = parseInt(parentComment.dataset.depth || 0);
                const newDepth = parentDepth + 1;
                // 최대 4단계까지만 들여쓰기
                const indentLevel = Math.min(newDepth, 4);
                const indentClass = newDepth > 0 ? 'ml-' + (indentLevel * 8) : '';

                const newReplyHTML = `
                    <div id="c_${data.comment.id}" class="mb-3 ${indentClass}" data-depth="${newDepth}" style="animation: slideIn 0.3s ease-out;">
                        <div class="flex gap-3">
                            <img src="${data.comment.photo}" class="w-8 h-8 rounded-full flex-shrink-0">
                            <div class="flex-1">
                                <div class="bg-gray-50 rounded-2xl px-3 py-2" style="background: rgba(139, 92, 246, 0.1);">
                                    <div class="font-semibold text-xs mb-1">${data.comment.nick}</div>
                                    <div class="text-sm comment-content-${data.comment.id}">${data.comment.content}</div>
                                </div>
                                <div class="flex gap-2 mt-1 ml-3">
                                    <button onclick="toggleReplyForm(${data.comment.id})" class="text-xs text-gray-500">답글</button>
                                </div>
                            </div>
                        </div>
                        <!-- 답글 입력창 -->
                        <div id="reply-form-${data.comment.id}" class="hidden mt-2 ml-11">
                            <div class="flex gap-2 items-center">
                                <img src="<?php echo $comment_profile_photo; ?>" class="w-7 h-7 rounded-full flex-shrink-0" alt="프로필">
                                <div class="flex-1 flex gap-2 bg-gray-100 rounded-full px-3 py-2 items-center">
                                    <input
                                        type="text"
                                        class="reply-input flex-1 bg-transparent border-none outline-none text-sm"
                                        placeholder="답글 입력..."
                                        data-parent-id="${data.comment.id}"
                                        onkeypress="if(event.key==='Enter'){submitReply(${data.comment.id})}">
                                    <button onclick="submitReply(${data.comment.id})" class="bg-transparent border-none cursor-pointer p-1">
                                        <i class="fa-solid fa-paper-plane text-purple-600 text-base"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // 부모 댓글 바로 아래에 삽입
                parentComment.insertAdjacentHTML('afterend', newReplyHTML);

                // 하이라이트 제거
                setTimeout(() => {
                    const newReply = document.getElementById('c_' + data.comment.id);
                    if (newReply) {
                        const bgElement = newReply.querySelector('.bg-gray-50');
                        if (bgElement) {
                            bgElement.style.background = '';
                        }
                    }
                }, 2000);
            }

            // 댓글 개수 업데이트
            const commentCountH3 = document.querySelector('#comment-list').previousElementSibling;
            if (commentCountH3) {
                const match = commentCountH3.textContent.match(/\d+/);
                const currentCount = match ? parseInt(match[0]) : 0;
                commentCountH3.textContent = '댓글 ' + (currentCount + 1) + '개';
            }

            // 입력창 초기화 및 닫기
            input.value = '';
            replyForm.classList.add('hidden');

        } else {
            alert(data.message || '답글 작성 중 오류가 발생했습니다.');
        }
    })
    .catch(error => {
        alert('오류 발생!\n\n' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    });
}

// 댓글 수정
function editComment(commentId, currentContent) {
    const contentDiv = document.querySelector('.comment-content-' + commentId);
    if (!contentDiv) return;

    // 이미 수정 중인 경우 취소
    if (contentDiv.querySelector('textarea')) {
        return;
    }

    // 원본 HTML 저장
    const originalHTML = contentDiv.innerHTML;

    // textarea로 변경
    contentDiv.innerHTML = `
        <div class="flex flex-col gap-2">
            <textarea class="w-full p-2 border rounded text-sm" rows="3" id="edit-textarea-${commentId}">${currentContent}</textarea>
            <div class="flex gap-2">
                <button onclick="saveComment(${commentId})" class="px-3 py-1 bg-purple-600 text-white text-xs rounded">저장</button>
                <button onclick="cancelEdit(${commentId}, \`${originalHTML.replace(/`/g, '\\`').replace(/\$/g, '\\$')}\`)" class="px-3 py-1 bg-gray-300 text-xs rounded">취소</button>
            </div>
        </div>
    `;
}

// 댓글 수정 취소
function cancelEdit(commentId, originalHTML) {
    const contentDiv = document.querySelector('.comment-content-' + commentId);
    if (contentDiv) {
        contentDiv.innerHTML = originalHTML;
    }
}

// 댓글 저장
function saveComment(commentId) {
    const textarea = document.getElementById('edit-textarea-' + commentId);
    if (!textarea) return;

    const newContent = textarea.value.trim();
    if (!newContent) {
        alert('내용을 입력해주세요.');
        return;
    }

    const formData = new FormData();
    formData.append('comment_id', commentId);
    formData.append('content', newContent);
    formData.append('bo_table', '<?php echo $bo_table; ?>');
    formData.append('wr_id', '<?php echo $wr_id; ?>');

    fetch('<?php echo G5_BBS_URL; ?>/comment_update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 수정된 내용으로 업데이트
            const contentDiv = document.querySelector('.comment-content-' + commentId);
            if (contentDiv) {
                contentDiv.innerHTML = newContent.replace(/\n/g, '<br>');
            }
            alert('댓글이 수정되었습니다.');
        } else {
            alert(data.message || '댓글 수정에 실패했습니다.');
        }
    })
    .catch(error => {
        alert('오류가 발생했습니다: ' + error.message);
    });
}

// 댓글 삭제
function deleteComment(commentId) {
    if (!confirm('정말로 이 댓글을 삭제하시겠습니까?')) {
        return;
    }

    const formData = new FormData();
    formData.append('comment_id', commentId);
    formData.append('bo_table', '<?php echo $bo_table; ?>');
    formData.append('wr_id', '<?php echo $wr_id; ?>');

    fetch('<?php echo G5_BBS_URL; ?>/comment_delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 댓글 요소 제거
            const commentElement = document.getElementById('c_' + commentId);
            if (commentElement) {
                commentElement.remove();
            }

            // 댓글 개수 업데이트
            const commentCountH3 = document.querySelector('#comment-list').previousElementSibling;
            if (commentCountH3) {
                const match = commentCountH3.textContent.match(/\d+/);
                const currentCount = match ? parseInt(match[0]) : 0;
                commentCountH3.textContent = '댓글 ' + Math.max(0, currentCount - 1) + '개';
            }

            alert('댓글이 삭제되었습니다.');
        } else {
            alert(data.message || '댓글 삭제에 실패했습니다.');
        }
    })
    .catch(error => {
        alert('오류가 발생했습니다: ' + error.message);
    });
}

function toggleGood() {
    <?php if (!$is_member) { ?>
    alert('로그인이 필요합니다.');
    location.href = '<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>';
    return;
    <?php } ?>

    console.log('toggleGood 함수 실행됨');
    console.log('요청 URL:', '<?php echo G5_BBS_URL; ?>/ajax.good.php');
    console.log('요청 데이터:', 'bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>');

    fetch('<?php echo G5_BBS_URL; ?>/ajax.good.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.result) {
            document.getElementById('heartIcon').classList.toggle('fa-regular');
            document.getElementById('heartIcon').classList.toggle('fa-solid');
            document.getElementById('goodCount').textContent = '좋아요 ' + data.count + '개';
        }
    });
}

// 전역 변수: 마지막 댓글 ID
window.lastCommentId = <?php
    $max_comment_id = 0;
    $comment_sql = "SELECT MAX(wr_id) as max_id FROM {$write_table}
                    WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1";
    $comment_result = sql_fetch($comment_sql);
    if ($comment_result) {
        $max_comment_id = $comment_result['max_id'] ? $comment_result['max_id'] : 0;
    }
    echo $max_comment_id;
?>;

// AJAX 댓글 제출
(function() {
    const form = document.getElementById('commentForm');
    const input = document.getElementById('commentInput');
    const commentList = document.getElementById('comment-list');

    if (!form || !input) return;

    const tokenInput = form.querySelector('input[name="token"]');

    const handleSubmit = function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        e.stopPropagation();

        if (!input.value.trim()) {
            input.focus();
            return false;
        }

        if (!tokenInput || !tokenInput.value) {
            alert('토큰 오류가 발생했습니다. 페이지를 새로고침해주세요.');
            return false;
        }

        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalContent = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        fetch('<?php echo G5_BBS_URL; ?>/comment_write_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.new_token && tokenInput) {
                    tokenInput.value = data.new_token;
                }

                if (data.comment.id) {
                    window.lastCommentId = Math.max(window.lastCommentId, data.comment.id);
                }

                const newCommentHTML = `
                    <div id="c_${data.comment.id}" class="mb-3" data-depth="0" style="animation: slideIn 0.3s ease-out;">
                        <div class="flex gap-3">
                            <img src="${data.comment.photo}" class="w-8 h-8 rounded-full flex-shrink-0">
                            <div class="flex-1">
                                <div class="bg-gray-50 rounded-2xl px-3 py-2" style="background: rgba(139, 92, 246, 0.1);">
                                    <div class="font-semibold text-xs mb-1">${data.comment.nick}</div>
                                    <div class="text-sm comment-content-${data.comment.id}">${data.comment.content}</div>
                                </div>
                                <?php if ($is_member) { ?>
                                <div class="flex gap-2 mt-1 ml-3">
                                    <button onclick="toggleReplyForm(${data.comment.id})" class="text-xs text-gray-500">답글</button>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php if ($is_member) { ?>
                        <div id="reply-form-${data.comment.id}" class="hidden mt-2 ml-11">
                            <div class="flex gap-2 items-center">
                                <img src="<?php echo $comment_profile_photo; ?>" class="w-7 h-7 rounded-full flex-shrink-0" alt="프로필">
                                <div class="flex-1 flex gap-2 bg-gray-100 rounded-full px-3 py-2 items-center">
                                    <input
                                        type="text"
                                        class="reply-input flex-1 bg-transparent border-none outline-none text-sm"
                                        placeholder="답글 입력..."
                                        data-parent-id="${data.comment.id}"
                                        onkeypress="if(event.key==='Enter'){submitReply(${data.comment.id})}">
                                    <button onclick="submitReply(${data.comment.id})" class="bg-transparent border-none cursor-pointer p-1">
                                        <i class="fa-solid fa-paper-plane text-purple-600 text-base"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                `;

                if (commentList) {
                    const emptyMessage = commentList.querySelector('.text-center.text-gray-500');
                    if (emptyMessage) emptyMessage.remove();

                    commentList.insertAdjacentHTML('beforeend', newCommentHTML);

                    const newComment = document.getElementById('c_' + data.comment.id);
                    if (newComment) {
                        newComment.scrollIntoView({ behavior: 'smooth', block: 'center' });

                        setTimeout(() => {
                            const bgElement = newComment.querySelector('.bg-gray-50');
                            if (bgElement) {
                                bgElement.style.background = '';
                            }
                        }, 2000);
                    }
                }

                const commentCountH3 = commentList.previousElementSibling;
                if (commentCountH3) {
                    const match = commentCountH3.textContent.match(/\d+/);
                    const currentCount = match ? parseInt(match[0]) : 0;
                    commentCountH3.textContent = '댓글 ' + (currentCount + 1) + '개';
                }

                input.value = '';

            } else {
                alert(data.message || '댓글 작성 중 오류가 발생했습니다.');
            }
        })
        .catch(error => {
            alert('오류 발생!\n\n' + error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalContent;
            input.focus();
        });

        return false;
    };

    form.addEventListener('submit', handleSubmit, true);
})();

// 실시간 댓글 polling
(function() {
    const commentList = document.getElementById('comment-list');
    if (!commentList) return;

    setInterval(function() {
        fetch('<?php echo G5_BBS_URL; ?>/comment_check.php?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&last_id=' + window.lastCommentId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.has_new) {
                    data.comments.forEach(comment => {
                        // 부모 댓글의 깊이 확인
                        let depth = 0;
                        if (comment.is_reply && comment.parent_comment_id) {
                            const parentComment = document.getElementById('c_' + comment.parent_comment_id);
                            if (parentComment) {
                                depth = parseInt(parentComment.dataset.depth || 0) + 1;
                            } else {
                                depth = 1;
                            }
                        }
                        const indentLevel = Math.min(depth, 4);
                        const indentClass = depth > 0 ? 'ml-' + (indentLevel * 8) : '';

                        // 모든 댓글에 답글 버튼과 폼 추가
                        let replyButton = '';
                        let replyForm = '';
                        <?php if ($is_member) { ?>
                        replyButton = `<div class="flex gap-2 mt-1 ml-3"><button onclick="toggleReplyForm(${comment.wr_id})" class="text-xs text-gray-500">답글</button></div>`;
                        replyForm = `
                            <div id="reply-form-${comment.wr_id}" class="hidden mt-2 ml-11">
                                <div class="flex gap-2 items-center">
                                    <img src="<?php echo $comment_profile_photo; ?>" class="w-7 h-7 rounded-full flex-shrink-0" alt="프로필">
                                    <div class="flex-1 flex gap-2 bg-gray-100 rounded-full px-3 py-2 items-center">
                                        <input
                                            type="text"
                                            class="reply-input flex-1 bg-transparent border-none outline-none text-sm"
                                            placeholder="답글 입력..."
                                            data-parent-id="${comment.wr_id}"
                                            onkeypress="if(event.key==='Enter'){submitReply(${comment.wr_id})}">
                                        <button onclick="submitReply(${comment.wr_id})" class="bg-transparent border-none cursor-pointer p-1">
                                            <i class="fa-solid fa-paper-plane text-purple-600 text-base"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        <?php } ?>

                        const newCommentHTML = `
                            <div id="c_${comment.wr_id}" class="mb-3 ${indentClass}" data-depth="${depth}" style="animation: slideIn 0.3s ease-out;">
                                <div class="flex gap-3">
                                    <img src="${comment.photo}" class="w-8 h-8 rounded-full flex-shrink-0">
                                    <div class="flex-1">
                                        <div class="bg-gray-50 rounded-2xl px-3 py-2" style="background: rgba(139, 92, 246, 0.1);">
                                            <div class="font-semibold text-xs mb-1">${comment.name}</div>
                                            <div class="text-sm comment-content-${comment.wr_id}">${comment.content}</div>
                                        </div>
                                        ${replyButton}
                                    </div>
                                </div>
                                ${replyForm}
                            </div>
                        `;

                        const emptyMessage = commentList.querySelector('.text-center.text-gray-500');
                        if (emptyMessage) emptyMessage.remove();

                        // 대댓글인 경우 부모 댓글 바로 아래에 삽입
                        if (comment.is_reply && comment.parent_comment_id) {
                            const parentComment = document.getElementById('c_' + comment.parent_comment_id);
                            if (parentComment) {
                                parentComment.insertAdjacentHTML('afterend', newCommentHTML);
                            } else {
                                // 부모 댓글을 찾을 수 없으면 맨 아래에 추가
                                commentList.insertAdjacentHTML('beforeend', newCommentHTML);
                            }
                        } else {
                            // 일반 댓글은 맨 아래에 추가
                            commentList.insertAdjacentHTML('beforeend', newCommentHTML);
                        }

                        window.lastCommentId = Math.max(window.lastCommentId, comment.wr_id);

                        const newComment = document.getElementById('c_' + comment.wr_id);
                        setTimeout(() => {
                            if (newComment && newComment.querySelector('.flex-1 > div')) {
                                newComment.querySelector('.flex-1 > div').style.background = '';
                            }
                        }, 2000);
                    });

                    const commentCountH3 = commentList.previousElementSibling;
                    if (commentCountH3) {
                        const match = commentCountH3.textContent.match(/\d+/);
                        const currentCount = match ? parseInt(match[0]) : 0;
                        commentCountH3.textContent = '댓글 ' + (currentCount + data.count) + '개';
                    }
                }
            })
            .catch(error => {
                console.error('댓글 체크 오류:', error);
            });
    }, 3000);
})();
</script>

<!-- 댓글 위치로 스크롤 -->
<script src="<?php echo G5_BBS_URL; ?>/scroll_to_comment.js"></script>

<!-- 알림 위젯 -->
<?php include_once(G5_BBS_PATH.'/notification_widget.php'); ?>

<!-- 이미지 뷰어 모달 -->
<div id="imageViewerModal" class="fixed inset-0 z-[99999] hidden" style="background: rgba(0,0,0,0.95);">
    <button onclick="closeImageViewer()" class="absolute top-4 right-4 text-white text-3xl z-10 p-2">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <div class="w-full h-full flex items-center justify-center p-4" onclick="closeImageViewer()">
        <img id="viewerImage" src="" alt="원본 이미지"
             class="max-w-full max-h-full object-contain"
             style="cursor: zoom-out;"
             onclick="event.stopPropagation();">
    </div>
</div>

<script>
// 이미지 뷰어 열기
function openImageViewer(imageUrl) {
    const modal = document.getElementById('imageViewerModal');
    const img = document.getElementById('viewerImage');

    img.src = imageUrl;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// 이미지 뷰어 닫기
function closeImageViewer() {
    const modal = document.getElementById('imageViewerModal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

// ESC 키로 닫기
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageViewer();
    }
});
</script>

</body>
</html>
