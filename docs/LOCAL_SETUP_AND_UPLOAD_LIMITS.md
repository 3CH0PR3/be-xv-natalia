# Local Environment Setup & Batch Upload Limits (`be-xv-natalia`)

This document records the local Windows / Laravel Herd / PHP 8.4 / Redis / Reverb / FFmpeg configuration required for reliable large-batch photo and video uploads (tested up to 100 files and multi-GB videos).

## 1. Herd Nginx & Dedicated FastCGI Pool Configuration
File: `C:\Users\Hi\.config\herd\config\valet\Nginx\be-xv-natalia.test.conf`

Laravel Herd (`127.0.0.1:9084`) launches only 4 single-threaded `php-cgi.exe` workers (`PHP_FCGI_CHILDREN=4`) sharing a single Winsock listening socket. Under sustained 16 MB concurrent chunk streaming + polling, a single listening socket with `PHP_FCGI_CHILDREN` (even raised to `8` on `:9094`) still contends on Windows Winsock `accept()` across worker processes and can hit occasional `connect() failed (10060)` timeouts during burst uploads. To eliminate both pool saturation and shared-socket Winsock `accept()` contention without modifying Herd's global `nginx.conf`, `be-xv-natalia.test.conf` defines a site-scoped multi-port `upstream` pool at the top of the file (before the `server` blocks):

```nginx
upstream be_xv_natalia_php {
    server 127.0.0.1:9094 max_fails=2 fail_timeout=5s;
    server 127.0.0.1:9095 max_fails=2 fail_timeout=5s;
    server 127.0.0.1:9096 max_fails=2 fail_timeout=5s;
    server 127.0.0.1:9097 max_fails=2 fail_timeout=5s;
    server 127.0.0.1:9098 max_fails=2 fail_timeout=5s;
    server 127.0.0.1:9099 max_fails=2 fail_timeout=5s;
    server 127.0.0.1:9084 backup;
}
```

Inside both `location ~ [^/]\.php(/|$)` blocks (port `443` SSL and port `60`), `fastcgi_pass be_xv_natalia_php;` and `fastcgi_next_upstream error timeout;` are configured alongside `client_max_body_size 512M;`:

```nginx
fastcgi_pass be_xv_natalia_php;
fastcgi_next_upstream error timeout;
fastcgi_connect_timeout 300s;
fastcgi_send_timeout 300s;
fastcgi_read_timeout 300s;
fastcgi_request_buffering off;
```

- **6 independent single-worker `php-cgi.exe` listeners (`127.0.0.1:9094`–`127.0.0.1:9099`)**: launched by `be-xv-natalia/start-dev.ps1` with `PHP_FCGI_CHILDREN` unset and `PHP_FCGI_MAX_REQUESTS=0` (spawning 6 dedicated `C:\Users\Hi\.config\herd\bin\php84\php-cgi.exe -b 127.0.0.1:<port>` processes, one per port). Replacing single-socket `PHP_FCGI_CHILDREN=8` on `:9094` with 6 independent single-port listeners gives each worker its own Winsock listen socket and eliminates shared-handle `accept()` contention and `10060` timeouts. If `:9094–:9099` are offline, Nginx automatically fails over to Herd's `:9084` `backup` pool.
- **Important**: do **NOT** add `fastcgi_keep_conn on;` while Herd's global `nginx.conf` uses `worker_processes auto;` (12 Nginx workers), as per-worker idle keepalive connections can starve single-threaded `php-cgi.exe` processes.
- `fastcgi_request_buffering off;` prevents Nginx from buffering 16 MB binary chunk bodies twice before streaming them to `php-cgi.exe`.
- `fastcgi_*_timeout 300s;` prevents upstream timeouts during concurrent batch uploads.
- Apply Nginx changes with `nginx.exe -p "C:\Users\Hi\.config\herd\config\nginx" -c nginx.conf -s reload` (or `herd restart`). Note that `herd restart` terminates all running `php-cgi.exe` processes on Windows, so start (or re-run) the `:9094–:9099` listeners from `start-dev.ps1` **after** `herd restart`.
- Note: Running `herd secure` / `herd unsecure` regenerates `be-xv-natalia.test.conf`; re-apply the `upstream be_xv_natalia_php` block and FastCGI directives if Herd overwrites the site file.

## 2. PHP 8.4 Configuration
File: `C:\Users\Hi\.config\herd\bin\php84\php.ini`

```ini
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
post_max_size = 256M
upload_max_filesize = 256M
max_file_uploads = 100
```

Restart Herd services (`herd restart`) after modifying `php.ini` so all `php-cgi.exe` workers reload the limits.

## 3. FFmpeg & FFprobe Installation
Installed persistently via WinGet (`Gyan.FFmpeg` v9.1):

- Command: `winget install -e --id Gyan.FFmpeg --accept-source-agreements --accept-package-agreements`
- Binaries:
  - `ffmpeg.exe`: `C:\Users\Hi\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-9.1-full_build\bin\ffmpeg.exe`
  - `ffprobe.exe`: `C:\Users\Hi\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-9.1-full_build\bin\ffprobe.exe`
- `.env` keys (`config/xv.php`):
  - `FFMPEG_ENABLED=true` (defaults to `false` under `APP_ENV=testing` so PHPUnit tests using `Storage::fake()` stay fast and deterministic)
  - `FFMPEG_BIN` and `FFPROBE_BIN` set with forward-slash paths (with automatic WinGet/PATH discovery fallback in `App\Support\Media\FfmpegVideoProcessor`).
- Behavior in `ProcessUploadOperationFileAction`:
  - Browser-incompatible video containers (`.avi`, `.ts`, `.mts`, `.m2ts`, `.mkv`, `.wmv`, `.flv`, `.3gp`, `.3g2`, `.ogv`, `.ogg`) are transcoded to H.264/AAC `.mp4` (`-movflags +faststart`) when valid media streams are detected, updating `final_path`/`file_path`, `mime_type` (`video/mp4`), and `byte_size`.
  - Videos with `poster_path === null` and a valid video stream automatically receive a `.jpg` poster frame extracted via `ffmpeg`, persisted on both `upload_operation_files` and `memory_media_items`.

## 4. Redis DB Isolation, Queue `retry_after`, and Logging
- `config/database.php`: defines a dedicated `'queue'` Redis connection mapped to `REDIS_QUEUE_DB=3`.
- `.env`:
  - `REDIS_QUEUE_CONNECTION=queue` (isolates `xv_natalia` queues from Redis DB 0)
  - `REDIS_QUEUE_RETRY_AFTER=660` (exceeds `media-heavy` worker `--timeout=600`)
  - `LOG_LEVEL=warning` (ensures non-fatal Reverb/FFmpeg warnings appear in `storage/logs/laravel.log`)
- `config/queue.php`: `'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 660)`.

## 5. Background Services (`start-dev.ps1`)
Because Laravel Horizon requires Linux `pcntl`/`posix`, local Windows development runs dedicated processes via `be-xv-natalia/start-dev.ps1`:

1. **Dedicated FastCGI Pool (`127.0.0.1:9094`–`127.0.0.1:9099`)**: 6 independent single-worker `php-cgi.exe -b 127.0.0.1:<port>` listeners with `PHP_FCGI_CHILDREN` unset and `PHP_FCGI_MAX_REQUESTS=0` (each port checked before launch and skipped if already listening).
2. **Reverb WebSockets (`:8081`)**: `php artisan reverb:start --host=0.0.0.0 --port=8081` (`REVERB_MAX_REQUEST_SIZE=524288`, `REVERB_APP_MAX_MESSAGE_SIZE=524288`).
3. **Fast Queue Worker**: `php artisan queue:work redis --queue=media-fast,default --tries=3 --timeout=90`
4. **Heavy Queue Worker**: `php artisan queue:work redis --queue=media-heavy --tries=2 --timeout=600` (videos `> 25 MB`).

## 6. Chunk Storage & Broadcast Architecture Notes
- Incoming binary/multipart chunks stream directly into isolated `.staging-{fileKey}-chunks/{chunkIndex}.part` files without `flock()` or `php://temp` double-buffering, avoiding NTFS synchronous sparse zero-fill on out-of-order chunks.
- `StoreOperationChunkAction` locks only `upload_operation_files` during intermediate chunk receipt and avoids locking `upload_operations` until file upload completion (or multipart checkpoint).
- `ProcessUploadOperationFileAction` validates all `0..total_chunks-1` parts, concatenates sequentially, processes images/videos, and cleans up the `.part` directory on commit.
- `UploadOperationHelper::finalPathFor` caps slugified base stems so paths always remain within `varchar(255)`.
- `UploadOperationHelper` compacts both `UploadOperationUpdated` and `MemoryCreated`/`MemoryUpdated` payloads safely below `REVERB_MAX_REQUEST_SIZE=524288` for batches up to 100 media items.
