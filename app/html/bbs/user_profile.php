<?php
include_once('./_common.php');

// mb_id 파라미터 받기
$mb_id = isset($_GET['mb_id']) ? clean_xss_tags($_GET['mb_id']) : '';

if (!$mb_id) {
    alert_close('회원 아이디가 지정되지 않았습니다.');
}

// 회원 정보 가져오기
$mb = get_member($mb_id);

if (!$mb['mb_id']) {
    alert_close('회원정보가 존재하지 않습니다.');
}

$g5['title'] = $mb['mb_nick'].'님의 프로필';

// 프로필 이미지
$profile_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
$profile_path = G5_DATA_PATH.'/member_image/'.substr($mb['mb_id'], 0, 2).'/'.$mb['mb_id'].'.gif';
if (file_exists($profile_path)) {
    $profile_photo = G5_DATA_URL.'/member_image/'.substr($mb['mb_id'], 0, 2).'/'.$mb['mb_id'].'.gif';
}

// 통계 정보 가져오기
// 1. 게시글 수 (gallery 게시판)
$post_count_sql = "SELECT COUNT(*) as cnt FROM {$g5['write_prefix']}gallery
                   WHERE mb_id = '{$mb_id}' AND wr_is_comment = 0";
$post_count = sql_fetch($post_count_sql);
$total_posts = $post_count['cnt'];

// 2. 댓글 수 (gallery 게시판)
$comment_count_sql = "SELECT COUNT(*) as cnt FROM {$g5['write_prefix']}gallery
                      WHERE mb_id = '{$mb_id}' AND wr_is_comment = 1";
$comment_count = sql_fetch($comment_count_sql);
$total_comments = $comment_count['cnt'];

// 3. 받은 아멘(좋아요) 수
$good_count_sql = "SELECT SUM(wr_good) as total FROM {$g5['write_prefix']}gallery
                   WHERE mb_id = '{$mb_id}' AND wr_is_comment = 0";
$good_count = sql_fetch($good_count_sql);
$total_goods = $good_count['total'] ? $good_count['total'] : 0;

// 최근 게시글 가져오기
$recent_posts_sql = "SELECT * FROM {$g5['write_prefix']}gallery
                     WHERE mb_id = '{$mb_id}' AND wr_is_comment = 0
                     ORDER BY wr_id DESC
                     LIMIT 9";
$recent_posts = sql_query($recent_posts_sql);

// 최근 댓글 가져오기
$recent_comments_sql = "SELECT a.*, b.wr_subject as parent_subject
                        FROM {$g5['write_prefix']}gallery a
                        LEFT JOIN {$g5['write_prefix']}gallery b ON (a.wr_parent = b.wr_id)
                        WHERE a.mb_id = '{$mb_id}' AND a.wr_is_comment = 1
                        ORDER BY a.wr_id DESC
                        LIMIT 10";
$recent_comments = sql_query($recent_comments_sql);

// 가입일 계산
$join_date = date('Y년 m월 d일', strtotime($mb['mb_datetime']));
$days_since = floor((strtotime('now') - strtotime($mb['mb_datetime'])) / 86400);

// 온라인 상태 확인
$online_check_sql = "SELECT COUNT(*) as cnt FROM {$g5['login_table']}
                     WHERE mb_id = '{$mb_id}'";
$online_check = sql_fetch($online_check_sql);
$is_online = $online_check['cnt'] > 0;
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { display: none; }
        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #EEF3F8;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
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

<header class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="flex items-center justify-between px-4 py-3 max-w-2xl mx-auto">
        <button onclick="history.back()" class="text-grace-green hover:text-deep-purple">
            <i class="fa-solid fa-arrow-left text-xl"></i>
        </button>
        <h1 class="text-lg font-semibold text-grace-green">프로필</h1>
        <div class="w-6"></div>
    </div>
</header>

<main class="pt-16 pb-20 max-w-2xl mx-auto">

    <!-- 프로필 정보 섹션 -->
    <section class="bg-white px-6 py-8 mb-2">
        <div class="flex items-start gap-6 mb-6">
            <!-- 프로필 사진 -->
            <div class="relative flex-shrink-0">
                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 p-1">
                    <img src="<?php echo $profile_photo; ?>"
                         class="w-full h-full rounded-full object-cover border-2 border-white"
                         alt="<?php echo $mb['mb_nick']; ?>">
                </div>
                <?php if ($is_online) { ?>
                <!-- 온라인 상태 표시 -->
                <div class="absolute bottom-1 right-1 w-6 h-6 bg-green-500 rounded-full border-3 border-white"></div>
                <?php } ?>
            </div>

            <!-- 기본 정보 -->
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                    <h2 class="text-xl font-bold text-gray-800"><?php echo $mb['mb_nick']; ?></h2>
                    <?php if ($is_online) { ?>
                    <span class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full flex items-center gap-1">
                        <i class="fa-solid fa-circle text-green-500 text-[6px] animate-pulse"></i>
                        활동중
                    </span>
                    <?php } ?>
                </div>
                <p class="text-sm text-gray-500 mb-3">@<?php echo $mb['mb_id']; ?></p>

                <!-- 통계 -->
                <div class="flex gap-6 mb-4">
                    <div class="text-center">
                        <div class="text-lg font-bold text-gray-800"><?php echo number_format($total_posts); ?></div>
                        <div class="text-xs text-gray-500">게시글</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-gray-800"><?php echo number_format($total_comments); ?></div>
                        <div class="text-xs text-gray-500">댓글</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-red-500"><?php echo number_format($total_goods); ?></div>
                        <div class="text-xs text-gray-500">아멘</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 자기소개 -->
        <?php if ($mb['mb_profile']) { ?>
        <div class="mb-4">
            <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line"><?php echo get_text($mb['mb_profile']); ?></p>
        </div>
        <?php } ?>

        <!-- 추가 정보 -->
        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
            <div class="flex items-center gap-1">
                <i class="fa-solid fa-calendar text-purple-500"></i>
                <?php echo $join_date; ?> 가입 (<?php echo number_format($days_since); ?>일)
            </div>
            <div class="flex items-center gap-1">
                <i class="fa-solid fa-coins text-yellow-500"></i>
                <?php echo number_format($mb['mb_point']); ?> 포인트
            </div>
        </div>
    </section>

    <!-- 탭 섹션 -->
    <section class="bg-white mb-2">
        <div class="flex border-b border-gray-200">
            <button onclick="switchTab('posts')" id="tab-posts"
                    class="flex-1 py-3 text-sm font-semibold text-deep-purple border-b-2 border-deep-purple">
                <i class="fa-solid fa-grid-2"></i> 게시글
            </button>
            <button onclick="switchTab('comments')" id="tab-comments"
                    class="flex-1 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent">
                <i class="fa-solid fa-comment"></i> 댓글
            </button>
        </div>

        <!-- 게시글 탭 -->
        <div id="content-posts" class="tab-content active">
            <?php if (sql_num_rows($recent_posts) > 0) { ?>
            <div class="grid grid-cols-3 gap-1 p-1">
                <?php while ($post = sql_fetch_array($recent_posts)) {
                    // 첨부 이미지 찾기
                    $img_sql = "SELECT bf_file FROM {$g5['board_file_table']}
                               WHERE bo_table = 'gallery' AND wr_id = '{$post['wr_id']}'
                               AND bf_type BETWEEN 1 AND 3
                               ORDER BY bf_no LIMIT 1";
                    $img = sql_fetch($img_sql);

                    if ($img) {
                        $post_img = G5_DATA_URL.'/file/gallery/'.$img['bf_file'];
                    } else {
                        // 이미지가 없으면 텍스트로 표시
                        $post_img = '';
                    }
                ?>
                <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&wr_id=<?php echo $post['wr_id']; ?>"
                   class="aspect-square bg-gradient-to-br from-purple-50 to-pink-50 flex items-center justify-center overflow-hidden relative group">
                    <?php if ($post_img) { ?>
                        <img src="<?php echo $post_img; ?>" class="w-full h-full object-cover group-hover:opacity-90 transition-opacity" alt="">
                    <?php } else { ?>
                        <div class="p-2 text-center">
                            <p class="text-xs text-gray-600 line-clamp-4"><?php echo strip_tags($post['wr_subject']); ?></p>
                        </div>
                    <?php } ?>
                    <!-- 호버시 통계 표시 -->
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
                        <div class="flex gap-3 text-white text-xs">
                            <span><i class="fa-solid fa-heart"></i> <?php echo $post['wr_good']; ?></span>
                            <span><i class="fa-solid fa-comment"></i> <?php echo $post['wr_comment']; ?></span>
                        </div>
                    </div>
                </a>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="py-16 text-center">
                <i class="fa-regular fa-images text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-500">아직 작성한 게시글이 없습니다</p>
            </div>
            <?php } ?>
        </div>

        <!-- 댓글 탭 -->
        <div id="content-comments" class="tab-content">
            <?php if (sql_num_rows($recent_comments) > 0) { ?>
            <div class="divide-y divide-gray-100">
                <?php while ($comment = sql_fetch_array($recent_comments)) { ?>
                <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&wr_id=<?php echo $comment['wr_parent']; ?>#c_<?php echo $comment['wr_id']; ?>"
                   class="block p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-xs text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-reply text-purple-500"></i>
                            <?php echo cut_str($comment['parent_subject'], 30); ?>
                        </p>
                        <span class="text-xs text-gray-400"><?php echo date('m/d', strtotime($comment['wr_datetime'])); ?></span>
                    </div>
                    <p class="text-sm text-gray-700 line-clamp-2"><?php echo strip_tags($comment['wr_content']); ?></p>
                </a>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="py-16 text-center">
                <i class="fa-regular fa-comment text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-500">아직 작성한 댓글이 없습니다</p>
            </div>
            <?php } ?>
        </div>
    </section>

</main>

<script>
function switchTab(tab) {
    // 모든 탭 버튼 비활성화
    document.getElementById('tab-posts').className = 'flex-1 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent';
    document.getElementById('tab-comments').className = 'flex-1 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent';

    // 모든 탭 컨텐츠 숨기기
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));

    // 선택한 탭 활성화
    if (tab === 'posts') {
        document.getElementById('tab-posts').className = 'flex-1 py-3 text-sm font-semibold text-deep-purple border-b-2 border-deep-purple';
        document.getElementById('content-posts').classList.add('active');
    } else {
        document.getElementById('tab-comments').className = 'flex-1 py-3 text-sm font-semibold text-deep-purple border-b-2 border-deep-purple';
        document.getElementById('content-comments').classList.add('active');
    }
}
</script>

</body>
</html>
