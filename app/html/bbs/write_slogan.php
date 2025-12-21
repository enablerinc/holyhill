<?php
include_once('./_common.php');

// 관리자만 접근 가능
if (!$is_admin) {
    alert('관리자만 표어를 등록할 수 있습니다.', G5_BBS_URL.'/index.php');
}

$bo_table = 'slogan';
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    alert("'slogan' 게시판이 존재하지 않습니다.\n\n관리자 페이지에서 'slogan' 게시판을 먼저 생성해주세요.", G5_BBS_URL.'/index.php');
}

$write_table = $g5['write_prefix'] . $bo_table;

// 수정 모드 체크
$w = isset($_GET['w']) ? $_GET['w'] : '';
$wr_id = isset($_GET['wr_id']) ? (int)$_GET['wr_id'] : 0;

$write = array();
if ($wr_id) {
    $write = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$wr_id}'");
    if (!$write) {
        alert('존재하지 않는 글입니다.', G5_BBS_URL.'/index.php');
    }
    $w = 'u';
}

$g5['title'] = $w == 'u' ? '표어 수정' : '표어 등록';
$subject = isset($write['wr_subject']) ? get_text($write['wr_subject']) : '';
$content = isset($write['wr_content']) ? get_text($write['wr_content']) : '';
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
        ::-webkit-scrollbar { display: none; }
        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #EEF3F8;
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

<header class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="<?php echo G5_BBS_URL; ?>/index.php" class="text-grace-green hover:text-gray-900">
            <i class="fa-solid fa-xmark text-xl"></i>
        </a>
        <h1 class="text-lg font-semibold text-grace-green"><?php echo $g5['title']; ?></h1>
        <div class="w-6"></div>
    </div>
</header>

<main class="pt-20 pb-8 px-4 max-w-lg mx-auto">
    <form id="slogan-form" action="<?php echo G5_BBS_URL; ?>/write_slogan_update.php" method="post">
        <input type="hidden" name="w" value="<?php echo $w; ?>">
        <input type="hidden" name="wr_id" value="<?php echo $wr_id; ?>">
        <input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">

        <!-- 미리보기 -->
        <div class="bg-gradient-to-r from-amber-50 to-yellow-50 rounded-2xl p-4 shadow-md border border-amber-200 mb-6">
            <div class="text-center">
                <i class="fa-solid fa-quote-left text-amber-400 text-lg mb-2"></i>
                <p id="preview-title" class="text-sm text-amber-600 mb-1"><?php echo $subject ? $subject : '2026년 표어'; ?></p>
                <p id="preview-content" class="text-lg font-bold text-amber-800 leading-relaxed">
                    "<?php echo $content ? $content : '표어 문구를 입력하세요'; ?>"
                </p>
                <i class="fa-solid fa-quote-right text-amber-400 text-lg mt-2"></i>
            </div>
        </div>

        <!-- 제목 입력 -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-tag text-amber-500 mr-1"></i> 제목
            </label>
            <input type="text" name="wr_subject" id="wr_subject" value="<?php echo $subject; ?>"
                   placeholder="예: 2026년 표어"
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-gray-800 focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                   required>
            <p class="text-xs text-gray-500 mt-1">표어의 제목을 입력하세요 (예: 2026년 표어)</p>
        </div>

        <!-- 문구 입력 -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-quote-right text-amber-500 mr-1"></i> 표어 문구
            </label>
            <textarea name="wr_content" id="wr_content" rows="3"
                      placeholder="예: 예수님 따라!! 말씀 따라!!"
                      class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-gray-800 focus:ring-2 focus:ring-amber-400 focus:border-transparent resize-none"
                      required><?php echo $content; ?></textarea>
            <p class="text-xs text-gray-500 mt-1">표어 문구를 입력하세요</p>
        </div>

        <!-- 등록 버튼 -->
        <button type="submit" class="w-full py-4 bg-amber-500 text-white font-semibold rounded-xl hover:bg-amber-600 transition-colors shadow-lg">
            <i class="fa-solid fa-check mr-2"></i>
            <?php echo $w == 'u' ? '표어 수정' : '표어 등록'; ?>
        </button>
    </form>
</main>

<script>
// 실시간 미리보기
document.getElementById('wr_subject').addEventListener('input', function() {
    document.getElementById('preview-title').textContent = this.value || '2026년 표어';
});

document.getElementById('wr_content').addEventListener('input', function() {
    document.getElementById('preview-content').textContent = '"' + (this.value || '표어 문구를 입력하세요') + '"';
});
</script>

</body>
</html>
