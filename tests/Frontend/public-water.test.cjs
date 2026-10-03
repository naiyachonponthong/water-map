// In-memory DOM checks with mocked requests, separate from live browser verification.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const F=require('../../public/js/nearby-flood.js');
const source=fs.readFileSync(path.join(__dirname,'../../public/js/public-water.js'),'utf8');
const settle=()=>new Promise(resolve=>setImmediate(resolve));
class Element{
  constructor(){this.dataset={};this.value='';this.events={};this.textContent='';this.hidden=false;this.checked=true;}
  addEventListener(name,fn){this.events[name]=fn;}
  emit(name,event={}){return this.events[name]?.({target:this,...event});}
}
function setup(){
  const nodes=new Map(), el=id=>{if(!nodes.has(id))nodes.set(id,new Element());return nodes.get(id);};
  const locations=['trang','bangkok'].map(slug=>({slug,name:slug,url:`/${slug}/water-map`,homeUrl:`/${slug}`,contextUrl:`/${slug}/context`,dataUrl:`/${slug}/stations`,reportsUrl:`/${slug}/reports`}));
  el('waterLocations').textContent=JSON.stringify(locations);el('waterPage').dataset.slug='trang';
  el('waterPage').querySelector=selector=>el(selector);
  const pending=[], historyCalls=[], widget={resetCalls:0,context:null,reports:null,error:false,reset(){this.resetCalls++;this.context=null;this.reports=null;},setContext(c){this.context=c;},setReports(r,e){this.reports=r;this.error=e;},contextError(){this.error=true;}};
  vm.runInNewContext(source,{document:{getElementById:el,hidden:false,title:''},window:{},FloodNear:{...F,mount:()=>widget},AbortController,Date,console,setInterval(){},history:{replaceState:(...args)=>historyCalls.push(args)},fetch:(url,options)=>new Promise((resolve,reject)=>pending.push({url,options,resolve:data=>resolve({ok:true,json:async()=>data}),reject}))});
  return{el,pending,widget,historyCalls,change(slug){el('waterProvince').value=slug;el('waterProvince').emit('change');}};
}
const data=(name)=>({stations:[{id:name,name,lat:7.5,lng:99.5,value:2,relative_bank:-1,unit:'ม. รทก.',color:'#16875c',situation:3,outdated:false,measured_at:new Date().toISOString()}],stale:false,fetched_at:new Date().toISOString()});
test('province switches clear metrics and ignore late responses from the old province',async()=>{
  const x=setup();x.change('bangkok');assert.equal(x.el('waterStationCount').textContent,'—');assert.equal(x.widget.resetCalls,2);
  for(const r of x.pending.slice(3)){r.resolve(r.url.endsWith('/stations')?data('Bangkok'):r.url.endsWith('/context')?{name:'Bangkok',areas:[],boundary_source:'reference'}:{reports:[]});}
  await settle();assert.equal(x.el('waterStationCount').textContent,1);
  for(const r of x.pending.slice(0,3)){r.resolve(r.url.endsWith('/stations')?data('OLD TRANG'):r.url.endsWith('/context')?{name:'OLD TRANG'}:{reports:[{id:99}]});}
  await settle();assert.equal(x.widget.context.name,'Bangkok');assert.equal(x.widget.reports.reports.length,0);assert.ok(!x.el('waterStationList').innerHTML.includes('OLD TRANG'));assert.ok(x.pending[0].options.signal.aborted);
});
test('provider failure is unavailable, not zero stations or zero current overflows',async()=>{
  const x=setup();x.pending[0].resolve({areas:[],boundary_source:'reference'});x.pending[1].reject(new Error('Network unavailable'));x.pending[2].reject(new Error('Network unavailable'));await settle();
  assert.equal(x.el('waterStationCount').textContent,'—');assert.equal(x.el('waterOverflowCount').textContent,'—');assert.equal(x.el('waterReportCount').textContent,'—');assert.equal(x.widget.error,true);assert.match(x.el('waterStatus').textContent,/ยังเชื่อมข้อมูล/);
});
test('stale cache and old measurements cannot inflate current overflow count',async()=>{
  const x=setup(), d=data('old station');d.stations[0].situation=5;d.stations[0].outdated=true;
  x.pending[0].resolve({areas:[],boundary_source:'reference'});x.pending[1].resolve(d);x.pending[2].resolve({reports:[]});await settle();assert.equal(x.el('waterOverflowCount').textContent,0);
  x.el('waterRefresh').emit('click');d.stale=true;x.pending[3].resolve({areas:[],boundary_source:'reference'});x.pending[4].resolve(d);x.pending[5].resolve({reports:[]});await settle();assert.equal(x.el('waterOverflowCount').textContent,'—');assert.match(x.el('waterStatus').textContent,/ข้อมูลเก่า/);
});
