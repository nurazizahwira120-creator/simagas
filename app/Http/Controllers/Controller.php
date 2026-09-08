<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Prefix nama rute panel milik role user yang sedang login (mis.
     * "kepsek" atau "super-admin", lihat UserRole::routePrefix()).
     *
     * Beberapa controller (Kelas/Siswa/User/Pegawai) dipakai bersama oleh
     * lebih dari satu grup rute yang isinya identik tapi prefix-nya beda
     * (contoh: panel Kepsek di /kepsek/* dan panel Super Admin di
     * /super-admin/* sama-sama memakai KelasController & view yang sama).
     * Helper ini dipakai saat redirect()->route(...) supaya controller
     * selalu kembali ke prefix milik role yang sedang login, bukan
     * hardcode ke satu prefix saja (yang akan 403 untuk role lainnya).
     */
    protected function panelPrefix(): string
    {
        return request()->user()?->role->routePrefix() ?? '';
    }
}
