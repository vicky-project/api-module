<?php

namespace Modules\Api\Core;

use Modules\Api\Interfaces\ApiServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseApiService implements ApiServiceInterface
{
	protected string $name;
	protected bool $requiresAuth = false;
	protected bool $readonly = false;

	protected Request $request;

	public function __construct(Request $request)
	{
		$this->request = $request;
	}

	/**
	 * Eksekusi service method dengan error handling standar
	 */
	public function execute(string $method, array $parameters = []): mixed
	{
		try {
			// Check if method exists
			if (!method_exists($this, $method)) {
				return ApiResponse::error(
					"Method {$method} not found in service {$this->getName()}",
					404
				);
			}

			// Check if method is allowed for readonly service
			if (
				$this->isReadonly() &&
				!in_array(strtoupper(request()->method()), ["GET", "OPTIONS"])
			) {
				return ApiResponse::error(
					"Service {$this->getName()} is readonly",
					405
				);
			}

			$methodParameters = $this->prepareMethodParameters($method, $parameters);

			// Execute method
			$result = call_user_func_array([$this, $method], $methodParameters);

			if ($result instanceof StreamedResponse) {
				return $result;
			}

			if ($result instanceof JsonResponse) {
				return $result;
			}

			return ApiResponse::success($result);
		} catch (\Illuminate\Validation\ValidationException $e) {
			return ApiResponse::validationError($e->errors());
		} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
			return ApiResponse::error("Resource not found", 404);
		} catch (\Exception $e) {
			return ApiResponse::error(
				$e->getMessage(),
				500,
				config("app.debug")
					? [
						"file" => $e->getFile(),
						"line" => $e->getLine(),
						"trace" => $e->getTrace(),
					]
					: null
			);
		}
	}

	/**
	 * Prepare parameters untuk method call berdasarkan method signture
	 */
	protected function prepareMethodParameters(
		string $method,
		array $parameters
	): array {
		$reflectionMethod = new \ReflectionMethod($this, $method);
		$methodParams = $reflectionMethod->getParameters();
		$preparedParameters = [];

		foreach ($methodParams as $param) {
			$paramName = $param->getName();
			$paramType = $param->getType();

			if (array_key_exists($paramName, $parameters)) {
				$preparedParameters[] = $this->castParameter(
					$parameters[$paramName],
					$paramType
				);
			} elseif (isset($parameters[$param->getPosition()])) {
				$preparedParameters[] = $this->castParameter(
					$parameters[$param->getPosition()],
					$paramType
				);
			} elseif ($param->isDefaultValueAvailable()) {
				$preparedParameters[] = $param->getDefaultValue();
			} else {
				throw new \InvalidArgumentException(
					"Missing required parameter: {$paramName} for method {$method}"
				);
			}
		}

		return $preparedParameters;
	}

	/**
	 * Cast parameter ke tipe yang sesuai
	 */
	protected function castParameter($value, ?\ReflectionType $type)
	{
		if ($type === null) {
			return $value;
		}

		$typeName = $type->getName();

		return match ($typeName) {
			"int" => (int) $value,
			"float" => (float) $value,
			"bool" => filter_var($value, FILTER_VALIDATE_BOOLEAN),
			"string" => (string) $value,
			"array" => is_array($value) ? $value : [$value],
			default => $value,
		};
	}

	/**
	 * Validasi input
	 */
	public function validate(
		array $data,
		array $rules,
		array $message = []
	): array {
		$validator = Validator::make($data, $rules, $message);

		if ($validator->fails()) {
			throw new \Illuminate\Validation\ValidationException($validator);
		}

		return $validator->validated();
	}

	/**
	 * Get service name
	 */
	public function getName(): string
	{
		return $this->name ?? class_basename($this);
	}

	/**
	 * Check if requires auth
	 */
	public function requiresAuth(): bool
	{
		return $this->requiresAuth;
	}

	/**
	 * Check if readonly
	 */
	public function isReadonly(): bool
	{
		return $this->readonly;
	}

	/**
	 * Get available methods
	 */
	public function getAvailableMethods(): array
	{
		$reflection = new \ReflectionClass($this);
		$methods = [];

		foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			$methodName = $method->getName();

			// Exclude base methods
			if (
				!in_array($methodName, [
					"__construct",
					"execute",
					"validate",
					"getName",
					"requiresAuth",
					"isReadonly",
					"getAvailableMethods",
					"ensureMethod",
					"prepareMethodParameters",
					"castParameter",
				])
			) {
				$methods[] = $methodName;
			}
		}

		return $methods;
	}

	/**
	 * Helper untuk pagination
	 */
	protected function paginate($query, $perPage = null)
	{
		$perPage = $perPage ?: request()->get("per_page", 15);
		return $query->paginate(min($perPage, 100));
	}

	protected function ensureMethod(string $method): void
	{
		if (!$this->request->isMethod($method)) {
			throw new \Exception("This endpoint only accepts {$method} request");
		}
	}
}
