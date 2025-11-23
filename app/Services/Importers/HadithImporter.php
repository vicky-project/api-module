<?php

namespace Modules\Api\Services\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Api\Models\HadithBook;
use Modules\Api\Models\Hadith;
use JsonException;
use Symfony\Component\Console\Helper\ProgressBar;
use Modules\Api\Traits\{DatabaseOperations, FileDownloader};
use Modules\Api\Interfaces\DataImporterContract;

class HadithImporter implements DataImporterContract
{
	use FileDownloader, DatabaseOperations;

	protected $url;
	protected $config;
	protected $type = "Hadiths";

	public function __construct(string $url, array $config)
	{
		$this->url = $url;
		$this->config = $config;
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function import(): void
	{
		$this->config["command"]->info("🚀 Starting {$this->type} import...");

		$tempFilePath = $this->downloadData($this->url, null, true, $this->config);
		if (!file_exists($tempFilePath)) {
			throw new \Exception("File not found: {$filePath}");
		}

		$jsonContent = file_get_contents($tempFilePath);
		$this->cleanupTempFile($tempFilePath);
		$data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);

		if (empty($data["hadiths"])) {
			throw new \Exception("Invalid JSON structure: 'hadiths' key not found");
		}
		$this->processData($data["hadiths"]);

		$this->config["command"]->info("✅ {$this->type} import completed");
	}

	protected function processData(array $hadiths): void
	{
		$startTime = microtime(true);
		$totalHadiths = 0;

		foreach ($hadiths as $bookData) {
			$hadithCount = $this->processBook($bookData);
			$totalHadiths += $hadithCount;
			$this->config["command"]->info(
				"Processed book {$bookData["name"]} ({$hadithCount} hadiths)"
			);
		}

		$processingTime = round(microtime(true) - $startTime, 2);
		$this->config["command"]->info(
			"Processed {$totalHadiths} hadiths in {$processingTime}s"
		);
	}

	protected function processBook(array $bookData): int
	{
		if (
			empty($bookData["id"]) ||
			empty($bookData["name"]) ||
			!isset($bookData["total_hadiths"])
		) {
			$this->config["command"]->warn(
				"Invalid book data: " . json_encode($bookData)
			);
			return 0;
		}

		$book = HadithBook::updateOrCreate(
			["id" => $bookData["id"]],
			[
				"name" => $bookData["name"],
				"total_hadiths" => $bookData["total_hadiths"],
			]
		);

		$hadiths = $bookData["hadiths"] ?? [];
		$hadithCount = count($hadiths);

		if ($hadithCount === 0) {
			$this->config["command"]->warn(
				"No hadiths found for book: {$bookData["name"]}"
			);
			return 0;
		}

		$this->processInChunks(
			$hadiths,
			fn($chunk) => $this->upsertHadiths($book->id, $chunk),
			"hadiths for book {$book->name}",
			true
		);

		return $hadithCount;
	}

	protected function upsertHadiths(string $bookId, array $hadiths): void
	{
		$dataToUpsert = [];

		foreach ($hadiths as $hadithData) {
			if (
				empty($hadithData["number"]) ||
				empty($hadithData["arabic"]) ||
				empty($hadithData["translation"])
			) {
				$this->config["command"]->warn(
					"Invalid hadith data: " . json_encode($hadithData)
				);
				continue;
			}

			$dataToUpsert[] = [
				"book_id" => $bookId,
				"number" => $hadithData["number"],
				"arabic" => $hadithData["arabic"],
				"translation" => $hadithData["translation"],
			];
		}

		if (empty($dataToUpsert)) {
			return;
		}

		DB::transaction(function () use ($dataToUpsert) {
			Hadith::upsert(
				$dataToUpsert,
				["book_id", "number"],
				["arabic", "translation"]
			);
		});
	}

	protected function processItemsIndividually(
		array $items,
		string $type,
		?ProgressBar $progressBar = null
	): void {
		if (strpos($type, "hadiths") !== false) {
			$bookId = explode(" ", $type)[3] ?? null;
			if ($bookId) {
				$this->fallbackUpsert($bookId, $items);
			}
		}
	}

	protected function fallbackUpsert(string $bookId, array $hadiths): void
	{
		foreach ($hadiths as $hadithData) {
			try {
				Hadith::updateOrCreate(
					[
						"book_id" => $bookId,
						"number" => $hadithData["number"],
					],
					[
						"arabic" => $hadithData["arabic"],
						"translation" => $hadithData["translation"],
					]
				);
			} catch (\Exception $e) {
				$this->config["command"]->error(
					"Hadith {$hadithData["number"]} failed: " . $e->getMessage()
				);
				Log::error("Hadith upsert failed", [
					"book_id" => $bookId,
					"number" => $hadithData["number"],
					"error" => $e->getMessage(),
				]);
			}
		}
	}
}
