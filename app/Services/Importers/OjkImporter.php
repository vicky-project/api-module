<?php

namespace Modules\Api\Services\Importers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Modules\Api\Models\OjkIllegal;
use Modules\Api\Models\OjkApp;
use Modules\Api\Models\OjkProduct;
use Carbon\Carbon;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;
use Modules\Api\Traits\DatabaseOperations;
use Modules\Api\Interfaces\DataImporterContract;

class OjkImporter implements DataImporterContract
{
	use DatabaseOperations;

	protected $config;
	protected $type = "OJK Data";
	protected $url;

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
		$this->config["command"]->info("🌐 Downloading data from: {$this->url}");

		$response = $this->fetchData();
		$data = $response->json()["ojk"] ?? null;

		if (!$data) {
			throw new Exception("Invalid OJK data structure");
		}

		$this->processSection(
			"illegals",
			$data["illegals"] ?? [],
			OjkIllegal::class,
			[$this, "prepareIllegalItem"]
		);
		$this->processSection("apps", $data["apps"] ?? [], OjkApp::class, [
			$this,
			"prepareAppItem",
		]);
		$this->processSection(
			"products",
			$data["products"] ?? [],
			OjkProduct::class,
			[$this, "prepareProductItem"]
		);

		$this->config["command"]->info("✅ {$this->type} import completed");
	}

	protected function fetchData()
	{
		return Http::timeout($this->config["http_timeout"])
			->retry($this->config["max_retries"], 1000)
			->get($this->url);
	}

	protected function processSection(
		string $type,
		array $items,
		string $modelClass,
		callable $preparer
	): void {
		$count = count($items);
		$label = $this->getSectionLabel($type);
		$this->config["command"]->info("📊 Processing {$count} {$label} entries");

		$this->processInChunks(
			$items,
			function ($chunk) use ($modelClass, $preparer) {
				$this->processBatch($chunk, $modelClass, $preparer);
			},
			$type,
			true
		);
	}

	protected function getSectionLabel(string $type): string
	{
		return match ($type) {
			"illegals" => "illegal fintech",
			"apps" => "app",
			"products" => "product",
			default => $type,
		};
	}

	protected function processBatch(
		array $items,
		string $modelClass,
		callable $preparer
	): void {
		$dataToInsert = [];

		foreach ($items as $item) {
			$prepared = $preparer($item);
			if ($prepared) {
				$dataToInsert[] = $prepared;
			}
		}

		if (!empty($dataToInsert)) {
			DB::transaction(function () use ($modelClass, $dataToInsert) {
				$modelClass::upsert(
					$dataToInsert,
					["id"],
					$this->getUpdateColumns($modelClass)
				);
			});
		}
	}

	protected function prepareIllegalItem(array $item): array
	{
		return [
			"id" => $item["id"] ?? null,
			"name" => $item["name"] ?? null,
			"alias" => json_encode($item["alias"] ?? []),
			"address" => json_encode($item["address"] ?? []),
			"web" => json_encode($item["web"] ?? []),
			"email" => json_encode($item["email"] ?? []),
			"phone" => json_encode($item["phone"] ?? []),
			"entity_type" => $item["entity_type"] ?? null,
			"activity_type" => json_encode($item["activity_type"] ?? []),
			"input_date" => $this->parseDate($item["input_date"] ?? null),
			"description" => $item["description"] ?? null,
			"created_at" => now(),
			"updated_at" => now(),
		];
	}

	protected function prepareAppItem(array $item): array
	{
		return [
			"id" => $item["id"] ?? null,
			"name" => $item["name"] ?? null,
			"url" => $item["url"] ?? null,
			"owner" => $item["owner"] ?? null,
			"created_at" => now(),
			"updated_at" => now(),
		];
	}

	protected function prepareProductItem(array $item): array
	{
		return [
			"id" => $item["id"] ?? null,
			"name" => $item["name"] ?? null,
			"management" => $item["management"] ?? null,
			"custodian" => $item["custodian"] ?? null,
			"type" => $item["type"] ?? null,
			"created_at" => now(),
			"updated_at" => now(),
		];
	}

	protected function getUpdateColumns(string $modelClass): array
	{
		return match ($modelClass) {
			OjkIllegal::class => [
				"name",
				"alias",
				"address",
				"web",
				"email",
				"phone",
				"entity_type",
				"activity_type",
				"input_date",
				"description",
				"updated_at",
			],
			OjkApp::class => ["name", "url", "owner", "updated_at"],
			OjkProduct::class => [
				"name",
				"management",
				"custodian",
				"type",
				"updated_at",
			],
			default => [],
		};
	}

	protected function parseDate(?string $dateString): ?string
	{
		if (!$dateString) {
			return null;
		}

		try {
			return Carbon::createFromFormat("d/m/Y", $dateString)->format("Y-m-d");
		} catch (\Exception $e) {
			return null;
		}
	}

	protected function processItemsIndividually(
		array $items,
		string $type,
		?ProgressBar $progressBar = null
	): void {
		$modelClass = match ($type) {
			"illegals" => OjkIllegal::class,
			"apps" => OjkApp::class,
			"products" => OjkProduct::class,
			default => throw new \Exception("Unknown type: {$type}"),
		};

		$preparer = match ($type) {
			"illegals" => [$this, "prepareIllegalItem"],
			"apps" => [$this, "prepareAppItem"],
			"products" => [$this, "prepareProductItem"],
		};

		foreach ($items as $item) {
			try {
				$prepared = $preparer($item);
				if ($prepared) {
					$modelClass::updateOrCreate(["id" => $prepared["id"]], $prepared);
				}
			} catch (\Exception $e) {
				$this->config["command"]->error(
					"Failed to process {$type} item: " . $e->getMessage()
				);
			}
		}
	}
}
