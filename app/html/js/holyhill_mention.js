/* 댓글 멘션 자동완성 (@ 입력 시 회원 목록)
 * - 댓글/답글/수정 입력창에서 @ 뒤에 이름을 치면 회원 목록 표시, 고르면 "@이름 " 입력
 * - 고른 사람은 HolyhillMention.ids(입력창) 으로 서버에 보냄 (동명이인 구분용)
 * - 폼 안의 입력창이면 hidden input[name=mention_ids] 를 자동으로 채움
 */
window.HolyhillMention = (function () {
    var script = document.currentScript;
    var API = script ? script.getAttribute('data-api') : '';
    var SELECTOR = 'textarea, input.reply-input, input#commentInput, input[id^="comment-input-"]';
    var NAME_RE = /(^|[\s(\[>])@([^\s@<>&,.!?;:()\[\]"'`]{1,20})/g;
    var TOKEN_RE = /(^|[\s(\[])@([^\s@]{0,20})$/;

    var picks = new WeakMap();       // 입력창 → { 이름: 회원ID }
    var box = null, target = null, items = [], sel = 0, tokenStart = -1, timer = null, seq = 0;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function highlight(html) {
        return String(html).replace(NAME_RE, '$1<span class="text-blue-600 font-medium">@$2</span>');
    }
    // 사용자가 쓴 글 → 화면용 HTML (이스케이프 + 줄바꿈 + 멘션 강조)
    function render(text) {
        return highlight(esc(text).replace(/\n/g, '<br>'));
    }
    function ids(el) {
        var map = el && picks.get(el);
        if (!map) return '';
        var text = el.value || '', out = [];
        Object.keys(map).forEach(function (name) {
            if (text.indexOf('@' + name) !== -1 && out.indexOf(map[name]) === -1) out.push(map[name]);
        });
        return out.slice(0, 10).join(',');
    }
    function syncHidden(el) {
        if (!el.form) return;
        var h = el.form.querySelector('input[name="mention_ids"]');
        if (!h) {
            h = document.createElement('input');
            h.type = 'hidden'; h.name = 'mention_ids';
            el.form.appendChild(h);
        }
        h.value = ids(el);
    }

    function ensureBox() {
        if (box) return box;
        box = document.createElement('div');
        box.setAttribute('role', 'listbox');
        box.style.cssText = 'position:fixed;z-index:100002;background:#fff;border:1px solid #e5e7eb;border-radius:12px;' +
            'box-shadow:0 8px 24px rgba(0,0,0,.15);max-height:240px;overflow-y:auto;display:none;min-width:200px;';
        box.addEventListener('mousedown', function (e) { e.preventDefault(); });   // 입력창 포커스 유지
        box.addEventListener('click', function (e) {
            var li = e.target.closest('[data-idx]');
            if (li) choose(parseInt(li.getAttribute('data-idx'), 10));
        });
        document.body.appendChild(box);
        return box;
    }
    function hide() {
        if (box) box.style.display = 'none';
        items = []; target = null; tokenStart = -1;
    }
    function place() {
        var r = target.getBoundingClientRect();
        var h = Math.min(240, items.length * 48 + 8);
        var below = window.innerHeight - r.bottom;
        box.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 268)) + 'px';
        box.style.width = Math.min(Math.max(r.width, 220), 320) + 'px';
        box.style.top = (below > h + 8 ? r.bottom + 4 : Math.max(8, r.top - h - 4)) + 'px';
    }
    function draw() {
        ensureBox();
        if (!items.length) { hide(); return; }
        box.innerHTML = items.map(function (it, i) {
            var avatar = it.photo
                ? '<img src="' + esc(it.photo) + '" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0">'
                : '<span style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#c084fc,#f472b6);color:#fff;font-size:12px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0">' + esc(it.name.charAt(0)) + '</span>';
            return '<div data-idx="' + i + '" role="option" style="display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;' +
                (i === sel ? 'background:#f3e8ff;' : '') + '">' + avatar +
                '<span style="font-size:14px;color:#111827">' + esc(it.name) + '</span>' +
                (it.sub ? '<span style="font-size:12px;color:#9ca3af">' + esc(it.sub) + '</span>' : '') + '</div>';
        }).join('');
        box.style.display = 'block';
        place();
    }
    function choose(i) {
        var it = items[i];
        if (!it || !target) return;
        var el = target, pos = el.selectionStart, v = el.value;
        var insert = '@' + it.name + ' ';
        el.value = v.slice(0, tokenStart) + insert + v.slice(pos);
        var caret = tokenStart + insert.length;
        el.setSelectionRange(caret, caret);
        var map = picks.get(el) || {};
        map[it.name] = it.id;
        picks.set(el, map);
        syncHidden(el);
        hide();
        el.focus();
    }
    function search(el, q) {
        var my = ++seq;
        fetch(API + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { items: [] }; })
            .then(function (d) {
                if (my !== seq || target !== el) return;   // 더 최근 입력이 있으면 무시
                items = d.items || []; sel = 0; draw();
            })
            .catch(function () {});
    }

    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.matches || !el.matches(SELECTOR)) return;
        syncHidden(el);
        var m = el.value.slice(0, el.selectionStart).match(TOKEN_RE);
        if (!m) { if (target === el) hide(); return; }
        target = el;
        tokenStart = el.selectionStart - m[2].length - 1;
        clearTimeout(timer);
        var q = m[2];
        timer = setTimeout(function () { search(el, q); }, 150);
    }, true);

    // 목록이 열려 있을 때 Enter/Tab 은 선택 (댓글 전송 막기), 위/아래 이동, Esc 닫기
    document.addEventListener('keydown', function (e) {
        if (!box || box.style.display === 'none' || e.target !== target) return;
        if (e.isComposing) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            sel = (sel + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length;
            draw();
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            choose(sel);
        } else if (e.key === 'Escape') {
            hide();
        } else {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
    }, true);

    document.addEventListener('focusout', function (e) {
        if (e.target === target) setTimeout(function () { if (document.activeElement !== target) hide(); }, 150);
    }, true);
    window.addEventListener('scroll', function () { if (target && box && box.style.display !== 'none') place(); }, true);
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f && f.querySelectorAll) Array.prototype.forEach.call(f.querySelectorAll(SELECTOR), syncHidden);
    }, true);

    return { ids: ids, render: render, highlight: highlight };
})();
