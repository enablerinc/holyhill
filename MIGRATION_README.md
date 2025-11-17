# 대댓글 구조 개선 마이그레이션 가이드

## 개요
기존의 `wr_comment_reply` 문자열 정렬 방식에서 `wr_comment_parent` 필드를 사용한 명확한 부모-자식 관계 방식으로 개선되었습니다.

이 변경으로 100개 이상의 댓글에서도 정렬이 깨지지 않으며, 인스타그램처럼 대댓글이 부모 댓글 바로 아래에 표시됩니다.

## 마이그레이션 실행 방법

### 방법 1: PHP 스크립트 사용 (권장)

1. 브라우저에서 다음 URL 접속:
   ```
   http://yourdomain.com/bbs/migration_add_comment_parent.php?password=your_temp_password_here
   ```

2. 스크립트 실행 전에 `migration_add_comment_parent.php` 파일을 열어서:
   - 6번째 줄의 `$admin_password` 값을 임시 비밀번호로 변경
   - 예: `$admin_password = 'my_secure_password_123';`

3. URL에 해당 비밀번호를 포함하여 접속:
   ```
   http://yourdomain.com/bbs/migration_add_comment_parent.php?password=my_secure_password_123
   ```

4. 실행이 완료되면:
   - 각 게시판별로 `wr_comment_parent` 컬럼이 추가됨
   - 기존 대댓글 데이터도 자동으로 마이그레이션됨
   - **중요**: 실행 완료 후 `migration_add_comment_parent.php` 파일을 즉시 삭제하세요!

### 방법 2: SQL 직접 실행

1. `migration_add_comment_parent.sql` 파일을 phpMyAdmin에서 열기

2. 사용 중인 게시판 테이블 확인:
   ```sql
   SELECT bo_table FROM g5_board;
   ```

3. 각 게시판 테이블에 대해 다음 SQL 실행:
   ```sql
   ALTER TABLE `g5_write_게시판명`
   ADD COLUMN `wr_comment_parent` int(11) NOT NULL DEFAULT 0 COMMENT '부모 댓글 ID'
   AFTER `wr_comment_reply`;
   ```

4. 기존 대댓글 데이터 마이그레이션 (선택사항):
   ```sql
   -- 각 게시판별로 실행
   UPDATE g5_write_게시판명 AS c1
   LEFT JOIN g5_write_게시판명 AS c2
     ON c2.wr_parent = c1.wr_parent
     AND c2.wr_is_comment = 1
     AND c2.wr_comment_reply = SUBSTRING(c1.wr_comment_reply, 1, 10)
   SET c1.wr_comment_parent = c2.wr_id
   WHERE c1.wr_is_comment = 1
   AND LENGTH(c1.wr_comment_reply) > 10;
   ```

## 변경된 파일

1. **app/html/bbs/comment_write_ajax.php**
   - 댓글 작성 시 `wr_comment_parent` 필드에 부모 댓글 ID 저장

2. **app/html/bbs/post.php**
   - `wr_comment_parent` 필드를 사용하여 부모-자식 관계 조회
   - 부모 댓글 바로 아래에 대댓글 표시

3. **app/html/bbs/comment_check.php**
   - 실시간 polling에서 `wr_comment_parent` 사용

## 주요 개선사항

### 이전 방식 (wr_comment_reply 기반)
- 문자열 정렬에 의존
- 100개 이상일 때 정렬 오류 가능
- 부모-자식 관계 파악이 복잡함

### 개선된 방식 (wr_comment_parent 기반)
- 명확한 부모 댓글 ID 참조
- 댓글 개수와 무관하게 정확한 정렬
- 간단하고 명확한 부모-자식 관계

## 데이터베이스 구조

```sql
wr_comment_parent INT(11) NOT NULL DEFAULT 0 COMMENT '부모 댓글 ID'
```

- 값이 `0`이면 일반 댓글 (최상위 댓글)
- 값이 `0`보다 크면 대댓글 (해당 값이 부모 댓글의 wr_id)

## 호환성

- 기존 `wr_comment_reply` 필드는 그대로 유지됩니다
- 기존 댓글 데이터에 영향을 주지 않습니다
- 마이그레이션 후에도 이전 방식으로 롤백 가능합니다

## 문제 해결

### 컬럼 추가 실패
- 데이터베이스 권한 확인
- 테이블 이름 확인 (`g5_write_게시판명` 형식)

### 기존 대댓글이 제대로 표시되지 않음
- 마이그레이션 스크립트를 다시 실행하세요
- 또는 수동으로 SQL 업데이트 실행

## 지원

문제가 발생하면 다음을 확인하세요:
1. 마이그레이션이 정상적으로 완료되었는지 확인
2. `wr_comment_parent` 컬럼이 추가되었는지 확인
3. 브라우저 캐시 삭제 후 재시도
