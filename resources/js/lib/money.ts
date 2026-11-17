/** Commercial documents store amounts as integer hundredths. */
export function toMinor(value: string | number): number | null {
    const text = String(value);
    if (!/^\d+(\.\d{1,2})?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    const amount = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    return Number.isSafeInteger(amount) ? amount : null;
}
export const fromMinor = (minor: number) => (minor / 100).toFixed(2);
export const money = (value: number | string | null, currency: string) =>
    `${currency} ${(Number(value ?? 0) / 100).toFixed(2)}`;
export function formatMoney(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(minor / 100);
    } catch {
        return money(minor, currency);
    }
}
