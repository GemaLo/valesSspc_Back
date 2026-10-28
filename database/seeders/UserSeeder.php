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
            'numEmpleado' => '727201',
            'firstName' => 'GEMA ELIZABETH',
            'lastName' => 'LOPERENA',
            'middleName' => 'GUTIERREZ',
            'nivel' => 'O23',
            'unidad' => '143',
            'active' => '1',
            'idType' => '1',
            'origen' => 'SSPC',
            'email' => 'gema.loperena@sspc.gob.mx',
            'must_change_password' => '1',
            'password' => Hash::make('password')
        ]);
        DB::table('users')->insert([
            'numEmpleado' => '724778',
            'firstName' => 'ANA LAURA',
            'lastName' => 'RODRIGUEZ',
            'middleName' => 'FLORES',
            'nivel' => 'M33',
            'unidad' => '142',
            'active' => '1',
            'idType' => '2',
            'origen' => 'SSPC',
            'email' => 'ana.rodriguezf@sspc.gob.mx',
            'must_change_password' => '1',
            'password' => Hash::make('password')
        ]);
        DB::table('users')->insert([
            'numEmpleado' => '711126',
            'firstName' => 'HECTOR GABRIEL',
            'lastName' => 'SERRALDE',
            'middleName' => 'VALENCIA',
            'nivel' => 'N31',
            'unidad' => '142',
            'active' => '1',
            'idType' => '3',
            'origen' => 'SSPC',
            'email' => 'hector.serralde@sspc.gob.mx',
            'must_change_password' => '1',
            'password' => Hash::make('password')
        ]);
    }
}
