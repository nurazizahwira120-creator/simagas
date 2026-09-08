<?php

namespace App\Models;

use App\Enums\StatusAkun;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'no_hp',
        'alamat',
        'foto',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => StatusAkun::class,
        ];
    }

    /**
     * URL foto profil yang siap dipasang di <img src="...">, atau null kalau
     * belum pernah mengunggah foto.
     *
     * Dibuat sebagai accessor supaya SATU tempat saja yang tahu cara mengubah
     * jalur tersimpan ("profile_photos/abc.jpg") menjadi URL — dipakai halaman
     * Profil, sidebar, topbar, dan Kartu Identitas. Kalau tiap tempat merakit
     * URL-nya sendiri, cukup satu yang lupa diubah saat penyimpanannya pindah.
     */
    public function getFotoUrlAttribute(): ?string
    {
        if (! $this->foto) {
            return null;
        }

        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($this->foto);

        // Bagian skema+host DIBUANG, sehingga yang tersisa jalur relatif
        // seperti "/storage/profile_photos/abc.jpg".
        //
        // Storage::url() merakit URL lengkap dari APP_URL di .env. Kalau
        // APP_URL tidak sama persis dengan alamat yang benar-benar dipakai
        // membuka aplikasi — dan di Laragon itu keadaan yang lumrah, mis.
        // APP_URL masih http://localhost padahal situsnya dibuka di
        // http://absensi-smk.test — maka SEMUA foto profil tampil rusak,
        // dengan gejala yang menyesatkan: seolah unggahannya gagal padahal
        // berkasnya ada. Jalur relatif selalu menunjuk ke host yang sedang
        // dibuka, jadi masalah itu tidak bisa terjadi.
        $jalur = parse_url($url, PHP_URL_PATH);

        return $jalur ?: $url;
    }

    /**
     * Kelas yang ia ampu, jika role-nya wali_kelas.
     */
    public function kelasWali(): HasOne
    {
        return $this->hasOne(Kelas::class, 'wali_kelas_id');
    }

    /**
     * Anak (siswa) yang ia wali-i, jika role-nya wali_murid.
     * hasMany karena satu wali murid bisa punya lebih dari satu anak
     * yang bersekolah di sini.
     */
    public function siswaWali(): HasMany
    {
        return $this->hasMany(Siswa::class, 'wali_murid_id');
    }

    /**
     * Data kepegawaian terkait (NIP, jabatan, dll), jika akun ini mewakili
     * seorang pegawai/staf sekolah. Opsional — tidak semua akun user
     * (mis. wali_murid) punya baris pegawai.
     */
    public function pegawai(): HasOne
    {
        return $this->hasOne(Pegawai::class, 'user_id');
    }
}
