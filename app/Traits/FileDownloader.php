<?php

namespace Modules\Api\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\File;

trait FileDownloader
{
	protected function downloadData(
		string $url,
		?ProgressBar $progressBar = null,
		bool $isShowMessage = true,
		array $customOptions = []
	): string {
		$config = array_merge(
			[
				"max_retries" => $this->config["max_retries"] ?? 3,
				"http_timeout" => $this->config["http_timeout"] ?? 600,
				"min_file_size" => $this->config["min_file_size"] ?? 1024,
				"retry_delay" => $this->config["retry_delay"] ?? 1000,
				"connect_timeout" => $this->config["connect_timeout"] ?? 30,
				"verify_ssl" => $this->config["verify_ssl"] ?? true,
				"headers" => $this->config["http_headers"] ?? [],
			],
			$customOptions
		);

		$this->showMessage(
			$isShowMessage,
			$progressBar,
			"🌐 Downloading {$this->type} data from: {$url}"
		);

		$retryCount = 0;
		$tempFilePath = "";
		$lastError = null;

		while ($retryCount <= $config["max_retries"]) {
			try {
				$tempFilePath = $this->createTempFile();
				$this->executeDownload($url, $tempFilePath, $config);
				$this->validateDownload($tempFilePath, $config["min_file_size"]);

				$fileSize = filesize($tempFilePath);
				$this->showMessage(
					$isShowMessage,
					$progressBar,
					"✅ Download completed. Size: " . round($fileSize / 1048576, 2) . "MB"
				);

				return $tempFilePath;
			} catch (\Exception $e) {
				$lastError = $e;
				$retryCount++;
				$this->handleDownloadError($e, $retryCount, $config, $tempFilePath);

				if ($retryCount <= $config["max_retries"]) {
					$this->exponentialBackoff($retryCount, $config["retry_delay"]);
				}
			}
		}

		throw new \Exception(
			"Download failed after {$config["max_retries"]} attempts: " .
				$lastError->getMessage(),
			0,
			$lastError
		);
	}

	protected function downloadMultiple(
		array $urls,
		?ProgressBar $progressBar = null
	): array {
		$responses = Http::pool(
			fn(Pool $pool) => array_map(
				fn($url) => $pool
					->as($url)
					->withOptions($this->getHttpOptions())
					->get($url),
				$urls
			)
		);

		$results = [];
		foreach ($urls as $url) {
			$response = $responses[$url] ?? null;
			if ($response && $response->successful()) {
				$tempFile = $this->createTempFile();
				File::put($tempFile, $response->body());
				$results[$url] = $tempFile;
			} else {
				$results[$url] = $this->downloadData($url, $progressBar, false);
			}
		}

		return $results;
	}

	protected function cleanupTempFile(
		string $filePath,
		?ProgressBar $progressBar = null,
		bool $isShowMessage = true
	): void {
		if (!file_exists($filePath)) {
			return;
		}

		try {
			if (@unlink($filePath)) {
				$this->showMessage(
					$isShowMessage,
					$progressBar,
					"♻️ Temporary file cleaned: {$filePath}"
				);
			} else {
				$this->showMessage(
					$isShowMessage,
					$progressBar,
					"⚠️ Failed to delete temporary file: {$filePath}",
					"warn"
				);
				Log::warning("Failed to delete temporary file", ["path" => $filePath]);
			}
		} catch (\Throwable $e) {
			Log::error("File cleanup error: " . $e->getMessage(), [
				"path" => $filePath,
				"exception" => $e,
			]);
		}
	}

	// Internal helper methods
	private function executeDownload(
		string $url,
		string $tempFilePath,
		array $config
	): void {
		$response = Http::withOptions($this->getHttpOptions($config))
			->timeout($config["http_timeout"])
			->connectTimeout($config["connect_timeout"])
			->withHeaders($config["headers"])
			->retry($config["max_retries"], $config["retry_delay"])
			->sink($tempFilePath)
			->get($url);

		if (!$response->successful()) {
			throw new \Exception("HTTP error! Status: {$response->status()}");
		}
	}

	private function validateDownload(string $filePath, int $minSize): void
	{
		clearstatcache(true, $filePath);
		$fileSize = filesize($filePath);

		if ($fileSize === false) {
			throw new \Exception("Unable to determine file size");
		}

		if ($fileSize < $minSize) {
			throw new \Exception(
				"Downloaded file is too small ({$fileSize} bytes). Minimum expected: {$minSize} bytes."
			);
		}
	}

	private function handleDownloadError(
		\Exception $e,
		int $retryCount,
		array $config,
		string $tempFilePath
	): void {
		$errorMsg =
			"Download attempt {$retryCount}/{$config["max_retries"]} failed: " .
			$e->getMessage();

		Log::error($errorMsg, [
			"exception" => $e,
			"url" => $this->url ?? "N/A",
			"retry_count" => $retryCount,
		]);

		$this->showMessage(true, null, $errorMsg, "error");

		if (file_exists($tempFilePath)) {
			$this->cleanupTempFile($tempFilePath, null, false);
		}
	}

	private function createTempFile(): string
	{
		$tempFile = tempnam(sys_get_temp_dir(), "{$this->type}_data_");
		if ($tempFile === false) {
			throw new \Exception("Failed to create temporary file");
		}
		return $tempFile;
	}

	private function exponentialBackoff(int $retryCount, int $baseDelay): void
	{
		$sleepTime = $baseDelay * pow(2, $retryCount - 1) + mt_rand(0, 1000);
		usleep($sleepTime * 1000);
	}

	private function showMessage(
		bool $isShowMessage,
		?ProgressBar $progressBar,
		string $message,
		string $type = "info"
	): void {
		if (!$isShowMessage) {
			return;
		}

		if ($progressBar) {
			$progressBar->setMessage($message);
		} elseif (isset($this->config["command"])) {
			match ($type) {
				"warn" => $this->config["command"]->warn($message),
				"error" => $this->config["command"]->error($message),
				default => $this->config["command"]->info($message),
			};
		}
	}

	private function getHttpOptions(array $config = []): array
	{
		return [
			"verify" => $config["verify_ssl"] ?? true,
			"allow_redirects" => [
				"max" => 5,
				"strict" => true,
				"referer" => true,
				"protocols" => ["http", "https"],
			],
			"http_errors" => false,
			"decode_content" => "gzip",
		];
	}
}
