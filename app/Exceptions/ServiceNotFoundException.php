<?php

namespace Modules\Api\Exceptions;

use Exception;

class ServiceNotFoundException extends Exception
{
	protected $serviceName;

	public function __construct(
		string $serviceName,
		int $code = 0,
		Exception $previous = null
	) {
		$this->serviceName = $serviceName;
		$message = "API Service '{$serviceName}' not found in registry";

		parent::__construct($message, $code, $previous);
	}

	public function getServiceName(): string
	{
		return $this->serviceName;
	}

	public function render($request)
	{
		if ($request->expectsJson()) {
			return response()->json(
				[
					"success" => false,
					"message" => $this->getMessage(),
					"service" => $this->serviceName,
					"timestamp" => now()->toISOString(),
				],
				404
			);
		}

		return response($request);
	}
}
