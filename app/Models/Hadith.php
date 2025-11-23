<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hadith extends Model
{
	protected $table = "hadiths";
	protected $primaryKey = "id";
	public $incrementing = true;

	protected $fillable = ["book_id", "number", "arabic", "translation"];

	public function book(): BelongsTo
	{
		return $this->belongsTo(HadithBook::class, "book_id", "id");
	}
}
