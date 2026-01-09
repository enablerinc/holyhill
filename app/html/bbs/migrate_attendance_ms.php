<?php
/**
 * 출석 밀리세컨즈 컬럼 추가 마이그레이션
 * 이 파일을 한 번 실행하여 po_datetime_ms 컬럼을 추가합니다.
 */
include_once('./_common.php');

if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

// po_datetime_ms 컬럼이 있는지 확인
$check_column = sql_query("SHOW COLUMNS FROM {$g5['point_table']} LIKE 'po_datetime_ms'");
if (sql_num_rows($check_column) == 0) {
    // 컬럼 추가
    $sql = "ALTER TABLE {$g5['point_table']} ADD COLUMN po_datetime_ms SMALLINT UNSIGNED DEFAULT 0 AFTER po_datetime";
    $result = sql_query($sql);

    if ($result) {
        // 인덱스 추가 (출석 순위 쿼리 최적화)
        sql_query("ALTER TABLE {$g5['point_table']} ADD INDEX idx_attendance (po_content(20), po_datetime, po_datetime_ms)");
        echo "<h2>마이그레이션 완료!</h2>";
        echo "<p>po_datetime_ms 컬럼이 추가되었습니다.</p>";
        echo "<p>이제 출석 시 밀리세컨즈까지 기록됩니다.</p>";
    } else {
        echo "<h2>오류 발생</h2>";
        echo "<p>컬럼 추가 중 오류가 발생했습니다.</p>";
    }
} else {
    echo "<h2>이미 완료됨</h2>";
    echo "<p>po_datetime_ms 컬럼이 이미 존재합니다.</p>";
}

echo "<br><a href='./index.php'>홈으로 돌아가기</a>";
