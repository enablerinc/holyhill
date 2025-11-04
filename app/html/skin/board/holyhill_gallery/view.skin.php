<?php
if (!defined('_GNUBOARD_')) exit;

// 작성자 정보
$mb_nick = $view['wr_name'] ? $view['wr_name'] : '알 수 없음';
$mb_photo = G5_THEME_URL.'/img/no-profile.svg';

if ($view['mb_id']) {
    $mb_result = sql_query("SELECT mb_nick, mb_photo FROM {$g5['member_table']} WHERE mb_id = '{$view['mb_id']}'");
    if ($mb_result && $row = sql_fetch_array($mb_result)) {
        $mb_nick = $row['mb_nick'];
        if ($row['mb_photo']) {
            $mb_photo = G5_DATA_URL.'/member/'.$row['mb_photo'];
        }
    }
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

// 댓글 토큰 생성
$comment_token = '';
if ($is_member) {
    $comment_token = get_random_token_string();
    set_session('ss_comment_token', $comment_token);
}

// 본문에 사용된 이미지 인덱스 추출
$used_image_indices = array();
preg_match_all('/\[이미지(\d+)\]/', $view['wr_content'], $matches);
if (!empty($matches[1])) {
    foreach ($matches[1] as $num) {
        $used_image_indices[] = intval($num) - 1; // 0-based index
    }
}

// 본문에서 [이미지N]을 실제 이미지로 변환
function replace_image_placeholders($content, $images, $bo_table) {
    // [이미지1], [이미지2] 등을 찾아서 실제 이미지 태그로 변환
    $content = preg_replace_callback('/\[이미지(\d+)\]/', function($matches) use ($images, $bo_table) {
        $index = intval($matches[1]) - 1; // 1부터 시작하므로 -1
        if (isset($images[$index])) {
            $image_url = G5_DATA_URL.'/file/'.$bo_table.'/'.$images[$index];
            return '<div class="my-4"><img src="'.$image_url.'" class="w-full rounded-lg" alt="이미지'.($index+1).'"></div>';
        }
        return $matches[0]; // 이미지가 없으면 원본 텍스트 유지
    }, $content);

    return $content;
}

// 본문 내용 처리
$processed_content = replace_image_placeholders(get_text($view['wr_content']), $images, $bo_table);

// 상단 갤러리용 이미지 (본문에 사용되지 않은 이미지만)
$gallery_images = array();
foreach ($images as $idx => $image) {
    if (!in_array($idx, $used_image_indices)) {
        $gallery_images[] = $image;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* 가로 스크롤 스타일 */
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
</head>
<body class="bg-gray-50" style="margin: 0; padding: 0;">

<div class="max-w-2xl mx-auto bg-white min-h-screen">
    
    <!-- 헤더 -->
    <header class="fixed top-0 left-0 right-0 bg-white border-b z-50" style="max-width: 640px; margin: 0 auto;">
        <div class="flex items-center justify-between px-4 py-3">
            <button onclick="history.back()"><i class="fa-solid fa-arrow-left text-xl"></i></button>
            <h1 class="font-semibold">피드</h1>
            <div class="w-6"></div>
        </div>
    </header>

    <!-- 본문 -->
    <main style="padding-top: 64px; padding-bottom: 200px;">
        <article>
            <!-- 작성자 -->
            <div class="p-4 flex items-center gap-3 border-b">
                <img src="<?php echo $mb_photo; ?>" class="w-10 h-10 rounded-full" alt="">
                <div>
                    <div class="font-semibold"><?php echo $mb_nick; ?></div>
                    <div class="text-xs text-gray-500"><?php echo date('Y-m-d', strtotime($view['wr_datetime'])); ?></div>
                </div>
            </div>

            <!-- 이미지 갤러리 (본문에 삽입되지 않은 이미지만 표시) -->
            <?php if (count($gallery_images) > 0) { ?>
            <div class="flex overflow-x-auto gap-2 p-2 scrollbar-hide" style="scroll-snap-type: x mandatory;">
                <?php foreach ($gallery_images as $image) { ?>
                <div class="flex-shrink-0" style="scroll-snap-align: start;">
                    <img src="<?php echo G5_DATA_URL.'/file/'.$bo_table.'/'.$image; ?>"
                         class="h-80 w-auto object-cover rounded-lg"
                         alt="갤러리 이미지">
                </div>
                <?php } ?>
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
                <div><span class="font-semibold mr-2"><?php echo $mb_nick; ?></span><?php echo $processed_content; ?></div>
            </div>

            <!-- 댓글 -->
            <div class="p-4">
                <h3 class="font-semibold mb-4">댓글 <?php echo $view['wr_comment']; ?>개</h3>
                <div id="comment-list">
                <?php
                $comment_result = sql_query("SELECT * FROM {$g5['write_prefix']}{$bo_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1 ORDER BY wr_num LIMIT 50");
                
                if (sql_num_rows($comment_result) > 0) {
                    while ($c = sql_fetch_array($comment_result)) {
                        $c_nick = $c['wr_name'] ? $c['wr_name'] : '알 수 없음';
                        $c_photo = G5_THEME_URL.'/img/no-profile.svg';
                        
                        if ($c['mb_id']) {
                            $c_mb = sql_fetch("SELECT mb_nick, mb_photo FROM {$g5['member_table']} WHERE mb_id = '{$c['mb_id']}'");
                            if ($c_mb) {
                                $c_nick = $c_mb['mb_nick'];
                                if ($c_mb['mb_photo']) $c_photo = G5_DATA_URL.'/member/'.$c_mb['mb_photo'];
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
            </div>
        </article>
    </main>

</div>

<!-- 댓글 입력창 -->
<div id="commentFormWrapper" style="position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 2px solid #e5e7eb; z-index: 99999; box-shadow: 0 -4px 12px rgba(0,0,0,0.1);">
    <div style="max-width: 640px; margin: 0 auto;">
        <?php if ($is_member) { ?>
        <form id="commentForm" method="post" action="<?php echo G5_BBS_URL; ?>/comment_write_ajax.php" style="padding: 16px;" novalidate>
            <input type="hidden" name="w" value="c">
            <input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
            <input type="hidden" name="wr_id" value="<?php echo $wr_id; ?>">
            <input type="hidden" name="comment_id" value="">
            <input type="hidden" name="token" value="<?php echo $comment_token; ?>">
            
            <div style="display: flex; gap: 12px; align-items: center;">
                <?php
                $comment_profile_photo = (isset($member['mb_photo']) && $member['mb_photo']) ? G5_DATA_URL.'/member/'.$member['mb_photo'] : G5_THEME_URL.'/img/no-profile.svg';
                ?>
                <img src="<?php echo $comment_profile_photo; ?>" style="width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;" alt="프로필">
                <div style="flex: 1; display: flex; gap: 8px; background: #f3f4f6; border-radius: 9999px; padding: 8px 16px; align-items: center;">
                    <input 
                        type="text" 
                        name="wr_content" 
                        id="commentInput" 
                        placeholder="댓글 입력..." 
                        style="flex: 1; background: transparent; border: none; outline: none; font-size: 14px;"
                        required>
                    <button type="submit" style="background: none; border: none; cursor: pointer; padding: 4px;">
                        <i class="fa-solid fa-paper-plane" style="color: #9333ea; font-size: 18px;"></i>
                    </button>
                </div>
            </div>
        </form>
        <?php } else { ?>
        <div style="padding: 16px; text-align: center;">
            <a href="<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode(G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id); ?>" style="color: #9333ea; text-decoration: none;">
                로그인하고 댓글 남기기
            </a>
        </div>
        <?php } ?>
    </div>
</div>

<script>
function scrollToComment() {
    const input = document.getElementById('commentInput');
    if (input) {
        input.focus();
        window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'});
    }
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

// AJAX 댓글 제출
(function() {
    const form = document.getElementById('commentForm');
    const input = document.getElementById('commentInput');
    const commentList = document.getElementById('comment-list');

    if (!form || !input) return;

    const tokenInput = form.querySelector('input[name="token"]');

    // wrest.js의 oldsubmit 메서드 제거
    if (form.oldsubmit) {
        delete form.oldsubmit;
    }

    // submit 이벤트 핸들러 (capture phase에서 실행하여 wrest.js보다 우선)
    const handleSubmit = function(e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // 다른 모든 리스너 중단
        e.stopPropagation();

        if (!input.value.trim()) {
            input.focus();
            return false;
        }

        // 토큰 확인
        if (!tokenInput || !tokenInput.value) {
            alert('토큰 오류가 발생했습니다. 페이지를 새로고침해주세요.');
            return false;
        }

        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalContent = submitBtn.innerHTML;

        // 전송 중 표시
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        fetch('<?php echo G5_BBS_URL; ?>/comment_write_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('응답 상태:', response.status);
            if (!response.ok) {
                alert('서버 오류: HTTP ' + response.status);
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('서버 응답 (원본):', text);

            // 응답이 비어있는지 확인
            if (!text || text.trim() === '') {
                alert('서버 응답이 비어있습니다.');
                throw new Error('빈 응답');
            }

            try {
                return JSON.parse(text);
            } catch(e) {
                console.error('JSON 파싱 오류:', e);
                console.error('응답 내용:', text);

                // 서버 응답의 일부를 alert로 표시
                const preview = text.substring(0, 200);
                alert('JSON 파싱 오류!\n\n서버 응답 미리보기:\n' + preview + '\n\n전체 내용은 Console을 확인하세요.');
                throw new Error('JSON 파싱 실패');
            }
        })
        .then(data => {
            console.log('파싱된 데이터:', data);

            if (data.success) {
                // 토큰 업데이트
                if (data.new_token && tokenInput) {
                    tokenInput.value = data.new_token;
                    console.log('새 토큰 설정:', data.new_token);
                }

                // 새 댓글 HTML 생성
                const newCommentHTML = `
                    <div class="flex gap-3 mb-3" style="animation: slideIn 0.3s ease-out;">
                        <img src="${data.comment.photo}" class="w-8 h-8 rounded-full">
                        <div class="flex-1 bg-gray-50 rounded-2xl px-3 py-2" style="background: rgba(139, 92, 246, 0.1);">
                            <div class="font-semibold text-xs mb-1">${data.comment.nick}</div>
                            <div class="text-sm">${data.comment.content}</div>
                        </div>
                    </div>
                `;

                if (commentList) {
                    // 빈 메시지 제거
                    const emptyMessage = commentList.querySelector('.text-center.text-gray-500');
                    if (emptyMessage) emptyMessage.remove();

                    // 댓글 추가
                    commentList.insertAdjacentHTML('beforeend', newCommentHTML);

                    // 스크롤 (부드럽게)
                    const allComments = commentList.querySelectorAll('.flex.gap-3.mb-3');
                    const lastComment = allComments[allComments.length - 1];
                    lastComment.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    // 하이라이트 효과 제거
                    setTimeout(() => {
                        lastComment.querySelector('.flex-1').style.background = '';
                    }, 2000);
                }

                // 댓글 개수 업데이트
                const commentCountH3 = commentList.previousElementSibling;
                if (commentCountH3) {
                    const match = commentCountH3.textContent.match(/\d+/);
                    const currentCount = match ? parseInt(match[0]) : 0;
                    commentCountH3.textContent = '댓글 ' + (currentCount + 1) + '개';
                }

                // 입력창 비우기
                input.value = '';

            } else {
                // 실패 시 메시지 표시 (DB에 저장되었을 수도 있으므로 페이지 새로고침 권장)
                console.error('댓글 저장 실패:', data.message);
                alert(data.message || '댓글 작성 중 오류가 발생했습니다.');
            }
        })
        .catch(error => {
            console.error('요청 오류:', error);
            alert('오류 발생!\n\n오류 내용: ' + error.message + '\n\nDB에는 저장되었을 수 있으니 페이지를 새로고침해주세요.');
        })
        .finally(() => {
            // 항상 버튼 복구 및 포커스
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalContent;
            input.focus();
        });

        return false; // 폼 기본 동작 완전 차단
    };

    // capture phase에서 실행하여 wrest.js보다 먼저 처리
    form.addEventListener('submit', handleSubmit, true);
})();
</script>

<style>
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

</body>
</html>
<?php
if (!$is_admin && $view['mb_id'] != $member['mb_id']) {
    sql_query("UPDATE {$g5['write_prefix']}{$bo_table} SET wr_hit = wr_hit + 1 WHERE wr_id = '{$wr_id}'");
}
?>
