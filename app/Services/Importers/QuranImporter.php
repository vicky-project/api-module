<?php

namespace Modules\Api\Services\Importers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Api\Models\QuranSurah;
use Modules\Api\Models\QuranVerse;
use JsonException;
use Symfony\Component\Console\Helper\ProgressBar;
use Modules\Api\Traits\{DatabaseOperations, FileDownloader};
use Modules\Api\Interfaces\DataImporterContract;

class QuranImporter implements DataImporterContract
{
	use FileDownloader, DatabaseOperations;

	protected $url;
	protected $config;
	protected $type = "Quran";

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
		$data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
		$this->cleanupTempFile($tempFilePath);

		if (empty($data["quran"])) {
			throw new \Exception("Invalid JSON structure: 'quran' key not found");
		}
		$this->processData($data["quran"]);

		$this->config["command"]->info("✅ {$this->type} import completed");
	}

	protected function processData(array $data): void
	{
		$startTime = microtime(true);
		$totalVerses = 0;

		foreach ($data as $surahData) {
			$verseCount = $this->processSurah($surahData);
			$totalVerses += $verseCount;
			$this->config["command"]->info(
				"Processed surah {$surahData["name_latin"]} ({$verseCount} verses)"
			);
		}

		$processingTime = round(microtime(true) - $startTime, 2);
		$this->config["command"]->info(
			"Processed {$totalVerses} verses in {$processingTime}s"
		);
	}

	protected function processSurah(array $surahData): int
	{
		if (
			empty($surahData["number"]) ||
			empty($surahData["name"]) ||
			empty($surahData["name_latin"])
		) {
			$this->config["command"]->warn(
				"Invalid surah data: " . json_encode($surahData)
			);
			return 0;
		}

		$surah = QuranSurah::updateOrCreate(
			["number" => $surahData["number"]],
			[
				"name" => $surahData["name"],
				"name_latin" => $surahData["name_latin"],
				"number_of_verses" => $surahData["number_of_verses"],
				"place" => $surahData["place"],
				"meaning" => $surahData["meaning"],
				"description" => $surahData["description"],
				"audio_full" => json_encode($surahData["audio_full"]),
			]
		);

		$verses = $surahData["verses"] ?? [];
		$verseCount = count($verses);

		if ($verseCount === 0) {
			$this->config["command"]->warn(
				"No verses found for surah: {$surahData["name_latin"]}"
			);
			return 0;
		}

		$this->processInChunks(
			$verses,
			fn($chunk) => $this->upsertVerses(
				$surah->id,
				$surahData["number"],
				$chunk
			),
			"verses for surah {$surah->name_latin}"
		);

		return $verseCount;
	}

	protected function upsertVerses(
		int $surahId,
		int $surahNumber,
		array $verses
	): void {
		$dataToUpsert = [];

		foreach ($verses as $verseData) {
			if (
				empty($verseData["verse_number"]) ||
				empty($verseData["arabic_text"]) ||
				empty($verseData["translation"])
			) {
				$this->config["command"]->warn(
					"Invalid verse data: " . json_encode($verseData)
				);
				continue;
			}

			$dataToUpsert[] = [
				"surah_id" => $surahId,
				"surah_number" => $surahNumber,
				"verse_number" => $verseData["verse_number"],
				"arabic_text" => $verseData["arabic_text"],
				"latin_text" => $verseData["latin_text"] ?? "",
				"translation" => $verseData["translation"],
				"audio" => json_encode($verseData["audio"]),
			];
		}

		if (empty($dataToUpsert)) {
			return;
		}

		DB::transaction(function () use ($dataToUpsert) {
			QuranVerse::upsert(
				$dataToUpsert,
				["surah_id", "verse_number"],
				["arabic_text", "latin_text", "translation", "audio"]
			);
		});
	}

	function processItemsIndividually(
		array $items,
		string $type,
		?ProgressBar $progressBar = null
	): void {
		if (strpos($type, "verses") !== false) {
			preg_match("/surah (\d+)/", $type, $matches);
			$surahId = $matches[1] ?? null;
			$surahNumber = explode(" ", $type)[3] ?? null;

			if ($surahId && $surahNumber) {
				$this->fallbackVersesUpsert($surahId, $surahNumber, $items);
			}
		}
	}

	protected function fallbackVersesUpsert(
		int $surahId,
		int $surahNumber,
		array $verses
	): void {
		foreach ($verses as $verseData) {
			try {
				QuranVerse::updateOrCreate(
					[
						"surah_id" => $surahId,
						"verse_number" => $verseData["verse_number"],
					],
					[
						"surah_number" => $surahNumber,
						"arabic_text" => $verseData["arabic_text"],
						"latin_text" => $verseData["latin_text"] ?? "",
						"translation" => $verseData["translation"],
						"audio" => json_encode($verseData["audio"]),
					]
				);
			} catch (\Exception $e) {
				$this->config["command"]->error(
					"Verse {$verseData["verse_number"]} failed: " . $e->getMessage()
				);
				Log::error("Quran verse upsert failed", [
					"surah_id" => $surahId,
					"verse_number" => $verseData["verse_number"],
					"error" => $e->getMessage(),
				]);
			}
		}
	}
}
