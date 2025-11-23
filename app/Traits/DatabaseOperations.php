<?php

namespace Modules\Api\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;

trait DatabaseOperations
{
	protected function processInChunks(
		array $items,
		callable $processCallback,
		string $type,
		bool $showOutput = false,
		?int $customChunkSize = null
	): void {
		$totalItems = count($items);
		if ($totalItems === 0) {
			if ($showOutput) {
				$this->getOutput()?->info("No {$type} to process");
			}
			return;
		}

		$chunkSize = $customChunkSize ?? ($this->config["chunk_size"] ?? 500);
		$chunks = array_chunk($items, $chunkSize);
		$progressBar = null;
		$output = $this->getOutput();

		if ($showOutput) {
			$output?->info(
				"Processing {$totalItems} {$type} in " . count($chunks) . " chunks..."
			);
			$progressBar = $this->initializeProgressBar($output, $totalItems);
		}

		foreach ($chunks as $chunk) {
			try {
				DB::transaction(fn() => $processCallback($chunk));
				$processed = count($chunk);

				if ($progressBar) {
					$progressBar->advance($processed);
				}
			} catch (\Exception $e) {
				$this->handleChunkError($chunk, $e, $type, $progressBar);
			}
		}

		if ($progressBar) {
			$progressBar->finish();
			$output?->writeln("");
		}
	}

	protected function bulkInsert(string $model, array $data)
	{
		$modelInstance = new $model();
		$tableName = $modelInstance->getTable();

		try {
			DB::table($tableName)->insert($data);
		} catch (\Exception $e) {
			throw $e;
		} finally {
			unset($data);
			gc_collect_cycles();
		}
	}

	private function handleChunkError(
		array $chunk,
		\Exception $exception,
		string $type,
		?ProgressBar $progressBar = null
	): void {
		$chunkSize = count($chunk);
		$errorMsg = "{$type} chunk processing failed: " . $exception->getMessage();

		$this->logError($errorMsg, $exception, $chunkSize, $type);
		$this->getOutput()?->error($errorMsg);

		// Individual processing fallback
		$this->getOutput()?->warn("Falling back to individual processing...");
		$this->processItemsIndividually($chunk, $type, $progressBar);
	}

	abstract protected function processItemsIndividually(
		array $items,
		string $type,
		?ProgressBar $progressBar = null
	): void;

	private function initializeProgressBar($output, int $total): ?ProgressBar
	{
		if (!$output || !class_exists(ProgressBar::class)) {
			return null;
		}

		$progressBar = new ProgressBar($output, $total);
		$progressBar->setFormat(
			"%message%\n %current%/%max% [%bar%] %percent:3s%% %elapsed:16s%/%estimated:-16s%"
		);
		$progressBar->setMessage("Processing...");
		$progressBar->start();

		return $progressBar;
	}

	private function logError(
		string $message,
		\Throwable $exception,
		int $chunkSize,
		string $type
	): void {
		Log::error($message, [
			"exception" => $exception,
			"chunk_size" => $chunkSize,
			"item_type" => $type,
			"error_trace" => $exception->getTraceAsString(),
		]);
	}

	private function getOutput(): ?\Symfony\Component\Console\Output\OutputInterface
	{
		return $this->config["command"]->getOutput() ?? null;
	}
}
