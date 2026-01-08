<?php
/**
 * 출석 체크 API
 * - 출석 가능 시간: 04:30 ~ 23:59
 * - 하루에 한 번만 출석 가능
 * - 출석 시 포인트 지급
 */

include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

// 로그인 체크
if (!$member['mb_id']) {
    echo json_encode(array(
        'success' => false,
        'error' => 'login_required',
        'message' => '로그인이 필요합니다.'
    ));
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// 출석 가능 시간 체크 함수
function isAttendanceTime() {
    $current_hour = (int)date('H');
    $current_minute = (int)date('i');
    $current_time = $current_hour * 60 + $current_minute; // 분 단위로 변환

    $start_time = 4 * 60 + 30; // 04:30 = 270분
    $end_time = 23 * 60 + 59;  // 23:59 = 1439분

    return ($current_time >= $start_time && $current_time <= $end_time);
}

// 오늘 출석 여부 체크
function hasAttendedToday($mb_id, $g5) {
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    $sql = "SELECT COUNT(*) as cnt FROM {$g5['point_table']}
            WHERE mb_id = '{$mb_id}'
            AND po_content LIKE '%첫로그인%'
            AND po_datetime >= '{$today_start}'
            AND po_datetime <= '{$today_end}'";
    $row = sql_fetch($sql);
    return $row['cnt'] > 0;
}

// 오늘의 출석 순위 조회 (동일 시간은 공동 등수)
function getTodayAttendanceRank($mb_id, $g5) {
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    $sql = "SELECT mb_id, po_datetime, TIME(po_datetime) as attend_time_only
            FROM {$g5['point_table']}
            WHERE po_content LIKE '%첫로그인%'
            AND po_datetime >= '{$today_start}'
            AND po_datetime <= '{$today_end}'
            ORDER BY TIME(po_datetime) ASC";
    $result = sql_query($sql);

    $prev_time = '';
    $rank = 0;
    $display_rank = 0;
    $target_rank = 0;

    while ($row = sql_fetch_array($result)) {
        $rank++;
        $current_time = $row['attend_time_only'];

        // 이전 시간과 다르면 새로운 등수
        if ($current_time !== $prev_time) {
            $display_rank = $rank;
            $prev_time = $current_time;
        }

        if ($row['mb_id'] == $mb_id) {
            return $display_rank;
        }
    }
    return 0;
}

// 연속 출석 일수 계산
function getConsecutiveDays($mb_id, $g5) {
    $sql = "SELECT DISTINCT DATE(po_datetime) as login_date
            FROM {$g5['point_table']}
            WHERE mb_id = '{$mb_id}'
            AND po_content LIKE '%첫로그인%'
            ORDER BY login_date DESC";
    $result = sql_query($sql);

    $dates = array();
    while ($row = sql_fetch_array($result)) {
        $dates[] = $row['login_date'];
    }

    if (empty($dates)) return 0;

    // 오늘 날짜부터 연속 체크
    $today = date('Y-m-d');
    $consecutive = 0;

    foreach ($dates as $date) {
        $expected_date = date('Y-m-d', strtotime("-{$consecutive} days"));
        if ($date == $expected_date) {
            $consecutive++;
        } else {
            break;
        }
    }

    return $consecutive;
}

// 오늘의 전체 출석자 목록 조회
function getTodayAttendanceList($g5, $limit = 50) {
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    // 시분초까지 정렬하고, 동일 시간일 경우 이름순 정렬
    $sql = "SELECT p.mb_id, m.mb_name, m.mb_nick, p.po_datetime as attend_time,
                   TIME(p.po_datetime) as attend_time_only
            FROM {$g5['point_table']} p
            JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
            WHERE p.po_content LIKE '%첫로그인%'
            AND p.po_datetime >= '{$today_start}'
            AND p.po_datetime <= '{$today_end}'
            ORDER BY TIME(p.po_datetime) ASC, m.mb_name ASC
            LIMIT {$limit}";
    $result = sql_query($sql);

    $list = array();
    $prev_time = '';
    $rank = 0;
    $display_rank = 0;

    while ($row = sql_fetch_array($result)) {
        $rank++;
        $current_time = $row['attend_time_only'];

        // 이전 시간과 같으면 공동 등수, 다르면 새로운 등수
        if ($current_time !== $prev_time) {
            $display_rank = $rank;
            $prev_time = $current_time;
        }

        $profile_img = get_profile_image_url($row['mb_id']);

        $list[] = array(
            'rank' => $display_rank,
            'mb_id' => $row['mb_id'],
            'mb_name' => get_text($row['mb_name']),
            'mb_nick' => get_text($row['mb_nick']),
            'attend_time' => date('H:i:s', strtotime($row['attend_time'])),
            'profile_img' => $profile_img
        );
    }

    return $list;
}

// 오늘 총 출석자 수 (회원 테이블과 JOIN하여 탈퇴한 회원 제외)
function getTodayAttendanceCount($g5) {
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    $sql = "SELECT COUNT(*) as cnt
            FROM {$g5['point_table']} p
            JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
            WHERE p.po_content LIKE '%첫로그인%'
            AND p.po_datetime >= '{$today_start}'
            AND p.po_datetime <= '{$today_end}'";
    $row = sql_fetch($sql);
    return (int)$row['cnt'];
}

switch ($action) {
    case 'check':
        // 출석 체크 실행

        // 시간 체크
        if (!isAttendanceTime()) {
            echo json_encode(array(
                'success' => false,
                'error' => 'not_attendance_time',
                'message' => '출석 가능 시간이 아닙니다. (04:30 ~ 23:59)'
            ));
            exit;
        }

        // 이미 출석했는지 체크
        if (hasAttendedToday($member['mb_id'], $g5)) {
            $rank = getTodayAttendanceRank($member['mb_id'], $g5);
            $consecutive = getConsecutiveDays($member['mb_id'], $g5);
            $total_count = getTodayAttendanceCount($g5);

            echo json_encode(array(
                'success' => false,
                'error' => 'already_attended',
                'message' => '이미 오늘 출석하셨습니다.',
                'data' => array(
                    'rank' => $rank,
                    'consecutive_days' => $consecutive,
                    'total_count' => $total_count
                )
            ));
            exit;
        }

        // 출석 처리 (포인트 지급)
        $point = $config['cf_login_point'] ? $config['cf_login_point'] : 10;
        insert_point($member['mb_id'], $point, G5_TIME_YMD.' 첫로그인', '@login', $member['mb_id'], G5_TIME_YMD);

        // 출석 후 정보 조회
        $rank = getTodayAttendanceRank($member['mb_id'], $g5);
        $consecutive = getConsecutiveDays($member['mb_id'], $g5);
        $total_count = getTodayAttendanceCount($g5);
        $attend_time = date('H:i:s');

        echo json_encode(array(
            'success' => true,
            'message' => '출석 완료!',
            'data' => array(
                'rank' => $rank,
                'consecutive_days' => $consecutive,
                'total_count' => $total_count,
                'attend_time' => $attend_time,
                'point' => $point
            )
        ));
        break;

    case 'status':
        // 출석 상태 조회
        $is_attendance_time = isAttendanceTime();
        $has_attended = hasAttendedToday($member['mb_id'], $g5);
        $rank = $has_attended ? getTodayAttendanceRank($member['mb_id'], $g5) : 0;
        $consecutive = getConsecutiveDays($member['mb_id'], $g5);
        $total_count = getTodayAttendanceCount($g5);

        // 출석 가능 시간까지 남은 시간
        $time_until_start = '';
        if (!$is_attendance_time) {
            $current_hour = (int)date('H');
            if ($current_hour < 4 || ($current_hour == 4 && (int)date('i') < 30)) {
                $target = strtotime(date('Y-m-d 04:30:00'));
                $diff = $target - time();
                $hours = floor($diff / 3600);
                $minutes = floor(($diff % 3600) / 60);
                $time_until_start = "{$hours}시간 {$minutes}분";
            }
        }

        echo json_encode(array(
            'success' => true,
            'data' => array(
                'is_attendance_time' => $is_attendance_time,
                'has_attended' => $has_attended,
                'rank' => $rank,
                'consecutive_days' => $consecutive,
                'total_count' => $total_count,
                'time_until_start' => $time_until_start
            )
        ));
        break;

    case 'list':
        // 오늘의 출석자 목록 조회
        $list = getTodayAttendanceList($g5);
        $total_count = getTodayAttendanceCount($g5);

        echo json_encode(array(
            'success' => true,
            'data' => array(
                'list' => $list,
                'total_count' => $total_count
            )
        ));
        break;

    default:
        echo json_encode(array(
            'success' => false,
            'error' => 'invalid_action',
            'message' => '잘못된 요청입니다.'
        ));
}
