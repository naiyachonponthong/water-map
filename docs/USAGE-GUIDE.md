# คู่มือในเว็บและภาพหน้าสถานการณ์

คู่มือสาธารณะ: `/guide` หรือ `/{province}/guide` เช่น `/trang/guide`
คู่มือเจ้าหน้าที่: `/admin/guide` ต้องเข้าสู่ระบบด้วยบัญชี active ตามเงื่อนไขเดิม

คู่มือมี 18 หัวข้อ แยกประชาชน เจ้าหน้าที่ และผู้ดูแล พร้อมสารบัญ ค้นหาทั้งเนื้อหา เปิด/ย่อรายละเอียด และพิมพ์คู่มือทั้งหมดหรือบันทึก PDF ผ่านเบราว์เซอร์ คำค้น/ตัวกรองจะคืนค่าเมื่อปิดหน้าพิมพ์ ลิงก์บริการคงจังหวัดที่เลือก หากไม่ได้เลือกจังหวัดจะพาไปเลือกก่อน

เมนูอยู่ในแถบหน้าแรก ส่วนท้ายหน้าสาธารณะ และเมนูระบบของเจ้าหน้าที่ ลิงก์ “วิธีอ่านแผนที่” ในหน้าสถานการณ์เปิดหัวข้อแผนที่ทันที คู่มือเป็นคำแนะนำแบบ static ไม่มีรหัสบัญชี ข้อมูลเคส หรือการเรียก API ภายนอก และไม่เพิ่มสิทธิ์ผู้ใช้งาน

เนื้อหาอยู่ใน `app/Support/UsageGuide.php` ให้ตรวจและแก้พร้อมการเปลี่ยนฟอร์ม/ขั้นตอนจริง ไม่สรุปว่าไม่มีน้ำท่วมเมื่อไม่พบรายงาน และไม่ใช้ระดับน้ำ รทก. เป็นความลึกน้ำท่วมบ้าน

## ภาพประกอบที่สร้างใหม่

- Mode: built-in image generation tool; ไม่ใช้ CLI/API key
- Final asset: `public/images/situation-riverside-hero.png` (2048 × 768)
- Placement: `resources/views/public/reports/map.blade.php` ส่วน “ดูสถานการณ์ในพื้นที่”
- Desktop: ภาพด้านขวาของข้อความ; mobile: แถบภาพใต้ข้อความ
- ป้ายกำกับ: “ภาพประกอบ ไม่ใช่สถานการณ์จริง”; ภาพตกแต่งใช้ alt ว่าง ไม่ใช่แผนที่หรือข้อมูลสด

Final prompt:

> Use case: stylized-concept. Asset type: wide hero illustration for an existing Thai community flood monitoring website, not a mockup. Create a beautiful refined contemporary 3D miniature illustration of a Thai tropical riverside neighborhood with tidy local houses, coconut palms, a winding turquoise river, an unobtrusive water-level monitoring post, a small map-location motif, and distant green hills. Reassuring community preparedness, calm daylight, subtle warm highlights. Panoramic composition: detailed riverside scene on the right half, a very quiet deep teal gradient and ample negative space on the left half for real HTML headings. Palette should harmonize with the existing dashboard: deep teal, sea green, pale mint, warm cream, small coral accents. Soft tactile paper-and-clay materials with polished editorial finish, beautiful depth and atmospheric light, coherent geometry. It must be clearly decorative, not an actual province map, live data, weather forecast or documentary flood event. No text, letters, numbers, logos, watermark, UI panels, disaster victims or distress. Wide landscape banner suitable for desktop hero and a separately cropped mobile artwork.

## การตรวจสอบ

`tests/Feature/UsageGuideTest.php` ตรวจ 18 หัวข้อ จังหวัด active/cookie/404 การไม่เรียกผู้ให้บริการ ขอบเขตสิทธิ์และเมนู ใช้ SQLite ในหน่วยความจำเท่านั้น

`tests/Frontend/usage-guide.test.cjs` ตรวจค้นหา/บทบาท/สารบัญ/ล้างตัวกรอง/เปิดรายละเอียด/ลิงก์หัวข้อ/การคืนสถานะหลังพิมพ์ โดยไม่เปิดหน้าพิมพ์จริง

เครื่องมือ smoke installer ตรวจ `/guide` และ `/trang/guide` เพิ่มในชุดติดตั้งใหม่ ใช้ฐานข้อมูลทดสอบแยก ไม่ติดตั้งทับระบบใช้งาน

ผลตรวจ 3 ต.ค. 2026: ชุดที่เกี่ยวข้อง 49 tests / 722 assertions ผ่าน; frontend 21 tests ผ่าน; ตรวจหน้าจอ 1440px, 390px และหน้าต่างปกติ รวมค้นหา ตัวกรองและลิงก์หัวข้อ ไม่มีการขอ GPS หรือส่งฟอร์มเหตุจริง

การรันทดสอบทั้งระบบ: 141 ผ่าน และ 5 ไม่ผ่านในส่วนงานอื่น (ไม่ใช่คู่มือใหม่) ยังไม่แก้ส่วนเหล่านั้นในงานนี้ การผ่านตัวติดตั้งและหน้าคู่มือไม่ใช่การรับรองความพร้อมทุก workflow สำหรับรับเหตุจริง
