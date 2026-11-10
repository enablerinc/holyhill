<?php
// 댓글 멘션 자동완성 스크립트 (댓글이 있는 화면에서 include)
if (!defined('_GNUBOARD_')) exit;
if (!$is_member || defined('HOLYHILL_MENTION_TAGS')) return;
define('HOLYHILL_MENTION_TAGS', true);
$mention_js = G5_PATH.'/js/holyhill_mention.js';
?>
<script src="<?php echo holyhill_url_path(G5_JS_URL.'/holyhill_mention.js'); ?>?v=<?php echo is_file($mention_js) ? filemtime($mention_js) : 1; ?>"
        data-api="<?php echo holyhill_url_path(G5_BBS_URL.'/member_search_ajax.php'); ?>"></script>
