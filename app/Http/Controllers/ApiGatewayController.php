<?php

namespace Modules\Api\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Api\Core\ApiResponse;
use Modules\Api\Core\ServiceRegistry;

class ApiGatewayController
{
	protected ServiceRegistry $registry;

	public function __construct(ServiceRegistry $registry)
	{
		$this->registry = $registry;
	}

	/**
	 * Handle all API service requests
	 * Route: /api/v1/{service}/{method?}
	 */
	public function handleService(
		Request $request,
		string $service,
		string $method = "index",
		string $params = ""
	) {
		// Get service from registry
		$serviceInstance = $this->registry->get($service);

		if (!$serviceInstance) {
			return ApiResponse::error("Service '{$service}' not found", 404);
		}

		// Check authentication
		if ($serviceInstance->requiresAuth() && !auth()->check()) {
			return ApiResponse::error("Authentication required", 401);
		}

		// Check if method is available
		if (!in_array($method, $serviceInstance->getAvailableMethods())) {
			return ApiResponse::error(
				"Method '{$method}' not available in service '{$service}'",
				404
			);
		}

		// Parse parameter dari URL dan request
		$allParameters = $this->parseParameters($request, $params);

		// Execute service method
		return $serviceInstance->execute($method, $allParameters);
	}

	/**
	 * Parse parameters dari berbagai sumber
	 */
	protected function parseParameters(
		Request $request,
		string $urlParams = ""
	): array {
		$parameters = [];

		// Parameters dari URL path (param1/param2/...)
		if (!empty($urlParams)) {
			$urlParameters = explode("/", $urlParams);
			foreach ($urlParameters as $param) {
				if (!empty(trim($param))) {
					$parameters[] = $param;
				}
			}
		}

		// Parameter dari query string (?param1=value&param2=value2)
		$queryParameters = $request->query();
		foreach ($queryParameters as $key => $value) {
			// Hindari duplikasi dengan URL parameters
			if (!is_numeric($key)) {
				$parameters[$key] = $value;
			}
		}

		// Parameters dari request body (JSON, form data)
		if (
			$request->isMethod("POST") ||
			$request->isMethod("PUT") ||
			$request->isMethod("PATCH")
		) {
			$bodyParameters = $request->all();
			foreach ($bodyParameters as $key => $value) {
				$parameters[$key] = $value;
			}
		}

		// Route parameters (jika ada)
		$routeParameters = $request->route()->parameters();
		foreach ($routeParameters as $key => $value) {
			if (!in_array($key, ["service", "method", "params"])) {
				$parameters[$key] = $value;
			}
		}

		return $parameters;
	}

	/**
	 * List all available services
	 */
	public function listServices()
	{
		$services = $this->registry->all()->map(function ($service) {
			$service = $service["instance"];
			return [
				"name" => $service->getName(),
				"requires_auth" => $service->requiresAuth(),
				"readonly" => $service->isReadonly(),
				"methods" => $service->getAvailableMethods(),
			];
		});

		return ApiResponse::success([
			"services" => $services,
			"total" => $services->count(),
			"auth_enabled" => config("api.auth.enabled"),
			"parameter_handling" => [
				"url_parameters" =>
					"Supported via /{service}/{method}/{param1}/{param2}/...",
				"query_parameters" => "Supported via ?key=value",
				"body_parameters" => "Supported via POST/PUT/PATCH body",
				"mix_parameters" => "All parameter types can be combined",
			],
		]);
	}
}
