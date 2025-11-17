/**
 * 댓글 위치로 스크롤하는 기능
 * URL에 #c_123 형태의 해시가 있으면 해당 댓글로 스크롤
 */
(function() {
    function scrollToComment() {
        const hash = window.location.hash;

        if (hash && hash.startsWith('#c_')) {
            // 페이지 로드 후 약간의 지연을 주어 DOM이 완전히 로드되도록 함
            setTimeout(function() {
                const commentId = hash.substring(1); // #c_123 -> c_123
                const commentElement = document.getElementById(commentId);

                if (commentElement) {
                    // 부드럽게 스크롤
                    commentElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    // 하이라이트 효과
                    commentElement.style.backgroundColor = '#F3E8FF';
                    setTimeout(function() {
                        commentElement.style.transition = 'background-color 2s';
                        commentElement.style.backgroundColor = '';
                    }, 1000);
                }
            }, 300);
        }
    }

    // 페이지 로드 완료 시 실행
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scrollToComment);
    } else {
        scrollToComment();
    }

    // 해시 변경 시에도 실행
    window.addEventListener('hashchange', scrollToComment);
})();
