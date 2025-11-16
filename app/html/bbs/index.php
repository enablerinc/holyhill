<?php
include_once('./_common.php');

$g5['title'] = '홈';

// YouTube URL을 iframe으로 변환
function convert_youtube_to_iframe_index($content) {
    $patterns = array(
        '/https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)(?:&[^\s]*)?/i',
        '/https?:\/\/(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]+)(?:\?[^\s]*)?/i'
    );

    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, function($matches) {
            $video_id = $matches[1];
            $iframe_html = '
            <div class="youtube-container my-4" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 0.5rem;">
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
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
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
            <h1 class="text-lg font-semibold text-grace-green">성산교회</h1>
        </div>
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-bell text-grace-green text-lg"></i>
        </div>
    </div>
</header>

<main id="main-content" class="pt-16 pb-20">

    <div id="main-container" class="max-w-2xl mx-auto">

        <!-- 스토리 섹션 -->
        <section id="stories" class="bg-white px-4 py-3 mb-2 sticky top-16 z-40">
            <div class="flex gap-3 overflow-x-auto scrollbar-hide">
                <div class="flex flex-col items-center gap-2 min-w-[64px]">
                    <button onclick="alert('스토리 기능은 추후 구현됩니다')"
                            class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 p-0.5">
                        <div class="w-full h-full bg-white rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-plus text-purple-500 text-lg"></i>
                        </div>
                    </button>
                    <span class="text-xs text-gray-700 font-medium">내 이야기</span>
                </div>

                <?php
                $story_sql = "SELECT mb_id, mb_nick FROM {$g5['member_table']}
                             WHERE mb_level >= 2
                             ORDER BY mb_today_login DESC
                             LIMIT 10";
                $story_result = sql_query($story_sql);

                while ($story = sql_fetch_array($story_result)) {
                    // 프로필 이미지
                    $story_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
                    $profile_path = G5_DATA_PATH.'/member_image/'.substr($story['mb_id'], 0, 2).'/'.$story['mb_id'].'.gif';
                    if (file_exists($profile_path)) {
                        $story_photo = G5_DATA_URL.'/member_image/'.substr($story['mb_id'], 0, 2).'/'.$story['mb_id'].'.gif';
                    }

                    $story_nick = $story['mb_nick'] ? $story['mb_nick'] : '회원';
                    ?>
                    <div class="flex flex-col items-center gap-2 min-w-[64px]">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 p-0.5">
                            <img src="<?php echo $story_photo; ?>"
                                 class="w-full h-full rounded-full object-cover border-2 border-white"
                                 alt="<?php echo $story_nick; ?>">
                        </div>
                        <span class="text-xs text-gray-700"><?php echo cut_str($story_nick, 6); ?></span>
                    </div>
                    <?php
                }
                ?>
            </div>
        </section>

        <!-- 오늘의 말씀 위젯 -->
        <section id="daily-word" class="mx-4 mb-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-2xl p-6 shadow-lg">
            <?php
            // 오늘 날짜에 등록된 말씀만 가져오기
            $word_sql = "SELECT wr_id, wr_subject, wr_content, wr_datetime, wr_name
                         FROM {$g5['write_prefix']}word
                         WHERE wr_is_comment = 0
                         AND DATE(wr_datetime) = CURDATE()
                         ORDER BY wr_id DESC
                         LIMIT 1";
            $word_result = sql_query($word_sql);

            if ($word_result && $word = sql_fetch_array($word_result)) {
                // YouTube URL이 있는지 확인
                $has_youtube = preg_match('/(youtube\.com|youtu\.be)/', $word['wr_content']);

                if ($has_youtube) {
                    $word_content = get_text($word['wr_content']);
                    $word_content = convert_youtube_to_iframe_index($word_content);
                } else {
                    $word_content = strip_tags($word['wr_content']);
                    $word_content = str_replace('&nbsp;', ' ', $word_content);
                    $word_content = trim($word_content);
                    $word_content = cut_str($word_content, 120);
                }

                // 오늘 등록된 말씀 개수 체크
                $today_count_sql = "SELECT COUNT(*) as cnt FROM {$g5['write_prefix']}word
                                   WHERE wr_is_comment = 0 AND DATE(wr_datetime) = CURDATE()";
                $today_count = sql_fetch($today_count_sql);
                $has_multiple = $today_count['cnt'] > 1;
                ?>
                <div class="<?php echo $has_youtube ? '' : 'text-center'; ?>">
                    <h3 class="text-sm font-medium text-purple-900 mb-2 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-book-bible text-purple-600"></i>
                        오늘의 말씀
                        <?php if ($has_multiple) { ?>
                        <span class="text-xs text-orange-600">(오늘 <?php echo $today_count['cnt']; ?>개 등록됨)</span>
                        <?php } ?>
                    </h3>
                    <?php if ($has_youtube) { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=word&wr_id=<?php echo $word['wr_id']; ?>"
                       class="block mb-2">
                        <?php if ($word['wr_subject']) { ?>
                            <h3 class="text-base font-bold mb-2 text-gray-900 text-left"><?php echo get_text($word['wr_subject']); ?></h3>
                        <?php } ?>
                        <?php echo $word_content; ?>
                    </a>
                    <?php } else { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=word&wr_id=<?php echo $word['wr_id']; ?>"
                       class="block mb-2 cursor-pointer hover:opacity-80 transition-opacity">
                        <p class="text-base font-medium text-gray-800 leading-relaxed">
                            "<?php echo $word_content; ?>"
                        </p>
                    </a>
                    <?php } ?>
                    <p class="text-xs text-purple-600 mb-3">
                        <?php echo date('Y년 m월 d일', strtotime($word['wr_datetime'])); ?> · <?php echo $word['wr_name']; ?>
                    </p>
                    <div class="flex items-center justify-center gap-3">
                        <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=word"
                           class="inline-block text-sm text-purple-600 hover:text-purple-800 font-medium">
                            전체 보기 →
                        </a>
                        <?php if ($is_admin) { ?>
                        <span class="text-gray-300">|</span>
                        <a href="<?php echo G5_BBS_URL; ?>/write.php?bo_table=word"
                           class="inline-block text-sm text-purple-600 hover:text-purple-800 font-medium">
                            <i class="fa-solid fa-plus text-xs"></i> 새 말씀 등록
                        </a>
                        <?php } ?>
                    </div>
                </div>
                <?php
            } else {
                ?>
                <div class="text-center">
                    <h3 class="text-sm font-medium text-purple-900 mb-2 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-book-bible text-purple-600"></i>
                        오늘의 말씀
                    </h3>
                    <p class="text-base font-medium text-gray-600 leading-relaxed mb-3">
                        오늘 등록된 말씀이 아직 없습니다
                    </p>
                    <?php if ($is_admin) { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/write.php?bo_table=word"
                       class="inline-block px-5 py-2 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700 font-medium transition-colors">
                        <i class="fa-solid fa-plus mr-1"></i> 오늘의 말씀 등록하기
                    </a>
                    <?php } else { ?>
                    <p class="text-xs text-gray-500">관리자가 곧 말씀을 등록할 예정입니다</p>
                    <?php } ?>
                </div>
                <?php
            }
            ?>
        </section>

        <!-- 피드 섹션 -->
        <section id="feed" class="space-y-4 pb-20">
            <?php
            $feed_sql = "SELECT * FROM {$g5['write_prefix']}gallery
                         WHERE wr_is_comment = 0
                         ORDER BY wr_id DESC
                         LIMIT 20";
            $feed_result = sql_query($feed_sql);

            if ($feed_result && sql_num_rows($feed_result) > 0) {
                while ($feed = sql_fetch_array($feed_result)) {
                    // 작성자 정보
                    $feed_nick = $feed['wr_name'] ? $feed['wr_name'] : '알 수 없음';
                    $feed_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';

                    if ($feed['mb_id']) {
                        $mb_result = sql_query("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$feed['mb_id']}'");
                        if ($mb_result && $mb = sql_fetch_array($mb_result)) {
                            $feed_nick = $mb['mb_nick'];
                        }

                        // 프로필 이미지
                        $profile_path = G5_DATA_PATH.'/member_image/'.substr($feed['mb_id'], 0, 2).'/'.$feed['mb_id'].'.gif';
                        if (file_exists($profile_path)) {
                            $feed_photo = G5_DATA_URL.'/member_image/'.substr($feed['mb_id'], 0, 2).'/'.$feed['mb_id'].'.gif';
                        }
                    }

                    // YouTube URL이 있는지 확인
                    $has_youtube_feed = preg_match('/(youtube\.com|youtu\.be)/', $feed['wr_content']);

                    // 첨부 이미지
                    $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']}
                                            WHERE bo_table = 'gallery'
                                            AND wr_id = '{$feed['wr_id']}'
                                            AND bf_type BETWEEN 1 AND 3
                                            ORDER BY bf_no
                                            LIMIT 1");
                    $img = sql_fetch_array($img_result);
                    $feed_img = $img ? G5_DATA_URL.'/file/gallery/'.$img['bf_file'] : '';

                    $comment_cnt = $feed['wr_comment'];
                    $good_cnt = $feed['wr_good'];
                    ?>

                    <article class="bg-white rounded-2xl shadow-md overflow-hidden mx-4">
                        <div class="p-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo $feed_photo; ?>"
                                     class="w-10 h-10 rounded-full object-cover"
                                     alt="<?php echo $feed_nick; ?>">
                                <div>
                                    <h4 class="font-semibold text-gray-800 text-sm"><?php echo $feed_nick; ?></h4>
                                    <p class="text-xs text-gray-500"><?php echo date('n월 j일', strtotime($feed['wr_datetime'])); ?></p>
                                </div>
                            </div>
                            <button class="text-gray-400 hover:text-gray-600">
                                <i class="fa-solid fa-ellipsis"></i>
                            </button>
                        </div>

                        <?php if ($has_youtube_feed) { ?>
                        <!-- YouTube 콘텐츠가 있을 때 -->
                        <div class="w-full p-4">
                            <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>"
                               class="block">
                                <?php if ($feed['wr_subject']) { ?>
                                    <h3 class="text-base font-bold mb-2 text-gray-900"><?php echo get_text($feed['wr_subject']); ?></h3>
                                <?php } ?>
                                <?php
                                $feed_content = get_text($feed['wr_content']);
                                echo convert_youtube_to_iframe_index($feed_content);
                                ?>
                            </a>
                        </div>
                        <?php } elseif ($feed_img) { ?>
                        <!-- 이미지가 있을 때 -->
                        <div class="w-full">
                            <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>">
                                <img src="<?php echo $feed_img; ?>"
                                     class="w-full h-auto max-h-[500px] object-cover cursor-pointer hover:opacity-95 transition-opacity"
                                     alt="<?php echo $feed['wr_subject']; ?>">
                            </a>
                        </div>
                        <?php } else { ?>
                        <!-- 이미지가 없을 때 텍스트 콘텐츠를 카드로 표시 -->
                        <div class="w-full">
                            <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>"
                               class="block bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 p-8 min-h-[300px] flex items-center justify-center cursor-pointer hover:opacity-95 transition-opacity">
                                <div class="text-center">
                                    <p class="text-lg font-medium text-gray-800 leading-relaxed line-clamp-6">
                                        <?php
                                        $text_content = strip_tags($feed['wr_content']);
                                        $text_content = str_replace('&nbsp;', ' ', $text_content);
                                        $text_content = trim($text_content);
                                        echo cut_str($text_content, 200);
                                        ?>
                                    </p>
                                </div>
                            </a>
                        </div>
                        <?php } ?>

                        <div class="p-4">
                            <div class="flex items-center gap-4 mb-3">
                                <button onclick="location.href='<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>'"
                                        class="flex items-center gap-2">
                                    <i class="fa-solid fa-heart text-red-500 text-xl"></i>
                                    <span class="text-sm text-gray-700">아멘 <?php echo number_format($good_cnt); ?>개</span>
                                </button>
                                <button onclick="location.href='<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>'"
                                        class="flex items-center gap-2">
                                    <i class="fa-regular fa-comment text-gray-700 text-xl"></i>
                                    <span class="text-sm text-gray-700"><?php echo number_format($comment_cnt); ?></span>
                                </button>
                            </div>

                            <div class="mb-2">
                                <span class="font-semibold text-sm mr-2"><?php echo $feed_nick; ?></span>
                                <span class="text-sm text-gray-800">
                                    <?php echo $feed['wr_subject']; ?>
                                </span>
                            </div>

                            <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&wr_id=<?php echo $feed['wr_id']; ?>"
                               class="text-xs text-gray-500 hover:text-gray-700">
                                댓글 모두 보기 →
                            </a>
                        </div>
                    </article>

                    <?php
                }
            } else {
                ?>
                <div class="mx-4 p-8 bg-white rounded-2xl shadow-md text-center">
                    <i class="fa-regular fa-images text-gray-300 text-5xl mb-4"></i>
                    <p class="text-gray-600 mb-4">아직 등록된 피드가 없습니다</p>
                    <?php if ($is_member) { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/write.php?bo_table=gallery"
                       class="inline-block px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        첫 번째 피드 작성하기
                    </a>
                    <?php } ?>
                </div>
                <?php
            }
            ?>
        </section>

    </div>

</main>

<div id="floating-attendance"
     onclick="alert('출석 체크 기능은 추후 구현됩니다')"
     class="fixed bottom-24 right-4 w-14 h-14 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full shadow-lg flex items-center justify-center cursor-pointer hover:scale-110 transition-transform z-40">
    <i class="fa-solid fa-check text-white text-lg"></i>
</div>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

</body>
</html>
