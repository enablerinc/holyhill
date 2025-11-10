<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
?>

<div class="min-h-screen flex items-center justify-center" style="background-color: #EEF3F8;">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-2xl shadow-warm p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #E8E2F7 0%, #B19CD9 50%, #6B46C1 100%);">
                    <i class="fa-solid fa-check text-2xl text-white"></i>
                </div>
                <h2 class="text-2xl font-bold" style="color: #6B705C;">로그인 성공</h2>
            </div>

            <div class="text-center text-gray-600 mb-6">
                <p>로그인에 성공했습니다.</p>
                <p class="text-sm mt-2">잠시 후 페이지로 이동합니다...</p>
            </div>

            <div class="flex justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: #B19CD9;"></div>
            </div>
        </div>
    </div>
</div>

<style>
    .shadow-warm {
        box-shadow: 0 4px 20px rgba(177, 156, 217, 0.15);
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }
</style>
