const fetch = require('node-fetch');

const csvUrl = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vToMROxujfLJpJtKdxu06HlC2tX7Okm2hN6kPNdxmoLwgyVQcZqwccHJ8gzsNTwvizyYHhiUcvl8874/pub?gid=1693812363&single=true&output=csv';

async function fetchCsv(url) {
  try {
    const response = await fetch(url, { timeout: 10000 });
    const csv = await response.text();
    
    const rows = [];
    const lines = csv.split('\n');
    
    for (const line of lines) {
      if (line.trim()) {
        const columns = line.split(',').map(col => col.replace(/^"|"$/g, ''));
        rows.push(columns);
      }
    }
    
    return rows;
  } catch (error) {
    console.error('Error fetching CSV:', error);
    return [];
  }
}

function h(s) {
  return s.replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
}

module.exports = async (req, res) => {
  res.setHeader('Content-Type', 'text/html; charset=utf-8');
  
  const rows = await fetchCsv(csvUrl);
  const headers = rows.length > 0 ? rows[0] : [];
  const bodyRows = rows.length > 1 ? rows.slice(1) : [];
  
  const studentSigIdx = headers.findIndex(h => h.trim() === 'Student Signature');
  
  const html = `
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
  <h3>Google Form Responses (${bodyRows.length})</h3>
  ${!rows.length ? '<p>Could not load the published sheet. Ensure it is published to web (CSV) and try again.</p>' : `
    <table>
      <thead>
        <tr>
          ${headers.map((head, i) => `
            <th>${h(head)}</th>
            ${studentSigIdx === i ? '<th>Generate</th>' : ''}
          `).join('')}
          ${studentSigIdx === -1 ? '<th>Generate</th>' : ''}
        </tr>
      </thead>
      <tbody>
        ${bodyRows.map((cols, rIdx) => {
          const sheetRowNumber = rIdx + 2;
          return `
            <tr>
              ${headers.map((head, i) => `
                <td>${h(cols[i] || '')}</td>
                ${studentSigIdx === i ? `<td><a class="btn" href="/preview?row=${sheetRowNumber}">Generate</a></td>` : ''}
              `).join('')}
              ${studentSigIdx === -1 ? `<td><a class="btn" href="/preview?row=${sheetRowNumber}">Generate</a></td>` : ''}
            </tr>
          `;
        }).join('')}
      </tbody>
    </table>
  `}
</body>
</html>
  `;
  
  res.send(html);
};
