<?php

namespace Modules\Api\Interfaces;

interface ApiResourceInterface
{
	/**
	 * Transform data untuk response
	 */
	public function transform($data);

	/**
	 * Tambahkan metadata
	 */
	public function withMeta(array $meta);

	/**
	 * Tambahkan links
	 */
	public function withLinks(array $links);
}
