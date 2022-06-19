<?php namespace Model\Exporter\DataExporters;

use League\Csv\Writer;
use Model\Exporter\DataExporter;

class Csv extends DataExporter
{
	private Writer $csv;

	public function __construct()
	{
		$this->csv = Writer::createFromString();
	}

	public function setHeader(array $header, array $options): void
	{
		$this->csv->setDelimiter($options['delimiter'] ?? ',');
		$this->csv->insertOne($header);
	}

	public function convert(iterable $data, array $options): string
	{
		$this->csv->setDelimiter($options['delimiter'] ?? ',');
		$this->csv->insertAll($data);
		return $this->csv->toString();
	}

	public function getFileExtension(): string
	{
		return 'csv';
	}

	public function finalize(array $tmp_files, string $filePath, int $pages, array $options): void
	{
		$file = fopen($filePath, 'w');

		foreach ($tmp_files as $tmp_file)
			fwrite($file, file_get_contents($tmp_file));

		fclose($file);
	}
}
