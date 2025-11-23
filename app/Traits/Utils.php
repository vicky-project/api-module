<?php

namespace Modules\Api\Traits;

use Illuminate\Http\Request;

trait Utils
{
	/**
	 * Generate cache key based on parameters
	 */
	protected function generateCacheKey(
		string $prefix,
		array $params = []
	): string {
		return $prefix . "_" . md5(serialize($params));
	}

	/**
	 * Escape string for LIKE queries
	 */
	protected function escapeForLike(string $value): string
	{
		return "%" . addcslashes($value, "%_\\") . "%";
	}

	/**
	 * Get pagination parameters from request
	 */
	protected function getPaginationParams(Request $request): array
	{
		return [
			"page" => $request->input("page", 1),
			"per_page" => $request->input("per_page", 10),
		];
	}
}
