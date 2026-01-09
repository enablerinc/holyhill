<?php
/**
 * g5_login 테이블 마이그레이션
 * - lo_ip UNIQUE 제약 조건 제거 (같은 IP에서 여러 사용자 지원)
 * - 이 스크립트는 한 번만 실행하면 됩니다.
 */

include_once('./_common.php');

if ($is_admin != 'super') {
    die('최고관리자만 실행할 수 있습니다.');
}

echo "<h2>g5_login 테이블 마이그레이션</h2>";
echo "<pre>";

// 1. 현재 테이블 구조 확인
echo "1. 현재 테이블 구조 확인...\n";
$result = sql_query("SHOW INDEX FROM {$g5['login_table']}");
$has_unique_ip = false;
while ($row = sql_fetch_array($result)) {
    echo "  - Index: {$row['Key_name']}, Column: {$row['Column_name']}, Non_unique: {$row['Non_unique']}\n";
    if ($row['Key_name'] == 'lo_ip_unique' && $row['Column_name'] == 'lo_ip') {
        $has_unique_ip = true;
    }
}

// 2. UNIQUE 제약 조건 제거
if ($has_unique_ip) {
    echo "\n2. lo_ip UNIQUE 제약 조건 제거...\n";
    $result = sql_query("ALTER TABLE {$g5['login_table']} DROP INDEX lo_ip_unique");
    if ($result) {
        echo "  - 성공! lo_ip_unique 인덱스 제거됨\n";
    } else {
        echo "  - 실패: " . mysqli_error($connect_db) . "\n";
    }
} else {
    echo "\n2. lo_ip_unique 인덱스가 이미 존재하지 않습니다. (스킵)\n";
}

// 3. 기존 중복 IP 레코드 정리 (로그인한 사용자만 유지)
echo "\n3. 기존 중복 레코드 정리...\n";

// 기존 데이터 모두 삭제 (깨끗하게 시작)
$result = sql_query("TRUNCATE TABLE {$g5['login_table']}");
if ($result) {
    echo "  - 기존 접속 기록 초기화 완료 (사용자들이 다시 접속하면 자동 생성됨)\n";
} else {
    echo "  - 경고: 기존 데이터 정리 실패\n";
}

// 4. 인덱스 추가 (mb_id에 인덱스 추가하여 검색 성능 향상)
echo "\n4. mb_id 인덱스 확인/추가...\n";
$result = sql_query("SHOW INDEX FROM {$g5['login_table']} WHERE Key_name = 'idx_mb_id'");
if (sql_num_rows($result) == 0) {
    $result = sql_query("ALTER TABLE {$g5['login_table']} ADD INDEX idx_mb_id (mb_id)");
    if ($result) {
        echo "  - mb_id 인덱스 추가 완료\n";
    } else {
        echo "  - mb_id 인덱스 추가 실패 (이미 존재할 수 있음)\n";
    }
} else {
    echo "  - mb_id 인덱스가 이미 존재합니다\n";
}

// 5. 최종 테이블 구조 확인
echo "\n5. 최종 테이블 구조:\n";
$result = sql_query("SHOW INDEX FROM {$g5['login_table']}");
while ($row = sql_fetch_array($result)) {
    echo "  - Index: {$row['Key_name']}, Column: {$row['Column_name']}, Non_unique: {$row['Non_unique']}\n";
}

echo "\n</pre>";
echo "<h3 style='color: green;'>마이그레이션 완료!</h3>";
echo "<p>이제 같은 IP에서 여러 사용자가 동시에 접속해도 모두 표시됩니다.</p>";
echo "<p><a href='index.php'>홈으로 돌아가기</a></p>";
