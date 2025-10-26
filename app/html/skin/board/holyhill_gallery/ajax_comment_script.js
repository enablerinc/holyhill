// 댓글 AJAX 제출
document.getElementById('commentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const input = document.getElementById('commentInput');
    const content = input.value.trim();
    
    if (!content) {
        alert('댓글 내용을 입력해주세요.');
        return;
    }
    
    // 전송 버튼 비활성화
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    
    fetch('<?php echo G5_BBS_URL; ?>/comment_write.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // 성공 시 페이지 새로고침 (댓글 목록 갱신)
        location.reload();
    })
    .catch(error => {
        alert('댓글 작성 중 오류가 발생했습니다.');
        submitBtn.disabled = false;
    });
});
