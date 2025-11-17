<?php
// 알림 테이블 생성 스크립트
include_once('./_common.php');

// 관리자만 실행 가능
if (!$is_admin) {
    die('관리자만 실행할 수 있습니다.');
}

$sql = "
CREATE TABLE IF NOT EXISTS `g5_notifications` (
  `no_id` int(11) NOT NULL AUTO_INCREMENT,
  `no_type` varchar(20) NOT NULL COMMENT '알림 타입: comment, reply, good, word',
  `no_from_mb_id` varchar(20) NOT NULL COMMENT '알림을 발생시킨 회원 ID',
  `no_to_mb_id` varchar(20) NOT NULL COMMENT '알림을 받을 회원 ID',
  `no_bo_table` varchar(20) DEFAULT NULL COMMENT '게시판 테이블명',
  `no_wr_id` int(11) DEFAULT NULL COMMENT '게시글 ID',
  `no_comment_id` int(11) DEFAULT NULL COMMENT '댓글 ID (있는 경우)',
  `no_content` text COMMENT '알림 내용',
  `no_url` varchar(255) DEFAULT NULL COMMENT '이동할 URL',
  `no_is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '읽음 여부 (0: 안읽음, 1: 읽음)',
  `no_datetime` datetime NOT NULL COMMENT '알림 생성 시간',
  PRIMARY KEY (`no_id`),
  KEY `no_to_mb_id` (`no_to_mb_id`),
  KEY `no_is_read` (`no_is_read`),
  KEY `no_datetime` (`no_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='알림 테이블';
";

$result = sql_query($sql, false);

if ($result) {
    echo "알림 테이블이 성공적으로 생성되었습니다.";
} else {
    echo "테이블 생성 중 오류 발생: " . sql_error();
}
