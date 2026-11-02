import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
const base=process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable browser runtime required');
const admin=JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS,'utf8')).find(x=>x.role==='Admin');
const browser=await chromium.launch({headless:true,executablePath:process.env.QA_CHROMIUM,args:['--no-sandbox']});
let total=0,failures=0;
const check=(ok,label)=>{total++;if(!ok)failures++;console.log(`${ok?'PASS':'FAIL'} ${label}`)};
try {
 for(const width of [1440,390]) {
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
  await page.goto(`${base}/quotations/create`,{waitUntil:'networkidle'});
  check(await page.locator('html').getAttribute('dir')==='rtl',`${width} Arabic RTL`);
  check(await page.locator('input[name=customer_id]').count()===1 && await page.locator('select[name=customer_id]').count()===0,`${width} single customer search selector`);
  const store=page.locator('select[name=store_id]');await store.selectOption({index:1});
  const search=page.locator('[data-quotation-customer-search]');
  for(const [term,label] of [['QA POS','English name'],['010','phone'],['عميل','Arabic name']]) {
   await search.fill(term);
   await page.locator('[data-quotation-customer-results] button').first().waitFor({state:'visible',timeout:10000});
   check(await page.locator('[data-quotation-customer-results] button').count()>0,`${width} ${label} partial results`);
  }
  await page.locator('[data-quotation-customer-results] button').first().click();
  check((await page.locator('input[name=customer_id]').inputValue())!=='',`${width} customer selected`);
  await page.locator('[data-quotation-customer-clear]').click();
  check((await page.locator('input[name=customer_id]').inputValue())==='',`${width} customer cleared`);
  await page.locator('summary').filter({hasText:'إضافة عميل جديد'}).click();
  await page.locator('[data-new-first]').fill('عميل');
  await page.locator('[data-new-last]').fill('تجربة');
  await page.locator('[data-new-phone]').fill(width===1440?'01099997766':'01099997767');
  await page.locator('[data-new-governorate]').selectOption({index:1});
  await page.locator('[data-create-quotation-customer]').click();
  await page.waitForFunction(()=>document.querySelector('input[name=customer_id]')?.value !== '',undefined,{timeout:10000});
  check((await page.locator('input[name=customer_id]').inputValue())!=='',`${width} new customer auto-selected`);
  if(width===1440) {
   await page.locator('[name="lines[0][line_type]"]').selectOption('manual');
   await page.locator('[name="lines[0][source_reference]"]').fill('QA-MANUAL-UX');
   await page.locator('[name="lines[0][description_ar]"]').fill('صنف اختبار');
   await page.locator('[name="lines[0][description_en]"]').fill('QA Manual');
   await page.locator('[name="lines[0][unit_price]"]').fill('50');
   await page.locator('button[type=submit]').filter({hasText:'حفظ عرض السعر'}).click();
   await page.waitForURL(u=>u.pathname==='/quotations');
  }
  await page.goto(`${base}/quotations`,{waitUntil:'networkidle'});
  check(await page.locator('table.data-table').count()===1,`${width} quotation list`);
  check(await page.locator('table.data-table tbody tr').first().locator('td:last-child').evaluate(el=>el.getBoundingClientRect().width<190),`${width} compact actions column`);
  const actions=page.locator('table.data-table tbody tr').first();
  check(await actions.locator('a[aria-label="عرض"]').count()>0,`${width} accessible view icon`);
  await actions.locator('[data-quotation-actions] summary').click();
  check(await actions.locator('[data-quotation-actions][open]').count()===1,`${width} overflow menu opens`);
  await page.goto(`${base}/pos`,{waitUntil:'networkidle'});
  check(await page.locator('[data-gift-card-scan] input[placeholder="اكتب أو امسح كود بطاقة الهدايا"]').count()===1,`${width} Gift Card search field`);
  check(await page.locator('[data-gift-card-scan] svg').count()>0,`${width} Gift Card search icon`);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),`${width} no overflow`);
  check(errors.length===0,`${width} console/HTTP clean: ${errors.slice(0,2).join('; ')}`);
  await context.close();
 }
} finally {await browser.close()}
console.log(`${total-failures}/${total} bounded browser checks`);if(failures)process.exitCode=1;
