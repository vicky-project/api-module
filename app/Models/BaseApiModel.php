<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Api\Traits\Filterable;
use Modules\Api\Traits\Loggable;

class BaseApiModel extends Model
{
	use Filterable, Loggable;

	/**
	 * The attributes that are mass assignable.
	 */
	protected $guarded = [];

	/**
	 * Default ordering
	 */
	public function scopeDefaultOrder($query)
	{
		return $query->orderBy("created_at", "desc");
	}

	/**
	 * Active scope
	 */
	public function scopeActive($query)
	{
		return $query->where("is_active", true);
	}

	/**
	 * Search scope
	 */
	public function scopeSearch($query, string $search)
	{
		if (property_exists($this, "searchable") && !empty($this->searchable)) {
			return $query->where(function ($q) use ($search) {
				foreach ($this->searchable as $column) {
					$q->orWhere($column, "LIKE", "%{$search}%");
				}
			});
		}

		return $query;
	}
}
