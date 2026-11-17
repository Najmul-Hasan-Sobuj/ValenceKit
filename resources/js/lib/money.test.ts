import { expect, test } from 'vitest';
import { toMinor, fromMinor, money } from './money';
test('converts decimal input exactly and rejects unsafe or malformed amounts', () => {
    expect(toMinor('0.29')).toBe(29);
    expect(toMinor('123.4')).toBe(12340);
    for (const input of ['-1', '1.234', '1e3', 'NaN', '9007199254740991', ''])
        expect(toMinor(input)).toBeNull();
    expect(fromMinor(29)).toBe('0.29');
    expect(money(29, 'BDT')).toBe('BDT 0.29');
});
