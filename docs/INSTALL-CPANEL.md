# ติดตั้งบน cPanel

ติดตั้งใหม่ด้วย [ZIP พร้อมใช้งานและข้อกำหนด](INSTALL-EASY.md) ห้ามใช้ฐานข้อมูลร่วมกับ WordPress หรือเว็บอื่น
ชื่อเมนูขึ้นกับผู้ให้บริการ ถ้าเปลี่ยน Document Root ไม่ได้ให้ติดต่อโฮสต์ก่อนอัปโหลดข้อมูลจริง

## เว็บไซต์และฐานข้อมูล

1. Domains → สร้าง/เลือกโดเมนหรือซับโดเมนเฉพาะระบบนี้
2. MultiPHP Manager หรือ Select PHP Version → PHP 8.3 ขึ้นไป และส่วนขยายที่ตัวติดตั้งระบุ
3. File Manager → อัปโหลด ZIP จาก Releases → Extract ได้ `floodthai`
4. กำหนด Document Root ไป `floodthai/public` เช่น `/home/ACCOUNT/floodthai/public` ตรวจตำแหน่งจริง ห้ามวางทั้งแอปใน public_html ที่เปิดสู่เว็บ
5. SSL/TLS Status → เปิด HTTPS และ redirect HTTPS
6. MySQL Database Wizard/Manage My Databases → สร้างฐานข้อมูลว่าง + ผู้ใช้ใหม่ → Add User To Database ให้สิทธิ์ในฐานข้อมูลนี้เท่านั้น
7. จดชื่อเต็มรวม prefix เช่น `ACCOUNT_floodthai` และ host/port ที่โฮสต์แจ้ง

## ติดตั้ง

1. เปิด `https://โดเมน/install.php` ตรวจความพร้อม แล้วอ่าน token จาก `storage/app/installer/setup-key.php` ผ่าน File Manager
2. ยืนยัน token กรอกฐานข้อมูล ตั้งผู้ดูแลของตัวเอง เลือกโฮสต์ทั่วไป และดำเนินแต่ละขั้นจนตัวติดตั้งล็อก
3. เข้าระบบ → ตั้งยืนยันตัวตน 2 ชั้น → `/admin/setup` ทำขั้นจังหวัด/หน่วยงาน/เจ้าหน้าที่
4. PHP ต้องเขียน `storage` กับ `bootstrap/cache` ได้ ไม่ตั้ง permission 777 ทั้งแอป

## Cron Jobs

1. Cron Jobs → Add New Cron Job → Common Settings: Once Per Minute
2. Minute/Hour/Day/Month/Weekday เป็น `*` ทั้งหมด
3. Command ใช้ PHP CLI 8.3 ขึ้นไป และ path ที่ตรงเครื่อง เช่น:

```sh
cd /home/ACCOUNT/floodthai && /PATH/TO/PHP83 artisan flood:cron
```

อย่าคัดลอก ACCOUNT หรือ PHP path ตัวอย่างตรง ๆ ขอ CLI path จากโฮสต์ บางเครื่อง PHP เว็บกับคำสั่งเป็นคนละรุ่น
ระหว่างทดสอบเก็บอีเมลแจ้งข้อผิดพลาด Cron ไว้ ตรวจรอบถัดไปที่ `/admin/setup` ขั้น 4: Scheduler และ Queue ต้องล่าสุด
ไม่ต้องเปิด queue worker ค้างในโหมดนี้ และไม่ตั้ง `schedule:run` ซ้ำกับ `flood:cron`

## เปิดใช้งานและอัปเดต

ทำรายการทดสอบในขั้น 5 ตรวจขอบเขตจริง แหล่งข้อมูล และสำรอง/กู้คืน แล้วเปิดรับเหตุเองในหน้าตั้งค่าจังหวัด
ถ้าเว็บ 500 ตรวจ log โดยไม่เปิด debug สาธารณะ; 404 ตรวจ Document Root และ rewrite; งานไม่รันตรวจ PHP CLI/`proc_open`
อัปเดตตาม [DEPLOY.md](DEPLOY.md) สำรองก่อน เก็บ `.env`/`APP_KEY`/ไฟล์อัปโหลด ห้ามใช้ตัวติดตั้งใหม่หรือ `migrate:fresh` กับข้อมูลเดิม
แจกต่อให้แนบ [LICENSE.md](../LICENSE.md) และคงเครดิตผู้จัดทำ
