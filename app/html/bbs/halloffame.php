<?php
include_once('./_common.php');

$g5['title'] = '명예의 전당';

// 우수 성산인 기준 점수 (여기서 변경 가능)
define('EXCELLENT_MEMBER_POINT', 1000);

// 현재 년도와 월 설정
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

// 해당 월의 시작일과 종료일 계산
$start_date = sprintf('%04d-%02d-01 00:00:00', $current_year, $current_month);
$end_date = date('Y-m-t 23:59:59', strtotime($start_date));

// ===========================
// 1. 회원별 월간 포인트 집계 및 게시글/댓글 수 계산
// ===========================

// 회원별 월간 포인트 합계
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

while ($row = sql_fetch_array($member_points_result)) {
    $mb_id = $row['mb_id'];

    // 프로필 이미지 경로
    $profile_img = G5_DATA_URL.'/member_image/'.substr($mb_id, 0, 2).'/'.$mb_id.'.gif';
    if (!file_exists(G5_DATA_PATH.'/member_image/'.substr($mb_id, 0, 2).'/'.$mb_id.'.gif')) {
        $profile_img = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
    }

    // 모든 게시판에서 회원의 게시글 수 및 댓글 수 조회
    $post_count = 0;
    $comment_count = 0;

    $board_list = sql_query("SELECT bo_table FROM {$g5['board_table']}");
    while ($board = sql_fetch_array($board_list)) {
        $bo_table = $board['bo_table'];
        $write_table = $g5['write_prefix'] . $bo_table;

        // 테이블 존재 여부 확인
        $table_check = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
        if (!sql_num_rows($table_check)) continue;

        // 게시글 수 (댓글 제외) - 해당 월만
        $post_sql = "SELECT COUNT(*) as cnt FROM {$write_table}
                     WHERE mb_id = '{$mb_id}' AND wr_is_comment = 0
                     AND wr_datetime >= '{$start_date}'
                     AND wr_datetime <= '{$end_date}'";
        $post_result = sql_fetch($post_sql);
        if ($post_result) {
            $post_count += (int)$post_result['cnt'];
        }

        // 댓글 수 - 해당 월만
        $comment_sql = "SELECT COUNT(*) as cnt FROM {$write_table}
                        WHERE mb_id = '{$mb_id}' AND wr_is_comment = 1
                        AND wr_datetime >= '{$start_date}'
                        AND wr_datetime <= '{$end_date}'";
        $comment_result = sql_fetch($comment_sql);
        if ($comment_result) {
            $comment_count += (int)$comment_result['cnt'];
        }
    }

    $all_members[] = array(
        'mb_id' => $mb_id,
        'name' => get_text($row['mb_name']),
        'nick' => get_text($row['mb_nick']),
        'points' => (int)$row['monthly_points'],
        'avatar' => $profile_img,
        'post_count' => $post_count,
        'comment_count' => $comment_count
    );
}

// ===========================
// 2. Top 3 회원 선정
// ===========================
$top_members = array();
for ($i = 0; $i < min(3, count($all_members)); $i++) {
    $top_members[] = array_merge($all_members[$i], array('rank' => $i + 1));
}

// ===========================
// 3. 우수 성산인 (Top 3 제외, 기준 점수 이상)
// ===========================
$excellent_members = array();
$rank = 4;
for ($i = 3; $i < count($all_members); $i++) {
    if ($all_members[$i]['points'] >= EXCELLENT_MEMBER_POINT) {
        $excellent_members[] = array_merge($all_members[$i], array('rank' => $rank));
        $rank++;
    }
}
$total_excellent = count($excellent_members);

// ===========================
// 4. 이달의 활동 통계
// ===========================

// 총 게시물 수 (해당 월)
$total_posts = 0;
$board_list = sql_query("SELECT bo_table FROM {$g5['board_table']}");
while ($board = sql_fetch_array($board_list)) {
    $bo_table = $board['bo_table'];
    $write_table = $g5['write_prefix'] . $bo_table;

    $table_check = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
    if (!sql_num_rows($table_check)) continue;

    $posts_sql = "SELECT COUNT(*) as cnt FROM {$write_table}
                  WHERE wr_is_comment = 0
                  AND wr_datetime >= '{$start_date}'
                  AND wr_datetime <= '{$end_date}'";
    $posts_result = sql_fetch($posts_sql);
    if ($posts_result) {
        $total_posts += (int)$posts_result['cnt'];
    }
}

// 총 댓글 수 (해당 월)
$total_comments = 0;
$board_list = sql_query("SELECT bo_table FROM {$g5['board_table']}");
while ($board = sql_fetch_array($board_list)) {
    $bo_table = $board['bo_table'];
    $write_table = $g5['write_prefix'] . $bo_table;

    $table_check = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
    if (!sql_num_rows($table_check)) continue;

    $comments_sql = "SELECT COUNT(*) as cnt FROM {$write_table}
                     WHERE wr_is_comment = 1
                     AND wr_datetime >= '{$start_date}'
                     AND wr_datetime <= '{$end_date}'";
    $comments_result = sql_fetch($comments_sql);
    if ($comments_result) {
        $total_comments += (int)$comments_result['cnt'];
    }
}

// 아멘 총합 (해당 월) - wr_good 컬럼 합계
$total_amens = 0;
$board_list = sql_query("SELECT bo_table FROM {$g5['board_table']}");
while ($board = sql_fetch_array($board_list)) {
    $bo_table = $board['bo_table'];
    $write_table = $g5['write_prefix'] . $bo_table;

    $table_check = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
    if (!sql_num_rows($table_check)) continue;

    // wr_good 컬럼이 있는지 확인
    $column_check = sql_query("SHOW COLUMNS FROM {$write_table} WHERE Field = 'wr_good'", false);
    if (!sql_num_rows($column_check)) continue;

    $amens_sql = "SELECT COALESCE(SUM(wr_good), 0) as total FROM {$write_table}
                  WHERE wr_datetime >= '{$start_date}'
                  AND wr_datetime <= '{$end_date}'";
    $amens_result = sql_fetch($amens_sql);
    if ($amens_result) {
        $total_amens += (int)$amens_result['total'];
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
// 5. 출석 우수자 통계
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

// 5-1. 1등 출석왕 (매일 가장 먼저 출석한 횟수)
$first_login_sql = "
    SELECT
        p.mb_id,
        m.mb_name,
        m.mb_nick,
        COUNT(*) as first_count
    FROM {$g5['point_table']} p
    JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
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

// 5-4. 최장 연속 출석자 계산
function getConsecutiveDays($mb_id, $g5) {
    $sql = "
        SELECT DISTINCT DATE(po_datetime) as login_date
        FROM {$g5['point_table']}
        WHERE mb_id = '{$mb_id}'
        AND po_content LIKE '%첫로그인%'
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
    SELECT DISTINCT p.mb_id, m.mb_name, m.mb_nick
    FROM {$g5['point_table']} p
    JOIN {$g5['member_table']} m ON p.mb_id = m.mb_id
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= DATE_SUB(NOW(), INTERVAL 60 DAY)
";
$active_result = sql_query($active_members_sql);
$consecutive_members = array();
while ($row = sql_fetch_array($active_result)) {
    $consecutive_days = getConsecutiveDays($row['mb_id'], $g5);
    if ($consecutive_days >= 3) {
        $consecutive_members[] = array(
            'mb_id' => $row['mb_id'],
            'mb_name' => $row['mb_name'],
            'mb_nick' => $row['mb_nick'],
            'consecutive_days' => $consecutive_days
        );
    }
}
usort($consecutive_members, function($a, $b) {
    return $b['consecutive_days'] - $a['consecutive_days'];
});
$consecutive_members = array_slice($consecutive_members, 0, 5);

// 5-5. 새벽 기도왕 (오전 5~7시 출석)
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
    AND HOUR(p.po_datetime) >= 5
    AND HOUR(p.po_datetime) < 7
    GROUP BY p.mb_id
    ORDER BY dawn_count DESC
    LIMIT 5
";
$dawn_result = sql_query($dawn_login_sql);
$dawn_members = array();
while ($row = sql_fetch_array($dawn_result)) {
    $dawn_members[] = $row;
}

// 5-6. 골든타임 출석자 (오늘의 첫 출석자)
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
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$today_start}'
    AND p.po_datetime <= '{$today_end}'
    ORDER BY p.po_datetime ASC
    LIMIT 1
";
$golden_result = sql_fetch($golden_time_sql);
$golden_time_member = $golden_result ? $golden_result : null;

// 프로필 이미지 가져오기 함수
function getProfileImage($mb_id) {
    $profile_img = G5_DATA_URL.'/member_image/'.substr($mb_id, 0, 2).'/'.$mb_id.'.gif';
    if (!file_exists(G5_DATA_PATH.'/member_image/'.substr($mb_id, 0, 2).'/'.$mb_id.'.gif')) {
        $profile_img = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
    }
    return $profile_img;
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



    <section id="top-three-section" class="px-4 mb-6">
        <div class="flex items-center gap-2 mb-4">
            <i class="fa-solid fa-crown text-divine-lilac text-lg"></i>
            <h3 class="text-lg font-semibold text-grace-green">이달의 베스트 성산인</h3>
        </div>

        <?php if (count($top_members) > 0): ?>
        <div class="flex gap-3 overflow-x-auto pb-2">
            <?php foreach ($top_members as $idx => $member): ?>
            <?php
                $border_class = '';
                $gradient_class = '';
                $badge_color = '';

                if ($member['rank'] == 1) {
                    $border_class = 'border-2 border-divine-lilac/30';
                    $gradient_class = 'golden-gradient';
                    $badge_color = 'bg-divine-lilac';
                    $shadow_class = 'shadow-divine';
                    $crown_class = 'crown-shadow';
                } elseif ($member['rank'] == 2) {
                    $border_class = 'border border-gray-200';
                    $gradient_class = 'silver-gradient';
                    $badge_color = 'bg-lilac';
                    $shadow_class = 'shadow-warm';
                    $crown_class = '';
                } else {
                    $border_class = 'border border-lilac/20';
                    $gradient_class = 'bronze-gradient';
                    $badge_color = 'bg-deep-purple';
                    $shadow_class = 'shadow-warm';
                    $crown_class = '';
                }

                $width_class = $member['rank'] == 1 ? 'min-w-[140px]' : 'min-w-[130px]';
                $img_size = $member['rank'] == 1 ? 'w-20 h-20' : 'w-18 h-18';
            ?>

            <div class="<?php echo $width_class; ?> bg-white rounded-2xl p-4 <?php echo $shadow_class; ?> <?php echo $border_class; ?>">
                <div class="text-center">
                    <div class="relative mb-3">
                        <div class="<?php echo $img_size; ?> <?php echo $gradient_class; ?> rounded-full p-1 mx-auto <?php echo $crown_class; ?>">
                            <img src="<?php echo $member['avatar']; ?>" class="w-full h-full rounded-full object-cover">
                        </div>
                        <?php if ($member['rank'] == 1): ?>
                        <div class="absolute -top-2 left-1/2 transform -translate-x-1/2">
                            <i class="fa-solid fa-crown text-divine-lilac text-xl crown-shadow"></i>
                        </div>
                        <?php endif; ?>
                        <div class="absolute -bottom-1 -right-1 <?php echo $member['rank'] == 1 ? 'w-6 h-6' : 'w-5 h-5'; ?> <?php echo $badge_color; ?> rounded-full flex items-center justify-center">
                            <span class="text-white text-xs font-bold"><?php echo $member['rank']; ?></span>
                        </div>
                    </div>
                    <h4 class="font-semibold text-grace-green text-sm mb-1"><?php echo $member['name']; ?></h4>
                    <p class="text-xs text-gray-500 mb-2"><?php echo $member['nick']; ?></p>
                    <div class="flex items-center justify-center gap-1 mb-2">
                        <i class="fa-solid fa-cross text-<?php echo $member['rank'] == 1 ? 'divine-lilac' : ($member['rank'] == 2 ? 'lilac' : 'deep-purple'); ?> text-xs"></i>
                        <span class="text-sm font-bold text-<?php echo $member['rank'] == 1 ? 'divine-lilac' : ($member['rank'] == 2 ? 'lilac' : 'deep-purple'); ?>"><?php echo number_format($member['points']); ?>점</span>
                    </div>
                    <div class="flex flex-wrap gap-1 justify-center">
                        <span class="text-xs bg-<?php echo $member['rank'] == 1 ? 'divine-lilac' : ($member['rank'] == 2 ? 'soft-lavender' : 'deep-purple'); ?>/20 text-deep-purple px-2 py-1 rounded-full">글 <?php echo $member['post_count']; ?>개</span>
                        <span class="text-xs bg-<?php echo $member['rank'] == 1 ? 'divine-lilac' : ($member['rank'] == 2 ? 'soft-lavender' : 'deep-purple'); ?>/20 text-deep-purple px-2 py-1 rounded-full">댓글 <?php echo $member['comment_count']; ?>개</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-2xl p-8 shadow-warm text-center">
            <i class="fa-solid fa-crown text-gray-300 text-5xl mb-3"></i>
            <p class="text-sm text-gray-400">이번 달 활동 내역이 없습니다.</p>
        </div>
        <?php endif; ?>
    </section>

    <section id="hall-of-faith" class="px-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-hands-praying text-lilac text-lg"></i>
                <h3 class="text-lg font-semibold text-grace-green">우수 성산인</h3>
                <span class="text-xs bg-lilac/20 text-deep-purple px-2 py-1 rounded-full"><?php echo number_format(EXCELLENT_MEMBER_POINT); ?>점 이상</span>
            </div>
            <span class="text-xs text-gray-500">총 <?php echo $total_excellent; ?>명</span>
        </div>

        <div class="space-y-3">
            <?php if (count($excellent_members) > 0): ?>
                <?php foreach ($excellent_members as $member): ?>
                <div class="bg-white rounded-xl p-4 shadow-warm flex items-center gap-4">
                    <div class="relative">
                        <img src="<?php echo $member['avatar']; ?>" class="w-14 h-14 rounded-full object-cover">
                        <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-lilac rounded-full flex items-center justify-center">
                            <span class="text-white text-xs font-bold"><?php echo $member['rank']; ?></span>
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-semibold text-grace-green"><?php echo $member['name']; ?></h4>
                            <span class="text-xs bg-soft-lavender text-deep-purple px-2 py-1 rounded-full"><?php echo $member['nick']; ?></span>
                        </div>
                        <div class="flex items-center gap-1 mb-2">
                            <i class="fa-solid fa-cross text-lilac text-xs"></i>
                            <span class="text-sm font-semibold text-lilac"><?php echo number_format($member['points']); ?>점</span>
                        </div>
                        <div class="flex gap-1">
                            <span class="text-xs bg-soft-lavender text-deep-purple px-2 py-1 rounded-full">글 <?php echo $member['post_count']; ?>개</span>
                            <span class="text-xs bg-soft-lavender text-deep-purple px-2 py-1 rounded-full">댓글 <?php echo $member['comment_count']; ?>개</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl p-8 shadow-warm text-center">
                    <i class="fa-solid fa-trophy text-gray-300 text-4xl mb-3"></i>
                    <p class="text-sm text-gray-400">이번 달 <?php echo number_format(EXCELLENT_MEMBER_POINT); ?>점 이상 획득한 회원이 없습니다.</p>
                </div>
            <?php endif; ?>
        </div>

        <button class="w-full mt-4 py-3 bg-lilac/10 text-deep-purple font-medium rounded-xl border border-lilac/20">
            모든 우수자 보기 (<?php echo $total_excellent; ?>명)
        </button>
    </section>

    <!-- 출석 우수자 통계 섹션 -->
    <section id="attendance-stats" class="px-4 mt-6">
        <div class="flex items-center gap-2 mb-4">
            <i class="fa-solid fa-calendar-check text-divine-lilac text-lg"></i>
            <h3 class="text-lg font-semibold text-grace-green">이달의 출석 성산인</h3>
        </div>

        <!-- 골든타임 출석자 (오늘의 첫 출석자) - 특별 표시 -->
        <?php if ($golden_time_member && $current_year == date('Y') && $current_month == date('n')): ?>
        <div class="bg-gradient-to-r from-yellow-400 via-orange-400 to-yellow-500 rounded-2xl p-4 mb-4 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-full -mr-10 -mt-10"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/10 rounded-full -ml-8 -mb-8"></div>
            <div class="relative flex items-center gap-4">
                <div class="relative">
                    <div class="w-16 h-16 bg-white rounded-full p-1 shadow-lg">
                        <img src="<?php echo getProfileImage($golden_time_member['mb_id']); ?>" class="w-full h-full rounded-full object-cover">
                    </div>
                    <div class="absolute -top-1 -right-1 w-7 h-7 bg-yellow-300 rounded-full flex items-center justify-center shadow-md animate-pulse">
                        <i class="fa-solid fa-bolt text-yellow-700 text-sm"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs bg-white/30 text-white px-2 py-0.5 rounded-full font-medium">오늘의 골든타임</span>
                    </div>
                    <h4 class="font-bold text-white text-lg"><?php echo get_text($golden_time_member['mb_name']); ?></h4>
                    <p class="text-white/80 text-sm"><?php echo date('H:i:s', strtotime($golden_time_member['login_time'])); ?> 첫 출석!</p>
                </div>
                <div class="text-right">
                    <i class="fa-solid fa-star text-yellow-200 text-3xl"></i>
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

            <!-- 새벽 기도왕 -->
            <div class="bg-white rounded-xl p-4 shadow-warm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-sun text-indigo-500 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-grace-green">새벽 기도왕</h4>
                    <span class="text-xs text-gray-400 ml-auto">오전 5~7시 출석</span>
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

</body>
</html>
