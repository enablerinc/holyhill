<?php
if (!defined('_GNUBOARD_')) exit;

// 기본 head 정보
include_once(G5_PATH.'/head.sub.php');
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="format-detection" content="telephone=no">
    
    <title><?php echo $g5['title']; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Pretendard Font -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
    
    <style>
        ::-webkit-scrollbar {
            display: none;
        }
        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #F3F4F6;
            margin: 0;
            padding: 0;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #E8E2F7 0%, #F8F6FF 100%);
        }
        /* 모바일에서 콘텐츠가 header/footer에 가려지지 않도록 */
        #main-content {
            padding-top: 64px;  /* header 높이 */
            padding-bottom: 80px; /* footer 높이 */
            min-height: 100vh;
        }
        /* 모바일 안전 영역 확보 */
        @supports (padding: max(0px)) {
            #main-content {
                padding-top: max(64px, env(safe-area-inset-top));
                padding-bottom: max(80px, env(safe-area-inset-bottom));
            }
        }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'holy-purple': '#9B7EDE',
                        'holy-pink': '#E8B4D9',
                    }
                }
            }
        }
    </script>
</head>
<body>

<!-- 상단 네비게이션 -->
<header id="header" class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-gray-200">
    <div class="max-w-2xl mx-auto flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
            <a href="/" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-cross text-white text-sm"></i>
                </div>
                <h1 class="text-lg font-semibold text-gray-800">성산교회</h1>
            </a>
        </div>
        <div class="flex items-center gap-4">
            <?php if ($is_member) { ?>
            <i class="fa-regular fa-bell text-gray-700 text-lg cursor-pointer"></i>
            <?php } else { ?>
            <a href="<?php echo G5_BBS_URL; ?>/login.php" class="text-sm text-purple-600 font-medium">
                로그인
            </a>
            <?php } ?>
        </div>
    </div>
</header>

<!-- 메인 컨텐츠 -->
<main id="main-content" class="max-w-2xl mx-auto">
