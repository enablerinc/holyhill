<?php
if (!defined('_GNUBOARD_')) exit;
?>

</main>

<!-- 하단 네비게이션 -->
<nav id="bottom-nav" class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
    <div class="max-w-2xl mx-auto flex items-center justify-around py-3">
        <a href="/" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-house text-purple-600 text-lg"></i>
            <span class="text-xs text-purple-600 font-medium">홈</span>
        </a>

        <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=gallery" class="flex flex-col items-center gap-1">
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

        <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=word" class="flex flex-col items-center gap-1">
            <i class="fa-solid fa-book-bible text-gray-600 text-lg"></i>
            <span class="text-xs text-gray-600">말씀</span>
        </a>

        <?php if ($is_member) { ?>
        <a href="<?php echo G5_BBS_URL; ?>/mypage.php" class="flex flex-col items-center gap-1">
            <i class="fa-regular fa-user text-gray-600 text-lg"></i>
            <span class="text-xs text-gray-600">내 정보</span>
        </a>
        <?php } else { ?>
        <a href="<?php echo G5_BBS_URL; ?>/login.php" class="flex flex-col items-center gap-1">
            <i class="fa-regular fa-user text-gray-600 text-lg"></i>
            <span class="text-xs text-gray-600">로그인</span>
        </a>
        <?php } ?>
    </div>
</nav>

<?php
include_once(G5_PATH.'/tail.sub.php');
