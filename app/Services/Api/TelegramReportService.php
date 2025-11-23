<?php

namespace Modules\Api\Services\Api;

use Modules\Api\Core\BaseApiService;
use Illuminate\Support\Facades\Http;
use Modules\Api\Services\TelegramService;

class TelegramReportService extends BaseApiService
{
	protected string $name = "telegram";
	protected bool $requiresAuth = false;
	protected bool $readonly = false;

	public function send()
	{
		$this->ensurePostMethod();

		$validated = $this->validate($this->request->all(), [
			"message" => "required|string",
			"chat_id" => "nullable|string",
		]);

		$trackingData = json_decode($validated["message"], true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new \Exception("Invalid JSON format in message");
		}

		$message = $this->formatMessage($trackingData);

		$telegram = new TelegramService();
		$sent = $telegram->sendMessage($message, $validated["chat_id"] ?? null);

		return [];
	}

	protected function formatMessage(array $data): string
	{
		$e = fn($value) => e($value ?? "N/A");
		$location = "";
		if (is_string($data["location"] ?? null)) {
			$location = $e($data["location"]);
		} elseif (is_array($data["location"] ?? null)) {
			$loc = $data["location"];
			$parts = [];
			if (!empty($loc["city"])) {
				$parts[] = $e($loc["city"]);
			}
			if (!empty($loc["region"])) {
				$parts[] = $e($loc["region"]);
			}
			if (!empty($loc["country"])) {
				$parts[] = $e($loc["country"]);
			}
			$location = implode(", ", $parts);

			if (!empty($loc["lat"]) && !empty($loc["lon"])) {
				$lat = $e($loc["lat"]);
				$lon = $e($loc["lon"]);
				$location .= "\n📍 <b>Koordinat:</b> {$lat}, {$lon}";
			}
		}

		// Bangun pesan HTML
		$html = "<b>🔔 VISITOR TRACKING</b>\n";
		$html .= "──────────────────\n";
		$html .= "<b>🆔 IP:</b> {$e($data["ip"] ?? "")}\n";
		$html .= "<b>🌐 Halaman:</b> <a href=\"{$e($data["page"] ?? "")}\">{$e(
			$data["page"] ?? ""
		)}</a>\n";
		$html .= "<b>🕒 Waktu:</b> {$e($data["time"] ?? "")}\n";
		$html .= "<b>📱 Perangkat:</b> {$e($data["agent"] ?? "")}\n";
		$html .= "<b>🖥️ Layar:</b> {$e($data["screen"] ?? "")}\n";
		$html .= "<b>📍 Lokasi:</b>\n{$location}";

		if (!empty($data["mapUrl"])) {
			$html .= "\n🗺️ <a href=\"{$e($data["mapUrl"])}\">Lihat Peta</a>";
		}

		$html .= "\n──────────────────";

		$html .= "\n\n<i>Sent from: " . config("app.name") . "</i>";

		return $html;
	}
}
