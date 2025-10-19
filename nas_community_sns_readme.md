# NAS 기반 커뮤니티(SNS형) 구축 가이드

> Synology DS923+ + Docker + Nginx + PHP-FPM + MariaDB + Redis + 그누보드5(G5)
>
> 목적: 인스타그램 유사 피드(글·사진), 댓글/대댓글, 좋아요/추천, 출석체크, 활동점수/월간 랭킹, 우수 회원 포상까지 운영 가능한 커뮤니티를 50명 규모로 안정적으로 운영한다.

---

## 0) 리포지토리 구조(제안)
```
nas-community-sns/
├─ README.md                # 이 파일
├─ infra/
│  ├─ docker-compose.yml
│  ├─ nginx.conf
│  ├─ php-overrides.ini
│  └─ initdb/
│     └─ 00_init.sql       # (선택) DB 초기 스키마/계정
├─ app/
│  ├─ html/                # 그누보드 코드가 배치될 폴더(컨테이너 마운트)
│  └─ plugins/             # 스킨/플러그인 백업
├─ scripts/
│  ├─ backup_db.sh
│  ├─ restore_db.sh
│  └─ crontab.example
└─ docs/
   ├─ SCORE_RULES.md       # 활동 점수 정책 상세
   ├─ ATTENDANCE.md        # 출석 플러그인 설정 가이드
   └─ OPERATIONS.md        # 운영·백업·보안 가이드
```

---

## 1) 하드웨어 & 네트워크 요약
- **도메인**: holyhill.net (예: community.holyhill.net 또는 sns.holyhill.net 서브도메인 적용 예정)
- **NAS**: Synology DS923+
- **RAM**: 기본 4GB + 삼성 DDR4 16GB = 총 20GB
- **HDD**: WD Red Plus 4TB × 2 (권장 RAID1)
- **NVMe**: WD Blue SN5000 500GB × 1 (SSD 캐시)
- **네트워크**: 1GbE, 외부 노출은 Cloudflare Tunnel 또는 DSM 리버스 프록시 + Let’s Encrypt

---

## 2) 전체 아키텍처
- **Nginx(리버스 프록시, 정적 캐시)** ↔ **PHP-FPM(8.2)** ↔ **MariaDB(10.11)** ↔ **Redis(세션/캐시)**
- **그누보드5**: 갤러리형 피드 + 댓글/대댓글 + 추천/좋아요 + 출석/포인트/랭킹
- **NVMe SSD 캐시**: Storage Manager → SSD Cache (R/W)

아키텍처 다이어그램(개요):
```
[Internet]
   │ HTTPS(443)
[Cloudflare / Router]
   │
[NAS: Nginx] ── FastCGI ──> [PHP-FPM]
   │                         │
   │                         ├── PDO ──> [MariaDB]
   │                         └── Redis ─> [Redis]
   └─ /data(uploads)  ─────> HDD RAID1 (NVMe 캐시)
```

---

## 3) 작업(Task) 로드맵

### 단계 A. NAS 초기화
1. HDD 장착 → DSM 설치 → 관리자 계정·업데이트
2. 스토리지 풀/볼륨 생성 (HDD 1→RAID1 예정 시 SHR 권장)
3. NVMe 장착 → **Storage Manager > SSD Cache** 구성 (Read/Write)
4. **패키지 설치**: Docker(또는 Container Manager), File Station, Text Editor
5. **보안**: 관리자 계정 보호, 방화벽, 2FA, 외부 포트 제한

### 단계 B. Docker 환경 준비
- 경로 생성: `/volume1/docker/nas-community-sns/`
- 파일 배치: `infra/docker-compose.yml`, `infra/nginx.conf`, `infra/php-overrides.ini`
- 실행: `docker compose -f infra/docker-compose.yml up -d`

### 단계 C. DB/웹 애플리케이션 설치
1. `app/html/`에 **그누보드5 최신본** 배치
2. 브라우저에서 `http://<NAS IP>:8080` → 설치 마법사 완료
3. 관리자 `/adm` 접속, 기본 환경 설정

### 단계 D. 커뮤니티 UX 기능 적용
- 갤러리 스킨 적용(피드 메인)
- 좋아요/추천 버튼 노출 및 포인트 연동
- 출석부 플러그인 설치/세팅
- 활동 점수 룰 반영(글/댓글/추천/출석)
- 월간 랭킹 위젯/페이지 구성

### 단계 E. 도메인 & HTTPS
- Cloudflare(권장) 또는 DSM Reverse Proxy + Let’s Encrypt로 `https://community.example.com` 연결

### 단계 F. 운영/백업 자동화
- DB 일일 덤프 + 업로드 폴더 스냅샷
- 로그/모니터링, Watchtower(선택), 업타임 체크

---

## 4) Docker Compose (예시)
```yaml
version: "3.9"
services:
  db:
    image: mariadb:10.11
    container_name: comm-db
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: change_me_root
      MYSQL_DATABASE: gnuboard
      MYSQL_USER: gnu
      MYSQL_PASSWORD: change_me_app
      TZ: Asia/Seoul
    volumes:
      - ../data/db:/var/lib/mysql
      - ./initdb:/docker-entrypoint-initdb.d:ro

  redis:
    image: redis:7-alpine
    container_name: comm-redis
    restart: unless-stopped
    command: ["redis-server","--appendonly","yes"]
    volumes:
      - ../data/redis:/data

  php:
    image: ghcr.io/thecodingmachine/php:8.2-v4-fpm
    container_name: comm-php
    restart: unless-stopped
    environment:
      PHP_EXTENSIONS: >-
        gd mbstring mysqli pdo_mysql zip exif intl opcache
      TEMPLATE_PHP_INI: production
      PHP_INI_MEMORY_LIMIT: 512M
      PHP_INI_UPLOAD_MAX_FILESIZE: 256M
      PHP_INI_POST_MAX_SIZE: 280M
      PHP_INI_MAX_EXECUTION_TIME: 120
      PHP_INI_DATE_TIMEZONE: Asia/Seoul
    working_dir: /var/www/html
    volumes:
      - ../app/html:/var/www/html
      - ../app/uploads:/var/www/html/data
      - ./php-overrides.ini:/usr/local/etc/php/conf.d/zz-overrides.ini
    depends_on: [db, redis]

  nginx:
    image: nginx:1.27
    container_name: comm-nginx
    restart: unless-stopped
    ports:
      - "8080:80"
    volumes:
      - ../app/html:/var/www/html:ro
      - ../app/uploads:/var/www/html/data
      - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on: [php]
```

**`infra/nginx.conf`**
```nginx
server {
  listen 80;
  server_name _;

  root /var/www/html;
  index index.php index.html;

  location / {
    try_files $uri $uri/ /index.php?$args;
  }

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass comm-php:9000;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_read_timeout 180;
  }

  location ~* \.(?:css|js|jpg|jpeg|png|gif|webp|svg|ico|mp4|m4v)$ {
    expires 7d;
    add_header Cache-Control "public";
    access_log off;
  }
}
```

**`infra/php-overrides.ini`**
```ini
session.save_handler = redis
session.save_path = "tcp://comm-redis:6379"
opcache.enable=1
opcache.enable_cli=1
opcache.validate_timestamps=1
opcache.revalidate_freq=2
upload_max_filesize=256M
post_max_size=280M
memory_limit=512M
max_execution_time=120
```

---

## 5) 필수 스킨/플러그인 목록(초안)

### A. UI/피드
- 갤러리형 게시판 스킨 (G5용 Bootstrap5 or 모던 갤러리)
- 반응형 메인(최신글/인기글 위젯)

### B. 참여 기능
- 좋아요/추천 확장(게시글/댓글 Good/Bad)
- 태그 기능(게시판 확장 스킨)

### C. 출석/포인트/랭킹
- **출석부(Attendance) 플러그인**
- **포인트 시스템**: G5 기본 (환경설정에서 배점)
- **랭킹/월간 우수회원**: 포인트 랭킹 위젯 또는 커스텀 페이지

### D. 운영/보안
- reCAPTCHA(회원가입/글쓰기 스팸 방지)
- 관리자 로그/접속 기록 뷰어

> 설치 출처: https://sir.kr 자료실(테마/스킨/플러그인). 상용 스킨은 별도 라이선스 확인.

---

## 6) 활동 점수 정책(예시)
> 상세는 `docs/SCORE_RULES.md`도 참고.

| 활동 | 점수 | 제한 |
|------|------|------|
| 출석 체크 | +5 | 1일 1회 |
| 글 작성 | +10 | 게시판별 가중치 가능 |
| 댓글 작성 | +2 | 스팸 방지(최소 글자 수) |
| 글 추천 받음 | +3 | 동일 사용자 중복 방지 |
| 추천 참여 | +1 | 과도한 반복 제한 |

- **월말 집계**: 월별 포인트 합산 → Top N 발표 + 리워드
- **자동화**: 크론 스케줄러(예: 매월 1일 00:10)로 집계 스크립트 실행

---

## 7) 도메인/SSL 설정
- Cloudflare에 도메인 추가 → 프록시/SSL "Full"
- DSM > 보안 > 인증서: Let’s Encrypt 발급 후 리버스 프록시에 바인딩
- 라우터 포트포워딩(필요 시): 80, 443 → NAS

---

## 8) 백업 & 운영
- `scripts/backup_db.sh`: 일일 덤프 + 보관(7~30일)
- 업로드 폴더: NAS 스냅샷/Hyper Backup 주간 스케줄
- 모니터링: DSM 리소스 모니터, Uptime Kuma(선택)

**`scripts/backup_db.sh` (샘플)**
```bash
#!/usr/bin/env bash
set -euo pipefail
TS=$(date +%F_%H%M)
BACKUP_DIR="/volume1/backup/db"
mkdir -p "$BACKUP_DIR"
docker exec comm-db mysqldump -uroot -pchange_me_root gnuboard \
  | gzip > "$BACKUP_DIR/gnuboard_$TS.sql.gz"
find "$BACKUP_DIR" -type f -mtime +30 -delete
```

---

## 9) 빠른 시작(요약)
1. NAS 초기화/업데이트 → SSD 캐시 구성
2. `/volume1/docker/nas-community-sns/` 생성 후 이 리포지토리 파일 배치
3. `docker compose -f infra/docker-compose.yml up -d`
4. `app/html/`에 그누보드 업로드 → 설치
5. 스킨/플러그인 적용, 포인트·출석·랭킹 룰 설정
6. 도메인/SSL 연결 → 공개 운영

---

## 10) 라이선스 & 저작권
- 본 인프라 스크립트/설정은 MIT 라이선스 권장(선택)
- 스킨/플러그인은 각 배포처 라이선스 준수

---

## 11) To-Do (오픈 이슈)
- [ ] 갤러리 메인 스킨 선정 및 적용
- [ ] 출석부 플러그인 후보 비교/선정
- [ ] 월간 랭킹 집계 스크립트 초안 작성
- [ ] 관리자 대시보드(운영 지표) 페이지
- [ ] Cloudflare Tunnel vs DSM RP 최종 결정

---

문의/유지보수: 운영자(야곱) / 기여: PR 환영

