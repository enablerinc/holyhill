<?php
include_once('./_common.php');

// 게시판 설정
$bo_table = 'diary';
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    alert('감사일기 게시판이 없습니다.', G5_BBS_URL.'/gratitude.php');
}

// 글쓰기 권한 체크
if ($member['mb_level'] < $board['bo_write_level']) {
    if ($member['mb_id'])
        alert('글을 쓸 권한이 없습니다.', G5_BBS_URL.'/gratitude.php');
    else
        alert('로그인 후 이용해 주세요.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/gratitude_write.php'));
}

// 수정 모드
$w = '';
$wr_id = 0;
$content = '';
$write_table = $g5['write_prefix'] . $bo_table;

if (isset($_GET['w']) && $_GET['w'] == 'u' && isset($_GET['wr_id'])) {
    $w = 'u';
    $wr_id = (int)$_GET['wr_id'];

    $write = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$wr_id}'");

    if (!$write) {
        alert('존재하지 않는 게시물입니다.', G5_BBS_URL.'/gratitude.php');
    }

    // 수정 권한 체크
    if ($member['mb_id'] != $write['mb_id'] && !$is_admin) {
        alert('수정 권한이 없습니다.', G5_BBS_URL.'/gratitude.php');
    }

    $content = $write['wr_content'];
}

$g5['title'] = $w == 'u' ? '감사일기 수정' : '감사일기 쓰기';

// 프로필 이미지
$profile_photo = '';
if ($is_member) {
    $profile_path = G5_DATA_PATH.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif';
    if (file_exists($profile_path)) {
        $profile_photo = G5_DATA_URL.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif';
    }
}

$member_name = $member['mb_name'] ? $member['mb_name'] : ($member['mb_nick'] ? $member['mb_nick'] : $member['mb_id']);

// 오늘 날짜
$today_date = date('Y년 m월 d일');
$day_of_week = array('일', '월', '화', '수', '목', '금', '토');
$today_dow = $day_of_week[date('w')];

// 감사 프롬프트 (랜덤)
$prompts = array(
    '오늘 하루 감사한 일은 무엇인가요?',
    '오늘 받은 작은 축복을 기록해보세요',
    '감사한 마음을 글로 표현해보세요',
    '오늘 행복했던 순간을 적어보세요',
    '감사할 일을 찾으면 더 많이 보여요',
    '작은 것에 감사하면 큰 것이 옵니다',
);
$random_prompt = $prompts[array_rand($prompts)];
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
    <script src="<?php echo G5_JS_URL; ?>/jquery-1.12.4.min.js"></script>
    <script>
        var g5_bbs_url = "<?php echo G5_BBS_URL; ?>";
        var g5_is_member = "<?php echo $is_member ? '1' : ''; ?>";
        var g5_is_admin = "<?php echo $is_admin ? '1' : ''; ?>";
    </script>
    <script src="<?php echo G5_JS_URL; ?>/common.js"></script>
    <style>
        ::-webkit-scrollbar { display: none; }
        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #FDF8F3;
        }
        textarea {
            resize: none;
        }
        textarea::placeholder {
            color: #C4B5A8;
        }
        .char-counter {
            transition: color 0.3s;
        }
        .char-counter.warning {
            color: #E8A598;
        }
        .char-counter.danger {
            color: #C44569;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'warm-cream': '#FDF8F3',
                        'soft-peach': '#FEF3E8',
                        'gentle-brown': '#8B7355',
                        'warm-pink': '#E8A598',
                        'deep-rose': '#C44569',
                        'muted-sage': '#A8B5A0'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-warm-cream min-h-screen">

<!-- 헤더 -->
<header class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-peach shadow-sm">
    <div class="max-w-2xl mx-auto flex items-center justify-between px-4 py-3">
        <a href="<?php echo G5_BBS_URL; ?>/gratitude.php" class="w-10 h-10 flex items-center justify-center -ml-2">
            <i class="fa-solid fa-xmark text-gentle-brown text-xl"></i>
        </a>
        <h1 class="text-base font-bold text-gentle-brown"><?php echo $w == 'u' ? '감사일기 수정' : '감사일기'; ?></h1>
        <button type="button" id="btn_submit" onclick="submitDiary()" class="px-4 py-2 bg-gradient-to-r from-warm-pink to-deep-rose text-white text-sm font-semibold rounded-full shadow-md hover:shadow-lg transition-shadow">
            완료
        </button>
    </div>
</header>

<main class="pt-20 pb-8 max-w-2xl mx-auto px-4">

    <!-- 날짜 카드 -->
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-soft-peach/50 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-gradient-to-br from-warm-pink to-deep-rose rounded-xl flex items-center justify-center shadow-md float-animation">
                <i class="fa-solid fa-heart text-white text-lg"></i>
            </div>
            <div>
                <p class="text-lg font-bold text-gentle-brown"><?php echo $today_date; ?></p>
                <p class="text-sm text-gentle-brown/60"><?php echo $today_dow; ?>요일의 감사</p>
            </div>
        </div>
    </div>

    <!-- 작성 폼 -->
    <form name="fwrite" id="fwrite" action="<?php echo G5_BBS_URL; ?>/write_update.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="uid" value="<?php echo get_uniqid(); ?>">
        <input type="hidden" name="w" value="<?php echo $w ?>">
        <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
        <input type="hidden" name="wr_id" value="<?php echo $wr_id ?>">
        <input type="hidden" name="token" value="" id="token">
        <input type="hidden" name="html" value="html1">
        <input type="hidden" name="wr_subject" value="<?php echo $today_date; ?> 감사일기">

        <div class="bg-white rounded-3xl shadow-sm border border-soft-peach/50 overflow-hidden">
            <!-- 프로필 영역 -->
            <div class="px-5 py-4 border-b border-soft-peach/30">
                <div class="flex items-center gap-3">
                    <?php if ($profile_photo) { ?>
                    <img src="<?php echo $profile_photo; ?>" alt="<?php echo $member_name; ?>" class="w-12 h-12 rounded-full object-cover border-2 border-soft-peach">
                    <?php } else { ?>
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-warm-pink to-deep-rose flex items-center justify-center border-2 border-soft-peach">
                        <span class="text-white font-bold text-lg"><?php echo mb_substr($member_name, 0, 1, 'UTF-8'); ?></span>
                    </div>
                    <?php } ?>
                    <div>
                        <p class="font-semibold text-gentle-brown"><?php echo $member_name; ?></p>
                        <p class="text-xs text-gentle-brown/50">감사일기를 기록해요</p>
                    </div>
                </div>
            </div>

            <!-- 텍스트 입력 영역 -->
            <div class="px-5 py-4">
                <textarea
                    name="wr_content"
                    id="wr_content"
                    class="w-full min-h-[250px] border-none focus:outline-none focus:ring-0 text-gentle-brown text-base leading-relaxed placeholder-gentle-brown/40"
                    placeholder="<?php echo $random_prompt; ?>"
                    maxlength="2000"
                    oninput="updateCharCount()"
                ><?php echo $content; ?></textarea>
            </div>

            <!-- 하단 정보 -->
            <div class="px-5 py-3 bg-warm-cream/50 border-t border-soft-peach/30">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-lightbulb text-warm-pink"></i>
                        <span class="text-xs text-gentle-brown/60">감사하는 마음은 행복을 부릅니다</span>
                    </div>
                    <span class="text-xs char-counter" id="charCounter">
                        <span id="currentChars">0</span>/2000
                    </span>
                </div>
            </div>
        </div>

        <!-- 감사 힌트 -->
        <div class="mt-6 bg-white/80 rounded-2xl p-4 border border-soft-peach/30">
            <p class="text-sm text-gentle-brown/70 mb-3 font-medium">
                <i class="fa-solid fa-sparkles text-warm-pink mr-1"></i>
                이런 것도 감사할 수 있어요
            </p>
            <div class="flex flex-wrap gap-2">
                <?php
                $hints = array('건강', '가족', '친구', '맛있는 음식', '좋은 날씨', '새로운 배움', '작은 친절', '따뜻한 햇살');
                foreach ($hints as $hint) {
                ?>
                <button type="button" onclick="insertHint('<?php echo $hint; ?>')" class="px-3 py-1.5 bg-soft-peach/70 text-gentle-brown text-sm rounded-full hover:bg-warm-pink/30 transition-colors">
                    <?php echo $hint; ?>
                </button>
                <?php } ?>
            </div>
        </div>

    </form>

</main>

<script>
// 글자 수 업데이트
function updateCharCount() {
    const textarea = document.getElementById('wr_content');
    const counter = document.getElementById('charCounter');
    const currentChars = document.getElementById('currentChars');
    const length = textarea.value.length;

    currentChars.textContent = length;

    counter.classList.remove('warning', 'danger');
    if (length > 1800) {
        counter.classList.add('danger');
    } else if (length > 1500) {
        counter.classList.add('warning');
    }
}

// 힌트 삽입
function insertHint(hint) {
    const textarea = document.getElementById('wr_content');
    const startPos = textarea.selectionStart;
    const endPos = textarea.selectionEnd;
    const currentValue = textarea.value;

    // 커서 위치에 삽입
    const prefix = currentValue ? (currentValue.endsWith('\n') || currentValue.endsWith(' ') ? '' : ' ') : '';
    const insertText = prefix + hint + '에 감사합니다. ';

    textarea.value = currentValue.substring(0, startPos) + insertText + currentValue.substring(endPos);
    textarea.selectionStart = textarea.selectionEnd = startPos + insertText.length;
    textarea.focus();

    updateCharCount();
}

// 폼 제출
function submitDiary() {
    const form = document.getElementById('fwrite');
    const content = document.getElementById('wr_content');

    if (!content.value.trim()) {
        alert('감사한 내용을 입력해주세요.');
        content.focus();
        return false;
    }

    // 토큰 생성
    const bo_table = form.bo_table.value;
    if (bo_table && typeof get_write_token === 'function') {
        const token = get_write_token(bo_table);
        if (token) {
            form.token.value = token;
        } else {
            alert('토큰 생성에 실패했습니다. 다시 시도해주세요.');
            return false;
        }
    } else {
        alert('오류: 토큰 생성 함수를 찾을 수 없습니다.');
        return false;
    }

    // 버튼 비활성화
    const btn = document.getElementById('btn_submit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    form.submit();
}

// 초기화
document.addEventListener('DOMContentLoaded', function() {
    updateCharCount();

    // 자동 포커스
    const textarea = document.getElementById('wr_content');
    if (!textarea.value) {
        textarea.focus();
    }
});

// 뒤로가기 경고
window.addEventListener('beforeunload', function(e) {
    const content = document.getElementById('wr_content').value.trim();
    if (content && !document.getElementById('btn_submit').disabled) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>

</body>
</html>
