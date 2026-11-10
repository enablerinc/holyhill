<?php
include_once('./_common.php');

if (!$is_member) {
    alert('로그인 후 이용해 주세요.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/notification_settings.php'));
}

$g5['title'] = '알림 설정';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    <link rel="manifest" href="<?php echo holyhill_url_path(G5_BBS_URL.'/manifest.php'); ?>">
    <link rel="apple-touch-icon" href="<?php echo holyhill_url_path(G5_IMG_URL.'/apple-touch-icon.png'); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="성산교회">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>window.FontAwesomeConfig = { autoReplaceSvg: 'nest' };</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; background-color: #EEF3F8; }
        .switch { position: relative; width: 44px; height: 26px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch span { position: absolute; inset: 0; background: #d1d5db; border-radius: 9999px; transition: .2s; cursor: pointer; }
        .switch span:before { content: ''; position: absolute; width: 20px; height: 20px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; box-shadow: 0 1px 2px rgba(0,0,0,.2); }
        .switch input:checked + span { background: #7c3aed; }
        .switch input:checked + span:before { transform: translateX(18px); }
        .switch input:disabled + span { opacity: .5; cursor: default; }
    </style>
</head>
<body class="min-h-screen">

<header class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-gray-200">
    <div class="max-w-2xl mx-auto flex items-center justify-between px-4 py-3">
        <a href="javascript:history.back()" class="w-8 text-gray-700"><i class="fa-solid fa-arrow-left text-xl"></i></a>
        <h1 class="text-base font-semibold text-gray-800">알림 설정</h1>
        <div class="w-8"></div>
    </div>
</header>

<main class="pt-16 pb-28 max-w-2xl mx-auto px-4">

    <!-- 이 기기 푸시 -->
    <section class="mt-4 bg-white rounded-2xl shadow-sm p-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-semibold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-mobile-screen text-purple-600"></i> 이 기기에서 푸시 알림 받기</h2>
                <p id="push-status" class="text-sm text-gray-500 mt-1">확인 중…</p>
            </div>
            <button id="push-toggle" type="button" class="hidden flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold"></button>
        </div>
        <div id="push-guide" class="hidden mt-3 text-sm text-gray-700 bg-purple-50 border border-purple-100 rounded-xl p-3 leading-relaxed"></div>
        <button id="push-test" type="button" class="hidden mt-3 w-full py-2.5 rounded-xl border border-purple-200 text-purple-700 text-sm font-medium hover:bg-purple-50">
            <i class="fa-regular fa-paper-plane mr-1"></i> 테스트 알림 보내기
        </button>
        <p class="text-xs text-gray-400 mt-3">휴대폰·PC 등 기기마다 따로 켜야 합니다.</p>
    </section>

    <!-- 종류별 -->
    <section class="mt-4 bg-white rounded-2xl shadow-sm">
        <div class="px-4 pt-4 pb-2">
            <h2 class="font-semibold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-sliders text-purple-600"></i> 받을 알림 종류</h2>
            <p class="text-xs text-gray-400 mt-1">끈 종류도 사이트 안의 알림 목록에는 그대로 쌓입니다.</p>
        </div>
        <div id="type-list" class="divide-y divide-gray-100">
            <p class="px-4 py-4 text-sm text-gray-400">불러오는 중…</p>
        </div>
    </section>

    <!-- 홈 화면 추가 안내 -->
    <section class="mt-4 bg-white rounded-2xl shadow-sm p-4 text-sm text-gray-700 leading-relaxed">
        <h2 class="font-semibold text-gray-800 mb-2 flex items-center gap-2"><i class="fa-solid fa-circle-info text-purple-600"></i> 휴대폰 홈 화면에 앱처럼 추가하기</h2>
        <p class="font-medium text-gray-800">아이폰 (iOS 16.4 이상)</p>
        <p class="text-gray-600 mb-2">Safari 에서 아래쪽 <i class="fa-solid fa-arrow-up-from-bracket"></i> 공유 버튼 → <b>홈 화면에 추가</b> → 홈 화면의 <b>성산교회</b> 아이콘으로 열고, 이 화면에서 알림을 켜주세요. 아이폰은 이렇게 추가해야만 푸시 알림을 받을 수 있습니다.</p>
        <p class="font-medium text-gray-800">안드로이드</p>
        <p class="text-gray-600">크롬에서 바로 알림을 켤 수 있습니다. 메뉴(⋮) → <b>홈 화면에 추가</b> 하면 앱처럼 쓸 수 있습니다.</p>
    </section>
</main>

<script>
(function () {
    const API = <?php echo json_encode(holyhill_url_path(G5_BBS_URL.'/push_api.php')); ?>;
    const SW  = <?php echo json_encode(holyhill_url_path(G5_BBS_URL.'/push_sw.php')); ?>;
    const $status = document.getElementById('push-status');
    const $toggle = document.getElementById('push-toggle');
    const $guide  = document.getElementById('push-guide');
    const $test   = document.getElementById('push-test');
    const $types  = document.getElementById('type-list');

    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    let cfg = null;

    function api(action, body) {
        return fetch(API + '?action=' + action, {
            method: body ? 'POST' : 'GET',
            headers: body ? { 'Content-Type': 'application/json' } : {},
            body: body ? JSON.stringify(body) : undefined,
            credentials: 'same-origin'
        }).then(r => r.json());
    }
    function keyBytes(b64) {
        const pad = '='.repeat((4 - b64.length % 4) % 4);
        const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(raw, c => c.charCodeAt(0));
    }
    function sameKey(sub) {
        const k = sub.options && sub.options.applicationServerKey;
        if (!k || !cfg) return true;
        const a = new Uint8Array(k), b = keyBytes(cfg.publicKey);
        return a.length === b.length && a.every((v, i) => v === b[i]);
    }
    async function getSub() {
        const reg = await navigator.serviceWorker.getRegistration('/');
        return reg ? reg.pushManager.getSubscription() : null;
    }
    function show(text, button, guide, test) {
        $status.textContent = text;
        if (button) {
            $toggle.className = 'flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold ' +
                (button.on ? 'bg-gray-100 text-gray-700' : 'bg-purple-600 text-white');
            $toggle.textContent = button.label;
            $toggle.onclick = button.onclick;
            $toggle.disabled = false;
        } else {
            $toggle.className = 'hidden';
        }
        $guide.className = guide ? 'mt-3 text-sm text-gray-700 bg-purple-50 border border-purple-100 rounded-xl p-3 leading-relaxed' : 'hidden';
        $guide.innerHTML = guide || '';
        $test.className = test ? 'mt-3 w-full py-2.5 rounded-xl border border-purple-200 text-purple-700 text-sm font-medium hover:bg-purple-50' : 'hidden';
    }

    async function render() {
        if (!supported) {
            if (isIOS && !standalone) {
                show('홈 화면에 추가한 뒤 사용할 수 있습니다.', null,
                    'Safari 아래쪽 <b>공유 버튼</b> → <b>홈 화면에 추가</b> → 홈 화면의 <b>성산교회</b> 아이콘으로 열어서 이 화면에서 켜주세요.');
            } else {
                show('이 브라우저는 푸시 알림을 지원하지 않습니다.', null,
                    '안드로이드는 <b>크롬</b>, 아이폰은 <b>홈 화면에 추가한 성산교회 앱</b>에서 켜주세요.');
            }
            return;
        }
        if (cfg && !cfg.ok) { show(cfg.message || '서버 설정 문제로 사용할 수 없습니다.', null); return; }
        if (Notification.permission === 'denied') {
            show('알림이 차단되어 있습니다.', null,
                isIOS ? '아이폰 <b>설정</b> → <b>알림</b> → <b>성산교회</b> 에서 알림을 허용한 뒤 이 화면을 다시 열어주세요.'
                      : '주소창 왼쪽 <b>자물쇠(사이트 설정)</b> → <b>알림 허용</b> 으로 바꾼 뒤 이 화면을 다시 열어주세요.');
            return;
        }
        const sub = await getSub();
        if (sub && Notification.permission === 'granted') {
            show('켜져 있습니다.', { on: true, label: '끄기', onclick: disable }, null, true);
        } else {
            show('꺼져 있습니다.', { on: false, label: '켜기', onclick: enable });
        }
    }

    async function enable() {
        $toggle.disabled = true;
        try {
            // 사용자 터치 직후 바로 권한 요청 (아이폰 필수)
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') { await render(); return; }
            const reg = await navigator.serviceWorker.register(SW, { scope: '/' });
            await navigator.serviceWorker.ready;
            let sub = await reg.pushManager.getSubscription();
            if (sub && !sameKey(sub)) { await sub.unsubscribe(); sub = null; }
            if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(cfg.publicKey) });
            const res = await api('subscribe', sub.toJSON());
            if (!res.ok) throw new Error(res.message || '등록 실패');
        } catch (e) {
            alert('알림을 켜지 못했습니다.\n' + (e && e.message ? e.message : ''));
        }
        await render();
    }

    async function disable() {
        $toggle.disabled = true;
        try {
            const sub = await getSub();
            if (sub) {
                await api('unsubscribe', { endpoint: sub.endpoint });
                await sub.unsubscribe();
            }
        } catch (e) {}
        await render();
    }

    $test.onclick = async function () {
        $test.disabled = true;
        await api('test', {}).catch(() => {});
        $test.textContent = '보냈습니다. 잠시 후 알림이 도착합니다.';
        setTimeout(() => { $test.disabled = false; $test.innerHTML = '<i class="fa-regular fa-paper-plane mr-1"></i> 테스트 알림 보내기'; }, 4000);
    };

    function renderTypes() {
        $types.innerHTML = '';
        cfg.types.forEach(t => {
            const row = document.createElement('label');
            row.className = 'flex items-center justify-between px-4 py-3.5 cursor-pointer';
            row.innerHTML = '<span class="text-sm text-gray-800"></span><span class="switch"><input type="checkbox"><span></span></span>';
            row.querySelector('.text-sm').textContent = t.label;
            const input = row.querySelector('input');
            input.checked = !!t.enabled;
            input.onchange = async function () {
                input.disabled = true;
                const want = input.checked;
                try {
                    const res = await api('setting', { type: t.type, enabled: want });
                    if (!res.ok) throw 0;
                } catch (e) { input.checked = !want; alert('저장하지 못했습니다.'); }
                input.disabled = false;
            };
            $types.appendChild(row);
        });
    }

    api('config').then(async (c) => {
        cfg = c;
        if (cfg.types) renderTypes();
        await render();
        // 이 기기가 이미 켜져 있으면 서버 기록도 맞춰둠 (계정을 바꿔 로그인한 경우 등)
        if (supported && cfg.ok && Notification.permission === 'granted') {
            const sub = await getSub();
            if (sub && sameKey(sub)) api('subscribe', sub.toJSON()).catch(() => {});
        }
    }).catch(() => { $status.textContent = '설정을 불러오지 못했습니다.'; });
})();
</script>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>
</body>
</html>
