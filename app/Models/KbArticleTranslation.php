<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An article's title and text in a language other than the default. */
class KbArticleTranslation extends Model
{
    protected $fillable = ['kb_article_id', 'language', 'title', 'article'];
}
