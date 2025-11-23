<?php

namespace Modules\Api\Core;

use Illuminate\Support\Collection;
use Modules\Api\Interfaces\ApiServiceInterface;
use Modules\Api\Exceptions\ServiceNotFoundException;

class ServiceRegistry
{
	protected Collection $services;
	protected Collection $serviceCache;

	public function __construct()
	{
		$this->services = new Collection();
		$this->serviceCache = new Collection();

		$this->registerFromConfig();
	}

	/**
	 * Register all services from configuration file
	 */
	protected function registerFromConfig(): void
	{
		$services = config("api.services", []);

		foreach ($services as $serviceName => $serviceClass) {
			$this->register($serviceName, $serviceClass);
		}
	}

	/**
	 * Register a service with the registry
	 */
	public function register(string $name, string $serviceClass): void
	{
		// Validate service class
		if (!class_exists($serviceClass)) {
			throw new \InvalidArgumentException(
				"Service class {$serviceClass} does not exist"
			);
		}

		// Check if class implements ApiServiceInterface
		$interfaces = class_implements($serviceClass);
		if (!in_array(ApiServiceInterface::class, $interfaces)) {
			throw new \InvalidArgumentException(
				"Service class {$serviceClass} must implement ApiServiceInterface"
			);
		}

		// Store service class (lazy loading)
		$this->services->put($name, $serviceClass);

		// Clear cache for this service
		$this->serviceCache->forget($name);
	}

	/**
	 * Get a service instance (with caching)
	 */
	public function get(string $name): ApiServiceInterface
	{
		// Check if service exists
		if (!$this->services->has($name)) {
			throw new ServiceNotFoundException("Service '{$name}' is not registered");
		}

		// Return cached instance if available
		if ($this->serviceCache->has($name)) {
			return $this->serviceCache->get($name);
		}

		// Create new instance and cache it
		$serviceClass = $this->services->get($name);
		$serviceInstance = app($serviceClass);

		// Validate instance
		if (!$serviceInstance instanceof ApiServiceInterface) {
			throw new \RuntimeException(
				"Service {$name} must be an instance of ApiServiceInterface"
			);
		}

		// Cache the instance
		$this->serviceCache->put($name, $serviceInstance);

		return $serviceInstance;
	}

	/**
	 * Check if service exists
	 */
	public function has(string $name): bool
	{
		return $this->services->has($name);
	}

	/**
	 * Get all registered services
	 */
	public function all(): Collection
	{
		return $this->services->map(function ($serviceClass, $name) {
			return [
				"name" => $name,
				"class" => $serviceClass,
				"instance" => $this->get($name),
			];
		});
	}

	/**
	 * Get all service names
	 */
	public function getServiceNames(): array
	{
		return $this->services->keys()->toArray();
	}

	/**
	 * Get services that require authentication
	 */
	public function getAuthServices(): Collection
	{
		return $this->all()->filter(function ($service) {
			return $service["instance"]->requiresAuth();
		});
	}

	/**
	 * Get public services (no authentication required)
	 */
	public function getPublicServices(): Collection
	{
		return $this->all()->filter(function ($service) {
			return !$service["instance"]->requiresAuth();
		});
	}

	/**
	 * Get readonly services
	 */
	public function getReadonlyServices(): Collection
	{
		return $this->all()->filter(function ($service) {
			return $service["instance"]->isReadonly();
		});
	}

	/**
	 * Get services with their available methods
	 */
	public function getServicesWithMethods(): Collection
	{
		return $this->all()->map(function ($service) {
			return [
				"name" => $service["name"],
				"class" => $service["class"],
				"requires_auth" => $service["instance"]->requiresAuth(),
				"readonly" => $service["instance"]->isReadonly(),
				"methods" => $service["instance"]->getAvailableMethods(),
			];
		});
	}

	/**
	 * Get service information
	 */
	public function getServiceInfo(string $name): array
	{
		$service = $this->get($name);

		return [
			"name" => $name,
			"class" => get_class($service),
			"requires_auth" => $service->requiresAuth(),
			"readonly" => $service->isReadonly(),
			"methods" => $service->getAvailableMethods(),
		];
	}

	/**
	 * Check if service method exists
	 */
	public function hasMethod(string $serviceName, string $method): bool
	{
		try {
			$service = $this->get($serviceName);
			return in_array($method, $service->getAvailableMethods());
		} catch (ServiceNotFoundException $e) {
			return false;
		}
	}

	/**
	 * Remove a service from registry
	 */
	public function unregister(string $name): bool
	{
		if ($this->services->has($name)) {
			$this->services->forget($name);
			$this->serviceCache->forget($name);

			return true;
		}

		return false;
	}

	/**
	 * Clear all services from registry
	 */
	public function clear(): void
	{
		$this->services = new Collection();
		$this->serviceCache = new Collection();
	}

	/**
	 * Reload services from config
	 */
	public function reload(): void
	{
		$this->clear();
		$this->registerFromConfig();
	}

	/**
	 * Get service statistics
	 */
	public function getStats(): array
	{
		$total = $this->services->count();
		$authServices = $this->getAuthServices()->count();
		$publicServices = $this->getPublicServices()->count();
		$readonlyServices = $this->getReadonlyServices()->count();

		return [
			"total_services" => $total,
			"auth_services" => $authServices,
			"public_services" => $publicServices,
			"readonly_services" => $readonlyServices,
			"service_names" => $this->getServiceNames(),
		];
	}

	/**
	 * Validate all registered services
	 */
	public function validateServices(): array
	{
		$results = [];

		foreach ($this->services as $name => $serviceClass) {
			try {
				$service = $this->get($name);

				$results[$name] = [
					"status" => "valid",
					"class" => $serviceClass,
					"requires_auth" => $service->requiresAuth(),
					"readonly" => $service->isReadonly(),
					"methods" => $service->getAvailableMethods(),
				];
			} catch (\Exception $e) {
				$results[$name] = [
					"status" => "invalid",
					"class" => $serviceClass,
					"error" => $e->getMessage(),
				];
			}
		}

		return $results;
	}

	/**
	 * Get services grouped by authentication requirement
	 */
	public function getServicesGroupedByAuth(): array
	{
		return [
			"public" => $this->getPublicServices()->toArray(),
			"protected" => $this->getAuthServices()->toArray(),
		];
	}

	/**
	 * Find service by class name
	 */
	public function findByClass(string $className): ?string
	{
		return $this->services->search(function ($serviceClass) use ($className) {
			return $serviceClass === $className;
		});
	}

	/**
	 * Register multiple services at once
	 */
	public function registerMultiple(array $services): void
	{
		foreach ($services as $name => $serviceClass) {
			$this->register($name, $serviceClass);
		}
	}

	/**
	 * Check if any services are registered
	 */
	public function isEmpty(): bool
	{
		return $this->services->isEmpty();
	}

	/**
	 * Get services count
	 */
	public function count(): int
	{
		return $this->services->count();
	}

	/**
	 * Magic method for property access
	 */
	public function __get(string $name)
	{
		if ($this->has($name)) {
			return $this->get($name);
		}

		throw new \InvalidArgumentException("Service {$name} not found");
	}
}
