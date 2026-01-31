<?php
include_once('./_common.php');
include_once(G5_BBS_PATH.'/notification.lib.php');

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

    // 포인트 지급 (추천한 사람에게 10포인트)
    $po_content = "{$board['bo_subject']} {$wr_id} 추천";
    insert_point($member['mb_id'], 10, $po_content, $bo_table, $wr_id, '추천');

    // 작성자에게 포인트 지급 (30포인트) 및 알림 전송
    $write = sql_fetch("SELECT mb_id FROM {$write_table} WHERE wr_id = '{$wr_id}'");
    if ($write['mb_id'] && $write['mb_id'] != $member['mb_id']) {
        $po_content = "{$board['bo_subject']} {$wr_id} 추천 받음";
        insert_point($write['mb_id'], 30, $po_content, $bo_table, $wr_id, '추천받음');

        // 좋아요 알림 전송
        $notification_content = generate_notification_content('good', $member['mb_nick']);
        $notification_url = G5_BBS_URL . '/board.php?bo_table=' . $bo_table . '&wr_id=' . $wr_id;
        create_notification('good', $member['mb_id'], $write['mb_id'], $bo_table, $wr_id, 0, $notification_content, $notification_url);
    }

    $result = 'success';
}

// 현재 추천 수 및 좋아요 상태
$write = sql_fetch("SELECT wr_good FROM {$write_table} WHERE wr_id = '{$wr_id}'");
$good_count = (int)$write['wr_good'];

// 현재 사용자의 좋아요 상태 확인
$is_good = false;
$good_check = sql_fetch("SELECT COUNT(*) as cnt FROM {$g5['board_good_table']}
                         WHERE bo_table = '{$bo_table}'
                         AND wr_id = '{$wr_id}'
                         AND mb_id = '{$member['mb_id']}'");
if ($good_check && $good_check['cnt'] > 0) {
    $is_good = true;
}

// 좋아요 미리보기 (최대 3명)
$likes_preview = array();
if ($good_count > 0) {
    $likes_sql = "SELECT bg.mb_id, m.mb_name, m.mb_nick
                  FROM {$g5['board_good_table']} bg
                  LEFT JOIN {$g5['member_table']} m ON bg.mb_id = m.mb_id
                  WHERE bg.bo_table = '{$bo_table}'
                  AND bg.wr_id = '{$wr_id}'
                  AND bg.bg_flag = 'good'
                  ORDER BY bg.bg_datetime DESC
                  LIMIT 3";
    $likes_result = sql_query($likes_sql);
    while ($liker = sql_fetch_array($likes_result)) {
        $likes_preview[] = array(
            'mb_id' => $liker['mb_id'],
            'name' => $liker['mb_name'] ? $liker['mb_name'] : ($liker['mb_nick'] ? $liker['mb_nick'] : $liker['mb_id']),
            'photo' => get_profile_image_url($liker['mb_id'])
        );
    }
}

echo json_encode([
    'result' => $result,
    'count' => $good_count,
    'is_good' => $is_good,
    'likes_preview' => $likes_preview
]);
?>
