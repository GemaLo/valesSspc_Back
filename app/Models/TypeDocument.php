<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeDocument extends Model
{
    protected $connection = 'oracle_primary';
    protected $table = 'typeDocuments';

    protected $primaryKey = 'idTypeDocument';
    protected $fillable = ['nameType', 'descriptionType'];
}
