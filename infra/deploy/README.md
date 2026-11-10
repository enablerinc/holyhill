# NAS 자동 배포

GitHub `main` 브랜치에 올라간 변경을 NAS가 5분마다 확인해서 자동으로 받아옵니다.
`app/html` 폴더가 컨테이너에 그대로 연결되어 있어서, 파일을 받으면 곧바로 사이트에 반영됩니다.

- 프로젝트 폴더: `/volume1/docker/nas-community-sns`
- 배포 스크립트: `infra/deploy/nas-auto-deploy.sh`
- 배포 기록: `logs/auto-deploy.log`

## 건드리지 않는 것

다음은 `.gitignore` 대상이라 배포가 덮어쓰거나 지우지 않습니다.

- `app/html/config.php`, `app/html/data/` (DB 접속 정보 `dbconfig.php`, 캐시, 세션, 썸네일)
- `app/uploads/` (첨부파일)
- `data/db/`, `data/redis/` (DB 데이터)
- `infra/docker-compose.yml`

NAS에서 직접 고친 파일이 GitHub 변경과 겹치면 **덮어쓰지 않고 멈춥니다.** 기록에 "실패"가 남습니다.

---

## 최초 설정 (1회)

DSM에 SSH로 접속해서 진행합니다.
(제어판 → 터미널 및 SNMP → SSH 서비스 활성화, 접속 후 `sudo -i`)

### 1. Git 설치

패키지 센터에서 **Git Server**를 설치합니다.

```sh
git --version
```

### 2. 백업

```sh
cd /volume1/docker
tar czf holyhill-backup-$(date +%Y%m%d).tgz -C nas-community-sns app/html infra
```

### 3. Synology Drive 동기화 끄기

지금까지는 PC의 SynologyDrive 폴더에서 이 폴더로 동기화해 왔습니다.
git과 Synology Drive가 같은 파일을 동시에 바꾸면 충돌하므로, **이 프로젝트 폴더의 동기화를 중지**합니다.
(PC의 Synology Drive Client에서 해당 동기화 작업 일시 중지 또는 삭제)

### 4. 배포 키 만들기 (읽기 전용)

```sh
mkdir -p /volume1/docker/.deploy && chmod 700 /volume1/docker/.deploy
ssh-keygen -t ed25519 -N "" -C "holyhill-nas-deploy" -f /volume1/docker/.deploy/holyhill_deploy_key
cat /volume1/docker/.deploy/holyhill_deploy_key.pub
```

출력된 한 줄을 GitHub에 등록합니다.
GitHub → `enablerinc/holyhill` → Settings → Deploy keys → Add deploy key
- Title: `NAS 자동 배포`
- Key: 위에서 출력된 내용
- **Allow write access 는 체크하지 않음** (읽기 전용)

연결 확인:

```sh
ssh -i /volume1/docker/.deploy/holyhill_deploy_key -o IdentitiesOnly=yes -T git@github.com
# "Hi enablerinc/holyhill! You've successfully authenticated" 가 나오면 성공
```

### 5. 프로젝트 폴더를 git으로 연결

```sh
cd /volume1/docker/nas-community-sns
git init
git checkout -b main
git config core.sshCommand "ssh -i /volume1/docker/.deploy/holyhill_deploy_key -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
git remote add origin git@github.com:enablerinc/holyhill.git
git fetch origin main

# 파일은 그대로 두고 기준만 GitHub main 에 맞춤
git reset origin/main

# 서버 파일과 GitHub 의 실제 차이 확인 (Windows 줄바꿈 차이는 제외)
git diff --stat --ignore-cr-at-eol
```

`git diff` 결과 확인:
- 아무것도 안 나오거나, 이번에 고친 파일(index.php 등)만 나오면 정상입니다.
- **서버에서 직접 고친 적 있는 파일이 나오면 여기서 멈추고** 그 목록을 알려주세요.

이상이 없으면 GitHub 버전으로 맞춥니다.

```sh
git reset --hard origin/main
sh infra/deploy/nas-auto-deploy.sh; echo "exit=$?"
```

### 6. 작업 스케줄러 등록

DSM 제어판 → 작업 스케줄러 → 생성 → 예약된 작업 → 사용자 정의 스크립트

- 일반: 작업 이름 `holyhill 자동 배포`, 사용자 **root**
- 스케줄: 매일, 첫 실행 시간 00:00, 반복 **5분마다**, 마지막 실행 시간 23:55
- 작업 설정 → 사용자 정의 스크립트:

  ```sh
  sh /volume1/docker/nas-community-sns/infra/deploy/nas-auto-deploy.sh
  ```

저장 후 목록에서 작업을 선택하고 **실행**을 눌러 한 번 돌려봅니다.

### 7. 확인

```sh
tail -5 /volume1/docker/nas-community-sns/logs/auto-deploy.log
```

배포됐는데 사이트에 반영되지 않으면 PHP 캐시 설정을 확인합니다.

```sh
docker exec comm-php php -i | grep opcache.validate_timestamps
```

`Off` 이면 `docker restart comm-php` 를 실행하세요.
배포 때마다 반영되게 하려면 이 값을 `On` 으로 바꿔야 합니다.

---

## 평소 운영

- 코드는 GitHub `main` 에만 반영하면 5분 안에 NAS에 올라갑니다. 파일을 직접 복사하지 않습니다.
- **되돌리기:** NAS에서 `git reset` 으로 되돌려도 5분 뒤 다시 최신으로 올라갑니다. 되돌릴 때는 GitHub `main` 에서 되돌리는 커밋을 올립니다.
- **배포 일시 중지:** 작업 스케줄러에서 `holyhill 자동 배포` 를 사용 안 함으로 바꿉니다.
