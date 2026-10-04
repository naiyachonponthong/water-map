const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

(async () => {
    const browser = await chromium.launch({channel: 'chrome', headless: true});
    const context = await browser.newContext({viewport: {width: 1440, height: 1000}, reducedMotion: 'reduce'});
    const errors = []; let failForecast = false, failSummary = false, delayTrang = false;
    context.on('page', p => p.on('pageerror', e => errors.push(e.message)));
    const base = new Date(); base.setUTCMinutes(0, 0, 0);
    const time = offset => new Date(base.getTime() + offset * 3600000).toISOString();
    const areas = [{code: '9201', name: 'อ.เมืองตรัง', subdistricts: [{code: '920101', name: 'ต.ทับเที่ยง'}, {code: '920102', name: 'ต.นาพละ'}]}, {code: '9202', name: 'อ.กันตัง', subdistricts: [{code: '920201', name: 'ต.กันตัง'}]}];
    await context.route('**/*.json*', async route => {
        const url = new URL(route.request().url()), province = url.pathname.startsWith('/krabi') ? 'krabi' : 'trang';
        if (delayTrang && province === 'trang') await new Promise(resolve => setTimeout(resolve, 500));
        let data, status = 200;
        if (url.pathname.endsWith('/context.json')) data = {areas: province === 'trang' ? areas : [{code: '8101', name: 'อ.เมืองกระบี่', subdistricts: [{code: '810101', name: 'ต.ปากน้ำ'}]}]};
        else if (url.pathname.endsWith('/summary.json')) {
            if (failSummary) { status = 503; data = {}; }
            else data = {reports: {count: province === 'krabi' ? 2 : (url.searchParams.has('subdistrict') ? 1 : 8), verified: 1, unassigned_in_province: 1,
                hours: Array.from({length: 25}, (_, i) => ({time: time(i - 24), count: i > 18 ? 1 : 0}))}, local_stations: [], as_of: new Date().toISOString()};
        } else if (url.pathname.endsWith('/forecast.json')) {
            if (failForecast && province === 'krabi') { status = 503; data = {}; }
            else data = {hours: Array.from({length: 24}, (_, i) => ({time: time(i + 1), rain_mm: i === 8 ? null : (i > 11 && i < 18 ? (i % 3 + 1) * .8 : 0)})),
                summary: {rain_mm: province === 'trang' ? 12.4 : 4.2, probability: 85, category: 'ฝนเล็กน้อย', from: time(0), to: time(24)}, updated_at: new Date().toISOString(), stale: false};
        } else if (url.pathname.endsWith('/stations.json')) data = {stations: [{id: '42', name: province === 'trang' ? 'สถานีคลองลำภูรา (ข้อมูลทดสอบ)' : 'สถานีทดสอบกระบี่', value: 2.3, unit: 'ม. รทก.'}]};
        else if (url.pathname.endsWith('/history.json')) data = {name: 'สถานีคลองลำภูรา (ข้อมูลทดสอบ)', unit: 'ม. รทก.', source: 'thaiwater', bank: 4.5, notice: 'ภาพตรวจ UI ใช้ข้อมูลทดสอบ ไม่ใช่ข้อมูลเผยแพร่',
            points: Array.from({length: 72}, (_, i) => ({time: time(i - 71), measured_at: time(i - 71), value: i > 16 && i < 23 ? null : 2 + Math.sin(i / 8) * .4 + i / 95}))};
        else if (url.pathname.endsWith('/data-status.json')) data = {checked_at: new Date().toISOString(), sources: [
            {source: 'water', name: 'ThaiWater', description: 'ระดับน้ำสถานีในจังหวัด', state: 'partial', label: 'บางสถานีข้อมูลเก่า', fetched_at: new Date().toISOString(), measured_at: time(-1), coverage: {total: 12, current: 10}, url: 'https://www.thaiwater.net/'},
            {source: 'forecast', name: 'Open-Meteo', description: 'พยากรณ์บริเวณตัวเมือง', state: 'fresh', label: 'ข้อมูลล่าสุด', fetched_at: new Date().toISOString(), url: 'https://open-meteo.com/'},
            {source: 'radar', name: 'RainViewer', description: 'ภาพเรดาร์ย้อนหลัง', state: 'unavailable', label: 'เชื่อมต่อต้นทางไม่สำเร็จ', fetched_at: time(-1), measured_at: time(-2), failed_at: new Date().toISOString(), url: 'https://www.rainviewer.com/'}]};
        else if (url.pathname.endsWith('/radar.json')) data = {};
        else { await route.abort(); return; }
        try { await route.fulfill({status, contentType: 'application/json', body: JSON.stringify(data)}); } catch (_) { /* old province requests may be intentionally aborted */ }
    });
    const page = await context.newPage();
    await page.goto('http://127.0.0.1:8997/insights-preview.html');
    await page.locator('[data-rain-value]').filter({hasText: '12.4'}).waitFor();
    await page.locator('[data-water-chart] svg').waitFor();
    assert.equal(await page.locator('[data-water-chart] polyline').count(), 2);
    await page.locator('[data-district]').selectOption('9201');
    await page.locator('[data-subdistrict]').selectOption('920101');
    await page.locator('[data-report-value]').filter({hasText: /^1$/}).waitFor();
    await page.locator('[data-save]').click();
    await page.reload();
    await page.locator('[data-report-value]').filter({hasText: /^1$/}).waitFor();
    assert.equal(await page.locator('[data-subdistrict]').inputValue(), '920101');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({path: path.resolve('output/qa/v1.1.0-desktop.png'), fullPage: true});
    await page.setViewportSize({width: 390, height: 844});
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({path: path.resolve('output/qa/v1.1.0-mobile.png'), fullPage: true});
    // Late responses, provider outage and area changes must never retain old metrics.
    failForecast = true; delayTrang = true;
    await page.locator('[data-refresh]').click();
    await page.locator('[data-province]').selectOption('krabi');
    await page.locator('[data-rain-caption]').filter({hasText: 'ยังเชื่อมต่อพยากรณ์ไม่ได้'}).waitFor();
    await page.waitForTimeout(700);
    assert.equal(await page.locator('[data-rain-value]').textContent(), '—');
    assert.equal(await page.locator('[data-rain-chart] .ia-bars').count(), 0);
    assert.equal(await page.locator('[data-report-value]').textContent(), '2');
    assert.match(await page.locator('[data-area-title]').textContent(), /กระบี่/);
    failSummary = true;
    await page.locator('[data-district]').selectOption('8101');
    await page.locator('[data-report-caption]').filter({hasText: 'ยังโหลดรายงานไม่ได้'}).waitFor();
    assert.equal(await page.locator('[data-report-value]').textContent(), '—');
    assert.equal(await page.locator('[data-report-table] table').count(), 0);
    await page.locator('[data-forget]').click();
    assert.deepEqual(await page.evaluate(() => JSON.parse(localStorage.getItem('floodthai.my-area.v1'))), []);
    const health = await context.newPage();
    await health.goto('http://127.0.0.1:8997/health-preview.html');
    await health.locator('.ia-health-card').nth(2).waitFor();
    assert.equal(await health.locator('.ia-health-card').count(), 3);
    await health.locator('[data-health-refresh]').click();
    await health.locator('[data-health-refresh]:enabled').waitFor();
    await health.screenshot({path: path.resolve('output/qa/v1.1.0-health.png'), fullPage: true});
    assert.equal(await health.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await health.goto('http://127.0.0.1:8997/admin-health-preview.html');
    await health.locator('.ia-health-card').nth(2).waitFor();
    assert.equal(errors.length, 0, errors.join('\n'));
    console.log('Visual QA passed: desktop/mobile layout, real gaps, saved area reload, province races, missing data, area failure, health cards, admin view. Screenshots use isolated test data.');
    await browser.close();
})().catch(error => { console.error(error); process.exit(1); });
