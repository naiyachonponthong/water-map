# ติดตั้งบน Plesk

ใช้กับการติดตั้งใหม่เท่านั้น เริ่มจาก [ข้อกำหนดและ ZIP](INSTALL-EASY.md) ใช้ฐานข้อมูลว่างแยกจากเว็บอื่น
ชื่อเมนูอาจต่างตามภาษา/รุ่น และบางโฮสต์ซ่อนการเปลี่ยน Document Root ให้ผู้ให้บริการช่วยตั้ง

## 1. เตรียมเว็บไซต์และไฟล์

1. Websites & Domains → เลือกโดเมนหรือซับโดเมนที่จะใช้ อย่าแก้โดเมนของเว็บอื่น
2. PHP Settings → PHP 8.3 ขึ้นไป เปิดส่วนขยายตามหน้าตรวจของตัวติดตั้ง
3. File Manager → อัปโหลด ZIP ที่แนบใน Releases แล้ว Extract จะได้โฟลเดอร์ `floodthai`
4. Hosting Settings → Document Root ชี้ไป `floodthai/public` ใต้พื้นที่ของโดเมน เช่น `water-map.example.com/floodthai/public` ตรวจ path จริงจาก File Manager
5. เปิด SSL/TLS certificate และบังคับ HTTPS; เว็บต้องเปิดได้ผ่านโดเมน HTTPS
6. PHP ต้องเขียน `storage` และ `bootstrap/cache` ได้ อย่าตั้งทั้งเว็บเป็น 777

ห้ามชี้ Document Root ไป `floodthai` เพราะจะเปิดไฟล์ตั้งค่า/ dependencies สู่สาธารณะ

## 2. ฐานข้อมูลและติดตั้งผ่านเว็บ

1. Databases → Add Database → เลือก Related site ให้ตรงเว็บไซต์นี้
2. สร้างฐานข้อมูลว่างและผู้ใช้ใหม่ จดชื่อเต็มรวม prefix เช่น `account_floodthai`
3. จำกัดผู้ใช้ให้ฐานข้อมูลนี้ และเลือก **Allow local connections only** เมื่อฐานข้อมูลอยู่บนเครื่องเดียวกับเว็บ ไม่ต้องเปิดจากทุก host
4. เปิด `https://โดเมน/install.php` แล้วอ่าน token จาก `floodthai/storage/app/installer/setup-key.php` ใน File Manager
5. กรอก token; กรอก database host/port ตาม Plesk และรหัสผ่านผู้ใช้ฐานข้อมูล ไม่ใช่รหัสผ่านเข้า Plesk
6. ตั้งเบอร์และรหัสผ่านผู้ดูแลใหม่ เลือกโหมดโฮสต์ทั่วไป แล้วดำเนินขั้นสร้างตาราง → seed → ล็อกตัวติดตั้ง
7. เข้าสู่ระบบ ตั้งยืนยันตัวตน 2 ชั้น เก็บรหัสสำรองส่วนตัว แล้วเปิด `/admin/setup`

ถ้าขึ้นชื่อฐานข้อมูล/ผู้ใช้มีอยู่แล้ว ไม่ต้องสร้างซ้ำ ตรวจฐานข้อมูลเดิมว่าเป็นของระบบนี้และยังว่าง
อย่าเผยแพร่ token หรือรหัสผ่านในภาพสาธารณะ; ถ้าเผยแพร่แล้วให้เปลี่ยนรหัสฐานข้อมูลและแก้ `.env` ให้ตรงกัน

## 3. Scheduled Tasks ทุกนาที

1. Websites & Domains → Scheduled Tasks → Add Task ภายใน subscription ของเว็บไซต์นี้
2. เลือก **Run a PHP script** ไม่ใช้ Fetch a URL
3. Script path → Choose File → เลือก `floodthai/artisan` ตามตำแหน่งจริง
4. With arguments → `flood:cron`
5. PHP version → 8.3 ขึ้นไป ตรงกับเว็บ
6. Run → Cron style → `* * * * *` และเปิด Active
7. Save → Run Now → ตรวจผลสำเร็จ; รอรอบถัดไปแล้วกลับ `/admin/setup` ขั้น 4 หรือ `/admin/hosting`

ทั้ง Scheduler และ Queue ต้องมีเวลา heartbeat ล่าสุด ไม่ใช่เพียงแค่บันทึก task ได้
ใน Plesk บางเครื่อง path ของ task อยู่ภายใน chroot ให้เลือกไฟล์ผ่าน Choose File แทนการคัดลอก path เต็ม `/var/www/vhosts/...`
ถ้า task เรียกไม่ได้ ให้ผู้ให้บริการตรวจ CLI PHP และ `proc_open` อย่าสร้างเว็บลิงก์ Cron สาธารณะ

## 4. ก่อนเปิดรับเหตุ

ทำผู้ช่วยทั้ง 5 ขั้น ใส่หน่วยงาน/เบอร์ฉุกเฉิน เพิ่มเจ้าหน้าที่และขอบเขตจริง ทดสอบแจ้งเหตุ → รับงาน → ปิดงาน
ทดสอบสำรองและกู้คืน ตรวจแหล่งข้อมูลภายนอกและเวลาอัปเดต แล้วจึงเปิดศูนย์/รับแจ้งจากหน้าตั้งค่า
การที่เว็บเปิดได้หรือขึ้นครบทุกขั้น ไม่รับรองความปลอดภัยของพื้นที่

## อัปเดตและแก้ปัญหา

- สำรองฐานข้อมูล, `.env`, `storage/app` และข้อมูลที่เกี่ยวข้องก่อนอัปเดต เก็บ `APP_KEY` เดิม
- ZIP สำหรับติดตั้งใหม่ไม่มีเครื่องมืออัปเดตผ่านเว็บ ใช้ [DEPLOY.md](DEPLOY.md) หรือให้ผู้ให้บริการช่วย migration ด้วย PHP CLI รุ่นถูกต้อง
- ห้ามลบ `storage`, ห้ามเปิดตัวติดตั้งกับข้อมูลเดิม และห้าม `migrate:fresh`
- หน้า 500: ตรวจ error log ใน Plesk และ `storage/logs` อย่าเปิด APP_DEBUG บนเว็บสาธารณะ
- 404 ทุกหน้า: ตรวจ Document Root และ URL rewriting ของโฮสต์
- Queue ไม่สด: ตรวจผล Run Now และสิทธิ์เขียน heartbeat; PHP เว็บกับ task ต้องรองรับระบบเหมือนกัน
- อ่านสิทธิ์แจกต่อและเครดิตใน [LICENSE.md](../LICENSE.md)
