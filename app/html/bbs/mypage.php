<?php
include_once('./_common.php');

// 로그인 체크
if (!$is_member) {
    alert('로그인이 필요합니다.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/mypage.php'));
}

$g5['title'] = '내 정보';

// 회원 정보 가져오기
$mb = get_member($member['mb_id']);

// 가입 경과 기간 계산
$join_date = strtotime($mb['mb_datetime']);
$now = G5_SERVER_TIME;
$diff = $now - $join_date;
$years = floor($diff / (365 * 60 * 60 * 24));
$months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));

// 게시물 수 조회 - 모든 게시판의 게시물 합계
$sql = "SELECT COUNT(*) as cnt FROM (";
$board_list = sql_query("SELECT bo_table FROM {$g5['board_table']}");
$first = true;
while ($board = sql_fetch_array($board_list)) {
    if (!$first) $sql .= " UNION ALL ";
    $sql .= "SELECT wr_id FROM {$g5['write_prefix']}{$board['bo_table']} WHERE mb_id = '{$mb['mb_id']}'";
    $first = false;
}
$sql .= ") as total_posts";

if ($first) {
    // 게시판이 없는 경우
    $post_count = 0;
} else {
    $result = sql_fetch($sql);
    $post_count = ($result && isset($result['cnt'])) ? $result['cnt'] : 0;
}

// 포인트
$point = number_format($mb['mb_point']);

// 최근 로그인 일수 - 포인트 테이블에서 로그인 기록 조회
$attendance_days = 0;
$po_sql = "SELECT COUNT(DISTINCT DATE(po_datetime)) as cnt FROM {$g5['point_table']} WHERE mb_id = '{$mb['mb_id']}' AND po_content LIKE '%로그인%'";
$po_result = sql_fetch($po_sql);
if ($po_result) {
    $attendance_days = (int)$po_result['cnt'];
}

// 프로필 이미지 경로
$profile_img = G5_DATA_URL.'/member_image/'.substr($mb['mb_id'], 0, 2).'/'.$mb['mb_id'].'.gif';
if (!file_exists(G5_DATA_PATH.'/member_image/'.substr($mb['mb_id'], 0, 2).'/'.$mb['mb_id'].'.gif')) {
    $profile_img = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
}

// 내게 온 댓글 조회 (gallery 게시판)
$my_comments_sql = "SELECT c.*,
                           p.wr_subject as parent_subject,
                           m.mb_nick as comment_author_nick,
                           m.mb_id as comment_author_id
                    FROM {$g5['write_prefix']}gallery c
                    LEFT JOIN {$g5['write_prefix']}gallery p ON (c.wr_parent = p.wr_id)
                    LEFT JOIN {$g5['member_table']} m ON (c.mb_id = m.mb_id)
                    WHERE c.wr_is_comment = 1
                    AND p.mb_id = '{$mb['mb_id']}'
                    AND c.mb_id != '{$mb['mb_id']}'
                    ORDER BY c.wr_datetime DESC
                    LIMIT 5";
$my_comments_result = sql_query($my_comments_sql);

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
        ::-webkit-scrollbar { display: none;}
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; }
        .profile-gradient { background: linear-gradient(135deg, #E8E2F7 0%, #B19CD9 50%, #6B46C1 100%); }
        .shadow-warm { box-shadow: 0 4px 20px rgba(177, 156, 217, 0.15); }
        .stat-card { background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(232,226,247,0.3) 100%); }
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
        <button onclick="goBack()" class="flex items-center gap-2">
            <i class="fa-solid fa-arrow-left text-grace-green text-lg"></i>
        </button>
        <h1 class="text-lg font-semibold text-grace-green">내 정보</h1>
        <button class="flex items-center gap-2">
            <i class="fa-solid fa-gear text-grace-green text-lg"></i>
        </button>
    </div>
</header>

<main id="main-content" class="pt-16 pb-20">

    <section id="profile-header" class="relative">
        <div class="profile-gradient h-32"></div>
        <div class="absolute -bottom-12 left-1/2 transform -translate-x-1/2">
            <div class="w-24 h-24 bg-white rounded-full p-1 shadow-warm">
                <img src="<?php echo $profile_img; ?>" class="w-full h-full rounded-full object-cover" alt="프로필 이미지">
            </div>
        </div>
    </section>

    <section id="profile-info" class="pt-16 px-4 text-center">
        <h2 class="text-xl font-semibold text-grace-green mb-1"><?php echo get_text($mb['mb_name']); ?></h2>
        <p class="text-sm text-gray-500 mb-2"><?php echo get_text($mb['mb_nick']); ?></p>
        <p class="text-xs text-grace-green mb-4">
            <?php
            if ($years > 0) {
                echo "함께한 지 {$years}년 {$months}개월";
            } else {
                echo "함께한 지 {$months}개월";
            }
            ?>
        </p>

        <div class="flex justify-center gap-6 mb-6">
            <div class="text-center">
                <div class="text-lg font-semibold text-grace-green"><?php echo $post_count; ?></div>
                <div class="text-xs text-gray-500">게시물</div>
            </div>
            <div class="text-center">
                <div class="text-lg font-semibold text-grace-green"><?php echo $point; ?></div>
                <div class="text-xs text-gray-500">포인트</div>
            </div>
            <div class="text-center">
                <div class="text-lg font-semibold text-grace-green"><?php echo $attendance_days; ?></div>
                <div class="text-xs text-gray-500">출석일</div>
            </div>
        </div>

        <?php if ($mb['mb_profile']) { ?>
        <p class="text-sm text-grace-green leading-relaxed px-4">
            <?php echo nl2br(get_text($mb['mb_profile'])); ?>
        </p>
        <?php } else { ?>
        <p class="text-sm text-gray-400 leading-relaxed px-4">
            소개 내용이 없습니다.
        </p>
        <?php } ?>
    </section>


    <section id="achievements" class="px-4 mt-6">
        <h3 class="text-lg font-semibold text-grace-green mb-4">획득한 뱃지</h3>

        <div class="grid grid-cols-3 gap-3">
            <?php if ($attendance_days >= 90) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm">
                <div class="w-12 h-12 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-crown text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">출석왕</div>
                <div class="text-xs text-gray-500">3개월 연속</div>
            </div>
            <?php } ?>

            <?php if ($post_count >= 10) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-music text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">활동적인</div>
                <div class="text-xs text-gray-500">활발한 참여</div>
            </div>
            <?php } ?>

            <?php if ($mb['mb_point'] >= 1000) { ?>
            <div class="bg-white rounded-2xl p-4 text-center shadow-warm">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <i class="fa-solid fa-heart text-white"></i>
                </div>
                <div class="text-xs font-medium text-grace-green">포인트왕</div>
                <div class="text-xs text-gray-500">포인트 우수자</div>
            </div>
            <?php } ?>
        </div>
    </section>

    <section id="recent-activity" class="px-4 mt-6">
        <h3 class="text-lg font-semibold text-grace-green mb-4">최근 활동</h3>

        <div class="space-y-3">
            <?php
            // 최근 게시물 조회 - 각 게시판에서 개별적으로 조회
            $recent_posts = array();
            $board_list = sql_query("SELECT bo_table FROM {$g5['board_table']} LIMIT 10");

            while ($board = sql_fetch_array($board_list)) {
                $bo_table = $board['bo_table'];
                $write_table = $g5['write_prefix'] . $bo_table;

                // 테이블 존재 여부 확인
                $table_check = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
                if (!sql_num_rows($table_check)) {
                    continue;
                }

                // 컬럼 존재 여부 확인
                $column_check = sql_query("SHOW COLUMNS FROM {$write_table} WHERE Field IN ('wr_subject', 'wr_datetime')", false);
                if (sql_num_rows($column_check) < 2) {
                    continue;
                }

                $sql = "SELECT wr_id, wr_subject, wr_datetime FROM {$write_table}
                        WHERE mb_id = '{$mb['mb_id']}'
                        ORDER BY wr_datetime DESC
                        LIMIT 3";
                $result = sql_query($sql, false);

                if ($result) {
                    while ($row = sql_fetch_array($result)) {
                        if (isset($row['wr_datetime']) && isset($row['wr_subject']) && $row['wr_datetime'] && $row['wr_subject']) {
                            $recent_posts[] = array(
                                'bo_table' => $bo_table,
                                'wr_id' => $row['wr_id'],
                                'wr_subject' => $row['wr_subject'],
                                'wr_datetime' => $row['wr_datetime']
                            );
                        }
                    }
                }
            }

            // 날짜순으로 정렬
            usort($recent_posts, function($a, $b) {
                return strtotime($b['wr_datetime']) - strtotime($a['wr_datetime']);
            });

            // 최대 3개만 표시
            $recent_posts = array_slice($recent_posts, 0, 3);

            if (count($recent_posts) > 0) {
                foreach ($recent_posts as $recent) {
                    $time_diff = time() - strtotime($recent['wr_datetime']);
                    if ($time_diff < 3600) {
                        $time_str = floor($time_diff / 60) . '분 전';
                    } elseif ($time_diff < 86400) {
                        $time_str = floor($time_diff / 3600) . '시간 전';
                    } else {
                        $time_str = floor($time_diff / 86400) . '일 전';
                    }
                    ?>
                    <div class="bg-white rounded-2xl p-4 shadow-warm">
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fa-solid fa-pen text-lilac"></i>
                            <span class="text-sm text-grace-green truncate flex-1"><?php echo get_text($recent['wr_subject']); ?></span>
                            <span class="text-xs text-gray-500 ml-auto"><?php echo $time_str; ?></span>
                        </div>
                    </div>
                    <?php
                }
            } else {
                ?>
                <div class="bg-white rounded-2xl p-4 shadow-warm text-center">
                    <span class="text-sm text-gray-400">최근 활동이 없습니다.</span>
                </div>
                <?php
            }
            ?>
        </div>
    </section>

    <section id="my-comments" class="px-4 mt-6">
        <h3 class="text-lg font-semibold text-grace-green mb-4 flex items-center justify-between">
            <span>내게 온 댓글</span>
            <?php if (sql_num_rows($my_comments_result) > 0) { ?>
            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                <?php echo sql_num_rows($my_comments_result); ?>개
            </span>
            <?php } ?>
        </h3>

        <div class="space-y-3">
            <?php
            if (sql_num_rows($my_comments_result) > 0) {
                while ($comment = sql_fetch_array($my_comments_result)) {
                    // 시간 계산
                    $time_diff = time() - strtotime($comment['wr_datetime']);
                    if ($time_diff < 3600) {
                        $time_str = floor($time_diff / 60) . '분 전';
                    } elseif ($time_diff < 86400) {
                        $time_str = floor($time_diff / 3600) . '시간 전';
                    } else {
                        $time_str = floor($time_diff / 86400) . '일 전';
                    }

                    // 댓글 작성자 프로필 이미지
                    $comment_author_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
                    if ($comment['comment_author_id']) {
                        $author_profile_path = G5_DATA_PATH.'/member_image/'.substr($comment['comment_author_id'], 0, 2).'/'.$comment['comment_author_id'].'.gif';
                        if (file_exists($author_profile_path)) {
                            $comment_author_photo = G5_DATA_URL.'/member_image/'.substr($comment['comment_author_id'], 0, 2).'/'.$comment['comment_author_id'].'.gif';
                        }
                    }
                    ?>
                    <a href="<?php echo G5_BBS_URL; ?>/post.php?bo_table=gallery&wr_id=<?php echo $comment['wr_parent']; ?>#c_<?php echo $comment['wr_id']; ?>"
                       class="bg-white rounded-2xl p-4 shadow-warm block hover:shadow-lg transition-shadow">
                        <div class="flex items-start gap-3 mb-3">
                            <img src="<?php echo $comment_author_photo; ?>"
                                 class="w-10 h-10 rounded-full object-cover flex-shrink-0"
                                 alt="<?php echo $comment['comment_author_nick']; ?>">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-semibold text-grace-green"><?php echo $comment['comment_author_nick']; ?></span>
                                    <span class="text-xs text-gray-400"><?php echo $time_str; ?></span>
                                </div>
                                <p class="text-xs text-gray-500 mb-2">
                                    <i class="fa-solid fa-reply text-purple-500"></i>
                                    <?php echo cut_str($comment['parent_subject'], 30); ?>에 댓글
                                </p>
                                <p class="text-sm text-gray-700 line-clamp-2"><?php echo strip_tags($comment['wr_content']); ?></p>
                            </div>
                        </div>
                    </a>
                    <?php
                }
            } else {
                ?>
                <div class="bg-white rounded-2xl p-8 shadow-warm text-center">
                    <i class="fa-regular fa-comment text-gray-300 text-4xl mb-3"></i>
                    <p class="text-sm text-gray-400">아직 받은 댓글이 없습니다.</p>
                </div>
                <?php
            }
            ?>
        </div>
    </section>

    <section id="settings-menu" class="px-4 mt-6 pb-6">
        <div class="bg-white rounded-2xl shadow-warm overflow-hidden">
            <a href="<?php echo G5_BBS_URL; ?>/member_confirm.php?url=<?php echo urlencode(G5_BBS_URL.'/register_form.php'); ?>" class="w-full flex items-center justify-between p-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-user-edit text-grace-green"></i>
                    <span class="text-sm text-grace-green">프로필 편집</span>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-400 text-sm"></i>
            </a>

            <a href="<?php echo G5_BBS_URL; ?>/point.php" class="w-full flex items-center justify-between p-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-coins text-grace-green"></i>
                    <span class="text-sm text-grace-green">포인트 내역</span>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-400 text-sm"></i>
            </a>

            <a href="<?php echo G5_BBS_URL; ?>/scrap.php" class="w-full flex items-center justify-between p-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-bookmark text-grace-green"></i>
                    <span class="text-sm text-grace-green">스크랩</span>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-400 text-sm"></i>
            </a>

            <button onclick="confirmLogout()" class="w-full flex items-center justify-between p-4">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-sign-out-alt text-red-500"></i>
                    <span class="text-sm text-red-500">로그아웃</span>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-400 text-sm"></i>
            </button>
        </div>
    </section>

</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<script>
function goBack() {
    window.history.back();
}

function confirmLogout() {
    if (confirm('로그아웃 하시겠습니까?')) {
        location.href = '<?php echo G5_BBS_URL; ?>/logout.php';
    }
}
</script>

</body>
</html>
