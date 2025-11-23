<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class BankCountry extends Model
{
	protected $fillable = ["code", "name"];

	public function cities(): HasMany
	{
		return $this->hasMany(BankCity::class, "country_code", "code");
	}

	public function banks(): hasManyThrough
	{
		return $this->hasManyThrough(
			Bank::class,
			BankCity::class,
			"country_code",
			"id",
			"code",
			"id"
		);
	}
}
