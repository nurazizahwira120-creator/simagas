# =====================================================================
#  Pembersih file lama - Absensi SMK Islam Assya'roniyyah
# ---------------------------------------------------------------------
#  KENAPA PERLU?
#  Meng-extract ZIP di atas project yang sudah ada hanya MENAMBAH dan
#  MENIMPA file. File yang saya RENAME / PINDAH di versi baru tidak ikut
#  terhapus, jadi versi lamanya menumpuk dan bikin bingung - bahkan bisa
#  bikin "php artisan migrate:fresh" GAGAL (lihat poin jobs table).
#
#  CARA PAKAI:
#    1. Buka PowerShell di folder root project (yang ada file "artisan")
#    2. Jalankan:  powershell -ExecutionPolicy Bypass -File bersihkan-file-lama.ps1
# =====================================================================

Write-Host ""
Write-Host "== Pembersih file lama - Absensi SMK ==" -ForegroundColor Cyan
Write-Host ""

# Konfirmasi sebelum mengeksekusi
$konfirmasi = Read-Host "Skrip ini akan menghapus beberapa file lama. Lanjutkan? (Y/T)"
if ($konfirmasi -notmatch "^[Yy]$") {
    Write-Host "Proses dibatalkan." -ForegroundColor Yellow
    exit 0
}
Write-Host ""

# Pastikan dijalankan dari root project Laravel
if (-not (Test-Path "artisan")) {
    Write-Host "GAGAL: file 'artisan' tidak ditemukan." -ForegroundColor Red
    Write-Host "Jalankan skrip ini dari folder root project (folder yang berisi 'artisan')." -ForegroundColor Red
    exit 1
}

# Daftar file/folder lama beserta penggantinya
$targets = @(
    @{ Path = "resources\views\guru";        Note = "dashboard wali kelas -> pindah ke resources\views\wali-kelas" },
    @{ Path = "resources\views\ortu";        Note = "dashboard wali murid  -> pindah ke resources\views\wali-murid" },
    @{ Path = "resources\views\superadmin";  Note = "panel super admin     -> pindah ke resources\views\super-admin" },
    @{ Path = "app\Http\Controllers\Auth\AuthenticatedSessionController.php"; Note = "diganti nama jadi AuthController.php" },
    @{ Path = "app\Models\Absensi.php";      Note = "diganti nama jadi AbsensiSiswa.php" },
    @{ Path = "database\factories\AbsensiFactory.php"; Note = "diganti nama jadi AbsensiSiswaFactory.php" },
    @{ Path = "database\migrations\2025_01_01_000003_create_absensi_table.php"; Note = "diganti 000004_create_absensi_siswa_table.php" },
    @{ Path = "database\migrations\2025_01_01_000004_create_jobs_table.php";    Note = "PENTING: nomornya pindah ke 000006. Kalau dua-duanya ada, migrate:fresh GAGAL (jobs table dibuat 2x)" }
)

$dihapus = 0
$dilewati = 0

foreach ($t in $targets) {
    if (Test-Path $t.Path) {
        try {
            Remove-Item -Path $t.Path -Recurse -Force -ErrorAction Stop
            Write-Host "  [HAPUS ] $($t.Path)" -ForegroundColor Yellow
            Write-Host "           ^ $($t.Note)" -ForegroundColor DarkGray
            $dihapus++
        } catch {
            Write-Host "  [ERROR ] $($t.Path) - $($_.Exception.Message)" -ForegroundColor Red
        }
    } else {
        Write-Host "  [aman  ] $($t.Path) (sudah tidak ada)" -ForegroundColor DarkGray
        $dilewati++
    }
}

Write-Host ""
Write-Host "Selesai: $dihapus dihapus, $dilewati memang sudah bersih." -ForegroundColor Green
Write-Host ""

# Bersihkan cache Laravel supaya route & view lama tidak nyangkut
Write-Host "Membersihkan semua cache Laravel..." -ForegroundColor Cyan
php artisan optimize:clear

Write-Host ""
Write-Host "Beres. Silakan jalankan ulang: php artisan migrate:fresh --seed" -ForegroundColor Green
Write-Host ""