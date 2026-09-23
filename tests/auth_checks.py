import base64
import http.cookiejar
import json
import urllib.request
import urllib.parse
import urllib.error

class Client:
    def __init__(self,base,opener=None,csrf=''):
        self.base,self.csrf=base,csrf
        self.opener=opener or urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        if not csrf:self.call('session')
    def call(self,action,values=None,status=200,token=None,json_body=None,extra=None):
        headers={'X-CSRF-Token':self.csrf if token is None else token,**(extra or {})}
        data=urllib.parse.urlencode(values).encode() if values is not None else None
        if json_body is not None:data=json.dumps(json_body).encode();headers['Content-Type']='application/json'
        request=urllib.request.Request(self.base+action,data,headers)
        try: response=self.opener.open(request,timeout=30)
        except urllib.error.HTTPError as error:response=error
        with response:
            body=response.read().decode();assert response.status==status,(action,response.status,body)
            result=json.loads(body)
        self.csrf=result.get('csrf',self.csrf)
        return result
    def upload(self):
        boundary='StageAuthUploadTest'
        png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j5ZkAAAAASUVORK5CYII=')
        body=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="image.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+png+f'\r\n--{boundary}--\r\n'.encode())
        request=urllib.request.Request(self.base+'upload',body,{'X-CSRF-Token':self.csrf,'Content-Type':'multipart/form-data; boundary='+boundary})
        with self.opener.open(request,timeout=30) as response:return json.load(response)

def check_accounts(base,opener,csrf,folder):
    admin=Client(base,opener,csrf);visitor=Client(base)
    page_url=base.split('api.php')[0]
    with visitor.opener.open(page_url+'apresentador.php') as response:
        html=response.read().decode();assert 'registerForm' in html and 'setupForm' not in html
    assert not visitor.call('session')['setupRequired']
    visitor.call('state')
    visitor.call('users',status=401);visitor.call('logs',status=401)
    visitor.call('setup',{'setupKey':'troque-esta-senha','name':'Attacker','email':'second@example.test','password':'Second-Pass-123'},status=400)
    visitor.call('register',{'name':'Operator Test','email':'operator@example.test','password':'Operator-Pass-123','role':'admin','status':'active'})
    visitor.call('login',{'email':'operator@example.test','password':'Operator-Pass-123'},status=403)
    visitor.call('command',{'command':'pause'},status=401)
    users=admin.call('users')['users'];operator=next(u for u in users if u['email']=='operator@example.test');owner=next(u for u in users if u['email']=='admin@example.test')
    assert operator['status']=='pending' and operator['role']=='operator'
    assert all('password_hash' not in u for u in users)
    admin.call('user_update',{'id':operator['id'],'status':'active'})
    old_token=visitor.csrf
    visitor.call('login',{'email':'operator@example.test','password':'Operator-Pass-123'})
    assert visitor.csrf!=old_token,'Login must rotate the session CSRF token'
    assert visitor.call('session')['user']['role']=='operator'
    with visitor.opener.open(page_url+'apresentador.php') as response:
        html=response.read().decode();assert 'Operator Test' in html and 'href="administracao.php"' not in html
    with admin.opener.open(page_url+'administracao.php') as response:
        html=response.read().decode();assert 'logsPanel' in html and 'usersPanel' in html
    uploaded=visitor.upload()['state']['items'][-1]
    assert uploaded['uploadedBy']=={'id':operator['id'],'name':'Operator Test'}
    assert (folder/uploaded['src']).is_file()
    renamed=visitor.call('rename',{'id':uploaded['id'],'title':'  Organizado  '})['state']
    assert next(i for i in renamed['items'] if i['id']==uploaded['id'])['title']=='Organizado'
    visitor.call('rename',{'id':uploaded['id'],'title':'   '},status=400)
    visitor.call('rename',{'id':'missing','title':'Name'},status=400)
    visitor.call('rename',{'id':uploaded['id'],'title':'Name'},token='invalid',status=403)
    visitor.call('archive',{'id':uploaded['id']})
    renamed=visitor.call('rename',{'id':uploaded['id'],'title':'Arquivo arquivado'})['state']
    assert next(i for i in renamed['items'] if i['id']==uploaded['id'])['title']=='Arquivo arquivado'
    visitor.call('purge',{'id':uploaded['id']},status=403)
    admin.call('purge',{'id':uploaded['id']},token='invalid',status=403)
    assert (folder/uploaded['src']).is_file()
    # Simulate converted pages and a previous quality render, without a converter.
    statefile=folder/'data/state.json'
    fixture=json.loads(statefile.read_text(encoding='utf-8'))
    key=uploaded['src'].split('/')[-1].rsplit('.',1)[0]
    page_paths=[]
    for suffix in ['-pages','-hd-abcdef12-pages']:
        directory=folder/'uploads'/(key+suffix)
        directory.mkdir();page=directory/'page-1.png';page.write_bytes(b'page')
        page_paths.append(page)
    for entry in fixture['items']:
        if entry['id']==uploaded['id']:entry['pages']=[page_paths[-1].relative_to(folder).as_posix()]
    statefile.write_text(json.dumps(fixture),encoding='utf-8')
    purged=admin.call('purge',{'id':uploaded['id']})['state']
    assert all(i['id']!=uploaded['id'] for i in purged['items'])
    assert not (folder/uploaded['src']).exists()
    assert all(not p.parent.exists() for p in page_paths)
    admin.call('purge',{'id':uploaded['id']},status=400)
    visitor.call('restore',{'id':uploaded['id']},status=400)
    # Deleting the currently selected upload keeps the queue usable.
    active=visitor.upload()['state']['items'][-1]
    fallback=visitor.call('add_text',{'title':'Fallback','text':'Next'})['state']['items'][-1]
    visitor.call('select',{'id':active['id']})
    purged=admin.call('purge',{'id':active['id']})['state']
    assert purged['playback']['currentId']==fallback['id']
    purged=admin.call('purge',{'id':fallback['id']})['state']
    assert not purged['items'] and purged['playback']['currentId'] is None
    print('PASS rename, archived rename, admin-only deletion, CSRF, media cleanup and playback')
    state=visitor.call('add_text',{'title':'Audit test','text':'Original text','duration':'10','uploadedBy':'forged'})['state']
    item=state['items'][-1];assert item['uploadedBy']['id']==operator['id']
    visitor.call('save',json_body={'screen':{'volume':34},'items':[{'id':item['id'],'duration':17,'text':'Changed text','uploadedBy':{'name':'FORGED'}}]})
    visitor.call('command',{'command':'pause'})
    visitor.call('command',{'command':'pause'},token='invalid',status=403)
    visitor.call('command',status=405)
    visitor.call('logs',status=403);visitor.call('users',status=403)
    visitor.call('user_update',{'id':operator['id'],'role':'admin'},status=403)
    visitor.call('archive',{'id':item['id']});visitor.call('restore',{'id':item['id']})
    visitor.call('ui_event',{'event':'queue.archived'})
    admin.call('user_update',{'id':owner['id'],'role':'operator'},status=400)
    admin.call('user_update',{'id':owner['id'],'status':'suspended'},status=400)
    admin.call('user_update',{'id':operator['id'],'status':'suspended'})
    visitor.call('command',{'command':'play'},status=401)
    visitor.call('state',status=401,extra={'X-Stage-Panel':'1'})
    admin.call('user_update',{'id':operator['id'],'status':'active'})
    visitor.call('command',{'command':'play'})
    visitor.call('save',json_body={'items':[{'id':item['id'],'endsAt':'2020-01-01T00:00'}]})
    visitor.call('state')
    first=admin.call('logs');logs=first['logs']
    while first['next']:
        first=admin.call('logs&before='+str(first['next']));logs+=first['logs']
    actions={log['action'] for log in logs}
    assert {'account.setup','account.register','account.login','users.update','presentation.upload','presentation.text','presentation.edit','playback.pause','presentation.archive','presentation.restore','queue.automatic','ui.queue.archived'}.issubset(actions),actions
    upload_log=next(log for log in logs if log['action']=='presentation.upload')
    assert upload_log['actor_id']==operator['id'] and upload_log['actor_name']=='Operator Test'
    edit=next(log for log in logs if log['action']=='presentation.edit' and 'screen' in log['details'])
    assert edit['details']['screen']['volume']['after']==34
    expiry=next(log for log in logs if log['action']=='queue.automatic' and log['details'].get('items'))
    assert expiry['actor_name']=='Sistema'
    serialized=json.dumps(logs)
    for secret in ['Admin-Test-123','Operator-Pass-123','password_hash',visitor.csrf,admin.csrf]:assert secret not in serialized
    assert any(log['outcome']=='failure' for log in logs)
    assert any(log['action']=='request.command' and log['actor_id']==operator['id'] and log['outcome']=='failure' for log in logs),'Denied actions must retain the known session identity'
    filtered=admin.call('logs&q=Operator&actor='+operator['id'])['logs']
    assert filtered and all(log['actor_id']==operator['id'] for log in filtered)
    assert admin.call('logs&q=%25')['logs']==[], 'Percent is treated as a literal search character'
    visitor.call('logout',{});visitor.call('command',{'command':'play'},status=401)
    visitor.call('session')
    # Repeated invalid logins are limited, independently of the account approval logic.
    limited=False
    for number in range(25):
        try: visitor.call('login',{'email':'absent@example.test','password':'Wrong-Password'},status=401)
        except AssertionError as error:
            assert '429' in str(error),error
            limited=True;break
    assert limited
    print('PASS accounts: pending approval, roles, CSRF, session rotation, suspension and last-admin protection',flush=True)
    print('PASS upload ownership and audit: before/after, failures, automatic expiry and no credentials in logs',flush=True)
