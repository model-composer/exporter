<?php namespace Model\Exporter;

abstract class DataExporter
{
	public function hasHeaderAt(int $page): bool
	{
		return $page === 1;
	}

	abstract public function setHeader(array $header, array $options): void;

	abstract public function convert(iterable $data, array $options): string;

	abstract public function getFileExtension(): string;

	abstract public function finalize(array $tmp_files, string $filePath, int $pages, array $options): void;
}
