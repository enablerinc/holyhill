<?php
if (!defined('_GNUBOARD_')) exit;
?>

</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<!-- 알림 위젯 -->
<?php if ($is_member) { ?>
<?php include_once(G5_BBS_PATH.'/notification_widget.php'); ?>
<?php } ?>

<?php
include_once(G5_PATH.'/tail.sub.php');
