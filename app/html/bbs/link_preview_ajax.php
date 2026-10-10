<?php
// 링크 미리보기(OG 메타데이터) 조회 + 캐싱 엔드포인트
include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

function lp_out($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

// 전역 addslashes 되어 있으므로 실제 URL 사용 시 해제
$url = isset($_REQUEST['url']) ? trim(stripslashes($_REQUEST['url'])) : '';

if (!$url || !preg_match('#^https?://#i', $url) || mb_strlen($url) > 2000) {
    lp_out(array('ok' => false, 'message' => 'invalid url'));
}

$lp_table = (defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_') . 'link_preview';
$hash = sha1($url);

// 캐시 테이블 보장
sql_query("CREATE TABLE IF NOT EXISTS {$lp_table} (
    lp_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lp_hash CHAR(40) NOT NULL,
    lp_url VARCHAR(2000) NOT NULL,
    lp_final_url VARCHAR(2000) NOT NULL DEFAULT '',
    lp_title VARCHAR(500) NOT NULL DEFAULT '',
    lp_description VARCHAR(1000) NOT NULL DEFAULT '',
    lp_image VARCHAR(2000) NOT NULL DEFAULT '',
    lp_site VARCHAR(255) NOT NULL DEFAULT '',
    lp_status TINYINT NOT NULL DEFAULT 0,
    lp_datetime DATETIME NOT NULL,
    PRIMARY KEY (lp_id),
    UNIQUE KEY lp_hash (lp_hash)
) ENGINE=MyISAM DEFAULT CHARSET=utf8", true);

// 캐시 조회 (성공 30일, 실패 1일 유효)
$row = sql_fetch("SELECT * FROM {$lp_table} WHERE lp_hash = '" . addslashes($hash) . "'");
if ($row) {
    $age = time() - strtotime($row['lp_datetime']);
    $fresh = ($row['lp_status'] == 1 && $age < 2592000) || ($row['lp_status'] == 0 && $age < 86400);
    if ($fresh) {
        lp_out(array(
            'ok' => ($row['lp_status'] == 1),
            'url' => $row['lp_final_url'] ? $row['lp_final_url'] : $row['lp_url'],
            'title' => $row['lp_title'],
            'description' => $row['lp_description'],
            'image' => $row['lp_image'],
            'site' => $row['lp_site'],
            'cached' => true
        ));
    }
}

// SSRF 방지: 내부/사설 IP 차단
$host = parse_url($url, PHP_URL_HOST);
if (!$host) lp_out(array('ok' => false, 'message' => 'bad host'));
$ip = gethostbyname($host);
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    lp_out(array('ok' => false, 'message' => 'blocked host'));
}

// 페이지 가져오기 (리다이렉트 추적, 타임아웃, 다운로드 크기 제한)
$html = '';
$final_url = $url;
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 7,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; HolyhillBot/1.0; +https://holyhill)',
        CURLOPT_HTTPHEADER => array('Accept-Language: ko,en;q=0.8'),
    ));
    if (defined('CURLPROTO_HTTP')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }
    // 다운로드 512KB 제한
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($c, $dltotal, $dlnow) {
        return ($dlnow > 524288) ? 1 : 0;
    });
    $html = curl_exec($ch);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
}

// 인코딩 처리 (euc-kr/cp949 → utf-8)
function lp_to_utf8($html) {
    if (preg_match('/charset\s*=\s*["\']?\s*([a-z0-9\-_]+)/i', substr($html, 0, 4000), $m)) {
        $cs = strtolower($m[1]);
        if (in_array($cs, array('euc-kr', 'ks_c_5601-1987', 'cp949', 'x-windows-949'))) {
            $conv = @iconv('CP949', 'UTF-8//IGNORE', $html);
            if ($conv !== false) return $conv;
        }
    }
    return $html;
}

// 메타 태그 추출 헬퍼
function lp_meta($html, $prop) {
    if (preg_match('/<meta[^>]+(?:property|name)\s*=\s*["\']' . preg_quote($prop, '/') . '["\'][^>]*?content\s*=\s*["\']([^"\']*)["\']/i', $html, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]*?(?:property|name)\s*=\s*["\']' . preg_quote($prop, '/') . '["\']/i', $html, $m)) {
        return trim($m[1]);
    }
    return '';
}

$title = $description = $image = $site = '';
if ($html) {
    $html = lp_to_utf8($html);

    $title = lp_meta($html, 'og:title');
    $description = lp_meta($html, 'og:description');
    $image = lp_meta($html, 'og:image');
    $site = lp_meta($html, 'og:site_name');

    if (!$title && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim($m[1]);
    }
    if (!$description) {
        $description = lp_meta($html, 'description');
    }

    // 엔티티 디코딩
    $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
    $site = html_entity_decode($site, ENT_QUOTES, 'UTF-8');
    $image = html_entity_decode($image, ENT_QUOTES, 'UTF-8');

    // 상대 이미지 경로를 절대 경로로
    if ($image && !preg_match('#^https?://#i', $image)) {
        $pu = parse_url($final_url);
        $scheme = isset($pu['scheme']) ? $pu['scheme'] : 'https';
        $fhost = isset($pu['host']) ? $pu['host'] : '';
        if (strpos($image, '//') === 0) {
            $image = $scheme . ':' . $image;
        } else if (strpos($image, '/') === 0) {
            $image = $scheme . '://' . $fhost . $image;
        } else {
            $base = isset($pu['path']) ? preg_replace('#/[^/]*$#', '/', $pu['path']) : '/';
            $image = $scheme . '://' . $fhost . $base . $image;
        }
    }

    // 길이 제한
    $title = mb_substr($title, 0, 300, 'UTF-8');
    $description = mb_substr($description, 0, 500, 'UTF-8');
    $site = mb_substr($site, 0, 200, 'UTF-8');
}

$status = ($title || $image) ? 1 : 0;

// 캐시 저장 (성공/실패 모두)
$now = date('Y-m-d H:i:s');
sql_query("REPLACE INTO {$lp_table} SET
    lp_hash = '" . addslashes($hash) . "',
    lp_url = '" . addslashes($url) . "',
    lp_final_url = '" . addslashes($final_url) . "',
    lp_title = '" . addslashes($title) . "',
    lp_description = '" . addslashes($description) . "',
    lp_image = '" . addslashes($image) . "',
    lp_site = '" . addslashes($site) . "',
    lp_status = '{$status}',
    lp_datetime = '{$now}'");

lp_out(array(
    'ok' => ($status == 1),
    'url' => $final_url ? $final_url : $url,
    'title' => $title,
    'description' => $description,
    'image' => $image,
    'site' => $site,
    'cached' => false
));
