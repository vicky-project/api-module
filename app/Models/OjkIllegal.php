<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class OjkIllegal extends Model
{
	protected $table = "ojk_illegals";
	protected $guarded = ["id"];
	protected $casts = [
		"alias" => "array",
		"address" => "array",
		"web" => "array",
		"email" => "array",
		"phone" => "array",
		"activity_type" => "array",
	];
}
