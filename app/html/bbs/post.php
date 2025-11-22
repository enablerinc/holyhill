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
    $mb_result = sql_query("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$write['mb_id']}'");
    if ($mb_result && $row = sql_fetch_array($mb_result)) {
        $mb_nick = $row['mb_nick'];
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
            return '<div class="my-4"><img src="'.$image_url.'" class="w-full rounded-lg" alt="이미지'.($index+1).'"></div>';
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
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
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
            <button onclick="location.href='<?php echo G5_BBS_URL; ?>/index.php'"><i class="fa-solid fa-arrow-left text-xl"></i></button>
            <?php if ($delete_href) { ?>
            <div class="relative">
                <button onclick="toggleMenu()" id="menuBtn"><i class="fa-solid fa-ellipsis-vertical text-xl"></i></button>
                <div id="menuDropdown" class="hidden absolute right-0 top-8 bg-white border rounded-lg shadow-lg py-2 w-32 z-50">
                    <a href="#" onclick="confirmDelete(event)" class="block px-4 py-2 hover:bg-gray-100 text-sm text-red-600">
                        <i class="fa-solid fa-trash mr-2"></i>삭제
                    </a>
                </div>
            </div>
            <?php } else { ?>
            <div class="w-6"></div>
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
                <div class="text-sm text-gray-500"><?php echo date('Y-m-d', strtotime($write['wr_datetime'])); ?></div>
            </div>

            <!-- 구분선 -->
            <hr class="border-gray-200">

            <!-- 내용 -->
            <div class="p-4">
                <!-- 미디어 갤러리 (본문에 삽입되지 않은 이미지와 동영상 표시) -->
                <?php if (count($gallery_media) > 0) { ?>
                <div class="flex overflow-x-auto gap-3 mb-4 -mx-4 px-4 scrollbar-hide" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;">
                    <?php foreach ($gallery_media as $media) {
                        $media_url = G5_DATA_URL.'/file/'.$bo_table.'/'.$media['file'];
                    ?>
                    <div class="flex-shrink-0" style="scroll-snap-align: start; width: 85vw; max-width: 500px;">
                        <?php if ($media['type'] === 'video') { ?>
                            <div class="relative w-full h-80 bg-black rounded-lg overflow-hidden">
                                <video class="w-full h-full object-contain" controls controlsList="nodownload">
                                    <source src="<?php echo $media_url; ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        <?php } else { ?>
                            <img src="<?php echo $media_url; ?>"
                                 class="w-full h-80 object-cover rounded-lg"
                                 alt="갤러리 이미지">
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

            <!-- 댓글 -->
            <div class="p-4">
                <h3 class="font-semibold mb-4">댓글 <?php echo $write['wr_comment']; ?>개</h3>
                <div id="comment-list">
                <?php
                // 모든 댓글 가져오기 (wr_comment_parent 필드로 명확한 부모-자식 관계 확인)
                $comment_result = sql_query("SELECT * FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1 ORDER BY wr_id ASC LIMIT 200");

                // 댓글을 배열로 변환하고 계층 구조 생성
                $all_comments = array();
                $parent_comments = array();
                $reply_comments = array();

                while ($c = sql_fetch_array($comment_result)) {
                    $all_comments[$c['wr_id']] = $c;

                    // wr_comment_parent로 대댓글 여부 확인 (0이면 일반 댓글, 0이 아니면 대댓글)
                    if ($c['wr_comment_parent'] > 0) {
                        // 대댓글: wr_comment_parent에 저장된 부모 댓글 ID로 그룹화
                        if (!isset($reply_comments[$c['wr_comment_parent']])) {
                            $reply_comments[$c['wr_comment_parent']] = array();
                        }
                        $reply_comments[$c['wr_comment_parent']][] = $c;
                    } else {
                        // 일반 댓글
                        $parent_comments[] = $c;
                    }
                }

                if (count($parent_comments) > 0 || count($reply_comments) > 0) {
                    // 부모 댓글을 먼저 출력하고, 각 부모 댓글 아래에 대댓글 출력
                    foreach ($parent_comments as $c) {
                        $c_nick = $c['wr_name'] ? $c['wr_name'] : '알 수 없음';
                        $c_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';

                        if ($c['mb_id']) {
                            $c_mb = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$c['mb_id']}'");
                            if ($c_mb) {
                                $c_nick = $c_mb['mb_nick'];
                            }

                            $c_profile_path = G5_DATA_PATH.'/member_image/'.substr($c['mb_id'], 0, 2).'/'.$c['mb_id'].'.gif';
                            if (file_exists($c_profile_path)) {
                                $c_photo = G5_DATA_URL.'/member_image/'.substr($c['mb_id'], 0, 2).'/'.$c['mb_id'].'.gif';
                            }
                        }
                        ?>
                        <div id="c_<?php echo $c['wr_id']; ?>" class="mb-3">
                            <div class="flex gap-3">
                                <img src="<?php echo $c_photo; ?>" class="w-8 h-8 rounded-full flex-shrink-0">
                                <div class="flex-1">
                                    <div class="bg-gray-50 rounded-2xl px-3 py-2">
                                        <div class="font-semibold text-xs mb-1"><?php echo $c_nick; ?></div>
                                        <div class="text-sm"><?php echo nl2br(get_text($c['wr_content'])); ?></div>
                                    </div>
                                    <?php if ($is_member) { ?>
                                    <button onclick="toggleReplyForm(<?php echo $c['wr_id']; ?>)" class="text-xs text-gray-500 mt-1 ml-3">답글</button>
                                    <?php } ?>
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

                        // 이 부모 댓글의 대댓글들 출력
                        if (isset($reply_comments[$c['wr_id']])) {
                            foreach ($reply_comments[$c['wr_id']] as $r) {
                                $r_nick = $r['wr_name'] ? $r['wr_name'] : '알 수 없음';
                                $r_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';

                                if ($r['mb_id']) {
                                    $r_mb = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$r['mb_id']}'");
                                    if ($r_mb) {
                                        $r_nick = $r_mb['mb_nick'];
                                    }

                                    $r_profile_path = G5_DATA_PATH.'/member_image/'.substr($r['mb_id'], 0, 2).'/'.$r['mb_id'].'.gif';
                                    if (file_exists($r_profile_path)) {
                                        $r_photo = G5_DATA_URL.'/member_image/'.substr($r['mb_id'], 0, 2).'/'.$r['mb_id'].'.gif';
                                    }
                                }
                                ?>
                                <div id="c_<?php echo $r['wr_id']; ?>" class="mb-3 ml-11">
                                    <div class="flex gap-3">
                                        <img src="<?php echo $r_photo; ?>" class="w-8 h-8 rounded-full flex-shrink-0">
                                        <div class="flex-1">
                                            <div class="bg-gray-50 rounded-2xl px-3 py-2">
                                                <div class="font-semibold text-xs mb-1"><?php echo $r_nick; ?></div>
                                                <div class="text-sm"><?php echo nl2br(get_text($r['wr_content'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                    }
                } else {
                    echo '<div class="text-center text-gray-500 py-4">첫 댓글을 남겨보세요!</div>';
                }
                ?>
                </div>
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
                const newReplyHTML = `
                    <div id="c_${data.comment.id}" class="mb-3 ml-11" style="animation: slideIn 0.3s ease-out;">
                        <div class="flex gap-3">
                            <img src="${data.comment.photo}" class="w-8 h-8 rounded-full flex-shrink-0">
                            <div class="flex-1">
                                <div class="bg-gray-50 rounded-2xl px-3 py-2" style="background: rgba(139, 92, 246, 0.1);">
                                    <div class="font-semibold text-xs mb-1">${data.comment.nick}</div>
                                    <div class="text-sm">${data.comment.content}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // 부모 댓글의 다음 형제 요소들 중에서 마지막 대댓글 찾기
                let insertAfter = parentComment;
                let nextElement = parentComment.nextElementSibling;
                while (nextElement && nextElement.classList.contains('ml-11')) {
                    insertAfter = nextElement;
                    nextElement = nextElement.nextElementSibling;
                }

                insertAfter.insertAdjacentHTML('afterend', newReplyHTML);

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
            document.getElementById('goodCount').textContent = '아멘 ' + data.count + '개';
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
                    <div id="c_${data.comment.id}" class="mb-3" style="animation: slideIn 0.3s ease-out;">
                        <div class="flex gap-3">
                            <img src="${data.comment.photo}" class="w-8 h-8 rounded-full flex-shrink-0">
                            <div class="flex-1">
                                <div class="bg-gray-50 rounded-2xl px-3 py-2" style="background: rgba(139, 92, 246, 0.1);">
                                    <div class="font-semibold text-xs mb-1">${data.comment.nick}</div>
                                    <div class="text-sm">${data.comment.content}</div>
                                </div>
                                <?php if ($is_member) { ?>
                                <button onclick="toggleReplyForm(${data.comment.id})" class="text-xs text-gray-500 mt-1 ml-3">답글</button>
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
                        const indentClass = comment.is_reply ? 'ml-11' : '';

                        // 일반 댓글인 경우 답글 버튼 포함
                        let replyButton = '';
                        let replyForm = '';
                        <?php if ($is_member) { ?>
                        if (!comment.is_reply) {
                            replyButton = `<button onclick="toggleReplyForm(${comment.wr_id})" class="text-xs text-gray-500 mt-1 ml-3">답글</button>`;
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
                        }
                        <?php } ?>

                        const newCommentHTML = `
                            <div id="c_${comment.wr_id}" class="mb-3 ${indentClass}" style="animation: slideIn 0.3s ease-out;">
                                <div class="flex gap-3">
                                    <img src="${comment.photo}" class="w-8 h-8 rounded-full flex-shrink-0">
                                    <div class="flex-1">
                                        <div class="bg-gray-50 rounded-2xl px-3 py-2" style="background: rgba(139, 92, 246, 0.1);">
                                            <div class="font-semibold text-xs mb-1">${comment.name}</div>
                                            <div class="text-sm">${comment.content}</div>
                                        </div>
                                        ${replyButton}
                                    </div>
                                </div>
                                ${replyForm}
                            </div>
                        `;

                        const emptyMessage = commentList.querySelector('.text-center.text-gray-500');
                        if (emptyMessage) emptyMessage.remove();

                        // 대댓글인 경우 부모 댓글 아래에 삽입
                        if (comment.is_reply && comment.parent_comment_id) {
                            const parentComment = document.getElementById('c_' + comment.parent_comment_id);
                            if (parentComment) {
                                // 부모 댓글의 다음 형제 요소들 중에서 마지막 대댓글 찾기
                                let insertAfter = parentComment;
                                let nextElement = parentComment.nextElementSibling;
                                while (nextElement && nextElement.classList.contains('ml-11')) {
                                    insertAfter = nextElement;
                                    nextElement = nextElement.nextElementSibling;
                                }
                                insertAfter.insertAdjacentHTML('afterend', newCommentHTML);
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

</body>
</html>
