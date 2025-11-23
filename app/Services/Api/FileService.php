<?php

namespace Modules\Api\Services\Api;

use Illuminate\Support\Number;
use Illuminate\Support\Facades\Storage;
use Modules\Api\Core\BaseApiService;

class FileService extends BaseApiService
{
	protected string $name = "files";
	protected bool $requiresAuth = false;
	protected bool $readonly = true;

	public function index()
	{
		$storage = Storage::disk("public");
		$home = $this->request->type === "music" ? "Musics" : "Heavy Duty";
		$p = $this->request->p !== $home ? $this->request->p : null;
		if ($this->request->f) {
			return $storage->response($this->request->f);
		}
		$parentFolder =
			$p !== $home && $p !== null && str($p)->beforeLast("/") !== $home
				? str($p)->beforeLast("/")
				: null;

		$files = [];
		$folders = [];
		try {
			$dirs = $storage->directories($p ?? $home);
			$allFiles = $storage->files($p ?? $home);
			foreach ($dirs as $dir) {
				$folders[] = [
					"name" => str($dir)->afterLast("/"),
					"path" => $dir,
					"size" => null,
					"modified" => now()
						->parse($storage->lastModified($dir))
						->format("d-m-Y H:i:s"),
				];
			}
			foreach ($allFiles as $file) {
				$files[] = [
					"name" => str($file)->afterLast("/"),
					"path" => $file,
					"size" => Number::fileSize($storage->size($file)),
					"modified" => now()
						->parse($storage->lastModified($file))
						->format("d-m-Y H:i:s"),
				];
			}
		} catch (\Exception $e) {
			\Log::error($e->getMessage());
			$folders = [];
			$files = [];
		}

		return [
			"parentFolder" => $parentFolder,
			"folders" => $folders,
			"files" => $files,
		];
	}
}
