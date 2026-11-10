<?php
include_once('./_common.php');

header('Content-Type: application/json');

if (!$is_member) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$bo_table = isset($_POST['bo_table']) ? preg_replace('/[^a-z0-9_]/i', '', $_POST['bo_table']) : '';
$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;

if (!$comment_id || !$content || !$bo_table || !$wr_id) {
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

// 권한 체크: 작성자 또는 관리자만 수정 가능
if ($comment['mb_id'] !== $member['mb_id'] && !$is_admin) {
    echo json_encode(['success' => false, 'message' => '수정 권한이 없습니다.']);
    exit;
}

// 댓글 수정
$result = sql_query("UPDATE {$write_table} SET
    wr_content = '" . addslashes($content) . "',
    wr_last = '" . G5_TIME_YMDHIS . "'
    WHERE wr_id = '{$comment_id}'");

if ($result) {
    // 수정으로 새로 언급된 사람에게만 멘션 알림
    $mention_post = sql_fetch("SELECT mb_id FROM {$write_table} WHERE wr_id = '{$wr_id}' AND wr_is_comment = 0");
    $mention_url = ($bo_table === 'diary' && $mention_post)
        ? G5_BBS_URL.'/gratitude_user.php?mb_id='.urlencode($mention_post['mb_id']).'&wr_id='.$wr_id
        : G5_BBS_URL.'/post.php?bo_table='.$bo_table.'&wr_id='.$wr_id.'#c_'.$comment_id;
    holyhill_mention_notify($content, isset($_POST['mention_ids']) ? $_POST['mention_ids'] : '', $member['mb_id'],
                            $bo_table, $wr_id, $comment_id, $mention_url,
                            array($mention_post ? $mention_post['mb_id'] : ''), $comment['wr_content']);

    echo json_encode([
        'success' => true,
        'content' => holyhill_mention_highlight(nl2br(htmlspecialchars($content)))
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '댓글 수정에 실패했습니다.']);
}
?>
