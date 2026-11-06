<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class typePerson extends Seeder
{
    public function run(): void
    {
        \DB::table('typePerson')->insert([
            ['TypePerson' => 'Beneficiario'],
            ['TypePerson' => 'Persona Autorizada'],
        ]);
    }
}
