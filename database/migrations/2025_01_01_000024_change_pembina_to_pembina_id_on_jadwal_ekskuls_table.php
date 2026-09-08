<?php
// Dibuat dengan:  php artisan make:migration change_pembina_to_pembina_id_on_jadwal_ekskuls_table --table=jadwal_ekskuls

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `pembina` (teks bebas) -> `pembina_id` (relasi ke tabel pegawai).
 *
 * ============ KENAPA KOLOM LAMANYA TIDAK DIHAPUS, TAPI DIGANTI NAMA ============
 * Menghapus `pembina` begitu saja akan MENGHILANGKAN nama pembina untuk
 * setiap baris yang namanya tidak persis sama dengan data di tabel pegawai —
 * "Pak Andi" vs "Andi Saputra, S.Pd.", atau pelatih silat dari luar yang
 * memang tidak punya baris pegawai sama sekali. Kehilangan itu tidak melempar
 * error apa pun: jadwalnya tetap ada, hanya pembinanya jadi kosong, dan tidak
 * ada cara mengembalikannya.
 *
 * Karena itu kolomnya DIGANTI NAMA jadi `pembina_luar` dan tetap disimpan.
 * Yang berhasil dicocokkan otomatis mengisi `pembina_id`; yang tidak, namanya
 * masih terbaca di halaman (lihat JadwalEkskul::namaPembina()) dengan penanda
 * bahwa ia belum tertaut ke data pegawai, sehingga admin tahu mana yang perlu
 * dipilih ulang lewat dropdown.
 * ==============================================================================
 *
 * nullOnDelete(), BUKAN cascadeOnDelete(): menghapus data seorang pegawai
 * tidak boleh ikut menghapus jadwal ekskulnya. Yang hilang cukup penunjuk
 * pembinanya, jadwal kegiatannya tetap ada untuk ditugaskan ke orang lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         | Setiap langkah dijaga hasColumn() supaya migration ini bisa
         | DIJALANKAN ULANG dengan aman.
         |
         | Itu bukan kehati-hatian berlebihan: kalau langkah ketiga gagal di
         | tengah jalan (koneksi putus, timeout hosting), dua langkah pertama
         | sudah terlanjur menempel di tabel TAPI barisnya belum tercatat di
         | tabel `migrations`. Tanpa penjagaan ini, percobaan berikutnya mati
         | dengan "duplicate column name: pembina_id" dan tidak ada jalan maju
         | maupun mundur selain menyunting skema dengan tangan.
         */
        if (! Schema::hasColumn('jadwal_ekskuls', 'pembina_id')) {
            Schema::table('jadwal_ekskuls', function (Blueprint $table) {
                $table->foreignId('pembina_id')->nullable()->after('hari')
                    ->constrained('pegawai')->nullOnDelete();
            });
        }

        // Ganti nama dulu, baru isi — supaya pembacaan di bawah tidak
        // bergantung pada kolom yang namanya sedang berubah.
        if (Schema::hasColumn('jadwal_ekskuls', 'pembina')
            && ! Schema::hasColumn('jadwal_ekskuls', 'pembina_luar')) {
            Schema::table('jadwal_ekskuls', function (Blueprint $table) {
                $table->renameColumn('pembina', 'pembina_luar');
            });
        }

        /*
         | JADIKAN NULLABLE — dan ini WAJIB, bukan kerapian.
         |
         | Kolom `pembina` dibuat NOT NULL di migration 000023, dan
         | renameColumn membawa sifat itu ikut serta. Begitu satu nama
         | berhasil dicocokkan, langkah berikutnya mengosongkan teks lamanya
         | (pembina_luar = null) dan database menolaknya:
         |
         |   SQLSTATE[23000]: NOT NULL constraint failed:
         |   jadwal_ekskuls.pembina_luar
         |
         | Migrationnya berhenti di tengah — sebagian baris sudah tertaut,
         | sebagian belum — dan barisnya tidak tercatat di tabel `migrations`.
         | Yang bikin repot: kalau tidak ada satu pun nama yang cocok,
         | migrationnya lolos mulus, jadi kesalahan ini justru MUNCUL DI
         | SERVER yang datanya rapi, bukan saat dicoba di komputer sendiri.
         */
        Schema::table('jadwal_ekskuls', function (Blueprint $table) {
            $table->string('pembina_luar', 100)->nullable()->change();
        });

        $this->cocokkanNamaPembina();
    }

    /**
     * Isi pembina_id dari nama lama, dicocokkan tanpa memedulikan huruf besar
     * kecil dan spasi berlebih. Dilakukan di PHP, bukan satu UPDATE ... JOIN:
     * sintaks join di dalam UPDATE berbeda antara MySQL dan SQLite, dan
     * migrasi yang hanya jalan di salah satunya akan gagal justru saat diuji.
     */
    private function cocokkanNamaPembina(): void
    {
        $pegawai = DB::table('pegawai')->select('id', 'nama')->get();

        if ($pegawai->isEmpty()) {
            return;
        }

        $peta = [];

        foreach ($pegawai as $p) {
            $kunci = mb_strtolower(trim((string) $p->nama));

            // Nama kembar: biarkan kosong daripada menebak. Menautkan jadwal
            // ke orang yang salah lebih buruk daripada membiarkan admin
            // memilih sendiri sekali.
            $peta[$kunci] = array_key_exists($kunci, $peta) ? null : $p->id;
        }

        DB::table('jadwal_ekskuls')
            ->select('id', 'pembina_luar')
            ->orderBy('id')
            ->chunkById(200, function ($baris) use ($peta) {
                foreach ($baris as $b) {
                    $kunci = mb_strtolower(trim((string) $b->pembina_luar));

                    if ($kunci === '' || empty($peta[$kunci])) {
                        continue;
                    }

                    DB::table('jadwal_ekskuls')
                        ->where('id', $b->id)
                        ->update(['pembina_id' => $peta[$kunci], 'pembina_luar' => null]);
                }
            });
    }

    public function down(): void
    {
        // Kembalikan nama pembina ke kolom teks SEBELUM relasinya dibuang,
        // supaya rollback tidak ikut menghapus informasinya.
        if (Schema::hasColumn('jadwal_ekskuls', 'pembina_luar')
            && ! Schema::hasColumn('jadwal_ekskuls', 'pembina')) {
            Schema::table('jadwal_ekskuls', function (Blueprint $table) {
                $table->renameColumn('pembina_luar', 'pembina');
            });
        }

        if (Schema::hasColumn('jadwal_ekskuls', 'pembina_id')) {
            foreach (DB::table('jadwal_ekskuls')->whereNotNull('pembina_id')->get(['id', 'pembina_id']) as $b) {
                $nama = DB::table('pegawai')->where('id', $b->pembina_id)->value('nama');

                DB::table('jadwal_ekskuls')->where('id', $b->id)->update(['pembina' => $nama ?: '-']);
            }

            // Sisa baris yang tidak punya nama sama sekali diisi '-', supaya
            // kolomnya tidak meninggalkan NULL di tabel yang aslinya NOT NULL.
            DB::table('jadwal_ekskuls')->whereNull('pembina')->update(['pembina' => '-']);

            /*
             | Kolom pembina_id SENGAJA TIDAK dibuang di SQLite.
             |
             | SQLite menolak "ALTER TABLE ... DROP COLUMN" untuk kolom yang
             | disebut dalam definisi kunci asing, dan ia juga tidak bisa
             | membuang kunci asingnya tersendiri:
             |
             |   error in table jadwal_ekskuls after drop column:
             |   unknown column "pembina_id" in foreign key definition
             |
             | Memaksanya berarti membangun ulang seluruh tabel di dalam
             | down() — dan pembangunan ulang itu sendiri berisiko kehilangan
             | tipe kolom serta indeks. Yang tertinggal cuma satu kolom
             | tambahan yang seluruh isinya sudah disalin kembali ke `pembina`
             | tepat di atas, jadi tidak ada informasi yang hilang, dan
             | penjagaan hasColumn() di up() membuat migrate berikutnya tetap
             | jalan mulus.
             |
             | Di MySQL (yang dipakai di hosting) kolomnya benar-benar dibuang
             | setelah kunci asingnya dilepas.
             */
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('jadwal_ekskuls', function (Blueprint $table) {
                    $table->dropForeign(['pembina_id']);
                    $table->dropColumn('pembina_id');
                });
            }
        }
    }
};
