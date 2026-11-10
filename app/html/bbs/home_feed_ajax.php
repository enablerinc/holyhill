<?php
// 메인 화면 '성산 샘터' 목록 (한 페이지 9개 + 페이지 번호)
// - index.php 에서 include 하여 첫 페이지를 그림
// - 페이지 번호를 누르면 이 파일을 AJAX 로 직접 호출하여 목록만 교체
if (!defined('_GNUBOARD_')) {
    include_once('./_common.php');
    define('HOME_FEED_DIRECT', true);
}
include_once(G5_LIB_PATH.'/thumbnail.lib.php');

if (!function_exists('home_feed_render')) {

define('HOME_FEED_PER_PAGE', 9);      // 3열 x 3줄
define('HOME_FEED_PAGE_BLOCK', 5);    // 페이지 번호 묶음 크기
define('HOME_FEED_THUMB_SIZE', 320);  // 타일 썸네일 크기(px)
define('HOME_FEED_THUMB_BUDGET', 3);  // 요청당 새로 만드는 썸네일 최대 개수 (첫 로딩 지연 방지)

// 첨부 이미지의 캐시 썸네일 URL. 새로 만들 썸네일이 예산을 넘으면 원본 URL 사용
function home_feed_thumb_url($bf_file, &$budget)
{
    $dir = G5_DATA_PATH.'/file/gallery';
    $url = G5_DATA_URL.'/file/gallery';
    $size = HOME_FEED_THUMB_SIZE;

    $ext = strtolower(pathinfo($bf_file, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') $ext = 'jpg';
    $base = preg_replace("/\.[^\.]+$/i", "", $bf_file);
    $cached = is_file("{$dir}/thumb-{$base}_{$size}x{$size}.{$ext}");

    if ($cached || $budget > 0) {
        if (!$cached) $budget--;
        $thumb = thumbnail($bf_file, $dir, $dir, $size, $size, false, true);
        if ($thumb) return $url.'/'.$thumb;
    }
    return $url.'/'.$bf_file;
}

// 페이지 HTML (타일 그리드 + 페이지 번호). 게시물이 없으면 빈 문자열
function home_feed_render($page)
{
    global $g5;

    $per_page = HOME_FEED_PER_PAGE;
    $write_table = $g5['write_prefix'].'gallery';

    // 1) 전체 개수 (wr_is_comment 인덱스 사용)
    $cnt = sql_fetch("SELECT COUNT(*) AS cnt FROM {$write_table} WHERE wr_is_comment = 0");
    $total = isset($cnt['cnt']) ? (int)$cnt['cnt'] : 0;
    if ($total < 1) return '';

    $total_pages = (int)ceil($total / $per_page);
    $page = min(max(1, (int)$page), $total_pages);
    $offset = ($page - 1) * $per_page;

    // 2) 이 페이지의 게시물 9개
    $posts = array();
    $wr_ids = array();
    $mb_ids = array();
    $result = sql_query("SELECT wr_id, wr_subject, wr_content, wr_link1, wr_name, mb_id, wr_datetime, wr_comment, wr_good
                         FROM {$write_table}
                         WHERE wr_is_comment = 0
                         ORDER BY wr_id DESC
                         LIMIT {$offset}, {$per_page}");
    while ($row = sql_fetch_array($result)) {
        $posts[] = $row;
        $wr_ids[] = (int)$row['wr_id'];
        if ($row['mb_id']) $mb_ids[$row['mb_id']] = true;
    }

    // 3) 작성자 이름 한 번에
    $names = array();
    if ($mb_ids) {
        $in = array();
        foreach (array_keys($mb_ids) as $id) $in[] = "'".sql_real_escape_string($id)."'";
        $result = sql_query("SELECT mb_id, mb_name, mb_nick FROM {$g5['member_table']} WHERE mb_id IN (".implode(',', $in).")");
        while ($m = sql_fetch_array($result)) {
            $names[$m['mb_id']] = $m['mb_name'] ? $m['mb_name'] : $m['mb_nick'];
        }
    }

    // 4) 첨부파일 한 번에
    $files = array();
    if ($wr_ids) {
        $result = sql_query("SELECT wr_id, bf_file, bf_type FROM {$g5['board_file_table']}
                             WHERE bo_table = 'gallery' AND wr_id IN (".implode(',', $wr_ids).")
                             ORDER BY wr_id, bf_no");
        while ($f = sql_fetch_array($result)) {
            if ($f['bf_file']) $files[(int)$f['wr_id']][] = $f;
        }
    }

    $image_exts = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $video_exts = array('mp4', 'webm', 'mov', 'avi', 'mkv', 'wmv');
    $audio_exts = array('mp3', 'm4a', 'wav', 'flac', 'aac', 'wma', 'ogg');
    $doc_exts   = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp', 'hwpx', 'txt', 'zip', 'rar', '7z');
    $yt_patterns = array(
        '/https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]+)/i'
    );
    $thumb_budget = HOME_FEED_THUMB_BUDGET;

    ob_start();
    ?>
    <div class="grid grid-cols-3 gap-2">
    <?php foreach ($posts as $feed) {
        $wr_id = (int)$feed['wr_id'];
        $title = get_text(strip_tags($feed['wr_subject']));
        $writer = ($feed['mb_id'] && isset($names[$feed['mb_id']])) ? $names[$feed['mb_id']] : $feed['wr_name'];
        $writer = get_text($writer ? $writer : '알 수 없음');

        // YouTube 썸네일 (hqdefault: 항상 존재하고 maxres 보다 가벼움)
        $video_id = '';
        $search = $feed['wr_link1'].' '.$feed['wr_content'];
        foreach ($yt_patterns as $p) {
            if (preg_match($p, $search, $m)) { $video_id = $m[1]; break; }
        }

        $has_video = ($video_id !== '');
        $has_audio = $has_doc = false;
        $image_file = '';
        if (isset($files[$wr_id])) {
            foreach ($files[$wr_id] as $f) {
                $ext = strtolower(pathinfo($f['bf_file'], PATHINFO_EXTENSION));
                if (in_array($ext, $video_exts)) $has_video = true;
                else if (in_array($ext, $audio_exts)) $has_audio = true;
                else if (in_array($ext, $doc_exts)) $has_doc = true;
                if (!$image_file && (in_array($ext, $image_exts) || ($f['bf_type'] >= 1 && $f['bf_type'] <= 3))) {
                    $image_file = $f['bf_file'];
                }
            }
        }

        if ($video_id) {
            $thumbnail = 'https://img.youtube.com/vi/'.$video_id.'/hqdefault.jpg';
        } else if ($image_file) {
            $thumbnail = home_feed_thumb_url($image_file, $thumb_budget);
        } else if (preg_match('/<img[^>]+src=["\']?([^"\'>\s]+)/i', $feed['wr_content'], $m)) {
            $thumbnail = $m[1]; // 예전 에디터 글
        } else {
            $thumbnail = '';
        }

        $text = trim(preg_replace('/https?:\/\/[^\s]+/', '', strip_tags($feed['wr_content'])));
        $text = preg_replace('/\[(이미지|동영상|음원|파일)\d+\]/u', '', $text);
    ?>
        <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&amp;wr_id=<?php echo $wr_id; ?>" class="block min-w-0">
            <article class="bg-white rounded-lg shadow-warm overflow-hidden">
                <div class="relative aspect-square <?php echo $thumbnail ? 'bg-gray-100' : 'bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50'; ?>">
                    <?php if ($thumbnail) { ?>
                    <img src="<?php echo $thumbnail; ?>" alt="<?php echo $title; ?>" loading="lazy" decoding="async" class="w-full h-full object-cover">
                    <?php } else { ?>
                    <p class="absolute inset-0 p-2 text-[11px] leading-snug text-gray-700 line-clamp-5 overflow-hidden"><?php echo get_text(cut_str($text, 60)); ?></p>
                    <?php } ?>

                    <?php if ($has_video && $thumbnail) { ?>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-8 h-8 bg-red-600/90 rounded-full flex items-center justify-center shadow">
                            <i class="fa-solid fa-play text-white text-xs ml-0.5"></i>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if (($has_video && !$thumbnail) || $has_audio || $has_doc) { ?>
                    <div class="absolute top-1 right-1 flex gap-0.5">
                        <?php if ($has_video && !$thumbnail) { ?><span class="w-4 h-4 bg-red-500 rounded-full flex items-center justify-center"><i class="fa-solid fa-play text-white text-[8px]"></i></span><?php } ?>
                        <?php if ($has_audio) { ?><span class="w-4 h-4 bg-green-500 rounded-full flex items-center justify-center"><i class="fa-solid fa-music text-white text-[8px]"></i></span><?php } ?>
                        <?php if ($has_doc) { ?><span class="w-4 h-4 bg-blue-500 rounded-full flex items-center justify-center"><i class="fa-solid fa-file text-white text-[8px]"></i></span><?php } ?>
                    </div>
                    <?php } ?>

                    <div class="absolute bottom-0 left-0 right-0 <?php echo $thumbnail ? 'bg-gradient-to-t from-black/60 to-transparent text-white' : 'text-gray-500'; ?> px-1.5 py-1">
                        <div class="flex items-center gap-1.5 text-[10px]">
                            <span class="flex items-center gap-0.5"><i class="fa-solid fa-heart <?php echo $thumbnail ? '' : 'text-red-400'; ?>"></i> <?php echo number_format($feed['wr_good']); ?></span>
                            <span class="flex items-center gap-0.5"><i class="fa-regular fa-comment"></i> <?php echo number_format($feed['wr_comment']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="px-1.5 py-1.5">
                    <p class="text-xs font-medium text-gray-800 truncate"><?php echo $title; ?></p>
                    <p class="text-[10px] text-gray-500 truncate"><?php echo $writer; ?> · <?php echo date('m.d', strtotime($feed['wr_datetime'])); ?></p>
                </div>
            </article>
        </a>
    <?php } ?>
    </div>

    <?php if ($total_pages > 1) {
        $block = HOME_FEED_PAGE_BLOCK;
        $start = (int)(floor(($page - 1) / $block) * $block) + 1;
        $end = min($start + $block - 1, $total_pages);
        $base_cls = 'min-w-[32px] h-8 px-2 flex items-center justify-center rounded-lg text-sm';
    ?>
    <nav class="flex items-center justify-center gap-1 mt-4" aria-label="성산 샘터 페이지">
        <?php if ($start > 1) { ?>
        <a href="?home_page=<?php echo $start - 1; ?>#recent-posts" data-home-page="<?php echo $start - 1; ?>" class="<?php echo $base_cls; ?> text-gray-500 hover:bg-gray-100" aria-label="이전 페이지"><i class="fa-solid fa-chevron-left text-xs"></i></a>
        <?php } ?>
        <?php for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) { ?>
        <span class="<?php echo $base_cls; ?> bg-purple-600 text-white font-semibold" aria-current="page"><?php echo $i; ?></span>
            <?php } else { ?>
        <a href="?home_page=<?php echo $i; ?>#recent-posts" data-home-page="<?php echo $i; ?>" class="<?php echo $base_cls; ?> text-gray-700 hover:bg-gray-100"><?php echo $i; ?></a>
            <?php }
        } ?>
        <?php if ($end < $total_pages) { ?>
        <a href="?home_page=<?php echo $end + 1; ?>#recent-posts" data-home-page="<?php echo $end + 1; ?>" class="<?php echo $base_cls; ?> text-gray-500 hover:bg-gray-100" aria-label="다음 페이지"><i class="fa-solid fa-chevron-right text-xs"></i></a>
        <?php } ?>
    </nav>
    <?php } ?>
    <?php
    return ob_get_clean();
}

} // function_exists

// AJAX 직접 호출: 회원에게만 목록 반환
if (defined('HOME_FEED_DIRECT')) {
    if (!$is_member) {
        http_response_code(403);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo home_feed_render(isset($_GET['page']) ? $_GET['page'] : 1);
}
