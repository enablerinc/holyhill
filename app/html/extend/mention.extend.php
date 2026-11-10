<?php
if (!defined('_GNUBOARD_')) exit;

// 댓글 멘션 (@이름)
// - 입력창에서 목록으로 고른 사람: 본문에 @그이름이 남아 있으면 알림 (동명이인 구분)
// - 직접 타이핑한 @이름: 같은 이름의 회원이 한 명뿐일 때만 알림
// - 댓글 하나에 최대 HOLYHILL_MENTION_MAX 명, 자기 자신·이미 댓글/답글 알림을 받는 사람은 제외
// - 수정할 때는 새로 추가된 사람에게만 알림
define('HOLYHILL_MENTION_MAX', 10);
define('HOLYHILL_MENTION_RE', '/(^|[\s(\[>])@([^\s@<>&,.!?;:()\[\]"\'`]{1,20})/u');

function holyhill_member_display_name($row)
{
    return !empty($row['mb_name']) ? $row['mb_name'] : $row['mb_nick'];
}

// 본문의 @이름 목록
function holyhill_mention_names($content)
{
    if (!preg_match_all(HOLYHILL_MENTION_RE, (string)$content, $m)) return array();
    return array_values(array_unique($m[2]));
}

// 멘션 대상 확정: mb_id => 이름
function holyhill_mention_resolve($content, $posted_ids)
{
    global $g5;
    $names = holyhill_mention_names($content);
    if (!$names) return array();
    $name_set = array_flip($names);
    $found = array();

    // 1) 목록에서 고른 사람
    $ids = array();
    foreach (explode(',', (string)$posted_ids) as $id) {
        $id = preg_replace('/[^a-zA-Z0-9_]/', '', $id);
        if ($id !== '') $ids[$id] = true;
    }
    $ids = array_slice(array_keys($ids), 0, HOLYHILL_MENTION_MAX);
    if ($ids) {
        $result = sql_query("SELECT mb_id, mb_name, mb_nick FROM {$g5['member_table']}
            WHERE mb_id IN ('".implode("','", $ids)."') AND mb_level > 1 AND mb_leave_date = '' AND mb_intercept_date = ''", false);
        if ($result) while ($r = sql_fetch_array($result)) {
            $name = holyhill_member_display_name($r);
            if (isset($name_set[$name])) {
                $found[$r['mb_id']] = $name;
                unset($name_set[$name]);
            }
        }
    }

    // 2) 직접 타이핑한 이름 (동명이인이 없을 때만)
    if ($name_set && count($found) < HOLYHILL_MENTION_MAX) {
        $in = array();
        foreach (array_keys($name_set) as $n) $in[] = "'".sql_real_escape_string($n)."'";
        $in = implode(',', $in);
        $result = sql_query("SELECT mb_id, mb_name, mb_nick FROM {$g5['member_table']}
            WHERE (mb_name IN ({$in}) OR (mb_name = '' AND mb_nick IN ({$in})))
              AND mb_level > 1 AND mb_leave_date = '' AND mb_intercept_date = ''", false);
        $by_name = array();
        if ($result) while ($r = sql_fetch_array($result)) $by_name[holyhill_member_display_name($r)][] = $r['mb_id'];
        foreach ($by_name as $name => $list) {
            if (isset($name_set[$name]) && count($list) === 1) $found[$list[0]] = $name;
        }
    }

    return array_slice($found, 0, HOLYHILL_MENTION_MAX, true);
}

// 멘션 알림 보내기. 반환: 보낸 수
// $skip_ids: 이미 댓글/답글 알림을 받는 사람 (알림 중복 방지)
// $old_content: 수정이면 수정 전 내용 (새로 추가된 사람에게만)
function holyhill_mention_notify($content, $posted_ids, $from_mb_id, $bo_table, $wr_id, $comment_id, $url, $skip_ids = array(), $old_content = null)
{
    global $member;
    if (!$from_mb_id || !function_exists('create_notification')) return 0;

    $targets = holyhill_mention_resolve($content, $posted_ids);
    if (!$targets) return 0;

    $before = ($old_content !== null) ? array_flip(holyhill_mention_names($old_content)) : array();
    $from_nick = !empty($member['mb_name']) ? $member['mb_name'] : (!empty($member['mb_nick']) ? $member['mb_nick'] : $from_mb_id);

    $preview = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$content)));
    if (mb_strlen($preview, 'UTF-8') > 40) $preview = mb_substr($preview, 0, 40, 'UTF-8').'…';
    $text = "{$from_nick}님이 댓글에서 회원님을 언급했습니다: \"{$preview}\"";

    $sent = 0;
    foreach ($targets as $to => $name) {
        if ($to === $from_mb_id || in_array($to, (array)$skip_ids, true)) continue;
        if (isset($before[$name])) continue;
        if (create_notification('mention', $from_mb_id, $to, $bo_table, (int)$wr_id, (int)$comment_id, $text, $url)) $sent++;
    }
    return $sent;
}

// 화면 표시: 이미 이스케이프된 HTML 의 @이름을 파란색으로
function holyhill_mention_highlight($html)
{
    return preg_replace(HOLYHILL_MENTION_RE, '$1<span class="text-blue-600 font-medium">@$2</span>', (string)$html);
}
