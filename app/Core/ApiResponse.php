<?php

namespace Modules\Api\Core;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
	/**
	 * Success response
	 */
	public static function success(
		$data = null,
		string $message = null,
		int $code = 200,
		array $meta = null
	): JsonResponse {
		$response = [
			"success" => true,
			"data" => $data,
			"message" => $message ?? "Operation completed successfuly",
		];

		if (config("api.response.include_timestamp", false)) {
			$response["timestamp"] = now()->toISOString();
		}

		if ($meta && config("api.response.include_metadata", false)) {
			$response["meta"] = $meta;
		}

		return response()->json($response, $code);
	}

	/**
	 * Error response
	 */
	public static function error(
		string $message,
		int $code = 400,
		array $errors = null
	): JsonResponse {
		$response = [
			"success" => false,
			"message" => $message ?? "An error occured",
		];

		if ($errors) {
			$response["errors"] = $errors;
		}

		if (config("api.response.include_timestamp", false)) {
			$response["timestamp"] = now()->toISOString();
		}

		return response()->json($response, $code);
	}

	/**
	 * Validation error response
	 */
	public static function validationError(
		array $errors,
		string $message = null
	): JsonResponse {
		return self::error($message ?? "Validation failed", 422, $errors);
	}

	/**
	 * Paginated response
	 */
	public static function paginated(
		$data,
		$paginator,
		string $message = null
	): JsonResponse {
		return self::success($data, $message ?? "Data retrieve successfuly", 200, [
			"pagination" => [
				"total" => $paginator->total(),
				"per_page" => $paginator->perPage(),
				"current_page" => $paginator->currentPage(),
				"last_page" => $paginator->lastPage(),
				"from" => $paginator->firstItem(),
				"to" => $paginator->lastItem(),
				"links" => [
					"next" => $paginator->nextPageUrl(),
					"previous" => $paginator->previousPageUrl(),
				],
			],
		]);
	}

	/**
	 * Created response
	 */
	public static function created(
		$data = null,
		string $message = null
	): JsonResponse {
		return self::success(
			$data,
			$message ?? "Resource created successfuly",
			201
		);
	}

	/**
	 * No content response
	 */
	public static function noContent(string $message = null): JsonResponse
	{
		return self::success(null, $message ?? "Resource deleted successfuly", 204);
	}

	/**
	 * Not found response
	 */
	public static function notFound(string $message = null): JsonResponse
	{
		return self::error($message ?? "Resource not found", 404);
	}
}
