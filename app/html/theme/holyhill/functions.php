<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * 유튜브 링크를 반응형 임베드로 변환하는 함수
 * @param string $content 변환할 내용
 * @return string 변환된 내용
 */
function convert_youtube_to_embed($content) {
    if (empty($content)) {
        return $content;
    }

    // 유튜브 URL 패턴들
    $patterns = array(
        // https://www.youtube.com/watch?v=VIDEO_ID
        '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)(?:[&\?][^\s<]*)?/i',
        // https://youtu.be/VIDEO_ID
        '/https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)(?:[&\?][^\s<]*)?/i',
        // https://www.youtube.com/embed/VIDEO_ID
        '/https?:\/\/(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]+)(?:[&\?][^\s<]*)?/i',
        // https://m.youtube.com/watch?v=VIDEO_ID
        '/https?:\/\/(?:www\.)?m\.youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)(?:[&\?][^\s<]*)?/i'
    );

    // 반응형 유튜브 임베드 HTML
    $replacement = '<div class="youtube-embed-wrapper" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; margin: 20px 0;">
        <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"
                src="https://www.youtube.com/embed/$1"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen>
        </iframe>
    </div>';

    // 모든 패턴에 대해 변환 수행
    foreach ($patterns as $pattern) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    return $content;
}

/**
 * 오늘의 말씀 내용에서 유튜브 임베드 표시 (strip_tags 전에 처리)
 * @param string $content 원본 내용
 * @param int $cut_length 자르기 길이 (0이면 자르지 않음)
 * @return string 처리된 내용
 */
function get_word_content_with_youtube($content, $cut_length = 0) {
    if (empty($content)) {
        return '';
    }

    // 유튜브 링크가 있는지 확인
    $has_youtube = preg_match('/https?:\/\/(?:www\.)?(youtube\.com|youtu\.be)/i', $content);

    if ($has_youtube) {
        // 유튜브 임베드로 변환
        $content = convert_youtube_to_embed($content);
        return $content; // 유튜브가 있으면 자르지 않고 반환
    } else {
        // 일반 텍스트 처리
        $text_content = strip_tags($content);
        $text_content = str_replace('&nbsp;', ' ', $text_content);
        $text_content = trim($text_content);

        if ($cut_length > 0) {
            $text_content = cut_str($text_content, $cut_length);
        }

        return '"' . $text_content . '"';
    }
}
?>
