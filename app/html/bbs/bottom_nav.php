<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 프로필 이미지 경로 (로그인한 경우에만)
$nav_profile_img = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
if ($is_member) {
    $nav_profile_img = G5_DATA_URL.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif';
    if (!file_exists(G5_DATA_PATH.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif')) {
        $nav_profile_img = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
    }
}
?>

<nav id="bottom-nav" class="fixed bottom-0 w-full bg-white border-t border-soft-lavender z-40">
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
            <img src="<?php echo $nav_profile_img; ?>" class="w-6 h-6 rounded-full object-cover border-2 border-lilac">
            <span class="text-xs text-lilac font-medium">내 정보</span>
        </a>
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
