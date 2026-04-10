<?php

$title = isset($pageTitle) ? (string) $pageTitle : 'Expense Register';
$styles = isset($pageStyles) && is_array($pageStyles) ? $pageStyles : [];
$bodyClassName = isset($bodyClass) ? (string) $bodyClass : '';

function buildAssetUrl(string $assetPath): string
{
	
	$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
	$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
	
	$scriptDir = dirname($scriptName);
	
	if (strpos($requestUri, '?') !== false) {
		$requestUri = substr($requestUri, 0, strpos($requestUri, '?'));
	}
	
	
	$basePath = rtrim($scriptDir, '/');
	$assetUrl = $basePath . '/' . ltrim($assetPath, '/');
	
	return $assetUrl;
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<?php foreach ($styles as $stylePath): ?>
		<link rel="stylesheet" href="<?php echo htmlspecialchars(buildAssetUrl((string) $stylePath), ENT_QUOTES, 'UTF-8'); ?>">
	<?php endforeach; ?>
</head>
<body class="<?php echo htmlspecialchars($bodyClassName, ENT_QUOTES, 'UTF-8'); ?>">
