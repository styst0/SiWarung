<?php

namespace App\Services;

class HargaJualService
{
    public function hitungOtomatis(int $hargaBeli, string $satuan): int
    {
        $pembulatan = (int) config('markup_harga.pembulatan', 500);
        [$min, $max] = $this->rentangUntuk($satuan);

        $batasBawah = $hargaBeli + $min;
        $batasAtas = $hargaBeli + $max;

        $kandidat = (int) (ceil($batasBawah / $pembulatan) * $pembulatan);

        if ($kandidat > $batasAtas) {
            $kandidat = (int) (floor($batasAtas / $pembulatan) * $pembulatan);
        }

        if ($kandidat < $hargaBeli) {
            $kandidat = (int) (ceil($hargaBeli / $pembulatan) * $pembulatan);
        }

        return $kandidat;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function rentangUntuk(string $satuan): array
    {
        $kunci = strtolower(trim($satuan));
        $alias = config('markup_harga.alias', []);
        $kunci = $alias[$kunci] ?? $kunci;

        $rentang = config('markup_harga.rentang', []);

        return $rentang[$kunci] ?? $rentang['default'] ?? [500, 1500];
    }
}
