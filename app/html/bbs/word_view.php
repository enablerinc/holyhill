<?php
include_once('./_common.php');

// 게시판 설정
$bo_table = 'word';
$wr_id = isset($_GET['wr_id']) ? (int)$_GET['wr_id'] : 0;

if (!$wr_id) {
    alert('잘못된 접근입니다.', G5_BBS_URL.'/word_list.php');
}

$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    alert('존재하지 않는 게시판입니다.', G5_URL);
}

// 권한 체크
if ($member['mb_level'] < $board['bo_read_level']) {
    if ($member['mb_id'])
        alert('읽기 권한이 없습니다.', G5_BBS_URL.'/word_list.php');
    else
        alert('읽기 권한이 없습니다.\\n\\n회원이시라면 로그인 후 이용해 보십시오.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/word_view.php?wr_id='.$wr_id));
}

// 게시글 가져오기
$write_table = $g5['write_prefix'] . $bo_table;
$write = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$wr_id}'");

if (!$write) {
    alert('존재하지 않는 게시물입니다.', G5_BBS_URL.'/word_list.php');
}

// 조회수 증가
sql_query("UPDATE {$write_table} SET wr_hit = wr_hit + 1 WHERE wr_id = '{$wr_id}'");

// 추천/비추천 기능을 위한 세션 설정
$ss_name = 'ss_view_'.$bo_table.'_'.$wr_id;
set_session($ss_name, true);

$g5['title'] = get_text($write['wr_subject']);

// YouTube URL을 iframe으로 변환
function convert_youtube_to_iframe_view($content) {
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

// YouTube URL 체크
$has_youtube = preg_match('/(youtube\.com|youtu\.be)/', $write['wr_content']);

// 첨부 이미지 가져오기
$files = array();
$file_sql = "SELECT * FROM {$g5['board_file_table']}
             WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}'
             AND bf_type BETWEEN 1 AND 3
             ORDER BY bf_no";
$file_result = sql_query($file_sql);
while ($row = sql_fetch_array($file_result)) {
    $files[] = $row;
}

// 댓글 수
$comment_count = sql_fetch("SELECT COUNT(*) as cnt FROM {$write_table} WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1");

// 수정/삭제 권한
$is_owner = ($is_member && $member['mb_id'] == $write['mb_id']);
$can_delete = ($is_owner || $is_admin);

// 삭제 토큰 생성 (Gnuboard 표준 방식)
$delete_token = '';
if ($can_delete) {
    set_session('ss_delete_token', $delete_token = uniqid(time()));
}

// 댓글 토큰 생성
$comment_token = '';
if ($is_member) {
    set_session('ss_comment_token', $comment_token = uniqid(time()));
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
    <script src="<?php echo G5_JS_URL; ?>/jquery-1.12.4.min.js"></script>
    <style>
        ::-webkit-scrollbar { display: none; }
        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #EEF3F8;
        }
        .content-body {
            line-height: 1.8;
            word-break: break-word;
        }
        .content-body img {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            margin: 1rem 0;
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
<header id="header" class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="javascript:history.back()" class="text-grace-green hover:text-gray-900">
            <i class="fa-solid fa-arrow-left text-xl"></i>
        </a>
        <h1 class="text-base font-semibold text-grace-green">오늘의 말씀</h1>
        <?php if ($can_delete) { ?>
        <button onclick="toggleMenu()" class="text-grace-green hover:text-gray-900">
            <i class="fa-solid fa-ellipsis-vertical text-xl"></i>
        </button>
        <?php } else { ?>
        <div class="w-6"></div>
        <?php } ?>
    </div>
</header>

<!-- 메뉴 드롭다운 -->
<?php if ($can_delete) { ?>
<div id="action-menu" class="hidden fixed top-14 right-4 bg-white rounded-lg shadow-lg border border-gray-200 z-50 overflow-hidden">
    <a href="<?php echo G5_BBS_URL; ?>/delete.php?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>&token=<?php echo $delete_token; ?>"
       onclick="return confirm('정말 삭제하시겠습니까?');"
       class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50">
        <i class="fa-solid fa-trash mr-2"></i>삭제하기
    </a>
</div>
<?php } ?>

<main id="main-content" class="pt-16 pb-20">
    <div class="max-w-2xl mx-auto">

        <!-- 게시글 내용 -->
        <article class="bg-white mx-4 mt-4 rounded-2xl shadow-md overflow-hidden">
            <!-- 헤더 -->
            <div class="px-4 pt-4 pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-600 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-book-bible text-white text-sm"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm"><?php echo $write['wr_name']; ?></h4>
                        <p class="text-xs text-gray-500">
                            <?php echo date('Y년 m월 d일 H:i', strtotime($write['wr_datetime'])); ?>
                            · 조회 <?php echo number_format($write['wr_hit']); ?>
                        </p>
                    </div>
                </div>
                <?php if ($write['wr_subject']) { ?>
                <h1 class="text-xl font-bold text-gray-900 mb-2"><?php echo get_text($write['wr_subject']); ?></h1>
                <?php } ?>
            </div>

            <!-- 내용 -->
            <div class="px-4 py-4">
                <div class="content-body text-gray-800">
                    <?php
                    if ($has_youtube) {
                        $content = get_text($write['wr_content']);
                        echo convert_youtube_to_iframe_view($content);
                    } else {
                        echo nl2br(get_text($write['wr_content']));
                    }
                    ?>
                </div>

                <!-- 첨부 이미지 -->
                <?php if (count($files) > 0) { ?>
                <div class="mt-4 space-y-3">
                    <?php foreach ($files as $file) { ?>
                    <img src="<?php echo G5_DATA_URL.'/file/'.$bo_table.'/'.$file['bf_file']; ?>"
                         class="w-full rounded-lg"
                         alt="<?php echo $file['bf_source']; ?>">
                    <?php } ?>
                </div>
                <?php } ?>
            </div>

            <!-- 좋아요 및 댓글 -->
            <div class="px-4 pb-4 border-t border-gray-100 pt-4">
                <div class="flex items-center gap-4">
                    <button onclick="toggleGood()" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                        <i class="fa-solid fa-heart text-red-500 text-xl"></i>
                        <span class="text-sm text-gray-700">좋아요 <span id="good-count"><?php echo number_format($write['wr_good']); ?></span>개</span>
                    </button>
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-comment text-gray-700 text-xl"></i>
                        <span class="text-sm text-gray-700"><?php echo number_format($comment_count['cnt']); ?>개</span>
                    </div>
                </div>
            </div>
        </article>

        <!-- 댓글 섹션 -->
        <section class="mx-4 mt-4">
            <?php
            // 댓글 목록
            $comment_sql = "SELECT * FROM {$write_table}
                           WHERE wr_parent = '{$wr_id}' AND wr_is_comment = 1
                           ORDER BY wr_id ASC";
            $comment_result = sql_query($comment_sql);

            if ($comment_count['cnt'] > 0) {
            ?>
            <div class="bg-white rounded-2xl shadow-md overflow-hidden p-4">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-comments text-purple-600"></i>
                    댓글 <span class="comment-count"><?php echo number_format($comment_count['cnt']); ?></span>개
                </h3>
                <div class="space-y-4">
                    <?php
                    while ($comment = sql_fetch_array($comment_result)) {
                        $comment_nick = $comment['wr_name'];
                        $is_comment_owner = ($is_member && $member['mb_id'] == $comment['mb_id']);

                        // 댓글 삭제 토큰 생성
                        $delete_comment_token = '';
                        if ($is_comment_owner || $is_admin) {
                            $delete_comment_token = uniqid(time());
                            set_session('ss_delete_comment_'.$comment['wr_id'].'_token', $delete_comment_token);
                        }
                    ?>
                    <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0" id="comment-<?php echo $comment['wr_id']; ?>">
                        <div class="flex items-start gap-3">
                            <?php
                            // 프로필 이미지 URL 가져오기
                            $comment_profile_img_url = '';
                            if ($comment['mb_id']) {
                                $member_img_dir = G5_DATA_PATH.'/member_image/'.substr($comment['mb_id'],0,2);
                                $member_img_name = get_mb_icon_name($comment['mb_id']);
                                $extensions = array('gif', 'jpg', 'jpeg', 'png');

                                foreach ($extensions as $ext) {
                                    $test_path = $member_img_dir.'/'.$member_img_name.'.'.$ext;
                                    if (is_file($test_path)) {
                                        $comment_profile_img_url = str_replace(G5_DATA_PATH, G5_DATA_URL, $test_path);
                                        break;
                                    }
                                }
                            }

                            if ($comment_profile_img_url) {
                            ?>
                            <img src="<?php echo $comment_profile_img_url; ?>" class="w-8 h-8 rounded-full object-cover flex-shrink-0" alt="profile">
                            <?php } else { ?>
                            <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-user text-gray-500 text-xs"></i>
                            </div>
                            <?php } ?>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-semibold text-sm text-gray-800"><?php echo $comment_nick; ?></span>
                                    <?php if ($is_comment_owner || $is_admin) { ?>
                                    <button onclick="deleteComment('<?php echo $comment['wr_id']; ?>', '<?php echo $bo_table; ?>', '<?php echo $wr_id; ?>', '<?php echo $delete_comment_token; ?>');"
                                       class="text-xs text-red-500 hover:text-red-700">
                                        삭제
                                    </button>
                                    <?php } ?>
                                </div>
                                <p class="text-sm text-gray-700 leading-relaxed"><?php echo nl2br(get_text($comment['wr_content'])); ?></p>
                                <span class="text-xs text-gray-500 mt-1 inline-block">
                                    <?php echo date('Y-m-d H:i', strtotime($comment['wr_datetime'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>

            <!-- 댓글 작성 -->
            <?php if ($is_member) { ?>
            <div class="bg-white rounded-2xl shadow-md overflow-hidden p-4 mt-4">
                <form id="commentForm" method="post" action="<?php echo G5_BBS_URL; ?>/write_comment_update.php" onsubmit="return submit_comment(this);">
                    <input type="hidden" name="token" value="<?php echo $comment_token; ?>">
                    <input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
                    <input type="hidden" name="wr_id" value="<?php echo $wr_id; ?>">
                    <input type="hidden" name="w" value="c">
                    <input type="hidden" name="comment_id" value="">
                    <input type="hidden" name="sca" value="">
                    <input type="hidden" name="sfl" value="">
                    <input type="hidden" name="stx" value="">
                    <input type="hidden" name="page" value="">

                    <div class="flex gap-2">
                        <textarea name="wr_content" id="wr_content"
                                  class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 resize-none"
                                  rows="3"
                                  placeholder="댓글을 입력하세요..."></textarea>
                        <button type="submit"
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium text-sm h-fit">
                            등록
                        </button>
                    </div>
                </form>
            </div>
            <?php } else { ?>
            <div class="bg-white rounded-2xl shadow-md overflow-hidden p-4 mt-4 text-center">
                <p class="text-sm text-gray-600 mb-3">댓글을 작성하려면 로그인이 필요합니다</p>
                <a href="<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                   class="inline-block px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium">
                    로그인
                </a>
            </div>
            <?php } ?>
        </section>

    </div>
</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<script>
// 메뉴 토글
function toggleMenu() {
    const menu = document.getElementById('action-menu');
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

// 메뉴 외부 클릭 시 닫기
document.addEventListener('click', function(e) {
    const menu = document.getElementById('action-menu');
    const menuButton = e.target.closest('button[onclick="toggleMenu()"]');

    if (menu && !menu.contains(e.target) && !menuButton) {
        menu.classList.add('hidden');
    }
});

// 좋아요 토글
function toggleGood() {
    <?php if ($is_member) { ?>
    $.ajax({
        url: '<?php echo G5_BBS_URL; ?>/good.php',
        type: 'POST',
        dataType: 'json',
        data: {
            js: 'on',
            bo_table: '<?php echo $bo_table; ?>',
            wr_id: '<?php echo $wr_id; ?>',
            good: 'good',
            js: 'on'
        },
        success: function(response) {
            if (response.error) {
                alert(response.error);
            } else {
                $('#good-count').text(new Intl.NumberFormat().format(response.count));
            }
        },
        error: function() {
            alert('오류가 발생했습니다.');
        }
    });
    <?php } else { ?>
    if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
        location.href = '<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>';
    }
    <?php } ?>
}

// 댓글 유효성 검사
function validate_comment(f) {
    if (f.wr_content.value.trim() == '') {
        alert('댓글 내용을 입력해주세요.');
        f.wr_content.focus();
        return false;
    }
    return true;
}

// 댓글 제출 (AJAX)
function submit_comment(f) {
    if (!validate_comment(f)) {
        return false;
    }

    var formData = $(f).serialize() + '&ajax=1';

    $.ajax({
        url: $(f).attr('action'),
        type: 'POST',
        dataType: 'json',
        data: formData,
        success: function(response) {
            if (response.success) {
                // 댓글 입력창 초기화
                $('#wr_content').val('');

                // 댓글 목록이 없으면 새로 생성
                var commentSection = $('.space-y-4');
                if (commentSection.length === 0) {
                    var newCommentSection = `
                        <div class="bg-white rounded-2xl shadow-md overflow-hidden p-4">
                            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                <i class="fa-solid fa-comments text-purple-600"></i>
                                댓글 <span class="comment-count">1</span>개
                            </h3>
                            <div class="space-y-4"></div>
                        </div>
                    `;
                    $('.bg-white.rounded-2xl.shadow-md.overflow-hidden.p-4.mt-4').first().before(newCommentSection);
                    commentSection = $('.space-y-4');
                }

                // 새 댓글 HTML 생성
                var newComment = `
                    <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0" id="comment-${response.comment_id}">
                        <div class="flex items-start gap-3">
                            ${response.profile_img ?
                                `<img src="${response.profile_img}" class="w-8 h-8 rounded-full object-cover flex-shrink-0" alt="profile">` :
                                `<div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-user text-gray-500 text-xs"></i>
                                </div>`
                            }
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-semibold text-sm text-gray-800">${response.wr_name}</span>
                                    <button onclick="deleteComment('${response.comment_id}', '<?php echo $bo_table; ?>', '<?php echo $wr_id; ?>', '${response.delete_token}');"
                                       class="text-xs text-red-500 hover:text-red-700">
                                        삭제
                                    </button>
                                </div>
                                <p class="text-sm text-gray-700 leading-relaxed">${response.wr_content}</p>
                                <span class="text-xs text-gray-500 mt-1 inline-block">${response.datetime}</span>
                            </div>
                        </div>
                    </div>
                `;

                // 댓글 목록에 추가
                commentSection.append(newComment);

                // 댓글 카운트 업데이트
                if (response.comment_count !== undefined) {
                    $('.comment-count').text(response.comment_count);
                    $('.fa-comment').next('span').text(response.comment_count + '개');
                }

                // 새 댓글 강조 효과
                $('#comment-' + response.comment_id).hide().fadeIn(300);
            } else {
                alert(response.message || '댓글 등록 중 오류가 발생했습니다.');
            }
        },
        error: function(xhr, status, error) {
            alert('댓글 등록 중 오류가 발생했습니다.');
        }
    });

    return false; // 기본 form submit 방지
}

// 댓글 삭제 (AJAX)
function deleteComment(comment_id, bo_table, wr_id, token) {
    $.ajax({
        url: '<?php echo G5_BBS_URL; ?>/delete_comment.php',
        type: 'POST',
        dataType: 'json',
        data: {
            comment_id: comment_id,
            bo_table: bo_table,
            wr_id: wr_id,
            token: token,
            ajax: '1'
        },
        success: function(response) {
            if (response.success) {
                // 댓글 요소를 부드럽게 제거
                $('#comment-' + comment_id).fadeOut(300, function() {
                    $(this).remove();
                    // 댓글 카운트 업데이트
                    if (response.comment_count !== undefined) {
                        $('.comment-count').text(response.comment_count);
                        $('.fa-comment').next('span').text(response.comment_count + '개');
                    }
                });
            } else {
                alert(response.message || '댓글 삭제 중 오류가 발생했습니다.');
            }
        },
        error: function(xhr, status, error) {
            alert('댓글 삭제 중 오류가 발생했습니다.');
        }
    });
}
</script>

</body>
</html>
