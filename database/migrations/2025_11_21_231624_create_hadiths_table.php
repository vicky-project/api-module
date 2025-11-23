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
		Schema::create("hadith_books", function (Blueprint $table) {
			$table->string("id")->primary();
			$table->string("name");
			$table->integer("total_hadiths");
			$table->timestamps();
		});

		Schema::create("hadiths", function (Blueprint $table) {
			$table->id();
			$table->string("book_id");
			$table->integer("number");
			$table->text("arabic")->fullText();
			$table->text("translation")->fullText();
			$table->timestamps();

			$table->index(["book_id", "number"]);

			$table
				->foreign("book_id")
				->references("id")
				->on("hadith_books")
				->onDelete("cascade");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("hadiths");
		Schema::dropIfExists("hadith_books");
	}
};
