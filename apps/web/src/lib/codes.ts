const SKU_ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

export function generateSku(): string {
  const bytes = new Uint8Array(6);
  crypto.getRandomValues(bytes);
  let suffix = "";
  for (const byte of bytes) {
    suffix += SKU_ALPHABET[byte % SKU_ALPHABET.length];
  }
  return `SKU-${suffix}`;
}

export function generateBarcode(): string {
  const digits = ["2", "0"];
  const bytes = new Uint8Array(10);
  crypto.getRandomValues(bytes);
  for (const byte of bytes) {
    digits.push(String(byte % 10));
  }
  const body = digits.join("");
  return body + ean13CheckDigit(body);
}

function ean13CheckDigit(body: string): string {
  let sum = 0;
  for (let index = 0; index < 12; index += 1) {
    const digit = Number(body[index]);
    sum += index % 2 === 0 ? digit : digit * 3;
  }
  return String((10 - (sum % 10)) % 10);
}
