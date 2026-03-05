import http.server
import socketserver
import json
import os
import re
import scraper
import threading

PORT = 8080
DIRECTORY = "."

# Global variable to check if a refresh is already in progress
refresh_lock = threading.Lock()
is_refreshing = False

def background_refresh():
    global is_refreshing
    with refresh_lock:
        if is_refreshing:
            return
        is_refreshing = True
    
    try:
        print("Background refreshing recordings list from remote...")
        scraper.update_recordings()
        print("Background refresh complete.")
    except Exception as e:
        print(f"Background refresh failed: {e}")
    finally:
        with refresh_lock:
            is_refreshing = False

class MyHandler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/api/recordings':
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            
            # Check for synchronous refresh request
            if 'refresh=1' in self.path:
                print("Manual refresh requested. Synchronously updating recordings...")
                scraper.update_recordings()
                print("Manual refresh complete.")
            else:
                # Trigger background refresh for normal requests
                threading.Thread(target=background_refresh, daemon=True).start()
            
            # Immediately serve current data (which is now updated if refresh=1 was passed)
            if os.path.exists("recordings.json"):
                with open("recordings.json", "r", encoding="utf-8") as f:
                    self.wfile.write(f.read().encode())
            else:
                self.wfile.write(json.dumps([]).encode())
            return

        # Handle Range requests for local audio files
        if self.path.startswith('/recordings/'):
            file_path = self.translate_path(self.path)
            if os.path.isfile(file_path):
                if 'Range' in self.headers:
                    return self.handle_range_request(file_path)
                else:
                    return self.handle_regular_request(file_path)

        return http.server.SimpleHTTPRequestHandler.do_GET(self)

    def do_HEAD(self):
        if self.path.startswith('/recordings/'):
            file_path = self.translate_path(self.path)
            if os.path.isfile(file_path):
                self.send_response(200)
                self.send_header('Content-type', self.guess_type(file_path))
                self.send_header('Content-Length', str(os.path.getsize(file_path)))
                self.send_header('Accept-Ranges', 'bytes')
                self.end_headers()
                return
        return http.server.SimpleHTTPRequestHandler.do_HEAD(self)

    def handle_regular_request(self, file_path):
        self.send_response(200)
        self.send_header('Content-type', self.guess_type(file_path))
        self.send_header('Content-Length', str(os.path.getsize(file_path)))
        self.send_header('Accept-Ranges', 'bytes')
        self.end_headers()
        with open(file_path, 'rb') as f:
            self.wfile.write(f.read())

    def handle_range_request(self, file_path):
        range_header = self.headers.get('Range')
        match = re.match(r'bytes=(\d+)-(\d*)', range_header)
        if not match:
            return self.handle_regular_request(file_path)

        file_size = os.path.getsize(file_path)
        start = int(match.group(1))
        end = match.group(2)
        end = int(end) if end else file_size - 1

        if start >= file_size:
            self.send_error(416, "Requested Range Not Satisfiable")
            return

        self.send_response(206)
        self.send_header('Content-type', self.guess_type(file_path))
        self.send_header('Accept-Ranges', 'bytes')
        self.send_header('Content-Range', f'bytes {start}-{end}/{file_size}')
        self.send_header('Content-Length', str(end - start + 1))
        self.end_headers()

        with open(file_path, 'rb') as f:
            f.seek(start)
            remaining = end - start + 1
            while remaining > 0:
                chunk_size = min(remaining, 64 * 1024)
                data = f.read(chunk_size)
                if not data:
                    break
                self.wfile.write(data)
                remaining -= len(data)

if __name__ == "__main__":
    os.chdir(DIRECTORY)
    with socketserver.ThreadingTCPServer(("", PORT), MyHandler) as httpd:
        print(f"Serving at http://localhost:{PORT}")
        httpd.serve_forever()
