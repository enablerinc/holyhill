<?php
if (!defined('_GNUBOARD_')) exit;

// 뱃지 테이블 정의
if (!isset($g5['badge_table'])) {
    $g5['badge_table'] = G5_TABLE_PREFIX.'member_badges';
}

// 테이블 존재 여부 확인 및 생성
function badge_table_check() {
    global $g5;

    $table_check = sql_query("SHOW TABLES LIKE '{$g5['badge_table']}'", false);
    if (!sql_num_rows($table_check)) {
        // 테이블이 없으면 생성
        $sql = "CREATE TABLE IF NOT EXISTS `{$g5['badge_table']}` (
            `mb_badge_id` int(11) NOT NULL AUTO_INCREMENT,
            `mb_id` varchar(20) NOT NULL DEFAULT '' COMMENT '회원 ID',
            `badge_type` varchar(50) NOT NULL DEFAULT '' COMMENT '뱃지 종류',
            `badge_year` int(4) NOT NULL DEFAULT 0 COMMENT '연도',
            `badge_month` int(2) NOT NULL DEFAULT 0 COMMENT '월',
            `badge_rank` int(11) NOT NULL DEFAULT 0 COMMENT '순위',
            `badge_value` varchar(100) DEFAULT '' COMMENT '수치 (점수, 횟수 등)',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`mb_badge_id`),
            UNIQUE KEY `unique_badge` (`mb_id`, `badge_type`, `badge_year`, `badge_month`),
            KEY `idx_mb_id` (`mb_id`),
            KEY `idx_badge_type` (`badge_type`),
            KEY `idx_year_month` (`badge_year`, `badge_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='회원 뱃지 테이블';";

        sql_query($sql, false);
    }
}

// 뱃지 타입 정의
define('BADGE_BEST_MEMBER', 'best_member');           // 베스트 성산인
define('BADGE_EXCELLENT_MEMBER', 'excellent_member'); // 우수 성산인
define('BADGE_FIRST_LOGIN', 'first_login');           // 1등 출석왕
define('BADGE_MOST_ATTENDANCE', 'most_attendance');   // 최다 출석자
define('BADGE_CONSECUTIVE', 'consecutive');           // 연속 출석 챔피언
define('BADGE_PERFECT_ATTENDANCE', 'perfect_attendance'); // 이달의 개근상
define('BADGE_DAWN', 'dawn');                         // 새벽 출석자

// 뱃지 정보 배열
function get_badge_info($badge_type) {
    $badges = array(
        BADGE_BEST_MEMBER => array(
            'name' => '베스트 성산인',
            'icon' => 'fa-trophy',
            'colors' => array(
                1 => 'from-yellow-400 to-amber-500',
                2 => 'from-gray-300 to-gray-400',
                3 => 'from-amber-600 to-amber-700'
            )
        ),
        BADGE_EXCELLENT_MEMBER => array(
            'name' => '우수 성산인',
            'icon' => 'fa-hands-praying',
            'colors' => 'from-purple-400 to-lilac'
        ),
        BADGE_FIRST_LOGIN => array(
            'name' => '1등 출석왕',
            'icon' => 'fa-clock',
            'colors' => 'from-amber-400 to-amber-600'
        ),
        BADGE_MOST_ATTENDANCE => array(
            'name' => '최다 출석',
            'icon' => 'fa-check-double',
            'colors' => 'from-green-400 to-green-600'
        ),
        BADGE_CONSECUTIVE => array(
            'name' => '연속 출석 챔피언',
            'icon' => 'fa-fire',
            'colors' => 'from-red-400 to-red-600'
        ),
        BADGE_PERFECT_ATTENDANCE => array(
            'name' => '개근상',
            'icon' => 'fa-medal',
            'colors' => 'from-purple-400 to-purple-600'
        ),
        BADGE_DAWN => array(
            'name' => '새벽 출석',
            'icon' => 'fa-sun',
            'colors' => 'from-indigo-400 to-indigo-600'
        )
    );

    return isset($badges[$badge_type]) ? $badges[$badge_type] : null;
}

// 회원의 역대 뱃지 조회
function get_member_badges($mb_id, $limit = 0) {
    global $g5;

    badge_table_check();

    $sql = "SELECT * FROM {$g5['badge_table']}
            WHERE mb_id = '".sql_real_escape_string($mb_id)."'
            ORDER BY badge_year DESC, badge_month DESC, badge_type ASC";

    if ($limit > 0) {
        $sql .= " LIMIT {$limit}";
    }

    $result = sql_query($sql);
    $badges = array();
    while ($row = sql_fetch_array($result)) {
        $badges[] = $row;
    }

    return $badges;
}

// 회원의 뱃지 통계 (뱃지 타입별 획득 횟수)
function get_member_badge_stats($mb_id) {
    global $g5;

    badge_table_check();

    $sql = "SELECT badge_type, COUNT(*) as cnt
            FROM {$g5['badge_table']}
            WHERE mb_id = '".sql_real_escape_string($mb_id)."'
            GROUP BY badge_type";

    $result = sql_query($sql);
    $stats = array();
    while ($row = sql_fetch_array($result)) {
        $stats[$row['badge_type']] = $row['cnt'];
    }

    return $stats;
}

// 뱃지 저장
function save_badge($mb_id, $badge_type, $year, $month, $rank = 0, $value = '') {
    global $g5;

    badge_table_check();

    $sql = "INSERT INTO {$g5['badge_table']}
            (mb_id, badge_type, badge_year, badge_month, badge_rank, badge_value, created_at)
            VALUES (
                '".sql_real_escape_string($mb_id)."',
                '".sql_real_escape_string($badge_type)."',
                {$year},
                {$month},
                {$rank},
                '".sql_real_escape_string($value)."',
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                badge_rank = {$rank},
                badge_value = '".sql_real_escape_string($value)."'";

    return sql_query($sql);
}

// 테이블 확인 실행
badge_table_check();

// ===========================
// 자동 월간 결산
// ===========================
function check_and_run_monthly_settlement() {
    global $g5;

    // 현재 날짜
    $current_year = (int)date('Y');
    $current_month = (int)date('n');
    $current_day = (int)date('j');

    // 매월 1일~5일 사이에만 전월 결산 체크 (부하 분산)
    if ($current_day > 5) {
        return;
    }

    // 전월 계산
    $prev_month = $current_month - 1;
    $prev_year = $current_year;
    if ($prev_month < 1) {
        $prev_month = 12;
        $prev_year--;
    }

    // 전월 결산이 이미 되었는지 확인
    $check_sql = "SELECT COUNT(*) as cnt FROM {$g5['badge_table']}
                  WHERE badge_year = {$prev_year}
                  AND badge_month = {$prev_month}";
    $check_result = sql_fetch($check_sql);

    // 이미 결산됨
    if ($check_result && $check_result['cnt'] > 0) {
        return;
    }

    // 전월에 활동 기록이 있는지 확인 (포인트 기록으로 체크)
    $prev_start = sprintf('%04d-%02d-01 00:00:00', $prev_year, $prev_month);
    $prev_end = date('Y-m-t 23:59:59', strtotime($prev_start));

    $activity_sql = "SELECT COUNT(*) as cnt FROM {$g5['point_table']}
                     WHERE po_datetime >= '{$prev_start}'
                     AND po_datetime <= '{$prev_end}'";
    $activity_result = sql_fetch($activity_sql);

    // 활동 기록이 없으면 결산 필요 없음
    if (!$activity_result || $activity_result['cnt'] == 0) {
        return;
    }

    // 결산 스크립트 실행 (직접 실행 대신 결산 함수 호출)
    run_badge_settlement($prev_year, $prev_month);
}

// 뱃지 결산 함수
function run_badge_settlement($settle_year, $settle_month) {
    global $g5;

    // 시작일/종료일
    $start_date = sprintf('%04d-%02d-01 00:00:00', $settle_year, $settle_month);
    $end_date = date('Y-m-t 23:59:59', strtotime($start_date));
    $days_in_month = (int)date('t', strtotime($start_date));

    // 베스트 성산인 기준 점수
    $BEST_MEMBER_POINT = 30000;
    $EXCELLENT_MEMBER_POINT = 1000;

    // 1. 베스트 성산인 (3만점 이상 상위 3명)
    $best_sql = "
        SELECT m.mb_id, COALESCE(SUM(p.po_point), 0) as monthly_points
        FROM {$g5['member_table']} m
        LEFT JOIN {$g5['point_table']} p ON m.mb_id = p.mb_id
            AND p.po_datetime >= '{$start_date}' AND p.po_datetime <= '{$end_date}'
        WHERE m.mb_level > 1
        GROUP BY m.mb_id
        HAVING monthly_points >= {$BEST_MEMBER_POINT}
        ORDER BY monthly_points DESC LIMIT 3";
    $best_result = sql_query($best_sql);
    $rank = 1;
    while ($row = sql_fetch_array($best_result)) {
        save_badge($row['mb_id'], BADGE_BEST_MEMBER, $settle_year, $settle_month, $rank, $row['monthly_points']);
        $rank++;
    }

    // 2. 우수 성산인 (1000점 이상, 베스트 제외)
    $excellent_sql = "
        SELECT m.mb_id, COALESCE(SUM(p.po_point), 0) as monthly_points
        FROM {$g5['member_table']} m
        LEFT JOIN {$g5['point_table']} p ON m.mb_id = p.mb_id
            AND p.po_datetime >= '{$start_date}' AND p.po_datetime <= '{$end_date}'
        WHERE m.mb_level > 1
        GROUP BY m.mb_id
        HAVING monthly_points >= {$EXCELLENT_MEMBER_POINT} AND monthly_points < {$BEST_MEMBER_POINT}
        ORDER BY monthly_points DESC";
    $excellent_result = sql_query($excellent_sql);
    while ($row = sql_fetch_array($excellent_result)) {
        save_badge($row['mb_id'], BADGE_EXCELLENT_MEMBER, $settle_year, $settle_month, 0, $row['monthly_points']);
    }

    // 3. 1등 출석왕
    $first_login_sql = "
        SELECT p.mb_id, COUNT(*) as first_count
        FROM {$g5['point_table']} p
        WHERE p.po_content LIKE '%첫로그인%'
        AND p.po_datetime >= '{$start_date}' AND p.po_datetime <= '{$end_date}'
        AND p.po_datetime = (
            SELECT MIN(p2.po_datetime) FROM {$g5['point_table']} p2
            WHERE p2.po_content LIKE '%첫로그인%'
            AND DATE(p2.po_datetime) = DATE(p.po_datetime)
            AND p2.po_datetime >= '{$start_date}' AND p2.po_datetime <= '{$end_date}'
        )
        GROUP BY p.mb_id ORDER BY first_count DESC LIMIT 3";
    $first_result = sql_query($first_login_sql);
    $rank = 1;
    while ($row = sql_fetch_array($first_result)) {
        save_badge($row['mb_id'], BADGE_FIRST_LOGIN, $settle_year, $settle_month, $rank, $row['first_count'] . '회');
        $rank++;
    }

    // 4. 최다 출석자
    $most_attendance_sql = "
        SELECT p.mb_id, COUNT(DISTINCT DATE(p.po_datetime)) as attend_days
        FROM {$g5['point_table']} p
        WHERE p.po_content LIKE '%첫로그인%'
        AND p.po_datetime >= '{$start_date}' AND p.po_datetime <= '{$end_date}'
        GROUP BY p.mb_id ORDER BY attend_days DESC LIMIT 3";
    $attend_result = sql_query($most_attendance_sql);
    $rank = 1;
    while ($row = sql_fetch_array($attend_result)) {
        save_badge($row['mb_id'], BADGE_MOST_ATTENDANCE, $settle_year, $settle_month, $rank, $row['attend_days'] . '일');
        $rank++;
    }

    // 5. 개근상
    $perfect_sql = "
        SELECT p.mb_id, COUNT(DISTINCT DATE(p.po_datetime)) as attend_days
        FROM {$g5['point_table']} p
        WHERE p.po_content LIKE '%첫로그인%'
        AND p.po_datetime >= '{$start_date}' AND p.po_datetime <= '{$end_date}'
        GROUP BY p.mb_id HAVING attend_days >= {$days_in_month}";
    $perfect_result = sql_query($perfect_sql);
    while ($row = sql_fetch_array($perfect_result)) {
        save_badge($row['mb_id'], BADGE_PERFECT_ATTENDANCE, $settle_year, $settle_month, 0, $row['attend_days'] . '일 개근');
    }

    // 6. 새벽 출석자
    $dawn_sql = "
        SELECT p.mb_id, COUNT(*) as dawn_count
        FROM {$g5['point_table']} p
        WHERE p.po_content LIKE '%첫로그인%'
        AND p.po_datetime >= '{$start_date}' AND p.po_datetime <= '{$end_date}'
        AND TIME(p.po_datetime) >= '04:30:00' AND TIME(p.po_datetime) < '05:00:00'
        GROUP BY p.mb_id ORDER BY dawn_count DESC LIMIT 3";
    $dawn_result = sql_query($dawn_sql);
    $rank = 1;
    while ($row = sql_fetch_array($dawn_result)) {
        save_badge($row['mb_id'], BADGE_DAWN, $settle_year, $settle_month, $rank, $row['dawn_count'] . '회');
        $rank++;
    }
}

// 로그인한 사용자가 있을 때만 자동 결산 체크 (부하 분산: 5% 확률)
if (isset($member) && $member['mb_id'] && rand(1, 100) <= 5) {
    check_and_run_monthly_settlement();
}
