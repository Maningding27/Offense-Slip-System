<?php
$csvUrl = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vToMROxujfLJpJtKdxu06HlC2tX7Okm2hN6kPNdxmoLwgyVQcZqwccHJ8gzsNTwvizyYHhiUcvl8874/pub?gid=1693812363&single=true&output=csv';
$rowNumber = isset($_GET['row']) ? (int)$_GET['row'] : 0;
if ($rowNumber < 2) { die('Invalid row'); }

function fetchCsv(string $url): array {
	$ctx = stream_context_create([ 'http' => [ 'timeout' => 10 ] ]);
	$csv = @file_get_contents($url, false, $ctx);
	if ($csv === false) { $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]); $csv = curl_exec($ch); curl_close($ch); }
	if ($csv === false) return [];
	$rows = [];
	$fh = fopen('php://temp', 'r+'); fwrite($fh, $csv); rewind($fh);
	while (($data = fgetcsv($fh)) !== false) { $rows[] = $data; }
	fclose($fh);
	return $rows;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function getGoogleDriveImage($url) {
	// Extract file ID from Google Drive URL
	$fileId = '';
	if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
		$fileId = $matches[1];
	} elseif (preg_match('/[?&]id=([a-zA-Z0-9-_]+)/', $url, $matches)) {
		$fileId = $matches[1];
	}
	
	if (!$fileId) return null;
	
	// Create proxy URL
	return "image_proxy.php?id=" . urlencode($fileId);
}

$rows = fetchCsv($csvUrl);
if (!$rows || count($rows) < $rowNumber) { die('Row not found'); }
$headers = $rows[0];
$dataRow = $rows[$rowNumber - 1];
$data = [];
foreach ($headers as $i => $key) { $data[trim($key)] = $dataRow[$i] ?? ''; }

$fields = [
	'Date of Report' => $data['Date of Report'] ?? ($data['Timestamp'] ?? ''),
	'Semester/Academic Year' => $data['Semester/Academic Year'] ?? '',
	'Full Name' => $data['Full Name'] ?? ($data['Student Name'] ?? ''),
	'Year & Level' => $data['Year & Level'] ?? ($data['Grade/Year Level & Strand/Program'] ?? ''),
	'Offenses' => $data['Offenses'] ?? ($data['Offense/s'] ?? ''),
	'Details of Offense/s' => $data['Details of Offense/s'] ?? '',
	'Location' => $data['Location'] ?? '',
	'Date/Time' => $data['Date/Time'] ?? '',
	'Remarks' => $data['Remarks'] ?? '',
	'Student Signature' => $data['Student Signature'] ?? '',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Preview Offense Slip</title>
	<style>
		body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, sans-serif; background: #f8fafc; }
		.toolbar { padding: 10px; background: #111827; color: #fff; display: flex; gap: 8px; align-items: center; }
		.btn { background: #10b981; color: #fff; border: 0; padding: 8px 12px; border-radius: 6px; cursor: pointer; }
		.wrapper { display: flex; justify-content: center; padding: 16px; }
		.page { position: relative; width: 1200px; }
		.page::before { content: ""; display: block; padding-top: calc(1963 / 2550 * 100%); }
		.template { position: absolute; inset: 0; background: #fff url('dcf-offenseslip.jpg') no-repeat center top; background-size: 100% 100%; }
		.canvas { position: absolute; inset: 0; }
		.field { position: absolute; font-size: 14px; line-height: 1.25; }

		/* LEFT SLIP positions (aligned to red marks) */
		.l-date { left: 25%; top: 27.1%; width: 30%; }
		.l-sem { left: 25%; top: 30.2%; width: 30%; }
		.l-name { left: 25%; top: 33.2%; width: 30%; }
		.l-level { left: 25%; top: 36.2%; width: 30%; }
		.l-offenses { left: 12%; top: 45.8%; width: 45%; white-space: pre-wrap; }
		.l-details { left: 12%; top: 52%; width: 60%; white-space: pre-wrap; }
		.l-location { left: 12%; top: 57.3%; width: 25%; }
		.l-datetime { left: 30.4%; top: 58.5%; width: 25%; }
		.l-remarks { left: 8%; top: 70.9%; width: 72%; white-space: pre-wrap; }
		.l-officials { left: 7.6%; top: 78.5%; width: 72%; white-space: pre-wrap; }

		/* RIGHT SLIP positions: same offsets + 50% width */
		.r-date { left: calc(24.5% + 50%); top: 27.1%; width: 30%; }
		.r-sem { left: calc(24.5% + 50%); top: 30.2%; width: 30%;}
		.r-name { left: calc(24.5% + 50%); top: 33.2%; width: 30%; }
		.r-level { left: calc(24.5% + 50%); top: 36.2%; width: 30%; }
		.r-offenses { left: calc(11.5% + 50%); top: 45.8%; width: 45%; white-space: pre-wrap; }
		.r-details { left: calc(11.5% + 50%); top: 52%; width: 60%; white-space: pre-wrap; }
		.r-location { left: calc(11.5% + 50%); top: 57.3%; width: 25%; }
		.r-datetime { left: calc(30% + 50%); top: 58.5%; width: 25%; }
		.r-remarks { left: calc(8% + 50%); top: 70.9%; width: 72%; white-space: pre-wrap; }
		.r-officials { left: calc(7.2% + 50%); top: 78.5%; width: 72%; white-space: pre-wrap; }
		
		/* Signature image styles */
		.l-signature { left: 26%; top: 74.5%; width: 25%; }
		.r-signature { left: calc(26% + 50%); top: 74.5%; width: 25%; }
		.signature-img { width: 70%; }
		
		/* Student signature styles */
		.l-student-signature { left: 4%; top: 87%; width: 25%; }
		.r-student-signature { left: calc(4% + 50%); top: 87%; width: 25%; }
		.student-signature-img { width: 70%; height: auto;}
	</style>
</head>
<body>
	<div class="toolbar">
		<span>Preview</span>
		<button id="btnDownload" class="btn">Download PDF</button>
		<span style="margin-left: 20px; font-size: 12px;">
			Student Signature: <?php 
			if (!empty($fields['Student Signature'])) {
				$signatureUrl = $fields['Student Signature'];
				if (strpos($signatureUrl, 'drive.google.com') !== false) {
					$signatureUrl = getGoogleDriveImage($signatureUrl);
				}
				echo 'Found: ' . substr($signatureUrl, 0, 50) . '...';
			} else {
				echo 'Not found';
			}
			?>
		</span>
	</div>
	<div class="wrapper">
		<div id="slip" class="page">
			<div class="template"></div>
			<div class="canvas">
				<!-- Left slip -->
				<div class="field l-date"><?= h($fields['Date of Report']) ?></div>
				<div class="field l-sem"><?= h($fields['Semester/Academic Year']) ?></div>
				<div class="field l-name"><?= h($fields['Full Name']) ?></div>
				<div class="field l-level"><?= h($fields['Year & Level']) ?></div>
				<div class="field l-offenses"><?= nl2br(h($fields['Offenses'])) ?></div>
				<div class="field l-details"><?= nl2br(h($fields['Details of Offense/s'])) ?></div>
				<div class="field l-location"><?= h($fields['Location']) ?></div>
				<div class="field l-datetime"><?= h($fields['Date/Time']) ?></div>
				<div class="field l-remarks"><?= nl2br(h($fields['Remarks'])) ?></div>
				<div class="field l-officials">Simon L. Maningding</div>
				<div class="field l-signature">
					<img src="signature.png" alt="Signature" class="signature-img">
				</div>
				<div class="field l-student-signature">
					<?php if (!empty($fields['Student Signature'])): ?>
						<?php 
						$signatureUrl = $fields['Student Signature'];
						// Use proxy for Google Drive images
						if (strpos($signatureUrl, 'drive.google.com') !== false) {
							$signatureUrl = getGoogleDriveImage($signatureUrl);
						}
						?>
						<img src="<?= h($signatureUrl) ?>" alt="Student Signature" class="student-signature-img" onerror="this.style.display='none'; console.log('Image failed to load:', this.src);">
					<?php else: ?>
						<div style="color: #999; font-size: 12px;">No signature</div>
					<?php endif; ?>
				</div>

				<!-- Right slip (same data) -->
				<div class="field r-date"><?= h($fields['Date of Report']) ?></div>
				<div class="field r-sem"><?= h($fields['Semester/Academic Year']) ?></div>
				<div class="field r-name"><?= h($fields['Full Name']) ?></div>
				<div class="field r-level"><?= h($fields['Year & Level']) ?></div>
				<div class="field r-offenses"><?= nl2br(h($fields['Offenses'])) ?></div>
				<div class="field r-details"><?= nl2br(h($fields['Details of Offense/s'])) ?></div>
				<div class="field r-location"><?= h($fields['Location']) ?></div>
				<div class="field r-datetime"><?= h($fields['Date/Time']) ?></div>
				<div class="field r-officials">Simon L. Maningding</div>
				<div class="field r-signature">
					<img src="signature.png" alt="Signature" class="signature-img">
				</div>
				<div class="field r-student-signature">
					<?php if (!empty($fields['Student Signature'])): ?>
						<?php 
						$signatureUrl = $fields['Student Signature'];
						// Use proxy for Google Drive images
						if (strpos($signatureUrl, 'drive.google.com') !== false) {
							$signatureUrl = getGoogleDriveImage($signatureUrl);
						}
						?>
						<img src="<?= h($signatureUrl) ?>" alt="Student Signature" class="student-signature-img" onerror="this.style.display='none'; console.log('Image failed to load:', this.src);">
					<?php else: ?>
						<div style="color: #999; font-size: 12px;">No signature</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
	<script>
		const btn = document.getElementById('btnDownload');
		btn.addEventListener('click', async () => {
			const el = document.getElementById('slip');
			const canvas = await html2canvas(el, { scale: 2 });
			const imgData = canvas.toDataURL('image/jpeg', 0.95);
			const { jsPDF } = window.jspdf;
			const pdf = new jsPDF({ orientation: 'landscape', unit: 'pt', format: [canvas.width, canvas.height] });
			pdf.addImage(imgData, 'JPEG', 0, 0, canvas.width, canvas.height);
			pdf.save('offense-slip.pdf');
		});
	</script>
</body>
</html>
