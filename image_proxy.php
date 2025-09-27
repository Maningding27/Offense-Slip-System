<?php
// Fast image proxy with caching and optimization
$fileId = $_GET['id'] ?? '';
if (empty($fileId)) {
    http_response_code(400);
    exit('Missing file ID');
}

// For Vercel serverless environment, use memory cache or skip caching
$cacheEnabled = false; // Disable file caching for Vercel
$cacheFile = null;
$cacheTime = 3600; // 1 hour cache

// Check if we have a cached version (only if caching is enabled)
if ($cacheEnabled && $cacheFile && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . filesize($cacheFile));
    readfile($cacheFile);
    exit;
}

// Try the fastest Google Drive URL first (thumbnail)
$imageUrl = "https://drive.google.com/thumbnail?id=" . $fileId . "&sz=w400"; // Smaller size for faster loading

// Fast cURL request with minimal timeout
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $imageUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 10, // Reduced timeout
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ImageProxy/1.0)',
    CURLOPT_HTTPHEADER => [
        'Accept: image/*',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($imageData === false || $httpCode !== 200 || strlen($imageData) < 100) {
    // Fallback to other URLs if thumbnail fails
    $fallbackUrls = [
        "https://drive.google.com/uc?export=view&id=" . $fileId,
        "https://docs.google.com/uc?export=view&id=" . $fileId
    ];
    
    foreach ($fallbackUrls as $fallbackUrl) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fallbackUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ImageProxy/1.0)',
            CURLOPT_HTTPHEADER => ['Accept: image/*'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($imageData !== false && $httpCode === 200 && strlen($imageData) > 100) {
            break;
        }
    }
}

if ($imageData === false || strlen($imageData) < 100) {
    // Return optimized placeholder
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=300'); // 5 minutes cache for placeholder
    echo '<svg width="200" height="50" xmlns="http://www.w3.org/2000/svg"><rect width="200" height="50" fill="#f0f0f0"/><text x="100" y="25" text-anchor="middle" font-family="Arial" font-size="12" fill="#666">Signature not accessible</text></svg>';
    exit;
}

// Optimize and cache the image
$image = @imagecreatefromstring($imageData);
if ($image !== false) {
    // Resize to max 400px width for faster loading
    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);
    
    if ($originalWidth > 400) {
        $newWidth = 400;
        $newHeight = ($originalHeight * 400) / $originalWidth;
        
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        imagedestroy($image);
        $image = $resizedImage;
    }
    
    // Remove background (white/light backgrounds)
    $image = removeBackground($image);
    
    // Save optimized version to cache (if caching enabled)
    if ($cacheEnabled && $cacheFile) {
        imagepng($image, $cacheFile, 6); // PNG compression level 6 (good balance)
    }
    
    // Output optimized image directly
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    
    // Output image directly to browser
    ob_start();
    imagepng($image, null, 6);
    $imageData = ob_get_contents();
    ob_end_clean();
    
    header('Content-Length: ' . strlen($imageData));
    echo $imageData;
    
    imagedestroy($image);
} else {
    // If image processing fails, output original data
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=3600');
    echo $imageData;
}

function removeBackground($image) {
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Create a new image with transparent background
    $newImage = imagecreatetruecolor($width, $height);
    imagealphablending($newImage, false);
    imagesavealpha($newImage, true);
    
    // Fill with transparent background
    $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
    imagefill($newImage, 0, 0, $transparent);
    
    // Process each pixel
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Calculate brightness
            $brightness = ($r + $g + $b) / 3;
            
            // Check if pixel is likely background (white/light)
            $isBackground = false;
            
            // Method 1: Very bright pixels (likely white background)
            if ($brightness > 240) {
                $isBackground = true;
            }
            
            // Method 2: Check if pixel is very close to white
            if ($r > 240 && $g > 240 && $b > 240) {
                $isBackground = true;
            }
            
            // Method 3: Check if pixel is close to the average color of edges
            if ($x < 5 || $x > $width - 5 || $y < 5 || $y > $height - 5) {
                if ($brightness > 200) {
                    $isBackground = true;
                }
            }
            
            // Method 4: Check for very low contrast (likely background)
            $contrast = max($r, $g, $b) - min($r, $g, $b);
            if ($contrast < 30 && $brightness > 200) {
                $isBackground = true;
            }
            
            if (!$isBackground) {
                // Keep the original pixel
                $color = imagecolorallocate($newImage, $r, $g, $b);
                imagesetpixel($newImage, $x, $y, $color);
            }
            // If it's background, leave it transparent
        }
    }
    
    imagedestroy($image);
    return $newImage;
}
?>
