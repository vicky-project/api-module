<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class QuranVerse extends Model
{
	protected $table = "quran_verses";
	protected $primaryKey = "id";
	public $incrementing = true;

	protected $with = ["surah"];

	/**
	 * The attributes that are mass assignable.
	 */
	protected $fillable = [
		"surah_id",
		"surah_number",
		"verse_number",
		"arabic_text",
		"latin_text",
		"translation",
		"audio",
	];

	protected function audio(): Attribute
	{
		return Attribute::make(
			get: fn($value) => json_decode($value, true),
			set: fn($value) => json_encode($value)
		);
	}

	public function surah(): BelongsTo
	{
		return $this->belongsTo(QuranSurah::class, "surah_id");
	}

	public function asmaulHusnas(): BelongsToMany
	{
		return $this->belongsToMany(
			AsmaulHusna::class,
			"asmaul_husna_verse",
			"quran_verse_id",
			"asmaul_husna_id"
		);
	}
}
