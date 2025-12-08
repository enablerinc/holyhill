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
            <div class="relative">
                <i id="notification-bell" class="fa-solid fa-bell text-grace-green text-lg cursor-pointer hover:text-deep-purple transition-colors"></i>
                <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
            </div>
        </div>
    </div>
</header>

<main id="main-content" class="pt-16 pb-20">

    <!-- 알림 패널 -->
    <div id="notification-panel" class="fixed top-16 right-0 w-full max-w-md h-screen bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out z-50 overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-4 z-10">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-xl font-bold text-gray-800">알림</h2>
                <i id="close-notification" class="fa-solid fa-times text-gray-600 text-xl cursor-pointer hover:text-gray-800"></i>
            </div>
            <button id="mark-all-read" class="text-sm text-purple-600 hover:text-purple-800 font-medium">
                모두 읽음 처리
            </button>
        </div>
        <div id="notification-list" class="divide-y divide-gray-100">
            <!-- 알림 목록이 여기에 동적으로 추가됩니다 -->
            <div class="flex items-center justify-center py-20">
                <div class="text-center">
                    <i class="fa-regular fa-bell text-gray-300 text-5xl mb-4"></i>
                    <p class="text-gray-500">알림이 없습니다</p>
                </div>
            </div>
        </div>
    </div>

    <div id="main-container" class="max-w-2xl mx-auto">

        <!-- 현재 활동중인 사용자 섹션 -->
        <section id="online-users" class="bg-white px-4 py-2 mb-2 border-b border-gray-100">
            <?php
            // 오늘 출석(로그인)한 모든 회원을 최근 활동 순으로 표시
            $today_login_sql = "
                SELECT m.mb_id, m.mb_nick,
                    GREATEST(
                        COALESCE(m.mb_today_login, '1000-01-01 00:00:00'),
                        COALESCE((SELECT MAX(wr_datetime) FROM {$g5['write_prefix']}gallery WHERE mb_id = m.mb_id), '1000-01-01 00:00:00'),
                        COALESCE((SELECT MAX(wr_datetime) FROM {$g5['write_prefix']}word WHERE mb_id = m.mb_id), '1000-01-01 00:00:00')
                    ) as last_activity
                FROM {$g5['member_table']} m
                WHERE m.mb_id != ''
                AND DATE(m.mb_today_login) = CURDATE()
                ORDER BY last_activity DESC
            ";
            $story_result = sql_query($today_login_sql);
            ?>

            <!-- 사용자 목록 -->
            <div class="flex gap-3 overflow-x-auto scrollbar-hide">
                <?php

                while ($story = sql_fetch_array($story_result)) {
                    // 프로필 이미지
                    $story_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
                    $profile_path = G5_DATA_PATH.'/member_image/'.substr($story['mb_id'], 0, 2).'/'.$story['mb_id'].'.gif';
                    if (file_exists($profile_path)) {
                        $story_photo = G5_DATA_URL.'/member_image/'.substr($story['mb_id'], 0, 2).'/'.$story['mb_id'].'.gif';
                    }

                    $story_nick = $story['mb_nick'] ? $story['mb_nick'] : '회원';
                    ?>
                    <a href="<?php echo G5_BBS_URL; ?>/user_profile.php?mb_id=<?php echo $story['mb_id']; ?>"
                       class="flex flex-col items-center gap-2 min-w-[64px] cursor-pointer hover:opacity-80 transition-opacity">
                        <div class="relative">
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 p-0.5">
                                <img src="<?php echo $story_photo; ?>"
                                     class="w-full h-full rounded-full object-cover border-2 border-white"
                                     alt="<?php echo $story_nick; ?>">
                            </div>
                            <!-- 온라인 상태 표시 -->
                            <div class="absolute bottom-0 right-0 w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                        </div>
                        <span class="text-xs text-gray-700 font-medium"><?php echo cut_str($story_nick, 6); ?></span>
                    </a>
                    <?php
                }

                // 접속자가 없을 때
                if (sql_num_rows($story_result) == 0) {
                    ?>
                    <div class="w-full text-center py-4">
                        <p class="text-sm text-gray-400">현재 활동중인 회원이 없습니다</p>
                    </div>
                    <?php
                }
                ?>
            </div>
        </section>

        <!-- 오늘의 말씀 위젯 -->
        <section id="daily-word" class="mx-4 mb-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-2xl p-6 shadow-lg">
            <?php
            // 오늘 이전에 등록된 말씀 중 가장 최신 것을 가져오기 (다음 말씀이 올라올 때까지 유지)
            $word_sql = "SELECT wr_id, wr_subject, wr_content, wr_datetime, wr_name
                         FROM {$g5['write_prefix']}word
                         WHERE wr_is_comment = 0
                         AND DATE(wr_datetime) <= CURDATE()
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
                ?>
                <div class="<?php echo $has_youtube ? '' : 'text-center'; ?>">
                    <h3 class="text-sm font-medium text-purple-900 mb-2 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-book-bible text-purple-600"></i>
                        오늘의 말씀
                    </h3>
                    <?php if ($has_youtube) { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/word_view.php?wr_id=<?php echo $word['wr_id']; ?>"
                       class="block mb-2">
                        <?php if ($word['wr_subject']) { ?>
                            <h3 class="text-base font-bold mb-2 text-gray-900 text-left"><?php echo get_text($word['wr_subject']); ?></h3>
                        <?php } ?>
                        <?php echo $word_content; ?>
                    </a>
                    <?php } else { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/word_view.php?wr_id=<?php echo $word['wr_id']; ?>"
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
                        <a href="<?php echo G5_BBS_URL; ?>/word_list.php"
                           class="inline-block text-sm text-purple-600 hover:text-purple-800 font-medium">
                            전체 보기 →
                        </a>
                        <?php if ($is_admin) { ?>
                        <span class="text-gray-300">|</span>
                        <a href="<?php echo G5_BBS_URL; ?>/write_word.php"
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
                        등록된 말씀이 아직 없습니다
                    </p>
                    <?php if ($is_admin) { ?>
                    <a href="<?php echo G5_BBS_URL; ?>/write_word.php"
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

        <?php
        // 공지사항 조회 (gallery 게시판)
        $notice_board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = 'gallery'");
        $notice_list = array();
        if ($notice_board && $notice_board['bo_notice']) {
            $notice_ids = explode(',', $notice_board['bo_notice']);
            $notice_ids = array_filter(array_map('intval', $notice_ids));
            if (!empty($notice_ids)) {
                $notice_ids_str = implode(',', $notice_ids);
                $notice_sql = "SELECT * FROM {$g5['write_prefix']}gallery WHERE wr_id IN ({$notice_ids_str}) ORDER BY FIELD(wr_id, {$notice_ids_str})";
                $notice_result = sql_query($notice_sql);
                while ($notice_row = sql_fetch_array($notice_result)) {
                    $notice_list[] = $notice_row;
                }
            }
        }
        ?>

        <?php if (!empty($notice_list)) { ?>
        <!-- 공지사항 섹션 -->
        <section id="announcements" class="px-4 py-4 border-b border-gray-200">
            <div class="flex items-center gap-2 mb-3">
                <i class="fa-solid fa-bullhorn text-amber-500"></i>
                <h2 class="text-base font-semibold text-gray-800">공지사항</h2>
            </div>
            <div class="space-y-2">
                <?php foreach ($notice_list as $notice) {
                    $notice_href = G5_BBS_URL.'/post.php?bo_table=gallery&amp;wr_id='.$notice['wr_id'];
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

        <!-- 피드 섹션 -->
        <section id="feed" class="space-y-4 pb-20">
            <?php
            // 회원 전용: 로그인하지 않은 사용자는 피드를 볼 수 없음
            if (!$is_member) {
            ?>
            <div class="mx-4 p-8 bg-white rounded-2xl shadow-md text-center">
                <i class="fa-solid fa-lock text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-600 mb-2">회원 전용 콘텐츠입니다</p>
                <p class="text-sm text-gray-500 mb-4">로그인하시면 성산샘터의 모든 게시물을 확인하실 수 있습니다.</p>
                <a href="<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode(G5_BBS_URL.'/index.php'); ?>"
                   class="inline-block px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    로그인하기
                </a>
            </div>
            <?php
            } else {
            // 로그인한 회원에게만 피드 표시
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

                    // 첨부 이미지 (본문 이미지 우선)
                    $feed_img = '';

                    // 1. 먼저 본문(wr_content)에서 첫 번째 이미지 찾기
                    if (preg_match('/<img[^>]+src=["\']?([^"\'>\s]+)["\']?[^>]*>/i', $feed['wr_content'], $img_match)) {
                        $feed_img = $img_match[1];
                    }

                    // 2. 본문에 이미지가 없으면 첨부파일에서 찾기
                    if (!$feed_img) {
                        $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']}
                                                WHERE bo_table = 'gallery'
                                                AND wr_id = '{$feed['wr_id']}'
                                                AND bf_type BETWEEN 1 AND 3
                                                ORDER BY bf_no
                                                LIMIT 1");
                        $img = sql_fetch_array($img_result);
                        $feed_img = $img ? G5_DATA_URL.'/file/gallery/'.$img['bf_file'] : '';
                    }

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
                                    <span class="text-sm text-gray-700">좋아요 <?php echo number_format($good_cnt); ?>개</span>
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
                    <a href="<?php echo G5_BBS_URL; ?>/write_post.php"
                       class="inline-block px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        첫 번째 피드 작성하기
                    </a>
                    <?php } ?>
                </div>
                <?php
            }
            } // end if ($is_member) - 회원 전용 피드
            ?>
        </section>

    </div>

</main>

<!-- 출석 버튼 (로그인한 회원만 표시) -->
<?php if ($is_member) { ?>
<div id="floating-attendance"
     onclick="handleAttendanceClick()"
     class="fixed bottom-24 right-4 w-14 h-14 rounded-full shadow-lg flex items-center justify-center cursor-pointer hover:scale-110 transition-all z-40 attendance-btn"
     data-status="loading">
    <i class="fa-solid fa-spinner fa-spin text-white text-lg"></i>
</div>

<!-- 출석 결과 패널 -->
<div id="attendance-panel" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full max-h-[80vh] overflow-y-auto shadow-2xl">
        <div id="attendance-result" class="p-6">
            <!-- 출석 결과가 여기에 동적으로 추가됩니다 -->
        </div>
        <div class="border-t border-gray-100 p-4">
            <button onclick="closeAttendancePanel()" class="w-full py-3 bg-purple-600 text-white font-medium rounded-xl hover:bg-purple-700 transition-colors">
                확인
            </button>
        </div>
    </div>
</div>

<!-- 오늘의 출석 현황 패널 -->
<div id="attendance-list-panel" class="fixed top-16 right-0 w-full max-w-md h-screen bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out z-50 overflow-y-auto">
    <div class="sticky top-0 bg-white border-b border-gray-200 p-4 z-10">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-xl font-bold text-gray-800">오늘의 출석 현황</h2>
            <i id="close-attendance-list" onclick="closeAttendanceListPanel()" class="fa-solid fa-times text-gray-600 text-xl cursor-pointer hover:text-gray-800"></i>
        </div>
        <div class="flex items-center justify-between">
            <p id="attendance-list-count" class="text-sm text-purple-600">오늘 0명 출석</p>
            <div id="attendance-time-info" class="flex items-center gap-1 text-xs">
                <i class="fa-regular fa-clock text-gray-400"></i>
                <span class="text-gray-500">출석 가능: 04:30 ~ 23:59</span>
            </div>
        </div>
    </div>
    <div id="attendance-list-content" class="divide-y divide-gray-100">
        <!-- 출석자 목록이 여기에 동적으로 추가됩니다 -->
    </div>
</div>

<!-- 오늘의 출석 현황 미니 위젯 (버튼 위) -->
<div id="attendance-mini-widget"
     onclick="openAttendanceListPanel()"
     class="fixed bottom-40 right-4 bg-white rounded-xl shadow-lg p-2 cursor-pointer hover:shadow-xl transition-shadow z-39 hidden">
    <div class="flex items-center gap-2">
        <div class="flex -space-x-2" id="attendance-avatars">
            <!-- 최근 출석자 아바타 -->
        </div>
        <span id="attendance-count-badge" class="text-xs text-purple-600 font-medium">0명</span>
    </div>
</div>

<style>
.attendance-btn[data-status="available"] {
    background: linear-gradient(135deg, #8B5CF6, #7C3AED);
}
.attendance-btn[data-status="completed"] {
    background: linear-gradient(135deg, #10B981, #059669);
}
.attendance-btn[data-status="unavailable"] {
    background: #9CA3AF;
    cursor: not-allowed;
}
.attendance-btn[data-status="loading"] {
    background: linear-gradient(135deg, #8B5CF6, #7C3AED);
}
</style>
<?php } ?>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<script>
// 알림 시스템
(function() {
    const notificationBell = document.getElementById('notification-bell');
    const notificationBadge = document.getElementById('notification-badge');
    const notificationPanel = document.getElementById('notification-panel');
    const closeNotification = document.getElementById('close-notification');
    const notificationList = document.getElementById('notification-list');
    const markAllRead = document.getElementById('mark-all-read');

    // 알림 패널 열기/닫기
    notificationBell.addEventListener('click', function() {
        notificationPanel.classList.toggle('translate-x-full');
        if (!notificationPanel.classList.contains('translate-x-full')) {
            loadNotifications();
        }
    });

    closeNotification.addEventListener('click', function() {
        notificationPanel.classList.add('translate-x-full');
    });

    // 패널 외부 클릭시 닫기
    document.addEventListener('click', function(e) {
        if (!notificationPanel.contains(e.target) &&
            !notificationBell.contains(e.target) &&
            !notificationPanel.classList.contains('translate-x-full')) {
            notificationPanel.classList.add('translate-x-full');
        }
    });

    // 알림 개수 가져오기
    function loadNotificationCount() {
        fetch('<?php echo G5_BBS_URL; ?>/notification_api.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    notificationBadge.textContent = data.count > 99 ? '99+' : data.count;
                    notificationBadge.classList.remove('hidden');
                } else {
                    notificationBadge.classList.add('hidden');
                }
            })
            .catch(error => console.error('알림 개수 로딩 오류:', error));
    }

    // 알림 목록 가져오기
    function loadNotifications() {
        fetch('<?php echo G5_BBS_URL; ?>/notification_api.php?action=list&limit=50')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    renderNotifications(data.notifications);
                } else {
                    notificationList.innerHTML = `
                        <div class="flex items-center justify-center py-20">
                            <div class="text-center">
                                <i class="fa-regular fa-bell text-gray-300 text-5xl mb-4"></i>
                                <p class="text-gray-500">알림이 없습니다</p>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => console.error('알림 로딩 오류:', error));
    }

    // 알림 렌더링
    function renderNotifications(notifications) {
        notificationList.innerHTML = '';

        notifications.forEach(notification => {
            const notificationItem = createNotificationItem(notification);
            notificationList.appendChild(notificationItem);
        });
    }

    // 알림 아이템 생성
    function createNotificationItem(notification) {
        const div = document.createElement('div');
        div.className = `p-4 hover:bg-gray-50 cursor-pointer transition-colors ${notification.no_is_read == '0' ? 'bg-purple-50' : ''}`;

        const iconClass = getNotificationIcon(notification.no_type);
        const timeAgo = getTimeAgo(notification.no_datetime);

        div.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <i class="${iconClass} text-2xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm ${notification.no_is_read == '0' ? 'font-semibold' : 'font-normal'} text-gray-800 mb-1">
                        ${notification.no_content}
                    </p>
                    <p class="text-xs text-gray-500">${timeAgo}</p>
                </div>
                ${notification.no_is_read == '0' ? '<div class="flex-shrink-0"><div class="w-2 h-2 bg-purple-600 rounded-full"></div></div>' : ''}
            </div>
        `;

        div.addEventListener('click', function() {
            handleNotificationClick(notification);
        });

        return div;
    }

    // 알림 타입별 아이콘
    function getNotificationIcon(type) {
        switch(type) {
            case 'comment':
                return 'fa-regular fa-comment text-blue-500';
            case 'reply':
                return 'fa-solid fa-reply text-green-500';
            case 'good':
                return 'fa-solid fa-heart text-red-500';
            case 'word':
                return 'fa-solid fa-book-bible text-purple-600';
            default:
                return 'fa-regular fa-bell text-gray-500';
        }
    }

    // 시간 계산
    function getTimeAgo(datetime) {
        const now = new Date();
        const past = new Date(datetime);
        const diff = Math.floor((now - past) / 1000);

        if (diff < 60) return '방금 전';
        if (diff < 3600) return Math.floor(diff / 60) + '분 전';
        if (diff < 86400) return Math.floor(diff / 3600) + '시간 전';
        if (diff < 604800) return Math.floor(diff / 86400) + '일 전';
        return past.toLocaleDateString('ko-KR');
    }

    // 알림 클릭 처리
    function handleNotificationClick(notification) {
        // 읽음 처리
        if (notification.no_is_read == '0') {
            markAsRead(notification.no_id);
        }

        // 해당 페이지로 이동
        if (notification.no_url) {
            window.location.href = notification.no_url;
        }
    }

    // 알림 읽음 처리
    function markAsRead(notificationId) {
        const formData = new FormData();
        formData.append('no_id', notificationId);

        fetch('<?php echo G5_BBS_URL; ?>/notification_api.php?action=read', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotificationCount();
            }
        })
        .catch(error => console.error('읽음 처리 오류:', error));
    }

    // 모두 읽음 처리
    markAllRead.addEventListener('click', function() {
        fetch('<?php echo G5_BBS_URL; ?>/notification_api.php?action=read_all', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
                loadNotificationCount();
            }
        })
        .catch(error => console.error('모두 읽음 처리 오류:', error));
    });

    // 페이지 로드시 알림 개수 확인
    <?php if ($is_member) { ?>
    loadNotificationCount();
    // 30초마다 알림 개수 갱신
    setInterval(loadNotificationCount, 30000);
    <?php } ?>
})();

<?php if ($is_member) { ?>
// 출석 시스템
(function() {
    const attendanceBtn = document.getElementById('floating-attendance');
    const attendancePanel = document.getElementById('attendance-panel');
    const attendanceResult = document.getElementById('attendance-result');
    const attendanceListPanel = document.getElementById('attendance-list-panel');
    const attendanceListContent = document.getElementById('attendance-list-content');
    const attendanceListCount = document.getElementById('attendance-list-count');
    const attendanceMiniWidget = document.getElementById('attendance-mini-widget');
    const attendanceAvatars = document.getElementById('attendance-avatars');
    const attendanceCountBadge = document.getElementById('attendance-count-badge');

    let currentStatus = {
        is_attendance_time: false,
        has_attended: false,
        rank: 0,
        consecutive_days: 0,
        total_count: 0
    };

    // 페이지 로드시 출석 상태 확인
    loadAttendanceStatus();

    // 30초마다 출석 상태 갱신
    setInterval(loadAttendanceStatus, 30000);

    // 출석 상태 로드
    function loadAttendanceStatus() {
        fetch('<?php echo G5_BBS_URL; ?>/ajax.attendance.php?action=status')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentStatus = data.data;
                    updateAttendanceButton();
                    loadAttendanceMiniWidget();
                }
            })
            .catch(error => console.error('출석 상태 로딩 오류:', error));
    }

    // 버튼 상태 업데이트
    function updateAttendanceButton() {
        let status = 'available';
        let icon = 'fa-check';
        let tooltip = '출석하기';

        if (!currentStatus.is_attendance_time) {
            status = 'unavailable';
            icon = 'fa-moon';
            tooltip = '출석 불가 시간\n(04:30 ~ 23:59)';
            if (currentStatus.time_until_start) {
                tooltip += '\n' + currentStatus.time_until_start + ' 후 시작';
            }
        } else if (currentStatus.has_attended) {
            status = 'completed';
            icon = 'fa-check-double';
            tooltip = currentStatus.rank + '등 출석 완료!';
        }

        attendanceBtn.dataset.status = status;
        attendanceBtn.innerHTML = `<i class="fa-solid ${icon} text-white text-lg"></i>`;
        attendanceBtn.title = tooltip;
    }

    // 출석 버튼 클릭 처리
    window.handleAttendanceClick = function() {
        // 출석 불가 시간
        if (!currentStatus.is_attendance_time) {
            showAttendanceResult({
                success: false,
                type: 'unavailable',
                message: '출석 불가 시간입니다',
                description: '출석 가능 시간: 04:30 ~ 23:59',
                time_until_start: currentStatus.time_until_start
            });
            return;
        }

        // 이미 출석 완료
        if (currentStatus.has_attended) {
            showAttendanceResult({
                success: true,
                type: 'already',
                message: '이미 출석하셨습니다!',
                rank: currentStatus.rank,
                consecutive_days: currentStatus.consecutive_days,
                total_count: currentStatus.total_count
            });
            return;
        }

        // 출석 처리
        attendanceBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-white text-lg"></i>';

        const formData = new FormData();
        formData.append('action', 'check');

        fetch('<?php echo G5_BBS_URL; ?>/ajax.attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentStatus.has_attended = true;
                currentStatus.rank = data.data.rank;
                currentStatus.consecutive_days = data.data.consecutive_days;
                currentStatus.total_count = data.data.total_count;

                showAttendanceResult({
                    success: true,
                    type: 'new',
                    message: '출석 완료!',
                    rank: data.data.rank,
                    attend_time: data.data.attend_time,
                    consecutive_days: data.data.consecutive_days,
                    total_count: data.data.total_count,
                    point: data.data.point
                });

                updateAttendanceButton();
                loadAttendanceMiniWidget();
            } else {
                if (data.error === 'already_attended') {
                    currentStatus.has_attended = true;
                    currentStatus.rank = data.data.rank;
                    updateAttendanceButton();
                }
                showAttendanceResult({
                    success: false,
                    type: data.error,
                    message: data.message
                });
            }
        })
        .catch(error => {
            console.error('출석 처리 오류:', error);
            updateAttendanceButton();
        });
    };

    // 출석 결과 표시
    function showAttendanceResult(result) {
        let html = '';

        if (result.type === 'unavailable') {
            html = `
                <div class="text-center">
                    <div class="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-moon text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">${result.message}</h3>
                    <p class="text-gray-600 mb-2">${result.description}</p>
                    ${result.time_until_start ? `<p class="text-purple-600 font-medium">${result.time_until_start} 후 출석 가능</p>` : ''}
                </div>
            `;
        } else if (result.type === 'already') {
            html = `
                <div class="text-center">
                    <div class="w-20 h-20 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-check-double text-green-500 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">${result.message}</h3>
                    <div class="bg-gray-50 rounded-xl p-4 mb-4">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-bold text-purple-600">${result.rank}등</p>
                                <p class="text-xs text-gray-500">오늘 순위</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-orange-500">${result.consecutive_days}일</p>
                                <p class="text-xs text-gray-500">연속 출석</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-blue-500">${result.total_count}명</p>
                                <p class="text-xs text-gray-500">오늘 출석</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else if (result.type === 'new') {
            let rankEmoji = '';
            let rankClass = 'text-purple-600';
            if (result.rank === 1) {
                rankEmoji = '🥇';
                rankClass = 'text-yellow-500';
            } else if (result.rank === 2) {
                rankEmoji = '🥈';
                rankClass = 'text-gray-400';
            } else if (result.rank === 3) {
                rankEmoji = '🥉';
                rankClass = 'text-orange-400';
            }

            html = `
                <div class="text-center">
                    <div class="w-24 h-24 mx-auto mb-4 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center shadow-lg">
                        ${result.rank <= 3 ? `<span class="text-4xl">${rankEmoji}</span>` : '<i class="fa-solid fa-check text-white text-4xl"></i>'}
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">${result.message}</h3>
                    <p class="text-lg ${rankClass} font-bold mb-1">${result.rank}등으로 출석!</p>
                    <p class="text-sm text-gray-500 mb-4">${result.attend_time} 출석 · +${result.point}P</p>
                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-4 mb-4">
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-bold text-orange-500">${result.consecutive_days}일</p>
                                <p class="text-xs text-gray-500">연속 출석</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-blue-500">${result.total_count}명</p>
                                <p class="text-xs text-gray-500">오늘 출석</p>
                            </div>
                        </div>
                    </div>
                    ${result.consecutive_days >= 7 ? `
                    <div class="flex items-center justify-center gap-2 text-orange-500">
                        <i class="fa-solid fa-fire"></i>
                        <span class="font-medium">${result.consecutive_days}일 연속 출석 달성!</span>
                    </div>
                    ` : ''}
                </div>
            `;
        } else {
            html = `
                <div class="text-center">
                    <div class="w-20 h-20 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-exclamation text-red-500 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">${result.message}</h3>
                </div>
            `;
        }

        attendanceResult.innerHTML = html;
        attendancePanel.classList.remove('hidden');
        attendancePanel.classList.add('flex');
    }

    // 출석 결과 패널 닫기
    window.closeAttendancePanel = function() {
        attendancePanel.classList.add('hidden');
        attendancePanel.classList.remove('flex');
    };

    // 출석 현황 패널 열기
    window.openAttendanceListPanel = function() {
        loadAttendanceList();
        attendanceListPanel.classList.remove('translate-x-full');
    };

    // 출석 현황 패널 닫기
    window.closeAttendanceListPanel = function() {
        attendanceListPanel.classList.add('translate-x-full');
    };

    // 출석자 목록 로드
    function loadAttendanceList() {
        fetch('<?php echo G5_BBS_URL; ?>/ajax.attendance.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderAttendanceList(data.data.list, data.data.total_count);
                }
            })
            .catch(error => console.error('출석자 목록 로딩 오류:', error));
    }

    // 출석자 목록 렌더링
    function renderAttendanceList(list, totalCount) {
        attendanceListCount.textContent = `오늘 ${totalCount}명 출석`;

        if (list.length === 0) {
            attendanceListContent.innerHTML = `
                <div class="flex items-center justify-center py-20">
                    <div class="text-center">
                        <i class="fa-regular fa-calendar-check text-gray-300 text-5xl mb-4"></i>
                        <p class="text-gray-500">아직 출석자가 없습니다</p>
                    </div>
                </div>
            `;
            return;
        }

        let html = '';
        list.forEach(item => {
            let rankBadge = '';
            if (item.rank === 1) {
                rankBadge = '<span class="text-lg">🥇</span>';
            } else if (item.rank === 2) {
                rankBadge = '<span class="text-lg">🥈</span>';
            } else if (item.rank === 3) {
                rankBadge = '<span class="text-lg">🥉</span>';
            } else {
                rankBadge = `<span class="w-6 h-6 bg-gray-200 rounded-full flex items-center justify-center text-xs text-gray-600">${item.rank}</span>`;
            }

            html += `
                <div class="p-4 hover:bg-gray-50 transition-colors flex items-center gap-3">
                    <div class="flex-shrink-0">${rankBadge}</div>
                    <img src="${item.profile_img}" class="w-10 h-10 rounded-full object-cover" alt="${item.mb_name}">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-800">${item.mb_name}</p>
                        <p class="text-xs text-gray-500">${item.attend_time} 출석</p>
                    </div>
                </div>
            `;
        });

        attendanceListContent.innerHTML = html;
    }

    // 미니 위젯 로드
    function loadAttendanceMiniWidget() {
        fetch('<?php echo G5_BBS_URL; ?>/ajax.attendance.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.total_count > 0) {
                    attendanceMiniWidget.classList.remove('hidden');

                    // 상위 3명 아바타 표시
                    let avatarsHtml = '';
                    const topThree = data.data.list.slice(0, 3);
                    topThree.forEach(item => {
                        avatarsHtml += `<img src="${item.profile_img}" class="w-6 h-6 rounded-full border-2 border-white object-cover" alt="${item.mb_name}">`;
                    });
                    attendanceAvatars.innerHTML = avatarsHtml;
                    attendanceCountBadge.textContent = data.data.total_count + '명';
                } else {
                    attendanceMiniWidget.classList.add('hidden');
                }
            })
            .catch(error => console.error('미니 위젯 로딩 오류:', error));
    }

    // 패널 외부 클릭시 닫기
    document.addEventListener('click', function(e) {
        if (!attendanceListPanel.contains(e.target) &&
            !attendanceMiniWidget.contains(e.target) &&
            !attendanceListPanel.classList.contains('translate-x-full')) {
            closeAttendanceListPanel();
        }
    });

    // 출석 결과 패널 외부 클릭시 닫기
    attendancePanel.addEventListener('click', function(e) {
        if (e.target === attendancePanel) {
            closeAttendancePanel();
        }
    });
})();
<?php } ?>
</script>

</body>
</html>
