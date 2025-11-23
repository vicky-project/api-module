<?php

use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\ApiGatewayController;

$version = config("api.version", "v1");
//$prefix = config("apiservice.prefix", "api");
$authEnabled = config("api.auth.enabled", false);

Route::group(
	[
		"prefix" => "/{$version}",
		"middleware" => ["api"],
	],
	function () use ($authEnabled) {
		// Public endpoints (always accessible)
		Route::get("services", [ApiGatewayController::class, "listServices"])->name(
			"services.list"
		);

		// Service endpoints with conditional auth
		if ($authEnabled) {
			// With authentication
			Route::middleware([config("api.auth.middleware")])->group(function () {
				Route::any("{service}/{method}/{params?}", [
					ApiGatewayController::class,
					"handleService",
				])
					->where("params", ".*")
					->name("api.service.method");

				// Fallback tanpa method
				Route::any("{service}", [
					ApiGatewayController::class,
					"handleService",
				])->name("api.service");
			});

			// Public services (excluded from auth)
			$publicServices = config("api.auth.public_services", []);
			foreach ($publicServices as $service) {
				Route::any("{$service}/{method}/{params?}", [
					ApiGatewayController::class,
					"handleService",
				])
					->where("params", ".*")
					->name("api.service.{$service}.method");

				Route::any("{$service}", [
					ApiGatewayController::class,
					"handleService",
				])->name("api.service.{$service}");
			}
		} else {
			// No authentication required
			Route::any("{service}/{method}/{params?}", [
				ApiGatewayController::class,
				"handleService",
			])
				->where("params", ".*")
				->name("api.service.method");

			Route::any("{service}", [
				ApiGatewayController::class,
				"handleService",
			])->name("api.service");
		}
	}
);
