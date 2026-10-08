#!/bin/bash
# =============================================================================
# Menautkan berkas statis folder web (public_html) ke folder public/ hasil
# `git pull`, supaya pembaruan langsung tampil tanpa menyalin manual.
#
# LATAR BELAKANG
#   Di cPanel ini, website dilayani dari ~/public_html, sedangkan `git pull`
#   memperbarui ~/backend_simagas/public. Berkas statis di public_html adalah
#   SALINAN dari awal pemasangan, jadi tidak pernah ikut terbarui. Itu yang
#   membuat CSS lama tetap tampil (pita merah "CSS versi lama"), dan
#   offline.html di server sampai sekarang masih versi lama.
#
# CARA PAKAI (di Terminal cPanel)
#   bash ~/backend_simagas/scripts/tautkan-public.sh --uji-coba   # lihat dulu
#   bash ~/backend_simagas/scripts/tautkan-public.sh              # jalankan
#   Folder web lain:  bash .../tautkan-public.sh ~/nama_folder_web
#
# YANG DIKERJAKAN
#   - Setiap BERKAS di backend public/ diganti tautan (symlink) di public_html.
#   - Berkas lama yang tergantikan DIPINDAH ke ~/public_html_lama_<waktu>/,
#     tidak dihapus — bisa dikembalikan kapan saja.
#   - TIDAK menyentuh: index.php & .htaccess (milik public_html sendiri,
#     menunjuk ke backend), storage, build, error_log, hot.
#   - Folder tidak pernah dipindah/ditimpa; hanya berkas di dalamnya. Berkas
#     yang ada di public_html tapi tidak ada di backend (mis. logo yang
#     diunggah manual) dibiarkan apa adanya.
#   - Aman dijalankan berulang: yang sudah tertaut dilewati.
# =============================================================================
set -euo pipefail

UJI_COBA=0
TUJUAN="$HOME/public_html"
for arg in "$@"; do
    case "$arg" in
        --uji-coba) UJI_COBA=1 ;;
        *) TUJUAN="$arg" ;;
    esac
done

ASAL="$(cd "$(dirname "$0")/.." && pwd)/public"
CADANGAN="$HOME/public_html_lama_$(date +%Y%m%d_%H%M%S)"

if [ ! -d "$ASAL" ] || [ ! -d "$TUJUAN" ]; then
    echo "Folder tidak ditemukan. ASAL=$ASAL TUJUAN=$TUJUAN" >&2
    exit 1
fi

if [ "$(readlink -f "$ASAL")" = "$(readlink -f "$TUJUAN")" ]; then
    echo "public_html dan backend public adalah folder yang sama — tidak ada yang perlu ditautkan."
    exit 0
fi

ditautkan=0; dilewati=0; dicadangkan=0

while IFS= read -r -d '' sumber; do
    relatif="${sumber#"$ASAL"/}"

    case "$relatif" in
        index.php|.htaccess|error_log|hot|storage|storage/*|build|build/*) continue ;;
    esac

    sasaran="$TUJUAN/$relatif"

    if [ -L "$sasaran" ] && [ "$(readlink -f "$sasaran")" = "$(readlink -f "$sumber")" ]; then
        dilewati=$((dilewati + 1))
        continue
    fi

    if [ "$UJI_COBA" = 1 ]; then
        if [ -e "$sasaran" ] || [ -L "$sasaran" ]; then
            echo "[akan diganti] $relatif"
        else
            echo "[akan dibuat]  $relatif"
        fi
        continue
    fi

    mkdir -p "$(dirname "$sasaran")"

    if [ -e "$sasaran" ] || [ -L "$sasaran" ]; then
        mkdir -p "$CADANGAN/$(dirname "$relatif")"
        mv "$sasaran" "$CADANGAN/$relatif"
        dicadangkan=$((dicadangkan + 1))
    fi

    ln -s "$sumber" "$sasaran"
    echo "[ditautkan]    $relatif"
    ditautkan=$((ditautkan + 1))
done < <(find "$ASAL" -type f -print0)

if [ "$UJI_COBA" = 1 ]; then
    echo "Uji coba selesai — belum ada yang diubah."
else
    echo "Selesai: $ditautkan ditautkan, $dilewati sudah tertaut sebelumnya."
    [ "$dicadangkan" -gt 0 ] && echo "Berkas lama ($dicadangkan) disimpan di: $CADANGAN"
fi
