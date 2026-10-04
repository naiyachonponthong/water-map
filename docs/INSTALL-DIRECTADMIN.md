# ติดตั้งบน DirectAdmin

เริ่มจาก [ชุด ZIP และข้อกำหนด](INSTALL-EASY.md) สำหรับฐานข้อมูลว่างและโดเมนแยกของระบบนี้
DirectAdmin แต่ละเครื่องอนุญาตการตั้ง Document Root ต่างกัน ถ้าบัญชีผู้ใช้แก้ไม่ได้ให้ผู้ให้บริการตั้งก่อน ไม่ย้ายไฟล์ส่วนตัวเข้าพื้นที่สาธารณะ

## เตรียมเว็บไซต์

1. Domain Setup → เลือก/เพิ่มโดเมนหรือซับโดเมน เปิด PHP 8.3 ขึ้นไปใน PHP Version Selector ตามที่โฮสต์มี
2. File Manager → อัปโหลด ZIP พร้อม dependencies จาก Releases แล้ว Extract
3. ให้ Document Root ชี้ `floodthai/public` ตามตำแหน่งจริง ไม่ใช่โฟลเดอร์แอปหลักหรือ public_html ที่เก็บทุกไฟล์
4. SSL Certificates → เปิดใบรับรองและ HTTPS; ตรวจว่า https://โดเมน เปิดได้
5. MySQL Management → สร้างฐานข้อมูลว่างกับผู้ใช้ใหม่ จดชื่อเต็มรวม prefix; จำกัดสิทธิ์เฉพาะฐานข้อมูลนี้และใช้การเชื่อมต่อภายในถ้าอยู่เครื่องเดียวกัน
6. ตรวจสิทธิ์เขียน `storage` และ `bootstrap/cache` อย่าตั้ง 777 ทั้งเว็บ

## ติดตั้งและตั้งผู้ดูแล

เปิด `/install.php` อ่าน token ผ่าน File Manager ที่ `storage/app/installer/setup-key.php` แล้วกรอกข้อมูลตามขั้น
ใช้รหัสฐานข้อมูล ไม่ใช่รหัส DirectAdmin ตั้งเบอร์/รหัสผู้ดูแลเฉพาะเว็บไซต์ เลือกโหมดโฮสต์ทั่วไป
ดำเนินสร้างตาราง → seed → ล็อกติดตั้ง จากนั้นเข้าสู่ระบบ ตั้งยืนยัน 2 ชั้นและเก็บรหัสสำรอง แล้วเปิด `/admin/setup`

## Cron Jobs ทุกนาที

Advanced Features → Cron Jobs → Create Cron Job (ตำแหน่งขึ้นกับ skin)
ตั้ง Minute/Hour/Day of Month/Month/Day of Week เป็น `*` ทั้งหมด แล้วใช้คำสั่ง:

```sh
cd /home/ACCOUNT/domains/DOMAIN/floodthai && /PATH/TO/PHP83 artisan flood:cron
```

แทน path ทั้งสองด้วยค่าจริงจากโฮสต์ ไม่สมมติว่า PHP CLI เป็นรุ่นเดียวกับ PHP เว็บ
เก็บผลแจ้งข้อผิดพลาด Cron ระหว่างตรวจ และกลับ `/admin/setup` ขั้น 4 หลังรอบถัดไป ตรวจ heartbeat ทั้ง Scheduler/Queue
CLI ต้องรองรับ `proc_open`; ไม่ต้องมี worker เปิดค้าง และไม่สร้าง Cron URL เปิดสาธารณะ

## ก่อนเปิดจริง/อัปเดต

เพิ่มหน่วยงาน เบอร์ฉุกเฉิน เจ้าหน้าที่ ขอบเขตพื้นที่จริง ตรวจข้อมูลภายนอก ทดสอบแจ้งเหตุครบวงจรและสำรอง/กู้คืนในผู้ช่วยขั้น 5
ระบบไม่เปิดศูนย์หรือรับเหตุเอง ให้ผู้ดูแลเปิดหลังทดสอบแล้ว
ถ้า 500 ตรวจ log แบบส่วนตัว; ถ้า 404 ตรวจ Document Root/rewrite; ถ้า Cron ไม่สดตรวจ path และ PHP CLI
อัปเดตตาม [DEPLOY.md](DEPLOY.md) สำรองฐานข้อมูล `.env` และไฟล์อัปโหลด เก็บ APP_KEY เดิม ห้ามใช้ตัวติดตั้งหรือ `migrate:fresh` กับข้อมูลเดิม
คงเครดิตและแนบ [LICENSE.md](../LICENSE.md) เมื่อแจกต่อ
