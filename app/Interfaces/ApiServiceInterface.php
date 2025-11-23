<?php

namespace Modules\Api\Interfaces;

use Illuminate\Http\JsonResponse;

interface ApiServiceInterface
{
	/**
	 * Nama service untuk registry
	 */
	public function getName(): string;

	/**
	 * Apakah service ini membutuhkan authentication
	 */
	public function requiresAuth(): bool;

	/**
	 * Apakah service ini readonly (hanya GET)
	 */
	public function isReadonly(): bool;

	/**
	 * Eksekusi method service
	 */
	public function execute(string $method, array $parameters = []): mixed;

	/**
	 * Validasi input parameters
	 */
	public function validate(array $data, array $rules): array;

	/**
	 * Get available methods
	 */
	public function getAvailableMethods(): array;
}
