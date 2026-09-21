"""Exercise the real HTTP API and converters against a disposable copy, never live data."""
from pathlib import Path
import http.cookiejar
import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.request
import urllib.parse
import zipfile

ROOT=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(ROOT/'.runtime/python'))
import pymupdf

def fixtures(folder):
    with pymupdf.open() as doc:
        for number in range(3):
            page=doc.new_page(width=960,height=540)
            page.insert_text((70,150),f'StageTV - Page {number+1}',fontsize=40)
        doc.save(folder/'sample.pdf')
    with zipfile.ZipFile(folder/'sample.xlsx','w') as z:
        z.writestr('[Content_Types].xml','''<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>''')
        z.writestr('_rels/.rels','''<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>''')
        z.writestr('xl/workbook.xml','''<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="First sheet" sheetId="1" r:id="rId1"/><sheet name="Second sheet" sheetId="2" r:id="rId2"/></sheets></workbook>''')
        z.writestr('xl/_rels/workbook.xml.rels','''<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>''')
        for n in [1,2]:
            z.writestr(f'xl/worksheets/sheet{n}.xml',f'''<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>StageTV Sheet {n}</t></is></c></row></sheetData></worksheet>''')
    with zipfile.ZipFile(folder/'sample.odp','w') as z:
        z.writestr('mimetype','application/vnd.oasis.opendocument.presentation')
        z.writestr('META-INF/manifest.xml','''<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2"><manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.presentation"/><manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/></manifest:manifest>''')
        z.writestr('content.xml','''<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" office:version="1.2"><office:body><office:presentation><draw:page draw:name="First"><draw:frame svg:x="2cm" svg:y="2cm" svg:width="20cm" svg:height="4cm"><draw:text-box><text:p>First slide</text:p></draw:text-box></draw:frame></draw:page><draw:page draw:name="Second"><draw:frame svg:x="2cm" svg:y="2cm" svg:width="20cm" svg:height="4cm"><draw:text-box><text:p>Second slide</text:p></draw:text-box></draw:frame></draw:page></office:presentation></office:body></office:document-content>''')
    subprocess.run([str(ROOT/'.runtime/LibreOffice/program/soffice.com'),'-env:UserInstallation='+(folder/'lo-profile').as_uri(),'--headless','--convert-to','pptx','--outdir',str(folder),str(folder/'sample.odp')],check=True,timeout=150,capture_output=True,creationflags=subprocess.CREATE_NO_WINDOW)

with tempfile.TemporaryDirectory(prefix='integration-',dir=ROOT/'.runtime',ignore_cleanup_errors=True) as directory:
    folder=Path(directory)
    if '--queue-only' not in sys.argv and '--auth-only' not in sys.argv: fixtures(folder)
    for name in ['api.php','index.php','apresentador.php','visualizador.php','administracao.php']:
        shutil.copy2(ROOT/name,folder/name)
    for directory_name in ['assets','app','config']:
        shutil.copytree(ROOT/directory_name,folder/directory_name)
    (folder/'scripts').mkdir();shutil.copy2(ROOT/'scripts/convert_document.py',folder/'scripts/convert_document.py')
    (folder/'data').mkdir();(folder/'uploads').mkdir()
    environment={**os.environ,'PYTHONPATH':str(ROOT/'.runtime/python'),'STAGETV_SOFFICE':str(ROOT/'.runtime/LibreOffice/program/soffice.exe'),'STAGETV_PYTHON':sys.executable}
    log=(folder/'server.log').open('w')
    server=subprocess.Popen([r'C:\xampp\php\php.exe','-S','127.0.0.1:8765','-t',str(folder)],stdout=log,stderr=log,env=environment,creationflags=subprocess.CREATE_NO_WINDOW)
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    base='http://127.0.0.1:8765/api.php?action='
    csrf=''
    def request(action,values=None):
        global csrf
        opener.addheaders=[('X-CSRF-Token',csrf)]
        body=urllib.parse.urlencode(values).encode() if values is not None else None
        with opener.open(base+action,body,timeout=240) as response:
            data=json.load(response)
        assert data['ok'],data
        csrf=data.get('csrf',csrf)
        opener.addheaders=[('X-CSRF-Token',csrf)]
        return data
    try:
        for attempt in range(30):
            try: request('state');break
            except OSError: time.sleep(.1)
        request('session')
        request('setup',{'setupKey':'troque-esta-senha','name':'Admin Test','email':'admin@example.test','password':'Admin-Test-123'})
        if '--auth-only' in sys.argv:
            from auth_checks import check_accounts
            check_accounts(base,opener,csrf,folder)
            sys.exit(0)
        request('command',{'command':'pause'})
        if '--queue-only' in sys.argv:
            current=request('add_text',{'title':'Queue test','text':'Preserved content','duration':'1'})['state']
            item=current['items'][0]
            scheduled=request('schedule',{'id':item['id'],'startsAt':'','endsAt':'2035-05-01T12:00'})['state']['items'][0]
            assert scheduled['endsAt']=='2035-05-01T12:00'
            for start,end in [('2035-05-01T13:00','2035-05-01T12:00'),('2035-02-30T10:00','')]:
                try: request('schedule',{'id':item['id'],'startsAt':start,'endsAt':end})
                except urllib.error.HTTPError as error: assert error.code==400
                else: raise AssertionError('Invalid schedule accepted')
            assert request('state')['state']['items'][0]['endsAt']=='2035-05-01T12:00'
            request('schedule',{'id':item['id'],'startsAt':'','endsAt':''})
            request('command',{'command':'play'});time.sleep(1.2)
            after=request('state')['state'];assert not after['items'][0].get('archivedAt')
            request('command',{'command':'next'})
            assert not request('state')['state']['items'][0].get('archivedAt')
            body=json.dumps({'items':[{'id':item['id'],'endsAt':'2020-01-01T00:00'}]}).encode()
            with opener.open(urllib.request.Request(base+'save',body,{'Content-Type':'application/json'})) as response:json.load(response)
            expired=request('state')['state']['items'][0]
            assert expired['archiveReason']=='expired' and expired['text']=='Preserved content'
            restored=request('restore',{'id':item['id']})['state']['items'][0]
            assert not restored.get('archivedAt') and not restored['endsAt']
            assert not request('state')['state']['items'][0].get('archivedAt')
            manual=request('delete',{'id':item['id']})['state']['items'][0]
            assert manual['archiveReason']=='manual' and manual['text']=='Preserved content'
            statefile=folder/'data/state.json'
            fixture=json.loads(statefile.read_text(encoding='utf-8'))
            fixture['items'].append({'id':'video-test','type':'video','title':'Video','enabled':True,'volume':100,'loop':False,'duration':10,'delay':0,'startsAt':'','endsAt':''})
            statefile.write_text(json.dumps(fixture),encoding='utf-8')
            updated=request('video_duration',{'id':'video-test','duration':'12.375'})['state']
            assert next(i for i in updated['items'] if i['id']=='video-test')['duration']==12.375
            body=json.dumps({'items':[{'id':'video-test','duration':2}]}).encode()
            with opener.open(urllib.request.Request(base+'save',body,{'Content-Type':'application/json'})) as response: updated=json.load(response)['state']
            assert next(i for i in updated['items'] if i['id']=='video-test')['duration']==12.375
            try: request('video_duration',{'id':'video-test','duration':'-1'})
            except urllib.error.HTTPError as error: assert error.code==400
            else: raise AssertionError('Invalid video duration accepted')
            print('PASS video duration precision and protection against manual overwrite',flush=True)
            print('PASS HTTP recurring queue, expiry, restoration and manual removal',flush=True)
            sys.exit(0)
        for ext,count in [('pdf',3),('xlsx',2),('pptx',2),('odp',2)]:
            name='sample.'+ext;boundary='StageTVTestBoundary'
            body=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()+(folder/name).read_bytes()+f'\r\n--{boundary}--\r\n'.encode())
            req=urllib.request.Request(base+'upload',body,{'Content-Type':'multipart/form-data; boundary='+boundary})
            try:
                with opener.open(req,timeout=240) as response:data=json.load(response)
            except urllib.error.HTTPError as error:raise AssertionError(error.read().decode())
            item=data['state']['items'][-1];assert item['type']=='presentation',item
            assert len(item['pages'])==count,(ext,item['pages'])
            assert 'serverPath' not in item
            for src in item['pages']:
                image=pymupdf.Pixmap(str(folder/src))
                assert max(image.width,image.height)==3840,(image.width,image.height)
            print('PASS real upload and conversion:',ext,count,'pages',flush=True)
        item=data['state']['items'][0]
        request('select',{'id':item['id']})
        pause=request('command',{'command':'pause'})['state']['playback'];assert pause['paused']
        time.sleep(.35)
        frozen=request('state')['state']['playback'];assert abs(pause['offsetMs']-frozen['offsetMs'])<1
        next_page=request('command',{'command':'next'})['state']['playback'];assert next_page['page']==1 and next_page['paused']
        previous=request('command',{'command':'prev'})['state']['playback'];assert previous['page']==0
        request('command',{'command':'play'});time.sleep(.1)
        one=request('state');time.sleep(.2);two=request('state')
        a,b=one['state']['playback'],two['state']['playback']
        assert abs((b['offsetMs']-a['offsetMs'])-(two['serverTimeMs']-one['serverTimeMs']))<2
        assert request('command',{'command':'blackout'})['state']['playback']['blackout']
        assert request('state')['state']['playback']['blackout']
        assert not request('command',{'command':'blackout'})['state']['playback']['blackout']
        before=request('state')['state']['playback']['reloadNonce']
        assert request('command',{'command':'reload'})['state']['playback']['reloadNonce']==before+1
        assert request('state')['state']['playback']['reloadNonce']==before+1
        archived=request('archive',{'id':item['id']})['state']
        archived_item=next(it for it in archived['items'] if it['id']==item['id'])
        assert archived_item['archiveReason']=='manual' and archived_item['archivedAt']
        assert (folder/archived_item['src']).is_file()
        assert all((folder/page).is_file() for page in archived_item['pages'])
        restored=request('restore',{'id':item['id']})['state']
        assert restored['items'][-1]['id']==item['id'] and not restored['items'][-1].get('archivedAt')
        # The old delete endpoint must also archive, even for an outdated presenter tab.
        legacy=request('delete',{'id':item['id']})['state']
        assert next(it for it in legacy['items'] if it['id']==item['id'])['archivedAt']
        assert (folder/archived_item['src']).is_file()
        print('PASS archive, restore, original preservation and safe legacy delete',flush=True)
        print('PASS HTTP controls: pause, page navigation, late join, blackout, reload',flush=True)
    finally:
        server.terminate();server.wait(timeout=10);log.close()
