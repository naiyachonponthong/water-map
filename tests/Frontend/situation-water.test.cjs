const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const water = require('../../public/js/situation-water.js');

test('station popup separates MSL and bank reference from flood depth and escapes provider text', () => {
  const html = water.popup({name:'<script>unsafe</script>',value:8.08,unit:'ม. รทก.',relative_bank:-7.62,trend:0,code:'X.56',label:'น้ำน้อย',color:'#b38a12',measured_at:'2026-10-03T07:00:00+07:00'},false);
  assert.ok(html.includes('ต่ำกว่าตลิ่ง 7.62 ม.'));
  assert.ok(html.includes('ไม่ใช่ความลึกน้ำท่วมบ้าน'));
  assert.ok(html.includes('&lt;script&gt;'));
  assert.ok(!html.includes('<script>'));
  assert.ok(html.includes('ทรงตัว'));
  assert.equal(water.bank({relative_bank:null}),'ยังไม่มีตลิ่งอ้างอิงที่เทียบได้');
  assert.equal(water.number(null),'—');
});

test('stale observations are explicitly marked, not current station status', () => {
  const html = water.popup({name:'A',value:2,unit:'ม.',relative_bank:null,trend:null,label:'น้ำปกติ',color:'#16875c'},true);
  assert.ok(html.includes('ข้อมูลเก่า'));
  assert.ok(html.includes('#87949c'));
  assert.ok(!html.includes('>น้ำปกติ<'));
});

test('without map library, list still loads and unavailable provider is unknown, not zero', async () => {
  const elements = new Map();
  function el(id) { if (!elements.has(id)) elements.set(id,{value:'',textContent:'',innerHTML:'',hidden:false,addEventListener(){}}); return elements.get(id); }
  let resolveWater;
  const sandbox = {window:{}, document:{hidden:false,getElementById:el,querySelector(){return null;}},Map,AbortController,Date,Number,String,Promise,setTimeout,clearTimeout,setInterval(){},fetch(url){
    if (url === 'context') return Promise.resolve({ok:true,json:async()=>({boundary:null,center:[7.5,99.5]})});
    return new Promise(resolve=>{resolveWater=resolve;});
  }};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/situation-water.js'),'utf8'),sandbox);
  const widget = sandbox.window.FloodSituationWater.init(null,{contextUrl:'context',waterUrl:'stations'});
  resolveWater({ok:true,json:async()=>({stations:[{id:'1',name:'สถานีทดสอบ',value:1.5,unit:'ม. รทก.',relative_bank:-2,color:'#16875c',label:'น้ำปกติ'}],stale:false,fetched_at:'2026-10-03T09:00:00+07:00'})});
  await new Promise(resolve=>setImmediate(resolve));
  assert.equal(el('pmWaterCount').textContent,1);
  assert.ok(el('pmWaterList').innerHTML.includes('สถานีทดสอบ'));
  assert.ok(el('pmBoundaryMessage').textContent.includes('โหลดตัวแผนที่ไม่ได้'));
  const pending = widget.load();
  resolveWater({ok:false}); await pending;
  assert.equal(el('pmWaterCount').textContent,'—');
  assert.ok(el('pmWaterList').textContent.includes('ไม่สามารถสรุปว่าระดับน้ำเป็นปกติ'));
});
