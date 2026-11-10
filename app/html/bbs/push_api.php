<?php
// 웹 푸시 API
//  config       (회원) 공개키, 종류별 설정
//  subscribe    (회원) 이 기기 구독 등록
//  unsubscribe  (회원) 이 기기 구독 해제
//  setting      (회원) 종류별 켜기/끄기 저장
//  test         (회원) 테스트 알림
//  pending      (서비스워커) 구독 주소로 아직 안 보여준 알림 가져오기
//  resubscribe  (서비스워커) 브라우저가 구독 주소를 바꿨을 때 갱신
//  open         (알림 클릭) 읽음 처리 후 해당 글로 이동
include_once('./_common.php');
include_once(G5_BBS_PATH.'/notification.lib.php');

$action = isset($_REQUEST['action']) ? preg_replace('/[^a-z_]/', '', $_REQUEST['action']) : '';

function push_json($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// JSON 본문 (전역 addslashes 대상이 아니므로 직접 이스케이프해서 사용)
function push_body()
{
    static $body = null;
    if ($body === null) {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) $body = array();
    }
    return $body;
}

function push_find_sub($endpoint)
{
    global $g5;
    if (!$endpoint) return null;
    $hash = sha1($endpoint);
    $r = holyhill_push_query("SELECT * FROM `{$g5['push_sub_table']}` WHERE ps_hash = '{$hash}'");
    return $r ? sql_fetch_array($r) : null;
}

$member_actions = array('config', 'subscribe', 'unsubscribe', 'setting', 'test');
if (in_array($action, $member_actions) && !$is_member) {
    push_json(array('ok' => false, 'message' => '로그인이 필요합니다.'), 401);
}
$me = $is_member ? sql_real_escape_string($member['mb_id']) : '';

switch ($action) {

case 'config':
    $keys = holyhill_vapid_keys();
    $settings = holyhill_push_settings_for(array($member['mb_id']));
    $types = array();
    foreach (holyhill_push_types() as $t => $info) {
        if (!empty($info['admin']) && !$is_admin) continue;
        $types[] = array('type' => $t, 'label' => $info['label'], 'enabled' => $settings[$member['mb_id']][$t]);
    }
    push_json(array('ok' => (bool)$keys, 'publicKey' => $keys ? $keys['public'] : '', 'types' => $types,
                    'message' => $keys ? '' : '서버에서 푸시 키를 만들 수 없습니다. (data 폴더 쓰기 권한 확인)'));

case 'subscribe':
    $b = push_body();
    $endpoint = isset($b['endpoint']) ? (string)$b['endpoint'] : '';
    if (!holyhill_push_endpoint_allowed($endpoint) || strlen($endpoint) > 1000) push_json(array('ok' => false, 'message' => '지원하지 않는 푸시 주소입니다.'), 400);
    $hash = sha1($endpoint);
    $ep  = sql_real_escape_string($endpoint);
    $p256 = sql_real_escape_string(substr(isset($b['keys']['p256dh']) ? $b['keys']['p256dh'] : '', 0, 255));
    $auth = sql_real_escape_string(substr(isset($b['keys']['auth']) ? $b['keys']['auth'] : '', 0, 64));
    $ua   = sql_real_escape_string(substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 255));
    // 지금까지의 알림은 이미 본 것으로 시작
    $last = sql_fetch("SELECT IFNULL(MAX(no_id), 0) AS m FROM g5_notifications WHERE no_to_mb_id = '{$me}'");
    $last_id = (int)$last['m'];
    $now = G5_TIME_YMDHIS;
    // 같은 기기에서 다른 계정으로 다시 켜면 그 계정으로 옮김
    holyhill_push_query("INSERT INTO `{$g5['push_sub_table']}`
        (mb_id, ps_hash, ps_endpoint, ps_p256dh, ps_auth, ps_ua, ps_last_no_id, ps_created, ps_updated)
        VALUES ('{$me}', '{$hash}', '{$ep}', '{$p256}', '{$auth}', '{$ua}', {$last_id}, '{$now}', '{$now}')
        ON DUPLICATE KEY UPDATE mb_id = VALUES(mb_id), ps_p256dh = VALUES(ps_p256dh), ps_auth = VALUES(ps_auth),
            ps_ua = VALUES(ps_ua), ps_last_no_id = VALUES(ps_last_no_id), ps_updated = VALUES(ps_updated)");
    push_json(array('ok' => true));

case 'unsubscribe':
    $b = push_body();
    $endpoint = isset($b['endpoint']) ? (string)$b['endpoint'] : '';
    if ($endpoint) {
        $hash = sha1($endpoint);
        holyhill_push_query("DELETE FROM `{$g5['push_sub_table']}` WHERE ps_hash = '{$hash}' AND mb_id = '{$me}'");
    }
    push_json(array('ok' => true));

case 'setting':
    $b = push_body();
    $types = holyhill_push_types();
    $type = isset($b['type']) ? (string)$b['type'] : '';
    if (!isset($types[$type]) || (!empty($types[$type]['admin']) && !$is_admin)) push_json(array('ok' => false, 'message' => '잘못된 설정입니다.'), 400);
    $on = !empty($b['enabled']) ? 1 : 0;
    holyhill_push_query("INSERT INTO `{$g5['push_setting_table']}` (mb_id, ns_type, ns_enabled) VALUES ('{$me}', '{$type}', {$on})
        ON DUPLICATE KEY UPDATE ns_enabled = VALUES(ns_enabled)");
    push_json(array('ok' => true));

case 'test':
    create_notification('test', 'system', $member['mb_id'], '', 0, 0, '푸시 알림이 정상적으로 도착했어요.', G5_BBS_URL.'/notification_settings.php');
    push_json(array('ok' => true));

case 'pending':
    // 서비스워커는 로그인 세션 없이 호출될 수 있으므로 구독 주소(브라우저와 이 서버만 아는 값)로 확인
    $b = push_body();
    $sub = push_find_sub(isset($b['endpoint']) ? (string)$b['endpoint'] : '');
    if (!$sub) push_json(array('ok' => false, 'items' => array()), 404);

    $items = holyhill_push_pending($sub);
    push_json(array('ok' => true, 'items' => $items));

case 'resubscribe':
    $b = push_body();
    $sub = push_find_sub(isset($b['old_endpoint']) ? (string)$b['old_endpoint'] : '');
    $endpoint = isset($b['endpoint']) ? (string)$b['endpoint'] : '';
    if (!$sub || !holyhill_push_endpoint_allowed($endpoint) || strlen($endpoint) > 1000) push_json(array('ok' => false), 400);
    $hash = sha1($endpoint);
    $ep = sql_real_escape_string($endpoint);
    $p256 = sql_real_escape_string(substr(isset($b['keys']['p256dh']) ? $b['keys']['p256dh'] : '', 0, 255));
    $auth = sql_real_escape_string(substr(isset($b['keys']['auth']) ? $b['keys']['auth'] : '', 0, 64));
    holyhill_push_query("UPDATE IGNORE `{$g5['push_sub_table']}` SET ps_hash = '{$hash}', ps_endpoint = '{$ep}', ps_p256dh = '{$p256}', ps_auth = '{$auth}', ps_updated = '".G5_TIME_YMDHIS."' WHERE ps_id = ".(int)$sub['ps_id']);
    push_json(array('ok' => true));

case 'open':
    $no_id = isset($_GET['no_id']) ? (int)$_GET['no_id'] : 0;
    if (!$is_member) {
        goto_url(G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/push_api.php?action=open&no_id='.$no_id));
    }
    $row = sql_fetch("SELECT no_url FROM g5_notifications WHERE no_id = '{$no_id}' AND no_to_mb_id = '{$me}'");
    $url = G5_BBS_URL.'/index.php';
    if ($row) {
        mark_notification_as_read($no_id, $member['mb_id']);
        // 이 사이트 안의 주소로만 이동
        $target = $row['no_url'];
        $site = parse_url(G5_URL);
        $t = parse_url($target);
        if ($target && (!isset($t['host']) || (isset($site['host']) && strcasecmp($t['host'], $site['host']) === 0))) {
            $url = holyhill_url_path($target);
        }
    }
    goto_url($url);

default:
    push_json(array('ok' => false, 'message' => 'unknown action'), 400);
}
