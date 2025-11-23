<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankCity extends Model
{
	protected $fillable = ["country_code", "name"];

	public function banks(): HasMany
	{
		return $this->hasMany(Bank::class, "city_id");
	}

	public function country(): BelongsTo
	{
		return $this->belongsTo(BankCountry::class, "country_code", "code");
	}
}
