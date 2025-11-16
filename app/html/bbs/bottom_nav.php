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
        <a href="/" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-house text-purple-600 text-lg"></i>
            <span class="text-xs text-purple-600 font-medium">홈</span>
        </a>

        <a href="<?php echo G5_BBS_URL; ?>/feed.php" class="flex flex-col items-center gap-1">
            <i class="fa-regular fa-images text-gray-600 text-lg"></i>
            <span class="text-xs text-gray-600">피드</span>
        </a>

        <?php if ($is_member) { ?>
        <a href="<?php echo G5_BBS_URL; ?>/write.php?bo_table=gallery" class="flex flex-col items-center gap-1">
            <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-plus text-white text-sm"></i>
            </div>
            <span class="text-xs text-gray-600">나눔</span>
        </a>
        <?php } ?>

        <a href="<?php echo G5_BBS_URL; ?>/halloffame.php" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-trophy text-lilac text-lg"></i>
            <span class="text-xs text-lilac font-medium">명예의 전당</span>
        </a>

        <?php if ($is_member) { ?>
        <a href="<?php echo G5_BBS_URL; ?>/mypage.php" class="flex flex-col items-center gap-1">
            <img src="<?php echo $nav_profile_img; ?>" class="w-6 h-6 rounded-full object-cover border-2 border-lilac">
            <span class="text-xs text-lilac font-medium">내 정보</span>
        </a>
        <?php } else { ?>
        <a href="<?php echo G5_BBS_URL; ?>/login.php" class="flex flex-col items-center gap-1">
            <i class="fa-regular fa-user text-gray-600 text-lg"></i>
            <span class="text-xs text-gray-600">로그인</span>
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
