<?php
/**
 * 월별 뱃지 결산 스크립트
 *
 * 사용 방법:
 * 1. 크론잡: 매월 1일 00:05에 실행 (전월 결산)
 *    0 5 1 * * php /path/to/badge_settlement.php
 *
 * 2. 수동 실행: 관리자 페이지에서 호출
 *    badge_settlement.php?year=2024&month=12
 */

include_once('./_common.php');

// 관리자 또는 CLI만 실행 가능
if (php_sapi_name() !== 'cli' && !$is_admin) {
    die('권한이 없습니다.');
}

// 연도/월 파라미터 (없으면 전월)
if (isset($_GET['year']) && isset($_GET['month'])) {
    $settle_year = (int)$_GET['year'];
    $settle_month = (int)$_GET['month'];
} else {
    // 전월 계산
    $prev_month_date = strtotime('first day of last month');
    $settle_year = (int)date('Y', $prev_month_date);
    $settle_month = (int)date('n', $prev_month_date);
}

// 시작일/종료일
$start_date = sprintf('%04d-%02d-01 00:00:00', $settle_year, $settle_month);
$end_date = date('Y-m-t 23:59:59', strtotime($start_date));
$days_in_month = (int)date('t', strtotime($start_date));

// 베스트 성산인 기준 점수
define('BEST_MEMBER_POINT', 30000);
define('EXCELLENT_MEMBER_POINT', 1000);

$result_log = array();
$result_log[] = "===== 뱃지 결산 시작: {$settle_year}년 {$settle_month}월 =====";
$result_log[] = "기간: {$start_date} ~ {$end_date}";
$result_log[] = "";

// ===========================
// 1. 베스트 성산인 (3만점 이상 상위 3명)
// ===========================
$result_log[] = "[1] 베스트 성산인 결산";

$best_sql = "
    SELECT
        m.mb_id,
        COALESCE(SUM(p.po_point), 0) as monthly_points
    FROM {$g5['member_table']} m
    LEFT JOIN {$g5['point_table']} p ON m.mb_id = p.mb_id
        AND p.po_datetime >= '{$start_date}'
        AND p.po_datetime <= '{$end_date}'
    WHERE m.mb_level > 1
    GROUP BY m.mb_id
    HAVING monthly_points >= " . BEST_MEMBER_POINT . "
    ORDER BY monthly_points DESC
    LIMIT 3
";
$best_result = sql_query($best_sql);
$rank = 1;
while ($row = sql_fetch_array($best_result)) {
    save_badge($row['mb_id'], BADGE_BEST_MEMBER, $settle_year, $settle_month, $rank, $row['monthly_points']);
    $result_log[] = "  - {$rank}위: {$row['mb_id']} ({$row['monthly_points']}점)";
    $rank++;
}
if ($rank == 1) {
    $result_log[] = "  - 해당자 없음";
}

// ===========================
// 2. 우수 성산인 (1000점 이상, 베스트 제외)
// ===========================
$result_log[] = "";
$result_log[] = "[2] 우수 성산인 결산";

$excellent_sql = "
    SELECT
        m.mb_id,
        COALESCE(SUM(p.po_point), 0) as monthly_points
    FROM {$g5['member_table']} m
    LEFT JOIN {$g5['point_table']} p ON m.mb_id = p.mb_id
        AND p.po_datetime >= '{$start_date}'
        AND p.po_datetime <= '{$end_date}'
    WHERE m.mb_level > 1
    GROUP BY m.mb_id
    HAVING monthly_points >= " . EXCELLENT_MEMBER_POINT . " AND monthly_points < " . BEST_MEMBER_POINT . "
    ORDER BY monthly_points DESC
";
$excellent_result = sql_query($excellent_sql);
$count = 0;
while ($row = sql_fetch_array($excellent_result)) {
    save_badge($row['mb_id'], BADGE_EXCELLENT_MEMBER, $settle_year, $settle_month, 0, $row['monthly_points']);
    $count++;
}
$result_log[] = "  - {$count}명 부여";

// ===========================
// 3. 1등 출석왕 (매일 가장 먼저 출석한 횟수 상위 3명)
// ===========================
$result_log[] = "";
$result_log[] = "[3] 1등 출석왕 결산";

$first_login_sql = "
    SELECT
        p.mb_id,
        COUNT(*) as first_count
    FROM {$g5['point_table']} p
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
    AND p.po_datetime = (
        SELECT MIN(p2.po_datetime)
        FROM {$g5['point_table']} p2
        WHERE p2.po_content LIKE '%첫로그인%'
        AND DATE(p2.po_datetime) = DATE(p.po_datetime)
        AND p2.po_datetime >= '{$start_date}'
        AND p2.po_datetime <= '{$end_date}'
    )
    GROUP BY p.mb_id
    ORDER BY first_count DESC
    LIMIT 3
";
$first_result = sql_query($first_login_sql);
$rank = 1;
while ($row = sql_fetch_array($first_result)) {
    save_badge($row['mb_id'], BADGE_FIRST_LOGIN, $settle_year, $settle_month, $rank, $row['first_count'] . '회');
    $result_log[] = "  - {$rank}위: {$row['mb_id']} ({$row['first_count']}회)";
    $rank++;
}
if ($rank == 1) {
    $result_log[] = "  - 해당자 없음";
}

// ===========================
// 4. 최다 출석자 (이번 달 총 출석 일수 상위 3명)
// ===========================
$result_log[] = "";
$result_log[] = "[4] 최다 출석자 결산";

$most_attendance_sql = "
    SELECT
        p.mb_id,
        COUNT(DISTINCT DATE(p.po_datetime)) as attend_days
    FROM {$g5['point_table']} p
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
    GROUP BY p.mb_id
    ORDER BY attend_days DESC
    LIMIT 3
";
$attend_result = sql_query($most_attendance_sql);
$rank = 1;
while ($row = sql_fetch_array($attend_result)) {
    save_badge($row['mb_id'], BADGE_MOST_ATTENDANCE, $settle_year, $settle_month, $rank, $row['attend_days'] . '일');
    $result_log[] = "  - {$rank}위: {$row['mb_id']} ({$row['attend_days']}일)";
    $rank++;
}
if ($rank == 1) {
    $result_log[] = "  - 해당자 없음";
}

// ===========================
// 5. 연속 출석 챔피언 (최장 연속 출석 상위 3명)
// ===========================
$result_log[] = "";
$result_log[] = "[5] 연속 출석 챔피언 결산";

// 연속 출석 계산 함수
function getConsecutiveDaysForSettlement($mb_id, $g5, $end_date) {
    $sql = "
        SELECT DISTINCT DATE(po_datetime) as login_date
        FROM {$g5['point_table']}
        WHERE mb_id = '{$mb_id}'
        AND po_content LIKE '%첫로그인%'
        AND po_datetime <= '{$end_date}'
        ORDER BY login_date DESC
    ";
    $result = sql_query($sql);
    $dates = array();
    while ($row = sql_fetch_array($result)) {
        $dates[] = $row['login_date'];
    }

    if (empty($dates)) return 0;

    $consecutive = 1;
    $max_consecutive = 1;

    for ($i = 0; $i < count($dates) - 1; $i++) {
        $current = strtotime($dates[$i]);
        $next = strtotime($dates[$i + 1]);
        $diff = ($current - $next) / 86400;

        if ($diff == 1) {
            $consecutive++;
            $max_consecutive = max($max_consecutive, $consecutive);
        } else {
            $consecutive = 1;
        }
    }

    return $max_consecutive;
}

// 활성 회원들의 연속 출석 계산
$active_members_sql = "
    SELECT DISTINCT p.mb_id
    FROM {$g5['point_table']} p
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
";
$active_result = sql_query($active_members_sql);
$consecutive_members = array();
while ($row = sql_fetch_array($active_result)) {
    $consecutive_days = getConsecutiveDaysForSettlement($row['mb_id'], $g5, $end_date);
    if ($consecutive_days >= 3) {
        $consecutive_members[] = array(
            'mb_id' => $row['mb_id'],
            'consecutive_days' => $consecutive_days
        );
    }
}
usort($consecutive_members, function($a, $b) {
    if ($b['consecutive_days'] == $a['consecutive_days']) {
        return strcmp($a['mb_id'], $b['mb_id']);
    }
    return $b['consecutive_days'] - $a['consecutive_days'];
});
$consecutive_members = array_slice($consecutive_members, 0, 3);

$rank = 1;
foreach ($consecutive_members as $member) {
    save_badge($member['mb_id'], BADGE_CONSECUTIVE, $settle_year, $settle_month, $rank, $member['consecutive_days'] . '일');
    $result_log[] = "  - {$rank}위: {$member['mb_id']} ({$member['consecutive_days']}일)";
    $rank++;
}
if ($rank == 1) {
    $result_log[] = "  - 해당자 없음";
}

// ===========================
// 6. 이달의 개근상 (매일 출석한 회원)
// ===========================
$result_log[] = "";
$result_log[] = "[6] 개근상 결산";

$perfect_sql = "
    SELECT
        p.mb_id,
        COUNT(DISTINCT DATE(p.po_datetime)) as attend_days
    FROM {$g5['point_table']} p
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
    GROUP BY p.mb_id
    HAVING attend_days >= {$days_in_month}
";
$perfect_result = sql_query($perfect_sql);
$count = 0;
while ($row = sql_fetch_array($perfect_result)) {
    save_badge($row['mb_id'], BADGE_PERFECT_ATTENDANCE, $settle_year, $settle_month, 0, $row['attend_days'] . '일 개근');
    $result_log[] = "  - {$row['mb_id']} ({$row['attend_days']}일)";
    $count++;
}
if ($count == 0) {
    $result_log[] = "  - 해당자 없음";
}

// ===========================
// 7. 새벽 출석자 (04:30 ~ 05:00 출석 상위 3명)
// ===========================
$result_log[] = "";
$result_log[] = "[7] 새벽 출석자 결산";

$dawn_sql = "
    SELECT
        p.mb_id,
        COUNT(*) as dawn_count
    FROM {$g5['point_table']} p
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
    AND TIME(p.po_datetime) >= '04:30:00'
    AND TIME(p.po_datetime) < '05:00:00'
    GROUP BY p.mb_id
    ORDER BY dawn_count DESC
    LIMIT 3
";
$dawn_result = sql_query($dawn_sql);
$rank = 1;
while ($row = sql_fetch_array($dawn_result)) {
    save_badge($row['mb_id'], BADGE_DAWN, $settle_year, $settle_month, $rank, $row['dawn_count'] . '회');
    $result_log[] = "  - {$rank}위: {$row['mb_id']} ({$row['dawn_count']}회)";
    $rank++;
}
if ($rank == 1) {
    $result_log[] = "  - 해당자 없음";
}

$result_log[] = "";
$result_log[] = "===== 결산 완료 =====";

// 결과 출력
if (php_sapi_name() === 'cli') {
    // CLI 모드
    echo implode("\n", $result_log) . "\n";
} else {
    // 웹 모드
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre>" . implode("\n", $result_log) . "</pre>";
    echo "<p><a href='" . G5_BBS_URL . "/halloffame.php'>명예의 전당으로 이동</a></p>";
}
