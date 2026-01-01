<?php
include_once('../common.php');

/**
 * 프로필 이미지 URL 반환 (캐시 버스팅 적용)
 * @param string $mb_id 회원 아이디
 * @return string 프로필 이미지 URL
 */
function get_profile_image_url($mb_id) {
    $default_img = 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-7.jpg';
    if (empty($mb_id)) return $default_img;

    $profile_path = G5_DATA_PATH.'/member_image/'.substr($mb_id, 0, 2).'/'.$mb_id.'.gif';
    if (file_exists($profile_path)) {
        return G5_DATA_URL.'/member_image/'.substr($mb_id, 0, 2).'/'.$mb_id.'.gif?v='.filemtime($profile_path);
    }
    return $default_img;
}

// 커뮤니티 사용여부
if(defined('G5_COMMUNITY_USE') && G5_COMMUNITY_USE === false) {
    if (!defined('G5_USE_SHOP') || !G5_USE_SHOP)
        die('<p>쇼핑몰 설치 후 이용해 주십시오.</p>');

    define('_SHOP_', true);
}