import {writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.MADLEN_QA_PLAYWRIGHT).href);
const browser=await chromium.launch({headless:true,executablePath:process.env.MADLEN_QA_CHROMIUM});
const context=await browser.newContext({viewport:{width:390,height:900}});
await context.route('**/*',r=>['127.0.0.1','localhost'].includes(new URL(r.request().url()).hostname)?r.continue():r.abort());
const p=await context.newPage();
try{
  for(const lang of ['de','en']){
    const prefix=lang==='en'?'/en':'';
    await p.goto('http://127.0.0.1:8098'+prefix+'/portfolio');
    if(await p.locator('#cookie-banner[aria-hidden=false]').count())await p.locator('#cookie-reject-all').click();
    await p.locator('[data-project-filter=weddings]').click();
    assert.equal(await p.locator('[data-project-filter=weddings]').getAttribute('aria-pressed'),'true');
    const visible=await p.locator('[data-project-category]:not([hidden])').evaluateAll(es=>es.map(e=>e.dataset.projectCategory));
    assert(visible.length>0&&visible.every(x=>x==='weddings'));
    await p.locator('[data-project-filter=all]').click();assert.equal(await p.locator('[data-project-category][hidden]').count(),0);
    await p.goto('http://127.0.0.1:8098'+prefix+'/portfolio/renaissance');
    await p.screenshot({path:'installation-artifacts/release-qa/project-'+lang+'-390.png'});
    await p.locator('#menu-toggle').focus();await p.keyboard.press('Enter');
    await p.locator('#mobile-menu a').filter({hasText:/^\s*(EN|DE)\s*$/}).filter({hasText:lang==='de'?'EN':'DE'}).first().click();
    await p.waitForURL('**'+(lang==='de'?'/en':'')+'/portfolio/renaissance');
    for(const route of ['/ueber-mich','/en/about','/impressum','/en/legal-notice','/datenschutz','/en/privacy','/agb','/en/terms']){
      const r=await context.request.get('http://127.0.0.1:8098'+route);assert.equal(r.status(),200,route);
    }
  }
  writeFileSync('installation-artifacts/release-qa/navigation-browser.json',JSON.stringify({result:'PASS',filters:'DE/EN wedding filter and all reset',keyboardMenu:true,mobileLanguage:'Renaissance DE/EN stays project',otherPagesHTTP:8},null,2));
  console.log('PASS real browser filters, keyboard menu, mobile DE/EN same project; eight other local routes HTTP 200');
}finally{await browser.close();}
