<?php
include_once('./_common.php');

// 로그인 체크
if (!$is_member) {
    alert('로그인이 필요합니다.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/mypage.php'));
}

$g5['title'] = '내 정보';

// 회원 정보 가져오기
$mb = get_member($member['mb_id']);

// 가입 경과 기간 계산
$join_date = strtotime($mb['mb_datetime']);
$now = G5_SERVER_TIME;
$diff = $now - $join_date;
$years = floor($diff / (365 * 60 * 60 * 24));
$months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));

// 게시물 수 조회 - 모든 게시판의 게시물 합계
$sql = "SELECT COUNT(*) as cnt FROM (";
$board_list = sql_query("SELECT bo_table FROM {$g5['board_table']}");
$first = true;
while ($board = sql_fetch_array($board_list)) {
    if (!$first) $sql .= " UNION ALL ";
    $sql .= "SELECT wr_id FROM {$g5['write_prefix']}{$board['bo_table']} WHERE mb_id = '{$mb['mb_id']}'";
    $first = false;
}
$sql .= ") as total_posts";

if ($first) {
    // 게시판이 없는 경우
    $post_count = 0;
} else {
    $result = sql_fetch($sql);
    $post_count = $result['cnt'];
}

// 포인트
$point = number_format($mb['mb_point']);

// 최근 로그인 일수 (간단히 처리)
$attendance_days = 0;
if ($mb['mb_login_point']) {
    // 로그인 포인트 기록으로부터 대략적인 출석일 계산
    $attendance_days = floor($mb['mb_login_point'] / abs($config['cf_login_point']));
}

// 프로필 이미지 경로
$profile_img = G5_DATA_URL.'/member_image/'.substr($mb['mb_id'], 0, 2).'/'.$mb['mb_id'].'.gif';
if (!file_exists(G5_DATA_PATH.'/member_image/'.substr($mb['mb_id'], 0, 2).'/'.$mb['mb_id'].'.gif')) {
    $profile_img = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
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
                        'deep-purple': '#6B46C1'
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
            <div class="w-24 h-24 bg-white rounded-full p-1 shadow-warm">
                <img src="<?php echo $profile_img; ?>" class="w-full h-full rounded-full object-cover" alt="프로필 이미지">
            </div>
        </div>
    </section>

    <section id="profile-info" class="pt-16 px-4 text-center">
        <h2 class="text-xl font-semibold text-grace-green mb-1"><?php echo get_text($mb['mb_name']); ?></h2>
        <p class="text-sm text-gray-500 mb-2"><?php echo get_text($mb['mb_nick']); ?></p>
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
                <div class="text-lg font-semibold text-grace-green"><?php echo $post_count; ?></div>
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
        <h3 class="text-lg font-semibold text-grace-green mb-4">획득한 뱃지</h3>

        <div class="grid grid-cols-3 gap-3">
            <?php if ($attendance_days >= 90) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm">
                <div class="w-12 h-12 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-crown text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">출석왕</div>
                <div class="text-xs text-gray-500">3개월 연속</div>
            </div>
            <?php } ?>

            <?php if ($post_count >= 10) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-music text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">활동적인</div>
                <div class="text-xs text-gray-500">활발한 참여</div>
            </div>
            <?php } ?>

            <?php if ($mb['mb_point'] >= 1000) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-heart text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">포인트왕</div>
                <div class="text-xs text-gray-500">포인트 우수자</div>
            </div>
            <?php } ?>
        </div>
    </section>

    <section id="recent-activity" class="px-4 mt-6">
        <h3 class="text-lg font-semibold text-grace-green mb-4">최근 활동</h3>

        <div class="space-y-3">
            <?php
            // 최근 게시물 조회
            $recent_posts_sql = "SELECT bo_table, wr_id, wr_subject, wr_datetime FROM (";
            $board_list = sql_query("SELECT bo_table FROM {$g5['board_table']} LIMIT 10");
            $first = true;
            while ($board = sql_fetch_array($board_list)) {
                if (!$first) $recent_posts_sql .= " UNION ALL ";
                $recent_posts_sql .= "SELECT '{$board['bo_table']}' as bo_table, wr_id, wr_subject, wr_datetime FROM {$g5['write_prefix']}{$board['bo_table']} WHERE mb_id = '{$mb['mb_id']}'";
                $first = false;
            }
            if (!$first) {
                $recent_posts_sql .= ") as recent ORDER BY wr_datetime DESC LIMIT 3";
                $recent_result = sql_query($recent_posts_sql);

                while ($recent = sql_fetch_array($recent_result)) {
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
            }

            if ($first || sql_num_rows($recent_result) == 0) {
                ?>
                <div class="bg-white rounded-2xl p-4 shadow-warm text-center">
                    <span class="text-sm text-gray-400">최근 활동이 없습니다.</span>
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

<nav id="bottom-nav" class="fixed bottom-0 w-full bg-white border-t border-soft-lavender">
    <div class="flex items-center justify-around py-3">
        <a href="<?php echo G5_URL; ?>" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-house text-grace-green text-lg"></i>
            <span class="text-xs text-grace-green">홈</span>
        </a>

        <a href="<?php echo G5_BBS_URL; ?>/new.php" class="flex flex-col items-center gap-1">
            <i class="fa-regular fa-compass text-grace-green text-lg"></i>
            <span class="text-xs text-grace-green">둘러보기</span>
        </a>

        <a href="<?php echo G5_BBS_URL; ?>/write.php" class="flex flex-col items-center gap-1">
            <div class="w-8 h-8 bg-lilac rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-plus text-white text-sm"></i>
            </div>
            <span class="text-xs text-grace-green">나눔</span>
        </a>

        <a href="<?php echo G5_BBS_URL; ?>/current_connect.php" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-trophy text-grace-green text-lg"></i>
            <span class="text-xs text-grace-green">명예의 전당</span>
        </a>

        <a href="<?php echo G5_BBS_URL; ?>/mypage.php" class="flex flex-col items-center gap-1">
            <img src="<?php echo $profile_img; ?>" class="w-6 h-6 rounded-full object-cover border-2 border-lilac">
            <span class="text-xs text-lilac font-medium">내 정보</span>
        </a>
    </div>
</nav>

<script>
function goBack() {
    window.history.back();
}

function confirmLogout() {
    if (confirm('로그아웃 하시겠습니까?')) {
        location.href = '<?php echo G5_BBS_URL; ?>/logout.php';
    }
}
</script>

</body>
</html>
