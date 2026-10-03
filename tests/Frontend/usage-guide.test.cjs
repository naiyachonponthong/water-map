const test = require('node:test');
const assert = require('node:assert/strict');
const { matches, init } = require('../../public/js/usage-guide.js');

function fixture(hash = '') {
  const el = (text = '') => ({textContent:text, hidden:false, value:'', dataset:{}, attrs:{}, events:{}, addEventListener(e, fn){this.events[e]=fn;}, setAttribute(k,v){this.attrs[k]=v;}, focus(){this.focused=true;}});
  const ids = Object.fromEntries(['usageGuide','guideSearch','guideCount','guideEmpty','guideReset','guideExpand','guidePrint'].map(id=>[id,el()]));
  const topics = [['water','citizen','ระดับน้ำ ThaiWater ตลิ่ง'],['dispatch','staff','รับงาน ทีมกู้ภัย'],['install','owner','ติดตั้ง PHP']].map(([id,audience,text])=>({...el(text),id,dataset:{audience},details:{open:false},querySelector(){return this.details;}}));
  const buttons = ['all','citizen','staff','owner'].map(key=>({...el(),dataset:{guideAudience:key}}));
  const toc = Object.fromEntries(topics.map(t=>[t.id,el()]));
  const document = {getElementById:id=>ids[id],querySelectorAll:selector=>selector==='[data-guide-section]'?topics:buttons,querySelector:selector=>toc[selector.match(/"(.*)"/)[1]]};
  const window = {location:{hash},events:{},addEventListener(e,fn){this.events[e]=fn;},print(){this.printed=true;}};
  init(document,window);
  return {ids,topics,buttons,toc,window};
}

test('Thai/English search matches all words and respects audience',()=>{
  assert.equal(matches('ระดับน้ำ ThaiWater ตลิ่ง','thaiwater ตลิ่ง','citizen','all'),true);
  assert.equal(matches('ThaiWater','thaiwater ฝน','citizen','all'),false);
  assert.equal(matches('รับงาน','รับงาน','staff','citizen'),false);
  assert.equal(matches('ระดับน้ำ','  ','citizen','citizen'),true);
});
test('search filters sections and table of contents, opens matched details',()=>{
  const {ids,topics,toc}=fixture(); ids.guideSearch.value='ตลิ่ง';ids.guideSearch.events.input();
  assert.deepEqual(topics.map(t=>t.hidden),[false,true,true]);
  assert.equal(toc.dispatch.hidden,true);assert.equal(topics[0].details.open,true);
  assert.equal(ids.guideCount.textContent,'1 หัวข้อ');assert.equal(ids.guideEmpty.hidden,true);
});
test('role filter updates pressed state and empty reset restores all sections',()=>{
  const {ids,topics,buttons}=fixture();buttons[2].events.click();
  assert.deepEqual(topics.map(t=>t.hidden),[true,false,true]);assert.equal(buttons[2].attrs['aria-pressed'],'true');
  ids.guideSearch.value='ไม่พบข้อมูล';ids.guideSearch.events.input();assert.equal(ids.guideEmpty.hidden,false);
  ids.guideReset.events.click();assert.equal(ids.guideSearch.value,'');assert.equal(ids.guideSearch.focused,true);
  assert.equal(topics.every(t=>!t.hidden),true);assert.equal(buttons[0].attrs['aria-pressed'],'true');
});
test('expand only visible sections and hash links reveal filtered topics',()=>{
  const {ids,topics,buttons,window}=fixture('#water');assert.equal(topics[0].details.open,true);
  buttons[2].events.click();ids.guideExpand.events.click();assert.equal(topics[1].details.open,true);assert.equal(topics[2].details.open,false);
  ids.guideExpand.events.click();assert.equal(topics[1].details.open,false);
  window.location.hash='#install';window.events.hashchange();assert.equal(topics.every(t=>!t.hidden),true);assert.equal(topics[2].details.open,true);
});
test('print includes entire manual then restores search/role/disclosure state',()=>{
  const {ids,topics,buttons,window}=fixture();buttons[1].events.click();ids.guideExpand.events.click();
  const prior=topics.map(t=>[t.hidden,t.details.open]);window.events.beforeprint();
  assert.equal(topics.every(t=>!t.hidden && t.details.open),true);window.events.afterprint();
  assert.deepEqual(topics.map(t=>[t.hidden,t.details.open]),prior);ids.guidePrint.events.click();assert.equal(window.printed,true);
});
test('unrelated pages are untouched',()=>assert.doesNotThrow(()=>init({getElementById:()=>null},{})));
