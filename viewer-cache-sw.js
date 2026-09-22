'use strict';
const CACHE='tvmax-media-v1',TTL=6*60*60*1000,LIMIT=512*1024*1024,MAX_FILE=256*1024*1024,MAX_ENTRIES=500;
const base=new URL('./',self.location.href),prefix=new URL('uploads/',base).pathname;
let queue=Promise.resolve();
function allowed(value){try{const u=new URL(value,base);return u.origin===base.origin&&u.pathname.startsWith(prefix)&&/\.(png|jpe?g|webp|gif|mp4|webm|ogv|mp3|ogg|wav)$/i.test(u.pathname)?u.href:null;}catch{return null;}}
function rangeBounds(header,size){
 const m=/^bytes=(\d*)-(\d*)$/.exec(header||'');if(!m||(!m[1]&&!m[2]))return null;
 const start=m[1]?Number(m[1]):Math.max(0,size-Number(m[2])),end=m[1]?(m[2]?Math.min(Number(m[2]),size-1):size-1):size-1;
 return Number.isSafeInteger(start)&&Number.isSafeInteger(end)&&start>=0&&start<size&&end>=start?[start,end]:null;
}
async function cached(url){const cache=await caches.open(CACHE),r=await cache.match(url);if(!r)return null;if(Date.now()-Number(r.headers.get('X-TVMax-Cached-At'))>TTL){await cache.delete(url);return null;}return r;}
async function trimCache(incoming=0){
 const cache=await caches.open(CACHE),keys=await cache.keys();let total=0;const rows=[];
 for(const key of keys){const r=await cache.match(key);if(!r)continue;const time=Number(r.headers.get('X-TVMax-Cached-At')),size=Number(r.headers.get('Content-Length'))||0;
  if(Date.now()-time>TTL){await cache.delete(key);continue;}rows.push({key,time,size});total+=size;
 }
 rows.sort((a,b)=>a.time-b.time);while(rows.length&&(total+incoming>LIMIT||rows.length>=MAX_ENTRIES)){const row=rows.shift();await cache.delete(row.key);total-=row.size;}
}
async function warm(url){
 if(await cached(url))return;
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),180000);
 try{
  const r=await fetch(url,{signal:controller.signal,cache:'force-cache'}),length=Number(r.headers.get('Content-Length'));
  if(r.status!==200||!length||length>MAX_FILE||!/^image\/|^video\/|^audio\//.test(r.headers.get('Content-Type')||'')){await r.body?.cancel();return;}
  await trimCache(length);const headers=new Headers(r.headers);headers.set('X-TVMax-Cached-At',String(Date.now()));
  const cache=await caches.open(CACHE);await cache.put(url,new Response(r.body,{status:200,headers}));
 }catch{/* Quota, offline and unsupported storage leave normal network playback available. */}finally{clearTimeout(timer);}
}
self.addEventListener('install',event=>event.waitUntil(self.skipWaiting()));
self.addEventListener('activate',event=>event.waitUntil(Promise.all([self.clients.claim(),trimCache()])));
self.addEventListener('message',event=>{
 if(event.data?.type!=='WARM_MEDIA'||!Array.isArray(event.data.urls))return;
 const urls=[...new Set(event.data.urls.map(allowed).filter(Boolean))].slice(0,MAX_ENTRIES);
 queue=queue.catch(()=>{}).then(async()=>{for(const url of urls)await warm(url);});event.waitUntil(queue);
});
self.addEventListener('fetch',event=>{
 const url=allowed(event.request.url);if(!url||event.request.method!=='GET')return;
 event.respondWith((async()=>{
  try{
   const r=await cached(url);if(r){const range=event.request.headers.get('Range');if(!range)return r;
    const blob=await r.blob(),bounds=rangeBounds(range,blob.size);
    if(!bounds)return fetch(event.request);
    const [start,end]=bounds,headers=new Headers(r.headers);headers.set('Content-Range',`bytes ${start}-${end}/${blob.size}`);headers.set('Content-Length',String(end-start+1));headers.set('Accept-Ranges','bytes');headers.delete('Content-Encoding');
    return new Response(blob.slice(start,end+1,blob.type),{status:206,headers});
   }
  }catch{/* Fall through when browser storage is unavailable. */}
  return fetch(event.request);
 })());
});
