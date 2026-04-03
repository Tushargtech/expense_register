<?php

$title = isset($pageTitle) ? (string) $pageTitle : 'Expense Register';
$styles = isset($pageStyles) && is_array($pageStyles) ? $pageStyles : [];
$bodyClassName = isset($bodyClass) ? (string) $bodyClass : '';
$configuredBasePath = isset($envConfig['app']['base_path']) ? (string) $envConfig['app']['base_path'] : '';
$runtimeBasePath = (string) dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $configuredBasePath !== '' ? $configuredBasePath : $runtimeBasePath;

/**
 * Build a browser-safe URL for local assets.
 *
 * This project folder includes a space in "Project Structure", so a literal
 * path can fail in some clients. We encode each URL segment to keep slashes
 * intact while safely handling spaces and special characters.
 */
function buildAssetUrl(string $basePath, string $assetPath): string
{
	$joinedPath = '/' . trim($basePath, '/') . '/' . ltrim($assetPath, '/');
	$segments = explode('/', $joinedPath);

	foreach ($segments as $index => $segment) {
		if ($segment === '') {
			continue;
		}
		$segments[$index] = rawurlencode($segment);
	}

	return implode('/', $segments);
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<?php foreach ($styles as $stylePath): ?>
		<link rel="stylesheet" href="<?php echo htmlspecialchars(buildAssetUrl($basePath, (string) $stylePath), ENT_QUOTES, 'UTF-8'); ?>">
	<?php endforeach; ?>
</head>
<body class="<?php echo htmlspecialchars($bodyClassName, ENT_QUOTES, 'UTF-8'); ?>">
