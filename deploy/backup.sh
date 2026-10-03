#!/usr/bin/env bash
# สำรองฐานข้อมูล + ไฟล์รูปที่ผู้ใช้อัปโหลด เก็บ 14 วัน
# ตั้งรหัสผ่าน DB ใน ~/.my.cnf ของผู้ใช้ที่รัน (ห้ามใส่ในสคริปต์)
set -euo pipefail

APP=/var/www/floodthai
DEST=${BACKUP_DIR:-/var/backups/floodthai}
DB=$(grep -E '^DB_DATABASE=' "$APP/.env" | cut -d= -f2-)
STAMP=$(date +%Y%m%d-%H%M)
mkdir -p "$DEST"

mysqldump --single-transaction --quick --routines "$DB" | gzip > "$DEST/db-$STAMP.sql.gz"
tar -czf "$DEST/uploads-$STAMP.tar.gz" -C "$APP/storage/app" public

find "$DEST" -type f -mtime +14 -delete
echo "$(date '+%F %T') backup ok $STAMP"

# แนะนำ: ส่งสำเนาออกนอกเครื่อง เช่น rclone copy "$DEST" remote:floodthai-backup
