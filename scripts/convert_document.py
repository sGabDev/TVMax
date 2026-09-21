"""Convert uploaded PDF/Office documents into numbered presentation images."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / '.runtime' / 'python'))

def convert(source, output):
    import pymupdf
    source, output = Path(source).resolve(), Path(output).resolve()
    output.mkdir(parents=True, exist_ok=True)
    pdf = source
    if source.suffix.lower() != '.pdf':
        candidates = [os.environ.get('TVMAX_SOFFICE', ''), os.environ.get('STAGETV_SOFFICE', ''),
                      str(ROOT / '.runtime' / 'LibreOffice' / 'program' / 'soffice.com'),
                      shutil.which('soffice'), shutil.which('libreoffice')]
        soffice = next((p for p in candidates if p and Path(p).is_file()), None)
        if not soffice:
            raise RuntimeError('Conversor de planilhas e slides indisponível no servidor. Configure STAGETV_SOFFICE.')
        if os.name == 'nt' and Path(soffice).with_suffix('.com').is_file():
            soffice = str(Path(soffice).with_suffix('.com'))
        profiles = ROOT / 'data' / 'conversion-profiles'
        profiles.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(prefix='profile-', dir=profiles, ignore_cleanup_errors=True) as profile:
            # A fresh profile prevents another Office session from intercepting the conversion.
            subprocess.run([soffice, '-env:UserInstallation=' + Path(profile).as_uri(),
                            '--headless', '--nologo', '--nodefault', '--nofirststartwizard',
                            '--convert-to', 'pdf', '--outdir', str(output), str(source)],
                           timeout=150, check=True, capture_output=True,
                           creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
        pdf = output / (source.stem + '.pdf')
        if not pdf.is_file():
            raise RuntimeError('Não foi possível converter este documento em páginas.')
    with pymupdf.open(pdf) as doc:
        if doc.needs_pass:
            raise RuntimeError('Remova a senha do documento antes de enviar.')
        if not 1 <= len(doc) <= 300:
            raise RuntimeError('A apresentação deve ter entre 1 e 300 páginas.')
        names = []
        for number, page in enumerate(doc):
            # Render above Full HD so text stays sharp when scaled on a 1080p display.
            # The old 2x ceiling reduced typical slides to only 1440 pixels wide.
            scale = 3840 / max(page.rect.width, page.rect.height)
            pixmap = page.get_pixmap(matrix=pymupdf.Matrix(scale, scale), alpha=False)
            name = f'page-{number + 1:04d}.png'
            pixmap.save(output / name)
            names.append(name)
    return names

if __name__ == '__main__':
    try:
        print(json.dumps({'ok': True, 'pages': convert(*sys.argv[1:3])}, ensure_ascii=True))
    except Exception as error:
        print(json.dumps({'ok': False, 'error': str(error)}, ensure_ascii=True))
        sys.exit(1)
