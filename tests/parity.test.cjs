const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const timeline=require('../assets/timeline.js');
const snapshots=JSON.parse(execFileSync(process.env.STAGETV_PHP||'C:\\xampp\\php\\php.exe',[__dirname+'/timeline.php'],{encoding:'utf8'}));
for(const {state,now,position} of snapshots){const result=timeline.resolve(state,now);assert.equal(result.item?.id,position.currentId);assert.equal(result.page,position.page);assert.equal(result.offsetMs,position.offsetMs);assert.equal(result.inDelay,position.inDelay)}
console.log('PASS PHP/JavaScript parity and server commands');
