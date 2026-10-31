#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(git rev-parse --show-toplevel)"
MODE="${1:-status}"
BRANCH="$(git symbolic-ref --quiet --short HEAD)" || { echo 'HEAD détaché : sélectionnez une branche de travail.' >&2; exit 1; }
case "$MODE" in
  status)
    git status --short --branch
    git log -1 --oneline
    ;;
  sync|publish)
    case "$BRANCH" in work/*|feat/*|fix/*) ;; *) echo "Opération refusée sur $BRANCH. Utilisez work/linux, work/mac ou une branche feat/…" >&2; exit 1;; esac
    if [[ -n "$(git status --porcelain)" ]]; then
      echo 'Enregistrez vos modifications dans un commit, ou mettez-les de côté avec git stash, avant de continuer.' >&2
      exit 1
    fi
    git fetch origin
    if [[ "$MODE" == sync ]]; then
      if git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
        git merge --no-edit "origin/$BRANCH"
      fi
      git merge --no-edit origin/develop
      echo 'Code synchronisé. Si nécessaire : sail composer install ; sail npm ci ; sail artisan migrate ; sail npm run build.'
    else
      git push --set-upstream origin "$BRANCH"
      echo 'Branche publiée. Ouvrez une Pull Request vers develop ; aucune publication Plesk.'
    fi
    ;;
  *) echo 'Usage : bash tools/dev/sync.sh status|sync|publish' >&2; exit 2;;
esac
