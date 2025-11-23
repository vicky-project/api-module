<?php

namespace Modules\Api\Interfaces;

interface ApiRepositoryInterface
{
	/**
	 * Get all records
	 */
	public function all(array $columns = ["*"]);

	/**
	 * Get paginated records
	 */
	public function paginate(int $perPage = 15, array $columns = ["*"]);

	/**
	 * Find record by ID
	 */
	public function find($id, array $columns = ["*"]);

	/**
	 * Find or fail
	 */
	public function findOrFail($id, array $columns = ["*"]);

	/**
	 * Create new record
	 */
	public function create(array $data);

	/**
	 * Update record
	 */
	public function update($id, array $data);

	/**
	 * Delete record
	 */
	public function delete($id);

	/**
	 * Apply filters
	 */
	public function withFilters(array $filters);
}
