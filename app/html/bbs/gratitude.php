<?php
include_once('./_common.php');

$g5['title'] = '감사일기';

// 게시판 설정 (diary 게시판)
$bo_table = 'diary';
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    alert('감사일기 게시판이 아직 생성되지 않았습니다.', G5_BBS_URL.'/index.php');
}

// 권한 체크
if ($member['mb_level'] < $board['bo_list_level']) {
    if ($member['mb_id'])
        alert('목록을 볼 권한이 없습니다.', G5_URL);
    else
        alert('로그인 후 이용해 주세요.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/gratitude.php'));
}

// 글쓰기 권한 체크
$write_href = '';
if ($member['mb_level'] >= $board['bo_write_level']) {
    $write_href = G5_BBS_URL.'/gratitude_write.php';
}

// 페이지당 게시물 수
$page_rows = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $page_rows;

// 글 테이블
$write_table = $g5['write_prefix'] . $bo_table;

// 전체 게시글 수
$total_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_is_comment = 0";
$total_count_result = sql_fetch($total_count_sql);
$total_count = $total_count_result['cnt'] ? (int)$total_count_result['cnt'] : 0;
$total_pages = max(1, ceil($total_count / $page_rows));

// 게시글 가져오기 (최신순)
$sql = "SELECT * FROM {$write_table} WHERE wr_is_comment = 0 ORDER BY wr_datetime DESC LIMIT {$offset}, {$page_rows}";
$result = sql_query($sql);

$list = array();
while ($row = sql_fetch_array($result)) {
    $list[] = $row;
}

// 날짜별로 그룹핑
$grouped_list = array();
foreach ($list as $item) {
    $date_key = date('Y-m-d', strtotime($item['wr_datetime']));
    if (!isset($grouped_list[$date_key])) {
        $grouped_list[$date_key] = array();
    }
    $grouped_list[$date_key][] = $item;
}

// 시간 표시 함수
function get_time_ago_gratitude($datetime) {
    $time_diff = time() - strtotime($datetime);

    if ($time_diff < 60) {
        return '방금 전';
    } elseif ($time_diff < 3600) {
        return floor($time_diff / 60) . '분 전';
    } elseif ($time_diff < 86400) {
        return floor($time_diff / 3600) . '시간 전';
    } elseif ($time_diff < 172800) {
        return '어제';
    } elseif ($time_diff < 604800) {
        return floor($time_diff / 86400) . '일 전';
    } else {
        return date('m.d', strtotime($datetime));
    }
}

// 날짜 표시 함수
function get_date_label($date_str) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($date_str === $today) {
        return '오늘 (' . date('m/d', strtotime($date_str)) . ')';
    } elseif ($date_str === $yesterday) {
        return '어제 (' . date('m/d', strtotime($date_str)) . ')';
    } else {
        $date_obj = new DateTime($date_str);
        $day_of_week = array('일', '월', '화', '수', '목', '금', '토');
        $dow = $day_of_week[$date_obj->format('w')];
        return date('m월 d일', strtotime($date_str)) . ' (' . $dow . ')';
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g5['title']; ?></title>
    <link rel="icon" type="image/png" href="<?php echo G5_IMG_URL; ?>/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>window.FontAwesomeConfig = { autoReplaceSvg: 'nest'};</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { display: none; }
        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #EEF3F8;
        }
        .diary-item {
            transition: all 0.2s ease;
        }
        .diary-item:active {
            background-color: #E8E2F7;
            transform: scale(0.99);
        }
        .date-divider {
            position: relative;
        }
        .date-divider::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1px;
            background: linear-gradient(to right, transparent, #E8E2F7, transparent);
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

<!-- 헤더 -->
<header class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="max-w-2xl mx-auto flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-gradient-to-br from-lilac to-deep-purple rounded-xl flex items-center justify-center shadow-md">
                <i class="fa-solid fa-book text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-grace-green">감사일기</h1>
                <p class="text-xs text-grace-green/60">매일 감사를 기록해요</p>
            </div>
        </div>
        <?php if ($write_href) { ?>
        <a href="<?php echo $write_href; ?>" class="w-10 h-10 bg-gradient-to-br from-lilac to-deep-purple rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-shadow">
            <i class="fa-solid fa-plus text-white"></i>
        </a>
        <?php } ?>
    </div>
</header>

<main class="pt-20 pb-24 max-w-2xl mx-auto">

    <?php if ($total_count > 0) { ?>
    <!-- 통계 카드 -->
    <div class="px-4 py-3">
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-soft-lavender/50">
            <div class="flex items-center justify-around text-center">
                <div>
                    <p class="text-2xl font-bold text-deep-purple"><?php echo number_format($total_count); ?></p>
                    <p class="text-xs text-grace-green/70">전체 감사</p>
                </div>
                <div class="w-px h-10 bg-soft-lavender"></div>
                <div>
                    <?php
                    $today_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_is_comment = 0 AND DATE(wr_datetime) = CURDATE()";
                    $today_count = sql_fetch($today_count_sql)['cnt'];
                    ?>
                    <p class="text-2xl font-bold text-lilac"><?php echo number_format($today_count); ?></p>
                    <p class="text-xs text-grace-green/70">오늘의 감사</p>
                </div>
                <div class="w-px h-10 bg-soft-lavender"></div>
                <div>
                    <?php
                    $writer_count_sql = "SELECT COUNT(DISTINCT mb_id) as cnt FROM {$write_table} WHERE wr_is_comment = 0 AND mb_id != ''";
                    $writer_count = sql_fetch($writer_count_sql)['cnt'];
                    ?>
                    <p class="text-2xl font-bold text-grace-green"><?php echo number_format($writer_count); ?></p>
                    <p class="text-xs text-grace-green/70">참여자</p>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- 게시물 목록 -->
    <div class="px-4" id="diary-list">
        <?php
        if (count($grouped_list) > 0) {
            foreach ($grouped_list as $date_key => $items) {
        ?>
        <!-- 날짜 구분 -->
        <div class="date-divider flex justify-center py-4">
            <span class="bg-warm-beige px-4 py-1 text-sm font-medium text-grace-green/80 relative z-10">
                <?php echo get_date_label($date_key); ?>
            </span>
        </div>

        <!-- 해당 날짜의 일기들 -->
        <div class="space-y-2 mb-2">
            <?php
            foreach ($items as $item) {
                $wr_id = $item['wr_id'];
                $writer_id = $item['mb_id'];
                $writer_nick = $item['wr_name'];

                // 작성자 정보
                $writer_photo = '';
                if ($writer_id) {
                    $member_info = sql_fetch("SELECT mb_nick, mb_name FROM {$g5['member_table']} WHERE mb_id = '{$writer_id}'");
                    if ($member_info) {
                        $writer_nick = $member_info['mb_name'] ? $member_info['mb_name'] : ($member_info['mb_nick'] ? $member_info['mb_nick'] : $item['wr_name']);
                    }
                    $profile_path = G5_DATA_PATH.'/member_image/'.substr($writer_id, 0, 2).'/'.$writer_id.'.gif';
                    if (file_exists($profile_path)) {
                        $writer_photo = G5_DATA_URL.'/member_image/'.substr($writer_id, 0, 2).'/'.$writer_id.'.gif';
                    }
                }

                // 내용 미리보기
                $content_preview = strip_tags($item['wr_content']);
                $content_preview = preg_replace('/\s+/', ' ', $content_preview);
                $content_preview = mb_substr(trim($content_preview), 0, 50, 'UTF-8');
                if (mb_strlen(trim(strip_tags($item['wr_content'])), 'UTF-8') > 50) {
                    $content_preview .= '...';
                }

                // 댓글 수
                $comment_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1";
                $comment_count = sql_fetch($comment_count_sql)['cnt'];

                // 좋아요 수
                $good_count = isset($item['wr_good']) ? $item['wr_good'] : 0;

                // 작성자 페이지 URL
                $user_href = G5_BBS_URL.'/gratitude_user.php?mb_id='.urlencode($writer_id).'&wr_id='.$wr_id;
            ?>
            <a href="<?php echo $user_href; ?>" class="diary-item block bg-white rounded-2xl p-4 shadow-sm border border-soft-lavender/30 hover:shadow-md">
                <div class="flex items-start gap-3">
                    <!-- 프로필 -->
                    <div class="flex-shrink-0">
                        <?php if ($writer_photo) { ?>
                        <img src="<?php echo $writer_photo; ?>" alt="<?php echo $writer_nick; ?>" class="w-11 h-11 rounded-full object-cover border-2 border-soft-lavender">
                        <?php } else { ?>
                        <div class="w-11 h-11 rounded-full bg-gradient-to-br from-lilac to-deep-purple flex items-center justify-center border-2 border-soft-lavender">
                            <span class="text-white font-bold text-sm"><?php echo mb_substr($writer_nick, 0, 1, 'UTF-8'); ?></span>
                        </div>
                        <?php } ?>
                    </div>

                    <!-- 내용 -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-semibold text-grace-green text-sm"><?php echo $writer_nick; ?></span>
                            <span class="text-xs text-grace-green/50"><?php echo get_time_ago_gratitude($item['wr_datetime']); ?></span>
                        </div>
                        <p class="text-grace-green/80 text-sm leading-relaxed line-clamp-2"><?php echo $content_preview; ?></p>

                        <!-- 좋아요/댓글 -->
                        <div class="flex items-center gap-4 mt-2">
                            <span class="flex items-center gap-1 text-xs text-grace-green/50">
                                <i class="fa-solid fa-heart text-lilac"></i>
                                <?php echo number_format($good_count); ?>
                            </span>
                            <span class="flex items-center gap-1 text-xs text-grace-green/50">
                                <i class="fa-regular fa-comment"></i>
                                <?php echo number_format($comment_count); ?>
                            </span>
                        </div>
                    </div>

                    <!-- 화살표 -->
                    <div class="flex-shrink-0 self-center">
                        <i class="fa-solid fa-chevron-right text-grace-green/30 text-sm"></i>
                    </div>
                </div>
            </a>
            <?php } ?>
        </div>
        <?php
            }
        } else {
        ?>
        <!-- 게시글 없음 -->
        <div class="text-center py-20">
            <div class="w-20 h-20 bg-soft-lavender rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-book text-3xl text-lilac"></i>
            </div>
            <p class="text-grace-green font-medium mb-2">아직 감사일기가 없어요</p>
            <p class="text-grace-green/60 text-sm mb-6">첫 번째 감사를 기록해 보세요!</p>
            <?php if ($write_href) { ?>
            <a href="<?php echo $write_href; ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-lilac to-deep-purple text-white rounded-full font-medium shadow-lg hover:shadow-xl transition-shadow">
                <i class="fa-solid fa-plus"></i>
                감사 기록하기
            </a>
            <?php } ?>
        </div>
        <?php } ?>
    </div>

    <!-- 더 보기 버튼 -->
    <?php if ($total_pages > 1 && $page < $total_pages) { ?>
    <div class="px-4 py-6 text-center">
        <button onclick="loadMore()" id="load-more-btn" class="px-8 py-3 bg-white border border-soft-lavender text-grace-green rounded-full font-medium hover:bg-soft-lavender/30 transition-colors">
            더 보기
        </button>
    </div>
    <?php } ?>

    <!-- 로딩 인디케이터 -->
    <div id="loading" class="hidden text-center py-8">
        <i class="fa-solid fa-spinner fa-spin text-2xl text-lilac"></i>
    </div>

</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<script>
let currentPage = <?php echo $page; ?>;
const totalPages = <?php echo $total_pages; ?>;
let isLoading = false;

function loadMore() {
    if (isLoading || currentPage >= totalPages) return;

    isLoading = true;
    currentPage++;

    const btn = document.getElementById('load-more-btn');
    const loading = document.getElementById('loading');

    if (btn) btn.classList.add('hidden');
    loading.classList.remove('hidden');

    fetch('<?php echo G5_BBS_URL; ?>/gratitude_ajax.php?page=' + currentPage)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.html) {
                document.getElementById('diary-list').insertAdjacentHTML('beforeend', data.html);

                if (currentPage < totalPages) {
                    if (btn) btn.classList.remove('hidden');
                }
            }
            loading.classList.add('hidden');
            isLoading = false;
        })
        .catch(error => {
            console.error('Error:', error);
            loading.classList.add('hidden');
            if (btn) btn.classList.remove('hidden');
            isLoading = false;
        });
}

// 무한 스크롤
window.addEventListener('scroll', function() {
    if (isLoading || currentPage >= totalPages) return;

    const scrollHeight = document.documentElement.scrollHeight;
    const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
    const clientHeight = document.documentElement.clientHeight;

    if (scrollTop + clientHeight >= scrollHeight - 500) {
        loadMore();
    }
});
</script>

</body>
</html>
