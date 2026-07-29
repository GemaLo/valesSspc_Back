<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'id_user' => 1,
            'firstName' => 'HECTOR GABRIEL',
            'lastName' => 'SERRALDE',
            'middleName' => 'VALENCIA',
            'nivel' => 'N31',
            'unidad' => '142',
            'active' => '1',
            //   'managmente' => '',
            'idType' => '1',
            'email' => 'hector.serralde@sspc.gob.mx',
            'password' => Hash::make('password')
        ]);
    }
}
