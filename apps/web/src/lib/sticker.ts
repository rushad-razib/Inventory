import JsBarcode from "jsbarcode";

export function printProductSticker(product: { name: string; sku: string; barcode: string }): void {
  const popup = window.open("", "product-sticker", "width=420,height=320");
  if (!popup) {
    window.alert("Allow pop-ups to print the sticker.");
    return;
  }

  popup.document.open();
  popup.document.write(`<!doctype html>
<html>
<head>
  <title>${escapeHtml(product.sku)}</title>
  <style>
    @page { size: 50mm 30mm; margin: 2mm; }
    html, body { margin: 0; padding: 0; }
    body { width: 46mm; font-family: sans-serif; color: #111; }
    .name { font-size: 8px; line-height: 1.2; white-space: nowrap; overflow: hidden; }
    svg { display: block; width: 46mm; height: 14mm; }
    .sku { font-size: 11px; font-weight: 700; }
    .digits { font-size: 9px; letter-spacing: 0.08em; }
  </style>
</head>
<body>
  <div class="name">${escapeHtml(product.name)}</div>
  <svg id="barcode"></svg>
  <div class="sku">${escapeHtml(product.sku)}</div>
  <div class="digits">${escapeHtml(product.barcode)}</div>
</body>
</html>`);
  popup.document.close();

  const svg = popup.document.getElementById("barcode");
  if (svg) {
    JsBarcode(svg, product.barcode, {
      format: "CODE128",
      displayValue: false,
      margin: 0,
      height: 48,
      width: 1.4,
      background: "#ffffff",
      lineColor: "#111111",
      xmlDocument: popup.document,
    });
  }

  popup.focus();
  popup.print();
}

function escapeHtml(value: string): string {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}
