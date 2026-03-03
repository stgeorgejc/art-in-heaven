#!/usr/bin/env bash
# =============================================================================
# Server Monitoring Script for Load Testing
#
# Run this on the VPS via SSH in a separate terminal while k6 tests execute.
#
# Usage:
#   ssh user@server 'bash -s' < load-tests/monitoring/server-checks.sh
#   # or copy to server and run:
#   chmod +x server-checks.sh && ./server-checks.sh
# =============================================================================

set -euo pipefail

INTERVAL=${1:-5}  # polling interval in seconds (default: 5)

echo "=================================================="
echo "  Art in Heaven — Load Test Server Monitor"
echo "  Interval: ${INTERVAL}s | Press Ctrl+C to stop"
echo "=================================================="

while true; do
  echo ""
  echo "--- $(date '+%Y-%m-%d %H:%M:%S') ---"

  # CPU load averages
  echo ""
  echo "[CPU] Load average:"
  uptime

  # Memory usage
  echo ""
  echo "[Memory]"
  free -h | head -2

  # MySQL connections
  echo ""
  echo "[MySQL] Active connections:"
  mysql -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null || echo "  (mysql access denied — run as root or configure .my.cnf)"

  # MySQL slow queries
  mysql -e "SHOW STATUS LIKE 'Slow_queries';" 2>/dev/null || true

  # Apache/httpd connections (if running)
  if pgrep -x httpd > /dev/null 2>&1; then
    echo ""
    echo "[Apache] Worker processes:"
    pgrep -c httpd 2>/dev/null || echo "  0"
  fi

  # Nginx connections (if running)
  if pgrep -x nginx > /dev/null 2>&1; then
    echo ""
    echo "[Nginx] Worker processes:"
    pgrep -c nginx 2>/dev/null || echo "  0"
  fi

  # PHP-FPM processes (if running)
  if pgrep -f php-fpm > /dev/null 2>&1; then
    echo ""
    echo "[PHP-FPM] Worker processes:"
    pgrep -cf php-fpm 2>/dev/null || echo "  0"
  fi

  # TCP socket summary
  echo ""
  echo "[Network] Socket summary:"
  ss -s 2>/dev/null | head -4 || netstat -s 2>/dev/null | head -4 || echo "  (ss/netstat not available)"

  # PHP session files
  echo ""
  echo "[Sessions] PHP session file count:"
  for dir in /var/lib/php/sessions /var/lib/php/session /tmp; do
    if [ -d "$dir" ]; then
      count=$(find "$dir" -maxdepth 1 -name 'sess_*' 2>/dev/null | wc -l)
      if [ "$count" -gt 0 ]; then
        echo "  $dir: $count files"
      fi
    fi
  done

  # Disk I/O (if iostat is available)
  if command -v iostat > /dev/null 2>&1; then
    echo ""
    echo "[Disk I/O]"
    iostat -x 1 1 2>/dev/null | tail -5 || true
  fi

  sleep "$INTERVAL"
done
