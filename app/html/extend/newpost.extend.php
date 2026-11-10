<?php
if (!defined('_GNUBOARD_')) exit;

// 새 글 표시 (N)
// - 최근 HOLYHILL_NEW_DAYS 일 안에 올라온 글 중, 내가 쓰지 않았고 아직 열어보지 않은 글
// - 글을 열면 읽음 기록이 남고 N 이 사라짐
// - 읽음 기록은 기준 기간이 지나면 의미가 없으므로 주기적으로 정리
define('HOLYHILL_NEW_DAYS', 3);
define('HOLYHILL_NEW_BOARDS', 'gallery,diary,word');   // 하단 메뉴 빨간 점 대상
define('HOLYHILL_NEW_COUNT_TTL', 60);                  // 하단 메뉴 개수 캐시(초)

$g5['post_read_table'] = G5_TABLE_PREFIX.'post_read';

// 읽음 테이블이 없으면 만들고 다시 실행
function holyhill_post_read_query($sql)
{
    global $g5;
    $result = sql_query($sql, false);
    if ($result === false) {
        sql_query("CREATE TABLE IF NOT EXISTS `{$g5['post_read_table']}` (
            `mb_id` varchar(20) NOT NULL,
            `bo_table` varchar(20) NOT NULL,
            `wr_id` int(11) NOT NULL,
            `read_at` datetime NOT NULL,
            PRIMARY KEY (`mb_id`, `bo_table`, `wr_id`),
            KEY `read_at` (`read_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='새 글 표시용 읽음 기록'", false);
        $result = sql_query($sql, false);
    }
    return $result;
}

function holyhill_new_cutoff()
{
    return date('Y-m-d H:i:s', G5_SERVER_TIME - HOLYHILL_NEW_DAYS * 86400);
}

// 목록의 글들 중 새 글인 wr_id 를 키로 하는 배열 (쿼리 1회)
// $rows 의 각 행에는 wr_id, mb_id, wr_datetime 이 있어야 함
function holyhill_new_post_ids($bo_table, $rows)
{
    global $g5, $member;

    $new = array();
    if (empty($member['mb_id']) || empty($rows)) return $new;

    $cutoff = holyhill_new_cutoff();
    foreach ($rows as $row) {
        if (!empty($row['wr_is_comment'])) continue;
        if ($row['mb_id'] === $member['mb_id']) continue;
        if ($row['wr_datetime'] < $cutoff) continue;
        $new[(int)$row['wr_id']] = true;
    }
    if (!$new) return $new;

    $me = sql_real_escape_string($member['mb_id']);
    $bo = sql_real_escape_string($bo_table);
    $result = holyhill_post_read_query("SELECT wr_id FROM `{$g5['post_read_table']}`
        WHERE mb_id = '{$me}' AND bo_table = '{$bo}' AND wr_id IN (".implode(',', array_keys($new)).")");
    if ($result) {
        while ($r = sql_fetch_array($result)) unset($new[(int)$r['wr_id']]);
    }
    return $new;
}

// 읽음 처리 (글 상세 화면에서 호출)
function holyhill_mark_read($bo_table, $wr_ids)
{
    global $g5, $member;
    if (empty($member['mb_id'])) return;

    $values = array();
    $me = sql_real_escape_string($member['mb_id']);
    $bo = sql_real_escape_string($bo_table);
    foreach ((array)$wr_ids as $id) {
        $id = (int)$id;
        if ($id > 0) $values[] = "('{$me}', '{$bo}', {$id}, '".G5_TIME_YMDHIS."')";
    }
    if (!$values) return;

    holyhill_post_read_query("INSERT IGNORE INTO `{$g5['post_read_table']}` (mb_id, bo_table, wr_id, read_at) VALUES ".implode(',', $values));
    holyhill_after_mark_read();
}

// 한 사람의 최근 글을 모두 읽음 처리 (감사일기 개인 페이지처럼 글이 모두 펼쳐지는 화면)
function holyhill_mark_read_author($bo_table, $author_mb_id)
{
    global $g5, $member;
    if (empty($member['mb_id']) || !$author_mb_id || $author_mb_id === $member['mb_id']) return;

    $me = sql_real_escape_string($member['mb_id']);
    $bo = sql_real_escape_string($bo_table);
    $author = sql_real_escape_string($author_mb_id);
    $write_table = $g5['write_prefix'].preg_replace('/[^a-z0-9_]/i', '', $bo_table);
    holyhill_post_read_query("INSERT IGNORE INTO `{$g5['post_read_table']}` (mb_id, bo_table, wr_id, read_at)
        SELECT '{$me}', '{$bo}', wr_id, '".G5_TIME_YMDHIS."' FROM {$write_table}
        WHERE mb_id = '{$author}' AND wr_is_comment = 0 AND wr_datetime >= '".holyhill_new_cutoff()."'");
    holyhill_after_mark_read();
}

function holyhill_after_mark_read()
{
    global $g5;
    set_session('ss_newpost_counts', '');  // 하단 메뉴 빨간 점 다시 계산
    // 가끔(1%) 오래된 읽음 기록 정리
    if (mt_rand(1, 100) === 1) {
        $old = date('Y-m-d H:i:s', G5_SERVER_TIME - (HOLYHILL_NEW_DAYS + 1) * 86400);
        sql_query("DELETE FROM `{$g5['post_read_table']}` WHERE read_at < '{$old}'", false);
    }
}

// 게시판별 안 읽은 새 글 수 (하단 메뉴 빨간 점). 쿼리 1회, 회원별 60초 캐시
function holyhill_new_counts()
{
    global $g5, $member;

    $boards = explode(',', HOLYHILL_NEW_BOARDS);
    $counts = array_fill_keys($boards, 0);
    if (empty($member['mb_id'])) return $counts;

    $cache = get_session('ss_newpost_counts');
    if (is_array($cache) && isset($cache['t']) && G5_SERVER_TIME - $cache['t'] < HOLYHILL_NEW_COUNT_TTL) {
        return $cache['c'];
    }

    $me = sql_real_escape_string($member['mb_id']);
    $cutoff = holyhill_new_cutoff();
    $parts = array();
    foreach ($boards as $bo) {
        // 최근 글 100개만 살펴봄 (wr_is_comment, wr_id 인덱스 사용)
        $parts[] = "SELECT '{$bo}' AS bo, COUNT(*) AS cnt
            FROM (SELECT wr_id, mb_id, wr_datetime FROM {$g5['write_prefix']}{$bo}
                  WHERE wr_is_comment = 0 ORDER BY wr_id DESC LIMIT 100) t
            LEFT JOIN `{$g5['post_read_table']}` r ON r.mb_id = '{$me}' AND r.bo_table = '{$bo}' AND r.wr_id = t.wr_id
            WHERE t.wr_datetime >= '{$cutoff}' AND t.mb_id <> '{$me}' AND r.wr_id IS NULL";
    }
    $result = holyhill_post_read_query(implode(' UNION ALL ', $parts));
    if ($result) {
        while ($r = sql_fetch_array($result)) $counts[$r['bo']] = (int)$r['cnt'];
    }

    set_session('ss_newpost_counts', array('t' => G5_SERVER_TIME, 'c' => $counts));
    return $counts;
}

// 배지 HTML
function holyhill_new_badge($style = 'corner')
{
    if ($style === 'inline') {
        return '<span class="inline-flex items-center justify-center w-4 h-4 mr-1 rounded-full bg-red-500 text-white text-[9px] font-bold align-middle" aria-label="새 글">N</span>';
    }
    return '<span class="absolute top-1.5 left-1.5 z-10 inline-flex items-center justify-center w-[18px] h-[18px] rounded-full bg-red-500 text-white text-[10px] font-bold shadow" aria-label="새 글">N</span>';
}
