/** Format integer taka for display. Never divide — the value is already whole BDT. */
export function formatTaka(amount: number): string {
  return `${new Intl.NumberFormat("en-BD").format(amount)} BDT`;
}

export function cartShelfTotal(lines: { unitPrice: number; quantity: number }[]): number {
  return lines.reduce((sum, line) => sum + line.unitPrice * line.quantity, 0);
}

export function changeDue(cashReceived: number, cashDue: number): number {
  return Math.max(cashReceived - cashDue, 0);
}

export function formatStoredRefund(cash: number, points: number): string {
  return `${formatTaka(cash)} and ${points} points`;
}
