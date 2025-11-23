<?php

namespace Modules\Api\Services\Api;

use Modules\Api\Core\BaseApiService;
use Illuminate\Support\Facades\Cache;
use Modules\Api\Models\{AsmaulHusna, QuranSurah, QuranVerse};
use Modules\Api\Traits\Utils;

class QuranService extends BaseApiService
{
	use Utils;

	protected string $name = "quran";
	protected bool $requiresAuth = false;
	protected bool $readonly = true;

	public function surahs()
	{
		$surahs = $this->getAllSurahs($this->getPaginationParams($this->request));

		return [
			"surahs" => $surahs->items(),
			"pagination" => [
				"total" => $surahs->total(),
				"per_page" => $surahs->perPage(),
				"current_page" => $surahs->currentPage(),
				"last_page" => $surahs->lastPage(),
			],
		];
	}

	public function surah(int $surahNumber)
	{
		return QuranSurah::where("number", $surahNumber)->firstOrFail();
	}

	public function verses(int $surahNumber)
	{
		$pagination = $this->getPaginationParams($this->request);

		return QuranVerse::with("surah")
			->where("surah_number", $surahNumber)
			->orderBy("verse_number")
			->paginate($pagination["per_page"], ["*"], "page", $pagination["page"]);
	}

	public function verse(int $surahNumber, int $verseNumber)
	{
		return QuranVerse::with("surah")
			->where("surah_number", $surahNumber)
			->where("verse_number", $verseNumber)
			->firstOrFail();
	}

	private function getAllSurahs($params)
	{
		return QuranSurah::paginate(
			$params["per_page"],
			["*"],
			"page",
			$params["page"]
		);
	}
}
