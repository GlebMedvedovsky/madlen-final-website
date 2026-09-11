// Uses the disposable qa-bootstrap.php instance only. No real dispatch/Netcup.
import {writeFileSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.MADLEN_QA_PLAYWRIGHT).href);
const out=resolve('installation-artifacts/release-qa');mkdirSync(out,{recursive:true});
const docker=(...args)=>execFileSync('docker',['exec','madlen-release-qa',...args],{encoding:'utf8',maxBuffer:5*1024*1024,timeout:240000});
const state=()=>JSON.parse(docker('php','/workspace/scripts/release/qa-console.php','state'));
const browser=await chromium.launch({headless:true,executablePath:process.env.MADLEN_QA_CHROMIUM});
const context=await browser.newContext({viewport:{width:1440,height:1000}});
await context.route('**/*',r=>['127.0.0.1','localhost'].includes(new URL(r.request().url()).hostname)?r.continue():r.abort());
const page=await context.newPage();
const title=page.locator('[id="form.title_de"]');
const requestId=()=>page.evaluate(()=>{
  for(const root of document.querySelectorAll('[wire\\:id]')) {
    const value=window.Livewire.find(root.getAttribute('wire:id')).get('productionRequestIds');
    if(typeof value?.publish==='string') return value.publish;
  }
  throw Error('Editor Livewire identity not found');
});
const post=async(job,path,data)=>{
  const r=await context.request.post('http://127.0.0.1:8097/api/publisher/v1/publications/'+job.id+path,{headers:{Authorization:'Bearer qa-only-api'},data});
  assert.equal(r.status(),200,await r.text());return r.json();
};
const run=async(job,runner)=>{
  console.log('Local build/activate',job.sequence,runner);
  assert.equal((await post(job,'/claim',{runner_id:runner})).claimed,true);
  const result=JSON.parse(docker('php','/workspace/scripts/release/qa-console.php','build-production',job.id));
  await post(job,'/status',{status:'uploading',runner_id:runner});
  assert.match(docker('php','/workspace/scripts/release/qa-console.php','artisan','madlen:production:activate',job.id,String(job.sequence),result.archive,result.sha256,'--runner='+runner),/atomar aktiviert/);
  console.log('Active',job.sequence);
};
const confirm=async()=>{
  await page.getByRole('button',{name:'Veröffentlichen',exact:true}).click();
  const button=page.getByRole('button',{name:'Bestätigen',exact:true});await button.waitFor();
  await button.click();await button.waitFor({state:'hidden'});
};
const report={};
try {
  await page.goto('http://127.0.0.1:8097/admin/login');
  await page.getByLabel('E-Mail-Adresse').fill('qa@example.test');
  await page.locator('input[type=password]').fill('Local-QA-only-928!');
  await page.getByRole('button',{name:'Anmelden',exact:true}).click();await page.waitForURL('**/admin');
  const project=state().projects.find(p=>!p.deleted_at && p.slug.startsWith('qa-') && p.cover_media_id);
  assert(project,'Run the existing synthetic create/upload fixture first');
  const editor='http://127.0.0.1:8097/admin/projects/'+project.id+'/edit';
  await page.goto(editor);let loads=0;page.on('load',()=>loads++);
  const initialCount=state().publications.length;
  const originalId=await requestId();
  const firstText='QA review first '+Date.now();await title.fill(firstText);await confirm();
  const first=state().publications.at(-1);assert.equal(first.request_id,originalId);
  assert.notEqual(await requestId(),originalId);
  await run(first,'401-1');
  assert.equal(state().publications.at(-1).status,'active');
  const secondText='QA review new content same tab '+Date.now();
  await title.fill(secondText);await confirm();
  const second=state().publications.at(-1);
  assert.notEqual(second.id,first.id);assert.notEqual(second.request_id,first.request_id);
  assert.equal(second.sequence,first.sequence+1);await run(second,'402-1');
  assert.equal(state().publications.length,initialCount+2);
  const publicPage=await context.newPage();
  await publicPage.goto('http://127.0.0.1:8098/portfolio/'+project.slug);
  await publicPage.getByRole('heading',{name:secondText,exact:true}).waitFor();
  await publicPage.locator('header a:visible').filter({hasText:/^\s*EN\s*$/}).first().click();
  await publicPage.waitForURL('**/en/portfolio/'+project.slug);
  report.newIntent={first:first.id,second:second.id,firstRequest:first.request_id,secondRequest:second.request_id,secondText,deEnHTTP:true};

  // Let the server complete the next operation, then drop ONLY its response.
  const lostId=await requestId();const lostText='QA review lost response '+Date.now();
  const updateURL=await page.locator('script[data-update-uri]').getAttribute('data-update-uri');
  assert.equal(new URL(updateURL).origin,'http://127.0.0.1:8097');
  await title.fill(lostText);
  await page.getByRole('button',{name:'Veröffentlichen',exact:true}).click();
  await page.getByRole('button',{name:'Bestätigen',exact:true}).waitFor();
  let lostBody;let completed;
  const lost=new Promise(resolve=>{completed=resolve;});
  await page.route(updateURL,async route=>{
    const body=route.request().postData();
    if(!lostBody){
      lostBody=body;const result=await route.fetch();assert.equal(result.status(),200);
      await route.abort('connectionreset');completed();
    }else await route.continue();
  });
  await page.getByRole('button',{name:'Bestätigen',exact:true}).click();
  let timer;
  try {await Promise.race([lost,new Promise((_,reject)=>{timer=setTimeout(()=>reject(Error('Lost response interception timed out')),30000);})]);}
  finally {clearTimeout(timer);}
  const third=state().publications.at(-1);assert.equal(third.request_id,lostId);
  assert.equal(await requestId(),lostId);assert.equal(await title.inputValue(),lostText);
  // Replay the exact failed Livewire body from the original browser/session,
  // not a remounted component or a newly generated operation ID.
  const replay=await page.evaluate(async({body,url})=>{
    const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-Livewire':''},body});
    return {status:response.status,body:await response.text()};
  },{body:lostBody,url:updateURL});
  assert.equal(replay.status,200);assert(JSON.parse(replay.body).components.length);
  assert.equal(state().publications.length,initialCount+3);
  assert.equal(state().publications.at(-1).id,third.id);
  assert.equal(page.url(),editor);assert.equal(loads,0);assert.equal(await title.inputValue(),lostText);
  report.lostResponse={requestId:lostId,publication:third.id,replayStatus:replay.status,duplicate:false,editorReloads:loads,formPreserved:true};
  await page.screenshot({path:out+'/review-publish-same-tab.png',fullPage:true});
  await post(third,'/claim',{runner_id:'403-1'});
  await post(third,'/status',{runner_id:'403-1',status:'failed',message:'Ende der isolierten Lost-response-Prüfung; kein externer Runner.'});
  report.limitations=['Local Chromium and synthetic CMS, HTTP dispatch faked; no Netcup/GitHub runner','Lost reply replay uses exact browser fetch body, not an automatic retry feature'];
  writeFileSync(resolve(out,'review-publish-browser.json'),JSON.stringify(report,null,2));
  console.log(JSON.stringify(report,null,2));
} catch(error) {
  await page.screenshot({path:out+'/review-publish-error.png',fullPage:true}).catch(()=>{});
  throw error;
} finally {await browser.close();}
