import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';
import path from 'node:path';

/*
 | Salinan cadangan aset: public/simagas.css & public/simagas.js
 |
 | Kenapa ada: @vite() MELEMPAR 500 (VireManifestNotFoundException) begitu
 | public/build/manifest.json tidak ada. Artinya satu folder yang lupa
 | tersalin = SELURUH aplikasi mati, termasuk halaman login. Itu kegagalan
 | yang jauh terlalu keras untuk masalah sekecil "berkas belum dipindah".
 |
 | Karena itu hasil build juga ditulis ke dua berkas bernama TETAP langsung
 | di public/. Nama tetap (tanpa hash) membuatnya bisa disalin manual seperti
 | logo.png, dan head-assets.blade.php memakainya sebagai cadangan kalau
 | manifest Vite tidak ditemukan. Kalau keduanya hilang pun halaman tetap
 | terbuka — cuma tampil polos, bukan 500.
 |
 | Berjalan otomatis setiap `npm run build`, jadi cadangannya tidak pernah
 | ketinggalan dari hasil build utama.
 */
function salinanCadangan() {
    return {
        name: 'simagas-salinan-cadangan',
        apply: 'build',
        closeBundle() {
            const dir = path.resolve('public/build/assets');
            if (!fs.existsSync(dir)) return;

            for (const berkas of fs.readdirSync(dir)) {
                const ext = path.extname(berkas);
                if (ext !== '.css' && ext !== '.js') continue;
                fs.copyFileSync(
                    path.join(dir, berkas),
                    path.resolve('public', 'simagas' + ext),
                );
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),

        // Tailwind v4 dipasang sebagai PLUGIN VITE, bukan lewat PostCSS.
        // TailAdmin Pro aslinya memakai webpack + @tailwindcss/postcss; jalur
        // itu tidak dipakai di sini karena Laravel sudah membawa Vite, dan
        // menjalankan dua bundler berdampingan hanya menambah satu hal lagi
        // yang bisa rusak tanpa memberi apa pun.
        tailwindcss(),

        salinanCadangan(),
    ],
});
