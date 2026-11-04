<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $connection = 'oracle_primary';
    protected $table = 'documents';

    protected $primaryKey = 'idDocument';
    protected $fillable = ['document', 'idTypeDocument', 'idRegister', 'status'];
}
