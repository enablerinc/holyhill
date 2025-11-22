<?php
include_once('./_common.php');

header('Content-Type: application/json');

if (!$is_member) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$bo_table = isset($_POST['bo_table']) ? preg_replace('/[^a-z0-9_]/i', '', $_POST['bo_table']) : '';
$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;

if (!$comment_id || !$bo_table || !$wr_id) {
    echo json_encode(['success' => false, 'message' => '잘못된 접근입니다.']);
    exit;
}

$write_table = $g5['write_prefix'] . $bo_table;
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    echo json_encode(['success' => false, 'message' => '게시판을 찾을 수 없습니다.']);
    exit;
}

// 댓글 정보 가져오기
$comment = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$comment_id}' AND wr_is_comment = 1");

if (!$comment) {
    echo json_encode(['success' => false, 'message' => '댓글을 찾을 수 없습니다.']);
    exit;
}

// 권한 체크: 작성자 또는 관리자만 삭제 가능
if ($comment['mb_id'] !== $member['mb_id'] && !$is_admin) {
    echo json_encode(['success' => false, 'message' => '삭제 권한이 없습니다.']);
    exit;
}

// 댓글 삭제
$result = sql_query("DELETE FROM {$write_table} WHERE wr_id = '{$comment_id}'");

if ($result) {
    // 원글의 댓글 수 감소
    sql_query("UPDATE {$write_table} SET wr_comment = wr_comment - 1 WHERE wr_id = '{$wr_id}'");

    // 포인트 회수
    if ($board['bo_comment_point']) {
        delete_point($comment['mb_id'], $bo_table, $comment_id, '댓글');
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => '댓글 삭제에 실패했습니다.']);
}
?>
