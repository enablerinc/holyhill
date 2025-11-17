<?php
/**
 * 대댓글 부모 ID 저장을 위한 마이그레이션 스크립트
 * 실행 방법: 브라우저에서 http://yourdomain.com/bbs/migration_add_comment_parent.php 접속
 * 주의: 실행 후 이 파일은 삭제하세요!
 */

// 보안을 위해 관리자만 실행 가능하도록 설정
$admin_password = 'your_temp_password_here'; // 임시 비밀번호 설정 (실행 후 파일 삭제할 것)

if (!isset($_GET['password']) || $_GET['password'] !== $admin_password) {
    die('접근 권한이 없습니다. ?password=your_temp_password_here 를 URL에 추가하세요.');
}

include_once('./_common.php');

if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

echo "<h2>대댓글 부모 ID 컬럼 추가 마이그레이션</h2>";
echo "<hr>";

// 모든 게시판 목록 가져오기
$sql = "SELECT bo_table FROM {$g5['board_table']}";
$result = sql_query($sql);

$success_count = 0;
$skip_count = 0;
$error_count = 0;

while ($row = sql_fetch_array($result)) {
    $bo_table = $row['bo_table'];
    $write_table = $g5['write_prefix'] . $bo_table;

    echo "<br><strong>[{$bo_table}]</strong> 게시판 처리 중...<br>";

    // 테이블이 존재하는지 확인
    $table_exists = sql_query("SHOW TABLES LIKE '{$write_table}'");
    if (sql_num_rows($table_exists) == 0) {
        echo "→ 테이블이 존재하지 않습니다. 건너뜀.<br>";
        $skip_count++;
        continue;
    }

    // wr_comment_parent 컬럼이 이미 있는지 확인
    $column_exists = sql_query("SHOW COLUMNS FROM {$write_table} LIKE 'wr_comment_parent'");
    if (sql_num_rows($column_exists) > 0) {
        echo "→ wr_comment_parent 컬럼이 이미 존재합니다. 건너뜀.<br>";
        $skip_count++;
        continue;
    }

    // 컬럼 추가
    $alter_sql = "ALTER TABLE `{$write_table}`
                  ADD COLUMN `wr_comment_parent` int(11) NOT NULL DEFAULT 0 COMMENT '부모 댓글 ID'
                  AFTER `wr_comment_reply`";

    if (sql_query($alter_sql)) {
        echo "→ <span style='color:green;'>✓ wr_comment_parent 컬럼 추가 성공!</span><br>";
        $success_count++;

        // 기존 대댓글 데이터 마이그레이션 (wr_comment_reply 기반으로 부모 댓글 찾기)
        echo "→ 기존 대댓글 데이터 마이그레이션 중...<br>";

        $comments_sql = "SELECT wr_id, wr_parent, wr_comment_reply
                         FROM {$write_table}
                         WHERE wr_is_comment = 1
                         AND LENGTH(wr_comment_reply) > 10";
        $comments_result = sql_query($comments_sql);

        $migrated = 0;
        while ($comment = sql_fetch_array($comments_result)) {
            $parent_reply = substr($comment['wr_comment_reply'], 0, 10);

            // 부모 댓글 찾기
            $parent_sql = "SELECT wr_id FROM {$write_table}
                          WHERE wr_parent = '{$comment['wr_parent']}'
                          AND wr_is_comment = 1
                          AND wr_comment_reply = '{$parent_reply}'";
            $parent_result = sql_fetch($parent_sql);

            if ($parent_result) {
                $update_sql = "UPDATE {$write_table}
                              SET wr_comment_parent = '{$parent_result['wr_id']}'
                              WHERE wr_id = '{$comment['wr_id']}'";
                sql_query($update_sql);
                $migrated++;
            }
        }

        echo "→ <span style='color:blue;'>✓ {$migrated}개의 기존 대댓글 데이터 마이그레이션 완료!</span><br>";

    } else {
        echo "→ <span style='color:red;'>✗ 컬럼 추가 실패: " . sql_error() . "</span><br>";
        $error_count++;
    }
}

echo "<hr>";
echo "<h3>마이그레이션 완료!</h3>";
echo "성공: {$success_count}개<br>";
echo "건너뜀: {$skip_count}개<br>";
echo "실패: {$error_count}개<br>";
echo "<br>";
echo "<strong style='color:red;'>중요: 마이그레이션이 완료되었으면 보안을 위해 이 파일(migration_add_comment_parent.php)을 즉시 삭제하세요!</strong>";
?>
