import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
const base = process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable browser runtime required');
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find(x => x.role === 'Admin');
const browser = await chromium.launch({headless:true, executablePath:process.env.QA_CHROMIUM,args:['--no-sandbox']});
let checks=0, failures=0;
const check=(ok,label)=>{checks++;if(!ok)failures++;console.log(`${ok?'PASS':'FAIL'} ${label}`)};
try {
  for (const width of [1440,390]) {
    const context=await browser.newContext({viewport:{width,height:900}});
    const page=await context.newPage();const errors=[];
    page.on('pageerror',e=>errors.push(e.message));
    page.on('console',m=>{if(m.type()==='error')errors.push(m.text())});
    page.on('response',r=>{if(r.status()>=500||r.status()===422)errors.push(`HTTP ${r.status()} ${r.url()}`)});
    await page.goto(`${base}/login`);
    await page.locator('input[name=username]').fill(admin.username);
    await page.locator('input[name=password]').fill(admin.password);
    await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login')),page.locator('[data-test=login-button]').click()]);
    await page.evaluate(async()=>{const token=document.querySelector('meta[name=csrf-token]').content;await fetch('/locale',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({_token:token,locale:'ar'})})});
    await page.goto(`${base}/quotations`,{waitUntil:'networkidle'});
    check(await page.locator('html').getAttribute('dir')==='rtl',`${width} Arabic RTL`);
    check(await page.locator('form[action$="/quotations"]').count()===0,`${width} quotation list first`);
    check(await page.locator('a[href$="/quotations/create"]').count()>0,`${width} add quotation action`);
    await page.goto(`${base}/quotations/create`,{waitUntil:'networkidle'});
    check(await page.locator('form[action$="/quotations"]').count()===1,`${width} separate create`);
    check((await page.locator('body').innerText()).includes('حفظ عرض السعر'),`${width} Arabic save wording`);
    await page.locator('[data-add-manual]').click();
    check(await page.locator('[name$="[line_type]"]').last().inputValue()==='manual',`${width} manual line selected`);
    const line=page.locator('[data-product-line]').last();
    check(await line.locator('[name$="[source_reference]"]').isVisible(),`${width} manual code visible`);
    await page.goto(`${base}/admin/audit`,{waitUntil:'networkidle'});
    check(await page.locator('ol[aria-label]').count()===1,`${width} timeline rail`);
    check(await page.getByText('تطبيق',{exact:true}).count()>0,`${width} filter apply`);
    await page.goto(`${base}/pos/shift`,{waitUntil:'networkidle'});
    check(!(await page.locator('body').innerText()).includes('View shift receipts'),`${width} shift Arabic labels`);
    check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),`${width} no horizontal overflow`);
    check(errors.length===0,`${width} no console or HTTP errors: ${errors.slice(0,2).join('; ')}`);
    await context.close();
  }
  const context=await browser.newContext({viewport:{width:1440,height:900}});const page=await context.newPage();
  await page.goto(`${base}/login`);await page.locator('input[name=username]').fill(admin.username);await page.locator('input[name=password]').fill(admin.password);await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login')),page.locator('[data-test=login-button]').click()]);
  await page.evaluate(async()=>{const token=document.querySelector('meta[name=csrf-token]').content;await fetch('/locale',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({_token:token,locale:'en'})})});
  await page.goto(`${base}/quotations/create`,{waitUntil:'networkidle'});check(await page.locator('html').getAttribute('dir')==='ltr','English LTR');check((await page.locator('body').innerText()).includes('Save Quotation'),'English quotation save');await context.close();
} finally {await browser.close()}
console.log(`${checks-failures}/${checks} bounded browser checks`);if(failures)process.exitCode=1;
