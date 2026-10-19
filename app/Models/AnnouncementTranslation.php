<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An announcement's title and text in a language other than the default. */
class AnnouncementTranslation extends Model
{
    protected $fillable = ['announcement_id', 'language', 'title', 'announcement'];
}
