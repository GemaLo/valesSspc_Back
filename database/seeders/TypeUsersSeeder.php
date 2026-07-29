<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TypeUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('typeUser')->insert([
            'idType' => 1,
            'typeUser' => 'COORDINADOR'
        ]);

                DB::table('typeUser')->insert([
            'idType' => 2,
            'typeUser' => 'PRESTACIONES'
        ]);

                DB::table('typeUser')->insert([
            'idType' => 3,
            'typeUser' => 'ENLACE'
        ]);
    }
}
