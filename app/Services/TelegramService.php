<?php

namespace Modules\Api\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TelegramService
{
	protected $token;
	protected $baseUrl;

	public function __construct()
	{
		$this->token = config("api.telegram.bot_token");
		$this->baseUrl = config("api.telegram.api_url");
	}

	public function sendMessage(
		string $message,
		string $chatId = null,
		string $parseMode = "html"
	) {
		$chatId = $chatId ?? config("api.telegram.chat_id");
		$endpoint = "{$this->baseUrl}{$this->token}/sendMessage";

		try {
			$response = Http::timeout(15)
				->retry(3, 1000)
				->post($endpoint, [
					"chat_id" => $chatId,
					"text" => $message,
					"parse_mode" => $parseMode,
					"disable_web_page_preview" => true,
				]);

			$this->handleResponse($response, $chatId);

			return true;
		} catch (Exception $e) {
			$this->logError($e, $chatId);
			return false;
		}
	}

	protected function handleResponse($response, $chatId)
	{
		if ($response->failed()) {
			$error = $response->json() ?? $response->body();
			Log::error("Telegram API Error", [
				"chat_id" => $chatId,
				"status" => $response->status(),
				"error" => $error,
				"response" => $response->body(),
			]);

			throw new Exception(
				"Telegram API Error: {$response->status()} - " . json_encode($error)
			);
		}
	}

	protected function logError(Exception $e, $chatId)
	{
		Log::critical("Telegram Message Failed", [
			"chat_id" => $chatId,
			"error" => $e->getMessage(),
			"trace" => $e->getTraceAsString(),
		]);
	}
}
