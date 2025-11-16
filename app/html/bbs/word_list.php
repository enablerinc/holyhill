<?php
include_once('./_common.php');

$g5['title'] = '오늘의 말씀';

// 게시판 설정
$bo_table = 'word';
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    alert('존재하지 않는 게시판입니다.', G5_URL);
}

// 권한 체크
if ($member['mb_level'] < $board['bo_list_level']) {
    if ($member['mb_id'])
        alert('목록을 볼 권한이 없습니다.', G5_URL);
    else
        alert('목록을 볼 권한이 없습니다.\\n\\n회원이시라면 로그인 후 이용해 보십시오.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/word_list.php'));
}

// YouTube URL을 iframe으로 변환
function convert_youtube_to_iframe_word($content) {
    $patterns = array(
        '/https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)(?:&[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i'
    );

    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, function($matches) {
            $video_id = $matches[1];
            $iframe_html = '
            <div class="youtube-container my-3" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 0.5rem;">
                <iframe
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 0.5rem;"
                    src="https://www.youtube.com/embed/' . htmlspecialchars($video_id) . '"
                    title="YouTube video player"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen>
                </iframe>
            </div>';
            return $iframe_html;
        }, $content);
    }

    return $content;
}

// 페이지당 게시물 수
$page_rows = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $page_rows;

// 전체 게시글 수
$write_table = $g5['write_prefix'] . $bo_table;
$total_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_is_comment = 0";
$total_count_result = sql_fetch($total_count_sql);
$total_count = $total_count_result['cnt'];

// 게시글 가져오기 (최신순)
$list_sql = "SELECT * FROM {$write_table}
             WHERE wr_is_comment = 0
             ORDER BY wr_id DESC
             LIMIT {$page_rows} OFFSET {$offset}";
$list_result = sql_query($list_sql);

$list = array();
while ($row = sql_fetch_array($list_result)) {
    $list[] = $row;
}

// 글쓰기 권한 체크
$write_href = '';
if ($member['mb_level'] >= $board['bo_write_level']) {
    $write_href = G5_BBS_URL.'/write_word.php';
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

<!-- 헤더 -->
<header id="header" class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="<?php echo G5_BBS_URL; ?>/index.php" class="text-grace-green hover:text-gray-900">
            <i class="fa-solid fa-xmark text-xl"></i>
        </a>
        <h1 class="text-lg font-semibold text-grace-green flex items-center gap-2">
            <i class="fa-solid fa-book-bible text-purple-600"></i>
            오늘의 말씀
        </h1>
        <?php if ($write_href) { ?>
        <a href="<?php echo $write_href; ?>" class="w-8 h-8 flex items-center justify-center">
            <i class="fa-solid fa-plus text-grace-green text-lg"></i>
        </a>
        <?php } else { ?>
        <div class="w-6"></div>
        <?php } ?>
    </div>
</header>

<main id="main-content" class="pt-16 pb-20">
    <div class="max-w-2xl mx-auto">

        <?php if (count($list) > 0) { ?>
        <!-- 말씀 목록 -->
        <section class="space-y-4 px-4 py-4">
            <?php
            foreach ($list as $word) {
                // 작성자 정보
                $word_nick = $word['wr_name'] ? $word['wr_name'] : '알 수 없음';

                // YouTube URL이 있는지 확인
                $has_youtube = preg_match('/(youtube\.com|youtu\.be)/', $word['wr_content']);

                // 첨부 이미지
                $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']}
                                        WHERE bo_table = 'word'
                                        AND wr_id = '{$word['wr_id']}'
                                        AND bf_type BETWEEN 1 AND 3
                                        ORDER BY bf_no
                                        LIMIT 1");
                $img = sql_fetch_array($img_result);
                $word_img = $img ? G5_DATA_URL.'/file/word/'.$img['bf_file'] : '';

                $view_href = G5_BBS_URL.'/word_view.php?wr_id='.$word['wr_id'];
            ?>

            <article class="bg-white rounded-2xl shadow-md overflow-hidden">
                <!-- 헤더 -->
                <div class="px-4 pt-4 pb-3 border-b border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-gradient-to-br from-purple-400 to-purple-600 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-book-bible text-white text-xs"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800 text-sm"><?php echo $word_nick; ?></h4>
                                <p class="text-xs text-gray-500"><?php echo date('Y년 m월 d일', strtotime($word['wr_datetime'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php if ($word['wr_subject']) { ?>
                    <h3 class="text-base font-bold text-gray-900"><?php echo get_text($word['wr_subject']); ?></h3>
                    <?php } ?>
                </div>

                <!-- 내용 -->
                <?php if ($has_youtube) { ?>
                <!-- YouTube 콘텐츠가 있을 때 -->
                <div class="px-4 py-3">
                    <a href="<?php echo $view_href; ?>" class="block">
                        <?php
                        $word_content = get_text($word['wr_content']);
                        echo convert_youtube_to_iframe_word($word_content);
                        ?>
                    </a>
                </div>
                <?php } elseif ($word_img) { ?>
                <!-- 이미지가 있을 때 -->
                <div class="w-full">
                    <a href="<?php echo $view_href; ?>">
                        <img src="<?php echo $word_img; ?>"
                             class="w-full h-auto max-h-[400px] object-cover cursor-pointer hover:opacity-95 transition-opacity"
                             alt="<?php echo $word['wr_subject']; ?>">
                    </a>
                </div>
                <div class="px-4 py-3">
                    <a href="<?php echo $view_href; ?>" class="block text-sm text-gray-700 leading-relaxed">
                        <?php
                        $text_content = strip_tags($word['wr_content']);
                        $text_content = str_replace('&nbsp;', ' ', $text_content);
                        $text_content = trim($text_content);
                        echo cut_str($text_content, 150);
                        ?>
                    </a>
                </div>
                <?php } else { ?>
                <!-- 텍스트만 있을 때 -->
                <div class="px-4 py-4">
                    <a href="<?php echo $view_href; ?>"
                       class="block bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 p-6 rounded-xl min-h-[200px] flex items-center justify-center cursor-pointer hover:opacity-95 transition-opacity">
                        <div class="text-center">
                            <p class="text-base font-medium text-gray-800 leading-relaxed line-clamp-6">
                                "<?php
                                $text_content = strip_tags($word['wr_content']);
                                $text_content = str_replace('&nbsp;', ' ', $text_content);
                                $text_content = trim($text_content);
                                echo cut_str($text_content, 200);
                                ?>"
                            </p>
                        </div>
                    </a>
                </div>
                <?php } ?>

                <!-- 푸터 -->
                <div class="px-4 pb-4">
                    <a href="<?php echo $view_href; ?>"
                       class="text-xs text-purple-600 hover:text-purple-800 font-medium">
                        전체 내용 보기 →
                    </a>
                </div>
            </article>

            <?php } ?>
        </section>

        <?php } else { ?>
        <!-- 게시글이 없을 때 -->
        <div class="mx-4 mt-8 p-12 bg-white rounded-2xl shadow-md text-center">
            <i class="fa-solid fa-book-bible text-gray-300 text-5xl mb-4"></i>
            <p class="text-gray-600 mb-4">아직 등록된 말씀이 없습니다</p>
            <?php if ($write_href) { ?>
            <a href="<?php echo $write_href; ?>"
               class="inline-block px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium transition-colors">
                <i class="fa-solid fa-plus mr-2"></i>첫 번째 말씀 등록하기
            </a>
            <?php } ?>
        </div>
        <?php } ?>

    </div>
</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

</body>
</html>
