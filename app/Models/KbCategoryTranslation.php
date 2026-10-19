<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A category's name and description in a language other than the default. */
class KbCategoryTranslation extends Model
{
    protected $fillable = ['kb_category_id', 'language', 'name', 'description'];
}
