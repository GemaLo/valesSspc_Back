<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            'firstName' => 'GEMA ELIZABETH',
            'lastName' => 'LOPERENA',
            'middleName' => 'GUTIERREZ',
            'nivel' => 'O23',
            'unidad' => '143',
            'active' => '1',
            'idType' => '1',
            'email' => 'gema.loperena@sspc.gob.mx',
            'password' => Hash::make('password')
        ]);
        DB::table('users')->insert([
            'firstName' => 'ANA LAURA',
            'lastName' => 'RODRIGUEZ',
            'middleName' => 'FLORES',
            'nivel' => 'M33',
            'unidad' => '142',
            'active' => '1',
            'idType' => '2',
            'email' => 'ana.rodriguezf@sspc.gob.mx',
            'password' => Hash::make('password')
        ]);
        DB::table('users')->insert([
            'firstName' => 'HECTOR GABRIEL',
            'lastName' => 'SERRALDE',
            'middleName' => 'VALENCIA',
            'nivel' => 'N31',
            'unidad' => '142',
            'active' => '1',
            'idType' => '3',
            'email' => 'hector.serralde@sspc.gob.mx',
            'password' => Hash::make('password')
        ]);
    }
}
