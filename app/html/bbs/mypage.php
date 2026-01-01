<?php
include_once('./_common.php');

// 로그인 체크
if (!$is_member) {
    alert('로그인이 필요합니다.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/mypage.php'));
}

$g5['title'] = '내 정보';

// 뱃지 시스템 로드
if (file_exists(G5_EXTEND_PATH.'/badge_system.extend.php')) {
    include_once(G5_EXTEND_PATH.'/badge_system.extend.php');
}

// 역대 뱃지 조회
$historical_badges = array();
$badge_stats = array();
if (function_exists('get_member_badges')) {
    $historical_badges = get_member_badges($member['mb_id']);
    $badge_stats = get_member_badge_stats($member['mb_id']);
}

// 회원 정보 가져오기
$mb = get_member($member['mb_id']);

// 가입 경과 기간 계산
$join_date = strtotime($mb['mb_datetime']);
$now = G5_SERVER_TIME;
$diff = $now - $join_date;
$years = floor($diff / (365 * 60 * 60 * 24));
$months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));

// 게시물 수 조회 - gallery 게시판의 게시물
$post_count_sql = "SELECT COUNT(*) as cnt FROM {$g5['write_prefix']}gallery
                   WHERE mb_id = '{$mb['mb_id']}' AND wr_is_comment = 0";
$post_count_result = sql_fetch($post_count_sql);
$post_count = (int)$post_count_result['cnt'];

// 감사일기 수 조회 - diary 게시판
$diary_count = 0;
$diary_table_check = sql_query("SHOW TABLES LIKE '{$g5['write_prefix']}diary'", false);
if (sql_num_rows($diary_table_check)) {
    $diary_count_sql = "SELECT COUNT(*) as cnt FROM {$g5['write_prefix']}diary
                       WHERE mb_id = '{$mb['mb_id']}' AND wr_is_comment = 0";
    $diary_count_result = sql_fetch($diary_count_sql);
    $diary_count = (int)$diary_count_result['cnt'];
}

// 총 게시물 = gallery + diary
$total_post_count = $post_count + $diary_count;

// 포인트
$point = number_format($mb['mb_point']);

// 최근 로그인 일수 - 포인트 테이블에서 로그인 기록 조회
$attendance_days = 0;
$po_sql = "SELECT COUNT(DISTINCT DATE(po_datetime)) as cnt FROM {$g5['point_table']} WHERE mb_id = '{$mb['mb_id']}' AND po_content LIKE '%로그인%'";
$po_result = sql_fetch($po_sql);
if ($po_result) {
    $attendance_days = (int)$po_result['cnt'];
}

// ===========================
// 명예의 전당 뱃지 조회
// ===========================
$current_year = date('Y');
$current_month = date('n');
$start_date = sprintf('%04d-%02d-01 00:00:00', $current_year, $current_month);
$end_date = date('Y-m-t 23:59:59', strtotime($start_date));
$days_in_month = (int)date('t', strtotime($start_date));
$today_day = (int)date('j');

// 이번 달 총 일수 계산
define('EXCELLENT_MEMBER_POINT', 1000);

// 획득한 명예의 전당 뱃지들
$hof_badges = array();

// 1. 이달의 베스트 성산인 (Top 3) 체크
$top_member_sql = "
    SELECT
        m.mb_id,
        COALESCE(SUM(p.po_point), 0) as monthly_points
    FROM {$g5['member_table']} m
    LEFT JOIN {$g5['point_table']} p ON m.mb_id = p.mb_id
        AND p.po_datetime >= '{$start_date}'
        AND p.po_datetime <= '{$end_date}'
    WHERE m.mb_level > 1
    GROUP BY m.mb_id
    HAVING monthly_points > 0
    ORDER BY monthly_points DESC, m.mb_id ASC
    LIMIT 3
";
$top_result = sql_query($top_member_sql);
$top_rank = 0;
$rank_idx = 1;
while ($row = sql_fetch_array($top_result)) {
    if ($row['mb_id'] == $mb['mb_id']) {
        $top_rank = $rank_idx;
        break;
    }
    $rank_idx++;
}
if ($top_rank > 0) {
    $hof_badges['best_member'] = $top_rank;
}

// 2. 우수 성산인 (1000점 이상) 체크
$my_monthly_points_sql = "
    SELECT COALESCE(SUM(po_point), 0) as monthly_points
    FROM {$g5['point_table']}
    WHERE mb_id = '{$mb['mb_id']}'
    AND po_datetime >= '{$start_date}'
    AND po_datetime <= '{$end_date}'
";
$my_monthly_result = sql_fetch($my_monthly_points_sql);
$my_monthly_points = $my_monthly_result ? (int)$my_monthly_result['monthly_points'] : 0;
if ($my_monthly_points >= EXCELLENT_MEMBER_POINT && $top_rank == 0) {
    $hof_badges['excellent_member'] = $my_monthly_points;
}

// 3. 1등 출석왕 (가장 먼저 출석 1위) 체크
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
    ORDER BY first_count DESC, p.mb_id ASC
    LIMIT 3
";
$first_result = sql_query($first_login_sql);
$first_rank = 0;
$first_count = 0;
$rank_idx = 1;
while ($row = sql_fetch_array($first_result)) {
    if ($row['mb_id'] == $mb['mb_id']) {
        $first_rank = $rank_idx;
        $first_count = $row['first_count'];
        break;
    }
    $rank_idx++;
}
if ($first_rank > 0) {
    $hof_badges['first_login'] = array('rank' => $first_rank, 'count' => $first_count);
}

// 4. 최다 출석자 (상위 3위) 체크
$most_attendance_sql = "
    SELECT
        p.mb_id,
        COUNT(DISTINCT DATE(p.po_datetime)) as attend_days
    FROM {$g5['point_table']} p
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= '{$start_date}'
    AND p.po_datetime <= '{$end_date}'
    GROUP BY p.mb_id
    ORDER BY attend_days DESC, p.mb_id ASC
    LIMIT 3
";
$attend_result = sql_query($most_attendance_sql);
$attend_rank = 0;
$my_attend_days = 0;
$rank_idx = 1;
while ($row = sql_fetch_array($attend_result)) {
    if ($row['mb_id'] == $mb['mb_id']) {
        $attend_rank = $rank_idx;
        $my_attend_days = $row['attend_days'];
        break;
    }
    $rank_idx++;
}
if ($attend_rank > 0) {
    $hof_badges['most_attendance'] = array('rank' => $attend_rank, 'days' => $my_attend_days);
}

// 5. 연속 출석 챔피언 (상위 3위) 체크
function getConsecutiveDaysForMember($mb_id, $g5) {
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

$my_consecutive = getConsecutiveDaysForMember($mb['mb_id'], $g5);

// 상위 연속 출석자 조회
$active_members_sql = "
    SELECT DISTINCT p.mb_id
    FROM {$g5['point_table']} p
    WHERE p.po_content LIKE '%첫로그인%'
    AND p.po_datetime >= DATE_SUB(NOW(), INTERVAL 60 DAY)
";
$active_result = sql_query($active_members_sql);
$consecutive_members = array();
while ($row = sql_fetch_array($active_result)) {
    $consecutive_days = getConsecutiveDaysForMember($row['mb_id'], $g5);
    if ($consecutive_days >= 3) {
        $consecutive_members[] = array(
            'mb_id' => $row['mb_id'],
            'consecutive_days' => $consecutive_days
        );
    }
}
usort($consecutive_members, function($a, $b) {
    if ($b['consecutive_days'] == $a['consecutive_days']) {
        return strcmp($a['mb_id'], $b['mb_id']); // 동점일 때 mb_id로 정렬
    }
    return $b['consecutive_days'] - $a['consecutive_days'];
});
$consecutive_members = array_slice($consecutive_members, 0, 3);

$consecutive_rank = 0;
foreach ($consecutive_members as $idx => $member) {
    if ($member['mb_id'] == $mb['mb_id']) {
        $consecutive_rank = $idx + 1;
        break;
    }
}
if ($consecutive_rank > 0) {
    $hof_badges['consecutive'] = array('rank' => $consecutive_rank, 'days' => $my_consecutive);
}

// 6. 이달의 개근상 체크
$my_month_attend_sql = "
    SELECT COUNT(DISTINCT DATE(po_datetime)) as attend_days
    FROM {$g5['point_table']}
    WHERE mb_id = '{$mb['mb_id']}'
    AND po_content LIKE '%첫로그인%'
    AND po_datetime >= '{$start_date}'
    AND po_datetime <= '{$end_date}'
";
$my_month_attend = sql_fetch($my_month_attend_sql);
$my_month_attend_days = $my_month_attend ? (int)$my_month_attend['attend_days'] : 0;
if ($my_month_attend_days >= $today_day) {
    $hof_badges['perfect_attendance'] = $my_month_attend_days;
}

// 7. 새벽 출석자 (상위 3위) 체크
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
    ORDER BY dawn_count DESC, p.mb_id ASC
    LIMIT 3
";
$dawn_result = sql_query($dawn_sql);
$dawn_rank = 0;
$dawn_count = 0;
$rank_idx = 1;
while ($row = sql_fetch_array($dawn_result)) {
    if ($row['mb_id'] == $mb['mb_id']) {
        $dawn_rank = $rank_idx;
        $dawn_count = $row['dawn_count'];
        break;
    }
    $rank_idx++;
}
if ($dawn_rank > 0) {
    $hof_badges['dawn'] = array('rank' => $dawn_rank, 'count' => $dawn_count);
}

// 8. 오늘의 골든타임 (첫 출석자) 체크
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$golden_sql = "
    SELECT mb_id, po_datetime as login_time
    FROM {$g5['point_table']}
    WHERE po_content LIKE '%첫로그인%'
    AND po_datetime >= '{$today_start}'
    AND po_datetime <= '{$today_end}'
    ORDER BY po_datetime ASC
    LIMIT 1
";
$golden_result = sql_fetch($golden_sql);
if ($golden_result && $golden_result['mb_id'] == $mb['mb_id']) {
    $hof_badges['golden_time'] = date('H:i:s', strtotime($golden_result['login_time']));
}

// 프로필 이미지 경로 - 캐시 버스팅 적용
$profile_img = get_profile_image_url($mb['mb_id']);

// 내게 온 댓글 조회 (gallery + diary 게시판)
$my_comments_list = array();

// gallery 댓글
$gallery_comments_sql = "SELECT c.*, 'gallery' as bo_table,
                           p.wr_subject as parent_subject,
                           m.mb_name as comment_author_nick,
                           m.mb_id as comment_author_id
                    FROM {$g5['write_prefix']}gallery c
                    LEFT JOIN {$g5['write_prefix']}gallery p ON (c.wr_parent = p.wr_id)
                    LEFT JOIN {$g5['member_table']} m ON (c.mb_id = m.mb_id)
                    WHERE c.wr_is_comment = 1
                    AND p.mb_id = '{$mb['mb_id']}'
                    AND c.mb_id != '{$mb['mb_id']}'
                    ORDER BY c.wr_datetime DESC
                    LIMIT 5";
$gallery_comments_result = sql_query($gallery_comments_sql);
while ($row = sql_fetch_array($gallery_comments_result)) {
    $my_comments_list[] = $row;
}

// diary 댓글
if (sql_num_rows($diary_table_check)) {
    $diary_comments_sql = "SELECT c.*, 'diary' as bo_table,
                               '감사일기' as parent_subject,
                               m.mb_name as comment_author_nick,
                               m.mb_id as comment_author_id
                        FROM {$g5['write_prefix']}diary c
                        LEFT JOIN {$g5['write_prefix']}diary p ON (c.wr_parent = p.wr_id)
                        LEFT JOIN {$g5['member_table']} m ON (c.mb_id = m.mb_id)
                        WHERE c.wr_is_comment = 1
                        AND p.mb_id = '{$mb['mb_id']}'
                        AND c.mb_id != '{$mb['mb_id']}'
                        ORDER BY c.wr_datetime DESC
                        LIMIT 5";
    $diary_comments_result = sql_query($diary_comments_sql);
    while ($row = sql_fetch_array($diary_comments_result)) {
        $my_comments_list[] = $row;
    }
}

// 날짜순 정렬 후 5개만
usort($my_comments_list, function($a, $b) {
    return strtotime($b['wr_datetime']) - strtotime($a['wr_datetime']);
});
$my_comments_list = array_slice($my_comments_list, 0, 5);

?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    <link rel="icon" type="image/png" href="<?php echo G5_IMG_URL; ?>/logo.png">
    <link rel="apple-touch-icon" href="<?php echo G5_IMG_URL; ?>/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script> window.FontAwesomeConfig = { autoReplaceSvg: 'nest'};</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { display: none;}
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; }
        .profile-gradient { background: linear-gradient(135deg, #E8E2F7 0%, #B19CD9 50%, #6B46C1 100%); }
        .shadow-warm { box-shadow: 0 4px 20px rgba(177, 156, 217, 0.15); }
        .stat-card { background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(232,226,247,0.3) 100%); }
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
<body class="bg-warm-beige">

<header id="header" class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="flex items-center justify-between px-4 py-3">
        <button onclick="goBack()" class="flex items-center gap-2">
            <i class="fa-solid fa-arrow-left text-grace-green text-lg"></i>
        </button>
        <h1 class="text-lg font-semibold text-grace-green">내 정보</h1>
        <button class="flex items-center gap-2">
            <i class="fa-solid fa-gear text-grace-green text-lg"></i>
        </button>
    </div>
</header>

<main id="main-content" class="pt-16 pb-20">

    <section id="profile-header" class="relative">
        <div class="profile-gradient h-32"></div>
        <div class="absolute -bottom-12 left-1/2 transform -translate-x-1/2">
            <div class="w-24 h-24 bg-white rounded-full p-1 shadow-warm relative cursor-pointer group" onclick="document.getElementById('profile_image_input').click()">
                <img id="profile_image_preview" src="<?php echo $profile_img; ?>" class="w-full h-full rounded-full object-cover" alt="프로필 이미지">
                <!-- 카메라 오버레이 -->
                <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="fa-solid fa-camera text-white text-xl"></i>
                </div>
                <!-- 모바일용 항상 보이는 카메라 아이콘 -->
                <div class="absolute -bottom-1 -right-1 w-8 h-8 bg-lilac rounded-full flex items-center justify-center shadow-lg border-2 border-white">
                    <i class="fa-solid fa-camera text-white text-xs"></i>
                </div>
            </div>
            <!-- 숨겨진 파일 입력 -->
            <input type="file" id="profile_image_input" accept="image/jpeg,image/png,image/gif" class="hidden" onchange="uploadProfileImage(this)">
        </div>
    </section>

    <!-- 업로드 로딩 오버레이 -->
    <div id="upload_overlay" class="fixed inset-0 bg-black/50 z-[100] flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 text-center">
            <div class="animate-spin w-10 h-10 border-4 border-lilac border-t-transparent rounded-full mx-auto mb-3"></div>
            <p class="text-sm text-grace-green">프로필 사진 변경 중...</p>
        </div>
    </div>

    <section id="profile-info" class="pt-16 px-4 text-center">
        <h2 class="text-xl font-semibold text-grace-green mb-1"><?php echo get_text($mb['mb_name']); ?></h2>
        <p class="text-sm text-gray-500 mb-2">@<?php echo get_text($mb['mb_id']); ?></p>
        <p class="text-xs text-grace-green mb-4">
            <?php
            if ($years > 0) {
                echo "함께한 지 {$years}년 {$months}개월";
            } else {
                echo "함께한 지 {$months}개월";
            }
            ?>
        </p>

        <div class="flex justify-center gap-6 mb-6">
            <div class="text-center">
                <div class="text-lg font-semibold text-grace-green"><?php echo $total_post_count; ?></div>
                <div class="text-xs text-gray-500">게시물</div>
            </div>
            <div class="text-center">
                <div class="text-lg font-semibold text-grace-green"><?php echo $point; ?></div>
                <div class="text-xs text-gray-500">포인트</div>
            </div>
            <div class="text-center">
                <div class="text-lg font-semibold text-grace-green"><?php echo $attendance_days; ?></div>
                <div class="text-xs text-gray-500">출석일</div>
            </div>
        </div>

        <?php if ($mb['mb_profile']) { ?>
        <p class="text-sm text-grace-green leading-relaxed px-4">
            <?php echo nl2br(get_text($mb['mb_profile'])); ?>
        </p>
        <?php } else { ?>
        <p class="text-sm text-gray-400 leading-relaxed px-4">
            소개 내용이 없습니다.
        </p>
        <?php } ?>
    </section>


    <section id="achievements" class="px-4 mt-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-grace-green">획득한 뱃지</h3>
            <?php if (count($hof_badges) > 0) { ?>
            <a href="<?php echo G5_BBS_URL; ?>/halloffame.php" class="text-xs text-lilac flex items-center gap-1">
                명예의 전당 <i class="fa-solid fa-chevron-right text-xs"></i>
            </a>
            <?php } ?>
        </div>

        <?php
        $has_any_badge = ($attendance_days >= 90) || ($total_post_count >= 10) || ($mb['mb_point'] >= 1000) || (count($hof_badges) > 0);
        ?>

        <?php if ($has_any_badge) { ?>
        <div class="grid grid-cols-3 gap-3">
            <?php // 명예의 전당 뱃지들 ?>

            <?php if (isset($hof_badges['best_member'])) {
                $rank = $hof_badges['best_member'];
                $rank_colors = array(
                    1 => 'from-yellow-400 to-amber-500',
                    2 => 'from-gray-300 to-gray-400',
                    3 => 'from-amber-600 to-amber-700'
                );
                $rank_names = array(1 => '1등', 2 => '2등', 3 => '3등');
            ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm border-2 border-divine-lilac/30 relative">
                <div class="absolute -top-2 -right-2 w-6 h-6 bg-divine-lilac rounded-full flex items-center justify-center">
                    <span class="text-white text-xs font-bold"><?php echo $rank; ?></span>
                </div>
                <div class="w-12 h-12 bg-gradient-to-r <?php echo $rank_colors[$rank]; ?> rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-trophy text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">베스트 성산인</div>
                <div class="text-xs text-lilac font-semibold"><?php echo $current_month; ?>월 <?php echo $rank_names[$rank]; ?></div>
            </div>
            <?php } ?>

            <?php if (isset($hof_badges['excellent_member'])) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm border border-lilac/20">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-lilac rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-hands-praying text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">우수 성산인</div>
                <div class="text-xs text-lilac"><?php echo number_format($hof_badges['excellent_member']); ?>점</div>
            </div>
            <?php } ?>

            <?php if (isset($hof_badges['golden_time'])) { ?>
            <div class="bg-gradient-to-r from-yellow-400 via-orange-400 to-yellow-500 rounded-2xl p-4 text-center shadow-warm relative overflow-hidden">
                <div class="absolute top-0 right-0 w-8 h-8 bg-white/10 rounded-full -mr-4 -mt-4"></div>
                <div class="w-12 h-12 bg-white/30 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-bolt text-white"></i>
                </div>
                <div class="text-xs font-medium text-white">골든타임</div>
                <div class="text-xs text-white/80"><?php echo $hof_badges['golden_time']; ?></div>
            </div>
            <?php } ?>

            <?php if (isset($hof_badges['first_login'])) {
                $rank = $hof_badges['first_login']['rank'];
                $count = $hof_badges['first_login']['count'];
            ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm relative">
                <?php if ($rank == 1) { ?>
                <div class="absolute -top-2 -right-2 w-6 h-6 bg-amber-500 rounded-full flex items-center justify-center">
                    <span class="text-white text-xs font-bold"><?php echo $rank; ?></span>
                </div>
                <?php } ?>
                <div class="w-12 h-12 bg-gradient-to-r from-amber-400 to-amber-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-clock text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">1등 출석왕</div>
                <div class="text-xs text-amber-600"><?php echo $count; ?>회 1등</div>
            </div>
            <?php } ?>

            <?php if (isset($hof_badges['most_attendance'])) {
                $rank = $hof_badges['most_attendance']['rank'];
                $days = $hof_badges['most_attendance']['days'];
            ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm relative">
                <?php if ($rank == 1) { ?>
                <div class="absolute -top-2 -right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                    <span class="text-white text-xs font-bold"><?php echo $rank; ?></span>
                </div>
                <?php } ?>
                <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-check-double text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">최다 출석</div>
                <div class="text-xs text-green-600"><?php echo $days; ?>일 출석</div>
            </div>
            <?php } ?>

            <?php if (isset($hof_badges['consecutive'])) {
                $rank = $hof_badges['consecutive']['rank'];
                $days = $hof_badges['consecutive']['days'];
            ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm relative">
                <?php if ($rank == 1) { ?>
                <div class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center">
                    <span class="text-white text-xs font-bold"><?php echo $rank; ?></span>
                </div>
                <?php } ?>
                <div class="w-12 h-12 bg-gradient-to-r from-red-400 to-red-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-fire text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">연속 출석</div>
                <div class="text-xs text-red-600"><?php echo $days; ?>일 연속</div>
            </div>
            <?php } ?>

            <?php if (isset($hof_badges['perfect_attendance'])) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm border border-purple-200">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-medal text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">개근상</div>
                <div class="text-xs text-purple-600"><?php echo $current_month; ?>월 개근</div>
            </div>
            <?php } ?>

            <?php if (isset($hof_badges['dawn'])) {
                $rank = $hof_badges['dawn']['rank'];
                $count = $hof_badges['dawn']['count'];
            ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm relative">
                <?php if ($rank == 1) { ?>
                <div class="absolute -top-2 -right-2 w-6 h-6 bg-indigo-500 rounded-full flex items-center justify-center">
                    <span class="text-white text-xs font-bold"><?php echo $rank; ?></span>
                </div>
                <?php } ?>
                <div class="w-12 h-12 bg-gradient-to-r from-indigo-400 to-indigo-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-sun text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">새벽 출석</div>
                <div class="text-xs text-indigo-600"><?php echo $count; ?>회</div>
            </div>
            <?php } ?>

            <?php // 기존 뱃지들 ?>

            <?php if ($attendance_days >= 90) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm">
                <div class="w-12 h-12 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-crown text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">출석왕</div>
                <div class="text-xs text-gray-500">3개월 연속</div>
            </div>
            <?php } ?>

            <?php if ($total_post_count >= 10) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-pen text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">활동적인</div>
                <div class="text-xs text-gray-500">활발한 참여</div>
            </div>
            <?php } ?>

            <?php if ($mb['mb_point'] >= 1000) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-coins text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">포인트왕</div>
                <div class="text-xs text-gray-500">포인트 우수자</div>
            </div>
            <?php } ?>
        </div>
        <?php } else { ?>
        <div class="bg-white rounded-2xl p-8 shadow-warm text-center">
            <i class="fa-solid fa-award text-gray-300 text-4xl mb-3"></i>
            <p class="text-sm text-gray-400">아직 획득한 뱃지가 없습니다.</p>
            <p class="text-xs text-gray-400 mt-1">활발하게 활동하면 뱃지를 획득할 수 있어요!</p>
        </div>
        <?php } ?>
    </section>

    <?php if (count($historical_badges) > 0) { ?>
    <!-- 역대 뱃지 섹션 -->
    <section id="historical-badges" class="px-4 mt-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-grace-green">역대 뱃지</h3>
            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                총 <?php echo count($historical_badges); ?>개
            </span>
        </div>

        <!-- 뱃지 통계 요약 -->
        <?php if (count($badge_stats) > 0) { ?>
        <div class="bg-white rounded-2xl p-4 shadow-warm mb-3">
            <div class="flex flex-wrap gap-2">
                <?php foreach ($badge_stats as $type => $cnt) {
                    $info = function_exists('get_badge_info') ? get_badge_info($type) : null;
                    if ($info) {
                ?>
                <div class="flex items-center gap-1 bg-soft-lavender/50 px-3 py-1.5 rounded-full">
                    <i class="fa-solid <?php echo $info['icon']; ?> text-lilac text-xs"></i>
                    <span class="text-xs text-grace-green"><?php echo $info['name']; ?></span>
                    <span class="text-xs font-bold text-deep-purple">x<?php echo $cnt; ?></span>
                </div>
                <?php } } ?>
            </div>
        </div>
        <?php } ?>

        <!-- 역대 뱃지 목록 (최근 10개) -->
        <div class="bg-white rounded-2xl shadow-warm overflow-hidden">
            <div class="divide-y divide-gray-100">
                <?php
                $display_badges = array_slice($historical_badges, 0, 10);
                foreach ($display_badges as $badge) {
                    $info = function_exists('get_badge_info') ? get_badge_info($badge['badge_type']) : null;
                    if (!$info) continue;

                    $color_class = is_array($info['colors'])
                        ? (isset($info['colors'][$badge['badge_rank']]) ? $info['colors'][$badge['badge_rank']] : $info['colors'][1])
                        : $info['colors'];
                ?>
                <div class="flex items-center gap-3 p-3">
                    <div class="w-10 h-10 bg-gradient-to-r <?php echo $color_class; ?> rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid <?php echo $info['icon']; ?> text-white text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-grace-green"><?php echo $info['name']; ?></span>
                            <?php if ($badge['badge_rank'] > 0 && $badge['badge_rank'] <= 3) { ?>
                            <span class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded"><?php echo $badge['badge_rank']; ?>위</span>
                            <?php } ?>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-400">
                            <span><?php echo $badge['badge_year']; ?>년 <?php echo $badge['badge_month']; ?>월</span>
                            <?php if ($badge['badge_value']) { ?>
                            <span class="text-lilac"><?php echo $badge['badge_value']; ?></span>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </section>
    <?php } ?>

    <section id="recent-activity" class="px-4 mt-6">
        <h3 class="text-lg font-semibold text-grace-green mb-4">최근 활동</h3>

        <div class="space-y-3">
            <?php
            // 최근 게시물 조회 - 각 게시판에서 개별적으로 조회
            $recent_posts = array();
            $board_list = sql_query("SELECT bo_table FROM {$g5['board_table']} LIMIT 10");

            while ($board = sql_fetch_array($board_list)) {
                $bo_table = $board['bo_table'];
                $write_table = $g5['write_prefix'] . $bo_table;

                // 테이블 존재 여부 확인
                $table_check = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
                if (!sql_num_rows($table_check)) {
                    continue;
                }

                // 컬럼 존재 여부 확인
                $column_check = sql_query("SHOW COLUMNS FROM {$write_table} WHERE Field IN ('wr_subject', 'wr_datetime')", false);
                if (sql_num_rows($column_check) < 2) {
                    continue;
                }

                $sql = "SELECT wr_id, wr_subject, wr_datetime FROM {$write_table}
                        WHERE mb_id = '{$mb['mb_id']}'
                        ORDER BY wr_datetime DESC
                        LIMIT 3";
                $result = sql_query($sql, false);

                if ($result) {
                    while ($row = sql_fetch_array($result)) {
                        if (isset($row['wr_datetime']) && isset($row['wr_subject']) && $row['wr_datetime'] && $row['wr_subject']) {
                            $recent_posts[] = array(
                                'bo_table' => $bo_table,
                                'wr_id' => $row['wr_id'],
                                'wr_subject' => $row['wr_subject'],
                                'wr_datetime' => $row['wr_datetime']
                            );
                        }
                    }
                }
            }

            // 날짜순으로 정렬
            usort($recent_posts, function($a, $b) {
                return strtotime($b['wr_datetime']) - strtotime($a['wr_datetime']);
            });

            // 최대 3개만 표시
            $recent_posts = array_slice($recent_posts, 0, 3);

            if (count($recent_posts) > 0) {
                foreach ($recent_posts as $recent) {
                    $time_diff = time() - strtotime($recent['wr_datetime']);
                    if ($time_diff < 3600) {
                        $time_str = floor($time_diff / 60) . '분 전';
                    } elseif ($time_diff < 86400) {
                        $time_str = floor($time_diff / 3600) . '시간 전';
                    } else {
                        $time_str = floor($time_diff / 86400) . '일 전';
                    }
                    ?>
                    <div class="bg-white rounded-2xl p-4 shadow-warm">
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fa-solid fa-pen text-lilac"></i>
                            <span class="text-sm text-grace-green truncate flex-1"><?php echo get_text($recent['wr_subject']); ?></span>
                            <span class="text-xs text-gray-500 ml-auto"><?php echo $time_str; ?></span>
                        </div>
                    </div>
                    <?php
                }
            } else {
                ?>
                <div class="bg-white rounded-2xl p-4 shadow-warm text-center">
                    <span class="text-sm text-gray-400">최근 활동이 없습니다.</span>
                </div>
                <?php
            }
            ?>
        </div>
    </section>

    <section id="my-comments" class="px-4 mt-6">
        <h3 class="text-lg font-semibold text-grace-green mb-4 flex items-center justify-between">
            <span>내게 온 댓글</span>
            <?php if (count($my_comments_list) > 0) { ?>
            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                <?php echo count($my_comments_list); ?>개
            </span>
            <?php } ?>
        </h3>

        <div class="space-y-3">
            <?php
            if (count($my_comments_list) > 0) {
                foreach ($my_comments_list as $comment) {
                    // 시간 계산
                    $time_diff = time() - strtotime($comment['wr_datetime']);
                    if ($time_diff < 3600) {
                        $time_str = floor($time_diff / 60) . '분 전';
                    } elseif ($time_diff < 86400) {
                        $time_str = floor($time_diff / 3600) . '시간 전';
                    } else {
                        $time_str = floor($time_diff / 86400) . '일 전';
                    }

                    // 댓글 작성자 프로필 이미지 - 캐시 버스팅 적용
                    $comment_author_photo = $comment['comment_author_id'] ? get_profile_image_url($comment['comment_author_id']) : 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';

                    // 댓글 링크 (gallery vs diary)
                    if ($comment['bo_table'] === 'diary') {
                        $comment_link = G5_BBS_URL.'/gratitude_user.php?mb_id='.urlencode($mb['mb_id']).'&wr_id='.$comment['wr_parent'];
                    } else {
                        $comment_link = G5_BBS_URL.'/post.php?bo_table=gallery&wr_id='.$comment['wr_parent'].'#c_'.$comment['wr_id'];
                    }
                    ?>
                    <a href="<?php echo $comment_link; ?>"
                       class="bg-white rounded-2xl p-4 shadow-warm block hover:shadow-lg transition-shadow">
                        <div class="flex items-start gap-3">
                            <img src="<?php echo $comment_author_photo; ?>"
                                 class="w-10 h-10 rounded-full object-cover flex-shrink-0"
                                 alt="<?php echo $comment['comment_author_nick']; ?>">
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <p class="text-xs text-gray-500 flex items-center gap-1 mb-1">
                                            <i class="fa-solid fa-reply text-purple-500"></i>
                                            <span class="text-purple-600 font-medium">@<?php echo $comment['comment_author_nick']; ?></span>님이 댓글을 남겼습니다
                                        </p>
                                        <?php if ($comment['parent_subject']) { ?>
                                        <p class="text-xs text-gray-400">
                                            <?php if ($comment['bo_table'] === 'diary') { ?>
                                            <i class="fa-solid fa-book text-lilac mr-1"></i>
                                            <?php } ?>
                                            <?php echo cut_str($comment['parent_subject'], 40); ?>
                                        </p>
                                        <?php } ?>
                                    </div>
                                    <span class="text-xs text-gray-400 ml-2"><?php echo $time_str; ?></span>
                                </div>
                                <p class="text-sm text-gray-700 line-clamp-2"><?php echo strip_tags($comment['wr_content']); ?></p>
                            </div>
                        </div>
                    </a>
                    <?php
                }
            } else {
                ?>
                <div class="bg-white rounded-2xl p-8 shadow-warm text-center">
                    <i class="fa-regular fa-comment text-gray-300 text-4xl mb-3"></i>
                    <p class="text-sm text-gray-400">아직 받은 댓글이 없습니다.</p>
                </div>
                <?php
            }
            ?>
        </div>
    </section>

    <section id="settings-menu" class="px-4 mt-6 pb-6">
        <div class="bg-white rounded-2xl shadow-warm overflow-hidden">
            <a href="<?php echo G5_BBS_URL; ?>/member_confirm.php?url=<?php echo urlencode(G5_BBS_URL.'/register_form.php'); ?>" class="w-full flex items-center justify-between p-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-user-edit text-grace-green"></i>
                    <span class="text-sm text-grace-green">프로필 편집</span>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-400 text-sm"></i>
            </a>

            <a href="<?php echo G5_BBS_URL; ?>/point.php" class="w-full flex items-center justify-between p-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-coins text-grace-green"></i>
                    <span class="text-sm text-grace-green">포인트 내역</span>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-400 text-sm"></i>
            </a>

            <a href="<?php echo G5_BBS_URL; ?>/scrap.php" class="w-full flex items-center justify-between p-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-bookmark text-grace-green"></i>
                    <span class="text-sm text-grace-green">스크랩</span>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-400 text-sm"></i>
            </a>

            <button onclick="confirmLogout()" class="w-full flex items-center justify-between p-4">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-sign-out-alt text-red-500"></i>
                    <span class="text-sm text-red-500">로그아웃</span>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-400 text-sm"></i>
            </button>
        </div>
    </section>

</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<script>
function goBack() {
    window.history.back();
}

function confirmLogout() {
    if (confirm('로그아웃 하시겠습니까?')) {
        location.href = '<?php echo G5_BBS_URL; ?>/logout.php';
    }
}

function uploadProfileImage(input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];

    // 파일 크기 체크 (10MB)
    if (file.size > 10 * 1024 * 1024) {
        alert('파일 크기는 10MB 이하로 선택해주세요.');
        return;
    }

    // 이미지 타입 체크
    if (!file.type.match(/image\/(jpeg|png|gif)/)) {
        alert('JPG, PNG, GIF 이미지만 업로드 가능합니다.');
        return;
    }

    // 미리보기 표시
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('profile_image_preview').src = e.target.result;
    };
    reader.readAsDataURL(file);

    // 업로드 시작
    const overlay = document.getElementById('upload_overlay');
    overlay.classList.remove('hidden');

    const formData = new FormData();
    formData.append('profile_image', file);

    fetch('<?php echo G5_BBS_URL; ?>/profile_image_update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        overlay.classList.add('hidden');
        if (data.success) {
            // 캐시 방지를 위해 타임스탬프 추가
            document.getElementById('profile_image_preview').src = data.image_url + '?' + new Date().getTime();
            alert('프로필 사진이 변경되었습니다.');
        } else {
            alert(data.message || '업로드에 실패했습니다.');
            // 실패 시 원래 이미지로 복원
            location.reload();
        }
    })
    .catch(error => {
        overlay.classList.add('hidden');
        alert('업로드 중 오류가 발생했습니다.');
        location.reload();
    });

    // 같은 파일 다시 선택 가능하도록
    input.value = '';
}
</script>

</body>
</html>
