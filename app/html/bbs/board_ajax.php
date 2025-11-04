<?php
include_once('./_common.php');

// CORS 헤더 (필요시)
header('Content-Type: application/json; charset=utf-8');

$bo_table = isset($_GET['bo_table']) ? clean_xss_tags($_GET['bo_table']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
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

// 기간 필터 조건
$date_condition = '';
switch($filter) {
    case '1week':
        $date_condition = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        break;
    case '1month':
        $date_condition = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3month':
        $date_condition = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case 'all':
    default:
        $date_condition = '';
        break;
}

// 게시글 가져오기
$offset = ($page - 1) * $page_rows;
$sql = "SELECT * FROM {$g5['write_prefix']}{$bo_table} 
        WHERE wr_is_comment = 0 {$date_condition}
        ORDER BY wr_good DESC, wr_num DESC 
        LIMIT {$offset}, {$page_rows}";

$result = sql_query($sql);
$items = array();

while ($row = sql_fetch_array($result)) {
    $wr_id = $row['wr_id'];
    
    // 첫 번째 이미지 가져오기
    $first_image = '';
    $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND bf_type BETWEEN 1 AND 3 ORDER BY bf_no LIMIT 1");
    if ($img_result && $img = sql_fetch_array($img_result)) {
        $first_image = G5_DATA_URL.'/file/'.$bo_table.'/'.$img['bf_file'];
    }
    
    // 이미지가 없으면 기본 이미지
    if (!$first_image) {
        $first_image = G5_THEME_URL.'/img/no-image.png';
    }
    
    $view_href = G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id;
    $good_count = isset($row['wr_good']) ? (int)$row['wr_good'] : 0;
    
    $items[] = array(
        'wr_id' => $wr_id,
        'subject' => strip_tags($row['wr_subject']),
        'image' => $first_image,
        'view_href' => $view_href,
        'good_count' => number_format($good_count),
        'datetime' => $row['wr_datetime']
    );
}

echo json_encode([
    'success' => true,
    'items' => $items,
    'page' => $page,
    'count' => count($items)
], JSON_UNESCAPED_UNICODE);
?>
