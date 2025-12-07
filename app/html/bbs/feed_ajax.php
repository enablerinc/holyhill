<?php
include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

$bo_table = isset($_GET['bo_table']) ? clean_xss_tags($_GET['bo_table']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';
$search_type = isset($_GET['search_type']) ? $_GET['search_type'] : 'subject';
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page_rows = isset($_GET['page_rows']) ? (int)$_GET['page_rows'] : 30;

if (!$bo_table) {
    echo json_encode(['success' => false, 'message' => '게시판이 지정되지 않았습니다.']);
    exit;
}

// 게시판 설정 확인
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");
if (!$board) {
    echo json_encode(['success' => false, 'message' => '존재하지 않는 게시판입니다.']);
    exit;
}

// 검색 조건
$search_condition = '';
if ($search_keyword) {
    $search_keyword_escaped = sql_real_escape_string($search_keyword);
    if ($search_type === 'name') {
        $search_condition = " AND wr_name LIKE '%{$search_keyword_escaped}%'";
    } else {
        $search_condition = " AND wr_subject LIKE '%{$search_keyword_escaped}%'";
    }
}

$write_table = $g5['write_prefix'] . $bo_table;

// 정렬 조건 (wr_datetime 기준 최신순)
$order_by = ($sort === 'popular') ? 'wr_good DESC, wr_datetime DESC' : 'wr_datetime DESC';

// 게시글 가져오기 (단순 쿼리 사용)
$offset = ($page - 1) * $page_rows;
$sql = "SELECT * FROM {$write_table} WHERE wr_is_comment = 0 {$search_condition} ORDER BY {$order_by} LIMIT {$offset}, {$page_rows}";

$result = sql_query($sql);
$items = array();

while ($row = sql_fetch_array($result)) {
    // 회원 정보 별도 조회
    $member = null;
    if ($row['mb_id']) {
        $member = sql_fetch("SELECT mb_nick, mb_photo FROM {$g5['member_table']} WHERE mb_id = '{$row['mb_id']}'");
    }

    // 댓글 수 별도 조회
    $comment_result = sql_fetch("SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_parent = '{$row['wr_id']}' AND wr_is_comment = 1");
    $row['comment_count'] = $comment_result['cnt'];
    $row['member_nick'] = $member ? $member['mb_nick'] : '';
    $row['member_photo'] = $member ? $member['mb_photo'] : '';
    $wr_id = $row['wr_id'];

    // 작성자 정보
    $writer_nick = $row['member_nick'] ? $row['member_nick'] : $row['wr_name'];
    $writer_id = $row['mb_id'];
    $writer_photo = '';
    if ($writer_id && $row['member_photo']) {
        $writer_photo = G5_DATA_URL.'/member/'.substr($writer_id, 0, 2).'/'.$row['member_photo'];
    }

    // 날짜 포맷
    $wr_datetime = $row['wr_datetime'];
    $date_diff = time() - strtotime($wr_datetime);
    if ($date_diff < 60) {
        $display_date = '방금 전';
    } elseif ($date_diff < 3600) {
        $display_date = floor($date_diff / 60) . '분 전';
    } elseif ($date_diff < 86400) {
        $display_date = floor($date_diff / 3600) . '시간 전';
    } elseif ($date_diff < 604800) {
        $display_date = floor($date_diff / 86400) . '일 전';
    } else {
        $display_date = date('Y.m.d', strtotime($wr_datetime));
    }

    // YouTube URL 추출
    $video_thumbnail = '';
    $video_id = '';
    $search_content = $row['wr_link1'] . ' ' . $row['wr_content'];

    $patterns = array(
        '/https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)(?:&[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i'
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $search_content, $matches)) {
            $video_id = $matches[1];
            break;
        }
    }

    if ($video_id) {
        $video_thumbnail = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
    }

    // 모든 이미지 가져오기
    $images = array();
    $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND bf_type BETWEEN 1 AND 3 ORDER BY bf_no");
    while ($img = sql_fetch_array($img_result)) {
        $images[] = G5_DATA_URL.'/file/'.$bo_table.'/'.$img['bf_file'];
    }

    // 텍스트 콘텐츠 추출
    $text_content = strip_tags($row['wr_content']);
    $text_content = preg_replace('/\[이미지\d+\]/', '', $text_content);
    $text_content = preg_replace('/https?:\/\/[^\s]+/', '', $text_content);
    $text_content = trim($text_content);

    $view_href = G5_BBS_URL.'/post.php?bo_table='.$bo_table.'&wr_id='.$wr_id;
    $good_count = isset($row['wr_good']) ? (int)$row['wr_good'] : 0;
    $comment_count = isset($row['comment_count']) ? (int)$row['comment_count'] : 0;

    $items[] = array(
        'wr_id' => $wr_id,
        'subject' => strip_tags($row['wr_subject']),
        'writer_nick' => $writer_nick,
        'writer_photo' => $writer_photo,
        'display_date' => $display_date,
        'video_id' => $video_id,
        'video_thumbnail' => $video_thumbnail,
        'images' => $images,
        'text_content' => $text_content ? cut_str($text_content, 150) : '',
        'view_href' => $view_href,
        'good_count' => number_format($good_count),
        'comment_count' => number_format($comment_count)
    );
}

echo json_encode([
    'success' => true,
    'items' => $items,
    'page' => $page,
    'count' => count($items)
], JSON_UNESCAPED_UNICODE);
?>
