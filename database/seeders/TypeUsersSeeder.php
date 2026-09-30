<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TypeUsersSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('typeUser')->insert([
            'idType' => 1,
            'typeUser' => 'Administrador'
        ]);

        DB::table('typeUser')->insert([
            'idType' => 2,
            'typeUser' => 'Gestor de Prestaciones'
        ]);

        DB::table('typeUser')->insert([
            'idType' => 3,
            'typeUser' => 'Enlace'
        ]);
    }
}
