#!/usr/bin/env python3
"""Runs the real catalog cropper in headless Chrome. Run from the project root:

    python3 tests/browser/run_catalog_cropper.py

Needs Google Chrome and `npm install`. Set CHROME_PATH if Chrome is elsewhere.
"""
import functools
import http.server
import json
import os
import pathlib
import subprocess
import sys
import tempfile
import threading

ROOT = pathlib.Path(__file__).resolve().parents[2]
CHROME = os.environ.get('CHROME_PATH', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')
PIXEL = bytes.fromhex('47494638396101000100800000000000ffffff21f90401000000002c00000000010001000002024401003b')
finished = threading.Event()
results = []


class Handler(http.server.SimpleHTTPRequestHandler):
    extensions_map = {**http.server.SimpleHTTPRequestHandler.extensions_map, '.js': 'text/javascript'}

    def do_GET(self):
        if self.path == '/__hold':
            finished.wait(120)
            self.send_response(200)
            self.send_header('Content-Type', 'image/gif')
            self.end_headers()
            self.wfile.write(PIXEL)
            return
        super().do_GET()

    def do_POST(self):
        results.extend(json.loads(self.rfile.read(int(self.headers['Content-Length']))))
        self.send_response(204)
        self.end_headers()
        finished.set()

    def log_message(self, *args):
        pass


server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(Handler, directory=str(ROOT)))
threading.Thread(target=server.serve_forever, daemon=True).start()
url = f'http://127.0.0.1:{server.server_address[1]}/tests/browser/catalog-cropper.html'

with tempfile.TemporaryDirectory() as profile:
    subprocess.run([
        CHROME, '--headless=new', '--disable-gpu', '--no-first-run',
        f'--user-data-dir={profile}', '--dump-dom', url,
    ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=150, check=False)
server.shutdown()

if not results:
    sys.exit('The browser check did not report any results.')
for result in results:
    print(('PASS' if result['passed'] else 'FAIL'), result['name'], '' if result['passed'] else '-> ' + result['detail'])
failed = sum(not result['passed'] for result in results)
print(f'{len(results) - failed} passed, {failed} failed')
sys.exit(1 if failed else 0)
