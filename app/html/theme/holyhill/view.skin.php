<?php
if (!defined('_GNUBOARD_')) exit;

// 작성자 정보
$mb_nick = $view['wr_name'] ? $view['wr_name'] : '알 수 없음';
$mb_photo_html = '';

if ($view['mb_id']) {
    $mb_result = sql_query("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$view['mb_id']}'");
    if ($mb_result && $row = sql_fetch_array($mb_result)) {
        $mb_nick = $row['mb_nick'];
    }
    // 표준 회원 이미지 함수 사용
    $mb_photo_html = get_member_profile_img($view['mb_id']);
}

// 이미지 태그에서 src 속성 추출
$mb_photo = G5_THEME_URL.'/img/no-profile.svg';
if ($mb_photo_html && preg_match('/src="([^"]+)"/', $mb_photo_html, $matches)) {
    $mb_photo = $matches[1];
}

// 이미지
$images = array();
$file_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND bf_type BETWEEN 1 AND 3 ORDER BY bf_no");
while ($file = sql_fetch_array($file_result)) {
    $images[] = $file['bf_file'];
}

// 좋아요
$is_good = false;
if ($is_member) {
    $good_result = sql_query("SELECT COUNT(*) as cnt FROM {$g5['board_good_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND mb_id = '{$member['mb_id']}'");
    $good_row = sql_fetch_array($good_result);
    $is_good = $good_row['cnt'] > 0;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">

<div class="max-w-2xl mx-auto bg-white min-h-screen">
    
    <!-- 헤더 -->
    <header class="fixed top-0 left-0 right-0 bg-white border-b z-50" style="max-width: 640px; margin: 0 auto;">
        <div class="flex items-center justify-between px-4 py-3">
            <button onclick="history.back()"><i class="fa-solid fa-arrow-left text-xl"></i></button>
            <h1 class="font-semibold">피드</h1>
            <div class="w-6"></div>
        </div>
    </header>

    <!-- 본문 (padding-bottom 증가!) -->
    <main style="padding-top: 64px; padding-bottom: 180px;">
        <article>
            <!-- 작성자 -->
            <div class="p-4 flex items-center gap-3 border-b">
                <img src="<?php echo $mb_photo; ?>" class="w-10 h-10 rounded-full" alt="">
                <div>
                    <div class="font-semibold"><?php echo $mb_nick; ?></div>
                    <div class="text-xs text-gray-500"><?php echo date('Y-m-d', strtotime($view['wr_datetime'])); ?></div>
                </div>
            </div>

            <!-- 이미지 -->
            <?php if (count($images) > 0) { ?>
            <div>
                <img src="<?php echo G5_DATA_URL.'/file/'.$bo_table.'/'.$images[0]; ?>" class="w-full">
            </div>
            <?php } ?>

            <!-- 액션 -->
            <div class="p-4 border-b">
                <div class="flex gap-4 mb-2">
                    <button onclick="toggleGood()">
                        <i class="<?php echo $is_good ? 'fa-solid' : 'fa-regular'; ?> fa-heart text-red-500 text-2xl" id="heartIcon"></i>
                    </button>
                    <button onclick="scrollToComment()">
                        <i class="fa-regular fa-comment text-2xl"></i>
                    </button>
                </div>
                <div id="goodCount" class="font-semibold text-sm">아멘 <?php echo $view['wr_good']; ?>개</div>
            </div>

            <!-- 내용 -->
            <div class="p-4 border-b">
                <div>
                    <span class="font-semibold mr-2"><?php echo $mb_nick; ?></span>
                    <?php
                    // 유튜브 링크 임베드 처리
                    $content = $view['wr_content'];
                    $has_youtube = preg_match('/https?:\/\/(?:www\.)?(youtube\.com|youtu\.be)/i', $content);

                    if ($has_youtube) {
                        // 유튜브가 있으면 임베드로 변환하여 표시
                        echo convert_youtube_to_embed($content);
                    } else {
                        // 일반 텍스트는 get_text()로 처리
                        echo get_text($content);
                    }
                    ?>
                </div>
            </div>

            <!-- 댓글 -->
            <div class="p-4">
                <h3 class="font-semibold mb-4">댓글 <?php echo $view['wr_comment']; ?>개</h3>
                <?php
                $comment_result = sql_query("SELECT * FROM {$g5['write_prefix']}{$bo_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1 ORDER BY wr_num LIMIT 50");
                
                if (sql_num_rows($comment_result) > 0) {
                    while ($c = sql_fetch_array($comment_result)) {
                        $c_nick = $c['wr_name'] ? $c['wr_name'] : '알 수 없음';
                        $c_photo = G5_THEME_URL.'/img/no-profile.svg';

                        if ($c['mb_id']) {
                            $c_mb = sql_fetch("SELECT mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$c['mb_id']}'");
                            if ($c_mb) {
                                $c_nick = $c_mb['mb_nick'];
                            }
                            // 표준 회원 이미지 함수 사용
                            $c_photo_html = get_member_profile_img($c['mb_id']);
                            if ($c_photo_html && preg_match('/src="([^"]+)"/', $c_photo_html, $matches)) {
                                $c_photo = $matches[1];
                            }
                        }
                        ?>
                        <div class="flex gap-3 mb-3">
                            <img src="<?php echo $c_photo; ?>" class="w-8 h-8 rounded-full">
                            <div class="flex-1 bg-gray-50 rounded-2xl px-3 py-2">
                                <div class="font-semibold text-xs mb-1"><?php echo $c_nick; ?></div>
                                <div class="text-sm"><?php echo get_text($c['wr_content']); ?></div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="text-center text-gray-500 py-4">첫 댓글을 남겨보세요!</div>';
                }
                ?>
            </div>
        </article>
    </main>

    <!-- 댓글 입력 (z-index 높이고 확실하게 표시) -->
    <div style="position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e5e7eb; z-index: 9999; max-width: 640px; margin: 0 auto; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);">
        <form method="post" action="<?php echo G5_BBS_URL; ?>/write_comment_update.php" style="padding: 16px;">
            <input type="hidden" name="w" value="c">
            <input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
            <input type="hidden" name="wr_id" value="<?php echo $wr_id; ?>">
            <input type="hidden" name="comment_id" value="">
            
            <div style="display: flex; gap: 12px; align-items: center;">
                <?php
                $user_photo = G5_THEME_URL.'/img/no-profile.svg';
                if ($is_member && $member['mb_id']) {
                    $user_photo_html = get_member_profile_img($member['mb_id']);
                    if ($user_photo_html && preg_match('/src="([^"]+)"/', $user_photo_html, $matches)) {
                        $user_photo = $matches[1];
                    }
                }
                ?>
                <img src="<?php echo $user_photo; ?>" style="width: 32px; height: 32px; border-radius: 50%;">
                <div style="flex: 1; display: flex; gap: 8px; background: #f3f4f6; border-radius: 9999px; padding: 8px 16px; align-items: center;">
                    <input 
                        type="text" 
                        name="wr_content" 
                        id="commentInput" 
                        placeholder="댓글 입력..." 
                        style="flex: 1; background: transparent; border: none; outline: none; font-size: 14px;"
                        required>
                    <button type="submit" style="background: none; border: none; cursor: pointer;">
                        <i class="fa-solid fa-paper-plane" style="color: #9333ea; font-size: 18px;"></i>
                    </button>
                </div>
            </div>
            
            <?php if (!$is_member) { ?>
            <div style="text-align: center; margin-top: 8px; font-size: 12px; color: #ef4444;">
                로그인이 필요합니다
            </div>
            <?php } ?>
        </form>
    </div>

</div>

<script>
function scrollToComment() {
    document.getElementById('commentInput').focus();
    window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'});
}

function toggleGood() {
    <?php if (!$is_member) { ?>
    alert('로그인이 필요합니다.');
    location.href = '<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>';
    return;
    <?php } ?>
    
    fetch('<?php echo G5_BBS_URL; ?>/ajax.good.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.result) {
            document.getElementById('heartIcon').classList.toggle('fa-regular');
            document.getElementById('heartIcon').classList.toggle('fa-solid');
            document.getElementById('goodCount').textContent = '아멘 ' + data.count + '개';
        }
    });
}
</script>

</body>
</html>
<?php
if (!$is_admin && $view['mb_id'] != $member['mb_id']) {
    sql_query("UPDATE {$g5['write_prefix']}{$bo_table} SET wr_hit = wr_hit + 1 WHERE wr_id = '{$wr_id}'");
}
?>
