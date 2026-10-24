import { describe, expect, it } from "vitest";
import { formatTaka } from "./money";

describe("formatTaka", () => {
  it("formats integer taka without inventing decimals", () => {
    expect(formatTaka(132)).toContain("132");
    expect(formatTaka(132)).toContain("BDT");
  });
});
