#!/usr/bin/env bash
set -u

cd "$(dirname "$0")" || exit 1

PORT=8000
HOST=127.0.0.1
BUKA_BROWSER=1
RESET_DB=0
DB_FILE="database/database.sqlite"
DB_BARU=0
SEED_DIJALANKAN=0
NODE_SIAP=0
TUNNEL=0
TUNNEL_URL=""
CLOUDFLARED_PID=""
CLOUDFLARED_LOG=""

if [ -t 1 ]; then
    TEBAL=$'\033[1m'
    HIJAU=$'\033[32m'
    KUNING=$'\033[33m'
    MERAH=$'\033[31m'
    NORMAL=$'\033[0m'
else
    TEBAL=""
    HIJAU=""
    KUNING=""
    MERAH=""
    NORMAL=""
fi

langkah() { printf '\n%s==> %s%s\n' "$TEBAL" "$1" "$NORMAL"; }
sukses() { printf '%s✓ %s%s\n' "$HIJAU" "$1" "$NORMAL"; }
peringatan() { printf '%s! %s%s\n' "$KUNING" "$1" "$NORMAL"; }
gagal() {
    printf '%s✗ %s%s\n' "$MERAH" "$1" "$NORMAL" >&2
    exit 1
}

bantuan() {
    cat <<'TEKS'
Cara pakai: bash start.sh [opsi]

  --lan          bisa dibuka dari HP/laptop lain yang satu WiFi
  --tunnel       buat alamat publik sementara (Cloudflare Tunnel) untuk demo/sidang
  --port ANGKA   pakai port tertentu (bawaan 8000)
  --fresh        hapus semua data lalu isi ulang dengan data demo
  --no-open      jangan buka browser otomatis
  -h, --help     tampilkan bantuan ini
TEKS
}

baca_opsi() {
    while [ $# -gt 0 ]; do
        case "$1" in
            --lan) HOST=0.0.0.0 ;;
            --tunnel) TUNNEL=1 ;;
            --fresh) RESET_DB=1 ;;
            --no-open) BUKA_BROWSER=0 ;;
            --port)
                shift
                PORT="${1:-}"
                ;;
            --port=*) PORT="${1#--port=}" ;;
            -h | --help)
                bantuan
                exit 0
                ;;
            *)
                bantuan
                gagal "Opsi tidak dikenal: $1"
                ;;
        esac
        shift
    done

    case "$PORT" in
        '' | *[!0-9]*) gagal "Port harus berupa angka." ;;
    esac
}

tambah_path() {
    [ -d "$1" ] || return 0
    case ":$PATH:" in
        *":$1:"*) ;;
        *) PATH="$PATH:$1" ;;
    esac
}

php_memenuhi() {
    command -v php >/dev/null 2>&1 && php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null
}

node_memenuhi() {
    command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1 &&
        node -e 'const [a, b] = process.versions.node.split(".").map(Number); process.exit(a > 22 || (a === 22 && b >= 12) || (a === 20 && b >= 19) ? 0 : 1)' 2>/dev/null
}

pasang_brew() {
    command -v brew >/dev/null 2>&1 ||
        gagal "$1 belum terpasang dan Homebrew tidak ditemukan. Pasang Homebrew dari https://brew.sh, lalu jalankan ulang: bash start.sh"
    langkah "Memasang $1 lewat Homebrew"
    brew install "$1" || gagal "Gagal memasang $1 lewat Homebrew."
}

periksa_alat() {
    langkah "Memeriksa PHP, Composer, dan Node.js"

    php_memenuhi || pasang_brew php
    php_memenuhi || gagal "PHP 8.3 atau lebih baru diperlukan (terdeteksi: $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo tidak ada))."
    php -m | grep -qi '^pdo_sqlite$' || gagal "Ekstensi PHP pdo_sqlite tidak aktif. Gunakan PHP dari Homebrew: brew install php"

    command -v composer >/dev/null 2>&1 || pasang_brew composer

    sukses "PHP $(php -r 'echo PHP_VERSION;') dan Composer siap"

    if node_memenuhi; then
        NODE_SIAP=1
        sukses "Node.js $(node -v) siap"
    else
        peringatan "Node.js 20.19+ atau 22.12+ tidak ditemukan (pasang dengan: brew install node)."
        peringatan "Aplikasi tetap jalan, hanya halaman Lupa Password yang belum bisa dibuka."
    fi
}

pasang_cloudflared() {
    [ "$TUNNEL" = 1 ] || return 0
    command -v cloudflared >/dev/null 2>&1 || pasang_brew cloudflared
    command -v cloudflared >/dev/null 2>&1 || gagal "cloudflared tidak ditemukan setelah dipasang."
    sukses "cloudflared siap"
}

siapkan_env() {
    if [ ! -f .env ]; then
        [ -f .env.example ] || gagal "File .env.example tidak ditemukan."
        cp .env.example .env
        sukses "File .env dibuat dari .env.example"
    fi
}

cek_env_produksi() {
    grep -qE '^APP_ENV=production' .env 2>/dev/null || return 0
    peringatan "APP_ENV di .env ini adalah 'production'. start.sh menjalankan server lewat 'php artisan serve', yang HANYA cocok untuk dipakai di komputer sendiri, bukan untuk hosting publik sungguhan."
}

pasang_composer() {
    local stempel="vendor/.lock-terpasang"
    local hash_lock
    hash_lock="$(shasum composer.lock | cut -d' ' -f1)"

    if [ -f vendor/autoload.php ] && [ -f "$stempel" ] && [ "$(cat "$stempel")" = "$hash_lock" ]; then
        sukses "Dependensi PHP sudah terpasang"
        return 0
    fi

    langkah "Memasang dependensi PHP (Composer)"
    composer install --no-interaction --prefer-dist --no-progress || gagal "composer install gagal."
    printf '%s' "$hash_lock" >"$stempel"
}

buat_app_key() {
    grep -qE '^APP_KEY=.+' .env && return 0
    php artisan key:generate --force --no-interaction >/dev/null 2>&1 || gagal "Gagal membuat APP_KEY."
    sukses "APP_KEY dibuat"
}

jumlah_user() {
    command -v sqlite3 >/dev/null 2>&1 || return 1
    sqlite3 "$DB_FILE" 'select count(*) from users;' 2>/dev/null
}

konfirmasi_reset() {
    printf '%sSemua data di database akan DIHAPUS dan diganti data demo. Lanjutkan? [y/N] %s' "$KUNING" "$NORMAL"
    read -r jawaban
    case "$jawaban" in
        y | Y | ya | Ya | YA) ;;
        *)
            echo "Dibatalkan."
            exit 0
            ;;
    esac
}

siapkan_database() {
    langkah "Menyiapkan database SQLite"

    if [ "$RESET_DB" = 1 ]; then
        konfirmasi_reset
        [ -f "$DB_FILE" ] || : >"$DB_FILE"
        php artisan migrate:fresh --seed --force --no-interaction || gagal "Reset database gagal."
        SEED_DIJALANKAN=1
        return 0
    fi

    if [ ! -f "$DB_FILE" ]; then
        : >"$DB_FILE"
        DB_BARU=1
    fi

    php artisan migrate --force --no-interaction || gagal "Migrasi database gagal."

    if [ "$DB_BARU" = 1 ] || [ "$(jumlah_user)" = "0" ]; then
        php artisan db:seed --force --no-interaction || gagal "Pengisian data demo gagal."
        SEED_DIJALANKAN=1
    fi
}

perlu_build() {
    [ -f public/build/manifest.json ] || return 0
    [ -n "$(find resources package.json package-lock.json tailwind.config.js postcss.config.js vite.config.js -newer public/build/manifest.json 2>/dev/null | head -n 1)" ]
}

bangun_aset() {
    [ "$NODE_SIAP" = 1 ] || return 0

    langkah "Menyiapkan tampilan halaman Lupa Password (Vite)"

    if [ ! -x node_modules/.bin/vite ]; then
        if ! npm install --no-audit --no-fund; then
            peringatan "npm install gagal. Langkah ini dilewati, halaman Lupa Password belum bisa dibuka."
            return 0
        fi
    fi

    if ! perlu_build; then
        sukses "Aset tampilan sudah terbaru"
        return 0
    fi

    if npm run build; then
        sukses "Aset tampilan berhasil dibangun"
        return 0
    fi

    peringatan "Build gagal. Memasang ulang node_modules lalu mencoba sekali lagi..."
    rm -rf node_modules
    if npm install --no-audit --no-fund && npm run build; then
        sukses "Aset tampilan berhasil dibangun"
        return 0
    fi

    peringatan "Build tetap gagal. Aplikasi utama tetap jalan, tapi halaman Lupa Password akan error."
}

port_terpakai() {
    lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1
}

pilih_port() {
    local awal="$PORT"
    local batas=$((PORT + 20))

    while [ "$PORT" -le "$batas" ]; do
        if ! port_terpakai "$PORT"; then
            [ "$PORT" = "$awal" ] || peringatan "Port $awal sedang dipakai, memakai port $PORT."
            return 0
        fi
        PORT=$((PORT + 1))
    done

    gagal "Tidak menemukan port kosong antara $awal dan $batas."
}

alamat_lan() {
    ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null
}

hentikan_tunnel() {
    [ -n "$CLOUDFLARED_PID" ] || return 0
    kill "$CLOUDFLARED_PID" 2>/dev/null
    wait "$CLOUDFLARED_PID" 2>/dev/null
    [ -n "$CLOUDFLARED_LOG" ] && rm -f "$CLOUDFLARED_LOG"
}

mulai_tunnel() {
    [ "$TUNNEL" = 1 ] || return 0

    langkah "Membuka alamat publik sementara (Cloudflare Tunnel)"

    CLOUDFLARED_LOG="$(mktemp)"
    cloudflared tunnel --url "http://127.0.0.1:$PORT" --no-autoupdate >"$CLOUDFLARED_LOG" 2>&1 &
    CLOUDFLARED_PID=$!
    trap hentikan_tunnel EXIT INT TERM

    local i=0
    while [ "$i" -lt 30 ]; do
        TUNNEL_URL="$(grep -oE 'https://[a-zA-Z0-9-]+\.trycloudflare\.com' "$CLOUDFLARED_LOG" | head -n 1)"
        [ -n "$TUNNEL_URL" ] && break
        kill -0 "$CLOUDFLARED_PID" 2>/dev/null || gagal "cloudflared berhenti sebelum alamat publik siap. Pastikan internet aktif lalu coba lagi."
        sleep 1
        i=$((i + 1))
    done

    [ -n "$TUNNEL_URL" ] || gagal "Alamat publik tidak muncul dalam 30 detik. Coba jalankan ulang, atau jalankan tanpa --tunnel."

    sukses "Alamat publik siap: $TUNNEL_URL"
}

tampilkan_info() {
    local ip

    printf '\n%s✓ SiWarung siap dijalankan%s\n\n' "$HIJAU" "$NORMAL"
    printf '  Buka di browser : http://localhost:%s\n' "$PORT"

    if [ "$HOST" = "0.0.0.0" ]; then
        ip="$(alamat_lan)"
        if [ -n "$ip" ]; then
            printf '  Dari HP (WiFi sama): http://%s:%s\n' "$ip" "$PORT"
        fi
    fi

    if [ -n "$TUNNEL_URL" ]; then
        printf '\n  %sAlamat publik (sementara): %s%s\n' "$TEBAL" "$TUNNEL_URL" "$NORMAL"
        printf '  Bagikan alamat ini hanya untuk demo/sidang. Alamat ini mati begitu skrip ini dihentikan (Ctrl+C).\n'
        if grep -qE '^APP_DEBUG=true' .env 2>/dev/null; then
            peringatan "APP_DEBUG masih 'true' — kalau terjadi error saat alamat publik ini dibuka, siapa pun yang membukanya akan melihat detail teknis aplikasi."
        fi
    fi

    if [ "$SEED_DIJALANKAN" = 1 ]; then
        printf '  Login           : ratih@toko.com\n'
        printf '  Password        : password\n'
    fi

    printf '\n  Tekan Ctrl+C untuk menghentikan server.\n\n'
}

buka_browser_saat_siap() {
    [ "$BUKA_BROWSER" = 1 ] || return 0
    command -v open >/dev/null 2>&1 || return 0

    (
        i=0
        while [ "$i" -lt 60 ]; do
            if curl -fs -o /dev/null "http://127.0.0.1:$PORT/up"; then
                open "http://localhost:$PORT"
                exit 0
            fi
            sleep 0.5
            i=$((i + 1))
        done
    ) >/dev/null 2>&1 &
}

tambah_path /opt/homebrew/bin
tambah_path /opt/homebrew/sbin
tambah_path /usr/local/bin
export PATH

baca_opsi "$@"
periksa_alat
pasang_cloudflared
siapkan_env
cek_env_produksi
pasang_composer
buat_app_key
siapkan_database
bangun_aset
pilih_port
mulai_tunnel
tampilkan_info
buka_browser_saat_siap

if [ "$TUNNEL" = 1 ]; then
    # Tidak pakai exec di sini: skrip perlu tetap hidup setelah "php artisan
    # serve" berhenti (mis. Ctrl+C) supaya trap hentikan_tunnel sempat
    # mematikan proses cloudflared, bukan meninggalkannya menyala di latar.
    php artisan serve --host="$HOST" --port="$PORT"
    kode=$?
    exit "$kode"
fi

exec php artisan serve --host="$HOST" --port="$PORT"
