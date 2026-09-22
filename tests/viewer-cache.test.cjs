const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
(async()=>{
 const events={},entries=new Map();let network=0;
 const cache={async match(key){return entries.get(typeof key==='string'?key:key.url)?.clone();},async put(key,r){entries.set(typeof key==='string'?key:key.url,r.clone());},async delete(key){return entries.delete(typeof key==='string'?key:key.url);},async keys(){return [...entries.keys()].map(url=>new Request(url));}};
 const env=vm.createContext({URL,Headers,Response,Request,AbortController,setTimeout,clearTimeout,Date,console,
  caches:{open:async()=>cache},fetch:async()=>{network++;return new Response('0123456789',{headers:{'Content-Type':'video/mp4','Content-Length':'10'}});},
  self:{location:{href:'https://example.com/tv/viewer-cache-sw.js'},addEventListener:(n,fn)=>events[n]=fn}});
 vm.runInContext(fs.readFileSync('viewer-cache-sw.js','utf8'),env);
 const run=code=>vm.runInContext(code,env),url='https://example.com/tv/uploads/movie.mp4';
 assert.equal(run("allowed('../api.php?action=state')"),null);assert.equal(run("allowed('https://other.com/tv/uploads/a.mp4')"),null);
 await run("warm('https://example.com/tv/uploads/movie.mp4')");assert.equal(network,1);
 await run("warm('https://example.com/tv/uploads/movie.mp4')");assert.equal(network,1,'Reuse complete cached media');
 async function get(range){let promise;events.fetch({request:new Request(url,{headers:range?{Range:range}:{}}),respondWith:p=>promise=p});return promise;}
 let r=await get('bytes=2-5');assert.equal(r.status,206);assert.equal(r.headers.get('Content-Range'),'bytes 2-5/10');assert.equal(await r.text(),'2345');
 r=await get('bytes=-3');assert.equal(await r.text(),'789');r=await get('bytes=8-');assert.equal(await r.text(),'89');
 r=await get();assert.equal(await r.text(),'0123456789');assert.equal(network,1,'Replay and seeking avoid network');
 await get('bytes=20-30');assert.equal(network,2,'Unsupported range falls back safely');
 entries.set(url,new Response('old',{headers:{'X-TVMax-Cached-At':String(Date.now()-7*3600000),'Content-Length':'3'}}));
 assert.equal(await run("cached('https://example.com/tv/uploads/movie.mp4')"),null);assert.equal(entries.size,0,'Expired files are deleted');
 for(let i=0;i<3;i++)entries.set(url+i,new Response('x',{headers:{'X-TVMax-Cached-At':String(Date.now()-i*1000),'Content-Length':String(250*1024*1024)}}));
 await run('trimCache()');assert.equal(entries.size,2,'Evict oldest files over total byte budget');assert.ok(!entries.has(url+'2'));
 console.log('PASS media cache reuse, ranges, suffixes, fallback, expiry and space eviction');
})().catch(e=>{console.error(e);process.exitCode=1;});
