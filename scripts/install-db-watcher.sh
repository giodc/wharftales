#!/bin/bash
#
# Install database update watcher as systemd service
#

cat > /etc/systemd/system/wharftales-db-update-watcher.service <<'EOF'
[Unit]
Description=WharfTales Database Update Watcher
After=docker.service

[Service]
Type=oneshot
User=wharftales
Group=wharftales
ExecStart=/opt/wharftales/scripts/db-update-watcher.sh
StandardOutput=append:/opt/wharftales/logs/db-watcher.log
StandardError=append:/opt/wharftales/logs/db-watcher.log

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/wharftales-db-update-watcher.timer <<'EOF'
[Unit]
Description=WharfTales Database Update Watcher Timer
Requires=wharftales-db-update-watcher.service

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable wharftales-db-update-watcher.timer
systemctl start wharftales-db-update-watcher.timer

echo "Database update watcher installed and started!"
echo "Check status with: systemctl status wharftales-db-update-watcher.timer"
