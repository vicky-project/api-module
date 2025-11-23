<?php

namespace Modules\Api\Services\Importers;

use Modules\Api\Models\{Bank, BankCity, BankCountry};
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;
use Modules\Api\Traits\{DatabaseOperations, FileDownloader};
use Modules\Api\Interfaces\DataImporterContract;

class SwiftGlobalImporter implements DataImporterContract
{
	use DatabaseOperations, FileDownloader;

	protected $url;
	protected $config;
	protected $type = "SwiftGlobal";

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
		$tempFilePath = null;

		try {
			// Download seluruh data sekaligus
			$tempFilePath = $this->downloadData(
				$this->url,
				null,
				true,
				$this->config
			);

			// Baca dan parse data dari file
			$jsonData = file_get_contents($tempFilePath);
			$data = json_decode($jsonData, true);

			// Hapus file sementara setelah dibaca
			$this->cleanupTempFile($tempFilePath);

			if (
				!isset($data["swift_global"]["countries"]) ||
				!is_array($data["swift_global"]["countries"])
			) {
				throw new Exception("Invalid response struktur for {$this->type} data");
			}

			$this->processSwiftCodes($data["swift_global"]["countries"]);
		} catch (Exception $e) {
			Log::error("Import {$this->type} error: " . $e->getMessage());
			$this->config["command"]->error(
				"❌ {$this->type} import failed. Error: " . $e->getMessage()
			);
			throw $e;
		} finally {
			if ($tempFilePath) {
				$this->cleanupTempFile($tempFilePath);
			}
		}
	}

	protected function processSwiftCodes(array $data)
	{
		$totalData = count($data);
		$this->config["command"]->info(
			"📝 Found {$totalData} countries with SWIFT codes"
		);

		$cityMapping = $this->dataMapping($data);

		$banks = [];
		foreach ($data as $key => $value) {
			foreach ($value["list"] as $val) {
				$banks[] = [
					"city_id" => $cityMapping[$val["city"]],
					"name" => $val["bank"],
					"branch" => $val["branch"] ?? null,
					"swift_code" => $val["swift_code"],
				];
			}
		}

		$totalData = count($banks);

		$this->config["command"]->info("ℹ️ Total swift code bank: {$totalData}");

		$this->processInChunks(
			$banks,
			fn($chunk) => $this->processSingleBank($chunk),
			"bank",
			true,
			$this->config["chunk_size"]
		);

		$this->config["command"]->info(
			"✅ Completed {$totalData} {$this->type} import"
		);
	}

	protected function dataMapping(array $data)
	{
		$collection = collect($data);

		$countries = $collection
			->pluck("country", "country_code")
			->map(fn($i, $k) => ["code" => $k, "name" => $i])
			->sortBy("code")
			->values()
			->all();

		$this->processInChunks(
			$countries,
			fn($chunk) => $this->processSingleCountry($chunk),
			"bank_country",
			true,
			$this->config["chunk_size"]
		);

		$cities = $collection
			->pluck("list.*.city", "country_code")
			->flatMap(
				fn($v, $k) => collect($v)
					->unique()
					->map(fn($i) => ["country_code" => $k, "name" => $i])
					->toArray()
			)
			->sortBy("name")
			->values()
			->all();

		$this->processInChunks(
			$cities,
			fn($chunk) => $this->processSingleCity($chunk),
			"bank_city",
			true,
			$this->config["chunk_size"]
		);

		return BankCity::pluck("id", "name")->toArray();
	}

	protected function processSingleCountry(array $items): void
	{
		BankCountry::insert($items);
	}

	protected function processSingleCity(array $items): void
	{
		BankCity::insert($items);
	}

	protected function processSingleBank(array $items): void
	{
		Bank::insert($items);
	}

	protected function processItemsIndividually(
		array $items,
		string $type,
		?ProgressBar $progressBar = null
	): void {
		$totalItems = count($items);
		$this->config["command"]->info(
			"Processing {$totalItems} items individually..."
		);

		$progress = $this->config["command"]
			->getOutput()
			->createProgressBar($totalItems);
		$progress->setFormat("%message%\n  %current%/%max% [%bar%] %percent:3s%%");
		$progress->setMessage("Processing swift bank individually");
		$progress->start();

		foreach ($items as $item) {
			try {
				DB::transaction(function () use ($type, $item) {
					switch ($type) {
						case "bank":
							$this->processSingleBank($item);
							break;
						case "bank_city":
							$this->processSingleCity($item);
							break;
						case "bank_country":
							$this->processSingleCountry($item);
							break;
					}
				});
				$progress->advance();
			} catch (Exception $e) {
				$progress->clear();
				$name = isset($item["name"]) ? $item["name"] : "Unknown";
				$this->config["command"]->error(
					"Failed to import {$type} item: {$name} - " . $e->getMessage()
				);
				$progress->display();
			}
		}

		$progress->finish();
		$this->config["command"]->newLine();
	}
}
