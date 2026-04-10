<?php

class BudgetFileParser
{
	public function parseUploadedFile(string $filePath, string $originalFileName): array
	{
		$extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

		if ($extension === 'csv') {
			return $this->parseCsv($filePath);
		}

		if (in_array($extension, ['xlsx', 'xls'], true)) {
			return $this->parseSpreadsheet($filePath);
		}

		if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
			return $this->parseImageWithOcr($filePath);
		}

		return [
			'rows' => [],
			'warnings' => [],
			'errors' => ['Unsupported file type. Allowed: CSV, XLSX/XLS, JPG/JPEG, PNG.'],
		];
	}

	private function parseCsv(string $filePath): array
	{
		$handle = fopen($filePath, 'rb');
		if ($handle === false) {
			return [
				'rows' => [],
				'warnings' => [],
				'errors' => ['Unable to open CSV file.'],
			];
		}

		$headerRow = fgetcsv($handle);
		if (!is_array($headerRow)) {
			fclose($handle);
			return [
				'rows' => [],
				'warnings' => [],
				'errors' => ['CSV file is empty or missing header row.'],
			];
		}

		$normalizedHeaders = array_map(function ($header): string {
			return $this->normalizeHeader((string) $header);
		}, $headerRow);

		$rows = [];
		while (($row = fgetcsv($handle)) !== false) {
			if (!is_array($row)) {
				continue;
			}

			$assoc = [];
			$hasValue = false;
			foreach ($normalizedHeaders as $index => $headerKey) {
				$value = isset($row[$index]) ? trim((string) $row[$index]) : '';
				if ($value !== '') {
					$hasValue = true;
				}
				$assoc[$headerKey] = $value;
			}

			if ($hasValue) {
				$rows[] = $assoc;
			}
		}

		fclose($handle);

		return [
			'rows' => $rows,
			'warnings' => [],
			'errors' => [],
		];
	}

	private function parseSpreadsheet(string $filePath): array
	{
		$autoloadError = $this->loadComposerAutoload();
		if ($autoloadError !== null) {
			return [
				'rows' => [],
				'warnings' => [],
				'errors' => [$autoloadError],
			];
		}
		$ioFactoryClass = '\\PhpOffice\\PhpSpreadsheet\\IOFactory';

		if (!class_exists($ioFactoryClass)) {
			return [
				'rows' => [],
				'warnings' => [],
				'errors' => ['Server issue.'],
			];
		}

		try {
			$spreadsheet = $ioFactoryClass::load($filePath);
			$sheet = $spreadsheet->getActiveSheet();
			$data = $sheet->toArray(null, true, true, false);
		} catch (Throwable $error) {
			return [
				'rows' => [],
				'warnings' => [],
				'errors' => ['Unable to parse spreadsheet file.'],
			];
		}

		if (count($data) < 1 || !is_array($data[0])) {
			return [
				'rows' => [],
				'warnings' => [],
				'errors' => ['Spreadsheet file is empty.'],
			];
		}

		$headerRow = array_map(function ($value): string {
			return $this->normalizeHeader((string) $value);
		}, $data[0]);

		$rows = [];
		for ($i = 1; $i < count($data); $i++) {
			$current = is_array($data[$i]) ? $data[$i] : [];
			$assoc = [];
			$hasValue = false;
			foreach ($headerRow as $index => $headerKey) {
				$value = isset($current[$index]) ? trim((string) $current[$index]) : '';
				if ($value !== '') {
					$hasValue = true;
				}
				$assoc[$headerKey] = $value;
			}
			if ($hasValue) {
				$rows[] = $assoc;
			}
		}

		return [
			'rows' => $rows,
			'warnings' => [],
			'errors' => [],
		];
	}

	private function parseImageWithOcr(string $filePath): array
	{
		$tesseractBinary = $this->resolveTesseractBinary();
		if ($tesseractBinary === null) {
			return [
				'rows' => [],
				'warnings' => [],
				'errors' => ['Server issue.'],
			];
		}

		$command = escapeshellarg($tesseractBinary) . ' ' . escapeshellarg($filePath) . ' stdout 2>/dev/null';
		$output = (string) shell_exec($command);

		if (trim($output) === '') {
			return [
				'rows' => [],
				'warnings' => [],
				'errors' => ['Could not extract readable text from image.'],
			];
		}

		$parsed = $this->parseKeyValueText($output);
		if (empty($parsed)) {
			return [
				'rows' => [],
				'warnings' => [],
				'errors' => ['Process completed but no recognizable budget fields were found.'],
			];
		}

		return [
			'rows' => [$parsed],
			'warnings' => ['Please verify imported values.'],
			'errors' => [],
		];
	}

	private function parseKeyValueText(string $text): array
	{
		$result = [];
		$lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

		foreach ($lines as $line) {
			$line = trim((string) $line);
			if ($line === '') {
				continue;
			}

			$delimiterPos = strpos($line, ':');
			if ($delimiterPos === false) {
				$delimiterPos = strpos($line, '=');
			}
			if ($delimiterPos === false) {
				continue;
			}

			$key = trim(substr($line, 0, $delimiterPos));
			$value = trim(substr($line, $delimiterPos + 1));
			if ($key === '' || $value === '') {
				continue;
			}

			$normalized = $this->normalizeHeader($key);
			$result[$normalized] = $value;
		}

		return $result;
	}

	private function resolveTesseractBinary(): ?string
	{
		$candidates = [
			'tesseract',
			'/opt/homebrew/bin/tesseract',
			'/usr/local/bin/tesseract',
		];

		foreach ($candidates as $candidate) {
			if ($candidate !== 'tesseract' && is_executable($candidate)) {
				return $candidate;
			}

			$resolved = (string) shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null');
			$resolved = trim($resolved);
			if ($resolved !== '') {
				return $resolved;
			}
		}

		return null;
	}

	private function normalizeHeader(string $header): string
	{
		$normalized = strtolower(trim($header));
		$normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
		$normalized = trim($normalized, '_');

		$aliases = [
			'dept' => 'department',
			'department_name' => 'department',
			'department_code' => 'department',
			'year' => 'budget_fiscal_year',
			'fiscal_year' => 'budget_fiscal_year',
			'period' => 'budget_fiscal_period',
			'fiscal_period' => 'budget_fiscal_period',
			'category' => 'budget_category',
			'category_name' => 'budget_category',
			'category_id' => 'budget_category_id',
			'amount' => 'budget_allocated_amount',
			'allocated_amount' => 'budget_allocated_amount',
			'budget_amount' => 'budget_allocated_amount',
			'currency' => 'budget_currency',
			'notes' => 'budget_notes',
			'description' => 'budget_notes',
		];

		return $aliases[$normalized] ?? $normalized;
	}

	private function loadComposerAutoload(): ?string
	{
		
		$autoloadPath = ROOT_PATH . '/vendor/autoload.php';
		if (is_file($autoloadPath)) {
			try {
				require_once $autoloadPath;
			} catch (Throwable $error) {
				$message = (string) $error->getMessage();
				if (stripos($message, 'Composer detected issues in your platform') !== false) {
					return 'Excel process is unavailable.';
				}

				return 'Excel process is currently unavailable.';
			}
			return null;
		}

		return 'Excel process is unavailable.';
	}
}
