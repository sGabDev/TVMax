const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const stored=new Map();let reloads=0;
function environment(){
 const timers=[],events={},classes=new Set(),navigator={onLine:true};
 const env=vm.createContext({navigator,location:{href:'https://example.com/visualizador.php',pathname:'/visualizador.php',reload(){reloads++;}},URL,Date,ErrorEvent:class{},
  sessionStorage:{getItem:k=>stored.get(k),setItem:(k,v)=>stored.set(k,v)},
  document:{body:{classList:{add:k=>classes.add(k),remove:k=>classes.delete(k)}},addEventListener:(n,f)=>events[n]=f},
  setInterval(){},setTimeout:(fn,ms)=>{const t={fn,ms};timers.push(t);return t;},clearTimeout:t=>{if(t)t.cancelled=true;},addEventListener:(n,f)=>events[n]=f});
 vm.runInContext(fs.readFileSync('assets/viewer-resilience.js','utf8'),env);
 return {env,timers,events,classes,navigator,fire(ms){for(const t of timers.splice(0))if(!t.cancelled&&t.ms===ms)t.fn();}};
}
let e=environment();e.fire(3000);assert.ok(e.classes.has('cursor-idle'));e.events.pointermove();assert.ok(!e.classes.has('cursor-idle'));
for(let i=0;i<3;i++){e=environment();e.env.StageRecovery.recover('Test');e.env.StageRecovery.recover('Duplicate');e.fire(5000);}
assert.equal(reloads,3,'Only one reload per page failure');e=environment();e.env.StageRecovery.recover('Test');e.fire(5000);assert.equal(reloads,3,'Persist retry cap across page reloads');
stored.clear();e=environment();e.navigator.onLine=false;e.env.StageRecovery.recover('Offline');e.fire(5000);assert.equal(reloads,3,'Do not reload an offline page');
console.log('PASS cursor inactivity, automatic recovery deduplication, persisted retry cap and offline playback protection');
