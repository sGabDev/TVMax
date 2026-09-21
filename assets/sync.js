class StageSync {
 constructor(onState,onError){this.onState=onState;this.onError=onError;this.state=null;this.clock=0;this.clockAt=0;this.bestRtt=Infinity;this.busy=false;this.sequence=0;this.accepted=0}
 now(){return this.clock+performance.now()-this.clockAt}
 async request(action,options={}){
  const sequence=++this.sequence,start=performance.now(),controller=new AbortController();
  const timeout=setTimeout(()=>controller.abort(),action==='upload'||action==='prepare'?240000:10000);
  try {
   const headers={...options.headers};
   if(globalThis.StageAuth?.csrf)headers['X-CSRF-Token']=StageAuth.csrf;
   if(globalThis.StageAuth?.panel)headers['X-Stage-Panel']='1';
   const response=await fetch('api.php?action='+action,{cache:'no-store',...options,headers,signal:controller.signal});
   const data=await response.json();if(!response.ok||!data.ok){if(response.status===401&&globalThis.StageAuth?.panel)location.href='apresentador.php';throw new Error(data.error||'Falha ao comunicar com o servidor.')}
   if(data.state && (!this.state||data.state.version>this.state.version||(data.state.version===this.state.version&&sequence>=this.accepted))){
    const end=performance.now(),rtt=end-start;
    // The lowest-latency sample avoids dependence on the device wall clock.
    if(action!=='upload'&&action!=='prepare'&&(rtt<=this.bestRtt*1.25||!this.clock||end-this.clockAt>60000)){this.clock=data.serverTimeMs+rtt/2;this.clockAt=end;this.bestRtt=rtt}
    this.accepted=Math.max(this.accepted,sequence);this.state=data.state;this.onState(data.state);
   }
   return data;
  } finally {clearTimeout(timeout)}
 }
 async poll(){if(this.busy)return;this.busy=true;try{await this.request('state')}catch(error){this.onError(error)}finally{this.busy=false}}
 start(){this.poll();this.interval=setInterval(()=>this.poll(),300);document.addEventListener('visibilitychange',()=>{if(!document.hidden){this.bestRtt=Infinity;this.poll()}})}
}
