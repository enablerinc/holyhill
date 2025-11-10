<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * holyhill 테마 설정
 */

// 테마명
define('G5_THEME_NAME', 'holyhill');

// 모바일/PC 사용 설정
// both: 기기에 따라 자동 전환
// pc: PC 화면만 사용 (모바일에서도 PC 화면)
// mobile: 모바일 화면만 사용 (PC에서도 모바일 화면)
define('G5_THEME_DEVICE', 'both');

// 모바일 경로 설정 (테마 내 mobile 폴더 사용)
if (G5_IS_MOBILE) {
    // 모바일 접속 시 테마의 mobile 폴더 사용
    define('G5_THEME_MOBILE_PATH', G5_THEME_PATH.'/mobile');
}

?>
