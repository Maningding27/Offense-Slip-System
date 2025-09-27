const fetch = require('node-fetch');
const sharp = require('sharp');

module.exports = async (req, res) => {
  const fileId = req.query.id;
  
  if (!fileId) {
    return res.status(400).send('Missing file ID');
  }
  
  // Try the fastest Google Drive URL first (thumbnail)
  const imageUrl = `https://drive.google.com/thumbnail?id=${fileId}&sz=w400`;
  
  try {
    // Fetch image from Google Drive
    const response = await fetch(imageUrl, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (compatible; ImageProxy/1.0)',
        'Accept': 'image/*'
      },
      timeout: 10000
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const imageBuffer = await response.buffer();
    
    // Process image with Sharp (resize and remove background)
    const processedImage = await sharp(imageBuffer)
      .resize(400, null, { withoutEnlargement: true })
      .png({ quality: 85 })
      .toBuffer();
    
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
