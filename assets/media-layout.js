(function(root){
 'use strict';
 function normalize(mode){return ['width','height'].includes(mode)?mode:'contain'}
 function size(mediaWidth,mediaHeight,viewWidth,viewHeight,mode){
  if(![mediaWidth,mediaHeight,viewWidth,viewHeight].every(n=>Number.isFinite(n)&&n>0))return null;
  mode=normalize(mode);
  const scale=mode==='width'?viewWidth/mediaWidth:mode==='height'?viewHeight/mediaHeight:Math.min(viewWidth/mediaWidth,viewHeight/mediaHeight);
  return {width:mediaWidth*scale,height:mediaHeight*scale};
 }
 function apply(container,mode){
  const width=container.clientWidth,height=container.clientHeight;
  if(!(width>0&&height>0))return;
  container.querySelectorAll('img,video').forEach(el=>{
   const box=size(el.naturalWidth||el.videoWidth,el.naturalHeight||el.videoHeight,width,height,mode);
   el.style.objectFit='contain';
   el.style.width=box?box.width+'px':'100%';el.style.height=box?box.height+'px':'100%';
  });
  // Text always remains entirely visible, including long messages on portrait phones.
  container.querySelectorAll('.text-slide').forEach(el=>{
   el.style.transform='';
   const scale=Math.min(1,width/(el.scrollWidth||width),height/(el.scrollHeight||height));
   el.style.transform='scale('+scale+')';
  });
 }
 root.StageMediaLayout={normalize,size,apply};
 if(typeof module!=='undefined')module.exports=root.StageMediaLayout;
})(typeof globalThis!=='undefined'?globalThis:this);
