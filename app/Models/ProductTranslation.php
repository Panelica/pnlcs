<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A product's description in a language other than the default. */
class ProductTranslation extends Model
{
    protected $fillable = ['product_id', 'language', 'description'];
}
