<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TypeActa extends Seeder
{
    public function run(): void
    {
        DB::connection('oracle_primary')->table('type_actas')->insert([
            ['nameType' => 'Acta Entrega'],
            ['nameType' => 'Acta Cierre'],
        ]);
    }
}
