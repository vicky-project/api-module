<?php

namespace Modules\Api\Interfaces;

interface DataImporterContract
{
	public function import(): void;
	public function getType(): string;
}
