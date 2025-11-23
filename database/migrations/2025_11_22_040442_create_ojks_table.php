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
		Schema::create("ojk_illegals", function (Blueprint $table) {
			$table->id();
			$table->string("name");
			$table->json("alias")->nullable();
			$table->json("address")->nullable();
			$table->json("web")->nullable();
			$table->json("email")->nullable();
			$table->json("phone")->nullable();
			$table->string("entity_type");
			$table->json("activity_type")->nullable();
			$table->date("input_date")->nullable();
			$table->text("description")->nullable();
			$table->timestamps();
		});

		Schema::create("ojk_apps", function (Blueprint $table) {
			$table->id();
			$table->string("name");
			$table->string("url")->nullable();
			$table->string("owner");
			$table->timestamps();
		});

		Schema::create("ojk_products", function (Blueprint $table) {
			$table->id();
			$table->string("name");
			$table->string("management");
			$table->string("custodian");
			$table->string("type");
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("ojk_products");
		Schema::dropIfExists("ojk_apps");
		Schema::dropIfExists("ojk_illegals");
	}
};
