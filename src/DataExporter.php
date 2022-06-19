<?php namespace Model\Exporter;

abstract class DataExporter
{
	abstract public function setHeader(array $header, array $options): void;

	abstract public function convert(iterable $data, array $options): string;

	abstract public function getFileExtension(): string;

	abstract public function finalize(string $filePath, string $folder, int $pages, array $options): void;
}
