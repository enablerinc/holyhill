<?php
if (!defined('_GNUBOARD_')) exit;

include_once(G5_THEME_PATH.'/head.php');
?>

<!-- 오늘의 말씀 위젯 -->
<div class="max-w-2xl mx-auto">
    <section id="daily-word" class="mx-4 mb-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-2xl p-6 shadow-lg">
        <?php
        $word_sql = "SELECT wr_id, wr_subject, wr_content, wr_datetime, wr_name
                     FROM {$g5['write_prefix']}word 
                     WHERE wr_is_comment = 0
                     ORDER BY wr_id DESC 
                     LIMIT 1";
        $word_result = sql_query($word_sql);
        
        if ($word_result && $word = sql_fetch_array($word_result)) {
            $word_content = strip_tags($word['wr_content']);
            $word_content = str_replace('&nbsp;', ' ', $word_content);
            $word_content = trim($word_content);
            $word_content = cut_str($word_content, 120);
            ?>
            <div class="text-center">
                <h3 class="text-sm font-medium text-purple-900 mb-2 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-book-bible text-purple-600"></i>
                    오늘의 말씀
                </h3>
                <p class="text-base font-medium text-gray-800 leading-relaxed mb-2">
                    "<?php echo $word_content; ?>"
                </p>
                <p class="text-xs text-purple-600 mb-3">
                    <?php echo date('Y년 m월 d일', strtotime($word['wr_datetime'])); ?> · <?php echo $word['wr_name']; ?>
                </p>
                <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=word&wr_id=<?php echo $word['wr_id']; ?>" 
                   class="inline-block text-sm text-purple-600 hover:text-purple-800 font-medium">
                    전체 보기 →
                </a>
            </div>
            <?php
        } else {
            ?>
            <div class="text-center">
                <h3 class="text-sm font-medium text-purple-900 mb-2 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-book-bible text-purple-600"></i>
                    오늘의 말씀
                </h3>
                <p class="text-base font-medium text-gray-600 leading-relaxed mb-3">
                    아직 등록된 말씀이 없습니다
                </p>
                <?php if ($is_admin) { ?>
                <a href="<?php echo G5_BBS_URL; ?>/write.php?bo_table=word" 
                   class="inline-block text-sm text-purple-600 hover:text-purple-800 font-medium">
                    첫 말씀 등록하기 →
                </a>
                <?php } ?>
            </div>
            <?php
        }
        ?>
    </section>

    <!-- 피드 섹션 (중요: wr_is_comment = 0 조건 추가!) -->
    <section id="feed" class="space-y-4">
        <?php
        // ✅ WHERE wr_is_comment = 0 추가 (게시글만 가져오기)
        $feed_sql = "SELECT * FROM {$g5['write_prefix']}gallery 
                     WHERE wr_is_comment = 0
                     ORDER BY wr_id DESC 
                     LIMIT 20";
        $feed_result = sql_query($feed_sql);
        
        if ($feed_result && sql_num_rows($feed_result) > 0) {
            while ($feed = sql_fetch_array($feed_result)) {
                // 작성자 정보
                $feed_nick = $feed['wr_name'] ? $feed['wr_name'] : '알 수 없음';
                $feed_photo = G5_THEME_URL.'/img/no-profile.svg';
                
                if ($feed['mb_id']) {
                    $mb_result = sql_query("SELECT mb_nick, mb_photo FROM {$g5['member_table']} WHERE mb_id = '{$feed['mb_id']}'");
                    if ($mb_result && $mb = sql_fetch_array($mb_result)) {
                        $feed_nick = $mb['mb_nick'];
                        if ($mb['mb_photo']) {
                            $feed_photo = G5_DATA_URL.'/member/'.$mb['mb_photo'];
                        }
                    }
                }
                
                // 첨부 이미지
                $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']} 
                                        WHERE bo_table = 'gallery' 
                                        AND wr_id = '{$feed['wr_id']}' 
                                        AND bf_type BETWEEN 1 AND 3
                                        ORDER BY bf_no 
                                        LIMIT 1");
                $img = sql_fetch_array($img_result);
                $feed_img = $img ? G5_DATA_URL.'/file/gallery/'.$img['bf_file'] : '';
                
                $comment_cnt = $feed['wr_comment'];
                $good_cnt = $feed['wr_good'];
                ?>
                
                <article class="bg-white rounded-2xl shadow-md overflow-hidden mx-4">
                    <div class="p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img src="<?php echo $feed_photo; ?>" 
                                 class="w-10 h-10 rounded-full object-cover"
                                 alt="<?php echo $feed_nick; ?>">
                            <div>
                                <h4 class="font-semibold text-gray-800 text-sm"><?php echo $feed_nick; ?></h4>
                                <p class="text-xs text-gray-500"><?php echo date('n월 j일', strtotime($feed['wr_datetime'])); ?></p>
                            </div>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-ellipsis"></i>
                        </button>
                    </div>

                    <?php if ($feed_img) { ?>
                    <div class="w-full">
                        <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>">
                            <img src="<?php echo $feed_img; ?>"
                                 class="w-full h-auto max-h-[500px] object-cover cursor-pointer hover:opacity-95 transition-opacity"
                                 alt="<?php echo $feed['wr_subject']; ?>">
                        </a>
                    </div>
                    <?php } else { ?>
                    <!-- 이미지가 없을 때 텍스트 콘텐츠를 카드로 표시 -->
                    <div class="w-full">
                        <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>"
                           class="block bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 p-8 min-h-[300px] flex items-center justify-center cursor-pointer hover:opacity-95 transition-opacity">
                            <div class="text-center">
                                <p class="text-lg font-medium text-gray-800 leading-relaxed line-clamp-6">
                                    <?php
                                    $text_content = strip_tags($feed['wr_content']);
                                    $text_content = str_replace('&nbsp;', ' ', $text_content);
                                    $text_content = trim($text_content);
                                    echo cut_str($text_content, 200);
                                    ?>
                                </p>
                            </div>
                        </a>
                    </div>
                    <?php } ?>

                    <div class="p-4">
                        <div class="flex items-center gap-4 mb-3">
                            <button onclick="location.href='<?php echo G5_BBS_URL; ?>/board.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>'" 
                                    class="flex items-center gap-2">
                                <i class="fa-solid fa-heart text-red-500 text-xl"></i>
                                <span class="text-sm text-gray-700">아멘 <?php echo number_format($good_cnt); ?>개</span>
                            </button>
                            <button onclick="location.href='<?php echo G5_BBS_URL; ?>/board.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>'" 
                                    class="flex items-center gap-2">
                                <i class="fa-regular fa-comment text-gray-700 text-xl"></i>
                                <span class="text-sm text-gray-700"><?php echo number_format($comment_cnt); ?></span>
                            </button>
                            <button class="flex items-center gap-2">
                                <i class="fa-solid fa-praying-hands text-purple-500 text-xl"></i>
                                <span class="text-sm text-gray-700">기도</span>
                            </button>
                        </div>

                        <div class="mb-2">
                            <span class="font-semibold text-sm mr-2"><?php echo $feed_nick; ?></span>
                            <span class="text-sm text-gray-800">
                                <?php 
                                $subject = $feed['wr_subject'];
                                echo $subject; 
                                ?>
                            </span>
                        </div>

                        <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>" 
                           class="text-xs text-gray-500 hover:text-gray-700">
                            댓글 모두 보기 →
                        </a>
                    </div>
                </article>
                
                <?php
            }
        } else {
            ?>
            <div class="mx-4 p-8 bg-white rounded-2xl shadow-md text-center">
                <i class="fa-regular fa-images text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-600 mb-4">아직 등록된 피드가 없습니다</p>
                <?php if ($is_member) { ?>
                <a href="<?php echo G5_BBS_URL; ?>/write.php?bo_table=gallery" 
                   class="inline-block px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    첫 번째 피드 작성하기
                </a>
                <?php } ?>
            </div>
            <?php
        }
        ?>
    </section>
</div>

<div id="floating-attendance" 
     onclick="alert('출석 체크 기능은 추후 구현됩니다')"
     class="fixed bottom-24 right-4 w-14 h-14 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full shadow-lg flex items-center justify-center cursor-pointer hover:scale-110 transition-transform z-40">
    <i class="fa-solid fa-check text-white text-lg"></i>
</div>

<style>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

<?php include_once(G5_THEME_PATH.'/tail.php'); ?>
