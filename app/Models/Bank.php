<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bank extends Model
{
	protected $fillable = ["city_id", "name", "branch", "swift_code"];

	public function city(): BelongsTo
	{
		return $this->belongsTo(BankCity::class);
	}
}
