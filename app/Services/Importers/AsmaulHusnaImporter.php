<?php

namespace Modules\Api\Services\Importers;

use Illuminate\Support\Facades\Http;
use Modules\Api\Models\AsmaulHusna;
use Modules\Api\Models\QuranVerse;
use Modules\Api\Interfaces\DataImporterContract;

class AsmaulHusnaImporter implements DataImporterContract
{
	protected $config;
	protected $type = "Asmaul Husna";
	protected $oldUrl;
	protected $newUrl;

	public function __construct(array $config)
	{
		$this->config = $config;
		$this->oldUrl = $config["old_url"];
		$this->newUrl = $config["new_url"];
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function import(): void
	{
		$this->config["command"]->info("🚀 Starting {$this->type} import...");

		// Download data lama
		$this->config["command"]->info(
			"🌐 Downloading base data from: {$this->oldUrl}"
		);
		$oldResponse = Http::timeout($this->config["http_timeout"])
			->retry($this->config["max_retries"], 1000)
			->get($this->oldUrl);

		if (!$oldResponse->successful()) {
			throw new \Exception(
				"HTTP error for base data! Status: {$oldResponse->status()}"
			);
		}

		// Download data baru
		$this->config["command"]->info(
			"🌐 Downloading additional data from: {$this->newUrl}"
		);
		$newResponse = Http::timeout($this->config["http_timeout"])
			->retry($this->config["max_retries"], 1000)
			->get($this->newUrl);

		if (!$newResponse->successful()) {
			throw new \Exception(
				"HTTP error for additional data! Status: {$newResponse->status()}"
			);
		}

		$oldData = $oldResponse->json()["data"] ?? [];
		$newData = $newResponse->json()["data"] ?? [];

		$this->processData($oldData, $newData);
		$this->config["command"]->info("✅ {$this->type} import completed");
	}

	protected function processData(array $oldData, array $newData): void
	{
		if (empty($oldData)) {
			throw new \Exception("No base data found");
		}

		// Mapping data baru berdasarkan nomor
		$newDataMap = [];
		foreach ($newData as $item) {
			$newDataMap[$item["number"]] = $item;
		}

		$progressBar = $this->config["command"]
			->getOutput()
			->createProgressBar(count($oldData));

		$progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %message%");
		$progressBar->setMessage("Starting import...");
		$progressBar->start();

		foreach ($oldData as $item) {
			$number = $item["no"];
			$newItem = $newDataMap[$number] ?? null;

			$progressBar->setMessage("Importing {$item["latine"]}");

			// Siapkan data multibahasa
			$meaning = [];
			$description = [];

			// Tambahkan bahasa Indonesia dari data lama
			$meaning["id"] = $item["arti"];

			// Tambahkan bahasa Inggris dari data baru jika ada
			if ($newItem && isset($newItem["en"])) {
				$meaning["en"] = $newItem["en"]["meaning"] ?? null;
				$description["en"] = $newItem["en"]["desc"] ?? null;
			}

			// Tambahkan bahasa Perancis dari data baru jika ada
			if ($newItem && isset($newItem["fr"])) {
				$meaning["fr"] = $newItem["fr"]["meaning"] ?? null;
				$description["fr"] = $newItem["fr"]["desc"] ?? null;
			}

			// Buat/update data
			$asma = AsmaulHusna::updateOrCreate(
				["number" => $number],
				[
					"arabic" => $item["arab"],
					"latine" => $newItem["transliteration"] ?? $item["latine"],
					"meaning" => $meaning,
					"description" => $description,
					"found" => $newItem["found"] ?? null,
				]
			);

			if ($newItem && isset($newItem["found"])) {
				$this->processReferences($asma, $newItem["found"]);
			}

			$progressBar->advance();
		}

		$progressBar->setMessage("✅ Import completed");
		$progressBar->finish();
		$this->config["command"]->newLine(2);
	}

	protected function processReferences(AsmaulHusna $asma, string $found): void
	{
		preg_match_all("/\((\d+):(\d+)\)/", $found, $matches, PREG_SET_ORDER);

		$verseIds = [];

		foreach ($matches as $match) {
			$surahNumber = (int) $match[1];
			$verseNumber = (int) $match[2];

			$verse = QuranVerse::where("surah_number", $surahNumber)
				->where("verse_number", $verseNumber)
				->firstOrFail();

			if ($verse) {
				$verseIds[] = $verse->id;
				$this->config["command"]->info(
					"Found verse: {$surahNumber}:{$verseNumber} (ID: {$verse->id})"
				);
			} else {
				$this->config["command"]->error(
					"Verse not found: {$surahNumber}:{$verseNumber}"
				);
			}
		}

		$this->config["command"]->info(
			"Syncing {$asma->number} with verse: " . implode(",", $verseIds)
		);
		$asma->verses()->sync($verseIds);
	}
}
