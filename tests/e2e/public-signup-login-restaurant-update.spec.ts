import { expect, test } from '@playwright/test';

test('public site -> free signup -> login -> update restaurant profile', async ({ page, baseURL }, testInfo) => {
  if (!baseURL) {
    throw new Error('baseURL is required. Set E2E_BASE_URL or APP_URL.');
  }

  const runId = Date.now();
  const email = `owner.${runId}@example.com`;
  const password = `Pass!${runId}`;
  const accountName = `E2E Account ${runId}`;
  const restaurantName = `E2E Restaurant ${runId}`;
  const updatedRestaurantName = `E2E Updated Restaurant ${runId}`;
  const updatedCity = 'Kadikoy';

  const shot = async (name: string) => {
    await page.screenshot({
      path: testInfo.outputPath(`${name}.png`),
      fullPage: true,
    });
  };

  await test.step('open public home page', async () => {
    await page.goto('/?lang=tr', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/(\?lang=tr)?$/);
    await shot('01-home');
  });

  await test.step('go to signup from plans area', async () => {
    const planButtons = page.locator('#plans a[href*="/signup"]');
    if (await planButtons.count()) {
      await planButtons.first().scrollIntoViewIfNeeded();
      await Promise.all([
        page.waitForURL(/\/signup/),
        planButtons.first().click(),
      ]);
    } else {
      await page.goto('/signup?lang=tr');
    }
    await expect(page).toHaveURL(/\/signup/);
    await shot('02-signup-page');
  });

  await test.step('select free plan and create account', async () => {
    const planSelect = page.locator('select[name="plan_code"]');
    await expect(planSelect.locator('option[value="free"]')).toHaveCount(1);

    await planSelect.selectOption('free');
    await page.fill('input[name="account_name"]', accountName);
    await page.fill('input[name="restaurant_name"]', restaurantName);
    await page.fill('input[name="first_name"]', 'E2E');
    await page.fill('input[name="last_name"]', 'Owner');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);

    await shot('03-signup-filled');

    await Promise.all([
      page.waitForURL(/\/admin\/login/),
      page.locator('form button').first().click(),
    ]);
    await shot('04-login-after-signup');
  });

  await test.step('login to admin panel', async () => {
    await page.fill('input[name="_username"]', email);
    await page.fill('input[name="_password"]', password);

    await Promise.all([
      page.waitForURL(/\/admin(\?.*)?$/),
      page.locator('button[type="submit"]').click(),
    ]);
    await expect(page).toHaveURL(/\/admin(\?.*)?$/);
    await shot('05-dashboard');
  });

  await test.step('open restaurant edit screen from panel', async () => {
    const editLink = page.locator('a[href*="/admin/restaurant/edit"]').first();
    await expect(editLink).toBeVisible();

    await Promise.all([
      page.waitForURL(/\/admin\/restaurant\/edit/),
      editLink.click(),
    ]);
    await shot('06-restaurant-edit');
  });

  await test.step('update restaurant information and save', async () => {
    await page.fill('input[name="restaurant_profile[name]"]', updatedRestaurantName);
    await page.fill('textarea[name="restaurant_profile[description]"]', 'Automated E2E profile update.');
    await page.fill('input[name="restaurant_profile[phone]"]', '+90 555 000 00 00');
    await page.fill('input[name="restaurant_profile[email]"]', email);
    await page.fill('textarea[name="restaurant_profile[address]"]', 'Moda Cd. No: 12');
    await page.fill('input[name="restaurant_profile[city]"]', updatedCity);
    await page.fill('input[name="restaurant_profile[countryCode]"]', 'TR');
    await page.fill('input[name="restaurant_profile[latitude]"]', '41.0082');
    await page.fill('input[name="restaurant_profile[longitude]"]', '28.9784');
    await page.fill('input[name="restaurant_profile[websiteUrl]"]', 'https://example.com/e2e');
    await page.fill('input[name="restaurant_profile[instagramUrl]"]', 'https://instagram.com/e2e_restaurant');

    await shot('07-restaurant-edit-filled');

    await Promise.all([
      page.waitForURL(/\/admin\/restaurant(\?.*)?$/),
      page.locator('form[name="restaurant_profile"] button[type="submit"]').click(),
    ]);

    await expect(page.locator('body')).toContainText(/g.ncellendi/i);
    await expect(page.locator('body')).toContainText(updatedRestaurantName);
    await expect(page.locator('body')).toContainText(updatedCity);
    await shot('08-restaurant-show-updated');
  });
});
