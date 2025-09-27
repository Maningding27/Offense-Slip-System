<?php
$csvUrl = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vToMROxujfLJpJtKdxu06HlC2tX7Okm2hN6kPNdxmoLwgyVQcZqwccHJ8gzsNTwvizyYHhiUcvl8874/pub?gid=1693812363&single=true&output=csv';

function fetchCsv(string $url): array {
	$ctx = stream_context_create([ 'http' => [ 'timeout' => 10 ] ]);
	$csv = @file_get_contents($url, false, $ctx);
	if ($csv === false) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
		$csv = curl_exec($ch);
		curl_close($ch);
	}
	if ($csv === false) return [];
	$rows = [];
	$fh = fopen('php://temp', 'r+');
	fwrite($fh, $csv);
	rewind($fh);
	while (($data = fgetcsv($fh)) !== false) { $rows[] = $data; }
	fclose($fh);
	return $rows;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$rows = fetchCsv($csvUrl);
$headers = $rows ? $rows[0] : [];
$bodyRows = $rows ? array_slice($rows, 1) : [];
$studentSigIdx = -1;
foreach ($headers as $i => $h) { if (trim($h) === 'Student Signature') { $studentSigIdx = $i; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Offense Slip Responses</title>
	<style>
		body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, sans-serif; margin: 20px; }
		table { border-collapse: collapse; width: 100%; }
		th, td { border: 1px solid #e5e7eb; padding: 8px 10px; font-size: 14px; }
		th { background: #f3f4f6; text-align: left; }
		.btn { display: inline-block; padding: 6px 10px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px; font-size: 12px; }
	</style>
</head>
<body>
	<h3>Google Form Responses (<?= count($bodyRows) ?>)</h3>
	<?php if (!$rows): ?>
		<p>Could not load the published sheet. Ensure it is published to web (CSV) and try again.</p>
	<?php else: ?>
		<table>
			<thead>
				<tr>
					<?php foreach ($headers as $i => $head): ?>
						<th><?= h($head) ?></th>
						<?php if ($studentSigIdx === $i): ?><th>Generate</th><?php endif; ?>
					<?php endforeach; ?>
					<?php if ($studentSigIdx === -1): ?><th>Generate</th><?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($bodyRows as $rIdx => $cols): $sheetRowNumber = $rIdx + 2; ?>
				<tr>
					<?php foreach ($headers as $i => $head): ?>
						<td><?= h($cols[$i] ?? '') ?></td>
						<?php if ($studentSigIdx === $i): ?>
							<td><a class="btn" href="preview.php?row=<?= (int)$sheetRowNumber ?>">Generate</a></td>
						<?php endif; ?>
					<?php endforeach; ?>
					<?php if ($studentSigIdx === -1): ?>
						<td><a class="btn" href="preview.php?row=<?= (int)$sheetRowNumber ?>">Generate</a></td>
					<?php endif; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</body>
</html>

