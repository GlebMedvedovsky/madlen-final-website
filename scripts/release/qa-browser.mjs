import { mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';
const { chromium } = await import(pathToFileURL(process.env.MADLEN_QA_PLAYWRIGHT).href);
const out = resolve('installation-artifacts/release-qa');
mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: process.env.MADLEN_QA_CHROMIUM });
const context = await browser.newContext({ viewport: {width:1440,height:1000} });
await context.route('**/*', route => {
  const url=new URL(route.request().url());
  return ['127.0.0.1','localhost'].includes(url.hostname) ? route.continue() : route.abort();
});
const page = await context.newPage();
const errors = [];
page.on('pageerror', e => errors.push(e.message));
context.on('page', p => p.on('pageerror',e=>errors.push(e.message)));
const docker = (...args) => execFileSync('docker', ['exec','madlen-release-qa', ...args], {encoding:'utf8', maxBuffer:4*1024*1024, timeout:180000});
const state = () => JSON.parse(docker('php','/workspace/scripts/release/qa-console.php','state'));
try {
  await page.goto('http://127.0.0.1:8097/admin/login');
  await page.getByLabel('E-Mail-Adresse').fill('qa@example.test');
  await page.locator('input[type=password]').fill('Local-QA-only-928!');
  await page.getByRole('button', {name:'Anmelden',exact:true}).click();
  await page.waitForURL('**/admin');
  while (state().media.length < 2) {
  await page.goto('http://127.0.0.1:8097/admin/media-assets/create');
  await page.locator('input[type=file]').setInputFiles(resolve('public/images/hero/memories-photo.webp'));
  await page.getByText('Upload abgeschlossen', {exact:true}).waitFor({timeout:20000});
  await page.getByRole('button',{name:'Erstellen',exact:true}).click();
  await page.waitForURL('**/media-assets/*/edit');
  console.log('UPLOADED',state().media.map(x=>({id:x.id, name:x.original_name})));
  }
  const media = state().media;
  await page.goto('http://127.0.0.1:8097/admin/projects/create');
  const slug = 'qa-release-'+Date.now();
  const choose = async (selector,text) => {
    await page.locator(selector).click();
    if (text.endsWith('.webp')) {
      await page.locator('input[aria-label="Search"]:visible').fill(text);
    }
    await page.getByRole('option').filter({hasText:text}).first().click();
  };
  await page.locator('[id="form.slug"]').fill(slug);
  await choose('[id="form.category_id"]', 'Editorial');
  await choose('[id="form.cover_media_id"]',media[0].original_name);
  await page.locator('[id="form.title_de"]').fill('QA Freigabe Deutsch');
  await page.locator('[id="form.description_de"]').fill('Gespeicherter deutscher Entwurf für die lokale Prüfung.');
  await page.getByRole('tab',{name:'Englische Inhalte'}).click();
  await page.locator('[id="form.title_en"]').fill('QA Release English');
  await page.locator('[id="form.description_en"]').fill('Saved English draft for the isolated release test.');
  for (const [index,item] of media.entries()) {
    await page.getByRole('button',{name:'Galeriebild hinzufügen',exact:true}).click();
    const fields = page.locator('button[role=combobox][id$=".media_asset_id"]');
    await fields.nth(index).waitFor();
    await fields.nth(index).click();
    await page.locator('input[aria-label="Search"]:visible').fill(item.original_name);
    await page.getByRole('option').filter({hasText:item.original_name}).first().click();
  }
  await page.getByRole('button',{name:'Runter verschieben',exact:true}).first().click();
  await page.locator('button[role=combobox][id$=".media_asset_id"]').first().filter({hasText:media[1].original_name}).waitFor();
  await page.screenshot({path:out+'/create-with-photos.png',fullPage:true});
  await page.getByRole('button',{name:'Erstellen',exact:true}).click();
  await page.waitForURL('**/projects/*/edit');
  const editorUrl = page.url();
  const project = state().projects.find(p=>p.slug===slug);
  assert.equal(project.status,'draft'); assert.equal(project.media_items.length,2);
  assert.equal(project.media_items[0].media_asset_id,media[1].id);
  await page.getByRole('tab',{name:'Deutsche Inhalte'}).click();
  await page.locator('[id="form.title_de"]').fill('NICHT GESPEICHERT – Vorschau');
  const popupPromise = context.waitForEvent('page');
  await page.getByRole('button',{name:'Vorschau',exact:true}).click();
  const previewTab = await popupPromise;
  await previewTab.waitForURL(/\/admin\/preview\//);
  assert.equal(page.url(),editorUrl);
  assert.equal(await page.locator('[id="form.title_de"]').inputValue(),'NICHT GESPEICHERT – Vorschau');
  const preview = state().previews.at(-1);
  assert.equal(state().projects.find(p=>p.id===project.id).title_de,'QA Freigabe Deutsch');
  assert.equal(preview.target_path,'portfolio/'+slug);
  await previewTab.screenshot({path:out+'/preview-waiting.png',fullPage:true});
  console.log('PREVIEW WAITING, building real Astro');
  const result = JSON.parse(docker('php','/workspace/scripts/release/qa-console.php','build-preview',preview.id));
  const accepted = await context.request.post('http://127.0.0.1:8097/api/preview-runner/v1/previews/'+preview.id+'/status',{
    headers:{Authorization:'Bearer qa-only-preview'},data:{status:'ready',result_archive:result.archive,result_sha256:result.sha256}
  });
  assert.equal(accepted.status(),200,await accepted.text());
  await previewTab.getByRole('heading',{name:'NICHT GESPEICHERT – Vorschau',exact:true}).waitFor({timeout:30000});
  await previewTab.screenshot({path:out+'/preview-de.png',fullPage:true});
  const base = '/admin/preview/'+preview.token;
  const switchLanguage=async(p,lang,route)=>{
    await p.locator('header a:visible').filter({hasText:new RegExp('^\\s*'+lang+'\\s*$')}).first().click();
    await p.waitForURL(new RegExp(route+'/?$'));
    assert.equal(await p.locator('html').getAttribute('lang'),lang.toLowerCase());
  };
  await switchLanguage(previewTab,'EN',base+'/en/portfolio/'+slug);
  await previewTab.locator('header a:visible').filter({hasText:/^\s*Services\s*$/}).waitFor();
  await switchLanguage(previewTab,'DE',base+'/portfolio/'+slug);
  await previewTab.goto('http://127.0.0.1:8097'+base+'/portfolio/renaissance');
  await switchLanguage(previewTab,'EN',base+'/en/portfolio/renaissance');
  await previewTab.locator('#cookie-reject-all').click();
  await previewTab.locator('[data-chat-toggle]').click();
  await previewTab.locator('[data-chat-input]').fill('Renaissance');
  await previewTab.locator('[data-chat-input]').press('Enter');
  await previewTab.locator('[data-chat-body] a[href="'+base+'/en/portfolio/renaissance"]').waitFor();
  await previewTab.locator('[data-chat-input]').fill('Contact');
  await previewTab.locator('[data-chat-input]').press('Enter');
  await previewTab.locator('[data-chat-body] a[href="'+base+'/en/contact"]').waitFor();
  await previewTab.locator('[data-chat-close]').click();
  await previewTab.screenshot({path:out+'/preview-en.png',fullPage:true});
  await switchLanguage(previewTab,'DE',base+'/portfolio/renaissance');
  const scoped=await previewTab.locator('a[href^="/"]').evaluateAll(es=>es.map(x=>x.getAttribute('href')));
  assert(scoped.every(h=>h.startsWith(base+'/') && h.split(base).length===2));
  await previewTab.close();
  assert.equal(page.url(),editorUrl);
  assert.equal(await page.locator('[id="form.title_de"]').inputValue(),'NICHT GESPEICHERT – Vorschau');
  await page.locator('[id="form.title_de"]').fill('QA Final Deutsch');
  const saveReply=page.waitForResponse(r=>r.request().method()==='POST');
  await page.getByRole('button',{name:'Speichern',exact:true}).click();await saveReply;
  assert.equal(state().projects.find(p=>p.id===project.id).title_de,'QA Final Deutsch');
  console.log('PASS browser: upload, reorder, save, unsaved snapshot, two tabs, DE/EN, chat links, return and explicit save');
  const confirm=async(p,label)=>{
    await p.getByRole('button',{name:label,exact:true}).click();
    await p.getByRole('button',{name:'Bestätigen',exact:true}).click();
    await p.getByRole('button',{name:'Bestätigen',exact:true}).waitFor({state:'hidden'});
  };
  const runProduction=async(job,runner)=>{
    const endpoint='http://127.0.0.1:8097/api/publisher/v1/publications/'+job.id;
    const post=async(path,data)=>{const r=await context.request.post(endpoint+path,{headers:{Authorization:'Bearer qa-only-api'},data});assert.equal(r.status(),200,await r.text());return r.json();};
    assert.equal((await post('/claim',{runner_id:runner})).claimed,true);
    const downloaded=await context.request.get(endpoint+'/package',{headers:{Authorization:'Bearer qa-only-api'}});assert.equal(downloaded.status(),200);
    const result=JSON.parse(docker('php','/workspace/scripts/release/qa-console.php','build-production',job.id));
    await post('/status',{status:'uploading',runner_id:runner});
    const output=docker('php','/workspace/scripts/release/qa-console.php','artisan','madlen:production:activate',job.id,String(job.sequence),result.archive,result.sha256,'--runner='+runner);
    assert.match(output,/atomar aktiviert/);
    assert.equal((await post('/status',{status:'active',runner_id:runner,target_release:job.sequence+'-'+job.id})).status,'active');
    return job.sequence+'-'+job.id;
  };
  const releases=await context.newPage();await releases.goto('http://127.0.0.1:8097/admin/releases');
  await confirm(releases,'Website produktiv veröffentlichen');
  const baseline=state().publications.at(-1);await runProduction(baseline,'100-1');
  const publicPage=await context.newPage();await publicPage.goto('http://127.0.0.1:8098/portfolio');
  assert.equal(await publicPage.locator('a[href*="'+slug+'"]').count(),0);
  await confirm(page,'Veröffentlichen');
  const job=state().publications.at(-1);
  const count=state().publications.length;
  await confirm(page,'Veröffentlichen');assert.equal(state().publications.length,count);
  const active=await runProduction(job,'101-1');
  await publicPage.goto('http://127.0.0.1:8098/portfolio/'+slug);
  await publicPage.getByRole('heading',{name:'QA Final Deutsch',exact:true}).waitFor();
  await switchLanguage(publicPage,'EN','/en/portfolio/'+slug);
  await publicPage.getByRole('heading',{name:'QA Release English',exact:true}).waitFor();
  await publicPage.screenshot({path:out+'/production-en.png',fullPage:true});
  writeFileSync(out+'/browser-production.json',JSON.stringify({slug,projectId:project.id,active,baseline:baseline.id},null,2));
  console.log('PASS browser: real production package + Astro build + authenticated result + current + DE/EN');
  writeFileSync(out+'/browser-progress.json',JSON.stringify({slug,project:project.id,preview:preview.id,editorUrl},null,2));
} catch(e) {
  await page.screenshot({path:out+'/browser-error.png',fullPage:true});
  console.log(await page.locator('body').innerText());
  for(const p of context.pages()) if(p!==page) {console.log('OTHER PAGE',p.url(),(await p.locator('body').innerText()).slice(-5000));await p.screenshot({path:out+'/other-error.png',fullPage:true});}
  throw e;
} finally { writeFileSync(out+'/browser-errors.json', JSON.stringify(errors,null,2)); await browser.close(); }
