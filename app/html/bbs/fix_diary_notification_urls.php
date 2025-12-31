<?php
/**
 * 감사일기 알림 URL 수정 스크립트
 * 기존 diary 게시판 알림의 URL을 post.php에서 gratitude_user.php로 변경
 *
 * 사용법: 브라우저에서 한 번 실행 후 삭제
 */
include_once('./_common.php');

// 관리자만 실행 가능
if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

echo "<pre>";
echo "=== 감사일기 알림 URL 수정 시작 ===\n\n";

// 수정 대상 조회
$sql = "SELECT no_id, no_url, no_wr_id
        FROM g5_notifications
        WHERE no_bo_table = 'diary'
        AND no_url LIKE '%post.php%'";
$result = sql_query($sql);

$count = 0;
$updated = 0;

while ($row = sql_fetch_array($result)) {
    $count++;
    $no_id = $row['no_id'];
    $old_url = $row['no_url'];
    $wr_id = $row['no_wr_id'];

    // 원글 작성자 조회
    $write = sql_fetch("SELECT mb_id FROM g5_write_diary WHERE wr_id = '{$wr_id}'");

    if ($write && $write['mb_id']) {
        $new_url = G5_BBS_URL . '/gratitude_user.php?mb_id=' . urlencode($write['mb_id']) . '&wr_id=' . $wr_id;

        $update_sql = "UPDATE g5_notifications
                       SET no_url = '" . sql_real_escape_string($new_url) . "'
                       WHERE no_id = '{$no_id}'";

        if (sql_query($update_sql)) {
            $updated++;
            echo "수정 완료 (ID: {$no_id})\n";
            echo "  이전: {$old_url}\n";
            echo "  이후: {$new_url}\n\n";
        }
    } else {
        echo "수정 실패 (ID: {$no_id}) - 원글을 찾을 수 없음 (wr_id: {$wr_id})\n\n";
    }
}

echo "=== 완료 ===\n";
echo "검색된 알림: {$count}개\n";
echo "수정된 알림: {$updated}개\n";
echo "</pre>";

echo "<p><strong>이 스크립트를 삭제해주세요!</strong></p>";
