<?php
include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

$bo_table = isset($_GET['bo_table']) ? clean_xss_tags($_GET['bo_table']) : '';
$wr_id = isset($_GET['wr_id']) ? (int)$_GET['wr_id'] : 0;
$last_comment_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if (!$bo_table || !$wr_id) {
    echo json_encode(['success' => false, 'message' => '필수 파라미터가 없습니다.']);
    exit;
}

// 게시판 확인
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");
if (!$board) {
    echo json_encode(['success' => false, 'message' => '존재하지 않는 게시판입니다.']);
    exit;
}

// 마지막 댓글 ID 이후의 새로운 댓글들 가져오기
$sql = "SELECT wr_id, wr_content, wr_name, wr_datetime, mb_id, wr_comment_reply
        FROM {$g5['write_prefix']}{$bo_table}
        WHERE wr_parent = '{$wr_id}'
        AND wr_is_comment = 1
        AND wr_id > '{$last_comment_id}'
        ORDER BY wr_comment_reply ASC";

$result = sql_query($sql);
$new_comments = array();

while ($row = sql_fetch_array($result)) {
    // 작성자 프로필 사진 가져오기
    $c_photo = G5_THEME_URL.'/img/no-profile.svg';
    if ($row['mb_id']) {
        $c_photo_html = get_member_profile_img($row['mb_id']);
        if ($c_photo_html && preg_match('/src="([^"]+)"/', $c_photo_html, $matches)) {
            $c_photo = $matches[1];
        }
    }

    // 시간 포맷 (몇 분 전, 몇 시간 전 등)
    $datetime = strtotime($row['wr_datetime']);
    $diff = time() - $datetime;

    if ($diff < 60) {
        $time_str = '방금 전';
    } elseif ($diff < 3600) {
        $time_str = floor($diff / 60) . '분 전';
    } elseif ($diff < 86400) {
        $time_str = floor($diff / 3600) . '시간 전';
    } else {
        $time_str = date('Y-m-d H:i', $datetime);
    }

    // 대댓글 여부 확인
    $is_reply = strlen($row['wr_comment_reply']) > 10;

    $new_comments[] = array(
        'wr_id' => $row['wr_id'],
        'content' => $row['wr_content'],
        'name' => $row['wr_name'],
        'photo' => $c_photo,
        'datetime' => $time_str,
        'is_reply' => $is_reply
    );
}

echo json_encode([
    'success' => true,
    'has_new' => count($new_comments) > 0,
    'count' => count($new_comments),
    'comments' => $new_comments
], JSON_UNESCAPED_UNICODE);
?>
