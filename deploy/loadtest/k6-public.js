// ทดสอบโหลดหน้าเว็บประชาชนช่วงน้ำท่วม ด้วย k6 (https://k6.io)
//
//   k6 run -e BASE=https://staging.example.go.th -e PROVINCE=chachoengsao deploy/loadtest/k6-public.js
//   k6 run -e BASE=... -e WRITE=1 deploy/loadtest/k6-public.js      # รวมการส่งรายงานน้ำ (ใช้กับเครื่องทดสอบเท่านั้น)
//
// ใช้กับเครื่อง staging ที่รัน DemoSeeder แล้ว ห้ามยิงเครื่องจริงช่วงเกิดเหตุ
// การส่งฟอร์มติด rate limit ต่อ IP: ตั้ง HELP_IP_LIMIT / REPORT_IP_LIMIT ใน .env ของ staging ให้สูงชั่วคราว
import http from 'k6/http';
import { check, sleep, group } from 'k6';

const BASE = __ENV.BASE || 'http://localhost:8000';
const P = __ENV.PROVINCE || 'chachoengsao';
const WRITE = __ENV.WRITE === '1';

export const options = {
  scenarios: {
    // คนเปิดดูหน้าแรก แผนที่ ประกาศ (ส่วนใหญ่ของทราฟฟิก)
    browse: {
      executor: 'ramping-vus',
      stages: [
        { duration: '1m', target: 100 },
        { duration: '3m', target: 500 },
        { duration: '2m', target: 500 },
        { duration: '1m', target: 0 },
      ],
      exec: 'browse',
    },
    // หน่วยงานอื่นดึงข้อมูลเปิดทุก 30 วินาที
    opendata: { executor: 'constant-vus', vus: 20, duration: '7m', exec: 'openData' },
    ...(WRITE ? { report: { executor: 'constant-arrival-rate', rate: 5, timeUnit: '1s', duration: '5m', preAllocatedVUs: 50, exec: 'report' } } : {}),
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{page:province}': ['p(95)<1500'],
    'http_req_duration{page:geojson}': ['p(95)<1000'],
    'http_req_duration{page:report}': ['p(95)<2500'],
  },
};

export function browse() {
  group('ประชาชนเปิดดู', () => {
    check(http.get(`${BASE}/${P}`, { tags: { page: 'province' } }), { 'หน้าแรก 200': (r) => r.status === 200 });
    sleep(Math.random() * 3 + 1);
    check(http.get(`${BASE}/${P}/map`, { tags: { page: 'map' } }), { 'แผนที่ 200': (r) => r.status === 200 });
    check(http.get(`${BASE}/${P}/map.geojson`, { tags: { page: 'geojson' } }), { 'geojson 200': (r) => r.status === 200 });
    sleep(Math.random() * 5 + 2);
    if (Math.random() < 0.3) http.get(`${BASE}/${P}/shelters`, { tags: { page: 'shelters' } });
  });
}

export function openData() {
  check(http.get(`${BASE}/${P}/open-data.json`, { tags: { page: 'opendata' } }), { 'open-data 200': (r) => r.status === 200 });
  sleep(30);
}

export function report() {
  const form = http.get(`${BASE}/${P}/report`);
  const token = (form.body.match(/name="_token" value="([^"]+)"/) || [])[1];
  if (!token) return;
  const lat = 13.69 + (Math.random() - 0.5) * 0.2;
  const lng = 101.07 + (Math.random() - 0.5) * 0.2;
  const res = http.post(`${BASE}/${P}/report`, { _token: token, lat, lng, level: 1 + Math.floor(Math.random() * 5), trend: 'rising' }, { tags: { page: 'report' }, redirects: 0 });
  check(res, { 'ส่งรายงานได้ (302)': (r) => r.status === 302 });
}
