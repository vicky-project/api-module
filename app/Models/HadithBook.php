<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HadithBook extends Model
{
	protected $table = "hadith_books";
	protected $primaryKey = "id";
	public $incrementing = false;
	protected $keyType = "string";

	protected $fillable = ["id", "name", "total_hadiths"];

	public function hadiths(): HasMany
	{
		return $this->hasMany(Hadith::class, "book_id", "id");
	}
}
