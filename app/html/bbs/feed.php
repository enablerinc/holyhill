<?php
include_once('./_common.php');

$g5['title'] = '피드';

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

// 기간 필터 파라미터 (기본값: 1주)
$filter = isset($_GET['filter']) ? $_GET['filter'] : '1week';
$date_condition = '';

switch($filter) {
    case '1week':
        $date_condition = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        break;
    case '1month':
        $date_condition = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3month':
        $date_condition = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case 'all':
    default:
        $date_condition = '';
        break;
}

// 좋아요 많은 순으로 게시글 가져오기
$write_table = $g5['write_prefix'] . $bo_table;
$sql = "SELECT * FROM {$write_table}
        WHERE wr_is_comment = 0 {$date_condition}
        ORDER BY wr_good DESC, wr_num DESC
        LIMIT {$page_rows}";

$result = sql_query($sql);
$list = array();
while ($row = sql_fetch_array($result)) {
    $list[] = $row;
}

// 전체 게시글 수 (페이징용)
$total_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_is_comment = 0 {$date_condition}";
$total_count_result = sql_fetch($total_count_sql);
$total_count = $total_count_result['cnt'];

// 글쓰기 권한 체크
$write_href = '';
if ($member['mb_level'] >= $board['bo_write_level']) {
    $write_href = G5_BBS_URL.'/write.php?bo_table='.$bo_table;
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
            <div class="w-8 h-8 bg-gradient-to-br from-lilac to-deep-purple rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-cross text-white text-sm"></i>
            </div>
            <h1 class="text-lg font-semibold text-grace-green">피드</h1>
        </div>
        <?php if ($write_href) { ?>
        <a href="<?php echo $write_href; ?>" class="w-8 h-8 flex items-center justify-center">
            <i class="fa-solid fa-plus text-grace-green text-lg"></i>
        </a>
        <?php } ?>
    </div>
</header>

<main id="main-content" class="pt-16 pb-20 max-w-2xl mx-auto">

    <!-- 기간 필터 탭 -->
    <section class="bg-white px-4 py-3 border-b border-soft-lavender">
        <div class="flex gap-2 overflow-x-auto">
            <?php
            $base_url = G5_BBS_URL.'/feed.php';
            $filters = array(
                '1week' => '1주',
                '1month' => '1개월',
                '3month' => '3개월',
                'all' => '전체'
            );

            foreach($filters as $key => $label) {
                $active_class = ($filter === $key)
                    ? 'bg-lilac text-white'
                    : 'bg-warm-beige text-grace-green hover:bg-soft-lavender';
            ?>
            <a href="<?php echo $base_url; ?>?filter=<?php echo $key; ?>"
               class="px-4 py-2 <?php echo $active_class; ?> rounded-full text-sm font-medium whitespace-nowrap transition-colors">
                <?php echo $label; ?>
            </a>
            <?php } ?>
        </div>
    </section>

    <!-- 인기 게시물 섹션 -->
    <section class="px-4 py-4">
        <div class="flex items-center justify-between mb-4">
            <?php
            $filter_labels = array(
                '1week' => '이번 주 인기 게시물',
                '1month' => '이번 달 인기 게시물',
                '3month' => '최근 3개월 인기 게시물',
                'all' => '전체 인기 게시물'
            );
            $section_title = isset($filter_labels[$filter]) ? $filter_labels[$filter] : '인기 게시물';
            ?>
            <h2 class="text-lg font-semibold text-grace-green"><?php echo $section_title; ?></h2>
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-fire text-orange-500"></i>
                <span class="text-sm text-gray-500"><?php echo number_format($total_count); ?>개</span>
            </div>
        </div>

        <?php
        // 게시글이 있는 경우
        if (count($list) > 0) {
        ?>
        <div class="grid grid-cols-3 gap-1">
            <?php
            for ($i=0; $i<count($list); $i++) {
                $wr_id = $list[$i]['wr_id'];

                // 게시글 내용에서 YouTube URL 추출 및 섬네일 생성
                $video_thumbnail = '';
                $video_id = '';

                // wr_link1 또는 wr_content에서 YouTube URL 찾기
                $search_content = $list[$i]['wr_link1'] . ' ' . $list[$i]['wr_content'];

                // YouTube URL 패턴
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

                // 비디오 ID가 있으면 섬네일 생성
                if ($video_id) {
                    $video_thumbnail = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
                }

                // 첫 번째 이미지 가져오기
                $first_image = '';
                $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND bf_type BETWEEN 1 AND 3 ORDER BY bf_no LIMIT 1");
                if ($img_result && $img = sql_fetch_array($img_result)) {
                    $first_image = G5_DATA_URL.'/file/'.$bo_table.'/'.$img['bf_file'];
                }

                $view_href = G5_BBS_URL.'/post.php?bo_table='.$bo_table.'&amp;wr_id='.$wr_id;
                $good_count = isset($list[$i]['wr_good']) ? $list[$i]['wr_good'] : 0;

                // 텍스트 콘텐츠 추출 (이미지/영상이 없을 때 사용)
                $text_content = strip_tags($list[$i]['wr_content']);
                $text_content = preg_replace('/\[이미지\d+\]/', '', $text_content);
                $text_content = trim($text_content);
            ?>

            <div class="aspect-square bg-white rounded-lg overflow-hidden shadow-warm relative group">
                <?php if ($video_thumbnail && $video_id) { ?>
                    <!-- YouTube 영상 자동 재생 -->
                    <div class="relative w-full h-full video-container" data-video-id="<?php echo $video_id; ?>">
                        <!-- 로딩 중 섬네일 -->
                        <img class="w-full h-full object-cover video-thumbnail"
                             src="<?php echo $video_thumbnail; ?>"
                             alt="<?php echo strip_tags($list[$i]['wr_subject']); ?>">

                        <!-- YouTube iframe -->
                        <div class="absolute inset-0 video-iframe-container" style="display: none;">
                            <iframe
                                class="w-full h-full"
                                data-src="https://www.youtube.com/embed/<?php echo $video_id; ?>?autoplay=1&mute=1&loop=1&playlist=<?php echo $video_id; ?>&controls=0&showinfo=0&rel=0&modestbranding=1&playsinline=1&start=0&end=15"
                                frameborder="0"
                                allow="autoplay; encrypted-media"
                                allowfullscreen>
                            </iframe>
                        </div>

                        <!-- 클릭 시 상세 페이지로 이동 -->
                        <a href="<?php echo $view_href; ?>" class="absolute inset-0 z-10"></a>
                    </div>
                <?php } elseif ($first_image) { ?>
                    <a href="<?php echo $view_href; ?>" class="block w-full h-full">
                        <!-- 이미지 표시 -->
                        <img class="w-full h-full object-cover hover:opacity-95 transition-opacity"
                             src="<?php echo $first_image; ?>"
                             alt="<?php echo strip_tags($list[$i]['wr_subject']); ?>">

                        <!-- 제목 오버레이 (호버 시 표시) -->
                        <?php if (!empty($list[$i]['wr_subject'])) { ?>
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/50 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <p class="text-white text-xs font-semibold line-clamp-2 leading-tight">
                                <?php echo strip_tags($list[$i]['wr_subject']); ?>
                            </p>
                        </div>
                        <?php } ?>
                    </a>
                    <div class="absolute bottom-1 right-1 bg-black/50 text-white text-xs px-1 rounded flex items-center gap-1 group-hover:opacity-0 transition-opacity z-20">
                        <i class="fa-solid fa-heart text-red-400 text-xs"></i>
                        <?php echo number_format($good_count); ?>
                    </div>
                <?php } else { ?>
                    <a href="<?php echo $view_href; ?>" class="block w-full h-full">
                        <!-- 텍스트 표시 -->
                        <div class="w-full h-full bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 p-3 flex items-center justify-center hover:opacity-95 transition-opacity">
                            <p class="text-xs text-gray-700 leading-relaxed line-clamp-6 break-words">
                                <?php echo $text_content ? cut_str($text_content, 80) : '내용 없음'; ?>
                            </p>
                        </div>

                        <!-- 제목 오버레이 (호버 시 표시) -->
                        <?php if (!empty($list[$i]['wr_subject'])) { ?>
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/50 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <p class="text-white text-xs font-semibold line-clamp-2 leading-tight">
                                <?php echo strip_tags($list[$i]['wr_subject']); ?>
                            </p>
                        </div>
                        <?php } ?>
                    </a>
                    <div class="absolute bottom-1 right-1 bg-black/50 text-white text-xs px-1 rounded flex items-center gap-1 group-hover:opacity-0 transition-opacity z-20">
                        <i class="fa-solid fa-heart text-red-400 text-xs"></i>
                        <?php echo number_format($good_count); ?>
                    </div>
                <?php } ?>

                <!-- 제목 오버레이 (영상용) -->
                <?php if ($video_thumbnail && $video_id && !empty($list[$i]['wr_subject'])) { ?>
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/50 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity z-20">
                    <p class="text-white text-xs font-semibold line-clamp-2 leading-tight">
                        <?php echo strip_tags($list[$i]['wr_subject']); ?>
                    </p>
                </div>
                <?php } ?>

                <!-- 좋아요 수 (영상용) -->
                <?php if ($video_thumbnail && $video_id) { ?>
                <div class="absolute bottom-1 right-1 bg-black/50 text-white text-xs px-1 rounded flex items-center gap-1 group-hover:opacity-0 transition-opacity z-20">
                    <i class="fa-solid fa-heart text-red-400 text-xs"></i>
                    <?php echo number_format($good_count); ?>
                </div>
                <?php } ?>
            </div>

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

    <!-- 로딩 인디케이터 -->
    <div id="loading" class="hidden text-center py-8">
        <i class="fa-solid fa-spinner fa-spin text-3xl text-lilac"></i>
        <p class="text-sm text-gray-500 mt-2">게시물을 불러오는 중...</p>
    </div>

    <!-- 더 이상 게시물이 없을 때 -->
    <div id="no-more" class="hidden text-center py-8 text-gray-500">
        <i class="fa-solid fa-check-circle text-2xl text-lilac mb-2"></i>
        <p class="text-sm">모든 게시물을 확인했습니다</p>
    </div>

</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<!-- YouTube 영상 자동 재생 스크립트 -->
<script>
// Intersection Observer로 화면에 보이는 영상만 로드 및 재생
(function() {
    const videoContainers = document.querySelectorAll('.video-container');

    const observerOptions = {
        root: null,
        rootMargin: '50px',
        threshold: 0.5 // 50% 이상 보일 때
    };

    const videoObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const container = entry.target;
            const iframeContainer = container.querySelector('.video-iframe-container');
            const iframe = iframeContainer.querySelector('iframe');
            const thumbnail = container.querySelector('.video-thumbnail');

            if (entry.isIntersecting) {
                // 화면에 보이면 iframe 로드 및 재생
                if (!iframe.src && iframe.dataset.src) {
                    iframe.src = iframe.dataset.src;

                    // iframe 로드 후 섬네일 숨기기
                    iframe.onload = function() {
                        setTimeout(() => {
                            thumbnail.style.display = 'none';
                            iframeContainer.style.display = 'block';
                        }, 500);
                    };
                }
            }
        });
    }, observerOptions);

    videoContainers.forEach(container => {
        videoObserver.observe(container);
    });
})();

// 무한 스크롤 JavaScript
(function() {
    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    const filter = '<?php echo $filter; ?>';
    const boTable = '<?php echo $bo_table; ?>';
    const totalCount = <?php echo $total_count; ?>;
    const pageRows = <?php echo $page_rows; ?>;
    const totalPages = Math.ceil(totalCount / pageRows);

    // 스크롤 이벤트
    window.addEventListener('scroll', function() {
        if (isLoading || !hasMore) return;

        // 스크롤이 하단에 도달했는지 확인 (300px 여유)
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
            document.getElementById('no-more').classList.remove('hidden');
            return;
        }

        isLoading = true;
        currentPage++;

        // 로딩 표시
        document.getElementById('loading').classList.remove('hidden');

        // AJAX 요청
        const url = '<?php echo G5_BBS_URL; ?>/board_ajax.php?bo_table=' + boTable +
                    '&filter=' + filter +
                    '&page=' + currentPage +
                    '&page_rows=' + pageRows;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.items.length > 0) {
                    const grid = document.querySelector('.grid.grid-cols-3');

                    data.items.forEach(item => {
                        let contentHTML = '';
                        if (item.has_image) {
                            contentHTML = `
                                <img class="w-full h-full object-cover hover:opacity-95 transition-opacity"
                                     src="${item.image}"
                                     alt="${item.subject}">
                            `;
                        } else {
                            const textContent = item.text_content || '내용 없음';
                            contentHTML = `
                                <div class="w-full h-full bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 p-3 flex items-center justify-center hover:opacity-95 transition-opacity">
                                    <p class="text-xs text-gray-700 leading-relaxed line-clamp-6 break-words">
                                        ${textContent}
                                    </p>
                                </div>
                            `;
                        }

                        const itemHTML = `
                            <div class="aspect-square bg-white rounded-lg overflow-hidden shadow-warm relative">
                                <a href="${item.view_href}" class="block w-full h-full">
                                    ${contentHTML}
                                </a>
                                <div class="absolute bottom-1 right-1 bg-black/50 text-white text-xs px-1 rounded flex items-center gap-1">
                                    <i class="fa-solid fa-heart text-red-400 text-xs"></i>
                                    ${item.good_count}
                                </div>
                            </div>
                        `;
                        grid.insertAdjacentHTML('beforeend', itemHTML);
                    });

                    isLoading = false;
                    document.getElementById('loading').classList.add('hidden');

                } else {
                    hasMore = false;
                    document.getElementById('loading').classList.add('hidden');
                    document.getElementById('no-more').classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                isLoading = false;
                document.getElementById('loading').classList.add('hidden');
                alert('게시물을 불러오는 중 오류가 발생했습니다.');
            });
    }
})();
</script>

</body>
</html>
