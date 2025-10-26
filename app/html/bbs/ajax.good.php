<?php
include_once('./_common.php');

header('Content-Type: application/json');

if (!$is_member) {
    echo json_encode(['result' => 'error', 'message' => '로그인이 필요합니다.']);
    exit;
}

$bo_table = isset($_POST['bo_table']) ? preg_replace('/[^a-z0-9_]/i', '', $_POST['bo_table']) : '';
$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;

if (!$bo_table || !$wr_id) {
    echo json_encode(['result' => 'error', 'message' => '잘못된 요청입니다.']);
    exit;
}

$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");
if (!$board) {
    echo json_encode(['result' => 'error', 'message' => '존재하지 않는 게시판입니다.']);
    exit;
}

$write_table = $g5['write_prefix'] . $bo_table;

// 이미 추천했는지 확인
$sql = "SELECT * FROM {$g5['board_good_table']} 
        WHERE bo_table = '{$bo_table}' 
        AND wr_id = '{$wr_id}' 
        AND mb_id = '{$member['mb_id']}'";
$row = sql_fetch($sql);

if ($row) {
    // 추천 취소
    sql_query("DELETE FROM {$g5['board_good_table']} 
               WHERE bo_table = '{$bo_table}' 
               AND wr_id = '{$wr_id}' 
               AND mb_id = '{$member['mb_id']}'");
    
    sql_query("UPDATE {$write_table} SET wr_good = wr_good - 1 WHERE wr_id = '{$wr_id}'");
    
    $result = 'canceled';
} else {
    // 추천 추가
    sql_query("INSERT INTO {$g5['board_good_table']} 
               SET bo_table = '{$bo_table}',
                   wr_id = '{$wr_id}',
                   mb_id = '{$member['mb_id']}',
                   bg_flag = 'good',
                   bg_datetime = NOW()");
    
    sql_query("UPDATE {$write_table} SET wr_good = wr_good + 1 WHERE wr_id = '{$wr_id}'");
    
    // 포인트 지급
    $po_content = "{$board['bo_subject']} {$wr_id} 추천";
    insert_point($member['mb_id'], 1, $po_content, $bo_table, $wr_id, '추천');
    
    // 작성자에게 포인트 지급
    $write = sql_fetch("SELECT mb_id FROM {$write_table} WHERE wr_id = '{$wr_id}'");
    if ($write['mb_id'] && $write['mb_id'] != $member['mb_id']) {
        $po_content = "{$board['bo_subject']} {$wr_id} 추천 받음";
        insert_point($write['mb_id'], 3, $po_content, $bo_table, $wr_id, '추천받음');
    }
    
    $result = 'success';
}

// 현재 추천 수
$write = sql_fetch("SELECT wr_good FROM {$write_table} WHERE wr_id = '{$wr_id}'");

echo json_encode([
    'result' => $result,
    'count' => (int)$write['wr_good']
]);
?>
