#!/usr/bin/env bash
set -Eeuo pipefail

# Botola (MA), Ligue 1 (FR), Premier League (GB), La Liga (ES), Serie A (IT)
# LEAGUES=(200 61 39 140 135)
LEAGUES=(201)

YEAR_NOW=$(date -u +%Y)
START_YEAR=$((YEAR_NOW - 5))
END_YEAR=$YEAR_NOW

SLEEP_BETWEEN_COUNTRIES="${SLEEP_BETWEEN_COUNTRIES:-0.5}"
SLEEP_BETWEEN_LEAGUES="${SLEEP_BETWEEN_LEAGUES:-0.5}"
SLEEP_BETWEEN_YEARS="${SLEEP_BETWEEN_YEARS:-60}"

# Always use absolute path to console
run() { docker exec sofascore_php bash -lc "php /var/www/app/bin/console $*"; }

# (re)import leagues for these countries first (optional but helpful)
COUNTRIES=(MA FR GB ES IT)
echo "=== Importing leagues for: ${COUNTRIES[*]} ==="
for C in "${COUNTRIES[@]}"; do
  run app:import:leagues --country="$C" || true
  sleep "$SLEEP_BETWEEN_COUNTRIES"
done

echo
echo "Backfill seasons ${START_YEAR}..${END_YEAR} for leagues: ${LEAGUES[*]}"
for Y in $(seq "$START_YEAR" "$END_YEAR"); do
  echo "=== Season $Y ==="
  for L in "${LEAGUES[@]}"; do
    echo "-- league=$L season=$Y : TEAMS"
    run app:import:teams --league="$L" --season="$Y" || true

    echo "-- league=$L season=$Y : MATCHES"
    run app:import:matches --league="$L" --season="$Y" || true
    sleep "$SLEEP_BETWEEN_LEAGUES"
  done

  if [[ "$Y" -lt "$END_YEAR" ]]; then
    echo "Sleeping ${SLEEP_BETWEEN_YEARS}s before season $((Y+1))…"
    sleep "$SLEEP_BETWEEN_YEARS"
  fi
done

echo "Backfill done."
