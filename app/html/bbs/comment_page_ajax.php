<?php
include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

$bo_table = isset($_GET['bo_table']) ? clean_xss_tags($_GET['bo_table']) : '';
$wr_id = isset($_GET['wr_id']) ? (int)$_GET['wr_id'] : 0;
$comment_page = isset($_GET['comment_page']) ? (int)$_GET['comment_page'] : 1;

if (!$bo_table || !$wr_id) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

// 게시판 정보 가져오기
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");
if (!$board) {
    echo json_encode(['success' => false, 'message' => '존재하지 않는 게시판입니다.']);
    exit;
}

// 글 테이블
$write_table = $g5['write_prefix'] . $bo_table;

// 글 정보 가져오기
$write = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$wr_id}'");
if (!$write) {
    echo json_encode(['success' => false, 'message' => '존재하지 않는 게시글입니다.']);
    exit;
}

// 시간 표시 함수
function get_time_ago_ajax($datetime) {
    $time_diff = time() - strtotime($datetime);

    if ($time_diff < 60) {
        return '방금 전';
    } elseif ($time_diff < 3600) {
        return floor($time_diff / 60) . '분 전';
    } elseif ($time_diff < 86400) {
        return floor($time_diff / 3600) . '시간 전';
    } elseif ($time_diff < 2592000) {
        return floor($time_diff / 86400) . '일 전';
    } else {
        return date('Y-m-d', strtotime($datetime));
    }
}

// 댓글 내용에서 유튜브 URL을 iframe으로 변환하는 함수
function process_comment_content_ajax($content) {
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

// 페이지네이션 설정 (루트 댓글 기준)
$root_comments_per_page = 30;

// 루트 댓글 수 조회 (wr_comment_parent = 0인 것만)
$root_comment_count = sql_fetch("SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1 AND wr_comment_parent = 0");
$total_root_comments = $root_comment_count['cnt'] ? (int)$root_comment_count['cnt'] : 0;
$total_comment_pages = max(1, ceil($total_root_comments / $root_comments_per_page));

// 전체 댓글 수 (루트 + 대댓글 모두)
$actual_comment_count = sql_fetch("SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1");
$total_comments_count = $actual_comment_count['cnt'] ? (int)$actual_comment_count['cnt'] : 0;

if ($comment_page < 1) $comment_page = 1;
if ($comment_page > $total_comment_pages) $comment_page = $total_comment_pages;

$comment_offset = ($comment_page - 1) * $root_comments_per_page;

// 현재 로그인한 사용자 프로필 사진 - 캐시 버스팅 적용
$comment_profile_photo = $is_member ? get_profile_image_url($member['mb_id']) : 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';

// 1단계: 현재 페이지의 루트 댓글 ID들 가져오기
$root_ids = array();
$root_result = sql_query("SELECT wr_id FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1 AND wr_comment_parent = 0 ORDER BY wr_id ASC LIMIT {$comment_offset}, {$root_comments_per_page}");
while ($r = sql_fetch_array($root_result)) {
    $root_ids[] = $r['wr_id'];
}

// 댓글을 배열로 변환하고 계층 구조 생성
$all_comments = array();
$children_map = array();

if (count($root_ids) > 0) {
    $root_ids_str = implode(',', $root_ids);

    // 2단계: 루트 댓글들과 그 모든 대댓글 가져오기
    // 루트 댓글
    $comment_result = sql_query("SELECT * FROM {$write_table} WHERE wr_id IN ({$root_ids_str}) ORDER BY wr_id ASC");
    while ($c = sql_fetch_array($comment_result)) {
        $all_comments[$c['wr_id']] = $c;
        if (!isset($children_map[0])) {
            $children_map[0] = array();
        }
        $children_map[0][] = $c;
    }

    // 대댓글들 (재귀적으로 모든 레벨) - wr_comment_parent가 루트 댓글이거나 다른 대댓글인 것
    // 모든 대댓글을 가져와서 부모가 현재 페이지에 속하는지 확인
    $all_replies_result = sql_query("SELECT * FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1 AND wr_comment_parent != 0 ORDER BY wr_id ASC");

    $pending_replies = array();
    while ($c = sql_fetch_array($all_replies_result)) {
        $pending_replies[$c['wr_id']] = $c;
    }

    // 반복적으로 부모가 있는 대댓글들을 추가
    $added = true;
    while ($added) {
        $added = false;
        foreach ($pending_replies as $reply_id => $reply) {
            $parent_id = (int)$reply['wr_comment_parent'];
            // 부모가 이미 추가되어 있으면 이 대댓글도 추가
            if (isset($all_comments[$parent_id])) {
                $all_comments[$reply_id] = $reply;
                if (!isset($children_map[$parent_id])) {
                    $children_map[$parent_id] = array();
                }
                $children_map[$parent_id][] = $reply;
                unset($pending_replies[$reply_id]);
                $added = true;
            }
        }
    }
}

// 댓글 HTML 생성
ob_start();

// 재귀적으로 댓글 렌더링하는 함수
function render_comment_ajax($comment, $depth, $children_map, $g5, $is_member, $is_admin, $member, $comment_profile_photo) {
    $max_indent = 4;
    $indent_level = min($depth, $max_indent);
    $indent_class = $depth > 0 ? 'ml-' . ($indent_level * 8) : '';

    $c = $comment;
    $c_nick = $c['wr_name'] ? $c['wr_name'] : '알 수 없음';
    $c_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';

    if ($c['mb_id']) {
        $c_mb = sql_fetch("SELECT mb_name FROM {$g5['member_table']} WHERE mb_id = '{$c['mb_id']}'");
        if ($c_mb) {
            $c_nick = $c_mb['mb_name'];
        }
        $c_photo = get_profile_image_url($c['mb_id']);
    }
    ?>
    <div id="c_<?php echo $c['wr_id']; ?>" class="mb-3 <?php echo $indent_class; ?>" data-depth="<?php echo $depth; ?>">
        <div class="flex gap-3">
            <img src="<?php echo $c_photo; ?>" class="w-8 h-8 rounded-full flex-shrink-0">
            <div class="flex-1">
                <div class="bg-gray-50 rounded-2xl px-3 py-2">
                    <div class="flex items-center justify-between mb-1">
                        <div class="font-semibold text-xs"><?php echo $c_nick; ?></div>
                        <div class="text-xs text-gray-400"><?php echo get_time_ago_ajax($c['wr_datetime']); ?></div>
                    </div>
                    <div class="text-sm comment-content-<?php echo $c['wr_id']; ?>"><?php echo process_comment_content_ajax(nl2br(get_text($c['wr_content']))); ?></div>
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
            render_comment_ajax($child, $depth + 1, $children_map, $g5, $is_member, $is_admin, $member, $comment_profile_photo);
        }
    }
}

// 최상위 댓글들 (wr_comment_parent = 0)
$root_comments = isset($children_map[0]) ? $children_map[0] : array();

if (count($root_comments) > 0) {
    foreach ($root_comments as $comment) {
        render_comment_ajax($comment, 0, $children_map, $g5, $is_member, $is_admin, $member, $comment_profile_photo);
    }
} else {
    echo '<div class="text-center text-gray-500 py-4">첫 댓글을 남겨보세요!</div>';
}

$comments_html = ob_get_clean();

// 페이지네이션 HTML 생성
ob_start();

if ($total_comment_pages > 1) {
    $start_page = max(1, $comment_page - 2);
    $end_page = min($total_comment_pages, $start_page + 4);
    if ($end_page - $start_page < 4) {
        $start_page = max(1, $end_page - 4);
    }
    ?>
    <div class="flex items-center justify-center gap-1 mt-4 flex-wrap">
        <?php if ($comment_page > 1) { ?>
        <button onclick="loadCommentPage(1)" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">«</button>
        <button onclick="loadCommentPage(<?php echo $comment_page - 1; ?>)" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">‹ 이전</button>
        <?php } ?>

        <?php
        for ($p = $start_page; $p <= $end_page; $p++) {
            $active_class = ($p == $comment_page) ? 'bg-purple-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700';
        ?>
        <button onclick="loadCommentPage(<?php echo $p; ?>)" class="px-3 py-1 text-xs <?php echo $active_class; ?> rounded"><?php echo $p; ?></button>
        <?php } ?>

        <?php if ($comment_page < $total_comment_pages) { ?>
        <button onclick="loadCommentPage(<?php echo $comment_page + 1; ?>)" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">다음 ›</button>
        <button onclick="loadCommentPage(<?php echo $total_comment_pages; ?>)" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">»</button>
        <?php } ?>
    </div>
    <?php
}

$pagination_html = ob_get_clean();

// 마지막 댓글 ID 가져오기
$max_comment_id = 0;
$comment_sql = "SELECT MAX(wr_id) as max_id FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1";
$max_result = sql_fetch($comment_sql);
if ($max_result) {
    $max_comment_id = $max_result['max_id'] ? $max_result['max_id'] : 0;
}

echo json_encode([
    'success' => true,
    'comments_html' => $comments_html,
    'pagination_html' => $pagination_html,
    'current_page' => $comment_page,
    'total_pages' => $total_comment_pages,
    'total_comments' => $total_comments_count,
    'last_comment_id' => $max_comment_id
]);
