<?php
// 코멘트 삭제
include_once('./_common.php');

$comment_id = isset($_REQUEST['comment_id']) ? (int) $_REQUEST['comment_id'] : 0;
$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';
$is_ajax = isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1';

// AJAX 에러 응답 함수
function ajax_error($message) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'message' => $message));
    exit;
}

$delete_comment_token = get_session('ss_delete_comment_'.$comment_id.'_token');
set_session('ss_delete_comment_'.$comment_id.'_token', '');

if (!($token && $delete_comment_token == $token)) {
    if ($is_ajax) {
        ajax_error('토큰 에러로 삭제 불가합니다.');
    } else {
        alert('토큰 에러로 삭제 불가합니다.');
    }
}

// 4.1
@include_once($board_skin_path.'/delete_comment.head.skin.php');

$write = sql_fetch(" select * from {$write_table} where wr_id = '{$comment_id}' ");

if (!$write['wr_id'] || !$write['wr_is_comment']) {
    if ($is_ajax) {
        ajax_error('등록된 코멘트가 없거나 코멘트 글이 아닙니다.');
    } else {
        alert('등록된 코멘트가 없거나 코멘트 글이 아닙니다.');
    }
}

if ($is_admin == 'super') // 최고관리자 통과
    ;
else if ($is_admin == 'group') { // 그룹관리자
    $mb = get_member($write['mb_id']);
    if ($member['mb_id'] === $group['gr_admin']) { // 자신이 관리하는 그룹인가?
        if ($member['mb_level'] >= $mb['mb_level']) // 자신의 레벨이 크거나 같다면 통과
            ;
        else {
            $error_msg = '그룹관리자의 권한보다 높은 회원의 코멘트이므로 삭제할 수 없습니다.';
            if ($is_ajax) ajax_error($error_msg);
            else alert($error_msg);
        }
    } else {
        $error_msg = '자신이 관리하는 그룹의 게시판이 아니므로 코멘트를 삭제할 수 없습니다.';
        if ($is_ajax) ajax_error($error_msg);
        else alert($error_msg);
    }
} else if ($is_admin === 'board') { // 게시판관리자이면
    $mb = get_member($write['mb_id']);
    if ($member['mb_id'] === $board['bo_admin']) { // 자신이 관리하는 게시판인가?
        if ($member['mb_level'] >= $mb['mb_level']) // 자신의 레벨이 크거나 같다면 통과
            ;
        else {
            $error_msg = '게시판관리자의 권한보다 높은 회원의 코멘트이므로 삭제할 수 없습니다.';
            if ($is_ajax) ajax_error($error_msg);
            else alert($error_msg);
        }
    } else {
        $error_msg = '자신이 관리하는 게시판이 아니므로 코멘트를 삭제할 수 없습니다.';
        if ($is_ajax) ajax_error($error_msg);
        else alert($error_msg);
    }
} else if ($member['mb_id']) {
    if ($member['mb_id'] !== $write['mb_id']) {
        $error_msg = '자신의 글이 아니므로 삭제할 수 없습니다.';
        if ($is_ajax) ajax_error($error_msg);
        else alert($error_msg);
    }
} else {
    if (!check_password($wr_password, $write['wr_password'])) {
        $error_msg = '비밀번호가 틀립니다.';
        if ($is_ajax) ajax_error($error_msg);
        else alert($error_msg);
    }
}

$len = strlen($write['wr_comment_reply']);
if ($len < 0) $len = 0;
$comment_reply = substr($write['wr_comment_reply'], 0, $len);

$sql = " select count(*) as cnt from {$write_table}
            where wr_comment_reply like '{$comment_reply}%'
            and wr_id <> '{$comment_id}'
            and wr_parent = '{$write['wr_parent']}'
            and wr_comment = '{$write['wr_comment']}'
            and wr_is_comment = 1 ";
$row = sql_fetch($sql);
if ($row['cnt'] && !$is_admin) {
    $error_msg = '이 코멘트와 관련된 답변코멘트가 존재하므로 삭제 할 수 없습니다.';
    if ($is_ajax) ajax_error($error_msg);
    else alert($error_msg);
}

// 코멘트 포인트 삭제
if (!delete_point($write['mb_id'], $bo_table, $comment_id, '댓글'))
    insert_point($write['mb_id'], $board['bo_comment_point'] * (-1), "{$board['bo_subject']} {$write['wr_parent']}-{$comment_id} 댓글삭제");

// 코멘트 삭제
sql_query(" delete from {$write_table} where wr_id = '{$comment_id}' ");

// 코멘트가 삭제되므로 해당 게시물에 대한 최근 시간을 다시 얻는다.
$sql = " select max(wr_datetime) as wr_last from {$write_table} where wr_parent = '{$write['wr_parent']}' ";
$row = sql_fetch($sql);

// 원글의 코멘트 숫자를 감소
sql_query(" update {$write_table} set wr_comment = wr_comment - 1, wr_last = '{$row['wr_last']}' where wr_id = '{$write['wr_parent']}' ");

// 코멘트 숫자 감소
sql_query(" update {$g5['board_table']} set bo_count_comment = bo_count_comment - 1 where bo_table = '{$bo_table}' ");

// 새글 삭제
sql_query(" delete from {$g5['board_new_table']} where bo_table = '{$bo_table}' and wr_id = '{$comment_id}' ");

// 사용자 코드 실행
@include_once($board_skin_path.'/delete_comment.skin.php');
@include_once($board_skin_path.'/delete_comment.tail.skin.php');

delete_cache_latest($bo_table);

run_event('bbs_delete_comment', $comment_id, $board);

// AJAX 요청인 경우 JSON 응답 반환
if ($is_ajax) {
    // 남은 댓글 수 계산
    $comment_count_result = sql_fetch("SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_parent = '{$write['wr_parent']}' AND wr_is_comment = 1");

    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'message' => '댓글이 삭제되었습니다.',
        'comment_count' => $comment_count_result['cnt']
    ));
    exit;
} else {
    goto_url(short_url_clean(G5_HTTP_BBS_URL.'/post.php?bo_table='.$bo_table.'&amp;wr_id='.$write['wr_parent'].'&amp;page='.$page. $qstr));
}