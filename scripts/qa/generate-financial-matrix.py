#!/usr/bin/env python3
"""Independent rational financial oracle; fixed seed, no application execution."""
from fractions import Fraction as F
from pathlib import Path
import json, random
ROOT=Path(__file__).resolve().parents[2]
rng=random.Random(20261004)
def units(x, scale, rounded=False):
    x=F(str(x))*10**scale
    negative=x<0;x=abs(x)
    n=x.numerator//x.denominator
    if rounded and 2*(x.numerator%x.denominator)>=x.denominator:n+=1
    return -n if negative else n
def text(n,scale):
    sign='-' if n<0 else '';n=abs(n)
    return sign+(str(n) if scale==0 else str(n//10**scale)+'.'+str(n%10**scale).zfill(scale))
def money(x):return text(units(x,2,True),2)
def decimal(x,scale=4):return text(units(x,scale),scale)
def pos(lines,discount,tax):
    gross=[units(F(l['quantity'])*F(l['unit_price']),2,True) for l in lines]
    ds=[units(l.get('discount_amount','0'),2,True) for l in lines]
    weights=[g-d for g,d in zip(gross,ds)];whole=units(discount,2,True)
    allocations=[0]*len(lines)
    if whole:
        shares=[F(whole*w,sum(weights)) for w in weights]
        allocations=[int(s) for s in shares]
        order=sorted(range(len(lines)),key=lambda i:(-(shares[i]-allocations[i]),i))
        for i in order[:whole-sum(allocations)]:allocations[i]+=1
    base=sum(weights)-whole;rate=F(tax.get('rate','0'))
    t=F(base)*rate/(100+rate) if tax.get('inclusive',False) else F(base)*rate/100
    tax_cents=units(t/100,2,True) if tax['applicable'] else 0
    resultlines=[dict(gross_amount=text(g,2),discount_amount=text(d,2),allocated_invoice_discount=text(a,2),net_amount=text(g-d-a,2)) for g,d,a in zip(gross,ds,allocations)]
    return dict(lines=resultlines,subtotal=text(sum(gross),2),line_discount_total=text(sum(ds),2),invoice_discount=text(whole,2),discount_total=text(sum(ds)+whole,2),taxable_base=text(base,2),tax_total=text(tax_cents,2),total=text(base if tax['applicable'] and tax.get('inclusive',False) else base+tax_cents,2))
def purchase(line):
    q=int(line['quantity']);cost=F(decimal(line['unit_cost']));kind=line.get('discount_type') or None
    dv=F(decimal(line.get('discount_value','0'))) if kind else F(0);rate=F(decimal(line.get('tax_rate','0')))
    gross=q*cost;discount=dv if kind=='amount' else gross*dv/100 if kind=='percentage' else F(0)
    discount=F(decimal(discount,8));net=F(decimal(gross-discount,8));inclusive=bool(line.get('tax_inclusive',False))
    numerator=F(decimal(net*rate,8));tax=F(decimal(numerator/(100+rate if inclusive else 100),8))
    subtotal=net-tax if inclusive else net;total=net if inclusive else net+tax
    return dict(quantity=text(q*10**6,6),unit_cost=decimal(cost),discount_type=kind,discount_value=decimal(dv),discount_amount=decimal(discount),tax_rate=decimal(rate),tax_code=line.get('tax_code') or None,tax_inclusive=inclusive,tax_amount=decimal(tax),subtotal=decimal(subtotal),line_total=decimal(total))
rows={'seed':20261004,'oracle':'Python Fraction rational values; half-away money, four-place purchasing truncation; integer product quantities','pos':[],'purchase':[],'rounding':[]}
for n in range(160):
    lines=[]
    for i in range(1+n%4):
        q=1+rng.randrange(19);price=text(1+rng.randrange(1000000),4);g=units(F(q)*F(price),2,True)
        lines.append(dict(quantity=str(q),unit_price=price,discount_amount=text(rng.randrange(g+1),2)))
    weights=sum(units(F(l['quantity'])*F(l['unit_price']),2,True)-units(l['discount_amount'],2) for l in lines)
    discount=text(rng.randrange(weights+1),2)
    for rate in ['0','14','15','100','0.0001','99.9999']:
        for inclusive in [False,True]:
            tax=dict(applicable=True,rate=rate,inclusive=inclusive)
            rows['pos'].append(dict(lines=lines,discount=discount,tax=tax,expected=pos(lines,discount,tax)))
for cost in ['0','0.0001','0.0050','1.9999','57','99.9999','9999999999999.1234','999999999999999.9999']:
    for quantity in ['1','3']:
        if len(cost.split('.')[0])==15 and quantity!='1':continue
        for kind in [None,'amount','percentage']:
            for rate in ['0','14','15','100','0.0001','99.9999']:
                for inclusive in [False,True]:
                    if len(cost.split('.')[0])==15 and not inclusive and rate!='0':continue
                    value='0' if kind is None else ('0.0001' if kind=='amount' and cost!='0' else '0') if kind=='amount' else '12.3456'
                    line=dict(quantity=quantity,unit_cost=cost,discount_type=kind,discount_value=value,tax_rate=rate,tax_inclusive=inclusive,tax_code='QA')
                    rows['purchase'].append(dict(line=line,expected=purchase(line)))
for scale in [0,1,2,4,6]:
    for _ in range(80):
        value=text(rng.randrange(-100000000000,100000000000),7)
        rows['rounding'].append(dict(value=value,scale=scale,expected=text(units(value,scale,True),scale)))

added={'pos':[], 'purchase':[]}
for weights in [['1000000.00','1000000.01','1000000.02'],['9999999999.0150'],['999999999.99','999999999.98','999999999.97']]:
    lines=[dict(quantity='99' if len(weights)==1 else '1',unit_price=w) for w in weights]
    for discount in ['0','0.01','1.99']:
        tax=dict(applicable=False);added['pos'].append(dict(lines=lines,discount=discount,tax=tax,expected=pos(lines,discount,tax)))
for _ in range(800):
    cost=text(rng.randrange(1,100000000),5);rate=text(rng.randrange(0,10000001),5)
    kind='percentage' if rng.randrange(2) else 'amount'
    value=text(rng.randrange(0,10000001),5) if kind=='percentage' else decimal(F(cost)/3,5)
    line=dict(quantity=str(1+rng.randrange(13)),unit_cost=cost,discount_type=kind,discount_value=value,tax_rate=rate,tax_inclusive=bool(rng.randrange(2)),tax_code='QA')
    added['purchase'].append(dict(line=line,expected=purchase(line)))
for quantity,cost in [('99999999999999','0.0001'),('9223372036854775807','0.0001')]:
    line=dict(quantity=quantity,unit_cost=cost);added['purchase'].append(dict(line=line,expected=purchase(line)))

(ROOT/'tests/Feature/QA/fixtures/financial-boundaries.json').write_text(json.dumps(added,separators=(',',':'))+'\n')
path=ROOT/'tests/Feature/QA/fixtures/financial-matrix.json';path.write_text(json.dumps(rows,separators=(',',':'))+'\n')
print('Deterministic fixture:',len(rows['pos']),'POS',len(rows['purchase']),'purchase',len(rows['rounding']),'rounding; seed',rows['seed'])

# Near schema-valid monetary limits: binary float cannot preserve cents.
precision={'pos':[], 'cash':[]}
for prices in [['99999999999999.99','99999999999999.98','99999999999999.97'],['99999999999999.99','0.01','0.02'],['9999999999.99','9999999999.98','9999999999.97']]:
    for quantity in ['1','9999'] if len(prices[0].split('.')[0])==10 else ['1']:
        for discount in ['0.01','1.99','9999.99']+(['19999999999999.99'] if len(prices[0].split('.')[0])==14 else []):
            lines=[dict(quantity=quantity,unit_price=p,discount_amount='1.01' if i==0 else '0') for i,p in enumerate(prices)]
            for rate in ['0','14','15','100','0.0001','99.9999']:
                for inclusive in [False,True]:
                    tax=dict(applicable=True,rate=rate,inclusive=inclusive)
                    precision['pos'].append(dict(lines=lines,discount=discount,tax=tax,expected=pos(lines,discount,tax)))
for amount in ['99999999999999.99','99999999999999.49','99999999999999.50','999989999901000.99','0','0.49','0.50','0.51','4.99','5.00','5.01','999999999999990.06']:
    for denomination in ['5.00','0.05','0.01','0.02','0.04']:
        x=F(amount);d=F(denomination);low=(x//d)*d;up=low+d
        adjustment=0 if low==x else up-x if up-x<=x-low else low-x
        precision['cash'].append(dict(amount=amount,denomination=denomination,expected=money(adjustment)))
(ROOT/'tests/Feature/QA/fixtures/financial-precision.json').write_text(json.dumps(precision,separators=(',',':'))+'\n')
print('Schema-valid precision matrix:',len(precision['pos']),'POS,',len(precision['cash']),'cash')
