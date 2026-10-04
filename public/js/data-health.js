(function () {
    'use strict';
    const date = value => value ? new Date(value).toLocaleString('th-TH', {timeZone: 'Asia/Bangkok', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) : 'ยังไม่มีข้อมูล';
    function element(tag, text, className) { const node = document.createElement(tag); node.textContent = text; if (className) node.className = className; return node; }
    document.querySelectorAll('[data-data-health]').forEach(root => {
        const cards = root.querySelector('[data-health-cards]'), button = root.querySelector('[data-health-refresh]'), message = root.querySelector('[data-health-message]');
        async function get(url) {
            const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(45000)});
            if (!response.ok) throw new Error('ไม่สามารถอ่านข้อมูลได้');
            return response.json();
        }
        async function status() {
            const data = await get(root.dataset.statusUrl);
            cards.replaceChildren();
            data.sources.forEach(row => {
                const card = element('article', '', 'ia-health-card');
                card.append(element('h3', row.name), element('p', row.description, 'ia-caption'), element('span', row.label, 'ia-state ia-state-' + row.state));
                const list = element('dl', '');
                [['ดึงข้อมูลล่าสุด', row.fetched_at], ['ตรวจวัด / ภาพเรดาร์ล่าสุด', row.measured_at], ['เชื่อมต่อไม่สำเร็จล่าสุด', row.failed_at]].forEach(([label, value]) => {
                    list.append(element('dt', label), element('dd', row.source === 'forecast' && label.startsWith('ตรวจวัด') ? 'เป็นข้อมูลพยากรณ์ ไม่ใช่การตรวจวัด' : date(value)));
                });
                if (row.source === 'water' && row.coverage) list.append(element('dt', 'สถานีที่มีค่าและยังไม่เก่า'), element('dd', row.coverage.current + ' / ' + row.coverage.total + ' สถานี'));
                const link = element('a', 'เปิดเว็บไซต์ต้นทาง ↗'); link.href = row.url; link.target = '_blank'; link.rel = 'noopener noreferrer';
                card.append(list, link); cards.append(card);
            });
            message.textContent = 'อ่านสถานะเมื่อ ' + date(data.checked_at) + ' · ไม่เรียกข้อมูลใหม่จนกดตรวจต้นทาง';
        }
        button.addEventListener('click', async () => {
            button.disabled = true; message.textContent = 'กำลังตรวจทีละแหล่งข้อมูล…';
            try {
                const results = await Promise.allSettled([root.dataset.waterUrl, root.dataset.forecastUrl, root.dataset.radarUrl].map(get));
                await status();
                if (results.some(r => r.status === 'rejected')) message.textContent += ' · บางต้นทางยังไม่พร้อม ดูสถานะแต่ละการ์ด';
            } catch (_) { cards.replaceChildren(element('p', 'ยังอ่านสถานะไม่ได้ กรุณาลองใหม่', 'ia-note')); }
            finally { button.disabled = false; }
        });
        status().catch(() => { cards.replaceChildren(element('p', 'ยังอ่านสถานะไม่ได้ กดตรวจต้นทางเพื่อลองใหม่', 'ia-note')); });
    });
})();
