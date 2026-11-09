<?php

namespace Database\Seeders; 

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TypeDocumentSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection('oracle_primary')->table('typeDocuments')->insert([
            ['nameType' => 'INE'],
            ['nameType' => 'Identificación Institucional'],
            ['nameType' => 'Talón de pago'],
            ['nameType' => 'Pasaporte'],
            ['nameType' => 'Cédula'],
            ['nameType' => 'Formato de Entrega'],
            ['nameType' => 'Formato de Reposición'],
            ['nameType' => 'Formato de Entrega por Fallecimiento'],
            ['nameType' => 'Formato de Pensión Alimenticia'],
            ['nameType' => 'Otro'],
        ]);
    }
}
