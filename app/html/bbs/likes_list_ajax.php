<?php
include_once(__DIR__.'/_common.php');

header('Content-Type: application/json');

$bo_table = isset($_GET['bo_table']) ? preg_replace('/[^a-z0-9_]/i', '', $_GET['bo_table']) : '';
$wr_id = isset($_GET['wr_id']) ? (int)$_GET['wr_id'] : 0;

if (!$bo_table || !$wr_id) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");
if (!$board) {
    echo json_encode(['success' => false, 'message' => '존재하지 않는 게시판입니다.']);
    exit;
}

// 좋아요 누른 사람 목록 가져오기
$sql = "SELECT bg.mb_id, bg.bg_datetime, m.mb_name, m.mb_nick
        FROM {$g5['board_good_table']} bg
        LEFT JOIN {$g5['member_table']} m ON bg.mb_id = m.mb_id
        WHERE bg.bo_table = '{$bo_table}'
        AND bg.wr_id = '{$wr_id}'
        AND bg.bg_flag = 'good'
        ORDER BY bg.bg_datetime DESC";

$result = sql_query($sql);

$likes = array();
while ($row = sql_fetch_array($result)) {
    $name = $row['mb_name'] ? $row['mb_name'] : ($row['mb_nick'] ? $row['mb_nick'] : $row['mb_id']);
    $photo = get_profile_image_url($row['mb_id']);

    $likes[] = array(
        'mb_id' => $row['mb_id'],
        'name' => $name,
        'photo' => $photo,
        'datetime' => $row['bg_datetime'],
        'time_ago' => get_time_ago($row['bg_datetime'])
    );
}

// 시간 표시 함수
function get_time_ago($datetime) {
    $time_diff = time() - strtotime($datetime);

    if ($time_diff < 60) {
        return '방금 전';
    } elseif ($time_diff < 3600) {
        return floor($time_diff / 60) . '분 전';
    } elseif ($time_diff < 86400) {
        return floor($time_diff / 3600) . '시간 전';
    } elseif ($time_diff < 172800) {
        return '어제';
    } elseif ($time_diff < 604800) {
        return floor($time_diff / 86400) . '일 전';
    } else {
        return date('m.d', strtotime($datetime));
    }
}

echo json_encode([
    'success' => true,
    'count' => count($likes),
    'likes' => $likes
]);
?>
