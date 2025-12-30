<?php
include_once('./_common.php');

$g5['title'] = '새 게시물';

// 게시판 설정
$bo_table = 'gallery';
$board = sql_fetch("SELECT * FROM {$g5['board_table']} WHERE bo_table = '{$bo_table}'");

if (!$board) {
    alert('존재하지 않는 게시판입니다.', G5_URL);
}

// 글쓰기 권한 체크
if ($member['mb_level'] < $board['bo_write_level']) {
    if ($member['mb_id'])
        alert('글을 쓸 권한이 없습니다.', G5_BBS_URL.'/feed.php');
    else
        alert('글을 쓸 권한이 없습니다.\\n\\n회원이시라면 로그인 후 이용해 보십시오.', G5_BBS_URL.'/login.php?url='.urlencode(G5_BBS_URL.'/write_post.php'));
}

// 수정 모드
$w = '';
$wr_id = 0;
$subject = '';
$content = '';
$file = array();
$is_notice = false;

if (isset($_GET['w']) && $_GET['w'] == 'u' && isset($_GET['wr_id'])) {
    $w = 'u';
    $wr_id = (int)$_GET['wr_id'];

    $write_table = $g5['write_prefix'] . $bo_table;
    $write = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$wr_id}'");

    if (!$write) {
        alert('존재하지 않는 게시물입니다.');
    }

    // 수정 권한 체크
    if ($member['mb_id'] != $write['mb_id'] && !$is_admin) {
        alert('수정 권한이 없습니다.');
    }

    $subject = get_text($write['wr_subject']);
    $content = $write['wr_content'];

    // 공지사항 여부 체크
    $notice_array = explode(',', $board['bo_notice']);
    $is_notice = in_array($wr_id, $notice_array);

    // 첨부파일 정보 가져오기
    $file_sql = "SELECT * FROM {$g5['board_file_table']}
                 WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}'
                 ORDER BY bf_no";
    $file_result = sql_query($file_sql);
    while ($row = sql_fetch_array($file_result)) {
        $file[] = $row;
    }
}

// 액션 URL
$action_url = G5_BBS_URL.'/write_update.php';

// 파일 업로드 개수
$file_count = 10;

// 프로필 이미지
$profile_photo = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
if ($is_member) {
    $profile_path = G5_DATA_PATH.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif';
    if (file_exists($profile_path)) {
        $profile_photo = G5_DATA_URL.'/member_image/'.substr($member['mb_id'], 0, 2).'/'.$member['mb_id'].'.gif';
    }
}
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
    <script src="<?php echo G5_JS_URL; ?>/jquery-1.12.4.min.js"></script>
    <script>
        var g5_bbs_url = "<?php echo G5_BBS_URL; ?>";
        var g5_is_member = "<?php echo $is_member ? '1' : ''; ?>";
        var g5_is_admin = "<?php echo $is_admin ? '1' : ''; ?>";
    </script>
    <script src="<?php echo G5_JS_URL; ?>/common.js"></script>
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

<!-- 헤더 -->
<header id="header" class="fixed top-0 w-full bg-white/95 backdrop-blur-sm z-50 border-b border-soft-lavender">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="<?php echo G5_BBS_URL; ?>/index.php" class="text-grace-green hover:text-gray-900">
            <i class="fa-solid fa-xmark text-xl"></i>
        </a>
        <h2 class="text-base font-semibold text-grace-green"><?php echo $w == 'u' ? '게시물 수정' : '새 게시물'; ?></h2>
        <div class="w-6"></div>
    </div>
</header>

<main id="main-content" class="pt-16 pb-32">
    <div class="max-w-2xl mx-auto px-4 py-4">
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">

            <form name="fwrite" id="fwrite" action="<?php echo $action_url ?>" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="uid" value="<?php echo get_uniqid(); ?>">
                <input type="hidden" name="w" value="<?php echo $w ?>">
                <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
                <input type="hidden" name="wr_id" value="<?php echo $wr_id ?>">
                <input type="hidden" name="token" value="" id="token">
                <input type="hidden" name="html" value="html1">

                <!-- 제목 입력 -->
                <div class="px-4 pt-4 pb-3 border-b border-gray-100">
                    <input
                        type="text"
                        name="wr_subject"
                        value="<?php echo $subject ?>"
                        id="wr_subject"
                        required
                        class="w-full text-lg font-semibold border-none focus:outline-none focus:ring-0 px-0 placeholder-gray-400"
                        placeholder="제목을 입력하세요..."
                        maxlength="255">
                </div>

                <?php if ($is_admin) { ?>
                <!-- 공지사항 설정 (관리자 전용) -->
                <div class="px-4 py-3 border-b border-gray-100 bg-amber-50">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="notice" value="1" <?php echo $is_notice ? 'checked' : ''; ?>
                               class="w-5 h-5 text-amber-500 border-gray-300 rounded focus:ring-amber-500">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-bullhorn text-amber-500"></i>
                            <span class="text-sm font-medium text-gray-700">공지사항으로 등록</span>
                            <span class="text-xs text-gray-500">(모든 사용자에게 상단에 고정 표시됩니다)</span>
                        </span>
                    </label>
                </div>
                <?php } ?>

                <!-- 문구/내용 입력 -->
                <div class="px-4 py-4 border-b border-gray-100">
                    <div class="flex items-start gap-3">
                        <img src="<?php echo $profile_photo; ?>"
                             class="w-10 h-10 rounded-full flex-shrink-0 object-cover"
                             alt="프로필">
                        <div class="flex-1">
                            <textarea
                                name="wr_content"
                                id="wr_content"
                                class="w-full border-none focus:outline-none focus:ring-0 resize-none text-sm px-0 placeholder-gray-400"
                                placeholder="문구를 입력하세요..."
                                rows="6"
                                style="min-height: 120px;"><?php echo $content; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 파일 업로드 영역 -->
                <div class="px-4 py-4">
                    <div id="media-upload-area" class="relative">
                        <!-- 파일 선택 버튼 그룹 -->
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <button type="button" onclick="selectFiles()" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2">
                                <i class="fa-solid fa-folder-open"></i>
                                <span class="text-sm">파일 선택</span>
                            </button>
                            <button type="button" onclick="capturePhoto()" class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2">
                                <i class="fa-solid fa-camera"></i>
                                <span class="text-sm">카메라 촬영</span>
                            </button>
                        </div>

                        <!-- 드래그 앤 드롭 영역 -->
                        <div id="drop-zone" class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center cursor-pointer hover:border-purple-400 hover:bg-purple-50 transition-colors">
                            <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 mb-2"></i>
                            <p class="text-gray-600 mb-1 font-medium text-sm">파일을 드래그하세요</p>
                            <p class="text-xs text-gray-400">이미지, 동영상, 음원, 문서 등 (최대 10개)</p>
                            <input type="file" id="file-input" name="bf_file[]" multiple style="display: none;">
                            <input type="file" id="camera-input" accept="image/*" capture="environment" style="display: none;">
                        </div>

                        <!-- 파일 프리뷰 그리드 -->
                        <div id="preview-grid" class="grid grid-cols-5 gap-2 mt-4 hidden">
                            <!-- 프리뷰가 여기에 추가됩니다 -->
                        </div>

                        <!-- 업로드된 파일 카운터 -->
                        <div id="media-counter" class="text-center text-sm text-gray-500 mt-2 hidden">
                            <i class="fa-solid fa-file text-gray-400 mr-1"></i>
                            <span id="current-count">0</span> / 10 개
                        </div>
                    </div>
                </div>

                <!-- 기존 이미지 (수정 모드) -->
                <?php if($w == 'u' && count($file) > 0) { ?>
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                    <p class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-folder-open text-gray-500"></i>
                        기존 업로드 이미지
                    </p>
                    <div class="grid grid-cols-5 gap-2">
                        <?php
                        foreach($file as $i => $f) {
                            if($f['bf_file'] && $f['bf_type'] >= 1 && $f['bf_type'] <= 3) {
                                $file_path = G5_DATA_URL.'/file/'.$bo_table.'/'.$f['bf_file'];
                        ?>
                        <div class="relative aspect-square bg-gray-200 rounded-lg overflow-hidden group">
                            <img src="<?php echo $file_path; ?>" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <label class="flex items-center gap-1 text-white cursor-pointer px-3 py-1 bg-red-500 rounded-full text-xs font-medium">
                                    <input type="checkbox" name="bf_file_del[<?php echo $f['bf_no']; ?>]" value="1" class="rounded">
                                    삭제
                                </label>
                            </div>
                        </div>
                        <?php
                            }
                        }
                        ?>
                    </div>
                </div>
                <?php } ?>

            </form>
        </div>
    </div>
</main>

<!-- 하단 고정 공유 버튼 -->
<div class="fixed bottom-0 w-full bg-white border-t border-soft-lavender z-40 px-4 py-3">
    <div class="max-w-2xl mx-auto">
        <button type="button" id="btn_submit" onclick="submitPost()"
                class="w-full bg-gradient-to-r from-purple-600 to-purple-500 text-white font-semibold py-3 rounded-xl hover:from-purple-700 hover:to-purple-600 transition-all shadow-lg">
            <i class="fa-solid fa-share-nodes mr-2"></i>공유하기
        </button>
    </div>
</div>

<style>
/* 미디어 프리뷰 스타일 */
.preview-item {
    position: relative;
    aspect-ratio: 1;
    background: #f3f4f6;
    border-radius: 8px;
    overflow: hidden;
}
.preview-item img,
.preview-item video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.preview-item .media-type-badge {
    position: absolute;
    top: 4px;
    left: 4px;
    background: rgba(236, 72, 153, 0.9);
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 3px;
}
.preview-item .insert-content-btn {
    position: absolute;
    bottom: 4px;
    left: 4px;
    background: rgba(139, 92, 246, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    transition: all 0.2s;
    font-size: 14px;
}
.preview-item:hover .insert-content-btn {
    opacity: 1;
}
.preview-item .insert-content-btn:hover {
    background: rgba(124, 58, 237, 1);
    transform: scale(1.1);
}
.preview-item .remove-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s;
    font-size: 14px;
}
.preview-item:hover .remove-btn {
    opacity: 1;
}
.preview-item .remove-btn:hover {
    background: rgba(239, 68, 68, 0.9);
}
.preview-item .index-badge {
    position: absolute;
    bottom: 4px;
    right: 4px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
}
.preview-item .play-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.6);
    color: white;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    pointer-events: none;
}
/* 음원 파일 스타일 */
.preview-item.audio-item {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px;
}
.preview-item.audio-item .file-icon {
    font-size: 24px;
    color: white;
    margin-bottom: 4px;
}
.preview-item.audio-item .file-name {
    font-size: 9px;
    color: white;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    width: 100%;
    padding: 0 4px;
}
.preview-item.audio-item .file-type-badge {
    position: absolute;
    top: 4px;
    left: 4px;
    background: rgba(255, 255, 255, 0.9);
    color: #059669;
    font-size: 9px;
    padding: 2px 5px;
    border-radius: 4px;
    font-weight: 600;
}

/* 문서 파일 스타일 */
.preview-item.doc-item {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px;
}
.preview-item.doc-item .file-icon {
    font-size: 24px;
    color: white;
    margin-bottom: 4px;
}
.preview-item.doc-item .file-name {
    font-size: 9px;
    color: white;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    width: 100%;
    padding: 0 4px;
}
.preview-item.doc-item .file-type-badge {
    position: absolute;
    top: 4px;
    left: 4px;
    background: rgba(255, 255, 255, 0.9);
    color: #1d4ed8;
    font-size: 9px;
    padding: 2px 5px;
    border-radius: 4px;
    font-weight: 600;
}

/* 드래그 오버 효과 */
#drop-zone.drag-over {
    border-color: #9333ea;
    background-color: #f3e8ff;
}
</style>

<script>
// 파일 업로드 관리
let uploadedFiles = [];
const MAX_FILES = 10;

// 파일 타입 확장자 정의
const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
const VIDEO_EXTS = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
const AUDIO_EXTS = ['mp3', 'm4a', 'wav', 'flac', 'aac', 'wma'];

// 파일 타입 판별
function getFileType(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (IMAGE_EXTS.includes(ext) || file.type.startsWith('image/')) return 'image';
    if (VIDEO_EXTS.includes(ext) || file.type.startsWith('video/')) return 'video';
    if (AUDIO_EXTS.includes(ext) || file.type.startsWith('audio/')) return 'audio';
    return 'document';
}

// 파일 아이콘 결정
function getFileIcon(ext) {
    ext = ext.toLowerCase();
    if (['pdf'].includes(ext)) return 'fa-file-pdf';
    if (['doc', 'docx'].includes(ext)) return 'fa-file-word';
    if (['xls', 'xlsx'].includes(ext)) return 'fa-file-excel';
    if (['ppt', 'pptx'].includes(ext)) return 'fa-file-powerpoint';
    if (['hwp', 'hwpx'].includes(ext)) return 'fa-file-lines';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return 'fa-file-zipper';
    if (['txt'].includes(ext)) return 'fa-file-lines';
    return 'fa-file';
}

// 파일 선택
function selectFiles() {
    document.getElementById('file-input').click();
}

// 카메라로 촬영
function capturePhoto() {
    document.getElementById('camera-input').click();
}

// 드롭존 클릭 이벤트
document.getElementById('drop-zone').addEventListener('click', function(e) {
    if (e.target.id === 'drop-zone' || e.target.closest('#drop-zone')) {
        selectFiles();
    }
});

// 파일 선택 이벤트
document.getElementById('file-input').addEventListener('change', function(e) {
    handleFiles(this.files);
});

document.getElementById('camera-input').addEventListener('change', function(e) {
    handleFiles(this.files);
});

// 드래그 앤 드롭 이벤트
const dropZone = document.getElementById('drop-zone');

dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.add('drag-over');
});

dropZone.addEventListener('dragleave', function(e) {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.remove('drag-over');
});

dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.remove('drag-over');

    const files = e.dataTransfer.files;
    handleFiles(files);
});

// 파일 처리 함수
function handleFiles(files) {
    const previewGrid = document.getElementById('preview-grid');
    const mediaCounter = document.getElementById('media-counter');
    const currentCount = document.getElementById('current-count');

    // 모든 파일 허용
    let allFiles = Array.from(files);

    // 최대 개수 체크
    const availableSlots = MAX_FILES - uploadedFiles.length;
    if (allFiles.length > availableSlots) {
        alert(`최대 ${MAX_FILES}개의 파일만 업로드할 수 있습니다. (현재: ${uploadedFiles.length}개)`);
        allFiles = allFiles.slice(0, availableSlots);
    }

    if (allFiles.length === 0) return;

    // 파일 추가
    allFiles.forEach(file => {
        uploadedFiles.push(file);
        addPreview(file, uploadedFiles.length - 1);
    });

    // UI 업데이트
    previewGrid.classList.remove('hidden');
    mediaCounter.classList.remove('hidden');
    currentCount.textContent = uploadedFiles.length;

    // FileList 업데이트
    updateFileInput();
}

// 프리뷰 추가
function addPreview(file, index) {
    const previewGrid = document.getElementById('preview-grid');
    const fileType = getFileType(file);
    const ext = file.name.split('.').pop().toUpperCase();

    const div = document.createElement('div');
    div.setAttribute('data-index', index);

    if (fileType === 'audio') {
        // 음원 파일 프리뷰
        div.className = 'preview-item audio-item';
        div.innerHTML = `
            <span class="file-type-badge">${ext}</span>
            <i class="fa-solid fa-music file-icon"></i>
            <span class="file-name">${file.name}</span>
            <span class="index-badge">${index + 1}</span>
            <button type="button" class="insert-content-btn" onclick="insertMediaToContent(${index})" title="본문에 삽입" style="bottom: auto; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0;">
                <i class="fa-solid fa-plus"></i>
            </button>
            <button type="button" class="remove-btn" onclick="removeMedia(${index})">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;
        previewGrid.appendChild(div);
    } else if (fileType === 'document') {
        // 문서 파일 프리뷰
        div.className = 'preview-item doc-item';
        const iconClass = getFileIcon(ext);
        div.innerHTML = `
            <span class="file-type-badge">${ext}</span>
            <i class="fa-solid ${iconClass} file-icon"></i>
            <span class="file-name">${file.name}</span>
            <span class="index-badge">${index + 1}</span>
            <button type="button" class="insert-content-btn" onclick="insertMediaToContent(${index})" title="본문에 삽입" style="bottom: auto; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0;">
                <i class="fa-solid fa-plus"></i>
            </button>
            <button type="button" class="remove-btn" onclick="removeMedia(${index})">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;
        previewGrid.appendChild(div);
    } else {
        // 이미지/동영상 프리뷰
        div.className = 'preview-item';

        const reader = new FileReader();
        reader.onload = function(e) {
            let mediaElement = '';
            let typeBadge = '';
            let playIcon = '';

            if (fileType === 'video') {
                mediaElement = `<video src="${e.target.result}" muted></video>`;
                typeBadge = '<span class="media-type-badge"><i class="fa-solid fa-video"></i> 동영상</span>';
                playIcon = '<div class="play-icon"><i class="fa-solid fa-play"></i></div>';
            } else {
                mediaElement = `<img src="${e.target.result}" alt="preview">`;
            }

            div.innerHTML = `
                ${mediaElement}
                ${typeBadge}
                <span class="index-badge">${index + 1}</span>
                ${playIcon}
                <button type="button" class="insert-content-btn" onclick="insertMediaToContent(${index})" title="본문에 삽입">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <button type="button" class="remove-btn" onclick="removeMedia(${index})">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;
            previewGrid.appendChild(div);
        };
        reader.readAsDataURL(file);
    }
}

// 파일을 본문에 삽입
function insertMediaToContent(index) {
    const contentTextarea = document.getElementById('wr_content');
    if (!contentTextarea) {
        alert('본문 입력창을 찾을 수 없습니다.');
        return;
    }

    const file = uploadedFiles[index];
    const fileType = getFileType(file);

    // 파일 타입에 따른 placeholder 생성
    let mediaPlaceholder;
    switch (fileType) {
        case 'audio':
            mediaPlaceholder = `[음원${index + 1}]\n\n`;
            break;
        case 'video':
            mediaPlaceholder = `[동영상${index + 1}]\n\n`;
            break;
        case 'document':
            mediaPlaceholder = `[파일${index + 1}]\n\n`;
            break;
        default:
            mediaPlaceholder = `[이미지${index + 1}]\n\n`;
    }

    // 커서 위치에 삽입
    const startPos = contentTextarea.selectionStart;
    const endPos = contentTextarea.selectionEnd;
    const currentValue = contentTextarea.value;

    contentTextarea.value = currentValue.substring(0, startPos) + mediaPlaceholder + currentValue.substring(endPos);

    // 커서 위치를 삽입된 텍스트 뒤로 이동
    contentTextarea.selectionStart = contentTextarea.selectionEnd = startPos + mediaPlaceholder.length;
    contentTextarea.focus();

    // 피드백
    const insertBtn = event.target.closest('.insert-content-btn');
    if (insertBtn) {
        const originalHTML = insertBtn.innerHTML;
        insertBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
        insertBtn.style.backgroundColor = '#10b981';
        setTimeout(() => {
            insertBtn.innerHTML = originalHTML;
            insertBtn.style.backgroundColor = '';
        }, 1000);
    }
}

// 미디어 제거
function removeMedia(index) {
    uploadedFiles.splice(index, 1);

    const previewGrid = document.getElementById('preview-grid');
    const currentCount = document.getElementById('current-count');

    // 프리뷰 재생성
    previewGrid.innerHTML = '';
    uploadedFiles.forEach((file, idx) => {
        addPreview(file, idx);
    });

    // UI 업데이트
    currentCount.textContent = uploadedFiles.length;

    if (uploadedFiles.length === 0) {
        previewGrid.classList.add('hidden');
        document.getElementById('media-counter').classList.add('hidden');
    }

    updateFileInput();
}

// FileInput 업데이트
function updateFileInput() {
    const fileInput = document.getElementById('file-input');
    const dataTransfer = new DataTransfer();

    uploadedFiles.forEach(file => {
        dataTransfer.items.add(file);
    });

    fileInput.files = dataTransfer.files;
}

// 폼 제출
function submitPost() {
    var form = document.getElementById("fwrite");
    if (!form) {
        alert("폼을 찾을 수 없습니다!");
        return false;
    }

    if (fwrite_submit(form)) {
        form.submit();
    }
}

function fwrite_submit(f) {
    // 토큰 생성 및 설정
    var bo_table = f.bo_table.value;
    if (bo_table && typeof get_write_token === 'function') {
        var token = get_write_token(bo_table);
        if (token) {
            f.token.value = token;
        } else {
            alert("토큰 생성에 실패했습니다.");
            return false;
        }
    } else {
        alert("오류: 토큰 생성 함수를 찾을 수 없습니다.");
        return false;
    }

    // 제목 검증
    if (f.wr_subject.value.trim() == "") {
        alert("제목을 입력하십시오.");
        f.wr_subject.focus();
        return false;
    }

    // 내용 검증
    if (f.wr_content.value.trim() == "") {
        alert("내용을 입력하십시오.");
        f.wr_content.focus();
        return false;
    }

    // 버튼 비활성화
    var btn_submit = document.getElementById("btn_submit");
    if (btn_submit) {
        btn_submit.disabled = true;
        btn_submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>공유 중...';
    }

    return true;
}
</script>

</body>
</html>
