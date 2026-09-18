<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public static function forBarang(string $namaBarang, int $diminta, int $tersedia): self
    {
        return new self(
            "Stok \"{$namaBarang}\" tidak cukup. Diminta {$diminta}, tersedia {$tersedia}."
        );
    }
}
