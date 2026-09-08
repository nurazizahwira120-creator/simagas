# =====================================================================
#  Pemasang Update - Absensi SMK Islam Assya'roniyyah
# ---------------------------------------------------------------------
#  Menyalin update dengan benar: file lama yang sudah dihapus/dipindah
#  ikut terhapus, cache dibersihkan, dan dibuatkan cadangan dulu.
#
#  CARA PAKAI
#    1. Extract zip dari chat ke folder mana saja, mis. C:\Users\Anda\Downloads\update
#    2. Buka PowerShell di folder root project (yang ada file "artisan")
#    3. Jalankan:
#         powershell -ExecutionPolicy Bypass -File pasang-update.ps1 -Sumber "C:\Users\Anda\Downloads\update"
# =====================================================================
 
param(
    [Parameter(Mandatory = $true)]
    [string]$Sumber
)
 
$ErrorActionPreference = 'Stop'
 
function Tulis($teks, $warna = 'Gray') { Write-Host $teks -ForegroundColor $warna }
 
Tulis ""
Tulis "== Pemasang Update - Absensi SMK ==" Cyan
Tulis ""
 
# ---------------------------------------------------------------------
# 1. Pemeriksaan keselamatan
# ---------------------------------------------------------------------
if (-not (Test-Path 'artisan')) {
    Tulis "GAGAL: file 'artisan' tidak ada di folder ini." Red
    Tulis "Jalankan skrip ini dari folder ROOT project Laravel Anda." Red
    exit 1
}
 
if (-not (Test-Path $Sumber -PathType Container)) {
    Tulis "GAGAL: folder sumber tidak ditemukan: $Sumber" Red
    Tulis "Pastikan zip-nya sudah di-EXTRACT dulu (bukan menunjuk ke file .zip)." Red
    exit 1
}
 
# Folder sumber harus berisi struktur yang kita harapkan
if (-not (Test-Path (Join-Path $Sumber 'app'))) {
    Tulis "GAGAL: folder sumber tidak berisi 'app'." Red
    Tulis "Kemungkinan Anda menunjuk ke folder pembungkus. Coba masuk satu level lagi." Red
    exit 1
}
 
Tulis "Sumber : $Sumber" DarkGray
Tulis "Tujuan : $(Get-Location)" DarkGray
Tulis ""
 
# ---------------------------------------------------------------------
# 2. Cadangan
# ---------------------------------------------------------------------
$stempel  = Get-Date -Format 'yyyyMMdd-HHmmss'
$cadangan = "_cadangan-$stempel"
 
Tulis "[1/5] Membuat cadangan ke $cadangan ..." Cyan
New-Item -ItemType Directory -Path $cadangan -Force | Out-Null
foreach ($item in @('app', 'database', 'resources\views', 'routes', 'bootstrap\app.php')) {
    if (Test-Path $item) {
        $tujuan = Join-Path $cadangan $item
        New-Item -ItemType Directory -Path (Split-Path $tujuan -Parent) -Force | Out-Null
        Copy-Item $item $tujuan -Recurse -Force
    }
}
Tulis "      selesai." DarkGray
 
# ---------------------------------------------------------------------
# 3. Sinkronisasi
# ---------------------------------------------------------------------
# CATATAN PENTING soal /MIR (mirror):
#   /MIR menghapus file di tujuan yang tidak ada di sumber. Itulah yang
#   memperbaiki masalah "file lama tertinggal". Tapi /MIR hanya AMAN untuk
#   folder yang paketnya kirim UTUH.
#
#   - app\, database\, resources\views\  -> dikirim utuh, aman di-mirror.
#   - resources\ (induk)  -> TIDAK di-mirror: berisi css\ dan js\ milik Anda.
#   - routes\             -> TIDAK di-mirror: berisi console.php milik Anda.
#   - bootstrap\          -> TIDAK di-mirror: berisi providers.php dan cache\.
#   Dua yang terakhir disalin per file saja.
 
Tulis "[2/5] Menyinkronkan folder (file lama ikut dibersihkan) ..." Cyan
 
$folderMirror = @('app', 'database', 'resources\views')
foreach ($f in $folderMirror) {
    $src = Join-Path $Sumber $f
    if (-not (Test-Path $src)) {
        Tulis "      lewati (tidak ada di sumber): $f" DarkYellow
        continue
    }
    robocopy $src $f /MIR /NFL /NDL /NJH /NJS /NP | Out-Null
    # robocopy: kode 0-7 = sukses, 8+ = error
    if ($LASTEXITCODE -ge 8) {
        Tulis "GAGAL menyalin $f (robocopy kode $LASTEXITCODE)" Red
        Tulis "Cadangan Anda ada di: $cadangan" Yellow
        exit 1
    }
    Tulis "      mirror: $f" DarkGray
}
 
Tulis "[3/5] Menyalin file tunggal ..." Cyan
foreach ($file in @('routes\web.php', 'bootstrap\app.php')) {
    $src = Join-Path $Sumber $file
    if (Test-Path $src) {
        Copy-Item $src $file -Force
        Tulis "      salin : $file" DarkGray
    }
}
 
# ---------------------------------------------------------------------
# 4. Bersihkan cache
# ---------------------------------------------------------------------
Tulis "[4/5] Membersihkan cache ..." Cyan
Remove-Item 'storage\framework\views\*.php' -Force -ErrorAction SilentlyContinue
php artisan view:clear   2>&1 | Out-Null
php artisan route:clear  2>&1 | Out-Null
php artisan config:clear 2>&1 | Out-Null
Tulis "      selesai." DarkGray
 
# ---------------------------------------------------------------------
# 5. Cek migration yang belum dijalankan
# ---------------------------------------------------------------------
Tulis "[5/5] Memeriksa migration ..." Cyan
$statusMigrasi = php artisan migrate:status 2>&1 | Out-String
$belumJalan = ([regex]::Matches($statusMigrasi, 'Pending')).Count
 
Tulis ""
Tulis "================= SELESAI =================" Green
Tulis "Cadangan tersimpan di: $cadangan" DarkGray
Tulis "(hapus manual kalau update-nya sudah terbukti aman)" DarkGray
Tulis ""
 
if ($belumJalan -gt 0) {
    Tulis "PERHATIAN: ada $belumJalan migration yang belum dijalankan." Yellow
    Tulis "Jalankan salah satu:" Yellow
    Tulis "   php artisan migrate                 # pertahankan data" Yellow
    Tulis "   php artisan migrate:fresh --seed    # bangun ulang + data contoh" Yellow
    Tulis ""
    Tulis "Kalau memilih migrate:fresh, TUTUP dulu semua tab aplikasi di browser," Yellow
    Tulis "lalu buka lagi dengan Ctrl+Shift+R - kalau tidak, akan muncul 419 Page Expired." Yellow
} else {
    Tulis "Tidak ada migration tertunda. Silakan buka aplikasinya." Green
    Tulis "Tekan Ctrl+Shift+R di browser supaya tidak memakai cache lama." DarkGray
}
Tulis ""
 