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
		Schema::create("quran_surahs", function (Blueprint $table) {
			$table->id();
			$table->integer("number")->unique();
			$table->string("name");
			$table->string("name_latin");
			$table->integer("number_of_verses");
			$table->string("place");
			$table->string("meaning");
			$table->text("description")->fullText();
			$table->json("audio_full")->nullable();
			$table->timestamps();
		});

		Schema::create("quran_verses", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("surah_id")
				->constrained("quran_surahs")
				->onDelete("cascade");
			$table->integer("surah_number");
			$table->integer("verse_number");
			$table->text("arabic_text")->fullText();
			$table->text("latin_text")->fullText();
			$table->text("translation")->fullText();
			$table->json("audio")->nullable();
			$table->timestamps();

			$table->index(["surah_number", "verse_number"]);

			$table
				->foreign("surah_number")
				->references("number")
				->on("quran_surahs")
				->onDelete("cascade");
		});

		Schema::create("asmaul_husnas", function (Blueprint $table) {
			$table->id();
			$table->integer("number")->unique();
			$table->string("latine");
			$table->string("arabic");
			$table->json("meaning")->nullable();
			$table->json("description")->nullable();
			$table->text("found")->nullable();
			$table->timestamps();
		});

		Schema::create("asmaul_husna_verse", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("asmaul_husna_id")
				->constrained()
				->onDelete("cascade");
			$table
				->foreignId("quran_verse_id")
				->constrained()
				->onDelete("cascade");
			$table->timestamps();

			$table->unique(["asmaul_husna_id", "quran_verse_id"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("asmaul_husna_verse");
		Schema::dropIfExists("asmaul_husnas");
		Schema::dropIfExists("quran_verses");
		Schema::dropIfExists("quran_surahs");
	}
};
