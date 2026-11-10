<?php
// 홈 화면 추가(앱 아이콘)용 태그 + 푸시를 켠 기기의 서비스워커 유지
// bottom_nav.php, notification_widget.php 에서 include (한 페이지에 한 번만 출력)
if (!defined('_GNUBOARD_')) exit;
if (defined('HOLYHILL_PWA_TAGS')) return;
define('HOLYHILL_PWA_TAGS', true);
?>
<script>
(function () {
    var head = document.head;
    function link(rel, href) {
        if (head.querySelector('link[rel="' + rel + '"]')) return;
        var l = document.createElement('link'); l.rel = rel; l.href = href; head.appendChild(l);
    }
    function meta(name, content) {
        if (head.querySelector('meta[name="' + name + '"]')) return;
        var m = document.createElement('meta'); m.name = name; m.content = content; head.appendChild(m);
    }
    link('manifest', <?php echo json_encode(holyhill_url_path(G5_BBS_URL.'/manifest.php')); ?>);
    link('apple-touch-icon', <?php echo json_encode(holyhill_url_path(G5_IMG_URL.'/apple-touch-icon.png')); ?>);
    meta('theme-color', '#ffffff');
    meta('mobile-web-app-capable', 'yes');
    meta('apple-mobile-web-app-capable', 'yes');
    meta('apple-mobile-web-app-title', '성산교회');

    // 알림을 허용한 기기는 서비스워커를 최신으로 유지
    if ('serviceWorker' in navigator && window.Notification && Notification.permission === 'granted') {
        navigator.serviceWorker.register(<?php echo json_encode(holyhill_url_path(G5_BBS_URL.'/push_sw.php')); ?>, { scope: '/' }).catch(function () {});
    }
})();
</script>
