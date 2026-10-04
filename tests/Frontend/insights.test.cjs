const test = require('node:test');
const assert = require('node:assert/strict');
const {preferences, validateArea, lineSegments, rainWindow, finite} = require('../../public/js/insights.js');

test('saved preferences reject corrupted data, unknown provinces, invalid codes and keep at most five unique areas', () => {
    const locations = [{slug: 'trang'}, {slug: 'krabi'}];
    assert.deepEqual(preferences('{broken', locations), []);
    assert.deepEqual(preferences('{}', locations), []);
    const valid = {province: 'trang', district: '9201', subdistrict: '', label: 'ตรัง'};
    const data = [valid, valid, {province: 'unknown', district: '', subdistrict: ''}, {province: 'trang', district: '', subdistrict: '920101'}, {province: 'krabi', district: '<script>', subdistrict: ''}];
    assert.deepEqual(preferences(JSON.stringify(data), locations), [valid]);
    assert.equal(preferences(JSON.stringify(Array.from({length: 9}, (_, i) => ({province: 'trang', district: String(9200 + i), subdistrict: ''}))), locations).length, 5);
});
test('administrative preferences validate exact ownership, not coordinates or loose names', () => {
    const context = {areas: [{code: '9201', subdistricts: [{code: '920101'}]}, {code: '9202', subdistricts: [{code: '920201'}]}]};
    assert.deepEqual(validateArea(context, '9201', '920201'), {district: '9201', subdistrict: ''});
    assert.deepEqual(validateArea(context, '1001', '920101'), {district: '', subdistrict: ''});
    assert.deepEqual(validateArea(context, '9201', '920101'), {district: '9201', subdistrict: '920101'});
});
test('water graph leaves missing intervals disconnected and preserves an observed zero', () => {
    const points = [{value: 1}, {value: 0}, {value: null}, {value: 3}, {value: '4'}, {value: NaN}];
    assert.deepEqual(lineSegments(points, i => i, v => v * 2), [[[0, 2], [1, 0]], [[3, 6]]]);
    assert.equal(finite(null), false); assert.equal(finite(0), true);
});
test('rain chart uses the next 24 hour-end observations and leaves missing hours unknown', () => {
    const hours = [{time: '2026-10-04T10:00:00+07:00', rain_mm: 99}, {time: '2026-10-04T11:00:00+07:00', rain_mm: 0}, {time: '2026-10-04T13:00:00+07:00', rain_mm: 2}];
    const rows = rainWindow(hours, '2026-10-04T10:35:00+07:00');
    assert.equal(rows.length, 24); assert.equal(rows[0].rain_mm, 0); assert.equal(rows[1].rain_mm, null); assert.equal(rows[2].rain_mm, 2);
    assert.equal(rows.some(row => row.rain_mm === 99), false);
});
