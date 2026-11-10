#!/bin/sh
# 성산교회 SNS - NAS 자동 배포
# GitHub main 브랜치에 새 커밋이 있으면 받아온다 (DSM 작업 스케줄러에서 5분마다 실행).
# app/html 이 컨테이너에 그대로 연결되어 있으므로 파일을 받으면 곧바로 반영된다.
#
# 안전장치
# - fast-forward 만 허용: NAS에서 직접 고친 파일과 충돌하면 덮어쓰지 않고 멈춘다.
# - 동시에 두 번 실행되지 않도록 잠금.
# - 설정(config.php, data/dbconfig.php), 업로드, DB 데이터는 .gitignore 대상이라 건드리지 않는다.
set -eu

REPO_DIR="${REPO_DIR:-/volume1/docker/nas-community-sns}"
BRANCH="${BRANCH:-main}"
LOG_FILE="${LOG_FILE:-$REPO_DIR/logs/auto-deploy.log}"
LOCK_DIR="/tmp/holyhill-auto-deploy.lock"

mkdir -p "$(dirname "$LOG_FILE")"
log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "$LOG_FILE"; }

mkdir "$LOCK_DIR" 2>/dev/null || exit 0
trap 'rmdir "$LOCK_DIR"' EXIT

cd "$REPO_DIR"

if ! git fetch -q origin "$BRANCH" 2>>"$LOG_FILE"; then
    log "실패: GitHub에서 받아오지 못함 (네트워크 또는 배포 키 확인)"
    exit 1
fi

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse "origin/$BRANCH")
[ "$LOCAL" = "$REMOTE" ] && exit 0

if git merge --ff-only -q "origin/$BRANCH" 2>>"$LOG_FILE"; then
    log "배포 완료: $(git log --oneline -1)"
else
    log "실패: NAS에서 직접 수정된 파일과 충돌. 'git status' 로 확인 필요 (아무 것도 덮어쓰지 않음)"
    exit 1
fi
