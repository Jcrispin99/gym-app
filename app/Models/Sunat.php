<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sunat extends Model
{
    protected $fillable = [
        'token_apiperu',
        'token_facturacion',
    ];
}
