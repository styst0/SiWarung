import { test, expect } from "@playwright/test";
import dotenv from "dotenv";

dotenv.config();

test.describe("Login Test", () => {
    const url = process.env.LOGIN_API_KEY!;
    test("Login berhasil dengan kredensial valid", async ({ page }) => {
        await page.goto(url);
        await page.fill('input[name="email"]', process.env.E2E_EMAIL!);
        await page.waitForTimeout(1000);
        await page.fill('input[name="password"]', process.env.E2E_PASSWORD!);
        await page.waitForTimeout(1000);

        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);

        await expect(page).toHaveURL("http://127.0.0.1:8000/");
    });
});
