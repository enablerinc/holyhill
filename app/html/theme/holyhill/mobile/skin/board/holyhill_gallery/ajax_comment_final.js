
<script>
// AJAX 댓글 제출 - 최적화된 포커스
(function() {
    const form = document.getElementById('commentForm');
    const input = document.getElementById('commentInput');
    const tokenInput = document.querySelector('input[name="token"]');
    const commentList = document.getElementById('comment-list');
    
    if (!form || !input) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const content = input.value.trim();
        if (!content) {
            input.focus();
            return;
        }
        
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalContent = submitBtn.innerHTML;
        
        // 버튼 비활성화
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        
        fetch('<?php echo G5_BBS_URL; ?>/comment_write_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 토큰 업데이트
                if (data.new_token && tokenInput) {
                    tokenInput.value = data.new_token;
                }
                
                // 새 댓글 추가
                const newCommentHTML = `
                    <div class="flex gap-3 mb-3" style="animation: slideIn 0.3s ease-out;">
                        <img src="${data.comment.photo}" class="w-8 h-8 rounded-full">
                        <div class="flex-1 bg-gray-50 rounded-2xl px-3 py-2" style="background: rgba(139, 92, 246, 0.1); transition: background 2s;">
                            <div class="font-semibold text-xs mb-1">${data.comment.nick}</div>
                            <div class="text-sm">${data.comment.content}</div>
                        </div>
                    </div>
                `;
                
                if (commentList) {
                    const emptyMessage = commentList.querySelector('.text-center.text-gray-500');
                    if (emptyMessage) emptyMessage.remove();
                    
                    commentList.insertAdjacentHTML('beforeend', newCommentHTML);
                    
                    // 마지막 댓글로 스크롤
                    const allComments = commentList.querySelectorAll('.flex.gap-3.mb-3');
                    const lastComment = allComments[allComments.length - 1];
                    
                    // 스크롤 완료 후 하이라이트 제거
                    lastComment.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => {
                        lastComment.querySelector('.flex-1').style.background = '';
                    }, 2000);
                }
                
                // 댓글 개수 업데이트
                const commentCountH3 = document.querySelector('h3.font-semibold.mb-4');
                if (commentCountH3) {
                    const match = commentCountH3.textContent.match(/\d+/);
                    const currentCount = match ? parseInt(match[0]) : 0;
                    commentCountH3.textContent = '댓글 ' + (currentCount + 1) + '개';
                }
                
                // ✅ 입력창 초기화 및 즉시 포커스
                input.value = '';
                input.focus();
                
            } else {
                alert(data.message || '댓글 작성 중 오류가 발생했습니다.');
                input.focus();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('댓글 작성 중 오류가 발생했습니다.');
            input.focus();
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalContent;
        });
    });
    
    // ✅ 페이지 로드 시 입력창 포커스 (선택사항)
    // input.focus();
})();
</script>

<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
