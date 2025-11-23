<?php

namespace Modules\Api\Repositories;

use Modules\Api\Interfaces\ApiRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements ApiRepositoryInterface
{
	protected $model;

	public function __construct(Model $model = null)
	{
		$this->model = $model;
	}

	public function all(array $columns = ["*"])
	{
		return $this->model->get($columns);
	}

	public function paginate(int $perPage = 15, array $columns = ["*"])
	{
		return $this->model->paginate($perPage, $columns);
	}

	public function find($id, array $columns = ["*"])
	{
		return $this->model->find($id, $columns);
	}

	public function findOrFail($id, array $columns = ["*"])
	{
		return $this->model->findOrFail($id, $columns);
	}

	public function create(array $data)
	{
		return $this->model->create($data);
	}

	public function update($id, array $data)
	{
		$model = $this->findOrFail($id);
		$model->update($data);
		return $model;
	}

	public function delete($id)
	{
		$model = $this->findOrFail($id);
		return $model->delete();
	}

	public function withFilters(array $filters)
	{
		$query = $this->model;

		foreach ($filters as $field => $value) {
			if (is_array($value)) {
				$query = $query->whereIn($field, $value);
			} else {
				$query = $query->where($field, $value);
			}
		}

		return $query;
	}

	/**
	 * Get model instance
	 */
	public function getModel()
	{
		return $this->model;
	}
}
