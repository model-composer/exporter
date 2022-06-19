<?php namespace Model\Exporter\DataExporters;

use Model\Exporter\DataExporter;

class Html extends DataExporter
{
	private string $header;

	public function setHeader(array $header, array $options): void
	{
		$this->header = '<thead><tr>';
		foreach ($header as $column)
			$this->header .= '<th>' . htmlentities($column, ENT_QUOTES | ENT_IGNORE, 'UTF-8') . '</th>';
		$this->header .= '</tr></thead>';
	}

	public function convert(iterable $data, array $options): string
	{
		$text = '';
		if (isset($this->header))
			$text = $this->header . '<tbody>';

		foreach ($data as $row) {
			$text .= '<tr>';
			foreach ($row as $value)
				$text .= '<td>' . htmlentities($value, ENT_QUOTES | ENT_IGNORE, 'UTF-8') . '</td>';
			$text .= '</tr>';
		}

		return $text;
	}

	public function getFileExtension(): string
	{
		return 'html';
	}

	public function finalize(string $filePath, string $folder, int $pages, array $options): void
	{
		$file = fopen($filePath, 'w');

		fwrite($file, '<!DOCTYPE html>
<style>
table{max-width:100%;border-collapse: collapse}
th, td{border: solid #999 1px;font-size: 11px;padding: 5px}
</style>

<table>');

		for ($c = 1; $c <= $pages; $c++)
			fwrite($file, file_get_contents($folder . DIRECTORY_SEPARATOR . $c));

		fwrite($file, '</tbody>
</table>');

		fclose($file);
	}
}
