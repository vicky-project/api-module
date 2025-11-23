<?php

namespace Modules\Api\Database\Seeders;

use Illuminate\Database\Seeder;

class ApiDatabaseSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$this->command->info("Starting importers.");
		$this->command->info("========================");

		$this->command->info("");
		$this->setResourceLimits();
		$this->command->info("");

		$importers = config("api.importers");
		$this->displayImporterList($importers["services"]);

		$this->runImporters($importers["services"]);

		$this->command->info("========================");
		$this->command->info("Data imported completed");

		//$this->call([QuranSeeder::class]);
	}

	protected function displayImporterList(array $services): void
	{
		$this->command->info("Available service importers:");

		foreach ($services as $name => $options) {
			$this->command->info(" - {$name}");
		}

		$this->command->info("");
	}

	protected function runImporters(array $services): void
	{
		$totalImporter = count($services);
		$current = 1;

		foreach ($services as $name => $config) {
			$this->command->info(
				"[{$current}/{$totalImporter}] Running {$name} importer..."
			);

			try {
				$this->runSingleImporter($name, $config);
				$this->command->info("{$name} importer completed successfully");
			} catch (\Exception $e) {
				$this->command->error("{$name} importer failed: " . $e->getMessage());
				\Log::error("DataImportSeeder: {$name} failed.", [
					"error" => $e->getMessage(),
					"trace" => $e->getTraceAsString(),
				]);

				if (!$this->shouldContinueOnFailure()) {
					throw $e;
				}
			}

			$current++;
			$this->command->info("");
		}
	}

	protected function runSingleImporter(
		string $name,
		array $importerConfig
	): void {
		$importer = null;
		$importerClass = $importerConfig["class"];

		if (!class_exists($importerClass)) {
			throw new \Exception("Importer class {$importerClass} not found.");
		}

		$config = array_merge(
			["command" => $this],
			config("api.importers.options", [])
		);

		if (isset($importerConfig["url"])) {
			$importer = new $importerClass($importerConfig["url"], $config);
		} elseif (isset($importerConfig["config"])) {
			$config = array_merge($config, $importerConfig["config"]);
			$importer = new $importerClass($config);
		} else {
			$importer = new $importerClass($config);
		}

		if (
			!method_exists($importer, "import") ||
			!method_exists($importer, "getType")
		) {
			throw new \Exception(
				"Importer {$importerClass} must be implements DataImporterContract"
			);
		}

		$startTime = microtime(true);

		$importer->import();

		$executionTime = round(microtime(true) - $startTime, 2);
		$this->command->info("{$name} completed in {$executionTime}s");
	}

	protected function setResourceLimits(): void
	{
		ini_set("memory_limit", config("api.importers.ini.memory_limit"));
		set_time_limit(config("api.importers.ini.max_execution_time"));

		$this->command->info("💾 Memory limit: " . ini_get("memory_limit"));
		$this->command->info(
			"⏱️ Max execution time: " . ini_get("max_execution_time")
		);
	}

	protected function shouldContinueOnFailure(): bool
	{
		return $this->command->option("continue-on-failure") ?? false;
	}

	// Method untuk kompatibilitas dengan importer
	public function info($message)
	{
		$this->command->info($message);
	}

	public function warn($message)
	{
		$this->command->warn($message);
	}

	public function error($message)
	{
		$this->command->error($message);
	}

	public function getOutput()
	{
		return $this->command->getOutput();
	}

	public function newLine($count = 1)
	{
		$this->command->getOutput()->newLine($count);
	}
}
