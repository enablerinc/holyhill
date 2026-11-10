<?php
// 홈 화면에 앱처럼 추가하기 위한 웹 앱 매니페스트
include_once('./_common.php');

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo json_encode(array(
    'name'             => '성산교회',
    'short_name'       => '성산교회',
    'lang'             => 'ko',
    'start_url'        => holyhill_url_path(G5_BBS_URL.'/index.php'),
    'scope'            => '/',
    'display'          => 'standalone',
    'background_color' => '#EEF3F8',
    'theme_color'      => '#ffffff',
    'icons'            => array(
        array('src' => holyhill_url_path(G5_IMG_URL.'/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'),
        array('src' => holyhill_url_path(G5_IMG_URL.'/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'),
    ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
