const {test} = require('node:test');
const assert = require('node:assert/strict');
const {distance, contains, summarize, recent, mount} = require('../../public/js/nearby-flood.js');
const now = Date.parse('2026-10-03T10:00:00+07:00');
const row = change => ({id:1,lat:7.5,lng:99.5,level:3,trusted:true,subdistrict_code:'920101',updated_at:'2026-10-03T09:00:00+07:00',expires_at:'2026-10-03T12:00:00+07:00',...change});
test('nearby radius uses real distances and excludes reports from another area',()=>{
  assert.equal(distance([7.5,99.5],[7.5,99.5]),0);
  assert.ok(distance([7.5,99.5],[7.5,99.51])>1000);
  const d=summarize([row(),row({id:2,lng:100})],{point:[7.5,99.5],radius:3000},now);
  assert.equal(d.hits.length,1);assert.equal(d.trusted,1);
});
test('administrative selections match exact codes and expose unassigned-report coverage',()=>{
  const d=summarize([row(),row({id:2,subdistrict_code:'920102'}),row({id:3,subdistrict_code:null})],{subdistrict:'920101'},now);
  assert.equal(d.hits.length,1);assert.equal(d.unassigned,1);
});
test('expired, stale and dry observations are not reported as current flooding',()=>{
  assert.equal(recent([row({expires_at:'2026-10-03T08:00:00+07:00'}),row({updated_at:'2026-10-02T09:00:00+07:00'}),row({level:1}),row({lat:null})],now).length,0);
});
test('province checks respect polygon holes and separate islands',()=>{
  const shape={type:'MultiPolygon',coordinates:[[[[99,7],[100,7],[100,8],[99,8],[99,7]],[[99.2,7.2],[99.4,7.2],[99.4,7.4],[99.2,7.4],[99.2,7.2]]],[[[101,7],[102,7],[102,8],[101,8],[101,7]]]]};
  assert.equal(contains([7.5,99.5],shape),true);assert.equal(contains([7.3,99.3],shape),false);assert.equal(contains([7.5,101.5],shape),true);assert.equal(contains([9,99],shape),false);
});
test('GPS errors, poor accuracy and out-of-province locations never claim no flooding',()=>{
  const descriptor=Object.getOwnPropertyDescriptor(globalThis,'navigator');let callback;
  Object.defineProperty(globalThis,'navigator',{configurable:true,value:{geolocation:{getCurrentPosition(success){callback=success;}}}});
  class Node{constructor(){this.events={};this.value='';}addEventListener(n,f){this.events[n]=f;}setAttribute(){}focus(){}}
  const nodes=new Map(),get=s=>{if(!nodes.has(s))nodes.set(s,new Node());return nodes.get(s);};
  const widget=mount({querySelector:get,querySelectorAll:()=>[]});
  const boundary={geometry:{type:'Polygon',coordinates:[[[99,7],[100,7],[100,8],[99,8],[99,7]]]}};
  try{
    widget.setContext({code:'92',name:'ตรัง',areas:[],boundary});widget.setReports({reports:[]});
    get('[data-near-gps]').events.click();callback({coords:{latitude:7.5,longitude:99.5,accuracy:200000}});
    assert.match(get('[data-near-result]').textContent,/คลาดเคลื่อน/);
    get('[data-near-gps]').events.click();callback({coords:{latitude:13.5,longitude:100.5,accuracy:10}});
    assert.match(get('[data-near-result]').textContent,/นอกขอบเขต/);
    get('[data-near-gps]').events.click();const oldCallback=callback;widget.reset();oldCallback({coords:{latitude:7.5,longitude:99.5,accuracy:10}});
    assert.match(get('[data-near-result]').textContent,/เลือกตำแหน่ง/);
  }finally{if(descriptor)Object.defineProperty(globalThis,'navigator',descriptor);else delete globalThis.navigator;}
});
