<?php
if (!defined('_GNUBOARD_')) exit;
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);
?>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
body { 
    font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: #fafafa;
}
</style>

<section id="bo_w" class="max-w-2xl mx-auto">
    
    <div class="bg-white rounded-xl shadow-sm overflow-hidden" style="font-family: 'Pretendard', sans-serif;">
        
        <!-- 헤더 -->
        <div class="border-b border-gray-200 px-4 py-3 flex items-center justify-between sticky top-0 bg-white z-10">
            <a href="<?php echo get_pretty_url($bo_table); ?>" class="text-gray-600 hover:text-gray-900">
                <i class="fa-solid fa-xmark text-xl"></i>
            </a>
            <h2 class="text-base font-semibold text-gray-900">새 게시물</h2>
            <button type="submit" form="fwrite" id="btn_submit" class="text-blue-500 font-semibold hover:text-blue-600">공유</button>
        </div>

        <form name="fwrite" id="fwrite" action="<?php echo $action_url ?>" onsubmit="return fwrite_submit(this);" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="uid" value="<?php echo get_uniqid(); ?>">
        <input type="hidden" name="w" value="<?php echo $w ?>">
        <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
        <input type="hidden" name="wr_id" value="<?php echo $wr_id ?>">
        <input type="hidden" name="sca" value="<?php echo $sca ?>">
        <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
        <input type="hidden" name="stx" value="<?php echo $stx ?>">
        <input type="hidden" name="spt" value="<?php echo $spt ?>">
        <input type="hidden" name="sst" value="<?php echo $sst ?>">
        <input type="hidden" name="sod" value="<?php echo $sod ?>">
        <input type="hidden" name="page" value="<?php echo $page ?>">
        
        <?php
        $option = '';
        $option_hidden = '';
        if ($is_notice || $is_html || $is_secret || $is_mail) { 
            $option = '';
            if ($is_notice) {
                $option .= PHP_EOL.'<li class="flex items-center gap-2"><input type="checkbox" id="notice" name="notice" value="1" '.$notice_checked.' class="rounded">'.PHP_EOL.'<label for="notice">공지</label></li>';
            }
            if ($is_html) {
                if ($is_dhtml_editor) {
                    $option_hidden .= '<input type="hidden" value="html1" name="html">';
                } else {
                    $option .= PHP_EOL.'<li class="flex items-center gap-2"><input type="checkbox" id="html" name="html" onclick="html_auto_br(this);" value="'.$html_value.'" '.$html_checked.' class="rounded">'.PHP_EOL.'<label for="html">html</label></li>';
                }
            }
            if ($is_secret) {
                if ($is_admin || $is_secret==1) {
                    $option .= PHP_EOL.'<li class="flex items-center gap-2"><input type="checkbox" id="secret" name="secret" value="secret" '.$secret_checked.' class="rounded">'.PHP_EOL.'<label for="secret">비밀글</label></li>';
                } else {
                    $option_hidden .= '<input type="hidden" name="secret" value="secret">';
                }
            }
            if ($is_mail) {
                $option .= PHP_EOL.'<li class="flex items-center gap-2"><input type="checkbox" id="mail" name="mail" value="mail" '.$recv_email_checked.' class="rounded">'.PHP_EOL.'<label for="mail">답변메일받기</label></li>';
            }
        }
        echo $option_hidden;
        ?>

        <!-- 이미지 업로드 영역 -->
        <div class="p-4 border-b border-gray-100">
            <div id="image-upload-area" class="relative">
                <!-- 드래그 앤 드롭 영역 -->
                <div id="drop-zone" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors">
                    <i class="fa-solid fa-images text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-600 mb-2 font-medium">사진을 드래그하거나 클릭하여 선택하세요</p>
                    <p class="text-sm text-gray-400">최대 10개까지 업로드 가능 (JPG, PNG, GIF)</p>
                    <input type="file" id="image-input" name="bf_file[]" accept="image/*" multiple style="display: none;">
                </div>
                
                <!-- 이미지 프리뷰 그리드 -->
                <div id="preview-grid" class="grid grid-cols-5 gap-2 mt-4 hidden">
                    <!-- 프리뷰 이미지가 여기에 추가됩니다 -->
                </div>
                
                <!-- 업로드된 이미지 카운터 -->
                <div id="image-counter" class="text-center text-sm text-gray-500 mt-2 hidden">
                    <i class="fa-solid fa-image text-gray-400 mr-1"></i>
                    <span id="current-count">0</span> / 10 장
                </div>
            </div>
        </div>

        <!-- 기존 이미지 (수정 모드) -->
        <?php if($w == 'u' && $is_file) { 
            $has_existing_files = false;
            for ($i=0; $i<$file_count; $i++) {
                if($file[$i]['file']) {
                    $has_existing_files = true;
                    break;
                }
            }
            if ($has_existing_files) {
        ?>
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
            <p class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-folder-open text-gray-500"></i>
                기존 업로드 이미지
            </p>
            <div class="grid grid-cols-5 gap-2">
                <?php 
                for ($i=0; $i<$file_count; $i++) { 
                    if($file[$i]['file']) {
                ?>
                <div class="relative aspect-square bg-gray-200 rounded-lg overflow-hidden group">
                    <img src="<?php echo $file[$i]['href']; ?>" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <label class="flex items-center gap-1 text-white cursor-pointer px-3 py-1 bg-red-500 rounded-full text-xs font-medium">
                            <input type="checkbox" name="bf_file_del[<?php echo $i ?>]" value="1" class="rounded">
                            삭제
                        </label>
                    </div>
                    <div class="absolute top-1 left-1 bg-black bg-opacity-60 text-white text-xs px-2 py-0.5 rounded">
                        <?php echo $i + 1; ?>
                    </div>
                </div>
                <?php 
                    } 
                } 
                ?>
            </div>
        </div>
        <?php 
            }
        } 
        ?>

        <!-- 제목 입력 -->
        <div class="px-4 py-3 border-b border-gray-100">
            <input 
                type="text" 
                name="wr_subject" 
                value="<?php echo $subject ?>" 
                id="wr_subject" 
                required 
                class="w-full text-base border-none focus:outline-none focus:ring-0 px-0 placeholder-gray-400" 
                placeholder="제목을 입력하세요..."
                maxlength="255">
        </div>

        <!-- 캡션/내용 입력 -->
        <div class="px-4 py-4">
            <div class="flex items-start gap-3">
                <?php if ($is_member) { ?>
                <img src="<?php echo $member['mb_photo'] ? G5_DATA_URL.'/member/'.$member['mb_photo'] : G5_THEME_URL.'/img/no-profile.svg'; ?>" 
                     class="w-8 h-8 rounded-full flex-shrink-0 object-cover">
                <?php } else { ?>
                <div class="w-8 h-8 rounded-full bg-gray-200 flex-shrink-0"></div>
                <?php } ?>
                <div class="flex-1">
                    <?php if ($is_dhtml_editor) { ?>
                        <div id="editor_wrapper">
                            <?php echo $editor_html; ?>
                        </div>
                    <?php } else { ?>
                    <textarea 
                        name="wr_content" 
                        id="wr_content"
                        class="w-full border-none focus:outline-none focus:ring-0 resize-none text-sm px-0 placeholder-gray-400"
                        placeholder="문구를 입력하세요..."
                        rows="5"
                        style="min-height: 100px;"><?php echo $content; ?></textarea>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- 추가 옵션 -->
        <?php if ($option) { ?>
        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
            <div class="flex items-center justify-between cursor-pointer" onclick="toggleOptions()">
                <span class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-gray-500"></i>
                    추가 옵션
                </span>
                <i class="fa-solid fa-chevron-down text-gray-400 transition-transform" id="option-icon"></i>
            </div>
            <ul id="option-list" class="space-y-2 mt-3 text-sm hidden"><?php echo $option; ?></ul>
        </div>
        <?php } ?>

        <!-- 캡챠 -->
        <?php if ($is_use_captcha) { ?>
        <div class="px-4 py-3 border-t border-gray-100">
            <?php echo $captcha_html ?>
        </div>
        <?php } ?>

        </form>
    </div>

</section>

<style>
/* 이미지 프리뷰 스타일 */
.preview-item {
    position: relative;
    aspect-ratio: 1;
    background: #f3f4f6;
    border-radius: 8px;
    overflow: hidden;
}
.preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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
    top: 4px;
    left: 4px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
}

/* 드래그 오버 효과 */
#drop-zone.drag-over {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

/* 에디터 스타일 조정 */
#wr_content_wrapper {
    border: none !important;
}

/* 체크박스 스타일 */
input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
</style>

<script>
// 이미지 업로드 관리
let uploadedFiles = [];
const MAX_FILES = 10;

// 드롭존 클릭 이벤트
document.getElementById('drop-zone').addEventListener('click', function(e) {
    if (e.target.id !== 'drop-zone' && !e.target.closest('#drop-zone')) return;
    document.getElementById('image-input').click();
});

// 파일 선택 이벤트
document.getElementById('image-input').addEventListener('change', function(e) {
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
    const imageInput = document.getElementById('image-input');
    const previewGrid = document.getElementById('preview-grid');
    const imageCounter = document.getElementById('image-counter');
    const currentCount = document.getElementById('current-count');
    
    // 이미지 파일만 필터링
    let imageFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
    
    // 최대 개수 체크
    const availableSlots = MAX_FILES - uploadedFiles.length;
    if (imageFiles.length > availableSlots) {
        alert(`최대 ${MAX_FILES}개의 이미지만 업로드할 수 있습니다. (현재: ${uploadedFiles.length}개)`);
        imageFiles = imageFiles.slice(0, availableSlots);
    }
    
    if (imageFiles.length === 0) return;
    
    // 파일 추가
    imageFiles.forEach(file => {
        uploadedFiles.push(file);
        addPreview(file, uploadedFiles.length - 1);
    });
    
    // UI 업데이트
    previewGrid.classList.remove('hidden');
    imageCounter.classList.remove('hidden');
    currentCount.textContent = uploadedFiles.length;
    
    // FileList 업데이트 (실제 업로드용)
    updateFileInput();
}

// 프리뷰 추가
function addPreview(file, index) {
    const previewGrid = document.getElementById('preview-grid');
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const div = document.createElement('div');
        div.className = 'preview-item';
        div.setAttribute('data-index', index);
        div.innerHTML = `
            <img src="${e.target.result}" alt="preview">
            <span class="index-badge">${index + 1}</span>
            <button type="button" class="remove-btn" onclick="removeImage(${index})">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;
        previewGrid.appendChild(div);
    };
    
    reader.readAsDataURL(file);
}

// 이미지 제거
function removeImage(index) {
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
        document.getElementById('image-counter').classList.add('hidden');
    }
    
    updateFileInput();
}

// FileInput 업데이트
function updateFileInput() {
    const imageInput = document.getElementById('image-input');
    const dataTransfer = new DataTransfer();
    
    uploadedFiles.forEach(file => {
        dataTransfer.items.add(file);
    });
    
    imageInput.files = dataTransfer.files;
}

// 옵션 토글
function toggleOptions() {
    const optionList = document.getElementById('option-list');
    const optionIcon = document.getElementById('option-icon');
    
    if (optionList && optionIcon) {
        if (optionList.classList.contains('hidden')) {
            optionList.classList.remove('hidden');
            optionIcon.style.transform = 'rotate(180deg)';
        } else {
            optionList.classList.add('hidden');
            optionIcon.style.transform = 'rotate(0deg)';
        }
    }
}

// 페이지 로드 시 기존 이미지 카운트 (수정 모드)
document.addEventListener('DOMContentLoaded', function() {
    // 에디터 스타일 조정
    const editorWrapper = document.getElementById('editor_wrapper');
    if (editorWrapper) {
        const editor = editorWrapper.querySelector('iframe, .cke, .editor');
        if (editor) {
            editor.style.border = 'none';
        }
    }
});
</script>

<script>
<?php if($write_min || $write_max) { ?>
var char_min = parseInt(<?php echo $write_min; ?>);
var char_max = parseInt(<?php echo $write_max; ?>);
check_byte("wr_content", "char_count");

$(function() {
    $("#wr_content").on("keyup", function() {
        check_byte("wr_content", "char_count");
    });
});
<?php } ?>

function html_auto_br(obj)
{
    if (obj.checked) {
        result = confirm("자동 줄바꿈을 하시겠습니까?\n\n자동 줄바꿈은 게시물 내용중 줄바뀐 곳을<br>태그로 변환하는 기능입니다.");
        if (result)
            obj.value = "html2";
        else
            obj.value = "html1";
    }
    else
        obj.value = "";
}

function fwrite_submit(f)
{
    <?php echo $editor_js; ?>

    var subject = "";
    var content = "";
    $.ajax({
        url: g5_bbs_url+"/ajax.filter.php",
        type: "POST",
        data: {
            "subject": f.wr_subject.value,
            "content": f.wr_content.value
        },
        dataType: "json",
        async: false,
        cache: false,
        success: function(data, textStatus) {
            subject = data.subject;
            content = data.content;
        }
    });

    if (subject) {
        alert("제목에 금지단어('"+subject+"')가 포함되어 있습니다");
        f.wr_subject.focus();
        return false;
    }

    if (content) {
        alert("내용에 금지단어('"+content+"')가 포함되어 있습니다");
        if (typeof(ed_wr_content) != "undefined")
            ed_wr_content.returnFalse();
        else
            f.wr_content.focus();
        return false;
    }

    if (document.getElementById("wr_subject").value == "") {
        alert("제목을 입력하십시오.");
        document.getElementById("wr_subject").focus();
        return false;
    }

    if (typeof(ed_wr_content) != "undefined") {
        if (ed_wr_content.outputBodyHTML() == "") {
            alert("내용을 입력하십시오.");
            ed_wr_content.returnFalse();
            return false;
        }
    }
    else if (document.getElementById("wr_content") && document.getElementById("wr_content").value == "") {
        alert("내용을 입력하십시오.");
        document.getElementById("wr_content").focus();
        return false;
    }

    if (document.getElementById("char_count")) {
        if (char_min > 0 || char_max > 0) {
            var cnt = parseInt(check_byte("wr_content", "char_count"));
            if (char_min > 0 && char_min > cnt) {
                alert("내용은 "+char_min+"글자 이상 쓰셔야 합니다.");
                return false;
            }
            else if (char_max > 0 && char_max < cnt) {
                alert("내용은 "+char_max+"글자 이하로 쓰셔야 합니다.");
                return false;
            }
        }
    }

    <?php echo $captcha_js; ?>

    var btn_submit = document.getElementById("btn_submit");
    if (btn_submit) {
        btn_submit.disabled = "disabled";
    }

    return true;
}
</script>