<?php

class SpreadsheetExportService
{
	private function normalizeSheetTitle(string $title): string
	{
		$title = trim($title);
		if ($title === '') {
			$title = 'Export';
		}

		$title = preg_replace('~[\\/?*\[\]:]~', ' ', $title) ?? 'Export';
		$title = trim(preg_replace('/\s+/', ' ', $title) ?? 'Export');

		return substr($title, 0, 31);
	}

	private function normalizeFilename(string $filename): string
	{
		$filename = trim(basename($filename));
		if ($filename === '') {
			$filename = 'export.xlsx';
		}

		if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx') {
			$filename .= '.xlsx';
		}

		return $filename;
	}

	public function streamXlsx(string $filename, array $headers, array $rows, string $sheetTitle = 'Export'): void
	{
		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle($this->normalizeSheetTitle($sheetTitle));

		$headerRow = 1;
		foreach (array_values($headers) as $columnIndex => $header) {
			$columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
			$sheet->setCellValue($columnLetter . $headerRow, (string) $header);
		}

		$sheet->getStyle('1:1')->getFont()->setBold(true);
		$sheet->freezePane('A2');

		foreach (array_values($rows) as $rowIndex => $row) {
			$excelRow = $rowIndex + 2;
			foreach (array_values($row) as $columnIndex => $value) {
				$columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
				$sheet->setCellValue($columnLetter . $excelRow, $value);
			}
		}

		$sheet->setAutoFilter($sheet->calculateWorksheetDimension());

		foreach (range(1, count($headers)) as $columnIndex) {
			$columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
			$sheet->getColumnDimension($columnLetter)->setAutoSize(true);
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . addslashes($this->normalizeFilename($filename)) . '"');
		header('Cache-Control: max-age=0');

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		$spreadsheet->disconnectWorksheets();
		exit;
	}
}