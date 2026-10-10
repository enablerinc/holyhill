<?php
// 게시물 수정 전용 핸들러
// - 첨부 추가/삭제 시 bf_no를 0부터 연속 재정렬하고, 본문의 [이미지N]/[동영상N]/[음원N]/[파일N]
//   토큰을 새 순번으로 재매핑하여 미디어가 깨지지 않도록 처리한다.
// - 공용 핸들러 write_update.php 는 건드리지 않는다.
include_once('./_common.php');

// 글쓰기 토큰 재사용 (write_post.php 의 get_write_token 과 짝)
check_write_token($bo_table);

if (!$is_member) {
    alert('로그인이 필요합니다.');
}

$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;
if (!$bo_table || !$wr_id) {
    alert('잘못된 접근입니다.');
}

$return_url = G5_BBS_URL.'/post.php?bo_table='.$bo_table.'&wr_id='.$wr_id;

$write = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$wr_id}' AND wr_is_comment = 0");
if (!$write['wr_id']) {
    alert('게시물이 존재하지 않습니다.', $return_url);
}

// 권한: 본인 또는 관리자
if ($write['mb_id'] != $member['mb_id'] && !$is_admin) {
    alert('수정 권한이 없습니다.', $return_url);
}

// 제목 / 내용 (write_update.php 와 동일한 정리 방식, 전역 addslashes 이미 적용됨)
$wr_subject = isset($_POST['wr_subject']) ? substr(trim($_POST['wr_subject']), 0, 255) : '';
$wr_subject = preg_replace("#[\\\]+$#", "", $wr_subject);
$wr_content = isset($_POST['wr_content']) ? substr(trim($_POST['wr_content']), 0, 65536) : '';
$wr_content = preg_replace("#[\\\]+$#", "", $wr_content);
if (function_exists('normalize_utf8_string')) {
    $wr_subject = normalize_utf8_string($wr_subject);
    $wr_content = normalize_utf8_string($wr_content);
}
if ($wr_subject === '') alert('제목을 입력하세요.', $return_url);
if ($wr_content === '') alert('내용을 입력하세요.', $return_url);

$g5_file = $g5['board_file_table'];

// 확장자 → 본문 토큰 라벨 (post.php 분류 규칙과 일치)
function pe_media_label($source, $bf_type) {
    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $img = array('jpg','jpeg','png','gif','webp','bmp','svg');
    $vid = array('mp4','webm','mov','avi','mkv','ogg');
    $aud = array('mp3','m4a','wav','flac','aac','wma');
    if (in_array($ext, $img) || ($bf_type >= 1 && $bf_type <= 3)) return '이미지';
    if (in_array($ext, $vid)) return '동영상';
    if (in_array($ext, $aud)) return '음원';
    return '파일';
}

// 1. 기존 첨부 로드 (bf_no 순) — 원 본문 번호(ordinal) = 1-based 순서
$orig = array();
$res = sql_query("SELECT * FROM {$g5_file} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' ORDER BY bf_no");
while ($r = sql_fetch_array($res)) {
    if ($r['bf_file']) $orig[] = $r;
}
$M = count($orig);

// 2. 삭제 집합 (bf_file_del[bf_no])
$del = (isset($_POST['bf_file_del']) && is_array($_POST['bf_file_del'])) ? $_POST['bf_file_del'] : array();

// 3. 생존 첨부 + 원→새 순번 매핑
$survivors = array();
$map = array(); // 원 ordinal(1..M) => 새 ordinal(1..S)
$fi = 0;
foreach ($orig as $idx => $r) {
    $ordinal = $idx + 1;
    if (isset($del[$r['bf_no']]) && $del[$r['bf_no']]) {
        // 디스크 파일 및 썸네일 삭제
        $delpath = G5_DATA_PATH.'/file/'.$bo_table.'/'.str_replace('../', '', $r['bf_file']);
        if (file_exists($delpath)) @unlink($delpath);
        if (preg_match("/\.({$config['cf_image_extension']})$/i", $r['bf_file'])) {
            delete_board_thumbnail($bo_table, $r['bf_file']);
        }
        continue;
    }
    $fi++;
    $map[$ordinal] = $fi;
    $survivors[] = $r;
}
$S = count($survivors);

// 4. 새 업로드 개수 사전 체크
$max_upload = ($bo_table == 'gallery') ? 10 : (int)$board['bo_upload_count'];
$req_count = 0;
if (isset($_FILES['bf_file']['name']) && is_array($_FILES['bf_file']['name'])) {
    for ($i = 0; $i < count($_FILES['bf_file']['name']); $i++) {
        if ($_FILES['bf_file']['name'][$i] && is_uploaded_file($_FILES['bf_file']['tmp_name'][$i])) $req_count++;
    }
}
if ($S + $req_count > $max_upload) {
    alert('첨부파일은 최대 '.number_format($max_upload).'개까지 가능합니다. 기존 파일을 삭제한 후 다시 시도해 주세요.', $return_url);
}

// 5. 새 파일 업로드 (write_update.php 검증 방식 재사용)
$newfiles = array();
@mkdir(G5_DATA_PATH.'/file/'.$bo_table, G5_DIR_PERMISSION);
@chmod(G5_DATA_PATH.'/file/'.$bo_table, G5_DIR_PERMISSION);
$chars_array = array_merge(range(0,9), range('a','z'), range('A','Z'));

if (isset($_FILES['bf_file']['name']) && is_array($_FILES['bf_file']['name'])) {
    for ($i = 0; $i < count($_FILES['bf_file']['name']); $i++) {
        $tmp_file = $_FILES['bf_file']['tmp_name'][$i];
        $filename = get_safe_filename($_FILES['bf_file']['name'][$i]);
        if (!$filename || !is_uploaded_file($tmp_file)) continue;
        if ($_FILES['bf_file']['error'][$i] != 0) continue;

        $filesize = (int)$_FILES['bf_file']['size'][$i];
        if (!$is_admin && $filesize > $board['bo_upload_size']) continue;

        $timg = @getimagesize($tmp_file);
        if (preg_match("/\.({$config['cf_image_extension']})$/i", $filename) ||
            preg_match("/\.({$config['cf_flash_extension']})$/i", $filename)) {
            if ($timg[2] < 1 || $timg[2] > 18) continue;
        }

        $source = $filename;
        $safe = preg_replace("/\.(php|pht|phtm|htm|cgi|pl|exe|jsp|asp|inc|phar)/i", "$0-x", $filename);
        shuffle($chars_array);
        $stored = md5(sha1($_SERVER['REMOTE_ADDR'])).'_'.substr(implode('', $chars_array), 0, 8).'_'.replace_filename($safe);
        $dest = G5_DATA_PATH.'/file/'.$bo_table.'/'.$stored;
        if (!@move_uploaded_file($tmp_file, $dest)) continue;
        @chmod($dest, G5_FILE_PERMISSION);

        $newfiles[] = array(
            'source' => $source,
            'file' => $stored,
            'filesize' => $filesize,
            'w' => isset($timg[0]) ? (int)$timg[0] : 0,
            'h' => isset($timg[1]) ? (int)$timg[1] : 0,
            'type' => isset($timg[2]) ? (int)$timg[2] : 0,
        );
    }
}
$K = count($newfiles);

// 6. board_file 재작성 (survivors → new, bf_no 0부터 연속)
sql_query("DELETE FROM {$g5_file} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}'");
$bf_no = 0;
foreach ($survivors as $r) {
    sql_query("INSERT INTO {$g5_file} SET
        bo_table = '{$bo_table}',
        wr_id = '{$wr_id}',
        bf_no = '{$bf_no}',
        bf_source = '".addslashes($r['bf_source'])."',
        bf_file = '".addslashes($r['bf_file'])."',
        bf_content = '".addslashes($r['bf_content'])."',
        bf_fileurl = '".addslashes($r['bf_fileurl'])."',
        bf_thumburl = '".addslashes($r['bf_thumburl'])."',
        bf_storage = '".addslashes($r['bf_storage'])."',
        bf_download = '".(int)$r['bf_download']."',
        bf_filesize = '".(int)$r['bf_filesize']."',
        bf_width = '".(int)$r['bf_width']."',
        bf_height = '".(int)$r['bf_height']."',
        bf_type = '".(int)$r['bf_type']."',
        bf_datetime = '".addslashes($r['bf_datetime'])."'");
    $bf_no++;
}
foreach ($newfiles as $nf) {
    sql_query("INSERT INTO {$g5_file} SET
        bo_table = '{$bo_table}',
        wr_id = '{$wr_id}',
        bf_no = '{$bf_no}',
        bf_source = '".addslashes($nf['source'])."',
        bf_file = '".addslashes($nf['file'])."',
        bf_content = '',
        bf_download = 0,
        bf_filesize = '".(int)$nf['filesize']."',
        bf_width = '".(int)$nf['w']."',
        bf_height = '".(int)$nf['h']."',
        bf_type = '".(int)$nf['type']."',
        bf_datetime = '".G5_TIME_YMDHIS."'");
    $bf_no++;
}
$total_files = $bf_no;

// 7. 본문 토큰 재매핑
//   - 기존 토큰(1..M): 생존하면 새 순번으로, 삭제됐으면 제거
//   - 새 토큰(M+1..M+K): 끝 순번(S+k)으로, 삽입 위치 유지
//   - 범위 밖 토큰: 제거
$seen_new = array();
$wr_content = preg_replace_callback('/\[(이미지|동영상|음원|파일)(\d+)\]/u', function ($m) use ($map, $M, $K, $S, &$seen_new) {
    $label = $m[1];
    $n = (int)$m[2];
    if ($n >= 1 && $n <= $M) {
        return isset($map[$n]) ? '['.$label.$map[$n].']' : '';
    }
    if ($n > $M && $n <= $M + $K) {
        $k = $n - $M;
        $seen_new[$k] = true;
        return '['.$label.($S + $k).']';
    }
    return '';
}, $wr_content);

// 본문에 삽입되지 않은 새 파일은 끝에 토큰 추가 (업로드 순서 유지)
for ($k = 1; $k <= $K; $k++) {
    if (empty($seen_new[$k])) {
        $label = pe_media_label($newfiles[$k-1]['source'], $newfiles[$k-1]['type']);
        $wr_content = rtrim($wr_content)."\n[".$label.($S + $k)."]";
    }
}

// 과도한 빈 줄 정리
$wr_content = preg_replace("/\n{3,}/", "\n\n", $wr_content);

// 8. 게시물 업데이트 (작성일시는 유지)
$wr_seo_title = exist_seo_title_recursive('bbs', generate_seo_title($wr_subject), $write_table, $wr_id);
sql_query("UPDATE {$write_table} SET
    wr_subject = '{$wr_subject}',
    wr_content = '{$wr_content}',
    wr_seo_title = '{$wr_seo_title}',
    wr_file = '{$total_files}'
    WHERE wr_id = '{$wr_id}'");

delete_cache_latest($bo_table);

goto_url($return_url);
