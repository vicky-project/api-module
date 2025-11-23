<?php

namespace Modules\Api\Services\Api;

use Modules\Api\Models\HadithBook;
use Modules\Api\Models\Hadith;
use Modules\Api\Core\BaseApiService;

class HadithService extends BaseApiService
{
	protected string $name = "hadith";
	protected bool $requiresAuth = false;
	protected bool $readonly = true;

	public function books()
	{
		return HadithBook::select(["id", "name", "total_hadiths"])->get();
	}

	public function book(string $bookId)
	{
		return HadithBook::findOrFail($bookId);
	}

	public function hadiths(string $bookId)
	{
		$book = HadithBook::select("id", "name", "total_hadiths")->findOrFail(
			$bookId
		);
		$hadiths = $book
			->hadiths()
			->select("number", "arabic", "translation")
			->orderBy("number")
			->paginate(10);
		$book->setRelation("hadiths", $hadiths);

		return $book;
	}

	public function show(int $hadithId)
	{
		return Hadith::with("book")->findOrFail($hadithId);
	}
}
