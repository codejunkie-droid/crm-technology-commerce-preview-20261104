"""Run against an isolated demo server: python3 tests/http.py http://127.0.0.1:8080.
Creates synthetic records only; never use against a live store.
"""
import json, sys, urllib.request, urllib.error, http.cookiejar, uuid
base=(sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8080').rstrip('/')
if not base.startswith(('http://127.0.0.1:','http://localhost:')):
 raise SystemExit('Tests are restricted to a loopback development server.')
class Client:
 def __init__(self):
  self.jar=http.cookiejar.CookieJar();self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar));self.csrf=''
 def request(self,path,data=None,csrf=True):
  headers={'Accept':'application/json'}
  if data is not None:headers['Content-Type']='application/json'
  if data is not None and csrf:headers['X-CSRF-Token']=self.csrf
  req=urllib.request.Request(base+path,data=json.dumps(data).encode() if data is not None else None,headers=headers)
  try:r=self.http.open(req)
  except urllib.error.HTTPError as e:r=e
  raw=r.read()
  try:body=json.loads(raw)
  except (ValueError,UnicodeDecodeError):body=raw
  return r.status,body
 def session(self):
  status,data=self.request('/api/session');assert status==200 and data['demo'], 'Demo server required'
  self.csrf=data['csrf'];return data

checks=[]
def check(ok,label):
 if not ok:raise AssertionError(label)
 checks.append(label);print('PASS',label)
a=Client();a.session();b=Client();b.session()
code,products=a.request('/api/products?category=laptops')
check(code==200 and products['products'] and all(p['category_slug']=='laptops' for p in products['products']),'Category results are correct')
p=products['products'][0];vid=p['variant_id']
code,_=a.request('/api/cart/add',{'variant_id':vid,'quantity':1},csrf=False);check(code==419,'HTTP mutations reject missing CSRF')
code,cart=a.request('/api/cart/items',{'variant_id':vid,'quantity':2,'price_cents':1});check(code==200 and cart['total_cents']==2*p['price_cents'],'Cart ignores forged browser price')
code,_=a.request('/api/cart/items',{'variant_id':vid,'quantity':99999});check(code==400,'Invalid cart quantity rejected')
check(b.request('/api/cart')[1]['count']==0,'Guest carts are isolated')
check(a.request('/api/admin')[0]==401,'Unauthenticated admin access denied')
code,_=a.request('/api/admin/settings',{'key':'announcement','value':'tamper'});check(code==401,'Unauthenticated administrative mutation denied')
key='http-test-'+uuid.uuid4().hex
customer={'idempotency_key':key,'name':'HTTP Test Customer','email':'http-test@example.invalid','phone':'0000000000','address':'Synthetic test address','city':'Test City','postal_code':'0000','payment_method':'eft','delivery':'quote','total_cents':1,'role':'super_admin'}
code,created=a.request('/api/checkout',customer);check(code==200 and 'order' in created,'EFT test checkout persists an order')
order=created['order'];check(int(order['total_cents'])==2*p['price_cents'] and order['status']=='pending_payment','Order uses server totals and does not mark unpaid order paid')
code,repeated=a.request('/api/checkout',customer);check(code==200 and repeated['order']['id']==order['id'],'Repeated checkout returns same order')
check(b.request('/api/orders/'+str(order['id']))[0]==404,'Another guest cannot access an order')
code,account=a.request('/api/auth/register',{'name':'HTTP Test User','email':'qa-'+uuid.uuid4().hex+'@example.invalid','password':uuid.uuid4().hex,'role':'super_admin'})
check(code==200 and account['user']['role']=='customer','Registration ignores privilege escalation')
a.csrf=account['csrf'];check(a.request('/api/admin')[0]==403,'Customer cannot access administration')
code,ticket=a.request('/api/support',{'subject':'HTTP test enquiry','category':'Product support','message':'Synthetic test ticket.'});check(code==200 and 'id' in ticket,'Support ticket persists')
check(b.request('/api/support/'+str(ticket['id']))[0] in [401,404],'Support ticket privacy enforced')
code,_=a.request('/api/support/'+str(ticket['id'])+'/reply',{'message':'Second test message.'});check(code==200,'Ticket owner can reply')
code,builds=a.request('/api/build/options');check(code==200 and all(builds['groups'].get(k) for k in ['cpu','motherboard','gpu','ram','storage','psu','case','cooling']),'All builder groups have fixtures')
components={k:group[0]['variant_id'] for k,group in builds['groups'].items()}
code,build=a.request('/api/build/validate',{'components':components});check(code==200 and build['status']=='needs_review' and build['missing'],'Builder discloses incomplete compatibility evidence')
code,saved=a.request('/api/build/save',{'components':components,'name':'HTTP Test Build','purpose':'custom','budget_cents':2500000});check(code==200 and 'share_token' in saved,'Build saves for signed-in customer')
code,shared=b.request('/api/build/'+saved['share_token']);check(code==200 and 'user_id' not in shared and 'email' not in shared,'Shared build excludes customer identity')
code,quote=a.request('/api/quotes',{'source':'build','components':components});check(code==200 and quote['pdf_url'],'Build quotation persists')
code,pdf=a.request(quote['pdf_url']);check(code==200 and pdf.startswith(b'%PDF-'),'Owner receives a valid PDF file')
check(b.request(quote['pdf_url'])[0]==404,'Another guest cannot download the quote')
code,_=a.request('/api/dealers/apply',{'business_name':'QA Fixture Business','registration_number':'TEST-NOT-REAL','contact_name':'HTTP Test User','phone':'0000000000','address':'Test address'});check(code==200 and a.session()['user']['role']=='pending_dealer','Dealer application grants pending status only')
code,_=a.request('/api/admin/dealers',{'id':1,'status':'approved'});check(code==403,'Pending dealer cannot approve own application')
check(a.request('/api/products?q=%27%20OR%201%3D1--')[0]==200,'Search injection is treated as input')
code,_=a.request('/api/cart/add',{'variant_id':vid,'quantity':1});check(code==200,'Cart remains usable after account changes')
code,_=a.request('/api/checkout',{**customer,'idempotency_key':uuid.uuid4().hex,'payment_method':'payfast'});check(code==503,'Unconfigured live card payment fails closed')
check(a.request('/api/payments/payfast/itn',{})[0]==503,'Unconfigured webhook fails closed')
print('\n%d HTTP checks passed. Local demo records only.'%len(checks))
