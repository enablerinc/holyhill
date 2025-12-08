<?php
/**
 * UTF8MB4 지원 확장
 * 이모지(Emoji) 저장을 위한 DB charset 설정
 *
 * 이 파일은 extend 폴더에 위치하며 common.php에서 자동으로 로드됩니다.
 */

if (!defined('_GNUBOARD_')) exit;

// DB 연결 후 utf8mb4로 charset 재설정
if (isset($g5['connect_db']) && $g5['connect_db']) {
    // MySQL 연결에 utf8mb4 charset 설정
    @mysqli_set_charset($g5['connect_db'], 'utf8mb4');

    // SET NAMES utf8mb4 실행 (이모지 저장을 위해 필요)
    @sql_query("SET NAMES utf8mb4");
    @sql_query("SET CHARACTER SET utf8mb4");
}
