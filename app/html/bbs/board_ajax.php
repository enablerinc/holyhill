<?php
include_once('./_common.php');

// CORS 헤더 (필요시)
header('Content-Type: application/json; charset=utf-8');

// 텍스트를 이미지로 변환하는 함수
function generate_text_image($subject, $content) {
    // HTML 태그 제거 및 텍스트 정리
    $text = strip_tags($content);
    $text = preg_replace('/\s+/', ' ', $text); // 여러 공백을 하나로
    $text = trim($text);

    // 제목 사용 (내용이 없으면)
    if (empty($text)) {
        $text = strip_tags($subject);
    }

    // 텍스트 길이 제한 (약 100자)
    if (mb_strlen($text, 'UTF-8') > 100) {
        $text = mb_substr($text, 0, 100, 'UTF-8') . '...';
    }

    // 텍스트를 여러 줄로 분할 (약 20자씩)
    $lines = [];
    $words = explode(' ', $text);
    $current_line = '';

    foreach ($words as $word) {
        if (mb_strlen($current_line . ' ' . $word, 'UTF-8') > 20) {
            if (!empty($current_line)) {
                $lines[] = $current_line;
                $current_line = $word;
            } else {
                $lines[] = $word;
            }
        } else {
            $current_line .= (empty($current_line) ? '' : ' ') . $word;
        }
    }
    if (!empty($current_line)) {
        $lines[] = $current_line;
    }

    // 최대 4줄까지만 표시
    $lines = array_slice($lines, 0, 4);

    // XML 특수문자 이스케이프
    foreach ($lines as &$line) {
        $line = htmlspecialchars($line, ENT_XML1, 'UTF-8');
    }

    // SVG 텍스트 요소 생성
    $y = 45; // 시작 y 좌표
    $text_elements = '';
    foreach ($lines as $line) {
        $text_elements .= "<text x=\"50%\" y=\"{$y}%\" text-anchor=\"middle\" fill=\"#6B705C\" font-size=\"14\" font-weight=\"500\">{$line}</text>";
        $y += 12; // 다음 줄 간격
    }

    // SVG 생성
    $svg = <<<SVG
<svg width="400" height="400" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#E8E2F7;stop-opacity:1" />
            <stop offset="100%" style="stop-color:#EEF3F8;stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="400" height="400" fill="url(#grad)"/>
    <foreignObject x="10" y="35%" width="380" height="30%">
        <div xmlns="http://www.w3.org/1999/xhtml" style="font-family: 'Pretendard', sans-serif; font-size: 14px; color: #6B705C; text-align: center; padding: 20px; word-break: keep-all; line-height: 1.6;">
            {$text}
        </div>
    </foreignObject>
</svg>
SVG;

    // Base64 인코딩 및 Data URI 생성
    $encoded = base64_encode($svg);
    return 'data:image/svg+xml;base64,' . $encoded;
}

$bo_table = isset($_GET['bo_table']) ? clean_xss_tags($_GET['bo_table']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page_rows = isset($_GET['page_rows']) ? (int)$_GET['page_rows'] : 30;

if (!$bo_table) {
    echo json_encode(['success' => false, 'message' => '게시판이 지정되지 않았습니다.']);
    exit;
}

// 게시판 설정 확인
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");
if (!$board) {
    echo json_encode(['success' => false, 'message' => '존재하지 않는 게시판입니다.']);
    exit;
}

// 기간 필터 조건
$date_condition = '';
switch($filter) {
    case '1week':
        $date_condition = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        break;
    case '1month':
        $date_condition = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3month':
        $date_condition = " AND wr_datetime >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case 'all':
    default:
        $date_condition = '';
        break;
}

// 게시글 가져오기
$offset = ($page - 1) * $page_rows;
$sql = "SELECT * FROM {$g5['write_prefix']}{$bo_table}
        WHERE wr_is_comment = 0 {$date_condition}
        ORDER BY wr_good DESC, wr_datetime DESC
        LIMIT {$offset}, {$page_rows}";

$result = sql_query($sql);
$items = array();

while ($row = sql_fetch_array($result)) {
    $wr_id = $row['wr_id'];

    // URL에서 YouTube 비디오 ID 추출
    $video_thumbnail = '';
    $video_url = '';

    // 먼저 wr_link1 체크
    if (!empty($row['wr_link1'])) {
        $video_url = $row['wr_link1'];
    }
    // wr_link1이 없으면 게시글 내용에서 URL 찾기
    elseif (!empty($row['wr_content'])) {
        if (preg_match('/https?:\/\/(?:www\.)?(?:youtube\.com|youtu\.be)\/[^\s<>"]+/i', $row['wr_content'], $url_matches)) {
            $video_url = $url_matches[0];
        }
    }

    // YouTube URL에서 비디오 ID 추출
    if ($video_url && preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $video_url, $matches)) {
        $video_id = $matches[1];
        $video_thumbnail = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
    }

    // 첫 번째 이미지 가져오기
    $first_image = '';
    $img_result = sql_query("SELECT bf_file FROM {$g5['board_file_table']} WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND bf_type BETWEEN 1 AND 3 ORDER BY bf_no LIMIT 1");
    if ($img_result && $img = sql_fetch_array($img_result)) {
        $first_image = G5_DATA_URL.'/file/'.$bo_table.'/'.$img['bf_file'];
    }

    // 텍스트 콘텐츠 추출
    $text_content = strip_tags($row['wr_content']);
    $text_content = preg_replace('/\[이미지\d+\]/', '', $text_content);
    $text_content = trim($text_content);

    $view_href = G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id;
    $good_count = isset($row['wr_good']) ? (int)$row['wr_good'] : 0;

    $items[] = array(
        'wr_id' => $wr_id,
        'subject' => strip_tags($row['wr_subject']),
        'video_thumbnail' => $video_thumbnail,
        'image' => $first_image,
        'has_video' => !empty($video_thumbnail),
        'has_image' => !empty($first_image),
        'text_content' => $text_content ? cut_str($text_content, 80) : '내용 없음',
        'view_href' => $view_href,
        'good_count' => number_format($good_count),
        'datetime' => $row['wr_datetime']
    );
}

echo json_encode([
    'success' => true,
    'items' => $items,
    'page' => $page,
    'count' => count($items)
], JSON_UNESCAPED_UNICODE);
?>
