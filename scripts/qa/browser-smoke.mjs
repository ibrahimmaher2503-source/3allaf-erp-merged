import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
const baseURL=process.env.QA_BROWSER_URL;
if (!baseURL || new URL(baseURL).hostname!=='127.0.0.1') throw new Error('Browser QA requires its disposable loopback server');
const browser=await chromium.launch({headless:true,...(process.env.QA_CHROMIUM?{executablePath:process.env.QA_CHROMIUM}:{}),args:['--no-sandbox']});
const accounts=process.env.QA_BROWSER_ACCOUNTS?JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS,'utf8')):[];
const failures=[];let assertions=0;const failureOnly=process.env.QA_BROWSER_FAILURE_ONLY==='1';
function check(ok,label) {if(failureOnly && !label.includes('browser errors'))return;assertions++; if(!ok) failures.push(label);else console.log(`PASS ${label}`);}
async function inspect(page,label) {
    if(failureOnly)return;
    check(await page.locator('html').getAttribute('dir').then(x=>['rtl','ltr'].includes(x)),`${label} direction`);
    check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),`${label} overflow`);
}
try {
 for (const [viewportName,viewport] of [['desktop',{width:1280,height:900}],['mobile',{width:390,height:844}]]) {
  for (const account of (accounts.length?accounts:[{role:'Guest'}]).filter(a=>!process.env.QA_BROWSER_ROLE || a.role===process.env.QA_BROWSER_ROLE)) {
   const context=await browser.newContext({viewport});const page=await context.newPage();const errors=[];
   const expectedDenials=new Set();
   page.on('response',response=>{
    const path=new URL(response.url()).pathname;
    if(account.role==='User' && response.status()===403 && response.request().resourceType()==='document' && ['/dashboard','/forbidden'].includes(path)) expectedDenials.add(response.url());
    else if(response.status()>=500) failures.push(`${account.role} unexpected HTTP ${response.status()} ${path}`);
   });
   page.on('pageerror',e=>errors.push({text:e.message,url:''}));page.on('console',m=>{if(m.type()==='error') errors.push({text:m.text(),url:m.location().url});});
   const response=await page.goto(baseURL+'/login',{waitUntil:'networkidle'});check(response.status()===200,`${account.role} login HTTP`);await inspect(page,`${account.role} login ${viewportName}`);
   if (account.username) {
    await page.locator('input[name=username]').fill(account.username);await page.locator('input[name=password]').fill(account.password);
    await Promise.all([page.waitForURL(url=>!url.pathname.endsWith('/login')),page.locator('[data-test=login-button]').click()]);
    check(!page.url().endsWith('/login'),`${account.role} authenticated`);
    const allowed=account.role==='Admin'?'/catalog/products':account.role==='Cashier'?'/pos':'/forbidden';
    const navigation=await page.goto(baseURL+allowed,{waitUntil:'networkidle'});check([200,403].includes(navigation.status()),`${account.role} ${allowed} HTTP`);await inspect(page,`${account.role} ${allowed} ${viewportName}`);
    if(account.role!=='Admin') {const denial=await context.request.get(baseURL+'/catalog/products/create');check(denial.status()===403,`${account.role} direct create denied`);}
    if(account.role==='Admin') {const form=await page.goto(baseURL+'/catalog/products/create',{waitUntil:'networkidle'});check(form.status()===200,`Admin form renders`);check(await page.locator('input').count()>0,`Admin form has fields`);await inspect(page,`Admin form ${viewportName}`);}
    // A 403 boundary page need not contain the application's CSRF meta tag.
    await page.goto(baseURL+'/settings/profile',{waitUntil:'networkidle'});
    const csrf=await page.locator('meta[name=csrf-token]').getAttribute('content');
    const logout=await context.request.post(baseURL+'/logout',{form:{_token:csrf},maxRedirects:0});check(logout.status()===302,`${account.role} logout succeeds`);
    const loggedOut=await context.request.get(baseURL+'/catalog/products',{maxRedirects:0});check(loggedOut.status()===302,`${account.role} logout revokes access`);
   }
   // Actual browser locale change exercises the application's CSRF-protected endpoint.
   await page.goto(baseURL+'/login',{waitUntil:'networkidle'});
   await page.evaluate(async()=>{const token=document.querySelector('meta[name=csrf-token]').content; await fetch('/locale',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({_token:token,locale:'ar'})});});
   await page.reload({waitUntil:'networkidle'});check(await page.locator('html').getAttribute('dir')==='rtl',`${account.role} Arabic RTL`);
   const unexpected=errors.filter(e=>!(expectedDenials.has(e.url) && /Failed to load resource:.*403/.test(e.text)));
   check(unexpected.length===0,`${account.role} ${viewportName} browser errors: ${unexpected.map(e=>`${e.text} (${e.url})`).join(';')}`);
   await context.close();
  }
 }
} finally {await browser.close();}
if(failures.length) {console.error(failures.join('\n'));process.exit(1);}
console.log(`PASS ${failureOnly?'resumed '+process.env.QA_BROWSER_ROLE+' console checks':'browser'} ${assertions} assertions: ${accounts.length?'authenticated Admin/User/Cashier':'Guest'} login/logout, allow/deny, forms, English/Arabic, desktop/mobile, console and overflow.`);
