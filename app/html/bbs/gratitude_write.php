<?php
include_once(__DIR__.'/_common.php');

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

    // 세션에 수정 정보 저장 (write_update.php 에서 검증용)
    set_session('ss_bo_table', $bo_table);
    set_session('ss_wr_id', $wr_id);
} else {
    // 새 글 작성 시에도 세션 설정
    set_session('ss_bo_table', $bo_table);
    set_session('ss_wr_id', 0);
}

$g5['title'] = $w == 'u' ? '감사일기 수정' : '감사일기 쓰기';

// 프로필 이미지 - 캐시 버스팅 적용
$profile_photo = $is_member ? get_profile_image_url($member['mb_id']) : '';

$member_name = $member['mb_name'] ? $member['mb_name'] : ($member['mb_nick'] ? $member['mb_nick'] : $member['mb_id']);

// 오늘 날짜
$today_date = date('Y년 m월 d일');
$day_of_week = array('일', '월', '화', '수', '목', '금', '토');
$today_dow = $day_of_week[date('w')];

// 감사 프롬프트 (랜덤)
$prompts = array(
    '오늘 하루를 허락하신 하나님께 감사해요',
    '오늘 경험한 하나님의 은혜를 기록해보세요',
    '범사에 감사하는 마음을 표현해보세요',
    '오늘 하루 지켜주신 하나님께 감사해요',
    '작은 것에도 감사하면 더 큰 축복이 옵니다',
    '감사는 하나님을 기쁘시게 하는 기도입니다',
    '오늘 누린 은혜를 세어보세요',
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
            background-color: #EEF3F8;
        }
        textarea {
            resize: none;
        }
        textarea::placeholder {
            color: #9CA3AF;
        }
        .char-counter {
            transition: color 0.3s;
        }
        .char-counter.warning {
            color: #B19CD9;
        }
        .char-counter.danger {
            color: #6B46C1;
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
<body class="bg-warm-beige min-h-screen">

<!-- 헤더 -->
<header class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="max-w-2xl mx-auto flex items-center justify-between px-4 py-3">
        <a href="<?php echo G5_BBS_URL; ?>/gratitude.php" class="w-10 h-10 flex items-center justify-center -ml-2">
            <i class="fa-solid fa-xmark text-grace-green text-xl"></i>
        </a>
        <h1 class="text-base font-bold text-grace-green"><?php echo $w == 'u' ? '감사일기 수정' : '감사일기'; ?></h1>
        <button type="button" id="btn_submit" onclick="submitDiary()" class="px-4 py-2 bg-gradient-to-r from-lilac to-deep-purple text-white text-sm font-semibold rounded-full shadow-md hover:shadow-lg transition-shadow">
            완료
        </button>
    </div>
</header>

<main class="pt-20 pb-8 max-w-2xl mx-auto px-4">

    <!-- 날짜 카드 -->
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-soft-lavender/50 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-gradient-to-br from-lilac to-deep-purple rounded-xl flex items-center justify-center shadow-md float-animation">
                <i class="fa-solid fa-book text-white text-lg"></i>
            </div>
            <div>
                <p class="text-lg font-bold text-grace-green"><?php echo $today_date; ?></p>
                <p class="text-sm text-grace-green/60"><?php echo $today_dow; ?>요일의 감사</p>
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

        <div class="bg-white rounded-3xl shadow-sm border border-soft-lavender/50 overflow-hidden">
            <!-- 프로필 영역 -->
            <div class="px-5 py-4 border-b border-soft-lavender/30">
                <div class="flex items-center gap-3">
                    <?php if ($profile_photo) { ?>
                    <img src="<?php echo $profile_photo; ?>" alt="<?php echo $member_name; ?>" class="w-12 h-12 rounded-full object-cover border-2 border-soft-lavender">
                    <?php } else { ?>
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-lilac to-deep-purple flex items-center justify-center border-2 border-soft-lavender">
                        <span class="text-white font-bold text-lg"><?php echo mb_substr($member_name, 0, 1, 'UTF-8'); ?></span>
                    </div>
                    <?php } ?>
                    <div>
                        <p class="font-semibold text-grace-green"><?php echo $member_name; ?></p>
                        <p class="text-xs text-grace-green/50">감사일기를 기록해요</p>
                    </div>
                </div>
            </div>

            <!-- 텍스트 입력 영역 -->
            <div class="px-5 py-4">
                <textarea
                    name="wr_content"
                    id="wr_content"
                    class="w-full min-h-[250px] border-none focus:outline-none focus:ring-0 text-grace-green text-base leading-relaxed placeholder-grace-green/40"
                    placeholder="<?php echo $random_prompt; ?>"
                    maxlength="2000"
                    oninput="updateCharCount()"
                ><?php echo $content; ?></textarea>
            </div>

            <!-- 하단 정보 -->
            <div class="px-5 py-3 bg-warm-beige/50 border-t border-soft-lavender/30">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-lightbulb text-lilac"></i>
                        <span class="text-xs text-grace-green/60">감사하는 마음은 행복을 부릅니다</span>
                    </div>
                    <span class="text-xs char-counter" id="charCounter">
                        <span id="currentChars">0</span>/2000
                    </span>
                </div>
            </div>
        </div>

        <!-- 감사 힌트 -->
        <div class="mt-6 bg-white/80 rounded-2xl p-4 border border-soft-lavender/30">
            <p class="text-sm text-grace-green/70 mb-3 font-medium">
                <i class="fa-solid fa-sparkles text-lilac mr-1"></i>
                이런 것도 감사할 수 있어요
            </p>
            <div class="flex flex-wrap gap-2">
                <?php
                $hints = array(
                    // 일상의 감사
                    '오늘 하루',
                    '눈을 뜨게 하심',
                    '건강',
                    '가족',
                    // 신앙의 감사
                    '말씀',
                    '예배',
                    '기도',
                    '교회 가족',
                    // 식사와 일상
                    '맛있는 식사',
                    '좋은 날씨',
                    '무탈한 하루',
                    '편안한 잠자리',
                    // 관계와 은혜
                    '만남',
                    '작은 친절',
                    '용서',
                    '새로운 배움',
                );
                foreach ($hints as $hint) {
                ?>
                <button type="button" onclick="insertHint('<?php echo $hint; ?>')" class="px-3 py-1.5 bg-soft-lavender/70 text-grace-green text-sm rounded-full hover:bg-lilac/30 transition-colors">
                    <?php echo $hint; ?>
                </button>
                <?php } ?>
            </div>
        </div>

        <!-- 추가 감사 문장 제안 -->
        <div class="mt-4 bg-gradient-to-r from-soft-lavender/30 to-lilac/20 rounded-2xl p-4 border border-soft-lavender/30">
            <p class="text-sm text-grace-green/70 mb-3 font-medium">
                <i class="fa-regular fa-lightbulb text-lilac mr-1"></i>
                이렇게 시작해보세요
            </p>
            <div class="space-y-2">
                <?php
                $sentences = array(
                    '오늘 하루를 허락하신 하나님께 감사드립니다.',
                    '눈을 뜨고 새 아침을 맞이하게 하심에 감사합니다.',
                    '오늘도 무탈하게 하루를 보내게 하심에 감사합니다.',
                    '맛있는 밥을 먹을 수 있음에 감사합니다.',
                    '사랑하는 가족과 함께할 수 있어 감사합니다.',
                    '오늘 말씀을 통해 위로받아 감사합니다.',
                    '교회 가족들과 함께 예배드릴 수 있어 감사합니다.',
                    '잠자리에 들기 전, 오늘 하루도 지켜주심에 감사합니다.',
                );
                foreach ($sentences as $sentence) {
                ?>
                <button type="button" onclick="insertSentence('<?php echo addslashes($sentence); ?>')" class="block w-full text-left px-3 py-2 bg-white/70 text-grace-green/80 text-sm rounded-lg hover:bg-white transition-colors">
                    "<?php echo $sentence; ?>"
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

// 문장 삽입
function insertSentence(sentence) {
    const textarea = document.getElementById('wr_content');
    const currentValue = textarea.value;

    // 줄바꿈 후 추가 (기존 내용이 있으면)
    const prefix = currentValue ? (currentValue.endsWith('\n') ? '' : '\n') : '';
    const insertText = prefix + sentence + '\n';

    textarea.value = currentValue + insertText;
    textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
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

<?php echo html_end(); // 접속자 추적 ?>
</body>
</html>
