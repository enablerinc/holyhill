<?php
/**
 * 알림 API 엔드포인트
 */
include_once('./_common.php');
include_once(G5_BBS_PATH.'/notification.lib.php');

header('Content-Type: application/json; charset=utf-8');

// 로그인 확인
if (!$is_member) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        // 알림 목록 조회
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $notifications = get_notifications($member['mb_id'], $limit, $offset);
        $unread_count = get_unread_notification_count($member['mb_id']);

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        break;

    case 'count':
        // 읽지 않은 알림 개수만 조회
        $count = get_unread_notification_count($member['mb_id']);
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        break;

    case 'read':
        // 알림 읽음 처리
        $no_id = isset($_POST['no_id']) ? (int)$_POST['no_id'] : 0;
        if ($no_id) {
            $result = mark_notification_as_read($no_id, $member['mb_id']);
            echo json_encode([
                'success' => $result ? true : false,
                'message' => $result ? '읽음 처리되었습니다.' : '처리 중 오류가 발생했습니다.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        }
        break;

    case 'read_all':
        // 모든 알림 읽음 처리
        $result = mark_all_notifications_as_read($member['mb_id']);
        echo json_encode([
            'success' => $result ? true : false,
            'message' => $result ? '모든 알림을 읽음 처리했습니다.' : '처리 중 오류가 발생했습니다.'
        ]);
        break;

    case 'delete_all':
        // 모든 알림 삭제
        $result = delete_all_notifications($member['mb_id']);
        echo json_encode([
            'success' => $result ? true : false,
            'message' => $result ? '모든 알림을 삭제했습니다.' : '처리 중 오류가 발생했습니다.'
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        break;
}
