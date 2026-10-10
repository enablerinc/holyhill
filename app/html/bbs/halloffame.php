<?php
/**
 * 명예의 전당 페이지
 *
 * [성능 최적화 완료]
 * - N+1 쿼리 문제 해결: UNION ALL로 한번에 집계
 * - 상관 서브쿼리를 JOIN으로 변경
 * - 연속 출석 계산 최적화: 단일 쿼리로 모든 데이터 조회
 *
 * [권장 인덱스] - 추가 성능 향상을 위해 아래 인덱스 생성 권장:
 * ALTER TABLE g5_point ADD INDEX idx_point_datetime_content (po_datetime, po_content(20));
 * ALTER TABLE g5_point ADD INDEX idx_point_mbid_datetime (mb_id, po_datetime);
 * -- 각 게시판 테이블(g5_write_*)에 대해:
 * -- ALTER TABLE g5_write_xxx ADD INDEX idx_write_mbid_datetime (mb_id, wr_datetime, wr_is_comment);
 */
include_once('./_common.php');

$g5['title'] = '명예의 전당';

// ===========================
// 베스트 성산인 월별 기준 점수 설정
// 형식: 'YYYY-MM' => 점수 (해당 월부터 적용)
// ===========================
$best_point_history = array(
    '2026-02' => 100000,  // 2026년 2월부터: 100,000점
    '2026-04' => 200000,  // 2026년 4월부터: 200,000점
    // 그 이전은 기본값 30,000점 적용
);

// 조회 월에 해당하는 기준 점수 계산
function get_best_member_point($year, $month, $history) {
    $current_ym = sprintf('%04d-%02d', $year, $month);
    $applicable_point = 30000; // 기본값

    ksort($history); // 날짜순 정렬
    foreach ($history as $ym => $point) {
        if ($current_ym >= $ym) {
            $applicable_point = $point;
        }
    }

    return $applicable_point;
}

// 현재 년도와 월 설정
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

// 해당 월의 베스트 성산인 기준 점수
$best_member_point = get_best_member_point($current_year, $current_month, $best_point_history);

// 해당 월의 시작일과 종료일 계산
$start_date = sprintf('%04d-%02d-01 00:00:00', $current_year, $current_month);
$end_date = date('Y-m-t 23:59:59', strtotime($start_date));

// ===========================
// 0. 게시판 테이블 목록 미리 조회 (성능 최적화)
// ===========================
$valid_write_tables = array();
$board_list_result = sql_query("SELECT bo_table FROM {$g5['board_table']}");
while ($board = sql_fetch_array($board_list_result)) {
    $bo_table = $board['bo_table'];
    $write_table = $g5['write_prefix'] . $bo_table;
    // information_schema로 테이블 존재 확인 (더 빠름)
    $table_check = sql_fetch("SELECT 1 FROM information_schema.tables
                              WHERE table_schema = DATABASE()
                              AND table_name = '{$write_table}' LIMIT 1");
    if ($table_check) {
        $valid_write_tables[] = $write_table;
    }
}

// ===========================
// 1. 회원별 월간 포인트 집계 및 게시글/댓글 수 계산 (최적화)
// ===========================

// 1-1. 회원별 월간 포인트 합계
$member_points_sql = "
    SELECT
        m.mb_id,
        m.mb_name,
        m.mb_nick,
        COALESCE(SUM(p.po_point), 0) as monthly_points
    FROM {$g5['member_table']} m
    LEFT JOIN {$g5['point_table']} p ON m.mb_id = p.mb_id
        AND p.po_datetime >= '{$start_date}'
        AND p.po_datetime <= '{$end_date}'
    WHERE m.mb_level > 1
    GROUP BY m.mb_id
    HAVING monthly_points > 0
    ORDER BY monthly_points DESC
";

$member_points_result = sql_query($member_points_sql);
$all_members = array();
$member_ids = array();

while ($row = sql_fetch_array($member_points_result)) {
    $mb_id = $row['mb_id'];
    $member_ids[] = "'" . sql_real_escape_string($mb_id) . "'";
    $all_members[$mb_id] = array(
        'mb_id' => $mb_id,
        'name' => get_text($row['mb_name']),
        'nick' => get_text($row['mb_nick']),
        'points' => (int)$row['monthly_points'],
        'avatar' => get_profile_image_url($mb_id),
        'post_count' => 0,
        'comment_count' => 0
    );
}

// 1-2. 모든 게시판에서 회원별 게시글/댓글 수 한번에 집계 (UNION ALL 사용)
if (count($member_ids) > 0 && count($valid_write_tables) > 0) {
    $member_ids_str = implode(',', $member_ids);

    // UNION ALL로 모든 게시판 데이터를 한번에 조회
    $union_parts = array();
    foreach ($valid_write_tables as $write_table) {
        $union_parts[] = "
            SELECT mb_id, wr_is_comment
            FROM {$write_table}
            WHERE mb_id IN ({$member_ids_str})
            AND wr_datetime >= '{$start_date}'
            AND wr_datetime <= '{$end_date}'
        ";
    }

    if (count($union_parts) > 0) {
        $union_sql = "
            SELECT
                mb_id,
                SUM(CASE WHEN wr_is_comment = 0 THEN 1 ELSE 0 END) as post_count,
                SUM(CASE WHEN wr_is_comment = 1 THEN 1 ELSE 0 END) as comment_count
            FROM (" . implode(" UNION ALL ", $union_parts) . ") AS combined
            GROUP BY mb_id
        ";

        $stats_result = sql_query($union_sql);
        while ($stat = sql_fetch_array($stats_result)) {
            if (isset($all_members[$stat['mb_id']])) {
                $all_members[$stat['mb_id']]['post_count'] = (int)$stat['post_count'];
                $all_members[$stat['mb_id']]['comment_count'] = (int)$stat['comment_count'];
            }
        }
    }
}

// 배열을 순서대로 변환
$all_members = array_values($all_members);

// ===========================
// 2. 베스트 성산인 (3만점 이상)
// ===========================
$best_members = array();
$rank = 1;
for ($i = 0; $i < count($all_members); $i++) {
    if ($all_members[$i]['points'] >= $best_member_point) {
        $best_members[] = array_merge($all_members[$i], array('rank' => $rank));
        $rank++;
    }
}
$total_best = count($best_members);

// ===========================
// 2.5 이달의 베스트 감사인 (매일 감사일기 작성)
// ===========================
$best_gratitude_members = array();
$diary_table = $g5['write_prefix'] . 'diary';
$diary_exists = sql_query("SHOW TABLES LIKE '{$diary_table}'", false);

if (sql_num_rows($diary_exists) > 0) {
    // 이번 달 경과 일수
    $days_passed = ($current_year == date('Y') && $current_month == date('n')) ? (int)date('j') : (int)date('t', strtotime($start_date));

    // 매일 감사일기 작성한 회원 조회
    $best_gratitude_sql = "
        SELECT
            d.mb_id,
            m.mb_name,
            m.mb_nick,
            COUNT(DISTINCT DATE(d.wr_datetime)) as diary_days,
            COUNT(*) as total_diaries
        FROM {$diary_table} d
        JOIN {$g5['member_table']} m ON d.mb_id = m.mb_id
        WHERE d.wr_is_comment = 0
        AND d.wr_datetime >= '{$start_date}'
        AND d.wr_datetime <= '{$end_date}'
        GROUP BY d.mb_id
        HAVING diary_days >= {$days_passed}
        ORDER BY total_diaries DESC, diary_days DESC
    ";
    $best_gratitude_result = sql_query($best_gratitude_sql);
    while ($row = sql_fetch_array($best_gratitude_result)) {
        $best_gratitude_members[] = $row;
    }
}

// ===========================
// 3. 이달의 활동 통계 (최적화 - UNION ALL 사용)
// ===========================

$total_posts = 0;
$total_comments = 0;
$total_amens = 0;

// wr_good 컬럼이 있는 테이블 목록 조회
$tables_with_good = array();
foreach ($valid_write_tables as $write_table) {
    $col_check = sql_fetch("SELECT 1 FROM information_schema.columns
                            WHERE table_schema = DATABASE()
                            AND table_name = '{$write_table}'
                            AND column_name = 'wr_good' LIMIT 1");
    if ($col_check) {
        $tables_with_good[] = $write_table;
    }
}

// 한번의 UNION ALL 쿼리로 게시물, 댓글 수 집계
if (count($valid_write_tables) > 0) {
    $union_stats = array();
    foreach ($valid_write_tables as $write_table) {
        $has_good = in_array($write_table, $tables_with_good);
        $good_col = $has_good ? "COALESCE(SUM(wr_good), 0)" : "0";
        $union_stats[] = "
            SELECT
                SUM(CASE WHEN wr_is_comment = 0 THEN 1 ELSE 0 END) as posts,
                SUM(CASE WHEN wr_is_comment = 1 THEN 1 ELSE 0 END) as comments,
                {$good_col} as amens
            FROM {$write_table}
            WHERE wr_datetime >= '{$start_date}'
            AND wr_datetime <= '{$end_date}'
        ";
    }

    $stats_sql = "
        SELECT
            SUM(posts) as total_posts,
            SUM(comments) as total_comments,
            SUM(amens) as total_amens
        FROM (" . implode(" UNION ALL ", $union_stats) . ") AS combined_stats
    ";
    $stats_result = sql_fetch($stats_sql);
    if ($stats_result) {
        $total_posts = (int)$stats_result['total_posts'];
        $total_comments = (int)$stats_result['total_comments'];
        $total_amens = (int)$stats_result['total_amens'];
    }
}

// 회원 출석 수 (해당 월) - 로그인 포인트 기록 기준
$attendance_sql = "
    SELECT COUNT(DISTINCT DATE(po_datetime), mb_id) as cnt
    FROM {$g5['point_table']}
    WHERE po_content LIKE '%로그인%'
    AND po_datetime >= '{$start_date}'
    AND po_datetime <= '{$end_date}'
";
$attendance_result = sql_fetch($attendance_sql);
$total_attendance = $attendance_result ? (int)$attendance_result['cnt'] : 0;

// ===========================
// 4. 출석 우수자 통계
// ===========================

// 이번 달 총 일수 계산
$days_in_month = (int)date('t', strtotime($start_date));
$today_day = ($current_year == date('Y') && $current_month == date('n')) ? (int)date('j') : $days_in_month;

// 전월 시작일과 종료일
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}
$prev_start_date = sprintf('%04d-%02d-01 00:00:00', $prev_year, $prev_month);
$prev_end_date = date('Y-m-t 23:59:59', strtotime($prev_start_date));

// 5-1. 1등 출석왕 (최적화 - 서브쿼리를 JOIN으로 변경)
$first_login_sql = "
    SELECT
        p.mb_id,
        m.mb_name,
        m.mb_nick,
        COUNT(*) as first_count
    FROM {$g5['point_table']} p
    JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
    JOIN (
        SELECT DATE(po_datetime) as login_date, MIN(TIME(po_datetime)) as first_time
        FROM {$g5['point_table']}
        WHERE po_content LIKE '%첫로그인%'
        AND po_datetime >= '{$start_date}'
        AND po_datetime <= '{$end_date}'
        GROUP BY DATE(po_datetime)
    ) first_times ON DATE(p.po_datetime) = first_times.login_date
                  AND TIME(p.po_datetime) = first_times.first_time
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
    GROUP BY p.mb_id
    ORDER BY first_count DESC, m.mb_name ASC
    LIMIT 5
";
$first_login_result = sql_query($first_login_sql);
$first_login_members = array();
while ($row = sql_fetch_array($first_login_result)) {
    $first_login_members[] = $row;
}

// 5-2. 최다 출석자 (이번 달 총 출석 일수)
$most_attendance_sql = "
    SELECT
        p.mb_id,
        m.mb_name,
        m.mb_nick,
        COUNT(DISTINCT DATE(p.po_datetime)) as attend_days
    FROM {$g5['point_table']} p
    JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
    GROUP BY p.mb_id
    ORDER BY attend_days DESC
    LIMIT 5
";
$most_attendance_result = sql_query($most_attendance_sql);
$most_attendance_members = array();
while ($row = sql_fetch_array($most_attendance_result)) {
    $most_attendance_members[] = $row;
}

// 전월 최다 출석자 (랭킹 비교용)
$prev_most_attendance_sql = "
    SELECT
        p.mb_id,
        COUNT(DISTINCT DATE(p.po_datetime)) as attend_days
    FROM {$g5['point_table']} p
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$prev_start_date}'
    AND p.po_datetime <= '{$prev_end_date}'
    GROUP BY p.mb_id
    ORDER BY attend_days DESC
";
$prev_most_result = sql_query($prev_most_attendance_sql);
$prev_rank_map = array();
$prev_rank = 1;
while ($row = sql_fetch_array($prev_most_result)) {
    $prev_rank_map[$row['mb_id']] = $prev_rank++;
}

// 현재 랭킹에 변동 정보 추가
foreach ($most_attendance_members as $idx => &$member) {
    $current_rank = $idx + 1;
    if (isset($prev_rank_map[$member['mb_id']])) {
        $prev_rank = $prev_rank_map[$member['mb_id']];
        $member['rank_change'] = $prev_rank - $current_rank;
    } else {
        $member['rank_change'] = 'new';
    }
}
unset($member);

// 5-3. 이달의 개근상 (매일 출석한 회원)
$perfect_attendance_sql = "
    SELECT
        p.mb_id,
        m.mb_name,
        m.mb_nick,
        COUNT(DISTINCT DATE(p.po_datetime)) as attend_days
    FROM {$g5['point_table']} p
    JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
    GROUP BY p.mb_id
    HAVING attend_days >= {$today_day}
    ORDER BY m.mb_name
";
$perfect_result = sql_query($perfect_attendance_sql);
$perfect_attendance_members = array();
while ($row = sql_fetch_array($perfect_result)) {
    $perfect_attendance_members[] = $row;
}

// 5-4. 최장 연속 출석자 계산 (최적화 - 한번에 모든 데이터 조회)
// 활성 회원들의 모든 출석 기록을 한번에 가져옴
$all_login_sql = "
    SELECT p.mb_id, m.mb_name, m.mb_nick, DATE(p.po_datetime) as login_date
    FROM {$g5['point_table']} p
    JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= DATE_SUB(NOW(), INTERVAL 180 DAY)
    GROUP BY p.mb_id, DATE(p.po_datetime)
    ORDER BY p.mb_id, login_date DESC
";
$all_login_result = sql_query($all_login_sql);

// 회원별 출석 날짜 그룹핑
$member_logins = array();
$member_info = array();
while ($row = sql_fetch_array($all_login_result)) {
    $mb_id = $row['mb_id'];
    if (!isset($member_logins[$mb_id])) {
        $member_logins[$mb_id] = array();
        $member_info[$mb_id] = array(
            'mb_name' => $row['mb_name'],
            'mb_nick' => $row['mb_nick']
        );
    }
    $member_logins[$mb_id][] = $row['login_date'];
}

// 연속 출석 일수 계산 (PHP에서 한번에 처리)
$consecutive_members = array();
foreach ($member_logins as $mb_id => $dates) {
    if (count($dates) < 3) continue;

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

    if ($max_consecutive >= 3) {
        $consecutive_members[] = array(
            'mb_id' => $mb_id,
            'mb_name' => $member_info[$mb_id]['mb_name'],
            'mb_nick' => $member_info[$mb_id]['mb_nick'],
            'consecutive_days' => $max_consecutive
        );
    }
}
usort($consecutive_members, function($a, $b) {
    return $b['consecutive_days'] - $a['consecutive_days'];
});
$consecutive_members = array_slice($consecutive_members, 0, 5);

// 5-5. 새벽 출석자 (04:30 ~ 05:00 출석)
$dawn_login_sql = "
    SELECT
        p.mb_id,
        m.mb_name,
        m.mb_nick,
        COUNT(*) as dawn_count
    FROM {$g5['point_table']} p
    JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
    AND TIME(p.po_datetime) >= '04:30:00'
    AND TIME(p.po_datetime) < '05:00:00'
    GROUP BY p.mb_id
    ORDER BY dawn_count DESC
    LIMIT 5
";
$dawn_result = sql_query($dawn_login_sql);
$dawn_members = array();
while ($row = sql_fetch_array($dawn_result)) {
    $dawn_members[] = $row;
}

// 5-6. 골든타임 출석자 (최적화 - 서브쿼리를 JOIN으로 변경)
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$golden_time_sql = "
    SELECT
        p.mb_id,
        m.mb_name,
        m.mb_nick,
        p.po_datetime as login_time
    FROM {$g5['point_table']} p
    JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
    JOIN (
        SELECT MIN(TIME(po_datetime)) as first_time
        FROM {$g5['point_table']}
        WHERE po_content LIKE '%첫로그인%'
        AND po_datetime >= '{$today_start}'
        AND po_datetime <= '{$today_end}'
    ) gt ON TIME(p.po_datetime) = gt.first_time
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$today_start}'
    AND p.po_datetime <= '{$today_end}'
    ORDER BY m.mb_name ASC
";
$golden_result = sql_query($golden_time_sql);
$golden_time_members = array();
while ($row = sql_fetch_array($golden_result)) {
    $golden_time_members[] = $row;
}
$golden_time_member = count($golden_time_members) > 0 ? $golden_time_members[0] : null;

// 프로필 이미지 가져오기 함수
function getProfileImage($mb_id) {
    return get_profile_image_url($mb_id);
}

?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script> window.FontAwesomeConfig = { autoReplaceSvg: 'nest'};</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { display: none;}
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; }
        .golden-gradient { background: linear-gradient(135deg, #C4A6E8, #B19CD9, #9B7AC7); }
        .silver-gradient { background: linear-gradient(135deg, #E8E2F7, #D1C7E3, #B19CD9); }
        .bronze-gradient { background: linear-gradient(135deg, #D1C7E3, #B19CD9, #8B5CF6); }
        .shadow-warm { box-shadow: 0 4px 20px rgba(177, 156, 217, 0.15); }
        .shadow-divine { box-shadow: 0 8px 32px rgba(196, 166, 232, 0.25); }
        .crown-shadow { box-shadow: 0 0 30px rgba(196, 166, 232, 0.4); }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'warm-beige': '#EEF3F8',
                        'soft-lavender': '#E8E2F7',
                        'grace-green': '#6B705C',
                        'lilac': '#B19CD9',
                        'deep-purple': '#6B46C1',
                        'divine-lilac': '#C4A6E8'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#EEF3F8]">

<header id="header" class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
            <img src="../img/logo.png" alt="성산교회 로고" class="w-8 h-8 rounded-lg object-cover">
            <h1 class="text-lg font-semibold text-grace-green">명예의 전당</h1>
        </div>
        <i class="fa-solid fa-trophy text-divine-lilac text-xl"></i>
    </div>
</header>

<main id="main-content" class="pt-16 pb-20">

    <section id="month-selector" class="px-4 py-4 bg-white border-b border-soft-lavender">
        <div class="flex items-center justify-between">
            <button onclick="changeMonth(-1)" class="w-10 h-10 flex items-center justify-center rounded-full bg-soft-lavender">
                <i class="fa-solid fa-chevron-left text-lilac"></i>
            </button>

            <div class="flex items-center gap-2">
                <i class="fa-solid fa-calendar text-lilac"></i>
                <h2 class="text-lg font-semibold text-grace-green"><?php echo $current_year; ?>년 <?php echo $current_month; ?>월</h2>
                <i class="fa-solid fa-chevron-down text-lilac text-sm"></i>
            </div>

            <button onclick="changeMonth(1)" class="w-10 h-10 flex items-center justify-center rounded-full bg-soft-lavender">
                <i class="fa-solid fa-chevron-right text-lilac"></i>
            </button>
        </div>
    </section>

    <section id="blessing-quote" class="mx-4 my-4 bg-gradient-to-r from-divine-lilac/20 to-soft-lavender rounded-2xl p-4 border border-divine-lilac/30">
        <div class="text-center">
            <i class="fa-solid fa-quote-left text-divine-lilac text-lg mb-2"></i>
            <p class="text-sm font-medium text-deep-purple leading-relaxed mb-2">
                "잘 하였도다 착하고 충성된 종아 네가 작은 일에 충성하였으매 내가 많은 것으로 네게 맡기리니 네 주인의 즐거움에 참예할찌어다"
            </p>
            <p class="text-xs text-lilac">마태복음 25:21</p>
        </div>
    </section>



    <section id="hall-of-faith" class="px-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-star text-yellow-500 text-lg"></i>
                <h3 class="text-lg font-semibold text-grace-green">베스트 성산인</h3>
                <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full"><?php echo number_format($best_member_point); ?>점 이상</span>
            </div>
            <span class="text-xs text-gray-500">총 <?php echo $total_best; ?>명</span>
        </div>

        <div class="space-y-3">
            <?php if (count($best_members) > 0): ?>
                <?php foreach ($best_members as $member): ?>
                <?php
                    // 메달 결정 (1,2,3등만)
                    $medal = '';
                    $badge_color = 'bg-yellow-500';
                    if ($member['rank'] == 1) $medal = '🥇';
                    elseif ($member['rank'] == 2) $medal = '🥈';
                    elseif ($member['rank'] == 3) $medal = '🥉';
                ?>
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-4 shadow-warm flex items-center gap-4 border border-purple-100">
                    <div class="relative">
                        <img src="<?php echo $member['avatar']; ?>" class="w-14 h-14 rounded-full object-cover border-2 border-yellow-400">
                        <?php if ($medal): ?>
                        <div class="absolute -top-2 -right-2 text-xl"><?php echo $medal; ?></div>
                        <?php else: ?>
                        <div class="absolute -bottom-1 -right-1 w-5 h-5 <?php echo $badge_color; ?> rounded-full flex items-center justify-center">
                            <span class="text-white text-xs font-bold"><?php echo $member['rank']; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-semibold text-grace-green"><?php echo $member['name']; ?></h4>
                            <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full"><?php echo $member['nick']; ?></span>
                            <i class="fa-solid fa-star text-yellow-500 text-xs"></i>
                        </div>
                        <div class="flex items-center gap-1 mb-2">
                            <i class="fa-solid fa-cross text-yellow-600 text-xs"></i>
                            <span class="text-sm font-bold text-yellow-600"><?php echo number_format($member['points']); ?>점</span>
                        </div>
                        <div class="flex gap-1">
                            <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full">글 <?php echo $member['post_count']; ?>개</span>
                            <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full">댓글 <?php echo $member['comment_count']; ?>개</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl p-8 shadow-warm text-center">
                    <i class="fa-solid fa-star text-gray-300 text-4xl mb-3"></i>
                    <p class="text-sm text-gray-400">이번 달 <?php echo number_format($best_member_point); ?>점 이상 획득한 회원이 없습니다.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- 베스트 감사인 섹션 -->
    <?php if (count($best_gratitude_members) > 0): ?>
    <section id="best-gratitude" class="px-4 mt-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-book-heart text-pink-500 text-lg"></i>
                <h3 class="text-lg font-semibold text-grace-green">이달의 베스트 감사인</h3>
            </div>
            <span class="text-xs bg-pink-100 text-pink-600 px-2 py-1 rounded-full">매일 작성</span>
        </div>

        <div class="bg-gradient-to-r from-pink-50 to-purple-50 rounded-2xl p-4 border border-pink-200">
            <p class="text-sm text-grace-green/70 mb-3">
                <i class="fa-solid fa-hands-praying text-pink-400 mr-1"></i>
                한번도 빠지지 않고 감사일기를 쓴 분들입니다
            </p>
            <div class="space-y-3">
                <?php foreach ($best_gratitude_members as $idx => $gmember): ?>
                <div class="flex items-center gap-3 bg-white/80 rounded-xl p-3">
                    <div class="relative">
                        <img src="<?php echo getProfileImage($gmember['mb_id']); ?>" class="w-12 h-12 rounded-full object-cover border-2 border-pink-300">
                        <?php if ($idx === 0): ?>
                        <div class="absolute -top-1 -right-1 text-lg">📖</div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-grace-green"><?php echo get_text($gmember['mb_name']); ?></h4>
                        <p class="text-xs text-pink-600">
                            <i class="fa-solid fa-pen-fancy mr-1"></i>
                            총 <?php echo $gmember['total_diaries']; ?>편 작성
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs bg-pink-100 text-pink-700 px-2 py-1 rounded-full font-medium">
                            <?php echo $gmember['diary_days']; ?>일 연속
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 출석 우수자 통계 섹션 -->
    <section id="attendance-stats" class="px-4 mt-6">
        <div class="flex items-center gap-2 mb-4">
            <i class="fa-solid fa-calendar-check text-divine-lilac text-lg"></i>
            <h3 class="text-lg font-semibold text-grace-green">이달의 출석 성산인</h3>
        </div>

        <!-- 골든타임 출석자 (오늘의 첫 출석자 - 공동 골든타임 지원) - 특별 표시 -->
        <?php if (count($golden_time_members) > 0 && $current_year == date('Y') && $current_month == date('n')): ?>
        <div class="bg-gradient-to-r from-yellow-400 via-orange-400 to-yellow-500 rounded-2xl p-4 mb-4 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-full -mr-10 -mt-10"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/10 rounded-full -ml-8 -mb-8"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs bg-white/30 text-white px-2 py-0.5 rounded-full font-medium">오늘의 골든타임</span>
                    <?php if (count($golden_time_members) > 1): ?>
                    <span class="text-xs bg-white/20 text-white px-2 py-0.5 rounded-full">공동 <?php echo count($golden_time_members); ?>명</span>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($golden_time_members as $gtm): ?>
                    <div class="flex items-center gap-3 bg-white/20 rounded-xl p-2 pr-4">
                        <div class="relative">
                            <div class="w-12 h-12 bg-white rounded-full p-0.5 shadow-lg">
                                <img src="<?php echo getProfileImage($gtm['mb_id']); ?>" class="w-full h-full rounded-full object-cover">
                            </div>
                            <div class="absolute -top-1 -right-1 w-5 h-5 bg-yellow-300 rounded-full flex items-center justify-center shadow-md animate-pulse">
                                <i class="fa-solid fa-bolt text-yellow-700 text-xs"></i>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-white"><?php echo get_text($gtm['mb_name']); ?></h4>
                            <p class="text-white/80 text-xs"><?php echo date('H:i:s', strtotime($gtm['login_time'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 출석 통계 카드들 -->
        <div class="grid grid-cols-1 gap-3">

            <!-- 1등 출석왕 -->
            <div class="bg-white rounded-xl p-4 shadow-warm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-clock text-amber-500 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-grace-green">1등 출석왕</h4>
                    <span class="text-xs text-gray-400 ml-auto">매일 가장 먼저 출석</span>
                </div>
                <?php if (count($first_login_members) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($first_login_members as $idx => $member): ?>
                    <div class="flex items-center gap-3 <?php echo $idx === 0 ? 'bg-amber-50 rounded-lg p-2 -mx-2' : ''; ?>">
                        <span class="w-5 h-5 <?php echo $idx === 0 ? 'bg-amber-500' : 'bg-gray-200'; ?> rounded-full flex items-center justify-center text-xs <?php echo $idx === 0 ? 'text-white font-bold' : 'text-gray-500'; ?>"><?php echo $idx + 1; ?></span>
                        <img src="<?php echo getProfileImage($member['mb_id']); ?>" class="w-8 h-8 rounded-full object-cover">
                        <span class="flex-1 text-sm <?php echo $idx === 0 ? 'font-semibold text-grace-green' : 'text-gray-600'; ?>"><?php echo get_text($member['mb_name']); ?></span>
                        <span class="text-sm <?php echo $idx === 0 ? 'text-amber-600 font-bold' : 'text-gray-500'; ?>"><?php echo $member['first_count']; ?>회</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-4">아직 데이터가 없습니다</p>
                <?php endif; ?>
            </div>

            <!-- 최다 출석자 (랭킹 변동 포함) -->
            <div class="bg-white rounded-xl p-4 shadow-warm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-check-double text-green-500 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-grace-green">최다 출석자</h4>
                    <span class="text-xs text-gray-400 ml-auto">이번 달 총 출석 일수</span>
                </div>
                <?php if (count($most_attendance_members) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($most_attendance_members as $idx => $member): ?>
                    <div class="flex items-center gap-3 <?php echo $idx === 0 ? 'bg-green-50 rounded-lg p-2 -mx-2' : ''; ?>">
                        <span class="w-5 h-5 <?php echo $idx === 0 ? 'bg-green-500' : 'bg-gray-200'; ?> rounded-full flex items-center justify-center text-xs <?php echo $idx === 0 ? 'text-white font-bold' : 'text-gray-500'; ?>"><?php echo $idx + 1; ?></span>
                        <img src="<?php echo getProfileImage($member['mb_id']); ?>" class="w-8 h-8 rounded-full object-cover">
                        <span class="flex-1 text-sm <?php echo $idx === 0 ? 'font-semibold text-grace-green' : 'text-gray-600'; ?>"><?php echo get_text($member['mb_name']); ?></span>
                        <!-- 랭킹 변동 표시 -->
                        <?php if ($member['rank_change'] === 'new'): ?>
                        <span class="text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded font-medium">NEW</span>
                        <?php elseif ($member['rank_change'] > 0): ?>
                        <span class="text-xs text-green-500 font-medium"><i class="fa-solid fa-caret-up"></i> <?php echo $member['rank_change']; ?></span>
                        <?php elseif ($member['rank_change'] < 0): ?>
                        <span class="text-xs text-red-500 font-medium"><i class="fa-solid fa-caret-down"></i> <?php echo abs($member['rank_change']); ?></span>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">-</span>
                        <?php endif; ?>
                        <span class="text-sm <?php echo $idx === 0 ? 'text-green-600 font-bold' : 'text-gray-500'; ?>"><?php echo $member['attend_days']; ?>일</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-4">아직 데이터가 없습니다</p>
                <?php endif; ?>
            </div>

            <!-- 최장 연속 출석자 -->
            <div class="bg-white rounded-xl p-4 shadow-warm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-fire text-red-500 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-grace-green">연속 출석 챔피언</h4>
                    <span class="text-xs text-gray-400 ml-auto">최장 연속 출석 기록</span>
                </div>
                <?php if (count($consecutive_members) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($consecutive_members as $idx => $member): ?>
                    <div class="flex items-center gap-3 <?php echo $idx === 0 ? 'bg-red-50 rounded-lg p-2 -mx-2' : ''; ?>">
                        <span class="w-5 h-5 <?php echo $idx === 0 ? 'bg-red-500' : 'bg-gray-200'; ?> rounded-full flex items-center justify-center text-xs <?php echo $idx === 0 ? 'text-white font-bold' : 'text-gray-500'; ?>"><?php echo $idx + 1; ?></span>
                        <img src="<?php echo getProfileImage($member['mb_id']); ?>" class="w-8 h-8 rounded-full object-cover">
                        <span class="flex-1 text-sm <?php echo $idx === 0 ? 'font-semibold text-grace-green' : 'text-gray-600'; ?>"><?php echo get_text($member['mb_name']); ?></span>
                        <div class="flex items-center gap-1">
                            <?php if ($member['consecutive_days'] >= 30): ?>
                            <i class="fa-solid fa-fire text-red-500 text-xs"></i>
                            <?php elseif ($member['consecutive_days'] >= 7): ?>
                            <i class="fa-solid fa-fire text-orange-400 text-xs"></i>
                            <?php endif; ?>
                            <span class="text-sm <?php echo $idx === 0 ? 'text-red-600 font-bold' : 'text-gray-500'; ?>"><?php echo $member['consecutive_days']; ?>일</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-4">3일 이상 연속 출석자가 없습니다</p>
                <?php endif; ?>
            </div>

            <!-- 이달의 개근상 -->
            <div class="bg-white rounded-xl p-4 shadow-warm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-medal text-purple-500 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-grace-green">이달의 개근상</h4>
                    <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded-full ml-auto"><?php echo count($perfect_attendance_members); ?>명</span>
                </div>
                <?php if (count($perfect_attendance_members) > 0): ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($perfect_attendance_members as $member): ?>
                    <div class="flex items-center gap-2 bg-purple-50 rounded-full pl-1 pr-3 py-1">
                        <img src="<?php echo getProfileImage($member['mb_id']); ?>" class="w-6 h-6 rounded-full object-cover">
                        <span class="text-sm text-purple-700 font-medium"><?php echo get_text($member['mb_name']); ?></span>
                        <i class="fa-solid fa-certificate text-purple-400 text-xs"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-4">
                    <?php if ($today_day < $days_in_month): ?>
                    아직 월말이 아닙니다 (<?php echo $today_day; ?>/<?php echo $days_in_month; ?>일)
                    <?php else: ?>
                    이번 달 개근자가 없습니다
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>

            <!-- 새벽 출석자 -->
            <div class="bg-white rounded-xl p-4 shadow-warm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-sun text-indigo-500 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-grace-green">새벽 출석자</h4>
                    <span class="text-xs text-gray-400 ml-auto">04:30 ~ 05:00 출석</span>
                </div>
                <?php if (count($dawn_members) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($dawn_members as $idx => $member): ?>
                    <div class="flex items-center gap-3 <?php echo $idx === 0 ? 'bg-indigo-50 rounded-lg p-2 -mx-2' : ''; ?>">
                        <span class="w-5 h-5 <?php echo $idx === 0 ? 'bg-indigo-500' : 'bg-gray-200'; ?> rounded-full flex items-center justify-center text-xs <?php echo $idx === 0 ? 'text-white font-bold' : 'text-gray-500'; ?>"><?php echo $idx + 1; ?></span>
                        <img src="<?php echo getProfileImage($member['mb_id']); ?>" class="w-8 h-8 rounded-full object-cover">
                        <span class="flex-1 text-sm <?php echo $idx === 0 ? 'font-semibold text-grace-green' : 'text-gray-600'; ?>"><?php echo get_text($member['mb_name']); ?></span>
                        <span class="text-sm <?php echo $idx === 0 ? 'text-indigo-600 font-bold' : 'text-gray-500'; ?>"><?php echo $member['dawn_count']; ?>회</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-4">새벽 출석자가 없습니다</p>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <section id="monthly-stats" class="mx-4 mt-6 bg-white rounded-2xl p-4 shadow-warm">
        <h3 class="text-lg font-semibold text-grace-green mb-4">이달의 활동 통계</h3>

        <div class="grid grid-cols-2 gap-4">
            <div class="text-center p-3 bg-[#EEF3F8] rounded-xl">
                <i class="fa-solid fa-file-lines text-lilac text-xl mb-2"></i>
                <p class="text-sm text-grace-green">총 게시물 수</p>
                <p class="text-lg font-bold text-lilac"><?php echo number_format($total_posts); ?>개</p>
            </div>

            <div class="text-center p-3 bg-[#EEF3F8] rounded-xl">
                <i class="fa-solid fa-comments text-lilac text-xl mb-2"></i>
                <p class="text-sm text-grace-green">총 댓글 수</p>
                <p class="text-lg font-bold text-lilac"><?php echo number_format($total_comments); ?>개</p>
            </div>

            <div class="text-center p-3 bg-[#EEF3F8] rounded-xl">
                <i class="fa-solid fa-heart text-lilac text-xl mb-2"></i>
                <p class="text-sm text-grace-green">좋아요 총합</p>
                <p class="text-lg font-bold text-lilac"><?php echo number_format($total_amens); ?>개</p>
            </div>

            <div class="text-center p-3 bg-[#EEF3F8] rounded-xl">
                <i class="fa-solid fa-calendar-check text-lilac text-xl mb-2"></i>
                <p class="text-sm text-grace-green">회원 출석 수</p>
                <p class="text-lg font-bold text-lilac"><?php echo number_format($total_attendance); ?>회</p>
            </div>
        </div>
    </section>

</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<script>

function changeMonth(direction) {
    const currentYear = <?php echo $current_year; ?>;
    const currentMonth = <?php echo $current_month; ?>;

    let newYear = currentYear;
    let newMonth = currentMonth + direction;

    if (newMonth > 12) {
        newMonth = 1;
        newYear++;
    } else if (newMonth < 1) {
        newMonth = 12;
        newYear--;
    }

    window.location.href = '?year=' + newYear + '&month=' + newMonth;
}
</script>

<?php
// 접속자 추적을 위한 설정
if (!isset($g5['lo_location'])) {
    $g5['lo_location'] = addslashes($g5['title'] ?? '페이지');
    $g5['lo_url'] = addslashes($_SERVER['REQUEST_URI'] ?? '');
}
echo html_end();
?>
</body>
</html>
