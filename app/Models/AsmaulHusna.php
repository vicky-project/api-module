<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AsmaulHusna extends Model
{
	protected $table = "asmaul_husnas";
	protected $fillable = [
		"number",
		"latine",
		"arabic",
		"meaning",
		"description",
		"found",
	];

	protected $casts = [
		"number" => "integer",
		"meaning" => "array",
		"description" => "array",
	];

	protected $with = ["verses"];

	public function verses(): BelongsToMany
	{
		return $this->belongsToMany(
			QuranVerse::class,
			"asmaul_husna_verse",
			"asmaul_husna_id",
			"quran_verse_id"
		);
	}

	public function getMeaning($lang)
	{
		$meanings = $this->meaning;

		if (isset($meanings[$lang])) {
			return $meanings[$lang];
		}

		if (isset($meanings["en"])) {
			return $meanings["en"];
		}

		return reset($meanings);
	}

	public function getDescription($lang)
	{
		$descriptions = $this->description;

		if (isset($descriptions[$lang])) {
			return $descriptions[$lang];
		}

		if (isset($descriptions["en"])) {
			return $descriptions["en"];
		}

		return reset($descriptions) ?: "";
	}
}
