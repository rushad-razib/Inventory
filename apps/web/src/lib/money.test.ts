import { describe, expect, it } from "vitest";
import { cartShelfTotal, changeDue, formatStoredRefund, formatTaka } from "./money";

describe("formatTaka", () => {
  it("formats integer taka without inventing decimals", () => {
    expect(formatTaka(132)).toContain("132");
    expect(formatTaka(132)).toContain("BDT");
  });
});

describe("cartShelfTotal", () => {
  it("adds shelf prices before the sale is posted", () => {
    expect(
      cartShelfTotal([
        { unitPrice: 100, quantity: 1 },
        { unitPrice: 150, quantity: 1 },
      ]),
    ).toBe(250);
  });
});

describe("formatStoredRefund", () => {
  it("shows the cash and points stored on a returned line", () => {
    expect(formatStoredRefund(132, 18)).toBe(`${formatTaka(132)} and 18 points`);
    expect(changeDue(500, 220)).toBe(280);
  });
});
