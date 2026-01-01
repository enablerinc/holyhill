<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 현재 페이지 감지
$current_script = basename($_SERVER['SCRIPT_NAME']);
$current_page = '';

if ($current_script == 'index.php' && strpos($_SERVER['REQUEST_URI'], '/bbs/index.php') !== false) {
    $current_page = 'home';
} elseif ($current_script == 'feed.php') {
    $current_page = 'feed';
} elseif ($current_script == 'gratitude.php' || $current_script == 'gratitude_user.php' || $current_script == 'gratitude_write.php') {
    $current_page = 'gratitude';
} elseif ($current_script == 'word_feed.php') {
    $current_page = 'word';
} elseif ($current_script == 'halloffame.php') {
    $current_page = 'halloffame';
} elseif ($current_script == 'mypage.php') {
    $current_page = 'mypage';
} elseif ($current_script == 'login.php') {
    $current_page = 'login';
}

// 텍스트 스타일 (검은색)
$active_text_class = 'text-xs text-gray-800 font-semibold';
$inactive_text_class = 'text-xs text-gray-600';

// 각 메뉴별 아이콘 색상
$menu_colors = array(
    'home' => array('active' => 'text-purple-600 text-xl', 'inactive' => 'text-purple-400 text-lg'),
    'feed' => array('active' => 'text-blue-600 text-xl', 'inactive' => 'text-blue-400 text-lg'),
    'gratitude' => array('active' => 'text-pink-600 text-xl', 'inactive' => 'text-pink-400 text-lg'),
    'word' => array('active' => 'text-amber-600 text-xl', 'inactive' => 'text-amber-500 text-lg'),
    'halloffame' => array('active' => 'text-yellow-600 text-xl', 'inactive' => 'text-yellow-500 text-lg'),
    'mypage' => array('active' => 'text-purple-600 text-xl', 'inactive' => 'text-purple-400 text-lg'),
    'login' => array('active' => 'text-gray-700 text-xl', 'inactive' => 'text-gray-400 text-lg')
);

// 프로필 이미지 경로 (로그인한 경우에만)
$nav_profile_img = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
if ($is_member && isset($_SESSION['ss_mb_id'])) {
    // 세션에서 직접 가져와서 extract()에 의한 변수 오염 방지
    $safe_mb_id = $_SESSION['ss_mb_id'];
    $nav_profile_img = G5_DATA_URL.'/member_image/'.substr($safe_mb_id, 0, 2).'/'.$safe_mb_id.'.gif';
    if (!file_exists(G5_DATA_PATH.'/member_image/'.substr($safe_mb_id, 0, 2).'/'.$safe_mb_id.'.gif')) {
        $nav_profile_img = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
    }
}
?>

<nav id="bottom-nav" class="fixed bottom-0 w-full bg-white border-t border-gray-200 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] z-40">
    <div class="flex items-center justify-around py-4">
        <!-- 홈 -->
        <a href="<?php echo G5_BBS_URL; ?>/index.php" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-house <?php echo ($current_page == 'home') ? $menu_colors['home']['active'] : $menu_colors['home']['inactive']; ?>"></i>
            <span class="<?php echo ($current_page == 'home') ? $active_text_class : $inactive_text_class; ?>">홈</span>
        </a>

        <!-- 성산샘터 -->
        <a href="<?php echo G5_BBS_URL; ?>/feed.php" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-droplet <?php echo ($current_page == 'feed') ? $menu_colors['feed']['active'] : $menu_colors['feed']['inactive']; ?>"></i>
            <span class="<?php echo ($current_page == 'feed') ? $active_text_class : $inactive_text_class; ?>">성산샘터</span>
        </a>

        <!-- 감사일기 -->
        <a href="<?php echo G5_BBS_URL; ?>/gratitude.php" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-book <?php echo ($current_page == 'gratitude') ? $menu_colors['gratitude']['active'] : $menu_colors['gratitude']['inactive']; ?>"></i>
            <span class="<?php echo ($current_page == 'gratitude') ? $active_text_class : $inactive_text_class; ?>">감사일기</span>
        </a>

        <!-- 말씀 -->
        <a href="<?php echo G5_BBS_URL; ?>/word_feed.php" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-book-bible <?php echo ($current_page == 'word') ? $menu_colors['word']['active'] : $menu_colors['word']['inactive']; ?>"></i>
            <span class="<?php echo ($current_page == 'word') ? $active_text_class : $inactive_text_class; ?>">말씀</span>
        </a>

        <!-- 명예의 전당 -->
        <a href="<?php echo G5_BBS_URL; ?>/halloffame.php" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-trophy <?php echo ($current_page == 'halloffame') ? $menu_colors['halloffame']['active'] : $menu_colors['halloffame']['inactive']; ?>"></i>
            <span class="<?php echo ($current_page == 'halloffame') ? $active_text_class : $inactive_text_class; ?>">명예의 전당</span>
        </a>

        <!-- 내 정보 / 로그인 -->
        <?php if ($is_member) { ?>
        <a href="<?php echo G5_BBS_URL; ?>/mypage.php" class="flex flex-col items-center gap-1">
            <div class="<?php echo ($current_page == 'mypage') ? 'p-0.5 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full' : 'p-0.5 bg-gradient-to-r from-purple-300 to-pink-300 rounded-full'; ?>">
                <img src="<?php echo $nav_profile_img; ?>" class="w-7 h-7 rounded-full object-cover border-2 border-white">
            </div>
            <span class="<?php echo ($current_page == 'mypage') ? $active_text_class : $inactive_text_class; ?>">내 정보</span>
        </a>
        <?php } else { ?>
        <a href="<?php echo G5_BBS_URL; ?>/login.php" class="flex flex-col items-center gap-1">
            <i class="fa-regular fa-user <?php echo ($current_page == 'login') ? $menu_colors['login']['active'] : $menu_colors['login']['inactive']; ?>"></i>
            <span class="<?php echo ($current_page == 'login') ? $active_text_class : $inactive_text_class; ?>">로그인</span>
        </a>
        <?php } ?>
    </div>
</nav>

<style>
/* Tailwind 색상 정의 (필요한 경우) */
:root {
    --color-soft-lavender: #E8E2F7;
    --color-grace-green: #6B705C;
    --color-lilac: #B19CD9;
}

.border-soft-lavender {
    border-color: var(--color-soft-lavender);
}

.text-grace-green {
    color: var(--color-grace-green);
}

.bg-lilac {
    background-color: var(--color-lilac);
}

.text-lilac {
    color: var(--color-lilac);
}

.border-lilac {
    border-color: var(--color-lilac);
}
</style>
