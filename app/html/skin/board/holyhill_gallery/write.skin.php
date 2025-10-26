<?php
if (!defined('_GNUBOARD_')) exit;
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);
?>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
body { 
    font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
}
</style>

<section id="bo_w" class="max-w-2xl mx-auto">
    
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <i class="fa-solid fa-pen text-purple-600"></i>
            <?php echo $g5['title'] ?>
        </h2>

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
                $option .= PHP_EOL.'<li class="chk_box"><input type="checkbox" id="notice" name="notice" value="1" '.$notice_checked.'>'.PHP_EOL.'<label for="notice"><span></span>공지</label></li>';
            }
            if ($is_html) {
                if ($is_dhtml_editor) {
                    $option_hidden .= '<input type="hidden" value="html1" name="html">';
                } else {
                    $option .= PHP_EOL.'<li class="chk_box"><input type="checkbox" id="html" name="html" onclick="html_auto_br(this);" value="'.$html_value.'" '.$html_checked.'>'.PHP_EOL.'<label for="html"><span></span>html</label></li>';
                }
            }
            if ($is_secret) {
                if ($is_admin || $is_secret==1) {
                    $option .= PHP_EOL.'<li class="chk_box"><input type="checkbox" id="secret" name="secret" value="secret" '.$secret_checked.'>'.PHP_EOL.'<label for="secret"><span></span>비밀글</label></li>';
                } else {
                    $option_hidden .= '<input type="hidden" name="secret" value="secret">';
                }
            }
            if ($is_mail) {
                $option .= PHP_EOL.'<li class="chk_box"><input type="checkbox" id="mail" name="mail" value="mail" '.$recv_email_checked.'>'.PHP_EOL.'<label for="mail"><span></span>답변메일받기</label></li>';
            }
        }
        echo $option_hidden;
        ?>

        <!-- 제목 -->
        <div class="mb-6">
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-heading text-purple-600"></i>
                    제목
                </span>
                <input type="text" name="wr_subject" value="<?php echo $subject ?>" id="wr_subject" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500" maxlength="255" placeholder="제목을 입력하세요">
            </label>
        </div>

        <!-- 내용 -->
        <div class="mb-6">
            <label class="block mb-2">
                <span class="text-sm font-medium text-gray-700 flex items-center gap-2">
                    <i class="fa-solid fa-align-left text-purple-600"></i>
                    내용
                </span>
            </label>
            <?php echo $editor_html; ?>
        </div>

        <!-- 파일 업로드 -->
        <?php for ($i=0; $is_file && $i<$file_count; $i++) { ?>
        <div class="mb-4 p-4 bg-gray-50 rounded-xl">
            <label for="bf_file_<?php echo $i+1 ?>" class="text-sm font-medium text-gray-700 flex items-center gap-2 mb-2">
                <i class="fa-solid fa-image text-purple-600"></i>
                파일 #<?php echo $i+1 ?>
            </label>
            <input type="file" name="bf_file[]" id="bf_file_<?php echo $i+1 ?>" title="파일첨부 <?php echo $i+1 ?> : 용량 <?php echo $upload_max_filesize ?> 이하만 업로드 가능" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
            <?php if ($is_file_content) { ?>
            <input type="text" name="bf_content[]" value="<?php echo ($w == 'u') ? $file[$i]['bf_content'] : ''; ?>" title="파일 설명을 입력해주세요." class="w-full px-3 py-2 border rounded-lg mt-2 text-sm" placeholder="파일 설명 (선택)">
            <?php } ?>
            <?php if($w == 'u' && $file[$i]['file']) { ?>
            <div class="mt-2 text-sm text-gray-600 flex items-center gap-2">
                <span><?php echo $file[$i]['source'].'('.$file[$i]['size'].')'; ?></span>
                <label class="flex items-center gap-1">
                    <input type="checkbox" name="bf_file_del[<?php echo $i ?>]" value="1" id="bf_file_del<?php echo $i ?>" class="rounded">
                    <span>삭제</span>
                </label>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- 옵션 -->
        <?php if ($option) { ?>
        <div class="mb-6 p-4 bg-gray-50 rounded-xl">
            <ul class="bo_v_option text-sm space-y-2"><?php echo $option; ?></ul>
        </div>
        <?php } ?>

        <!-- 캡챠 -->
        <?php if ($is_use_captcha) { ?>
        <div class="mb-6">
            <?php echo $captcha_html ?>
        </div>
        <?php } ?>

        <!-- 버튼 -->
        <div class="flex gap-3">
            <a href="<?php echo get_pretty_url($bo_table); ?>" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-xl text-center font-semibold hover:bg-gray-300 transition-colors">
                취소
            </a>
            <button type="submit" id="btn_submit" class="flex-1 px-6 py-3 bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700 transition-colors">
                <i class="fa-solid fa-paper-plane mr-2"></i>
                작성완료
            </button>
        </div>

        </form>
    </div>

</section>

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

    document.getElementById("btn_submit").disabled = "disabled";

    return true;
}
</script>
