<?php
// 멘션 자동완성: 이름으로 회원 찾기 (회원만, 최대 8명)
include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!$is_member) {
    http_response_code(401);
    echo json_encode(array('items' => array()));
    exit;
}

$q = isset($_GET['q']) ? trim(stripslashes($_GET['q'])) : '';
$q = mb_substr(preg_replace('/[\s@]+/u', '', $q), 0, 20, 'UTF-8');
$like = sql_real_escape_string(addcslashes($q, '%_\\'));
$exact = sql_real_escape_string($q);

$where = "mb_level > 1 AND mb_leave_date = '' AND mb_intercept_date = ''";
if ($q !== '') {
    $where .= " AND (mb_name LIKE '{$like}%' OR mb_nick LIKE '{$like}%')";
    $order = "(mb_name = '{$exact}') DESC, mb_today_login DESC";
} else {
    $order = "mb_today_login DESC";   // @ 만 입력했을 때: 최근 접속한 회원
}

$items = array();
$result = sql_query("SELECT mb_id, mb_name, mb_nick FROM {$g5['member_table']} WHERE {$where} ORDER BY {$order} LIMIT 8", false);
if ($result) {
    while ($r = sql_fetch_array($result)) {
        $name = holyhill_member_display_name($r);
        $photo = get_profile_image_url($r['mb_id']);
        $items[] = array(
            'id'    => $r['mb_id'],
            'name'  => $name,
            // 동명이인 구분용: 닉네임이 이름과 다르면 함께 표시
            'sub'   => ($r['mb_nick'] && $r['mb_nick'] !== $name) ? $r['mb_nick'] : '',
            'photo' => (strpos($photo, 'avatar-7.jpg') === false) ? holyhill_url_path($photo) : '',
        );
    }
}

echo json_encode(array('items' => $items), JSON_UNESCAPED_UNICODE);
