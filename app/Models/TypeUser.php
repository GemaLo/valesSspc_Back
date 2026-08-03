<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeUser extends Model
{
    protected $connection = 'oracle_primary';
    protected $table = 'typeUser'; 
    protected $primaryKey = 'idType';

    protected $fillable = ['idType', 'typeUser']; 
}