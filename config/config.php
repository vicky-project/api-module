<?php

return [
	/*
    |--------------------------------------------------------------------------
    | API Service Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi dasar untuk module ApiService
    |
    */
	"version" => env("API_VERSION", "v1"),
	"prefix" => env("API_PREFIX", "api"),
	"name" => env("API_NAME", "Laravel API Service"),

	/*
    |--------------------------------------------------------------------------
    | Authentication & Authorization
    |--------------------------------------------------------------------------
    |
    | Konfigurasi autentikasi untuk API services
    | - enabled: true/false untuk enable authentication
    | - middleware: middleware yang digunakan untuk auth
    | - public_services: service yang bisa diakses tanpa auth
    |
    */
	"auth" => [
		"enabled" => env("API_AUTH_ENABLED", false),
		"middleware" => env("API_AUTH_MIDDLEWARE", "auth:api"),
		"public_services" => ["health", "docs", "services"],
	],

	/*
    |--------------------------------------------------------------------------
    | Service Registration
    |--------------------------------------------------------------------------
    |
    | Daftar manual semua service API yang tersedia
    | Format: 'service_name' => 'Fully\Qualified\Class'
    |
    | Service yang terdaftar di sini akan otomatis available di API
    |
    */
	"services" => [
		// Built-in services
		"files" => \Modules\Api\Services\Api\FileService::class,
		"telegram" => \Modules\Api\Services\Api\TelegramReportService::class,
		"quran" => \Modules\Api\Services\Api\QuranService::class,
		"asmaul_husna" => \Modules\Api\Services\Api\AsmaulHusnaService::class,
		"hadith" => \Modules\Api\Services\Api\HadithService::class,

		// Your custom services - TAMBAHKAN SERVICE BARU DI SINI
		// 'users' => \App\Services\Api\UserService::class,
		// 'products' => \Modules\Product\Services\Api\ProductService::class,
	],

	/*
	|--------------------------------------------------------------------------
	| Configuration seeder to feed data.
	|--------------------------------------------------------------------------
	*/
	"importers" => [
		"options" => [
			"max_retries" => env("API_MAX_RETRIES", 3),
			"http_timeout" => env("API_HTTP_TIMEOUT", 300),
			"min_file_size" => env("API_MIN_FILE_SIZE", 1024),
			"retry_delay" => env("API_RETRY_DELAY", 1000),
			"connect_timeout" => env("API_CONNECT_TIMEOUT", 30),
			"chunk_size" => env("API_CHUNKSIZE", 200),
		],

		// PHP ini configuration
		"ini" => [
			"memory_limit" => env("API_MEMORY_LIMIT", "1024M"), // 1Gb
			"max_execution_time" => env("API_MAX_EXECUTION_TIME", 5400), // 1 jam
		],

		/*
		  |----------------------------------
		  | Do not edit URL value if you don't have any valid url to populate date.
		  |-----------------------------------
		  |
		  */
		"services" => [
			"quran" => [
				"url" => "https://vickyserver.my.id/data/quran/quran_data.json",
				"class" => \Modules\Api\Services\Importers\QuranImporter::class,
			],
			"asmaul_husna" => [
				"config" => [
					"old_url" => "https://islamic-api.vwxyz.id/asmaulhusna",
					"new_url" =>
						"https://raw.githubusercontent.com/KabDeveloper/99-Names-Of-Allah/refs/heads/main/99_Names_Of_Allah.json",
				],
				"class" => \Modules\Api\Services\Importers\AsmaulHusnaImporter::class,
			],
			"hadith" => [
				"url" => "https://vickyserver.my.id/data/hadiths/hadiths_data.json",
				"class" => \Modules\Api\Services\Importers\HadithImporter::class,
			],
			"ojk" => [
				"url" => "https://vickyserver.my.id/data/ojk/ojk_data.json",
				"class" => \Modules\Api\Services\Importers\OjkImporter::class,
			],
			"swift_global" => [
				"url" =>
					"https://vickyserver.my.id/data/swift_global/swift_global_data.json",
				"class" => \Modules\Api\Services\Importers\SwiftGlobalImporter::class,
			],
		],
	],

	/*
    |--------------------------------------------------------------------------
    | Response Standards
    |--------------------------------------------------------------------------
    |
    | Standar format response untuk semua API endpoints
    |
    */
	"response" => [
		"format" => [
			"success" => "success",
			"data" => "data",
			"message" => "message",
			"errors" => "errors",
			"timestamp" => "timestamp",
			"metadata" => "meta",
		],
		"include_timestamp" => true,
		"include_metadata" => true,
	],

	/*
    |--------------------------------------------------------------------------
    | Database Options
    |--------------------------------------------------------------------------
    |
    | Opsi database (opsional - tidak wajib digunakan)
    |
    */
	"database" => [
		"enabled" => env("API_DATABASE_ENABLED", false),
		"logging" => env("API_LOGGING_ENABLED", false),
	],

	/*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting untuk API requests
    |
    */
	"rate_limiting" => [
		"enabled" => env("API_RATE_LIMITING", true),
		"max_attempts" => env("API_RATE_LIMIT", 60),
		"decay_minutes" => env("API_RATE_DECAY", 1),
	],

	/*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Cross-Origin Resource Sharing configuration
    |
    */
	"cors" => [
		"enabled" => env("API_CORS_ENABLED", true),
		"allowed_origins" => ["*"],
		"allowed_methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
		"allowed_headers" => ["Content-Type", "Authorization", "X-Requested-With"],
	],

	/*
    |--------------------------------------------------------------------------
    | TELEGRAM Report Configuration
    |--------------------------------------------------------------------------
    |
    | To send report telegram message.
    |
    */
	"telegram" => [
		"bot_token" => env("TELEGRAM_BOT_TOKEN"),
		"chat_id" => env("TELEGRAM_CHAT_ID"),
		"api_url" => env("TELEGRAM_API_URL", "https://api.telegram.org/bot"),
	],
];
