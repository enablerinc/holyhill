<?php
include_once('./_common.php');

header('Content-Type: application/json');

$bo_table = 'diary';
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    echo json_encode(['success' => false, 'message' => '게시판이 없습니다.']);
    exit;
}

// 권한 체크
if ($member['mb_level'] < $board['bo_list_level']) {
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

$page_rows = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $page_rows;

$write_table = $g5['write_prefix'] . $bo_table;

// 게시글 가져오기
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

// HTML 생성
ob_start();

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

$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html,
    'page' => $page
]);
