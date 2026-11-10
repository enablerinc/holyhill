<?php
if (!defined('_GNUBOARD_')) exit;

// 웹 푸시 알림
// - 표준 Web Push(VAPID). 외부 서비스 가입·추가 라이브러리 없음
// - 내용 없는(payload 없는) 푸시를 보내고, 서비스워커가 push_api.php 에서 알림 내용을 가져와 표시
// - create_notification() 이 알림을 만들면 받는 사람을 대기열에 넣고,
//   페이지 응답을 보낸 뒤(shutdown) 한꺼번에 발송 → 글쓰기/댓글 속도에 영향 없음
// - 종류별 켜기/끄기는 푸시에만 적용 (사이트 안 알림 목록은 그대로 쌓임)

$g5['push_sub_table']     = G5_TABLE_PREFIX.'push_subscription';
$g5['push_setting_table'] = G5_TABLE_PREFIX.'notification_setting';

define('HOLYHILL_PUSH_KEY_FILE', G5_DATA_PATH.'/push_vapid_keys.php');
// 푸시 서비스 주소만 허용 (임의 주소로 요청을 보내지 않도록)
define('HOLYHILL_PUSH_HOSTS', 'fcm.googleapis.com,android.googleapis.com,updates.push.services.mozilla.com,push.services.mozilla.com,web.push.apple.com,api.push.apple.com,notify.windows.com');

function holyhill_push_types()
{
    return array(
        'comment'    => array('label' => '내 글에 댓글',     'default' => 1),
        'reply'      => array('label' => '내 댓글에 답글',   'default' => 1),
        'mention'    => array('label' => '나를 언급 (멘션)', 'default' => 1),
        'word'       => array('label' => '새 생명말씀',      'default' => 1),
        'good'       => array('label' => '내 글에 좋아요',   'default' => 0),
        'new_member' => array('label' => '신규 회원 가입',   'default' => 1, 'admin' => true),
    );
}

// 테이블이 없으면 만들고 다시 실행
function holyhill_push_query($sql)
{
    global $g5;
    $result = sql_query($sql, false);
    if ($result === false) {
        sql_query("CREATE TABLE IF NOT EXISTS `{$g5['push_sub_table']}` (
            `ps_id` int(11) NOT NULL AUTO_INCREMENT,
            `mb_id` varchar(20) NOT NULL,
            `ps_hash` char(40) NOT NULL,
            `ps_endpoint` text NOT NULL,
            `ps_p256dh` varchar(255) NOT NULL DEFAULT '',
            `ps_auth` varchar(64) NOT NULL DEFAULT '',
            `ps_ua` varchar(255) NOT NULL DEFAULT '',
            `ps_last_no_id` int(11) NOT NULL DEFAULT 0,
            `ps_created` datetime NOT NULL,
            `ps_updated` datetime NOT NULL,
            PRIMARY KEY (`ps_id`),
            UNIQUE KEY `ps_hash` (`ps_hash`),
            KEY `mb_id` (`mb_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='웹 푸시 구독 (기기별)'", false);
        sql_query("CREATE TABLE IF NOT EXISTS `{$g5['push_setting_table']}` (
            `mb_id` varchar(20) NOT NULL,
            `ns_type` varchar(20) NOT NULL,
            `ns_enabled` tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`mb_id`, `ns_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='푸시 알림 종류별 설정'", false);
        $result = sql_query($sql, false);
    }
    return $result;
}

// 회원들의 종류별 설정 (기본값 포함). mb_id => array(type => bool)
function holyhill_push_settings_for($mb_ids)
{
    global $g5;
    $types = holyhill_push_types();
    $out = array();
    foreach ($mb_ids as $id) {
        foreach ($types as $t => $info) $out[$id][$t] = (bool)$info['default'];
    }
    if (!$mb_ids) return $out;

    $in = array();
    foreach ($mb_ids as $id) $in[] = "'".sql_real_escape_string($id)."'";
    $result = holyhill_push_query("SELECT mb_id, ns_type, ns_enabled FROM `{$g5['push_setting_table']}` WHERE mb_id IN (".implode(',', $in).")");
    if ($result) {
        while ($r = sql_fetch_array($result)) {
            if (isset($types[$r['ns_type']])) $out[$r['mb_id']][$r['ns_type']] = (bool)$r['ns_enabled'];
        }
    }
    return $out;
}

// 도메인을 뺀 경로 (http/https 판단을 브라우저에 맡김)
function holyhill_url_path($url)
{
    $p = @parse_url($url);
    $path = isset($p['path']) ? $p['path'] : '/';
    if (!empty($p['query'])) $path .= '?'.$p['query'];
    if (!empty($p['fragment'])) $path .= '#'.$p['fragment'];
    return $path;
}

function holyhill_b64url($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// VAPID 키 (서버 data 폴더에 한 번 만들어 보관, GitHub 에 올라가지 않음)
function holyhill_vapid_keys()
{
    static $keys = null;
    if ($keys !== null) return $keys;

    if (is_file(HOLYHILL_PUSH_KEY_FILE)) {
        $k = include HOLYHILL_PUSH_KEY_FILE;
        if (is_array($k) && !empty($k['private']) && !empty($k['public'])) return $keys = $k;
    }

    $res = @openssl_pkey_new(array('curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC));
    if (!$res || !@openssl_pkey_export($res, $pem)) return $keys = false;
    $d = openssl_pkey_get_details($res);
    $pub = "\x04".str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $k = array('private' => $pem, 'public' => holyhill_b64url($pub));

    $php = "<?php\nif (!defined('_GNUBOARD_')) exit;\n// 웹 푸시 VAPID 키 (자동 생성). 지우면 모든 기기에서 알림을 다시 켜야 합니다.\nreturn ".var_export($k, true).";\n";
    $tmp = HOLYHILL_PUSH_KEY_FILE.'.'.getmypid().'.tmp';
    if (@file_put_contents($tmp, $php, LOCK_EX) === false) return $keys = false;
    @chmod($tmp, 0600);
    // 동시에 두 요청이 만들었으면 먼저 만들어진 키를 사용
    if (is_file(HOLYHILL_PUSH_KEY_FILE)) {
        @unlink($tmp);
        $k2 = include HOLYHILL_PUSH_KEY_FILE;
        return $keys = (is_array($k2) ? $k2 : false);
    }
    @rename($tmp, HOLYHILL_PUSH_KEY_FILE);
    return $keys = $k;
}

function holyhill_vapid_subject()
{
    global $config;
    $mail = isset($config['cf_admin_email']) ? trim($config['cf_admin_email']) : '';
    return filter_var($mail, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$mail : 'mailto:admin@holyhill.net';
}

// ES256 JWT (DER 서명을 r||s 64바이트로 변환)
function holyhill_vapid_jwt($audience, $keys, $now = null)
{
    $now = $now ? $now : time();
    $header  = holyhill_b64url(json_encode(array('typ' => 'JWT', 'alg' => 'ES256')));
    $payload = holyhill_b64url(json_encode(array('aud' => $audience, 'exp' => $now + 12 * 3600, 'sub' => holyhill_vapid_subject()), JSON_UNESCAPED_SLASHES));
    $input = $header.'.'.$payload;
    if (!openssl_sign($input, $der, $keys['private'], OPENSSL_ALGO_SHA256)) return false;
    $raw = holyhill_der_to_raw($der);
    return $raw === false ? false : $input.'.'.holyhill_b64url($raw);
}

function holyhill_der_to_raw($der)
{
    $pos = 0;
    if (ord($der[$pos++]) !== 0x30) return false;
    $len = ord($der[$pos++]);
    if ($len & 0x80) $pos += ($len & 0x7f);
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        if (ord($der[$pos++]) !== 0x02) return false;
        $n = ord($der[$pos++]);
        $int = substr($der, $pos, $n);
        $pos += $n;
        $int = ltrim($int, "\0");
        if (strlen($int) > 32) return false;
        $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
    }
    return $out;
}

function holyhill_push_endpoint_allowed($endpoint)
{
    $u = @parse_url($endpoint);
    if (!$u || empty($u['scheme']) || $u['scheme'] !== 'https' || empty($u['host'])) return false;
    $host = strtolower($u['host']);
    foreach (explode(',', HOLYHILL_PUSH_HOSTS) as $allowed) {
        if ($host === $allowed || substr($host, -strlen('.'.$allowed)) === '.'.$allowed) return true;
    }
    return false;
}

// ── 발송 대기열 ────────────────────────────────────────────
$GLOBALS['holyhill_push_queue'] = array();

// notification.lib.php 의 create_notification() 에서 호출
function holyhill_push_queue($mb_id, $type)
{
    if (!$mb_id) return;
    if (empty($GLOBALS['holyhill_push_queue'])) register_shutdown_function('holyhill_push_flush');
    $GLOBALS['holyhill_push_queue'][$mb_id][$type] = true;
}

// 응답을 먼저 보낸 뒤 대기열 발송
function holyhill_push_flush()
{
    global $g5;
    $queue = $GLOBALS['holyhill_push_queue'];
    $GLOBALS['holyhill_push_queue'] = array();
    if (!$queue) return;

    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    @ignore_user_abort(true);
    @set_time_limit(30);

    $targets = holyhill_push_targets($queue);
    if ($targets) holyhill_push_send($targets);
}

// 대기열(mb_id => 종류들) 중 실제로 보낼 구독 목록 (기기가 있고, 그 종류를 켜 둔 회원)
function holyhill_push_targets($queue)
{
    global $g5;
    $mb_ids = array_keys($queue);
    if (!$mb_ids) return array();
    $in = array();
    foreach ($mb_ids as $id) $in[] = "'".sql_real_escape_string($id)."'";
    $result = holyhill_push_query("SELECT ps_id, mb_id, ps_endpoint FROM `{$g5['push_sub_table']}` WHERE mb_id IN (".implode(',', $in).")");
    if (!$result) return array();
    $subs = array();
    while ($r = sql_fetch_array($result)) $subs[] = $r;
    if (!$subs) return array();

    $settings = holyhill_push_settings_for($mb_ids);
    $targets = array();
    foreach ($subs as $s) {
        foreach ($queue[$s['mb_id']] as $type => $_) {
            if ($type === 'test' || !empty($settings[$s['mb_id']][$type])) { $targets[] = $s; break; }
        }
    }
    return $targets;
}

// 서비스워커가 가져갈 알림: 이 기기에 아직 안 보여준, 켜 둔 종류의 안 읽은 알림 (최근 3건 + 외 N건)
function holyhill_push_pending($sub)
{
    global $g5;
    $settings = holyhill_push_settings_for(array($sub['mb_id']));
    $allowed = array("'test'");
    foreach ($settings[$sub['mb_id']] as $t => $on) if ($on) $allowed[] = "'".$t."'";
    $to = sql_real_escape_string($sub['mb_id']);
    $last = (int)$sub['ps_last_no_id'];

    $rows = array();
    $max_id = $last;
    $result = sql_query("SELECT no_id, no_type, no_content, no_url FROM g5_notifications
        WHERE no_to_mb_id = '{$to}' AND no_id > {$last} AND no_is_read = 0 AND no_type IN (".implode(',', $allowed).")
        ORDER BY no_id DESC LIMIT 20", false);
    if ($result) while ($r = sql_fetch_array($result)) { $rows[] = $r; $max_id = max($max_id, (int)$r['no_id']); }
    if ($max_id > $last) {
        holyhill_push_query("UPDATE `{$g5['push_sub_table']}` SET ps_last_no_id = {$max_id}, ps_updated = '".G5_TIME_YMDHIS."' WHERE ps_id = ".(int)$sub['ps_id']);
    }

    $items = array();
    foreach (array_slice($rows, 0, 3) as $r) {
        $items[] = array(
            'title' => '성산교회',
            'body'  => strip_tags($r['no_content']),
            'url'   => holyhill_url_path(G5_BBS_URL.'/push_api.php?action=open&no_id='.(int)$r['no_id']),
            'tag'   => 'no-'.(int)$r['no_id'],
        );
    }
    if (count($rows) > 3) {
        $items[] = array('title' => '성산교회', 'body' => '새 알림 '.(count($rows) - 3).'건이 더 있습니다.',
                         'url' => holyhill_url_path(G5_BBS_URL.'/index.php'), 'tag' => 'holyhill-more');
    }
    return $items;
}

// 실제 발송 (동시에). 반환: ps_id => HTTP 코드
function holyhill_push_send($subs)
{
    global $g5;
    $keys = holyhill_vapid_keys();
    if (!$keys || !function_exists('curl_multi_init')) return array();

    $mh = curl_multi_init();
    $handles = array();
    $jwts = array();
    foreach ($subs as $s) {
        $endpoint = $s['ps_endpoint'];
        if (!holyhill_push_endpoint_allowed($endpoint)) continue;
        $u = parse_url($endpoint);
        $aud = 'https://'.$u['host'].(isset($u['port']) ? ':'.$u['port'] : '');
        if (!isset($jwts[$aud])) $jwts[$aud] = holyhill_vapid_jwt($aud, $keys);
        if (!$jwts[$aud]) continue;

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => array(
                'TTL: 86400',
                'Urgency: high',
                'Content-Length: 0',
                'Authorization: vapid t='.$jwts[$aud].', k='.$keys['public'],
            ),
        ));
        curl_multi_add_handle($mh, $ch);
        $handles[(int)$s['ps_id']] = $ch;
    }
    if (!$handles) { curl_multi_close($mh); return array(); }

    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running && $status === CURLM_OK);

    $codes = array();
    $gone = array();
    foreach ($handles as $ps_id => $ch) {
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $codes[$ps_id] = $code;
        if ($code === 404 || $code === 410) $gone[] = $ps_id;  // 앱 삭제·권한 해제 등으로 사라진 구독
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    if ($gone) sql_query("DELETE FROM `{$g5['push_sub_table']}` WHERE ps_id IN (".implode(',', $gone).")", false);
    return $codes;
}
