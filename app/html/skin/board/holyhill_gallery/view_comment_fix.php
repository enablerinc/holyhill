<!-- 기존 파일에서 댓글 입력 부분을 찾아서 아래로 교체 -->

<!-- 댓글 입력 폼 -->
<?php if ($is_member) { ?>
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
    <form name="fviewcomment" id="fviewcomment" method="post" action="<?php echo G5_BBS_URL; ?>/write_comment_update.php" onsubmit="return submitComment(event)">
    <input type="hidden" name="w" value="c">
    <input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
    <input type="hidden" name="wr_id" value="<?php echo $wr_id; ?>">
    <input type="hidden" name="comment_id" value="">
    <input type="hidden" name="sca" value="<?php echo $sca; ?>">
    <input type="hidden" name="sfl" value="<?php echo $sfl; ?>">
    <input type="hidden" name="stx" value="<?php echo $stx; ?>">
    <input type="hidden" name="page" value="<?php echo $page; ?>">
    <?php echo captcha_html(); ?>
    
    <div class="max-w-2xl mx-auto flex items-center gap-3 px-4 py-3">
        <img src="<?php echo $member['mb_photo'] ? G5_DATA_URL.'/member/'.$member['mb_photo'] : G5_THEME_URL.'/img/no-profile.svg'; ?>" 
             class="w-8 h-8 rounded-full object-cover">
        <div class="flex-1 flex items-center bg-gray-100 rounded-full px-4 py-2">
            <input type="text" 
                   name="wr_content" 
                   id="comment-input" 
                   placeholder="댓글을 입력하세요..." 
                   class="flex-1 bg-transparent text-sm focus:outline-none"
                   required>
            <button type="submit" class="ml-2">
                <i class="fa-solid fa-paper-plane text-purple-600 text-lg"></i>
            </button>
        </div>
    </div>
    </form>
</div>

<script>
function submitComment(event) {
    event.preventDefault();
    
    const form = document.getElementById('fviewcomment');
    const content = form.wr_content.value.trim();
    
    if (!content) {
        alert('댓글 내용을 입력해주세요.');
        return false;
    }
    
    if (content.length < 2) {
        alert('댓글은 최소 2자 이상 입력해주세요.');
        return false;
    }
    
    form.submit();
    return true;
}
</script>

<?php } else { ?>
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
    <div class="max-w-2xl mx-auto px-4 py-3 text-center">
        <a href="<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode(G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id); ?>" 
           class="text-sm text-purple-600 hover:text-purple-800">
            로그인하고 댓글 남기기
        </a>
    </div>
</div>
<?php } ?>
