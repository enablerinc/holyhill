<?php
include_once('./_common.php');

// 게시판 설정
$bo_table = 'diary';
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    alert('감사일기 게시판이 없습니다.', G5_BBS_URL.'/gratitude.php');
}

// 권한 체크
if ($member['mb_level'] < $board['bo_list_level']) {
    if ($member['mb_id'])
        alert('목록을 볼 권한이 없습니다.', G5_URL);
    else
        alert('로그인 후 이용해 주세요.', G5_BBS_URL.'/login.php?url='.urlencode($_SERVER['REQUEST_URI']));
}

// 대상 회원 ID
$target_mb_id = isset($_GET['mb_id']) ? clean_xss_tags($_GET['mb_id']) : '';
$scroll_to_wr_id = isset($_GET['wr_id']) ? (int)$_GET['wr_id'] : 0;

if (!$target_mb_id) {
    alert('잘못된 접근입니다.', G5_BBS_URL.'/gratitude.php');
}

// 대상 회원 정보
$target_member = sql_fetch("SELECT mb_id, mb_nick, mb_name FROM {$g5['member_table']} WHERE mb_id = '".sql_real_escape_string($target_mb_id)."'");
if (!$target_member) {
    alert('존재하지 않는 회원입니다.', G5_BBS_URL.'/gratitude.php');
}

$target_name = $target_member['mb_name'] ? $target_member['mb_name'] : ($target_member['mb_nick'] ? $target_member['mb_nick'] : $target_mb_id);

// 프로필 이미지
$target_photo = '';
$profile_path = G5_DATA_PATH.'/member_image/'.substr($target_mb_id, 0, 2).'/'.$target_mb_id.'.gif';
if (file_exists($profile_path)) {
    $target_photo = G5_DATA_URL.'/member_image/'.substr($target_mb_id, 0, 2).'/'.$target_mb_id.'.gif';
}

$g5['title'] = $target_name . '의 감사일기';

// 글 테이블
$write_table = $g5['write_prefix'] . $bo_table;

// 해당 회원의 전체 일기 수
$total_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE mb_id = '".sql_real_escape_string($target_mb_id)."' AND wr_is_comment = 0";
$total_count = sql_fetch($total_count_sql)['cnt'];

// 받은 총 좋아요 수
$total_good_sql = "SELECT SUM(wr_good) as total FROM {$write_table} WHERE mb_id = '".sql_real_escape_string($target_mb_id)."' AND wr_is_comment = 0";
$total_good = sql_fetch($total_good_sql)['total'] ?? 0;

// 해당 회원의 모든 일기 가져오기 (최신순)
$sql = "SELECT * FROM {$write_table} WHERE mb_id = '".sql_real_escape_string($target_mb_id)."' AND wr_is_comment = 0 ORDER BY wr_datetime DESC";
$result = sql_query($sql);

$diary_list = array();
while ($row = sql_fetch_array($result)) {
    $diary_list[] = $row;
}

// 현재 로그인한 사용자의 프로필 (댓글용)
$my_profile_photo = '';
if ($is_member) {
    $my_profile_path = G5_DATA_PATH.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif';
    if (file_exists($my_profile_path)) {
        $my_profile_photo = G5_DATA_URL.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif';
    }
}

// 댓글 토큰
$comment_token = '';
if ($is_member) {
    $comment_token = get_random_token_string();
    set_session('ss_comment_token', $comment_token);
}

// 시간 표시 함수
function get_time_ago_user($datetime) {
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
        return date('Y.m.d', strtotime($datetime));
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
        .diary-card {
            transition: all 0.3s ease;
        }
        .diary-card.highlight {
            animation: highlight-pulse 2s ease-out;
        }
        @keyframes highlight-pulse {
            0% { box-shadow: 0 0 0 4px rgba(177, 156, 217, 0.6); }
            100% { box-shadow: none; }
        }
        .comment-section {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .comment-section.open {
            max-height: 2000px;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .new-comment {
            animation: slideIn 0.3s ease-out;
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
        <a href="<?php echo G5_BBS_URL; ?>/gratitude.php" class="w-10 h-10 flex items-center justify-center -ml-2">
            <i class="fa-solid fa-arrow-left text-grace-green text-lg"></i>
        </a>
        <h1 class="text-base font-bold text-grace-green"><?php echo $target_name; ?>의 감사일기</h1>
        <div class="w-10"></div>
    </div>
</header>

<main class="pt-16 pb-24 max-w-2xl mx-auto">

    <!-- 프로필 카드 -->
    <div class="px-4 py-6">
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-soft-lavender/50 text-center">
            <!-- 프로필 이미지 -->
            <div class="mb-4">
                <?php if ($target_photo) { ?>
                <img src="<?php echo $target_photo; ?>" alt="<?php echo $target_name; ?>" class="w-20 h-20 rounded-full object-cover mx-auto border-4 border-soft-lavender shadow-lg">
                <?php } else { ?>
                <div class="w-20 h-20 rounded-full bg-gradient-to-br from-lilac to-deep-purple flex items-center justify-center mx-auto border-4 border-soft-lavender shadow-lg">
                    <span class="text-white font-bold text-2xl"><?php echo mb_substr($target_name, 0, 1, 'UTF-8'); ?></span>
                </div>
                <?php } ?>
            </div>

            <!-- 이름 -->
            <h2 class="text-xl font-bold text-grace-green mb-4"><?php echo $target_name; ?></h2>

            <!-- 통계 -->
            <div class="flex items-center justify-center gap-8">
                <div class="text-center">
                    <p class="text-2xl font-bold text-deep-purple"><?php echo number_format($total_count); ?></p>
                    <p class="text-xs text-grace-green/60">감사일기</p>
                </div>
                <div class="w-px h-10 bg-soft-lavender"></div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-lilac"><?php echo number_format($total_good); ?></p>
                    <p class="text-xs text-grace-green/60">받은 공감</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 일기 목록 -->
    <div class="px-4 space-y-4" id="diary-container">
        <?php
        if (count($diary_list) > 0) {
            foreach ($diary_list as $diary) {
                $wr_id = $diary['wr_id'];
                $is_highlight = ($wr_id == $scroll_to_wr_id);

                // 좋아요 여부 체크
                $is_good = false;
                if ($is_member) {
                    $good_check = sql_fetch("SELECT COUNT(*) as cnt FROM {$g5['board_good_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND mb_id = '{$member['mb_id']}'");
                    $is_good = $good_check['cnt'] > 0;
                }

                // 댓글 수
                $comment_count_sql = "SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1";
                $comment_count = sql_fetch($comment_count_sql)['cnt'];

                // 날짜 포맷
                $date_display = date('Y년 m월 d일', strtotime($diary['wr_datetime']));
                $day_of_week = array('일', '월', '화', '수', '목', '금', '토');
                $dow = $day_of_week[date('w', strtotime($diary['wr_datetime']))];
        ?>
        <article id="diary-<?php echo $wr_id; ?>" class="diary-card bg-white rounded-2xl shadow-sm border border-soft-lavender/30 overflow-hidden <?php echo $is_highlight ? 'highlight' : ''; ?>">
            <!-- 날짜 헤더 -->
            <div class="px-5 py-3 bg-gradient-to-r from-soft-lavender/50 to-warm-beige border-b border-soft-lavender/30">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-grace-green"><?php echo $date_display; ?> (<?php echo $dow; ?>)</span>
                    <span class="text-xs text-grace-green/50"><?php echo get_time_ago_user($diary['wr_datetime']); ?></span>
                </div>
            </div>

            <!-- 본문 -->
            <div class="px-5 py-4">
                <p class="text-grace-green leading-relaxed whitespace-pre-wrap"><?php echo nl2br(get_text($diary['wr_content'])); ?></p>
            </div>

            <!-- 하단 액션 -->
            <div class="px-5 py-3 border-t border-soft-lavender/30 bg-warm-beige/30">
                <div class="flex items-center justify-between">
                    <!-- 좋아요 버튼 -->
                    <button onclick="toggleGood(<?php echo $wr_id; ?>)" class="flex items-center gap-2 px-4 py-2 rounded-full hover:bg-soft-lavender/50 transition-colors" id="good-btn-<?php echo $wr_id; ?>">
                        <i class="<?php echo $is_good ? 'fa-solid' : 'fa-regular'; ?> fa-heart text-lg <?php echo $is_good ? 'text-deep-purple' : 'text-grace-green/50'; ?>" id="heart-icon-<?php echo $wr_id; ?>"></i>
                        <span class="text-sm text-grace-green" id="good-count-<?php echo $wr_id; ?>"><?php echo number_format($diary['wr_good']); ?></span>
                    </button>

                    <!-- 댓글 토글 버튼 -->
                    <button onclick="toggleComments(<?php echo $wr_id; ?>)" class="flex items-center gap-2 px-4 py-2 rounded-full hover:bg-soft-lavender/50 transition-colors" id="comment-toggle-<?php echo $wr_id; ?>">
                        <i class="fa-regular fa-comment text-lg text-grace-green/50"></i>
                        <span class="text-sm text-grace-green" id="comment-count-<?php echo $wr_id; ?>"><?php echo number_format($comment_count); ?></span>
                        <i class="fa-solid fa-chevron-down text-xs text-grace-green/30 transition-transform" id="comment-arrow-<?php echo $wr_id; ?>"></i>
                    </button>

                    <?php if (($is_member && $member['mb_id'] === $diary['mb_id']) || $is_admin) { ?>
                    <!-- 수정/삭제 -->
                    <div class="relative">
                        <button onclick="toggleMenu(<?php echo $wr_id; ?>)" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-soft-lavender/50">
                            <i class="fa-solid fa-ellipsis text-grace-green/50"></i>
                        </button>
                        <div id="menu-<?php echo $wr_id; ?>" class="hidden absolute right-0 bottom-10 bg-white border border-soft-lavender rounded-xl shadow-lg py-2 w-24 z-10">
                            <a href="<?php echo G5_BBS_URL; ?>/gratitude_write.php?w=u&wr_id=<?php echo $wr_id; ?>" class="block px-4 py-2 text-sm text-grace-green hover:bg-soft-lavender/50">수정</a>
                            <button onclick="deleteDiary(<?php echo $wr_id; ?>)" class="block w-full text-left px-4 py-2 text-sm text-deep-purple hover:bg-soft-lavender/50">삭제</button>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <!-- 댓글 섹션 -->
            <div class="comment-section" id="comments-<?php echo $wr_id; ?>">
                <div class="px-5 py-4 bg-warm-beige/50 border-t border-soft-lavender/30">
                    <!-- 댓글 목록 -->
                    <div class="space-y-3 mb-4" id="comment-list-<?php echo $wr_id; ?>">
                        <?php
                        // 댓글 가져오기
                        $comments_sql = "SELECT * FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1 ORDER BY wr_id ASC LIMIT 50";
                        $comments_result = sql_query($comments_sql);

                        while ($comment = sql_fetch_array($comments_result)) {
                            $c_nick = $comment['wr_name'];
                            $c_photo = '';

                            if ($comment['mb_id']) {
                                $c_member = sql_fetch("SELECT mb_name, mb_nick FROM {$g5['member_table']} WHERE mb_id = '{$comment['mb_id']}'");
                                if ($c_member) {
                                    $c_nick = $c_member['mb_name'] ? $c_member['mb_name'] : ($c_member['mb_nick'] ? $c_member['mb_nick'] : $comment['wr_name']);
                                }
                                $c_profile_path = G5_DATA_PATH.'/member_image/'.substr($comment['mb_id'], 0, 2).'/'.$comment['mb_id'].'.gif';
                                if (file_exists($c_profile_path)) {
                                    $c_photo = G5_DATA_URL.'/member_image/'.substr($comment['mb_id'], 0, 2).'/'.$comment['mb_id'].'.gif';
                                }
                            }
                        ?>
                        <div class="flex gap-3" id="comment-<?php echo $comment['wr_id']; ?>">
                            <?php if ($c_photo) { ?>
                            <img src="<?php echo $c_photo; ?>" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            <?php } else { ?>
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-lilac to-deep-purple flex items-center justify-center flex-shrink-0">
                                <span class="text-white text-xs font-bold"><?php echo mb_substr($c_nick, 0, 1, 'UTF-8'); ?></span>
                            </div>
                            <?php } ?>
                            <div class="flex-1">
                                <div class="bg-white rounded-2xl px-4 py-2 shadow-sm">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-semibold text-sm text-grace-green"><?php echo $c_nick; ?></span>
                                        <span class="text-xs text-grace-green/40"><?php echo get_time_ago_user($comment['wr_datetime']); ?></span>
                                    </div>
                                    <p class="text-sm text-grace-green/80"><?php echo nl2br(get_text($comment['wr_content'])); ?></p>
                                </div>
                                <?php if (($is_member && $member['mb_id'] === $comment['mb_id']) || $is_admin) { ?>
                                <div class="flex gap-2 mt-1 ml-4">
                                    <button onclick="deleteComment(<?php echo $comment['wr_id']; ?>, <?php echo $wr_id; ?>)" class="text-xs text-deep-purple/70 hover:text-deep-purple">삭제</button>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <!-- 댓글 입력 -->
                    <?php if ($is_member) { ?>
                    <div class="flex gap-3 items-center">
                        <?php if ($my_profile_photo) { ?>
                        <img src="<?php echo $my_profile_photo; ?>" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                        <?php } else { ?>
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-lilac to-deep-purple flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-xs font-bold"><?php echo mb_substr($member['mb_name'] ? $member['mb_name'] : $member['mb_nick'], 0, 1, 'UTF-8'); ?></span>
                        </div>
                        <?php } ?>
                        <div class="flex-1 flex gap-2 bg-white rounded-full px-4 py-2 shadow-sm border border-soft-lavender/50">
                            <input type="text"
                                   id="comment-input-<?php echo $wr_id; ?>"
                                   class="flex-1 bg-transparent border-none outline-none text-sm text-grace-green placeholder-grace-green/40"
                                   placeholder="따뜻한 댓글을 남겨주세요..."
                                   onkeypress="if(event.key==='Enter') submitComment(<?php echo $wr_id; ?>)">
                            <button onclick="submitComment(<?php echo $wr_id; ?>)" class="text-deep-purple hover:text-lilac transition-colors">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                    <?php } else { ?>
                    <div class="text-center py-2">
                        <a href="<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="text-sm text-deep-purple hover:underline">로그인하고 댓글 남기기</a>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </article>
        <?php
            }
        } else {
        ?>
        <div class="text-center py-20">
            <div class="w-16 h-16 bg-soft-lavender rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-regular fa-face-sad-tear text-2xl text-lilac"></i>
            </div>
            <p class="text-grace-green">아직 작성된 감사일기가 없어요</p>
        </div>
        <?php } ?>
    </div>

</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<script>
// 전역 변수
const boTable = '<?php echo $bo_table; ?>';
const commentToken = '<?php echo $comment_token; ?>';

// 특정 일기로 스크롤
<?php if ($scroll_to_wr_id) { ?>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const target = document.getElementById('diary-<?php echo $scroll_to_wr_id; ?>');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 300);
});
<?php } ?>

// 좋아요 토글
function toggleGood(wrId) {
    <?php if (!$is_member) { ?>
    if (confirm('로그인이 필요합니다. 로그인 페이지로 이동할까요?')) {
        location.href = '<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>';
    }
    return;
    <?php } ?>

    fetch('<?php echo G5_BBS_URL; ?>/ajax.good.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'bo_table=' + boTable + '&wr_id=' + wrId
    })
    .then(r => r.json())
    .then(data => {
        if (data.result) {
            const icon = document.getElementById('heart-icon-' + wrId);
            const count = document.getElementById('good-count-' + wrId);

            icon.classList.toggle('fa-regular');
            icon.classList.toggle('fa-solid');
            icon.classList.toggle('text-grace-green/50');
            icon.classList.toggle('text-deep-purple');

            count.textContent = data.count;
        }
    });
}

// 댓글 섹션 토글
function toggleComments(wrId) {
    const section = document.getElementById('comments-' + wrId);
    const arrow = document.getElementById('comment-arrow-' + wrId);

    section.classList.toggle('open');
    arrow.style.transform = section.classList.contains('open') ? 'rotate(180deg)' : '';
}

// 메뉴 토글
function toggleMenu(wrId) {
    const menu = document.getElementById('menu-' + wrId);
    menu.classList.toggle('hidden');
}

// 외부 클릭시 메뉴 닫기
document.addEventListener('click', function(e) {
    if (!e.target.closest('[id^="menu-"]') && !e.target.closest('button')) {
        document.querySelectorAll('[id^="menu-"]').forEach(menu => {
            menu.classList.add('hidden');
        });
    }
});

// 댓글 작성
function submitComment(wrId) {
    const input = document.getElementById('comment-input-' + wrId);
    const content = input.value.trim();

    if (!content) {
        input.focus();
        return;
    }

    const formData = new FormData();
    formData.append('w', 'c');
    formData.append('bo_table', boTable);
    formData.append('wr_id', wrId);
    formData.append('wr_content', content);
    formData.append('token', commentToken);

    fetch('<?php echo G5_BBS_URL; ?>/comment_write_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // 새 댓글 추가
            const commentList = document.getElementById('comment-list-' + wrId);
            const newCommentHTML = `
                <div class="flex gap-3 new-comment" id="comment-${data.comment.id}">
                    <img src="${data.comment.photo || ''}" class="w-8 h-8 rounded-full object-cover flex-shrink-0" onerror="this.outerHTML='<div class=\\'w-8 h-8 rounded-full bg-gradient-to-br from-lilac to-deep-purple flex items-center justify-center flex-shrink-0\\'><span class=\\'text-white text-xs font-bold\\'>${data.comment.nick.charAt(0)}</span></div>'">
                    <div class="flex-1">
                        <div class="bg-white rounded-2xl px-4 py-2 shadow-sm" style="background: rgba(177, 156, 217, 0.1);">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold text-sm text-grace-green">${data.comment.nick}</span>
                                <span class="text-xs text-grace-green/40">방금 전</span>
                            </div>
                            <p class="text-sm text-grace-green/80">${data.comment.content}</p>
                        </div>
                    </div>
                </div>
            `;
            commentList.insertAdjacentHTML('beforeend', newCommentHTML);

            // 댓글 수 업데이트
            const countEl = document.getElementById('comment-count-' + wrId);
            countEl.textContent = parseInt(countEl.textContent.replace(/,/g, '')) + 1;

            // 입력창 초기화
            input.value = '';

            // 하이라이트 제거
            setTimeout(() => {
                const newComment = document.getElementById('comment-' + data.comment.id);
                if (newComment) {
                    newComment.querySelector('.bg-white').style.background = '';
                }
            }, 2000);

        } else {
            alert(data.message || '댓글 작성에 실패했습니다.');
        }
    })
    .catch(error => {
        alert('오류가 발생했습니다: ' + error.message);
    });
}

// 댓글 삭제
function deleteComment(commentId, parentWrId) {
    if (!confirm('댓글을 삭제하시겠습니까?')) return;

    const formData = new FormData();
    formData.append('comment_id', commentId);
    formData.append('bo_table', boTable);
    formData.append('wr_id', parentWrId);

    fetch('<?php echo G5_BBS_URL; ?>/comment_delete.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('comment-' + commentId).remove();

            // 댓글 수 업데이트
            const countEl = document.getElementById('comment-count-' + parentWrId);
            countEl.textContent = Math.max(0, parseInt(countEl.textContent.replace(/,/g, '')) - 1);
        } else {
            alert(data.message || '삭제에 실패했습니다.');
        }
    });
}

// 일기 삭제
function deleteDiary(wrId) {
    if (!confirm('이 감사일기를 삭제하시겠습니까?')) return;

    // 삭제 토큰 가져오기
    fetch('<?php echo G5_BBS_URL; ?>/delete.php?bo_table=' + boTable + '&wr_id=' + wrId + '&token=<?php echo uniqid(time()); ?>', {
        method: 'GET'
    })
    .then(() => {
        document.getElementById('diary-' + wrId).remove();
        alert('삭제되었습니다.');

        // 일기가 더 이상 없으면 목록으로 이동
        if (document.querySelectorAll('.diary-card').length === 0) {
            location.href = '<?php echo G5_BBS_URL; ?>/gratitude.php';
        }
    });
}
</script>

</body>
</html>
