<?php
include_once('./_common.php');

// 관리자만 접근 가능
if (!$is_admin) {
    alert('관리자만 표어를 등록할 수 있습니다.', G5_BBS_URL.'/index.php');
}

$bo_table = isset($_POST['bo_table']) ? $_POST['bo_table'] : 'slogan';
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    alert("'slogan' 게시판이 존재하지 않습니다.", G5_BBS_URL.'/index.php');
}

$write_table = $g5['write_prefix'] . $bo_table;

$w = isset($_POST['w']) ? $_POST['w'] : '';
$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;
$wr_subject = isset($_POST['wr_subject']) ? trim($_POST['wr_subject']) : '';
$wr_content = isset($_POST['wr_content']) ? trim($_POST['wr_content']) : '';

if (!$wr_subject) {
    alert('제목을 입력해주세요.');
}

if (!$wr_content) {
    alert('표어 문구를 입력해주세요.');
}

$wr_subject = sql_real_escape_string($wr_subject);
$wr_content = sql_real_escape_string($wr_content);
$mb_id = $member['mb_id'];
$wr_name = $member['mb_nick'] ? $member['mb_nick'] : $member['mb_name'];
$wr_datetime = date('Y-m-d H:i:s');
$wr_ip = $_SERVER['REMOTE_ADDR'];

if ($w == 'u' && $wr_id) {
    // 수정
    $sql = "UPDATE {$write_table} SET
                wr_subject = '{$wr_subject}',
                wr_content = '{$wr_content}',
                wr_last = '{$wr_datetime}'
            WHERE wr_id = '{$wr_id}'";
    sql_query($sql);

    $msg = '표어가 수정되었습니다.';
} else {
    // 신규 등록
    // wr_num 구하기
    $sql = "SELECT MIN(wr_num) as min_num FROM {$write_table}";
    $row = sql_fetch($sql);
    $wr_num = (int)$row['min_num'] - 1;

    $sql = "INSERT INTO {$write_table} SET
                wr_num = '{$wr_num}',
                wr_reply = '',
                wr_parent = 0,
                wr_is_comment = 0,
                wr_comment = 0,
                wr_comment_reply = '',
                ca_name = '',
                wr_option = '',
                wr_subject = '{$wr_subject}',
                wr_content = '{$wr_content}',
                wr_link1 = '',
                wr_link2 = '',
                wr_link1_hit = 0,
                wr_link2_hit = 0,
                wr_hit = 0,
                wr_good = 0,
                wr_nogood = 0,
                mb_id = '{$mb_id}',
                wr_password = '',
                wr_name = '{$wr_name}',
                wr_email = '',
                wr_homepage = '',
                wr_datetime = '{$wr_datetime}',
                wr_last = '{$wr_datetime}',
                wr_ip = '{$wr_ip}',
                wr_1 = '',
                wr_2 = '',
                wr_3 = '',
                wr_4 = '',
                wr_5 = '',
                wr_6 = '',
                wr_7 = '',
                wr_8 = '',
                wr_9 = '',
                wr_10 = ''";
    sql_query($sql);
    $wr_id = sql_insert_id();

    // wr_parent 업데이트
    sql_query("UPDATE {$write_table} SET wr_parent = '{$wr_id}' WHERE wr_id = '{$wr_id}'");

    $msg = '표어가 등록되었습니다.';
}

alert($msg, G5_BBS_URL.'/index.php');
