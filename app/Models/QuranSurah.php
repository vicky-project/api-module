<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class QuranSurah extends Model
{
	protected $table = "quran_surahs";
	protected $primaryKey = "id";
	public $incrementing = true;

	/**
	 * The attributes that are mass assignable.
	 */
	protected $fillable = [
		"number",
		"name",
		"name_latin",
		"number_of_verses",
		"place",
		"meaning",
		"description",
		"audio_full",
	];

	protected function audioFull(): Attribute
	{
		return Attribute::make(
			get: fn($value) => $value ? json_decode($value, true) : [],
			set: fn($value) => is_string($value) ? $value : json_encode($value)
		);
	}

	public function verses(): HasMany
	{
		return $this->hasMany(QuranVerse::class, "surah_id");
	}
}
