<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create("bank_countries", function (Blueprint $table) {
			$table->id();
			$table->string("code", 3)->unique();
			$table->string("name");
			$table->timestamps();

			$table->index("code");
		});

		Schema::create("bank_cities", function (Blueprint $table) {
			$table->id();
			$table->string("country_code", 3);
			$table->string("name");
			$table->timestamps();

			$table
				->foreign("country_code")
				->references("code")
				->on("bank_countries")
				->cascadeOnDelete();
			$table->index(["country_code", "name"]);
		});

		Schema::create("banks", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("city_id")
				->constrained("bank_cities")
				->cascadeOnDelete();
			$table->string("name");
			$table->string("branch")->nullable();
			$table->string("swift_code");
			$table->timestamps();

			$table->index("swift_code");
			$table->index("name");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("banks");
		Schema::dropIfExists("bank_cities");
		Schema::dropIfExists("bank_countries");
	}
};
