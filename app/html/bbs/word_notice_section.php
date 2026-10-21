<?php
// 말씀 게시판 공지사항 섹션 (word_feed.php, word_list.php 공용)
// 필요 변수: $board, $write_table
if (!defined('_GNUBOARD_')) exit;

$word_notice_list = array();
if (!empty($board['bo_notice'])) {
    $word_notice_ids = array_filter(array_map('intval', explode(',', $board['bo_notice'])));
    if (!empty($word_notice_ids)) {
        $word_notice_ids_str = implode(',', $word_notice_ids);
        $word_notice_result = sql_query("SELECT wr_id, wr_subject, wr_datetime FROM {$write_table} WHERE wr_id IN ({$word_notice_ids_str}) AND wr_is_comment = 0 ORDER BY FIELD(wr_id, {$word_notice_ids_str})");
        while ($word_notice_row = sql_fetch_array($word_notice_result)) {
            $word_notice_list[] = $word_notice_row;
        }
    }
}

if (!empty($word_notice_list)) { ?>
<!-- 공지사항 섹션 -->
<section class="px-4 pt-4">
    <div class="flex items-center gap-2 mb-3">
        <i class="fa-solid fa-bullhorn text-amber-500"></i>
        <h2 class="text-base font-semibold text-gray-800">공지사항</h2>
    </div>
    <div class="space-y-2">
        <?php foreach ($word_notice_list as $word_notice) { ?>
        <a href="<?php echo G5_BBS_URL; ?>/word_view.php?wr_id=<?php echo $word_notice['wr_id']; ?>" class="block">
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 hover:bg-amber-100 transition-colors">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500 text-white">공지</span>
                    <h3 class="flex-1 font-medium text-gray-900 text-sm line-clamp-1"><?php echo get_text($word_notice['wr_subject']); ?></h3>
                    <span class="text-xs text-gray-500 flex-shrink-0"><?php echo date('Y.m.d', strtotime($word_notice['wr_datetime'])); ?></span>
                </div>
            </div>
        </a>
        <?php } ?>
    </div>
</section>
<?php } ?>
