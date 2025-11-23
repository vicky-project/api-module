<?php

namespace Modules\Api\Services\Api;

use Modules\Api\Models\AsmaulHusna;
use Modules\Api\Core\BaseApiService;

class AsmaulHusnaService extends BaseApiService
{
	protected string $name = "asmaul_husna";
	protected bool $requiresAuth = false;
	protected bool $readonly = true;

	public function index()
	{
		return AsmaulHusna::withCount("verses")
			->select(
				"id",
				"number",
				"latine",
				"arabic",
				"meaning",
				"description",
				"found"
			)
			->orderBy("number")
			->get();
	}

	public function show($number)
	{
		$lang = $this->request->input("lang", "id");

		$asma = AsmaulHusna::where("number", $number)
			->with(["verses.surah"])
			->first();

		if (!$asma) {
			return [];
		}

		$verses = $asma->verses->map(function ($verse) {
			return [
				"id" => $verse->id,
				"surah_number" => $verse->surah_number,
				"surah_name" => $verse->surah->name,
				"verse_number" => $verse->verse_number,
				"arabic_text" => $verse->arabic_text,
				"references" => "{$verse->surah_number}:{$verse->verse_number}",
			];
		});

		$response = [
			"id" => $asma->id,
			"number" => $asma->number,
			"latine" => $asma->latine,
			"arabic" => $asma->arabic,
			"meaning" => $asma->getMeaning($lang),
			"description" => $asma->getDescription($lang),
			"found" => $asma->found,
			"references" => $verses,
			"verses" => $asma->verses->map(function ($verse) {
				return [
					"id" => $verse->id,
					"surah_number" => $verse->surah_number,
					"surah_name" => $verse->surah->name,
					"verse_number" => $verse->verse_number,
					"arabic_text" => $verse->arabic_text,
					"latin_text" => $verse->latin_text,
					"translation" => $verse->translation,
					"audio" => $verse->audio,
					"surah" => $verse->surah,
				];
			}),
		];

		return $response;
	}
}
