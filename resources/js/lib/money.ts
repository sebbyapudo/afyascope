export function formatMinorAmount(amountMinor: number): string {
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amountMinor / 100);
}
