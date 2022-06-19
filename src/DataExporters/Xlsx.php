<?php namespace Model\Exporter\DataExporters;

use ExcelMerge\ExcelMerge;
use Model\Exporter\DataExporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Xlsx extends DataExporter
{
	private bool $headerSet = false;
	private Spreadsheet $spreadsheet;
	private Worksheet $sheet;

	public function __construct()
	{
		$this->spreadsheet = new Spreadsheet();
		$this->sheet = $this->spreadsheet->getActiveSheet();
	}

	public function setHeader(array $header, array $options): void
	{
		$letter = 'A';
		foreach ($header as $column) {
			$this->sheet->setCellValue($letter . '1', $column);
			$letter = $this->increaseLetter($letter);
		}

		$this->sheet->getStyle('A1:' . $letter . '1')->getFont()->setBold(true);

		$this->headerSet = true;
	}

	public function convert(iterable $data, array $options): string
	{
		$rowNumber = $this->headerSet ? 2 : 1;

		foreach ($data as $row) {
			$letter = 'A';
			foreach ($row as $value) {
				$this->sheet->setCellValue($letter . $rowNumber, $value);
				$letter = $this->increaseLetter($letter);
			}

			$rowNumber++;
		}

		foreach ($this->sheet->getColumnIterator() as $column)
			$this->sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);

		ob_start();
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
		$writer->save('php://output');
		return ob_get_clean();
	}

	public function getFileExtension(): string
	{
		return 'xlsx';
	}

	public function finalize(array $tmp_files, string $filePath, int $pages, array $options): void
	{
		$merged = new ExcelMerge($tmp_files);
		$merged->save($filePath);
	}

	private function increaseLetter(string $letter): string
	{
		$lettere = array_map(function ($l) {
			return ord($l);
		}, str_split($letter));

		$lettere = array_reverse($lettere);

		$riporto = 1;
		foreach ($lettere as &$l) {
			$l += $riporto;
			$riporto = 0;

			if ($l > 90) {
				$riporto = $l - 90;
				$l = 65;
			}
		}
		unset($l);

		if ($riporto > 0)
			$lettere[] = 65;

		$lettere = array_reverse($lettere);

		return implode('', array_map(function ($l) {
			return chr($l);
		}, $lettere));
	}
}
