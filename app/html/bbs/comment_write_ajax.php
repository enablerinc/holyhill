<?php
include_once('./_common.php');

header('Content-Type: application/json');

if (!$is_member) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$comment_token = trim(get_session('ss_comment_token'));
set_session('ss_comment_token', '');
if(empty($_POST['token']) || !$comment_token || $comment_token != $_POST['token']) {
    echo json_encode(['success' => false, 'message' => '올바른 방법으로 이용해 주십시오.']);
    exit;
}

$bo_table = preg_replace('/[^a-z0-9_]/i', '', $_POST['bo_table']);
$wr_id = (int)$_POST['wr_id'];
$wr_content = trim($_POST['wr_content']);

if (!$bo_table || !$wr_id || !$wr_content) {
    echo json_encode(['success' => false, 'message' => '잘못된 접근입니다.']);
    exit;
}

$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");
$write_table = $g5['write_prefix'] . $bo_table;
$write = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$wr_id}' AND wr_is_comment = 0");

if (!$write) {
    echo json_encode(['success' => false, 'message' => '게시글을 찾을 수 없습니다.']);
    exit;
}

$row = sql_fetch("SELECT MAX(CAST(wr_comment_reply AS UNSIGNED)) as max_reply 
                  FROM {$write_table} 
                  WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1");
$reply_num = $row['max_reply'] ? $row['max_reply'] + 1 : 1;
$wr_comment_reply = str_pad($reply_num, 10, '0', STR_PAD_LEFT);

sql_query("INSERT INTO {$write_table} SET 
    wr_num = '{$write['wr_num']}',
    wr_comment = 1,
    wr_comment_reply = '{$wr_comment_reply}',
    wr_is_comment = 1,
    wr_parent = '{$wr_id}',
    wr_content = '" . addslashes($wr_content) . "',
    wr_name = '{$member['mb_nick']}',
    mb_id = '{$member['mb_id']}',
    wr_datetime = '" . G5_TIME_YMDHIS . "',
    wr_ip = '{$_SERVER['REMOTE_ADDR']}'");

$comment_id = sql_insert_id();

sql_query("UPDATE {$write_table} SET wr_comment = wr_comment + 1 WHERE wr_id = '{$wr_id}'");

if ($board['bo_comment_point']) {
    insert_point($member['mb_id'], $board['bo_comment_point'], 
                 "{$board['bo_subject']} 댓글", $bo_table, $wr_id, '댓글');
}

// ✅ 새 토큰 생성
$new_token = get_random_token_string();
set_session('ss_comment_token', $new_token);

// 방금 작성한 댓글 정보 반환
$c_mb = get_member($member['mb_id']);
$c_photo = $c_mb['mb_photo'] ? G5_DATA_URL.'/member/'.$c_mb['mb_photo'] : G5_THEME_URL.'/img/no-profile.svg';

echo json_encode([
    'success' => true,
    'new_token' => $new_token,  // ✅ 새 토큰 전달
    'comment' => [
        'id' => $comment_id,
        'content' => htmlspecialchars($wr_content),
        'nick' => $member['mb_nick'],
        'photo' => $c_photo,
        'datetime' => '방금 전'
    ]
]);
?>
