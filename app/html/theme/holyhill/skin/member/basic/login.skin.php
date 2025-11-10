<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css">', 0);
?>

<!-- 로그인 시작 { -->
<style>
    ::-webkit-scrollbar { display: none;}
    body {
        font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
        background-color: #EEF3F8 !important;
    }
    .login-gradient {
        background: linear-gradient(135deg, #E8E2F7 0%, #B19CD9 50%, #6B46C1 100%);
    }
    .shadow-warm {
        box-shadow: 0 4px 20px rgba(177, 156, 217, 0.15);
    }
    .input-focus:focus {
        border-color: #B19CD9 !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(177, 156, 217, 0.1) !important;
    }
    .btn-gradient {
        background: linear-gradient(to right, #B19CD9, #6B46C1);
    }
    .btn-gradient:hover {
        box-shadow: 0 10px 25px rgba(177, 156, 217, 0.3);
    }
</style>

<main id="login-main" class="min-h-screen flex flex-col">

    <!-- 히어로 섹션 -->
    <section id="hero-section" class="login-gradient h-[400px] flex flex-col items-center justify-center px-6 relative overflow-hidden">
        <div class="absolute top-10 left-10 w-20 h-20 bg-white/10 rounded-full blur-xl"></div>
        <div class="absolute bottom-20 right-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>

        <div class="relative z-10 text-center">
            <div class="w-20 h-20 bg-white/90 rounded-3xl mx-auto mb-6 flex items-center justify-center shadow-warm">
                <i class="fa-solid fa-church text-4xl" style="color: #6B46C1;"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">성산교회</h1>
            <p class="text-white/90 text-sm">함께 성장하는 신앙 공동체</p>
        </div>
    </section>

    <!-- 로그인 폼 섹션 -->
    <section id="login-form-section" class="flex-1 -mt-8 rounded-t-3xl px-6 pt-8 pb-12" style="background-color: #EEF3F8;">
        <div class="max-w-md mx-auto">
            <h2 class="text-2xl font-semibold mb-2" style="color: #6B705C;">환영합니다</h2>
            <p class="text-sm text-gray-500 mb-8">하나님의 사랑 안에서 함께해요</p>

            <form name="flogin" action="<?php echo $login_action_url ?>" onsubmit="return flogin_submit(this);" method="post" class="space-y-4">
                <input type="hidden" name="url" value="<?php echo $login_url ?>">

                <!-- 아이디 입력 -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: #6B705C;">아이디</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fa-solid fa-envelope text-gray-400"></i>
                        </div>
                        <input type="text" name="mb_id" id="login_id" required
                               placeholder="아이디를 입력하세요"
                               class="input-focus w-full pl-12 pr-4 py-3 bg-white border border-gray-200 rounded-xl transition-all"
                               style="color: #6B705C;">
                    </div>
                </div>

                <!-- 비밀번호 입력 -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: #6B705C;">비밀번호</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" name="mb_password" id="login_pw" required
                               placeholder="비밀번호를 입력하세요"
                               class="input-focus w-full pl-12 pr-12 py-3 bg-white border border-gray-200 rounded-xl transition-all"
                               style="color: #6B705C;">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center">
                            <i id="password-toggle-icon" class="fa-solid fa-eye text-gray-400"></i>
                        </button>
                    </div>
                </div>

                <!-- 로그인 옵션 -->
                <div class="flex items-center justify-between pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="auto_login" id="login_auto_login"
                               class="w-4 h-4 rounded border-gray-300"
                               style="accent-color: #B19CD9;">
                        <span class="text-sm text-gray-600">로그인 유지</span>
                    </label>
                    <a href="<?php echo G5_BBS_URL ?>/password_lost.php" class="text-sm font-medium" style="color: #B19CD9;">비밀번호 찾기</a>
                </div>

                <!-- 로그인 버튼 -->
                <button type="submit" class="btn-gradient w-full text-white py-3.5 rounded-xl font-medium shadow-warm transition-all mt-6">
                    로그인
                </button>
            </form>

            <!-- 구분선 -->
            <div class="relative my-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-4 text-gray-500" style="background-color: #EEF3F8;">또는</span>
                </div>
            </div>

            <!-- 소셜 로그인 -->
            <?php
            $social_skin_path = get_social_skin_path();
            if($social_skin_path && file_exists($social_skin_path.'/social_login.skin.php')) {
            ?>
            <div id="social-login" class="space-y-3">
                <?php @include_once($social_skin_path.'/social_login.skin.php'); ?>
            </div>
            <?php } else { ?>
            <div id="social-login" class="space-y-3">
                <button class="w-full flex items-center justify-center gap-3 bg-white border border-gray-200 py-3 rounded-xl font-medium shadow-warm hover:shadow-xl transition-all" style="color: #6B705C;">
                    <i class="fa-brands fa-google text-xl"></i>
                    <span>Google로 계속하기</span>
                </button>

                <button class="w-full flex items-center justify-center gap-3 py-3 rounded-xl font-medium shadow-warm hover:shadow-xl transition-all text-gray-800" style="background-color: #FEE500;">
                    <i class="fa-solid fa-comment text-xl"></i>
                    <span>카카오로 계속하기</span>
                </button>

                <button class="w-full flex items-center justify-center gap-3 text-white py-3 rounded-xl font-medium shadow-warm hover:shadow-xl transition-all" style="background-color: #03C75A;">
                    <i class="fa-brands fa-line text-xl"></i>
                    <span>네이버로 계속하기</span>
                </button>
            </div>
            <?php } ?>

            <!-- 회원가입 링크 -->
            <div class="text-center mt-8">
                <p class="text-sm text-gray-600">
                    아직 회원이 아니신가요?
                    <a href="<?php echo G5_BBS_URL ?>/register.php" class="font-semibold ml-1" style="color: #B19CD9;">회원가입</a>
                </p>
            </div>

            <!-- 약관 정보 -->
            <div class="mt-12 pt-6 border-t border-gray-200">
                <div class="flex items-center justify-center gap-2 text-gray-400 mb-4">
                    <i class="fa-solid fa-shield-alt text-sm"></i>
                    <span class="text-xs">안전한 로그인</span>
                </div>
                <p class="text-xs text-center text-gray-400 leading-relaxed">
                    로그인하시면 <span style="color: #B19CD9;">이용약관</span> 및 <span style="color: #B19CD9;">개인정보처리방침</span>에<br>동의하는 것으로 간주됩니다
                </p>
            </div>
        </div>
    </section>

</main>

<script>
// 비밀번호 보기/숨기기 토글
function togglePassword() {
    const passwordInput = document.getElementById('login_pw');
    const toggleIcon = document.getElementById('password-toggle-icon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// 자동 로그인 확인
jQuery(function($){
    $("#login_auto_login").click(function(){
        if (this.checked) {
            this.checked = confirm("자동로그인을 사용하시면 다음부터 회원아이디와 비밀번호를 입력하실 필요가 없습니다.\n\n공공장소에서는 개인정보가 유출될 수 있으니 사용을 자제하여 주십시오.\n\n자동로그인을 사용하시겠습니까?");
        }
    });
});

// 로그인 폼 제출
function flogin_submit(f)
{
    if( $( document.body ).triggerHandler( 'login_sumit', [f, 'flogin'] ) !== false ){
        return true;
    }
    return false;
}
</script>
<!-- } 로그인 끝 -->
