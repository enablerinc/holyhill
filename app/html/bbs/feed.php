<?php
include_once('./_common.php');

$g5['title'] = '성산샘터';

// 게시판 설정 (gallery 게시판)
$bo_table = 'gallery';
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    alert('존재하지 않는 게시판입니다.', G5_URL);
}

// 권한 체크
if ($member['mb_level'] < $board['bo_list_level']) {
    if ($member['mb_id'])
        alert('목록을 볼 권한이 없습니다.', G5_URL);
    else
        alert('목록을 볼 권한이 없습니다.\\n\\n회원이시라면 로그인 후 이용해 보십시오.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/feed.php'));
}

// 페이지당 게시물 수 설정
$page_rows = 30;

// 정렬 파라미터 (기본값: 최신순)
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';

// 검색 파라미터
$search_type = isset($_GET['search_type']) ? $_GET['search_type'] : 'subject';
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';

if ($search_keyword) {
    $search_keyword_escaped = sql_real_escape_string($search_keyword);
    if ($search_type === 'name') {
        $search_condition = " AND wr_name LIKE '%{$search_keyword_escaped}%'";
    } else {
        $search_condition = " AND wr_subject LIKE '%{$search_keyword_escaped}%'";
    }
}

// 기간 필터 파라미터 (기본값: 전체)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$date_cond_simple = '';

switch($filter) {
    case '1week':
        $date_cond_simple = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        break;
    case '1month':
        $date_cond_simple = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3month':
        $date_cond_simple = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case 'all':
    default:
        $date_cond_simple = '';
        break;
}

// 게시글 가져오기
$write_table = $g5['write_prefix'] . $bo_table;

// 정렬 조건 (wr_datetime 기준 최신순)
$order_by = ($sort === 'popular') ? 'wr_good DESC, wr_datetime DESC' : 'wr_datetime DESC';

$sql = "SELECT * FROM {$write_table} WHERE wr_is_comment = 0 {$date_cond_simple} {$search_condition} ORDER BY {$order_by} LIMIT {$page_rows}";

$result = sql_query($sql);
$list = array();
while ($row = sql_fetch_array($result)) {
    $list[] = $row;
}

// 전체 게시글 수 (페이징용)
$total_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_is_comment = 0 {$date_cond_simple} {$search_condition}";
$total_count_result = sql_fetch($total_count_sql);
$total_count = isset($total_count_result['cnt']) ? $total_count_result['cnt'] : 0;

// 글쓰기 권한 체크
$write_href = '';
if ($member['mb_level'] >= $board['bo_write_level']) {
    $write_href = G5_BBS_URL.'/write_post.php';
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script> window.FontAwesomeConfig = { autoReplaceSvg: 'nest'};</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { display: none; }
        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #EEF3F8;
        }
        .shadow-warm { box-shadow: 0 4px 20px rgba(177, 156, 217, 0.15); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .image-slider { -webkit-overflow-scrolling: touch; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'warm-beige': '#EEF3F8',
                        'soft-lavender': '#E8E2F7',
                        'grace-green': '#6B705C',
                        'lilac': '#B19CD9',
                        'deep-purple': '#6B46C1'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-warm-beige">

<header id="header" class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
            <img src="../img/logo.png" alt="성산교회 로고" class="w-8 h-8 rounded-lg object-cover">
            <h1 class="text-lg font-semibold text-grace-green">성산샘터</h1>
        </div>
        <div class="flex items-center gap-4">
            <?php if ($is_member) { ?>
            <div class="relative">
                <i id="notification-bell" class="fa-regular fa-bell text-gray-700 text-lg cursor-pointer hover:text-purple-600 transition-colors"></i>
                <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
            </div>
            <?php } ?>
            <?php if ($write_href) { ?>
            <a href="<?php echo $write_href; ?>" class="w-8 h-8 flex items-center justify-center">
                <i class="fa-solid fa-plus text-grace-green text-lg"></i>
            </a>
            <?php } ?>
        </div>
    </div>
</header>

<main id="main-content" class="pt-16 pb-20 max-w-2xl mx-auto">

    <!-- 검색 및 정렬 -->
    <section class="bg-white px-4 py-3 border-b border-soft-lavender">
        <!-- 검색창 -->
        <form action="<?php echo G5_BBS_URL; ?>/feed.php" method="get" class="mb-3">
            <input type="hidden" name="sort" value="<?php echo $sort; ?>">
            <div class="flex gap-2">
                <select name="search_type" class="px-3 py-2 bg-warm-beige border-0 rounded-lg text-sm text-gray-700 focus:ring-2 focus:ring-lilac">
                    <option value="subject" <?php echo $search_type === 'subject' ? 'selected' : ''; ?>>제목</option>
                    <option value="name" <?php echo $search_type === 'name' ? 'selected' : ''; ?>>작성자</option>
                </select>
                <div class="flex-1 relative">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>"
                           placeholder="검색어를 입력하세요"
                           class="w-full px-4 py-2 bg-warm-beige border-0 rounded-lg text-sm focus:ring-2 focus:ring-lilac">
                    <?php if ($search_keyword) { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/feed.php?sort=<?php echo $sort; ?>" class="absolute right-10 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                    <?php } ?>
                </div>
                <button type="submit" class="px-4 py-2 bg-lilac text-white rounded-lg text-sm font-medium hover:bg-deep-purple transition-colors">
                    <i class="fa-solid fa-search"></i>
                </button>
            </div>
        </form>

    </section>

    <?php
    // 공지사항 조회
    $notice_list = array();
    if ($board['bo_notice']) {
        $notice_ids = explode(',', $board['bo_notice']);
        $notice_ids = array_filter(array_map('intval', $notice_ids));
        if (!empty($notice_ids)) {
            $notice_ids_str = implode(',', $notice_ids);
            $notice_sql = "SELECT * FROM {$write_table} WHERE wr_id IN ({$notice_ids_str}) ORDER BY FIELD(wr_id, {$notice_ids_str})";
            $notice_result = sql_query($notice_sql);
            while ($notice_row = sql_fetch_array($notice_result)) {
                $notice_list[] = $notice_row;
            }
        }
    }
    ?>

    <?php if (!empty($notice_list)) { ?>
    <!-- 공지사항 섹션 -->
    <section class="px-4 py-4 border-b border-gray-200">
        <div class="flex items-center gap-2 mb-3">
            <i class="fa-solid fa-bullhorn text-amber-500"></i>
            <h2 class="text-base font-semibold text-gray-800">공지사항</h2>
        </div>
        <div class="space-y-2">
            <?php foreach ($notice_list as $notice) {
                $notice_href = G5_BBS_URL.'/post.php?bo_table='.$bo_table.'&amp;wr_id='.$notice['wr_id'];
                $notice_date = date('Y.m.d', strtotime($notice['wr_datetime']));
            ?>
            <a href="<?php echo $notice_href; ?>" class="block">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 hover:bg-amber-100 transition-colors">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500 text-white">
                            공지
                        </span>
                        <h3 class="flex-1 font-medium text-gray-900 text-sm line-clamp-1">
                            <?php echo get_text($notice['wr_subject']); ?>
                        </h3>
                        <span class="text-xs text-gray-500 flex-shrink-0"><?php echo $notice_date; ?></span>
                    </div>
                </div>
            </a>
            <?php } ?>
        </div>
    </section>
    <?php } ?>

    <!-- 게시물 목록 -->
    <section class="px-4 py-4">
        <?php
        // 게시글이 있는 경우
        if (count($list) > 0) {
        ?>
        <!-- 타일(그리드) 형식 -->
        <div class="grid grid-cols-2 gap-3" id="feed-list">
            <?php
            for ($i=0; $i<count($list); $i++) {
                $wr_id = $list[$i]['wr_id'];
                $wr_subject = strip_tags($list[$i]['wr_subject']);
                $good_count = isset($list[$i]['wr_good']) ? $list[$i]['wr_good'] : 0;

                // 작성자 정보
                $writer_id = $list[$i]['mb_id'];
                $writer_nick = $list[$i]['wr_name'];
                $writer_photo = '';
                if ($writer_id) {
                    $member_info = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$writer_id}'");
                    if ($member_info) {
                        $writer_nick = $member_info['mb_nick'] ? $member_info['mb_nick'] : $list[$i]['wr_name'];
                    }
                    // 프로필 이미지 확인
                    $profile_path = G5_DATA_PATH.'/member_image/'.substr($writer_id, 0, 2).'/'.$writer_id.'.gif';
                    if (file_exists($profile_path)) {
                        $writer_photo = G5_DATA_URL.'/member_image/'.substr($writer_id, 0, 2).'/'.$writer_id.'.gif';
                    }
                }

                // 댓글 수 조회
                $comment_result = sql_fetch("SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1");
                $comment_count = isset($comment_result['cnt']) ? $comment_result['cnt'] : 0;

                // 날짜 포맷
                $wr_datetime = $list[$i]['wr_datetime'];
                $date_diff = time() - strtotime($wr_datetime);
                if ($date_diff < 60) {
                    $display_date = '방금 전';
                } elseif ($date_diff < 3600) {
                    $display_date = floor($date_diff / 60) . '분 전';
                } elseif ($date_diff < 86400) {
                    $display_date = floor($date_diff / 3600) . '시간 전';
                } elseif ($date_diff < 604800) {
                    $display_date = floor($date_diff / 86400) . '일 전';
                } else {
                    $display_date = date('m.d', strtotime($wr_datetime));
                }

                // 게시글 내용에서 YouTube URL 추출 및 섬네일 생성
                $video_thumbnail = '';
                $video_id = '';
                $search_content = $list[$i]['wr_link1'] . ' ' . $list[$i]['wr_content'];

                $patterns = array(
                    '/https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i',
                    '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)(?:&[^\s]*)?/i',
                    '/https?:\/\/(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i'
                );

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $search_content, $matches)) {
                        $video_id = $matches[1];
                        break;
                    }
                }

                if ($video_id) {
                    $video_thumbnail = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
                }

                // 첫 번째 이미지 가져오기 (에디터 본문 이미지 우선)
                $first_image = '';

                // 1. 먼저 본문(wr_content)에서 첫 번째 이미지 찾기
                if (preg_match('/<img[^>]+src=["\']?([^"\'>\s]+)["\']?[^>]*>/i', $list[$i]['wr_content'], $img_match)) {
                    $first_image = $img_match[1];
                }

                // 2. 본문에 이미지가 없으면 첨부파일에서 찾기
                if (!$first_image) {
                    $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND bf_type BETWEEN 1 AND 3 ORDER BY bf_no LIMIT 1");
                    if ($img = sql_fetch_array($img_result)) {
                        $first_image = G5_DATA_URL.'/file/'.$bo_table.'/'.$img['bf_file'];
                    }
                }

                // 썸네일 결정 (영상 > 이미지)
                $thumbnail = $video_thumbnail ? $video_thumbnail : $first_image;
                $has_video = !empty($video_id);
                $has_image = !empty($thumbnail);

                $view_href = G5_BBS_URL.'/post.php?bo_table='.$bo_table.'&amp;wr_id='.$wr_id;

                // 텍스트 콘텐츠 추출
                $text_content = strip_tags($list[$i]['wr_content']);
                $text_content = preg_replace('/\[이미지\d+\]/', '', $text_content);
                $text_content = preg_replace('/https?:\/\/[^\s]+/', '', $text_content);
                $text_content = trim($text_content);
            ?>

            <!-- 타일 카드 -->
            <a href="<?php echo $view_href; ?>" class="block">
                <article class="bg-white rounded-xl shadow-warm overflow-hidden hover:shadow-lg transition-shadow">
                    <?php if ($has_image) { ?>
                    <!-- 이미지가 있는 경우: 썸네일 표시 -->
                    <div class="relative aspect-square">
                        <img src="<?php echo $thumbnail; ?>" alt="<?php echo $wr_subject; ?>" class="w-full h-full object-cover">
                        <?php if ($has_video) { ?>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-10 h-10 bg-black/60 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-play text-white text-sm ml-0.5"></i>
                            </div>
                        </div>
                        <?php } ?>
                        <!-- 공감/댓글 오버레이 -->
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-2">
                            <div class="flex items-center gap-2 text-white text-xs">
                                <span class="flex items-center gap-1">
                                    <i class="fa-solid fa-heart"></i>
                                    <?php echo number_format($good_count); ?>
                                </span>
                                <span class="flex items-center gap-1">
                                    <i class="fa-regular fa-comment"></i>
                                    <?php echo number_format($comment_count); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php } else { ?>
                    <!-- 텍스트만 있는 경우: 텍스트 카드 -->
                    <div class="aspect-square bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 p-3 flex flex-col justify-center">
                        <p class="text-gray-800 text-sm leading-relaxed line-clamp-5 text-center">
                            <?php echo cut_str($text_content, 80); ?>
                        </p>
                        <!-- 공감/댓글 -->
                        <div class="mt-auto pt-2 flex items-center justify-center gap-3 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <i class="fa-solid fa-heart text-red-400"></i>
                                <?php echo number_format($good_count); ?>
                            </span>
                            <span class="flex items-center gap-1">
                                <i class="fa-regular fa-comment"></i>
                                <?php echo number_format($comment_count); ?>
                            </span>
                        </div>
                    </div>
                    <?php } ?>
                    <!-- 하단: 작성자 정보 -->
                    <div class="p-2">
                        <div class="flex items-center gap-2">
                            <?php if ($writer_photo) { ?>
                            <img src="<?php echo $writer_photo; ?>" alt="<?php echo $writer_nick; ?>" class="w-5 h-5 rounded-full object-cover">
                            <?php } else { ?>
                            <div class="w-5 h-5 rounded-full bg-gradient-to-br from-lilac to-deep-purple flex items-center justify-center">
                                <span class="text-white text-xs font-semibold"><?php echo mb_substr($writer_nick, 0, 1); ?></span>
                            </div>
                            <?php } ?>
                            <span class="text-xs text-gray-700 font-medium truncate flex-1"><?php echo $writer_nick; ?></span>
                            <span class="text-xs text-gray-400"><?php echo $display_date; ?></span>
                        </div>
                    </div>
                </article>
            </a>

            <?php } ?>
        </div>
        <?php
        } else {
            // 게시글이 없는 경우
        ?>
        <div class="text-center py-20 text-gray-500">
            <i class="fa-regular fa-images text-4xl mb-4 block"></i>
            <p>첫 게시글을 작성해보세요!</p>
            <?php if ($write_href) { ?>
            <a href="<?php echo $write_href ?>" class="inline-block mt-4 px-6 py-2 bg-lilac text-white rounded-full font-medium hover:bg-deep-purple">
                글쓰기
            </a>
            <?php } ?>
        </div>
        <?php } ?>
    </section>

    <!-- 더 보기 버튼 -->
    <?php if ($total_count > $page_rows) { ?>
    <div id="load-more-btn" class="text-center py-4">
        <button onclick="loadMorePosts()" class="px-6 py-3 bg-lilac text-white rounded-full text-sm font-medium hover:bg-deep-purple transition-colors">
            <i class="fa-solid fa-plus mr-2"></i>더 보기
        </button>
    </div>
    <?php } ?>

    <!-- 로딩 인디케이터 -->
    <div id="loading" class="hidden text-center py-8">
        <i class="fa-solid fa-spinner fa-spin text-3xl text-lilac"></i>
        <p class="text-sm text-gray-500 mt-2">게시물을 불러오는 중...</p>
    </div>

    <!-- 더 이상 게시물이 없을 때 -->
    <div id="no-more" class="<?php echo $total_count <= $page_rows ? '' : 'hidden'; ?> text-center py-8 text-gray-500">
        <i class="fa-solid fa-check-circle text-2xl text-lilac mb-2"></i>
        <p class="text-sm">모든 게시물을 확인했습니다</p>
    </div>

</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<!-- 무한 스크롤 스크립트 -->
<script>
// 전역 변수
let currentPage = 1;
let isLoading = false;
let hasMore = true;
const sort = '<?php echo $sort; ?>';
const searchType = '<?php echo $search_type; ?>';
const searchKeyword = '<?php echo addslashes($search_keyword); ?>';
const boTable = '<?php echo $bo_table; ?>';
const totalCount = <?php echo $total_count; ?>;
const pageRows = <?php echo $page_rows; ?>;
const totalPages = Math.ceil(totalCount / pageRows);

// 초기화: 1페이지만 있으면 더 보기 버튼 숨김
if (totalPages <= 1) {
    hasMore = false;
    const loadMoreBtn = document.getElementById('load-more-btn');
    if (loadMoreBtn) loadMoreBtn.style.display = 'none';
}

// 버튼 클릭으로 더 보기
function loadMorePosts() {
    if (isLoading || !hasMore) return;
    loadMore();
}

// 스크롤 이벤트
window.addEventListener('scroll', function() {
    if (isLoading || !hasMore) return;

    const scrollHeight = document.documentElement.scrollHeight;
    const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
    const clientHeight = document.documentElement.clientHeight;

    if (scrollTop + clientHeight >= scrollHeight - 300) {
        loadMore();
    }
});

function loadMore() {
    if (currentPage >= totalPages) {
        hasMore = false;
        const loadMoreBtn = document.getElementById('load-more-btn');
        if (loadMoreBtn) loadMoreBtn.style.display = 'none';
        document.getElementById('no-more').classList.remove('hidden');
        return;
    }

    isLoading = true;
    currentPage++;

    document.getElementById('loading').classList.remove('hidden');

    const url = '<?php echo G5_BBS_URL; ?>/feed_ajax.php?bo_table=' + boTable +
                '&sort=' + sort +
                '&search_type=' + searchType +
                '&search=' + encodeURIComponent(searchKeyword) +
                '&page=' + currentPage +
                '&page_rows=' + pageRows;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.items.length > 0) {
                const feedList = document.getElementById('feed-list');

                    data.items.forEach(item => {
                        // 썸네일 결정
                        const thumbnail = item.video_thumbnail || (item.images && item.images.length > 0 ? item.images[0] : '');
                        const hasVideo = item.video_id && item.video_thumbnail;
                        const hasImage = !!thumbnail;

                        // 작성자 프로필 이미지
                        let profileHTML = item.writer_photo
                            ? `<img src="${item.writer_photo}" alt="${item.writer_nick}" class="w-5 h-5 rounded-full object-cover">`
                            : `<div class="w-5 h-5 rounded-full bg-gradient-to-br from-lilac to-deep-purple flex items-center justify-center">
                                <span class="text-white text-xs font-semibold">${item.writer_nick.charAt(0)}</span>
                               </div>`;

                        // 타일 카드 HTML
                        let contentHTML = '';
                        if (hasImage) {
                            // 이미지가 있는 경우
                            contentHTML = `
                                <div class="relative aspect-square">
                                    <img src="${thumbnail}" alt="${item.subject}" class="w-full h-full object-cover">
                                    ${hasVideo ? `
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="w-10 h-10 bg-black/60 rounded-full flex items-center justify-center">
                                            <i class="fa-solid fa-play text-white text-sm ml-0.5"></i>
                                        </div>
                                    </div>` : ''}
                                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-2">
                                        <div class="flex items-center gap-2 text-white text-xs">
                                            <span class="flex items-center gap-1">
                                                <i class="fa-solid fa-heart"></i>
                                                ${item.good_count}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <i class="fa-regular fa-comment"></i>
                                                ${item.comment_count}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            // 텍스트만 있는 경우
                            contentHTML = `
                                <div class="aspect-square bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 p-3 flex flex-col justify-center">
                                    <p class="text-gray-800 text-sm leading-relaxed line-clamp-5 text-center">${item.text_content || ''}</p>
                                    <div class="mt-auto pt-2 flex items-center justify-center gap-3 text-xs text-gray-500">
                                        <span class="flex items-center gap-1">
                                            <i class="fa-solid fa-heart text-red-400"></i>
                                            ${item.good_count}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <i class="fa-regular fa-comment"></i>
                                            ${item.comment_count}
                                        </span>
                                    </div>
                                </div>
                            `;
                        }

                        const itemHTML = `
                            <a href="${item.view_href}" class="block">
                                <article class="bg-white rounded-xl shadow-warm overflow-hidden hover:shadow-lg transition-shadow">
                                    ${contentHTML}
                                    <div class="p-2">
                                        <div class="flex items-center gap-2">
                                            ${profileHTML}
                                            <span class="text-xs text-gray-700 font-medium truncate flex-1">${item.writer_nick}</span>
                                            <span class="text-xs text-gray-400">${item.display_date}</span>
                                        </div>
                                    </div>
                                </article>
                            </a>
                        `;
                        feedList.insertAdjacentHTML('beforeend', itemHTML);
                    });

                    isLoading = false;
                    document.getElementById('loading').classList.add('hidden');

                    // 마지막 페이지면 더 보기 버튼 숨김
                    if (currentPage >= totalPages) {
                        hasMore = false;
                        const loadMoreBtn = document.getElementById('load-more-btn');
                        if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                        document.getElementById('no-more').classList.remove('hidden');
                    }

            } else {
                hasMore = false;
                const loadMoreBtn = document.getElementById('load-more-btn');
                if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('no-more').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            isLoading = false;
            document.getElementById('loading').classList.add('hidden');
        });
}
</script>

<!-- 알림 위젯 -->
<?php if ($is_member) { ?>
<?php include_once(G5_BBS_PATH.'/notification_widget.php'); ?>
<?php } ?>

</body>
</html>
