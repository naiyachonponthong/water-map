# คู่มือขึ้นเซิร์ฟเวอร์จริง (FloodThai)

คู่มือนี้สำหรับ Ubuntu 24.04 เครื่องเดียว รองรับจังหวัดขนาดกลางช่วงเกิดเหตุได้สบาย
(แนะนำอย่างน้อย 4 vCPU / 8 GB RAM / SSD 80 GB) ถ้าดูแลหลายจังหวัดพร้อมกัน แยก MySQL ไปอีกเครื่อง

## 1. ติดตั้งซอฟต์แวร์

```bash
sudo apt update && sudo apt install -y nginx mysql-server supervisor git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
  php8.3-gd php8.3-intl php8.3-bcmath
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

ตั้งค่า PHP (`/etc/php/8.3/fpm/php.ini`): `upload_max_filesize = 10M`, `post_max_size = 30M`, `memory_limit = 256M`
และ `/etc/php/8.3/fpm/pool.d/www.conf`: `pm = dynamic`, `pm.max_children = 40` (ปรับตาม RAM)

## 2. ฐานข้อมูล

```sql
CREATE DATABASE floodthai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'floodthai'@'localhost' IDENTIFIED BY 'รหัสผ่านยาวๆ';
GRANT ALL ON floodthai.* TO 'floodthai'@'localhost';
```

## 3. วางโค้ด

```bash
sudo mkdir -p /var/www/floodthai && sudo chown $USER:www-data /var/www/floodthai
git clone <repo> /var/www/floodthai && cd /var/www/floodthai
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
```

แก้ `.env` อย่างน้อย:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://flood.example.go.th
APP_TIMEZONE=Asia/Bangkok

DB_DATABASE=floodthai
DB_USERNAME=floodthai
DB_PASSWORD=...

SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=127.0.0.1          # ถ้าอยู่หลัง Cloudflare/LB ใส่ช่วง IP ของ proxy หรือ *
PII_RETENTION_DAYS=180

BROADCAST_CONNECTION=reverb
REVERB_APP_SECRET=สุ่มใหม่ห้ามใช้ค่าตัวอย่าง
REVERB_HOST=flood.example.go.th
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

MAIL_...                           # ถ้าใช้
SUPERADMIN_PHONE=เบอร์ผู้ดูแลจริง
SUPERADMIN_PASSWORD=รหัสใหม่อย่างน้อย12ตัวห้ามใช้ค่าตัวอย่าง
LINE_LOGIN_CHANNEL_ID=...          # LINE Login สำหรับเจ้าหน้าที่ (ถ้าใช้)
LINE_LOGIN_CHANNEL_SECRET=...
```

> **APP_KEY สำคัญมาก** เบอร์โทรทั้งระบบเข้ารหัสด้วยคีย์นี้ ถ้าหายจะอ่านเบอร์ไม่ได้อีก เก็บสำเนาไว้ที่ปลอดภัยนอกเครื่อง

```bash
php artisan migrate --force
php artisan db:seed --force                 # จังหวัด อำเภอ บทบาท ผู้ดูแลระบบสูงสุด
php artisan flood:import-areas ไฟล์.geojson --level=subdistrict   # ขอบเขตตำบล (ดู README หัวข้อนำเข้าขอบเขต)
php artisan storage:link
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 4. Web server + HTTPS

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/floodthai   # แก้ชื่อโดเมน
sudo ln -s /etc/nginx/sites-available/floodthai /etc/nginx/sites-enabled/
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d flood.example.go.th
sudo nginx -t && sudo systemctl reload nginx
```

Realtime (Reverb) ใช้พอร์ต 443 เดียวกันผ่าน path `/app` และ `/apps` ไม่ต้องเปิดพอร์ตเพิ่ม

## 5. งานเบื้องหลัง

```bash
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/floodthai.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo crontab -u www-data deploy/crontab
```

งานที่ scheduler ทำให้:

| ทุก | คำสั่ง | หน้าที่ |
|---|---|---|
| 1 นาที | `flood:expire-offers` | งานที่ทีมไม่ตอบ กลับเข้าคิว |
| 5 นาที | `flood:evaluate-risks` | ตรวจจุดเสี่ยง เปิดเคสตรวจเยี่ยมเชิงรุก |
| 5 นาที | `flood:recalc-priority` | คะแนนเร่งด่วนเพิ่มตามเวลารอ |
| 10 นาที | `flood:fetch-stations` | ดึงค่าสถานีวัดน้ำจาก API |
| ชั่วโมงละครั้ง | `flood:fetch-forecast` | พยากรณ์ฝน Open-Meteo + เตือนฝนหนัก |
| ทุกวัน 03:30 | `flood:prune-data` | ลบเบอร์โทรที่เกินระยะเก็บ (PDPA) |
| ทุกวัน 03:00 | `model:prune` | ล้างประวัติการใช้งานเกิน 365 วัน |

ตรวจว่า scheduler ทำงาน: `php artisan schedule:list`

## 6. สำรองข้อมูล

`deploy/backup.sh` สำรองฐานข้อมูลและรูปที่อัปโหลด เก็บ 14 วัน (ตั้งรหัส DB ใน `~/.my.cnf` ของ www-data)
แนะนำส่งสำเนาออกนอกเครื่องด้วย rclone ไป Google Drive / S3 และ **ทดลองกู้คืนอย่างน้อยเดือนละครั้ง**

```bash
gunzip < db-20261003-0315.sql.gz | mysql floodthai     # กู้คืน
```

## 7. อัปเดตเวอร์ชัน

```bash
cd /var/www/floodthai && ./deploy/deploy.sh
```

## 8. เช็กลิสต์ก่อนเปิดใช้จริง

- [ ] `APP_DEBUG=false` และเปิด `https://โดเมน/up` ได้สถานะ 200
- [ ] ตั้งเบอร์/รหัสผู้ดูแลเฉพาะเว็บไซต์ก่อน seed และเปิดยืนยันตัวตน 2 ชั้น (production ไม่ยอม seed ด้วยรหัสตัวอย่าง; ระบบบังคับบทบาทใน `REQUIRE_2FA_ROLES` อยู่แล้ว)
- [ ] queue worker ทำงาน (`supervisorctl status`): ส่ง LINE และตรวจจุดเสี่ยงจากรายงานน้ำทำงานในคิว ถ้า worker หยุด ประกาศ LINE จะค้างสถานะ "กำลังส่ง"
- [ ] กรอกหน่วยงานผู้ควบคุมข้อมูลและช่องทางติดต่อ DPO (ตั้งค่า > ทั่วไป) ให้ฝ่ายกฎหมายตรวจหน้า `/{จังหวัด}/privacy`
- [ ] จอทีวีใช้บัญชีแยกที่มีสิทธิ์ดูแดชบอร์ดอย่างเดียว (หน้า `/admin/tv` ไม่หมดเวลาอัตโนมัติ)
- [ ] นำเข้าขอบเขตอำเภอ/ตำบล ตั้งเบอร์ฉุกเฉิน ลิงก์ข้อมูลน้ำ (หน้า "ตั้งค่า")
- [ ] สร้างทีมกู้ภัย ให้หัวหน้าทีมติดตั้งแอปภาคสนามบนมือถือ และทดลองรับงาน 1 รอบ
- [ ] ทดลองแจ้งขอความช่วยเหลือจากมือถือ ดูว่าเคสเด้งบนศูนย์สั่งการแบบสด
- [ ] ตั้ง LINE OA (ตั้งค่า > LINE OA) และทดลองส่งตำแหน่งในแชท
- [ ] เพิ่มสถานีวัดน้ำ จุดเสี่ยง ศูนย์พักพิงที่เตรียมไว้ (สถานะ "เตรียมเปิด")
- [ ] ทดลองสำรองและกู้คืนฐานข้อมูล
- [ ] เปิดจอทีวีศูนย์สั่งการที่ `/admin/tv`

## 9. รองรับคนเข้าพร้อมกันจำนวนมาก

**ทดสอบโหลดบนเครื่อง staging ก่อนฤดูน้ำหลาก** (ห้ามยิงเครื่องจริง):

```bash
php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder   # ข้อมูลตัวอย่างครบทุกส่วน
k6 run -e BASE=https://staging.example.go.th -e PROVINCE=chachoengsao deploy/loadtest/k6-public.js
# รวมการส่งรายงานน้ำ: ตั้ง HELP_IP_LIMIT=100000 REPORT_IP_LIMIT=100000 ใน .env ของ staging ชั่วคราว แล้วเพิ่ม -e WRITE=1
```

เกณฑ์ผ่านในสคริปต์: ผิดพลาดไม่เกิน 1%, หน้าแรก p95 ไม่เกิน 1.5 วินาที, แผนที่ p95 ไม่เกิน 1 วินาที ที่ 500 คนพร้อมกัน


- หน้า `/{จังหวัด}`, แผนที่, `open-data.json` แคช 30 ถึง 60 วินาทีอยู่แล้ว ถ้าวางหลัง Cloudflare ให้ตั้ง Cache Rule เฉพาะ `*/map.geojson` และ `*/open-data.json`
- เปลี่ยน `CACHE_STORE` และ `SESSION_DRIVER` เป็น `redis` เมื่อมีเจ้าหน้าที่ใช้พร้อมกันเกิน 100 คน (`apt install redis-server php8.3-redis`)
- จำกัดอัตราการส่งฟอร์มของประชาชนมีอยู่แล้ว (ขอความช่วยเหลือ 6 ครั้ง/30 นาที/เครื่อง, รายงานน้ำ 4 ครั้ง/10 นาที)
- ถ้า Reverb รับไม่ไหวหรือหยุด หน้าจอศูนย์สั่งการจะดึงข้อมูลทุก 20 วินาทีเองโดยไม่ต้องทำอะไร

## 10. ความปลอดภัยและข้อมูลส่วนบุคคล

- ยืนยันตัวตน 2 ชั้น (TOTP ใช้ Google/Microsoft Authenticator) บังคับบทบาทใน `REQUIRE_2FA_ROLES` (ค่าเริ่มต้น super-admin, province-admin, dispatcher) มีรหัสสำรอง 8 รหัส ผู้ดูแลล้างให้ได้ถ้าทำมือถือหาย
- หลังบ้านออกจากระบบเองเมื่อไม่ใช้งานเกิน `IDLE_TIMEOUT_MIN` นาที (ค่าเริ่มต้น 120) แอปภาคสนามและจอทีวีไม่นับ

- เบอร์โทรเข้ารหัสในฐานข้อมูล ค้นหาด้วย HMAC ไม่เก็บเลขบัตรประชาชน
- ส่งออกเบอร์เต็มได้เฉพาะผู้มีสิทธิ์ และถูกบันทึกในประวัติการใช้งานทุกครั้ง
- `flood:prune-data` ลบเบอร์ของเคสที่ปิดและผู้อพยพที่ออกจากศูนย์เกิน `PII_RETENTION_DAYS` วัน เหลือแต่ตัวเลขสถิติ
- ทุกการดึง URL ภายนอก (สถานีวัดน้ำ) ผ่านตัวกัน SSRF
- webhook LINE ตรวจลายเซ็นทุกครั้ง
- หน้าแผนที่ `/{จังหวัด}/map` อนุญาตให้เว็บอื่นฝังด้วย iframe ได้ หน้าอื่นไม่อนุญาต
