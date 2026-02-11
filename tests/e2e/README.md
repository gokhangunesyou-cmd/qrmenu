# E2E Flow: Public Signup -> Admin Update

This folder contains Playwright tests for the public-to-admin onboarding flow.

## Scenario

- Open public home page
- Go to signup from plans section
- Select `free` plan and register
- Login to admin panel
- Open restaurant edit from panel
- Update restaurant profile and save

## Run

1. Start app and database:
```bash
make up
make migrate
make seed
```

2. Install Playwright dependencies:
```bash
npm install
npx playwright install chromium
```

3. Run only this scenario:
```bash
npm run e2e:public-signup-flow
```

## Outputs

- Screenshots: `test-results/**.png`
- Video: `test-results/**/video.webm`
- HTML report: `playwright-report/index.html`

If your app runs on another URL:
```bash
E2E_BASE_URL=http://localhost:8080 npm run e2e:public-signup-flow
```
