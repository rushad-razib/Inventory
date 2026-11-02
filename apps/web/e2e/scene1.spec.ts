import { expect, test } from "@playwright/test";

test("Nadia drafts a purchase order from the distribution center alerts", async ({ page }) => {
  await page.goto("/login");
  await page.getByLabel("Email").fill("nadia@hartandfield.test");
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.getByRole("link", { name: "Reorder alerts" }).click();

  const boxes = page.getByRole("checkbox");
  await expect(boxes.first()).toBeVisible();
  const count = await boxes.count();
  for (let index = 0; index < count; index += 1) {
    await boxes.nth(index).check();
  }

  await page.getByRole("button", { name: "Create drafts" }).click();
  await expect(page.getByText("Created 1 draft.")).toBeVisible();
});
