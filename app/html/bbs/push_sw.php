<?php
// 서비스워커 (웹 푸시 수신·표시)
// PHP 로 내보내는 이유: nginx 의 .js 7일 캐시를 피하고, 사이트 전체(/)를 범위로 쓰기 위해
include_once('./_common.php');

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Service-Worker-Allowed: /');
?>
// 성산교회 푸시 서비스워커
const API   = <?php echo json_encode(holyhill_url_path(G5_BBS_URL.'/push_api.php')); ?>;
const HOME  = <?php echo json_encode(holyhill_url_path(G5_BBS_URL.'/index.php')); ?>;
const ICON  = <?php echo json_encode(holyhill_url_path(G5_IMG_URL.'/icon-192.png')); ?>;
const BADGE = <?php echo json_encode(holyhill_url_path(G5_IMG_URL.'/push-badge.png')); ?>;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

async function fetchPending() {
    const sub = await self.registration.pushManager.getSubscription();
    if (!sub) return [];
    const res = await fetch(API + '?action=pending', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ endpoint: sub.endpoint }),
        credentials: 'omit',
        cache: 'no-store'
    });
    if (!res.ok) return [];
    const data = await res.json();
    return Array.isArray(data.items) ? data.items : [];
}

self.addEventListener('push', (event) => {
    event.waitUntil((async () => {
        let items = [];
        try { items = await fetchPending(); } catch (e) {}

        if (!items.length) {
            // 이미 다른 푸시에서 보여줬으면 조용히 넘어감, 아니면 기본 안내 (브라우저 규칙상 알림 표시 필요)
            const shown = await self.registration.getNotifications();
            if (shown.length) return;
            items = [{ title: '성산교회', body: '새 알림이 있습니다.', url: HOME, tag: 'holyhill-generic' }];
        }
        for (const it of items) {
            await self.registration.showNotification(it.title || '성산교회', {
                body: it.body || '',
                icon: ICON,
                badge: BADGE,
                tag: it.tag || undefined,
                data: { url: it.url || HOME }
            });
        }
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || HOME;
    event.waitUntil((async () => {
        const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const w of wins) {
            if (new URL(w.url).origin === self.location.origin) {
                try { await w.focus(); await w.navigate(url); return; } catch (e) {}
            }
        }
        await self.clients.openWindow(url);
    })());
});

// 브라우저가 구독 주소를 바꾼 경우 서버에 새 주소 알림
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil((async () => {
        const old = event.oldSubscription;
        let sub = event.newSubscription;
        if (!sub && old && old.options) {
            sub = await self.registration.pushManager.subscribe(old.options);
        }
        if (!sub || !old) return;
        const j = sub.toJSON();
        await fetch(API + '?action=resubscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ old_endpoint: old.endpoint, endpoint: j.endpoint, keys: j.keys }),
            credentials: 'omit'
        });
    })());
});
