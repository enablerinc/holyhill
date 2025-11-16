<?php
include_once('./_common.php');

$g5['title'] = '명예의 전당';

// 현재 년도와 월 설정
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

// 샘플 데이터 (나중에 실제 데이터베이스 쿼리로 대체 예정)
$top_members = array(
    array(
        'rank' => 1,
        'name' => '김은혜',
        'department' => '청년부',
        'points' => 347,
        'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-1.jpg',
        'tags' => array('#QT25회', '#기도30회')
    ),
    array(
        'rank' => 2,
        'name' => '박성민',
        'department' => '장년부',
        'points' => 298,
        'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-2.jpg',
        'tags' => array('#봉사22회')
    ),
    array(
        'rank' => 3,
        'name' => '이소망',
        'department' => '청년부',
        'points' => 276,
        'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-5.jpg',
        'tags' => array('#찬양팀')
    )
);

$excellent_members = array(
    array(
        'rank' => 4,
        'name' => '최다윗',
        'department' => '청년부',
        'points' => 254,
        'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-3.jpg',
        'tags' => array('#QT18회', '#셀모임'),
        'growth' => '+12%'
    ),
    array(
        'rank' => 5,
        'name' => '한사랑',
        'department' => '청년부',
        'points' => 238,
        'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-6.jpg',
        'tags' => array('#기도25회', '#나눔글'),
        'streak' => '3개월 연속',
        'hot' => true
    ),
    array(
        'rank' => 6,
        'name' => '정요한',
        'department' => '장년부',
        'points' => 225,
        'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-8.jpg',
        'tags' => array('#예배참석', '#말씀나눔'),
        'growth' => '+8%'
    ),
    array(
        'rank' => 7,
        'name' => '강베드로',
        'department' => '장년부',
        'points' => 218,
        'avatar' => 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-9.jpg',
        'tags' => array('#봉사활동', '#교제')
    )
);

$total_excellent = 12; // 총 우수자 수

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
        ::-webkit-scrollbar { display: none;}
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; }
        .golden-gradient { background: linear-gradient(135deg, #C4A6E8, #B19CD9, #9B7AC7); }
        .silver-gradient { background: linear-gradient(135deg, #E8E2F7, #D1C7E3, #B19CD9); }
        .bronze-gradient { background: linear-gradient(135deg, #D1C7E3, #B19CD9, #8B5CF6); }
        .shadow-warm { box-shadow: 0 4px 20px rgba(177, 156, 217, 0.15); }
        .shadow-divine { box-shadow: 0 8px 32px rgba(196, 166, 232, 0.25); }
        .crown-shadow { box-shadow: 0 0 30px rgba(196, 166, 232, 0.4); }
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
                        'deep-purple': '#6B46C1',
                        'divine-lilac': '#C4A6E8'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#EEF3F8]">

<header id="header" class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
            <button onclick="goBack()" class="w-8 h-8 flex items-center justify-center">
                <i class="fa-solid fa-arrow-left text-grace-green text-lg"></i>
            </button>
            <div class="w-8 h-8 bg-gradient-to-br from-lilac to-deep-purple rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-cross text-white text-sm"></i>
            </div>
            <h1 class="text-lg font-semibold text-grace-green">명예의 전당</h1>
        </div>
        <i class="fa-solid fa-trophy text-divine-lilac text-xl"></i>
    </div>
</header>

<main id="main-content" class="pt-16 pb-20">

    <section id="month-selector" class="px-4 py-4 bg-white border-b border-soft-lavender">
        <div class="flex items-center justify-between">
            <button onclick="changeMonth(-1)" class="w-10 h-10 flex items-center justify-center rounded-full bg-soft-lavender">
                <i class="fa-solid fa-chevron-left text-lilac"></i>
            </button>

            <div class="flex items-center gap-2">
                <i class="fa-solid fa-calendar text-lilac"></i>
                <h2 class="text-lg font-semibold text-grace-green"><?php echo $current_year; ?>년 <?php echo $current_month; ?>월</h2>
                <i class="fa-solid fa-chevron-down text-lilac text-sm"></i>
            </div>

            <button onclick="changeMonth(1)" class="w-10 h-10 flex items-center justify-center rounded-full bg-soft-lavender">
                <i class="fa-solid fa-chevron-right text-lilac"></i>
            </button>
        </div>
    </section>

    <section id="blessing-quote" class="mx-4 my-4 bg-gradient-to-r from-divine-lilac/20 to-soft-lavender rounded-2xl p-4 border border-divine-lilac/30">
        <div class="text-center">
            <i class="fa-solid fa-quote-left text-divine-lilac text-lg mb-2"></i>
            <p class="text-sm font-medium text-deep-purple leading-relaxed mb-2">
                "충성된 종아 잘하였도다 네가 적은 일에 충성하였으니<br>많은 것을 네게 맡기리라"
            </p>
            <p class="text-xs text-lilac">마태복음 25:21</p>
        </div>
    </section>

    <section id="top-three-section" class="px-4 mb-6">
        <div class="flex items-center gap-2 mb-4">
            <i class="fa-solid fa-crown text-divine-lilac text-lg"></i>
            <h3 class="text-lg font-semibold text-grace-green">이달의 베스트 성산인</h3>
        </div>

        <div class="flex gap-3 overflow-x-auto pb-2">
            <?php foreach ($top_members as $idx => $member): ?>
            <?php
                $border_class = '';
                $gradient_class = '';
                $badge_color = '';

                if ($member['rank'] == 1) {
                    $border_class = 'border-2 border-divine-lilac/30';
                    $gradient_class = 'golden-gradient';
                    $badge_color = 'bg-divine-lilac';
                    $shadow_class = 'shadow-divine';
                    $crown_class = 'crown-shadow';
                } elseif ($member['rank'] == 2) {
                    $border_class = 'border border-gray-200';
                    $gradient_class = 'silver-gradient';
                    $badge_color = 'bg-lilac';
                    $shadow_class = 'shadow-warm';
                    $crown_class = '';
                } else {
                    $border_class = 'border border-lilac/20';
                    $gradient_class = 'bronze-gradient';
                    $badge_color = 'bg-deep-purple';
                    $shadow_class = 'shadow-warm';
                    $crown_class = '';
                }

                $width_class = $member['rank'] == 1 ? 'min-w-[140px]' : 'min-w-[130px]';
                $img_size = $member['rank'] == 1 ? 'w-20 h-20' : 'w-18 h-18';
            ?>

            <div class="<?php echo $width_class; ?> bg-white rounded-2xl p-4 <?php echo $shadow_class; ?> <?php echo $border_class; ?>">
                <div class="text-center">
                    <div class="relative mb-3">
                        <div class="<?php echo $img_size; ?> <?php echo $gradient_class; ?> rounded-full p-1 mx-auto <?php echo $crown_class; ?>">
                            <img src="<?php echo $member['avatar']; ?>" class="w-full h-full rounded-full object-cover">
                        </div>
                        <?php if ($member['rank'] == 1): ?>
                        <div class="absolute -top-2 left-1/2 transform -translate-x-1/2">
                            <i class="fa-solid fa-crown text-divine-lilac text-xl crown-shadow"></i>
                        </div>
                        <?php endif; ?>
                        <div class="absolute -bottom-1 -right-1 <?php echo $member['rank'] == 1 ? 'w-6 h-6' : 'w-5 h-5'; ?> <?php echo $badge_color; ?> rounded-full flex items-center justify-center">
                            <span class="text-white text-xs font-bold"><?php echo $member['rank']; ?></span>
                        </div>
                    </div>
                    <h4 class="font-semibold text-grace-green text-sm mb-1"><?php echo $member['name']; ?></h4>
                    <p class="text-xs text-gray-500 mb-2"><?php echo $member['department']; ?></p>
                    <div class="flex items-center justify-center gap-1 mb-2">
                        <i class="fa-solid fa-cross text-<?php echo $member['rank'] == 1 ? 'divine-lilac' : ($member['rank'] == 2 ? 'lilac' : 'deep-purple'); ?> text-xs"></i>
                        <span class="text-sm font-bold text-<?php echo $member['rank'] == 1 ? 'divine-lilac' : ($member['rank'] == 2 ? 'lilac' : 'deep-purple'); ?>"><?php echo $member['points']; ?>점</span>
                    </div>
                    <div class="flex flex-wrap gap-1 justify-center">
                        <?php foreach ($member['tags'] as $tag): ?>
                        <span class="text-xs bg-<?php echo $member['rank'] == 1 ? 'divine-lilac' : ($member['rank'] == 2 ? 'soft-lavender' : 'deep-purple'); ?>/20 text-deep-purple px-2 py-1 rounded-full"><?php echo $tag; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="hall-of-faith" class="px-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-hands-praying text-lilac text-lg"></i>
                <h3 class="text-lg font-semibold text-grace-green">우수 성산인</h3>
                <span class="text-xs bg-lilac/20 text-deep-purple px-2 py-1 rounded-full">200점 이상</span>
            </div>
            <span class="text-xs text-gray-500">총 <?php echo $total_excellent; ?>명</span>
        </div>

        <div class="space-y-3">
            <?php foreach ($excellent_members as $member): ?>
            <div class="bg-white rounded-xl p-4 shadow-warm flex items-center gap-4">
                <div class="relative">
                    <img src="<?php echo $member['avatar']; ?>" class="w-14 h-14 rounded-full object-cover">
                    <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-lilac rounded-full flex items-center justify-center">
                        <span class="text-white text-xs font-bold"><?php echo $member['rank']; ?></span>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <h4 class="font-semibold text-grace-green"><?php echo $member['name']; ?></h4>
                        <span class="text-xs bg-soft-lavender text-deep-purple px-2 py-1 rounded-full"><?php echo $member['department']; ?></span>
                        <?php if (isset($member['hot']) && $member['hot']): ?>
                        <div class="w-4 h-4 bg-divine-lilac rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-fire text-white text-xs"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-1 mb-2">
                        <i class="fa-solid fa-cross text-lilac text-xs"></i>
                        <span class="text-sm font-semibold text-lilac"><?php echo $member['points']; ?>점</span>
                        <?php if (isset($member['growth'])): ?>
                        <span class="text-xs text-green-600 ml-2">↑<?php echo $member['growth']; ?> 증가</span>
                        <?php endif; ?>
                        <?php if (isset($member['streak'])): ?>
                        <span class="text-xs bg-divine-lilac/20 text-divine-lilac px-2 py-1 rounded-full"><?php echo $member['streak']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-1">
                        <?php foreach ($member['tags'] as $tag): ?>
                        <span class="text-xs bg-soft-lavender text-deep-purple px-2 py-1 rounded-full"><?php echo $tag; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="w-8 h-8 bg-lilac/20 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-praying-hands text-lilac text-sm"></i>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <button class="w-full mt-4 py-3 bg-lilac/10 text-deep-purple font-medium rounded-xl border border-lilac/20">
            모든 우수자 보기 (<?php echo $total_excellent; ?>명)
        </button>
    </section>

    <section id="monthly-stats" class="mx-4 mt-6 bg-white rounded-2xl p-4 shadow-warm">
        <h3 class="text-lg font-semibold text-grace-green mb-4">이달의 활동 통계</h3>

        <div class="grid grid-cols-2 gap-4">
            <div class="text-center p-3 bg-[#EEF3F8] rounded-xl">
                <i class="fa-solid fa-praying-hands text-lilac text-xl mb-2"></i>
                <p class="text-sm text-grace-green">총 게시물 수</p>
                <p class="text-lg font-bold text-lilac">1,247회</p>
            </div>

            <div class="text-center p-3 bg-[#EEF3F8] rounded-xl">
                <i class="fa-solid fa-book-open text-lilac text-xl mb-2"></i>
                <p class="text-sm text-grace-green">총 댓글 수</p>
                <p class="text-lg font-bold text-lilac">856회</p>
            </div>

            <div class="text-center p-3 bg-[#EEF3F8] rounded-xl">
                <i class="fa-solid fa-heart text-lilac text-xl mb-2"></i>
                <p class="text-sm text-grace-green">아멘 총합</p>
                <p class="text-lg font-bold text-lilac">2,134개</p>
            </div>

            <div class="text-center p-3 bg-[#EEF3F8] rounded-xl">
                <i class="fa-solid fa-hands-helping text-lilac text-xl mb-2"></i>
                <p class="text-sm text-grace-green">회원 출석 수</p>
                <p class="text-lg font-bold text-lilac">142회</p>
            </div>
        </div>
    </section>

</main>

<?php include_once(G5_BBS_PATH.'/bottom_nav.php'); ?>

<script>
function goBack() {
    window.history.back();
}

function changeMonth(direction) {
    const currentYear = <?php echo $current_year; ?>;
    const currentMonth = <?php echo $current_month; ?>;

    let newYear = currentYear;
    let newMonth = currentMonth + direction;

    if (newMonth > 12) {
        newMonth = 1;
        newYear++;
    } else if (newMonth < 1) {
        newMonth = 12;
        newYear--;
    }

    window.location.href = '?year=' + newYear + '&month=' + newMonth;
}
</script>

</body>
</html>
