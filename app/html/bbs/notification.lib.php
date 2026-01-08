<?php
/**
 * 알림 관련 라이브러리
 */

if (!defined('_GNUBOARD_')) exit;

/**
 * 알림 생성 함수
 * @param string $type 알림 타입 (comment, reply, good, word)
 * @param string $from_mb_id 알림을 발생시킨 회원 ID
 * @param string $to_mb_id 알림을 받을 회원 ID
 * @param string $bo_table 게시판 테이블명
 * @param int $wr_id 게시글 ID
 * @param int $comment_id 댓글 ID (선택)
 * @param string $content 알림 내용
 * @param string $url 이동할 URL
 */
function create_notification($type, $from_mb_id, $to_mb_id, $bo_table = '', $wr_id = 0, $comment_id = 0, $content = '', $url = '') {
    global $g5;

    // 디버깅 로그
    error_log("create_notification 호출: type=$type, from=$from_mb_id, to=$to_mb_id");

    // 자기 자신에게는 알림 안 보냄
    if ($from_mb_id === $to_mb_id) {
        error_log("알림 생성 실패: 자기 자신에게 알림 (from=$from_mb_id, to=$to_mb_id)");
        return false;
    }

    // 받는 사람이 회원인지 확인
    if (!$to_mb_id) {
        error_log("알림 생성 실패: 받는 사람 ID 없음");
        return false;
    }

    $sql = " INSERT INTO g5_notifications SET
                no_type = '$type',
                no_from_mb_id = '$from_mb_id',
                no_to_mb_id = '$to_mb_id',
                no_bo_table = '$bo_table',
                no_wr_id = '$wr_id',
                no_comment_id = '$comment_id',
                no_content = '".addslashes($content)."',
                no_url = '".addslashes($url)."',
                no_is_read = 0,
                no_datetime = '".G5_TIME_YMDHIS."' ";

    $result = sql_query($sql);
    if (!$result) {
        error_log("알림 생성 SQL 실패: " . sql_error());
    } else {
        error_log("알림 생성 성공: type=$type, to=$to_mb_id");
    }
    return $result;
}

/**
 * 알림 목록 가져오기
 * @param string $mb_id 회원 ID
 * @param int $limit 가져올 개수
 * @param int $offset 오프셋
 */
function get_notifications($mb_id, $limit = 20, $offset = 0) {
    global $g5;

    $sql = " SELECT n.*,
                    m.mb_nick as from_mb_nick
             FROM g5_notifications n
             LEFT JOIN {$g5['member_table']} m ON n.no_from_mb_id = m.mb_id
             WHERE n.no_to_mb_id = '$mb_id'
             ORDER BY n.no_datetime DESC
             LIMIT $offset, $limit ";

    $result = sql_query($sql);
    $notifications = array();

    while ($row = sql_fetch_array($result)) {
        // 메시지 미리보기 추가
        $row['message_preview'] = '';

        if ($row['no_bo_table'] && $row['no_wr_id']) {
            $write_table = $g5['write_prefix'] . $row['no_bo_table'];

            // 댓글인 경우 댓글 내용, 아니면 원글 내용
            $target_id = $row['no_comment_id'] ? $row['no_comment_id'] : $row['no_wr_id'];

            $write_sql = "SELECT wr_subject, wr_content FROM {$write_table} WHERE wr_id = '{$target_id}'";
            $write_row = sql_fetch($write_sql);

            if ($write_row) {
                // 댓글이면 내용, 원글이면 제목 또는 내용
                if ($row['no_comment_id']) {
                    $preview = strip_tags($write_row['wr_content']);
                } else {
                    $preview = $write_row['wr_subject'] ? $write_row['wr_subject'] : strip_tags($write_row['wr_content']);
                }
                // 50자로 자르기
                $preview = trim(preg_replace('/\s+/', ' ', $preview));
                if (mb_strlen($preview, 'UTF-8') > 50) {
                    $preview = mb_substr($preview, 0, 50, 'UTF-8') . '...';
                }
                $row['message_preview'] = $preview;
            }
        }

        $notifications[] = $row;
    }

    return $notifications;
}

/**
 * 읽지 않은 알림 개수
 * @param string $mb_id 회원 ID
 */
function get_unread_notification_count($mb_id) {
    $sql = " SELECT COUNT(*) as cnt
             FROM g5_notifications
             WHERE no_to_mb_id = '$mb_id'
             AND no_is_read = 0 ";
    $row = sql_fetch($sql);
    return (int)$row['cnt'];
}

/**
 * 알림 읽음 처리
 * @param int $no_id 알림 ID
 * @param string $mb_id 회원 ID (권한 확인용)
 */
function mark_notification_as_read($no_id, $mb_id) {
    $sql = " UPDATE g5_notifications
             SET no_is_read = 1
             WHERE no_id = '$no_id'
             AND no_to_mb_id = '$mb_id' ";
    return sql_query($sql);
}

/**
 * 모든 알림 읽음 처리
 * @param string $mb_id 회원 ID
 */
function mark_all_notifications_as_read($mb_id) {
    $sql = " UPDATE g5_notifications
             SET no_is_read = 1
             WHERE no_to_mb_id = '$mb_id'
             AND no_is_read = 0 ";
    return sql_query($sql);
}

/**
 * 알림 내용 생성
 * @param string $type 알림 타입
 * @param string $from_nick 발생시킨 사람 닉네임
 * @param string $content 추가 내용
 */
function generate_notification_content($type, $from_nick, $content = '') {
    switch ($type) {
        case 'comment':
            return "{$from_nick}님이 회원님의 게시글에 댓글을 남겼습니다.";
        case 'reply':
            return "{$from_nick}님이 회원님의 댓글에 답글을 남겼습니다.";
        case 'good':
            return "{$from_nick}님이 회원님의 게시글에 좋아요를 눌렀습니다.";
        case 'word':
            return "새로운 말씀이 등록되었습니다.";
        default:
            return $content;
    }
}
