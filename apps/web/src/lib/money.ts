/** Format integer taka for display. Never divide — the value is already whole BDT. */
export function formatTaka(amount: number): string {
  return `${new Intl.NumberFormat("en-BD").format(amount)} BDT`;
}
