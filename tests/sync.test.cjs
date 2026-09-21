const vm=require('node:vm'),fs=require('node:fs'),assert=require('node:assert/strict');
const pending=[];let time=0;
const context=vm.createContext({performance:{now:()=>time},AbortController,setTimeout,clearTimeout,fetch:()=>new Promise(resolve=>pending.push(resolve))});
vm.runInContext(fs.readFileSync('assets/sync.js','utf8')+';globalThis.client=new StageSync(()=>{},()=>{})',context);
function respond(index,version){pending[index]({ok:true,json:async()=>({ok:true,state:{version},serverTimeMs:1000000+time})})}
(async()=>{
 const command=context.client.request('command');time=10;
 const poll=context.client.request('state');time=20;respond(1,1);await poll;
 time=30;respond(0,2);await command;
 assert.equal(context.client.state.version,2,'A newer mutation must win even when an overlapping poll started later');
 const old=context.client.request('state');const fresh=context.client.request('command');time=40;respond(3,4);await fresh;respond(2,3);await old;
 assert.equal(context.client.state.version,4,'Late old responses cannot undo confirmed controls');
 assert.ok(context.client.now()>1000000);
 console.log('PASS clock estimation and out-of-order command responses');
})().catch(error=>{console.error(error);process.exitCode=1});
