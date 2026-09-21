const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const Timeline=require('../assets/timeline.js');
class Element {
 constructor(tag='div'){this.tagName=tag.toUpperCase();this.children=[];this.style={};this.dataset={};this.attrs={};this.hidden=false;this.value='';const values=new Set();this.classList={add:x=>values.add(x),remove:x=>values.delete(x),contains:x=>values.has(x),toggle:(x,on)=>on?values.add(x):values.delete(x)}}
 appendChild(x){this.children.push(x)}append(...xs){this.children.push(...xs)}replaceChildren(){this.children=[]}
 querySelectorAll(s){return this.children.filter(x=>s.split(',').includes(x.tagName.toLowerCase()))}
 setAttribute(k,v){this.attrs[k]=v}addEventListener(){}removeAttribute(k){delete this.attrs[k]}pause(){this.paused=true}load(){}
}
function environment(){
 const elements=new Map(),buttons=['play','prev','next','blackout'].map(cmd=>{const e=new Element('button');e.dataset.cmd=cmd;return e});
 elements.set('#playPause',buttons[0]);elements.set('#standbyButton',buttons[3]);
 const timers=new Map();let id=0,clock=100000;
 const document={querySelector:s=>{if(s==='.modal.open')return null;if(!elements.has(s))elements.set(s,new Element());return elements.get(s)},querySelectorAll:s=>s==='[data-cmd]'?buttons:[],createElement:t=>new Element(t),addEventListener(){}};
 class Sync {constructor(onState){this.onState=onState}now(){return clock}start(){}}
 const context=vm.createContext({document,StageMediaLayout:require('../assets/media-layout.js'),StageTimeline:Timeline,StageSync:Sync,Date,Image:Element,performance:{now:()=>clock},setInterval(){},setTimeout:(fn,ms)=>{timers.set(++id,{fn,at:clock+ms});return id},clearTimeout:id=>timers.delete(id),location:{reload(){}},FormData:class{append(){}}});
 const run=s=>vm.runInContext(s,context);
 function advance(ms){clock+=ms;for(const [id,timer] of timers)if(timer.at<=clock){timers.delete(id);timer.fn()}}
 return {context,document,buttons,run,advance};
}
const state={version:1,items:[{id:'a',enabled:true,type:'text',text:'First',title:'One',duration:2},{id:'b',enabled:true,type:'text',text:'Second',title:'Two',duration:2}],screen:{transitionMs:650,volume:70},playback:{anchorId:'a',anchorAtMs:100000,offsetMs:0,paused:false,reloadNonce:0}};
const viewer=environment();vm.runInContext(fs.readFileSync('assets/viewer.js','utf8'),viewer.context);
viewer.run(`sync.onState(${JSON.stringify(state)})`);viewer.advance(800);
assert.equal(viewer.run('layers[layer].children.length'),1,'Transition cleanup must not remove the active layer');
viewer.advance(1300);viewer.run('update()');assert.equal(viewer.run('position.item.id'),'b');viewer.advance(800);assert.equal(viewer.run('layers[layer].children.length'),1);
const presenter=environment();vm.runInContext(fs.readFileSync('assets/presenter.js','utf8'),presenter.context);
presenter.run(`sync.onState(${JSON.stringify(state)})`);
assert.equal(presenter.buttons[0].dataset.cmd,'pause');
assert.equal(presenter.buttons[0].attrs['aria-label'],'Pausar');
state.version++;state.playback.paused=true;presenter.run(`sync.onState(${JSON.stringify(state)})`);
assert.equal(presenter.buttons[0].dataset.cmd,'play');assert.equal(presenter.buttons[0].attrs['aria-label'],'Reproduzir');assert.equal(presenter.document.querySelector('#playbackStatus').textContent,'Pausado');
state.version++;state.playback.blackout=true;presenter.run(`sync.onState(${JSON.stringify(state)})`);assert.equal(presenter.buttons[3].attrs['aria-pressed'],'true');
viewer.run(`sync.onState(${JSON.stringify(state)})`);
assert.equal(viewer.document.querySelector('#blackout').attrs['aria-hidden'],'false');
assert.equal(viewer.document.querySelector('#blackout').classList.contains('on'),true);
assert.equal(viewer.document.querySelector('#progressWrap').style.display,'none');
console.log('PASS viewer layers and presenter play/pause/blackout indicators');
(async()=>{
 const env=environment(),players=[];let blocked=false;
 env.document.createElement=tag=>{const el=new Element(tag);if(['video','audio'].includes(tag)){
  el.getAttribute=key=>key==='src'?el.src:el.attrs[key];el.calls=0;
  el.play=()=>{el.calls++;return blocked?Promise.reject(Object.assign(new Error('blocked'),{name:'NotAllowedError'})):Promise.resolve()};players.push(el);
 }return el};
 for(const name of ['#layerA','#layerB'])env.document.querySelector(name).contains=el=>env.document.querySelector(name).children.includes(el);
 vm.runInContext(fs.readFileSync('assets/viewer.js','utf8'),env.context);
 assert.equal(env.document.querySelector('#enableMedia').hidden,false,'Audio activation is visible without state or content');
 blocked=true;await env.document.querySelector('#enableMedia').onclick();
 assert.equal(env.document.querySelector('#enableMedia').hidden,false,'Permission rejection must retain the activation button');
 assert.equal(env.document.querySelector('#enableMedia').disabled,false,'Activation can be retried');
 blocked=false;await env.document.querySelector('#enableMedia').onclick();
 assert.equal(players.length,4,'Reuse the two video and two audio players on retry');
 assert.ok(players.every(el=>el.calls===2&&el.paused),'Prime and pause all players without content');
 assert.equal(env.document.querySelector('#enableMedia').hidden,true);
 assert.equal(env.document.querySelector('#audioHelp').hidden,true);
 env.run('audioEnabled=false');env.run(`sync.onState(${JSON.stringify({...state,items:[],playback:{...state.playback,paused:true,blackout:true}})})`);
 assert.equal(env.document.querySelector('#enableMedia').hidden,false,'Standby must not hide audio activation');
 await env.document.querySelector('#enableMedia').onclick();
 assert.ok(players.every(el=>el.paused),'Activating during standby must not start the presentation');
 console.log('PASS audio activation without media, permission failure/retry, player reuse and standby');
})().catch(error=>{console.error(error);process.exitCode=1});
{
 const env=environment();vm.runInContext(fs.readFileSync('assets/presenter.js','utf8'),env.context);
 const cards=Array.from({length:25},(_,i)=>({dataset:{title:'archive '+i},hidden:false}));
 env.document.querySelectorAll=s=>s==='.archived-card'?cards:[];
 env.run("queueView='archive';filterQueue()");assert.equal(cards.filter(c=>!c.hidden).length,10);
 env.document.querySelector('#archiveNext').onclick();assert.equal(cards[10].hidden,false);assert.equal(cards[0].hidden,true);
 env.document.querySelector('#archivePageSize').value='20';env.document.querySelector('#archivePageSize').onchange();assert.equal(cards.filter(c=>!c.hidden).length,20);
 env.document.querySelector('#queueSearch').value='archive 24';env.document.querySelector('#queueSearch').oninput();assert.equal(cards.filter(c=>!c.hidden).length,1);
 console.log('PASS archive pagination, page size and search');
}
(async()=>{
 const fields=new Map(['.duration','.delay','.start','.end','.select-item','.archive-item','.move-up','.move-down','.save-schedule','.cancel-schedule','.schedule-status'].map(key=>[key,new Element()]));
 fields.get('.duration').value='10';fields.get('.delay').value='0';
 const card={classList:new Element().classList,dataset:{id:'a'},querySelector:s=>fields.get(s),querySelectorAll:s=>s.split(',').map(key=>fields.get(key))};
 const original=presenter.document.querySelectorAll;
 presenter.document.querySelectorAll=s=>s==='.item'?[card]:original(s);
 presenter.run('bindItems()');fields.get('.start').value='2030-05-01T10:00';fields.get('.end').value='2030-05-01T12:00';fields.get('.start').oninput();
 assert.equal(presenter.run('dirty'),false,'Schedule drafts must not trigger autosave');
 presenter.run('globalThis.requests=[];sync.request=async(action,opts)=>requests.push({action,body:opts.body})');
 await presenter.run('save()');
 const payload=JSON.parse(presenter.run('requests[0].body'));
 assert.equal('startsAt' in payload.items[0],false,'Other settings must not submit draft schedules');
 presenter.document.querySelectorAll=original;presenter.run('renderQueue()');
 assert.ok(presenter.document.querySelector('#playlist').innerHTML.includes('2030-05-01T10:00'),'Draft survives rerender');
 presenter.document.querySelectorAll=s=>s==='.item'?[card]:original(s);
 await fields.get('.save-schedule').onclick();
 assert.equal(presenter.run('requests[1].action'),'schedule');
 assert.equal(presenter.run('scheduleDrafts.size'),0);
 presenter.document.querySelectorAll=original;
 console.log('PASS schedule drafts, rerender persistence and explicit save');
})().catch(error=>{console.error(error);process.exitCode=1});
(async()=>{
 const pending=[];
 viewer.document.createElement=tag=>{const el=new Element(tag);if(tag==='img')el.decode=()=>new Promise(resolve=>pending.push(resolve));return el};
 const images={version:20,items:[{id:'image-a',type:'image',src:'a.png',enabled:true,title:'A',duration:100},{id:'image-b',type:'image',src:'b.png',enabled:true,title:'B',duration:100}],screen:{transition:'slide',transitionMs:650,volume:70},playback:{anchorId:'image-a',anchorAtMs:100000,offsetMs:0,paused:true,reloadNonce:0}};
 const oldKey=viewer.run('visibleKey');
 viewer.run(`sync.onState(${JSON.stringify(images)})`);
 assert.equal(viewer.run('visibleKey'),oldKey,'Keep the old picture while decoding the incoming image');
 images.version++;images.playback.anchorId='image-b';viewer.run(`sync.onState(${JSON.stringify(images)})`);
 pending[0]();for(let i=0;i<5;i++)await Promise.resolve();
 assert.equal(viewer.run('visibleKey'),oldKey,'A superseded load cannot replace the currently requested media');
 pending[1]();for(let i=0;i<5;i++)await Promise.resolve();
 assert.ok(viewer.run('visibleKey').startsWith('image-b:'),'Display the decoded, most recent media');
 viewer.advance(1000);assert.equal(viewer.run('layers[layer].children.length'),1,'Cleanup must preserve the newly decoded layer');
 console.log('PASS transition loading, cancellation and layer cleanup');
})().catch(error=>{console.error(error);process.exitCode=1});
