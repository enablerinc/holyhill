<?php
/**
 * 명예의 전당 페이지 성능 최적화를 위한 인덱스 생성 스크립트
 *
 * 사용법: 브라우저에서 이 파일에 접속하거나 CLI에서 실행
 * 실행 후 이 파일은 삭제하세요.
 */

include_once('./_common.php');

echo "<pre>\n";
echo "=== 명예의 전당 인덱스 생성 스크립트 ===\n\n";

$success_count = 0;
$error_count = 0;

// 1. 포인트 테이블 인덱스
echo "[1] 포인트 테이블 인덱스 생성 중...\n";

// 인덱스 존재 여부 확인 함수
function index_exists($table, $index_name) {
    $result = sql_query("SHOW INDEX FROM {$table} WHERE Key_name = '{$index_name}'", false);
    return $result && sql_num_rows($result) > 0;
}

// 1-1. po_datetime, po_content 복합 인덱스
$point_table = $GLOBALS['g5']['point_table'];

if (!index_exists($point_table, 'idx_point_datetime_content')) {
    $sql = "ALTER TABLE {$point_table} ADD INDEX idx_point_datetime_content (po_datetime, po_content(20))";
    if (sql_query($sql, false)) {
        echo "  - idx_point_datetime_content 생성 완료\n";
        $success_count++;
    } else {
        echo "  - idx_point_datetime_content 생성 실패\n";
        $error_count++;
    }
} else {
    echo "  - idx_point_datetime_content 이미 존재\n";
}

// 1-2. mb_id, po_datetime 복합 인덱스
if (!index_exists($point_table, 'idx_point_mbid_datetime')) {
    $sql = "ALTER TABLE {$point_table} ADD INDEX idx_point_mbid_datetime (mb_id, po_datetime)";
    if (sql_query($sql, false)) {
        echo "  - idx_point_mbid_datetime 생성 완료\n";
        $success_count++;
    } else {
        echo "  - idx_point_mbid_datetime 생성 실패\n";
        $error_count++;
    }
} else {
    echo "  - idx_point_mbid_datetime 이미 존재\n";
}

// 2. 게시판 테이블 인덱스
echo "\n[2] 게시판 테이블 인덱스 생성 중...\n";

$board_list = sql_query("SELECT bo_table FROM {$g5['board_table']}");
while ($board = sql_fetch_array($board_list)) {
    $bo_table = $board['bo_table'];
    $write_table = $g5['write_prefix'] . $bo_table;

    // 테이블 존재 확인
    $table_check = sql_fetch("SELECT 1 FROM information_schema.tables
                              WHERE table_schema = DATABASE()
                              AND table_name = '{$write_table}' LIMIT 1");
    if (!$table_check) continue;

    $index_name = 'idx_write_mbid_datetime';

    if (!index_exists($write_table, $index_name)) {
        $sql = "ALTER TABLE {$write_table} ADD INDEX {$index_name} (mb_id, wr_datetime, wr_is_comment)";
        if (sql_query($sql, false)) {
            echo "  - {$write_table}.{$index_name} 생성 완료\n";
            $success_count++;
        } else {
            echo "  - {$write_table}.{$index_name} 생성 실패\n";
            $error_count++;
        }
    } else {
        echo "  - {$write_table}.{$index_name} 이미 존재\n";
    }
}

echo "\n=== 완료 ===\n";
echo "성공: {$success_count}개\n";
echo "실패: {$error_count}개\n";
echo "\n이 파일을 삭제해주세요: " . __FILE__ . "\n";
echo "</pre>\n";
?>
