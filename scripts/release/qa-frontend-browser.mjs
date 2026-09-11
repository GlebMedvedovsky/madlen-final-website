import {writeFileSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.MADLEN_QA_PLAYWRIGHT).href);
const out=resolve('installation-artifacts/release-qa');mkdirSync(out,{recursive:true});
const browser=await chromium.launch({headless:true,executablePath:process.env.MADLEN_QA_CHROMIUM});
const context=await browser.newContext();
await context.route('**/*',r=>['127.0.0.1','localhost'].includes(new URL(r.request().url()).hostname)?r.continue():r.abort());
const p=await context.newPage(),errors=[],missing=[],checks=[];
p.on('pageerror',e=>errors.push(e.message));p.on('response',r=>{if(r.status()===404)missing.push(r.url());});
try{
  for(const width of [320,390,768,1440])for(const lang of ['de','en']){
    await p.setViewportSize({width,height:900});
    const routes=lang==='de'?['/','/portfolio','/portfolio/renaissance','/leistungen','/kontakt']:['/en','/en/portfolio','/en/portfolio/renaissance','/en/services','/en/contact'];
    for(const route of routes){
      console.log('Frontend',width,lang,route);
      assert.equal((await p.goto('http://127.0.0.1:8098'+route)).status(),200);
      assert.equal(await p.locator('html').getAttribute('lang'),lang);
      if(await p.locator('#cookie-banner[aria-hidden=false]').count())await p.locator('#cookie-reject-all').click();
      // Exercise lazy loading and reveal intersections down the entire page.
      await p.evaluate(async()=>{const height=document.documentElement.scrollHeight;for(let y=0;y<height;y+=650){scrollTo({top:y,behavior:'instant'});await new Promise(r=>setTimeout(r,120));}scrollTo({top:0,behavior:'instant'});});
      await p.evaluate(()=>Promise.race([Promise.all([...document.images].filter(i=>i.getBoundingClientRect().width>0).map(i=>i.decode().catch(()=>{}))),new Promise(r=>setTimeout(r,12000))]));
      // Intrinsic lazy-image heights can extend the page after the first sweep.
      for(const img of await p.locator('img').all())if(await img.isVisible()){
        if(!await img.evaluate(i=>i.complete&&i.naturalWidth>0)){
          await img.scrollIntoViewIfNeeded();
          await img.evaluate(i=>Promise.race([i.decode(),new Promise((_,reject)=>setTimeout(()=>reject(Error('Image did not load: '+i.src)),12000))]));
        }
      }
      await p.evaluate(()=>scrollTo({top:0,behavior:'instant'}));
      const measurement=await p.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,broken:[...document.images].filter(i=>i.getBoundingClientRect().width>0&&(!i.complete||!i.naturalWidth)).map(i=>i.currentSrc)}));
      assert(measurement.scroll<=width+1,route+' horizontal overflow '+JSON.stringify(measurement));assert.deepEqual(measurement.broken,[]);
      const urls=await p.locator('a[href^="/"]').evaluateAll(es=>es.map(e=>e.getAttribute('href')));assert(urls.every(u=>!u.includes('/admin/preview/')));
      checks.push({width,lang,route,images:'loaded',overflow:false});
      if(route==='/'||route==='/en'){
        if(width===390||width===1440)await p.screenshot({path:out+'/home-'+lang+'-'+width+'.png',fullPage:true});
        if(width===390){await p.locator('#menu-toggle').click();assert.equal(await p.locator('#mobile-menu').getAttribute('aria-hidden'),'false');await p.locator('#menu-toggle').click();}
        await p.locator('#cookie-floating-button').click();await p.locator('#cookie-modal[aria-hidden=false]').waitFor();
        assert.match(await p.locator('#cookie-modal-title').innerText(),lang==='en'?/Cookie settings/:/Cookie-Einstellungen/);await p.locator('#cookie-close').click();
        await p.locator('[data-chat-toggle]').click();await p.locator('[data-chat-input]').fill('Renaissance');await p.locator('[data-chat-input]').press('Enter');
        await p.locator('[data-chat-body] a[href="'+(lang==='en'?'/en':'')+'/portfolio/renaissance"]').waitFor();await p.locator('[data-chat-close]').click();
      }
    }
  }
  const contacts=[];await p.setViewportSize({width:390,height:900});
  for(const lang of ['de','en']){
    await p.goto('http://127.0.0.1:8098'+(lang==='de'?'/kontakt':'/en/contact'));
    await p.locator('#contact-name').fill('Release QA');await p.locator('#contact-email').fill('qa-'+lang+'@example.test');
    await p.locator('#contact-request').selectOption({index:1});await p.locator('#contact-date').fill('Nach Vereinbarung');await p.locator('#contact-location').fill('Stuttgart');
    await p.locator('#contact-message').fill('Synthetic local acceptance inquiry. No SMTP delivery is performed.');await p.locator('[name=privacy]').check();
    await p.waitForTimeout(3200);
    const response=p.waitForResponse(r=>r.url().endsWith('/api/contact')&&r.request().method()==='POST');await p.locator('[data-contact-form] button[type=submit]').click();const r=await response;
    assert.equal(r.status(),200,await r.text());await p.locator('[data-contact-feedback][data-state=success]').waitFor();
    contacts.push({lang,status:r.status(),message:await p.locator('[data-contact-status]').innerText()});
    await p.screenshot({path:out+'/contact-'+lang+'-success.png',fullPage:true});
  }
  assert.deepEqual(errors,[]);assert.deepEqual(missing,[]);
  writeFileSync(out+'/frontend-browser.json',JSON.stringify({result:'PASS',browser:'Chromium',checks,contacts,consoleErrors:errors,missing},null,2));
  console.log('PASS Chromium: 40 DE/EN pages/widths, images, mobile menu, chat/cookies, real local contact POST (array mailer)');
}catch(e){await p.screenshot({path:out+'/frontend-error.png',fullPage:true});writeFileSync(out+'/frontend-progress.json',JSON.stringify({checks,errors,missing},null,2));throw e;}finally{await browser.close();}
