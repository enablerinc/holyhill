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

// 글 테이블
$write_table = $g5['write_prefix'] . $bo_table;

// 목표 인원수 (관리자 설정, 기본값 40)
$goal_count = isset($board['bo_1']) && (int)$board['bo_1'] > 0 ? (int)$board['bo_1'] : 40;

// 선택된 날짜 (기본: 오늘)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
// 미래 날짜 방지
if ($selected_date > date('Y-m-d')) {
    $selected_date = date('Y-m-d');
}
// 유효한 날짜 형식 체크
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// 오늘의 참여자 수 (고유 mb_id 기준)
$today_participant_sql = "SELECT COUNT(DISTINCT mb_id) as cnt FROM {$write_table} WHERE wr_is_comment = 0 AND DATE(wr_datetime) = CURDATE() AND mb_id != ''";
$today_participants = (int)sql_fetch($today_participant_sql)['cnt'];

// 연속 기록 계산 (매일 1명 이상 작성된 연속 일수)
$streak_days = 0;
$check_date = date('Y-m-d');
while (true) {
    $streak_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_is_comment = 0 AND DATE(wr_datetime) = '{$check_date}'";
    $streak_count = (int)sql_fetch($streak_sql)['cnt'];
    if ($streak_count > 0) {
        $streak_days++;
        $check_date = date('Y-m-d', strtotime($check_date . ' -1 day'));
    } else {
        break;
    }
    // 최대 365일까지만 체크
    if ($streak_days >= 365) break;
}

// 단계별 성장 상태 결정
function get_growth_stage($participants, $goal) {
    $ratio = $participants / $goal;
    if ($ratio >= 1) {
        return ['icon' => '🌺', 'name' => '만개', 'message' => '감사가 만개했어요!', 'stage' => 5];
    } elseif ($ratio >= 0.75) {
        return ['icon' => '🌸', 'name' => '꽃', 'message' => '곧 만개해요!', 'stage' => 4];
    } elseif ($ratio >= 0.5) {
        return ['icon' => '🌷', 'name' => '꽃봉오리', 'message' => '꽃봉오리가 맺혔어요', 'stage' => 3];
    } elseif ($ratio >= 0.25) {
        return ['icon' => '🌿', 'name' => '새싹', 'message' => '새싹이 자라요', 'stage' => 2];
    } else {
        return ['icon' => '🌱', 'name' => '씨앗', 'message' => '씨앗이 심어졌어요', 'stage' => 1];
    }
}
$growth = get_growth_stage($today_participants, $goal_count);

// 선택된 날짜의 이전/다음 날 (일기가 있는 날짜)
$prev_date_sql = "SELECT DATE(wr_datetime) as d FROM {$write_table} WHERE wr_is_comment = 0 AND DATE(wr_datetime) < '{$selected_date}' ORDER BY wr_datetime DESC LIMIT 1";
$prev_date_row = sql_fetch($prev_date_sql);
$prev_date = $prev_date_row['d'] ?? null;

$next_date_sql = "SELECT DATE(wr_datetime) as d FROM {$write_table} WHERE wr_is_comment = 0 AND DATE(wr_datetime) > '{$selected_date}' AND DATE(wr_datetime) <= CURDATE() ORDER BY wr_datetime ASC LIMIT 1";
$next_date_row = sql_fetch($next_date_sql);
$next_date = $next_date_row['d'] ?? null;

// 페이지당 게시물 수
$page_rows = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $page_rows;

// 전체 게시글 수 (전체 기준)
$total_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_is_comment = 0";
$total_count_result = sql_fetch($total_count_sql);
$total_count = $total_count_result['cnt'] ? (int)$total_count_result['cnt'] : 0;

// 선택된 날짜의 게시글 수
$selected_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_is_comment = 0 AND DATE(wr_datetime) = '{$selected_date}'";
$selected_count = (int)sql_fetch($selected_count_sql)['cnt'];
$total_pages = max(1, ceil($selected_count / $page_rows));

// 선택된 날짜의 게시글 가져오기 (최신순)
$sql = "SELECT * FROM {$write_table} WHERE wr_is_comment = 0 AND DATE(wr_datetime) = '{$selected_date}' ORDER BY wr_datetime DESC LIMIT {$offset}, {$page_rows}";
$result = sql_query($sql);

$list = array();
while ($row = sql_fetch_array($result)) {
    $list[] = $row;
}

// 선택된 날짜의 참여자 수
$selected_participant_sql = "SELECT COUNT(DISTINCT mb_id) as cnt FROM {$write_table} WHERE wr_is_comment = 0 AND DATE(wr_datetime) = '{$selected_date}' AND mb_id != ''";
$selected_participants = (int)sql_fetch($selected_participant_sql)['cnt'];

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
        /* 성장 아이콘 애니메이션 */
        .growth-icon {
            font-size: 3rem;
            animation: gentle-bounce 2s ease-in-out infinite;
        }
        @keyframes gentle-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        /* 만개 축하 애니메이션 */
        .bloom-celebration {
            position: relative;
            overflow: visible;
        }
        .floating-heart {
            position: absolute;
            font-size: 1.2rem;
            animation: float-up 3s ease-out infinite;
            opacity: 0;
        }
        @keyframes float-up {
            0% { opacity: 0; transform: translateY(0) scale(0.5); }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; transform: translateY(-60px) scale(1); }
        }
        .floating-heart:nth-child(1) { left: 10%; animation-delay: 0s; }
        .floating-heart:nth-child(2) { left: 25%; animation-delay: 0.5s; }
        .floating-heart:nth-child(3) { left: 40%; animation-delay: 1s; }
        .floating-heart:nth-child(4) { left: 55%; animation-delay: 0.3s; }
        .floating-heart:nth-child(5) { left: 70%; animation-delay: 0.8s; }
        .floating-heart:nth-child(6) { left: 85%; animation-delay: 1.2s; }
        /* 만개 시 특별 효과 */
        .bloom-glow {
            animation: glow-pulse 2s ease-in-out infinite;
        }
        @keyframes glow-pulse {
            0%, 100% { filter: drop-shadow(0 0 5px rgba(177, 156, 217, 0.5)); }
            50% { filter: drop-shadow(0 0 15px rgba(177, 156, 217, 0.8)); }
        }
        /* 성장 단계 인디케이터 */
        .stage-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        .stage-dot.active {
            transform: scale(1.3);
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
            <img src="<?php echo G5_IMG_URL; ?>/logo.png" alt="성산교회 로고" class="w-9 h-9 rounded-lg object-cover">
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

    <!-- 연속 기록 + 성장 카드 -->
    <div class="px-4 py-3">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-soft-lavender/50 <?php echo $growth['stage'] >= 5 ? 'bloom-celebration' : ''; ?>">
            <?php if ($growth['stage'] >= 5) { ?>
            <!-- 만개 시 떠오르는 하트 -->
            <span class="floating-heart">💜</span>
            <span class="floating-heart">🌸</span>
            <span class="floating-heart">💐</span>
            <span class="floating-heart">✨</span>
            <span class="floating-heart">🌺</span>
            <span class="floating-heart">💜</span>
            <?php } ?>

            <!-- 연속 기록 -->
            <?php if ($streak_days > 0) { ?>
            <div class="text-center mb-4">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 bg-gradient-to-r from-orange-100 to-red-100 rounded-full">
                    <span class="text-lg">🔥</span>
                    <span class="text-sm font-bold text-orange-600">우리 교회 연속 감사 <?php echo number_format($streak_days); ?>일째!</span>
                </span>
            </div>
            <?php } ?>

            <!-- 성장 아이콘 -->
            <div class="text-center mb-3">
                <span class="growth-icon <?php echo $growth['stage'] >= 5 ? 'bloom-glow' : ''; ?>"><?php echo $growth['icon']; ?></span>
            </div>

            <!-- 참여 현황 -->
            <div class="text-center mb-3">
                <p class="text-lg font-bold text-grace-green">
                    오늘 <span class="text-deep-purple"><?php echo number_format($today_participants); ?></span>명 참여
                </p>
                <p class="text-sm text-grace-green/70 mt-1"><?php echo $growth['message']; ?></p>
                <?php if ($growth['stage'] < 5) {
                    $remaining = $goal_count - $today_participants;
                ?>
                <p class="text-xs text-lilac mt-1">🌺 만개까지 <?php echo number_format($remaining); ?>명!</p>
                <?php } ?>
            </div>

            <!-- 단계 인디케이터 -->
            <div class="flex items-center justify-center gap-2 mb-4">
                <span class="stage-dot <?php echo $growth['stage'] >= 1 ? 'bg-green-300 active' : 'bg-gray-200'; ?>" title="씨앗"></span>
                <span class="stage-dot <?php echo $growth['stage'] >= 2 ? 'bg-green-400 active' : 'bg-gray-200'; ?>" title="새싹"></span>
                <span class="stage-dot <?php echo $growth['stage'] >= 3 ? 'bg-pink-300 active' : 'bg-gray-200'; ?>" title="꽃봉오리"></span>
                <span class="stage-dot <?php echo $growth['stage'] >= 4 ? 'bg-pink-400 active' : 'bg-gray-200'; ?>" title="꽃"></span>
                <span class="stage-dot <?php echo $growth['stage'] >= 5 ? 'bg-purple-400 active' : 'bg-gray-200'; ?>" title="만개"></span>
            </div>

            <!-- 간단 통계 -->
            <div class="flex items-center justify-center gap-6 text-center pt-3 border-t border-soft-lavender/50">
                <div>
                    <p class="text-lg font-bold text-deep-purple"><?php echo number_format($total_count); ?></p>
                    <p class="text-xs text-grace-green/60">전체 감사</p>
                </div>
                <div class="w-px h-8 bg-soft-lavender"></div>
                <div>
                    <?php
                    $total_writers_sql = "SELECT COUNT(DISTINCT mb_id) as cnt FROM {$write_table} WHERE wr_is_comment = 0 AND mb_id != ''";
                    $total_writers = sql_fetch($total_writers_sql)['cnt'];
                    ?>
                    <p class="text-lg font-bold text-grace-green"><?php echo number_format($total_writers); ?></p>
                    <p class="text-xs text-grace-green/60">총 참여자</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 날짜 네비게이션 -->
    <div class="px-4 py-2">
        <div class="bg-white rounded-xl p-3 shadow-sm border border-soft-lavender/50">
            <div class="flex items-center justify-between">
                <!-- 이전 날짜 -->
                <?php if ($prev_date) { ?>
                <a href="?date=<?php echo $prev_date; ?>" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-soft-lavender/50 transition-colors">
                    <i class="fa-solid fa-chevron-left text-grace-green"></i>
                </a>
                <?php } else { ?>
                <div class="w-10 h-10 flex items-center justify-center">
                    <i class="fa-solid fa-chevron-left text-gray-300"></i>
                </div>
                <?php } ?>

                <!-- 현재 날짜 -->
                <div class="text-center">
                    <?php
                    $selected_date_obj = new DateTime($selected_date);
                    $day_names = array('일', '월', '화', '수', '목', '금', '토');
                    $dow = $day_names[$selected_date_obj->format('w')];
                    $is_today = ($selected_date === date('Y-m-d'));
                    ?>
                    <p class="text-base font-bold text-grace-green">
                        <?php echo $selected_date_obj->format('Y년 n월 j일'); ?> (<?php echo $dow; ?>)
                    </p>
                    <?php if ($is_today) { ?>
                    <span class="text-xs text-lilac font-medium">오늘</span>
                    <?php } else { ?>
                    <a href="?" class="text-xs text-deep-purple hover:underline">오늘로 이동</a>
                    <?php } ?>
                </div>

                <!-- 다음 날짜 -->
                <?php if ($next_date && $next_date <= date('Y-m-d')) { ?>
                <a href="?date=<?php echo $next_date; ?>" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-soft-lavender/50 transition-colors">
                    <i class="fa-solid fa-chevron-right text-grace-green"></i>
                </a>
                <?php } else { ?>
                <div class="w-10 h-10 flex items-center justify-center">
                    <i class="fa-solid fa-chevron-right text-gray-300"></i>
                </div>
                <?php } ?>
            </div>

            <!-- 선택된 날짜 참여 현황 -->
            <?php if (!$is_today && $selected_count > 0) { ?>
            <div class="mt-2 pt-2 border-t border-soft-lavender/30 text-center">
                <span class="text-sm text-grace-green/70"><?php echo number_format($selected_participants); ?>명이 <?php echo number_format($selected_count); ?>개의 감사를 기록했어요</span>
            </div>
            <?php } ?>
        </div>
    </div>

    <!-- 게시물 목록 -->
    <div class="px-4" id="diary-list">
        <?php if (count($list) > 0) { ?>
        <div class="space-y-2 py-2">
            <?php
            foreach ($list as $item) {
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
                                <i class="fa-solid fa-heart text-red-500"></i>
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
        <?php } else { ?>
        <!-- 게시글 없음 -->
        <div class="text-center py-16">
            <div class="w-16 h-16 bg-soft-lavender rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-regular fa-face-meh text-2xl text-lilac"></i>
            </div>
            <?php if ($selected_date === date('Y-m-d')) { ?>
            <p class="text-grace-green font-medium mb-2">오늘 아직 감사일기가 없어요</p>
            <p class="text-grace-green/60 text-sm mb-6">첫 번째 감사를 기록해 보세요!</p>
            <?php if ($write_href) { ?>
            <a href="<?php echo $write_href; ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-lilac to-deep-purple text-white rounded-full font-medium shadow-lg hover:shadow-xl transition-shadow">
                <i class="fa-solid fa-plus"></i>
                감사 기록하기
            </a>
            <?php } ?>
            <?php } else { ?>
            <p class="text-grace-green font-medium mb-2">이 날에는 감사일기가 없어요</p>
            <a href="?" class="text-sm text-deep-purple hover:underline">오늘로 이동</a>
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
const selectedDate = '<?php echo $selected_date; ?>';
let isLoading = false;

function loadMore() {
    if (isLoading || currentPage >= totalPages) return;

    isLoading = true;
    currentPage++;

    const btn = document.getElementById('load-more-btn');
    const loading = document.getElementById('loading');

    if (btn) btn.classList.add('hidden');
    loading.classList.remove('hidden');

    fetch('<?php echo G5_BBS_URL; ?>/gratitude_ajax.php?page=' + currentPage + '&date=' + selectedDate)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.html) {
                const diaryList = document.getElementById('diary-list');
                const container = diaryList.querySelector('.space-y-2');
                if (container) {
                    container.insertAdjacentHTML('beforeend', data.html);
                }

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
