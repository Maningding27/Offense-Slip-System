const fetch = require('node-fetch');
const sharp = require('sharp');

async function removeBackground(imageBuffer) {
  try {
    // Get image metadata
    const metadata = await sharp(imageBuffer).metadata();
    const { width, height } = metadata;
    
    // Convert to raw pixel data
    const { data } = await sharp(imageBuffer)
      .raw()
      .toBuffer({ resolveWithObject: true });
    
    // Process each pixel to remove white/light backgrounds
    const processedData = Buffer.alloc(data.length);
    
    for (let i = 0; i < data.length; i += 4) {
      const r = data[i];
      const g = data[i + 1];
      const b = data[i + 2];
      const a = data[i + 3];
      
      // Calculate brightness
      const brightness = (r + g + b) / 3;
      
      // Check if pixel is likely background (white/light)
      let isBackground = false;
      
      // Method 1: Very bright pixels (likely white background)
      if (brightness > 240) {
        isBackground = true;
      }
      
      // Method 2: Check if pixel is very close to white
      if (r > 240 && g > 240 && b > 240) {
        isBackground = true;
      }
      
      // Method 3: Check for very low contrast (likely background)
      const contrast = Math.max(r, g, b) - Math.min(r, g, b);
      if (contrast < 30 && brightness > 200) {
        isBackground = true;
      }
      
      if (isBackground) {
        // Make pixel transparent
        processedData[i] = 0;     // R
        processedData[i + 1] = 0; // G
        processedData[i + 2] = 0; // B
        processedData[i + 3] = 0; // A (transparent)
      } else {
        // Keep original pixel
        processedData[i] = r;
        processedData[i + 1] = g;
        processedData[i + 2] = b;
        processedData[i + 3] = a;
      }
    }
    
    // Create new image with processed data
    const processedImage = await sharp(processedData, {
      raw: {
        width,
        height,
        channels: 4
      }
    })
    .png({ quality: 85 })
    .toBuffer();
    
    return processedImage;
    
  } catch (error) {
    console.error('Background removal error:', error);
    // Return original image if processing fails
    return imageBuffer;
  }
}

module.exports = async (req, res) => {
  const fileId = req.query.id;
  
  if (!fileId) {
    return res.status(400).send('Missing file ID');
  }
  
  // Try multiple Google Drive URL formats
  const urls = [
    `https://drive.google.com/thumbnail?id=${fileId}&sz=w400`,
    `https://drive.google.com/uc?export=view&id=${fileId}`,
    `https://docs.google.com/uc?export=view&id=${fileId}`
  ];
  
  let imageBuffer = null;
  
  // Try each URL until one works
  for (const imageUrl of urls) {
    try {
      const response = await fetch(imageUrl, {
        headers: {
          'User-Agent': 'Mozilla/5.0 (compatible; ImageProxy/1.0)',
          'Accept': 'image/*'
        },
        timeout: 10000
      });
      
      if (response.ok) {
        imageBuffer = await response.buffer();
        break;
      }
    } catch (error) {
      console.error(`Failed to fetch from ${imageUrl}:`, error);
      continue;
    }
  }
  
  if (!imageBuffer) {
    // Return placeholder image
    const placeholder = `
      <svg width="200" height="50" xmlns="http://www.w3.org/2000/svg">
        <rect width="200" height="50" fill="#f0f0f0"/>
        <text x="100" y="25" text-anchor="middle" font-family="Arial" font-size="12" fill="#666">
          Signature not accessible
        </text>
      </svg>
    `;
    
    res.setHeader('Content-Type', 'image/svg+xml');
    res.setHeader('Cache-Control', 'public, max-age=300');
    res.send(placeholder);
    return;
  }
  
  try {
    // Resize image first
    const resizedImage = await sharp(imageBuffer)
      .resize(400, null, { withoutEnlargement: true })
      .png()
      .toBuffer();
    
    // Remove background
    const processedImage = await removeBackground(resizedImage);
    
    // Set headers
    res.setHeader('Content-Type', 'image/png');
    res.setHeader('Cache-Control', 'public, max-age=3600');
    res.setHeader('Content-Length', processedImage.length);
    
    // Send processed image
    res.send(processedImage);
    
  } catch (error) {
    console.error('Image processing error:', error);
    
    // Return placeholder image
    const placeholder = `
      <svg width="200" height="50" xmlns="http://www.w3.org/2000/svg">
        <rect width="200" height="50" fill="#f0f0f0"/>
        <text x="100" y="25" text-anchor="middle" font-family="Arial" font-size="12" fill="#666">
          Signature not accessible
        </text>
      </svg>
    `;
    
    res.setHeader('Content-Type', 'image/svg+xml');
    res.setHeader('Cache-Control', 'public, max-age=300');
    res.send(placeholder);
  }
};
