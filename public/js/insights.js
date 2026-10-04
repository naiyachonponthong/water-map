(function () {
    'use strict';
    const finite = value => typeof value === 'number' && Number.isFinite(value);
    const storageKey = 'floodthai.my-area.v1';
    function preferences(raw, locations) {
        try {
            const rows = JSON.parse(raw || '[]');
            if (!Array.isArray(rows)) return [];
            const seen = new Set();
            return rows.filter(row => {
                if (!row || !locations.some(p => p.slug === row.province) || ![row.district, row.subdistrict].every(c => typeof c === 'string' && /^(?:\d{1,12})?$/.test(c)) || (row.subdistrict && !row.district)) return false;
                const key = [row.province, row.district, row.subdistrict].join(':');
                if (seen.has(key)) return false;
                seen.add(key); return true;
            }).slice(0, 5).map(row => ({province: row.province, district: row.district, subdistrict: row.subdistrict,
                label: typeof row.label === 'string' ? row.label.slice(0, 160) : row.province}));
        } catch (_) { return []; }
    }
    function validateArea(context, district, subdistrict) {
        const area = context.areas.find(row => row.code === district);
        if (!area) return {district: '', subdistrict: ''};
        return {district, subdistrict: area.subdistricts.some(row => row.code === subdistrict) ? subdistrict : ''};
    }
    function lineSegments(points, x, y) {
        const segments = []; let part = [];
        points.forEach((point, index) => {
            if (finite(point.value)) part.push([x(index), y(point.value)]);
            else if (part.length) { segments.push(part); part = []; }
        });
        if (part.length) segments.push(part);
        return segments;
    }
    function rainWindow(hours, at) {
        const base = new Date(at); base.setUTCMinutes(0, 0, 0);
        const next = new Map(hours.map(h => [new Date(h.time).getTime(), h]));
        return Array.from({length: 24}, (_, i) => {
            const time = base.getTime() + (i + 1) * 3600000;
            return next.get(time) || {time: new Date(time).toISOString(), rain_mm: null};
        });
    }
    if (typeof module !== 'undefined' && module.exports) { module.exports = {preferences, validateArea, lineSegments, rainWindow, finite}; return; }
    const root = document.querySelector('[data-insights]'); if (!root) return;
    const locations = JSON.parse(root.querySelector('[data-insights-config]').textContent);
    const find = name => root.querySelector('[data-' + name + ']');
    const provinceSelect = find('province'), districtSelect = find('district'), subdistrictSelect = find('subdistrict'), stationSelect = find('station');
    const date = value => new Date(value).toLocaleString('th-TH', {timeZone: 'Asia/Bangkok', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'});
    const hour = value => new Date(value).toLocaleTimeString('th-TH', {timeZone: 'Asia/Bangkok', hour: '2-digit', minute: '2-digit'});
    const text = (key, value) => { find(key).textContent = value; };
    function node(tag, value, className) { const n = document.createElement(tag); n.textContent = value; if (className) n.className = className; return n; }
    function empty(key, message) { find(key).replaceChildren(node('div', message, 'ia-empty')); }
    function options(select, rows, placeholder) {
        select.replaceChildren(new Option(placeholder, ''));
        rows.forEach(row => select.add(new Option(row.name, row.code)));
        select.disabled = rows.length === 0;
    }
    function table(key, rows, valueKey, unit) {
        const t = node('table', ''); const caption = node('caption', unit); const head = node('thead', ''), body = node('tbody', ''), headings = node('tr', '');
        ['เวลาไทย', unit].forEach(label => { const th = node('th', label); th.scope = 'col'; headings.append(th); }); head.append(headings);
        rows.forEach(row => { const tr = node('tr', ''); tr.append(node('td', date(row.measured_at || row.time)), node('td', finite(row[valueKey]) ? row[valueKey].toLocaleString('th-TH', {maximumFractionDigits: 2}) : 'ไม่มีข้อมูล')); body.append(tr); });
        t.append(caption, head, body); find(key).replaceChildren(t);
    }
    function bars(key, rows, valueKey, unit, tableKey) {
        const values = rows.map(row => row[valueKey]).filter(finite);
        if (!values.length) { empty(key, 'ยังไม่มีข้อมูลสำหรับกราฟ'); table(tableKey, rows, valueKey, unit); return; }
        const max = Math.max(...values, 1), wrap = node('div', '', 'ia-bars'); wrap.setAttribute('role', 'img'); wrap.setAttribute('aria-label', 'กราฟ ' + unit + ' รายชั่วโมง ดูค่าทั้งหมดได้จากตารางด้านล่าง');
        rows.forEach(row => {
            const cell = node('span', '', 'ia-bar-cell'), value = row[valueKey];
            cell.title = date(row.time) + ' · ' + (finite(value) ? value + ' ' + unit : 'ไม่มีข้อมูล');
            const bar = node('span', '', finite(value) ? 'ia-bar' : 'ia-bar-missing');
            if (finite(value)) bar.style.height = Math.max(2, value / max * 100) + '%';
            cell.append(bar); wrap.append(cell);
        });
        const ticks = node('div', '', 'ia-chart-ticks'); [0, 6, 12, 18, rows.length - 1].forEach(i => { if (rows[i]) ticks.append(node('span', hour(rows[i].time))); });
        find(key).replaceChildren(wrap, ticks); table(tableKey, rows, valueKey, unit);
    }
    let currentWaterData = null;
    function waterChart(data) {
        currentWaterData = data;
        const observed = data.points.filter(p => finite(p.value));
        text('water-caption', data.name + (observed.length ? ' · ตรวจวัดล่าสุด ' + date(observed.at(-1).measured_at) : ' · ยังไม่มีค่าตรวจวัดในช่วงนี้'));
        text('water-note', data.notice + ' · หน่วย ' + data.unit + ' · ต้นทาง ' + (data.source === 'thaiwater' ? 'ThaiWater' : 'สถานีที่หน่วยงานเผยแพร่'));
        table('water-table', data.points.filter(p => finite(p.value)), 'value', data.unit);
        if (!observed.length) { empty('water-chart', 'ยังไม่มีข้อมูลย้อนหลัง'); return; }
        const vals = observed.map(p => p.value); if (finite(data.bank)) vals.push(data.bank);
        const range = Math.max(.2, Math.max(...vals) - Math.min(...vals)), min = Math.min(...vals) - range * .15, max = Math.max(...vals) + range * .15;
        const width = Math.max(300, find('water-chart').clientWidth), height = 240, left = 48, right = width - 18,
            x = i => left + i / Math.max(1, data.points.length - 1) * (right - left), y = v => 192 - (v - min) / (max - min) * 160;
        const ns = 'http://www.w3.org/2000/svg';
        function svgNode(tag, attrs, label) { const el = document.createElementNS(ns, tag); Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, String(v))); if (label) el.textContent = label; return el; }
        const svg = svgNode('svg', {viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': 'ระดับน้ำย้อนหลัง ' + data.unit + ' เส้นประคือระดับตลิ่งถ้ามี ช่องว่างคือไม่มีข้อมูล'});
        for (let i = 0; i <= 3; i++) {
            const value = min + (max - min) * i / 3, yy = y(value);
            svg.append(svgNode('line', {x1: left, y1: yy, x2: right, y2: yy, stroke: '#e5eef1'}), svgNode('text', {x: left - 8, y: yy + 4, 'text-anchor': 'end', fill: '#718b99', 'font-size': 11}, value.toFixed(2)));
        }
        if (finite(data.bank)) {
            svg.append(svgNode('line', {x1: left, y1: y(data.bank), x2: right, y2: y(data.bank), stroke: '#d88955', 'stroke-dasharray': '7 6', 'stroke-width': 1.5}), svgNode('text', {x: right, y: y(data.bank) - 7, 'text-anchor': 'end', fill: '#ab6637', 'font-size': 11}, 'ตลิ่ง ' + data.bank.toFixed(2)));
        }
        lineSegments(data.points, x, y).forEach(segment => {
            if (segment.length > 1) svg.append(svgNode('polyline', {points: segment.map(p => p.join(',')).join(' '), fill: 'none', stroke: '#158d9d', 'stroke-width': 3, 'stroke-linejoin': 'round'}));
            segment.forEach(([xx, yy]) => svg.append(svgNode('circle', {cx: xx, cy: yy, r: 3, fill: '#158d9d'})));
        });
        (width < 600 ? [0, 71] : [0, 24, 48, 71]).forEach(i => svg.append(svgNode('text', {x: x(i), y: 222, 'text-anchor': i === 71 ? 'end' : 'start', fill: '#718b99', 'font-size': 11}, date(data.points[i].time))));
        find('water-chart').replaceChildren(svg);
        if (observed.length === 1) find('water-note').prepend('มีข้อมูล 1 จุด รอการตรวจวัดครั้งถัดไปเพื่อแสดงแนวโน้ม · ');
    }
    let saved;
    try { saved = preferences(localStorage.getItem(storageKey), locations); } catch (_) { saved = []; }
    let location = null, context = null, generation = 0, summaryGeneration = 0, historyGeneration = 0, aborter = null, stations = [], localStations = [], externalStations = [];
    function showSaved() {
        const container = find('saved'); container.replaceChildren();
        saved.forEach(row => { const b = node('button', row.label); b.type = 'button'; b.addEventListener('click', () => selectProvince(row.province, row)); container.append(b); });
        find('forget').hidden = saved.length === 0;
    }
    function persist() {
        try { localStorage.setItem(storageKey, JSON.stringify(saved)); return true; }
        catch (_) { text('selection-message', 'เบราว์เซอร์ไม่อนุญาตให้บันทึกพื้นที่ ยังดูข้อมูลได้ตามปกติ'); return false; }
    }
    async function get(url, signal) {
        const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: AbortSignal.any([signal, AbortSignal.timeout(45000)])});
        if (!response.ok) throw new Error('ยังโหลดข้อมูลไม่ได้ กรุณาลองใหม่');
        return response.json();
    }
    function areaTitle() {
        const district = context?.areas.find(d => d.code === districtSelect.value);
        const subdistrict = district?.subdistricts.find(s => s.code === subdistrictSelect.value);
        return [subdistrict?.name, district?.name, location?.name].filter(Boolean).join(' · ');
    }
    function updateTitle() { text('area-title', areaTitle() || 'เลือกพื้นที่เพื่อเริ่มต้น'); }
    function chooseDistrict(subdistrict = '') {
        const district = context?.areas.find(d => d.code === districtSelect.value);
        options(subdistrictSelect, district?.subdistricts || [], 'ทั้งอำเภอ / เขต'); subdistrictSelect.value = subdistrict; updateTitle();
    }
    function resetCharts() {
        currentWaterData = null;
        ['rain-value', 'report-value'].forEach(k => text(k, '—'));
        ['rain-caption', 'report-caption', 'water-caption'].forEach(k => text(k, 'กำลังโหลดข้อมูล…'));
        ['rain-chart', 'report-chart', 'water-chart'].forEach(k => empty(k, 'กำลังโหลด…'));
        ['rain-time', 'water-note'].forEach(k => text(k, ''));
        ['rain-table', 'report-table', 'water-table'].forEach(k => find(k).replaceChildren());
        text('health-mini', 'กำลังอ่านสถานะ…');
    }
    async function loadHealth(g, signal) {
        try {
            const data = await get(location.health, signal); if (generation !== g) return;
            const box = find('health-mini'); box.replaceChildren();
            data.sources.forEach(row => {
                const line = node('div', '', 'ia-health-row'); line.append(node('span', row.name), node('span', row.label, 'ia-state ia-state-' + row.state)); box.append(line);
            });
        } catch (_) { if (generation === g) text('health-mini', 'ยังอ่านสถานะไม่ได้ เปิดหน้าความสดของข้อมูลเพื่อตรวจอีกครั้ง'); }
    }
    function stationOptions(g, signal) {
        const old = stationSelect.value;
        stations = [...localStations, ...externalStations];
        stationSelect.replaceChildren(new Option(stations.length ? 'เลือกสถานี' : 'ยังไม่มีสถานีที่มีข้อมูล', ''));
        stations.forEach((s, i) => stationSelect.add(new Option(s.name + (s.source === 'thaiwater' ? ' · ThaiWater' : ' · หน่วยงาน'), String(i))));
        stationSelect.disabled = !stations.length;
        const selected = stations.findIndex(s => [s.source, s.id, s.datum].join(':') === old);
        // Selection keys are stable when a second provider finishes later.
        Array.from(stationSelect.options).slice(1).forEach((option, i) => { option.value = [stations[i].source, stations[i].id, stations[i].datum].join(':'); });
        stationSelect.value = selected >= 0 ? old : (stationSelect.options[1]?.value || '');
        if (stationSelect.value && stationSelect.value !== old) loadHistory(g, signal);
        else if (!stations.length) { text('water-caption', 'ยังไม่มีสถานีพร้อมแสดงกราฟ'); empty('water-chart', 'ยังไม่มีข้อมูลสถานี'); }
    }
    async function loadHistory(g, signal) {
        currentWaterData = null;
        const h = ++historyGeneration, s = stations.find(s => [s.source, s.id, s.datum].join(':') === stationSelect.value);
        empty('water-chart', 'กำลังโหลดประวัติ…'); find('water-table').replaceChildren(); text('water-note', ''); text('water-caption', s ? 'กำลังโหลด ' + s.name : 'เลือกสถานีเพื่อดูกราฟ');
        if (!s) return;
        try {
            const url = new URL(location.history, window.location.origin); url.searchParams.set('source', s.source); url.searchParams.set('station', s.id); if (s.datum) url.searchParams.set('datum', s.datum);
            const data = await get(url, signal); if (generation !== g || historyGeneration !== h) return; waterChart(data);
        } catch (_) { if (generation === g && historyGeneration === h) { text('water-caption', 'ยังไม่มีประวัติที่อ่านได้ ลองอัปเดตอีกครั้ง'); empty('water-chart', 'ไม่แสดงเส้นกราฟเมื่อข้อมูลขาดหาย'); } }
    }
    async function loadSummary(g, signal) {
        const version = ++summaryGeneration;
        text('report-value', '—'); text('report-caption', 'กำลังโหลดรายงานพื้นที่…'); empty('report-chart', 'กำลังโหลด…'); find('report-table').replaceChildren();
        try {
            const url = new URL(location.summary, window.location.origin);
            if (districtSelect.value) url.searchParams.set('district', districtSelect.value);
            if (subdistrictSelect.value) url.searchParams.set('subdistrict', subdistrictSelect.value);
            const data = await get(url, signal); if (generation !== g || summaryGeneration !== version) return;
            text('report-value', String(data.reports.count));
            text('report-caption', 'เจ้าหน้าที่ยืนยัน ' + data.reports.verified + ' รายงาน · ณ ' + date(data.as_of) + ((districtSelect.value && data.reports.unassigned_in_province) ? ' · มีรายงานในจังหวัดที่ยังไม่ระบุพื้นที่ อาจไม่อยู่ในยอดนี้' : ''));
            bars('report-chart', data.reports.hours, 'count', 'รายงาน', 'report-table');
            localStations = data.local_stations; stationOptions(g, signal);
        } catch (_) { if (generation === g && summaryGeneration === version) { text('report-caption', 'ยังโหลดรายงานไม่ได้ กรุณาอัปเดตอีกครั้ง'); empty('report-chart', 'ยังไม่มีข้อมูลที่อ่านได้'); } }
    }
    async function selectProvince(slug, desired) {
        aborter?.abort(); aborter = new AbortController(); const signal = aborter.signal, g = ++generation;
        location = locations.find(p => p.slug === slug) || null; context = null; provinceSelect.value = location?.slug || '';
        stations = []; localStations = []; externalStations = [];
        options(districtSelect, [], 'ทั้งจังหวัด'); options(subdistrictSelect, [], 'ทั้งอำเภอ / เขต'); options(stationSelect, [], 'กำลังโหลดสถานี…');
        find('save').disabled = true; find('refresh').disabled = !location; resetCharts(); updateTitle();
        root.querySelectorAll('[data-link]').forEach(a => { if (location) a.href = location[a.dataset.link]; else a.removeAttribute('href'); });
        if (!location) { ['rain-caption', 'report-caption', 'water-caption'].forEach(k => text(k, 'เลือกจังหวัดเพื่อดูข้อมูล')); ['rain-chart', 'report-chart', 'water-chart'].forEach(k => empty(k, 'ยังไม่ได้เลือกจังหวัด')); text('health-mini', 'ยังไม่ได้เลือกจังหวัด'); return; }
        const contextTask = (async () => {
            try {
                const data = await get(location.context, signal); if (generation !== g) return;
                context = data; options(districtSelect, data.areas, 'ทั้งจังหวัด');
                if (desired) { const area = validateArea(data, desired.district, desired.subdistrict); districtSelect.value = area.district; chooseDistrict(area.subdistrict);
                    if (desired.district !== area.district || desired.subdistrict !== area.subdistrict) text('selection-message', 'พื้นที่ที่บันทึกเปลี่ยนไป กรุณาเลือกอำเภอและตำบลอีกครั้ง');
                }
                find('save').disabled = false; updateTitle(); await loadSummary(g, signal);
            } catch (_) { if (generation === g) { text('selection-message', 'ยังโหลดรายการอำเภอไม่ได้ ดูภาพรวมจังหวัดก่อนได้ กดอัปเดตเพื่อลองใหม่'); await loadSummary(g, signal); } }
        })();
        const rainTask = (async () => {
            try {
                const data = await get(location.forecast, signal); if (generation !== g) return;
                text('rain-value', finite(data.summary.rain_mm) ? data.summary.rain_mm.toLocaleString('th-TH', {maximumFractionDigits: 1}) : '—');
                text('rain-caption', data.summary.category + ' · โอกาสฝนสูงสุด ' + (finite(data.summary.probability) ? data.summary.probability + '%' : 'ข้อมูลไม่ครบ') + (data.stale ? ' · ข้อมูลแคชเก่า' : ''));
                bars('rain-chart', rainWindow(data.hours, data.summary.from), 'rain_mm', 'ฝน (มม.)', 'rain-table');
                text('rain-time', 'Open-Meteo · ดึงเมื่อ ' + date(data.updated_at) + ' · ช่วง ' + date(data.summary.from) + ' – ' + date(data.summary.to));
            } catch (_) { if (generation === g) { text('rain-caption', 'ยังเชื่อมต่อพยากรณ์ไม่ได้ กรุณาลองใหม่'); empty('rain-chart', 'ไม่ใช้ข้อมูลจังหวัดเดิมแทน'); } }
        })();
        const waterTask = (async () => {
            try {
                const data = await get(location.water, signal); if (generation !== g) return;
                externalStations = data.stations.filter(s => finite(s.value)).map(s => ({id: s.id, name: s.name, source: 'thaiwater', datum: s.unit === 'ม. รทก.' ? 'msl' : 'local'}));
                stationOptions(g, signal);
            } catch (_) { if (generation === g && !localStations.length) { text('water-caption', 'ThaiWater ยังไม่พร้อม · สถานีหน่วยงานจะแสดงเมื่อโหลดได้'); empty('water-chart', 'ยังไม่มีข้อมูลที่อ่านได้'); } }
        })();
        await Promise.allSettled([contextTask, rainTask, waterTask]); if (generation === g) loadHealth(g, signal);
    }
    provinceSelect.addEventListener('change', () => selectProvince(provinceSelect.value));
    districtSelect.addEventListener('change', () => { chooseDistrict(); loadSummary(generation, aborter.signal); });
    subdistrictSelect.addEventListener('change', () => { updateTitle(); loadSummary(generation, aborter.signal); });
    stationSelect.addEventListener('change', () => loadHistory(generation, aborter.signal));
    find('refresh').addEventListener('click', () => selectProvince(location.slug, {district: districtSelect.value, subdistrict: subdistrictSelect.value}));
    find('save').addEventListener('click', () => {
        const row = {province: location.slug, district: districtSelect.value, subdistrict: subdistrictSelect.value, label: areaTitle()};
        saved = [row, ...saved.filter(s => [s.province, s.district, s.subdistrict].join(':') !== [row.province, row.district, row.subdistrict].join(':'))].slice(0, 5);
        if (persist()) text('selection-message', 'บันทึก ' + row.label + ' ไว้ในเครื่องแล้ว · เปิดเมนูพื้นที่ของฉันครั้งหน้าจะกลับมาพื้นที่นี้'); showSaved();
    });
    find('forget').addEventListener('click', () => { saved = []; if (persist()) text('selection-message', 'ล้างพื้นที่ที่บันทึกในเครื่องแล้ว'); showSaved(); });
    showSaved();
    window.addEventListener('resize', () => { if (currentWaterData) waterChart(currentWaterData); });
    const initial = root.dataset.initial || saved[0]?.province || '';
    selectProvince(initial, saved.find(s => s.province === initial));
})();
