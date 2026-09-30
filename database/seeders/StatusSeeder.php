<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('status')->insert([
            'idStatus' => 1,
            'status' => 'Pendiente'
        ]);

        DB::table('status')->insert([
            'idStatus' => 2,
            'status' => 'Entregado'
        ]);
    }
}
