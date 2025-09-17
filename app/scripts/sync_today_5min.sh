#!/usr/bin/env bash
set -Eeuo pipefail

# Botola (MA), Ligue 1 (FR), Premier League (GB), La Liga (ES), Serie A (IT)
LEAGUES=(200 201 61 39 140 135)
SEASON=$(date -u +%Y)
TODAY_UTC=$(date -u +%F)

run() { docker exec sofascore_php bash -lc "php /var/www/app/bin/console $*"; }

for L in "${LEAGUES[@]}"; do
  run app:import:matches --league="$L" --season="$SEASON" --date="$TODAY_UTC" || true
  sleep 0.5
done
