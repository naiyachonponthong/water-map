<div class="border rounded-3 p-3 mb-3 bg-light">
    <h3 class="h6 fw-bold">Plesk — Run a PHP script</h3>
    <p class="small">เปิด Scheduled Tasks → Add Task → Run a PHP script แล้วเลือกไฟล์ <code>artisan</code> ด้วย Choose File</p>
    <dl class="row small mb-0"><dt class="col-sm-4">Script path</dt><dd class="col-sm-8">เลือก <code>floodthai/artisan</code> ในโฟลเดอร์โดเมนของคุณ<br><span class="text-muted">path สัมพันธ์กับ Home directory ของบัญชี เช่น <code>example.com/floodthai/artisan</code></span></dd><dt class="col-sm-4">Arguments</dt><dd class="col-sm-8"><code>flood:cron</code></dd><dt class="col-sm-4">PHP version</dt><dd class="col-sm-8">8.3 ขึ้นไป ให้ตรงกับเวอร์ชันเว็บไซต์</dd><dt class="col-sm-4">Cron style</dt><dd class="col-sm-8"><code>* * * * *</code></dd></dl>
    <p class="small mb-0">เปิด Active และกด Run Now เพื่อตรวจว่ารันสำเร็จ</p>
</div>
<details class="border rounded-3 p-3 mb-3"><summary class="fw-bold">cPanel / DirectAdmin — ตั้ง Cron ด้วยคำสั่ง</summary><p class="small mt-3">เลือกทุกนาที และแทน <code>/PATH/TO/PHP</code> ด้วย PHP CLI 8.3 ขึ้นไปที่ผู้ให้บริการแจ้ง:</p><pre class="small bg-light p-3 rounded text-wrap">cd {{ escapeshellarg(base_path()) }} &amp;&amp; /PATH/TO/PHP artisan flood:cron</pre><p class="small mb-0">PHP CLI ต้องใช้ proc_open ได้ ถ้าคำสั่งไม่ทำงาน ให้ส่งผล Run Now หรือ Cron log ให้ผู้ให้บริการตรวจ path และเวอร์ชัน PHP</p></details>
