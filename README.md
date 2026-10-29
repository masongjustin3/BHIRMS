# Stockroom — Inventory Gap Solution

Expo/React Native app with a Firebase Authentication module (email + password,
Google Sign-In, password reset, logout, persistent sessions) and an inventory
dashboard.

## Features

- **Register** with email + password and inline validation (required fields,
  email format, minimum password length, password confirmation).
- **Login** with clear messages for wrong credentials / rate limits.
- **Google Sign-In** using the native SDK (works in a standalone APK).
- **Forgot password** via a Firebase password-reset email.
- **Logout** from the Account screen; the route guard sends you back to Login.
- **Persistent session** — the Firebase session is stored in AsyncStorage, so
  reopening the app keeps you signed in.

## Getting started

```bash
npm install
npx expo start
```

Press `a` to open Android. Google Sign-In needs a development build, because it
uses a native module:

```bash
npx expo run:android
```

Configuration (Firebase providers, Google SHA-1, env vars) is documented in
[`AUTH_SETUP.md`](./AUTH_SETUP.md). Copy `.env.example` to `.env` to override the
built-in Firebase project. The app defaults to the `smartstock-20d14` Firebase
project; blank Firebase values in `.env` fall back to those defaults. To test
email/password and password reset, enable Email/Password in Firebase
Authentication. Google Sign-In also needs the Web OAuth client ID and a matching
Android signing SHA-1, and it cannot run in Expo Go.

## Project layout

```
src/
  app/                 Expo Router routes
    index.tsx          existing inventory dashboard (requires a signed-in user)
    (auth)/            login, register, forgot-password (redirect if signed in)
    (app)/             inventory, account (redirect if signed out)
    _layout.tsx        restores Firebase session and protects app routes
  components/auth/     shared auth UI (fields, buttons, alerts, loading)
  context/AuthContext  auth state + actions (useAuth())
  lib/                 firebase init, Google auth, validation, error messages
  constants/           color palettes
```

## Scripts

```bash
npm start          # expo start
npm run android    # expo start --android
npm run ios        # expo start --ios
npm run web        # expo start --web
npm run lint       # expo lint
npx tsc --noEmit   # typecheck
```

## Building the APK

EAS (cloud, no local Android SDK required):

```bash
npx eas-cli@latest login
npx eas-cli@latest init
npx eas-cli@latest build -p android --profile preview
```

The `preview` profile in `eas.json` is configured for `android.buildType: "apk"`.
For a local Gradle build and for signing/Google SHA-1 details, see
[`AUTH_SETUP.md`](./AUTH_SETUP.md#5-build-the-apk).

## Notes

- Android package: `com.SmartStock` (must match the registered Firebase Android app).
- Firebase project: `smartstock-20d14` (override with `EXPO_PUBLIC_FIREBASE_*`).
- `android/` and `ios/` are generated (Continuous Native Generation) and are
  git-ignored — configure native behaviour in `app.config.ts`.
