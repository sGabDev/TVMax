const assert=require('node:assert/strict');
const {size,normalize,apply}=require('../assets/media-layout.js');
for(const [vw,vh] of [[360,800],[800,360],[1920,1080],[3840,2160]]){
 for(const [mw,mh] of [[1920,1080],[1080,1920],[1000,1000],[2400,400],[400,2400]]){
  const screen=size(mw,mh,vw,vh,'contain');
  assert.ok(screen.width<=vw+.001&&screen.height<=vh+.001,'Fit screen never crops');
  assert.ok(Math.abs(screen.width/screen.height-mw/mh)<.00001,'Aspect ratio is preserved');
  assert.equal(size(mw,mh,vw,vh,'width').width,vw);
  assert.ok(Math.abs(size(mw,mh,vw,vh,'height').height-vh)<.00001);
 }
}
assert.equal(normalize('cover'),'contain');assert.equal(size(0,0,360,800,'width'),null);
const image={naturalWidth:1920,naturalHeight:1080,style:{}};
const container={clientWidth:360,clientHeight:800,querySelectorAll:s=>s==='img,video'?[image]:[]};
apply(container,'contain');assert.equal(image.style.width,'360px');assert.equal(image.style.height,'202.5px');
container.clientWidth=800;container.clientHeight=360;apply(container,'contain');assert.equal(image.style.width,'640px');assert.equal(image.style.height,'360px');
apply(container,'width');assert.equal(image.style.width,'800px');assert.equal(image.style.height,'450px');
console.log('PASS media fit: portrait, landscape, TVs, extreme ratios and rotation');
