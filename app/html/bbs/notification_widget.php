<?php
/**
 * 알림 위젯 - 모든 페이지에서 사용 가능
 */
if (!defined('_GNUBOARD_')) exit;
?>

<!-- 알림 패널 -->
<div id="notification-panel" class="fixed top-16 right-0 w-full max-w-md h-screen bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out z-50 overflow-y-auto">
    <div class="sticky top-0 bg-white border-b border-gray-200 p-4 z-10">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-xl font-bold text-gray-800">알림</h2>
            <i id="close-notification" class="fa-solid fa-times text-gray-600 text-xl cursor-pointer hover:text-gray-800"></i>
        </div>
        <button id="mark-all-read" class="text-sm text-purple-600 hover:text-purple-800 font-medium">
            모두 읽음 처리
        </button>
    </div>
    <div id="notification-list" class="divide-y divide-gray-100">
        <!-- 알림 목록이 여기에 동적으로 추가됩니다 -->
        <div class="flex items-center justify-center py-20">
            <div class="text-center">
                <i class="fa-regular fa-bell text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-500">알림이 없습니다</p>
            </div>
        </div>
    </div>
</div>

<script>
// 알림 시스템
(function() {
    const notificationBell = document.getElementById('notification-bell');
    const notificationBadge = document.getElementById('notification-badge');
    const notificationPanel = document.getElementById('notification-panel');
    const closeNotification = document.getElementById('close-notification');
    const notificationList = document.getElementById('notification-list');
    const markAllRead = document.getElementById('mark-all-read');

    if (!notificationBell) return; // 알림 벨이 없으면 실행 안함

    // 알림 패널 열기/닫기
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationPanel.classList.toggle('translate-x-full');
        if (!notificationPanel.classList.contains('translate-x-full')) {
            loadNotifications();
        }
    });

    closeNotification.addEventListener('click', function() {
        notificationPanel.classList.add('translate-x-full');
    });

    // 패널 외부 클릭시 닫기
    document.addEventListener('click', function(e) {
        if (!notificationPanel.contains(e.target) &&
            !notificationBell.contains(e.target) &&
            !notificationPanel.classList.contains('translate-x-full')) {
            notificationPanel.classList.add('translate-x-full');
        }
    });

    // 알림 개수 가져오기
    function loadNotificationCount() {
        fetch('<?php echo G5_BBS_URL; ?>/notification_api.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    notificationBadge.textContent = data.count > 99 ? '99+' : data.count;
                    notificationBadge.classList.remove('hidden');
                } else {
                    notificationBadge.classList.add('hidden');
                }
            })
            .catch(error => console.error('알림 개수 로딩 오류:', error));
    }

    // 알림 목록 가져오기
    function loadNotifications() {
        fetch('<?php echo G5_BBS_URL; ?>/notification_api.php?action=list&limit=50')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    renderNotifications(data.notifications);
                } else {
                    notificationList.innerHTML = `
                        <div class="flex items-center justify-center py-20">
                            <div class="text-center">
                                <i class="fa-regular fa-bell text-gray-300 text-5xl mb-4"></i>
                                <p class="text-gray-500">알림이 없습니다</p>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => console.error('알림 로딩 오류:', error));
    }

    // 알림 렌더링
    function renderNotifications(notifications) {
        notificationList.innerHTML = '';

        notifications.forEach(notification => {
            const notificationItem = createNotificationItem(notification);
            notificationList.appendChild(notificationItem);
        });
    }

    // 알림 아이템 생성
    function createNotificationItem(notification) {
        const div = document.createElement('div');
        div.className = `p-4 hover:bg-gray-50 cursor-pointer transition-colors ${notification.no_is_read == '0' ? 'bg-purple-50' : ''}`;

        const iconClass = getNotificationIcon(notification.no_type);
        const timeAgo = getTimeAgo(notification.no_datetime);

        div.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <i class="${iconClass} text-2xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm ${notification.no_is_read == '0' ? 'font-semibold' : 'font-normal'} text-gray-800 mb-1">
                        ${notification.no_content}
                    </p>
                    <p class="text-xs text-gray-500">${timeAgo}</p>
                </div>
                ${notification.no_is_read == '0' ? '<div class="flex-shrink-0"><div class="w-2 h-2 bg-purple-600 rounded-full"></div></div>' : ''}
            </div>
        `;

        div.addEventListener('click', function() {
            handleNotificationClick(notification);
        });

        return div;
    }

    // 알림 타입별 아이콘
    function getNotificationIcon(type) {
        switch(type) {
            case 'comment':
                return 'fa-regular fa-comment text-blue-500';
            case 'reply':
                return 'fa-solid fa-reply text-green-500';
            case 'good':
                return 'fa-solid fa-heart text-red-500';
            case 'word':
                return 'fa-solid fa-book-bible text-purple-600';
            default:
                return 'fa-regular fa-bell text-gray-500';
        }
    }

    // 시간 계산
    function getTimeAgo(datetime) {
        const now = new Date();
        const past = new Date(datetime);
        const diff = Math.floor((now - past) / 1000);

        if (diff < 60) return '방금 전';
        if (diff < 3600) return Math.floor(diff / 60) + '분 전';
        if (diff < 86400) return Math.floor(diff / 3600) + '시간 전';
        if (diff < 604800) return Math.floor(diff / 86400) + '일 전';
        return past.toLocaleDateString('ko-KR');
    }

    // 알림 클릭 처리
    function handleNotificationClick(notification) {
        // 읽음 처리
        if (notification.no_is_read == '0') {
            markAsRead(notification.no_id);
        }

        // 해당 페이지로 이동
        if (notification.no_url) {
            window.location.href = notification.no_url;
        }
    }

    // 알림 읽음 처리
    function markAsRead(notificationId) {
        const formData = new FormData();
        formData.append('no_id', notificationId);

        fetch('<?php echo G5_BBS_URL; ?>/notification_api.php?action=read', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotificationCount();
            }
        })
        .catch(error => console.error('읽음 처리 오류:', error));
    }

    // 모두 읽음 처리
    markAllRead.addEventListener('click', function(e) {
        e.stopPropagation();
        fetch('<?php echo G5_BBS_URL; ?>/notification_api.php?action=read_all', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
                loadNotificationCount();
            }
        })
        .catch(error => console.error('모두 읽음 처리 오류:', error));
    });

    // 페이지 로드시 알림 개수 확인
    <?php if ($is_member) { ?>
    loadNotificationCount();
    // 30초마다 알림 개수 갱신
    setInterval(loadNotificationCount, 30000);
    <?php } ?>
})();
</script>
