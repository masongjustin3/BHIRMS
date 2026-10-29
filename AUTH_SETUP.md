# Authentication setup (Firebase + Google Sign-In)

This document covers everything that has to be configured **outside** the code
before email/password login, Google Sign-In and password reset work in a real
build, and how to produce the Android APK.

The app already contains a working auth module:

| Feature | Where |
| --- | --- |
| Email + password sign-in | `src/context/AuthContext.tsx` → `signInWithEmail` |
| Registration with validation | `src/context/AuthContext.tsx` → `registerWithEmail`, `src/lib/validation.ts` |
| Google Sign-In | `src/lib/googleAuth.ts` (native `@react-native-google-signin/google-signin`) |
| Forgot password | `src/context/AuthContext.tsx` → `sendPasswordReset` (Firebase reset email) |
| Logout | `src/context/AuthContext.tsx` → `logout` + the `(app)` route guard |
| Session persistence | `src/lib/firebase.ts` (`initializeAuth` + `getReactNativePersistence(AsyncStorage)`) |

Route protection lives in the layouts:

- `src/app/index.tsx` – redirects to `/inventory` or `/login`
- `src/app/(auth)/_layout.tsx` – signed-in users are sent to `/inventory`
- `src/app/(app)/_layout.tsx` – signed-out users are sent to `/login`

---

## 1. Firebase project

The app defaults to the existing project **`smartstock-20d14`** (see
`src/lib/firebaseConfig.ts` / `app.config.ts`). You can point it at another
project with the environment variables in section 3.

Open <https://console.firebase.google.com/project/smartstock-20d14> and do the
following.

### 1.1 Enable the sign-in providers

**Authentication → Sign-in method**

1. Enable **Email/Password**.
2. Enable **Google** and pick a support email.

### 1.2 Register the Android app

**Project settings → Your apps → Add app → Android**

This project uses the existing Firebase Android app registered as
`com.SmartStock`, and loads its `google-services.json` during native builds.
Keep this package name in sync with the Android app entry in Firebase.

- **Android package name:** `com.SmartStock`
  (must match `android.package` in `app.config.ts` and `google-services.json`)
- **App nickname:** `Stockroom`
- **Debug signing certificate SHA-1:** add every SHA-1 you build with
  (see below). You can add more later under *Project settings → Your apps →
  Android app → Add fingerprint*.

Getting the SHA-1:

| Build type | Command |
| --- | --- |
| Local debug (`expo run:android`) | `keytool -list -v -keystore android\app\debug.keystore -alias androiddebugkey -storepass android -keypass android` |
| EAS Build (recommended) | `npx eas-cli@latest credentials -p android` → select the project → *Keystore* → copy **SHA-1 Fingerprint** |
| Local release (`gradlew assembleRelease`) | `keytool -list -v -keystore android\app\release.keystore -alias <alias>` (or the keystore you configured) |

The Firebase JSON currently has the local debug SHA-1 registered. Register the
SHA-1 from the key that signs the APK you install. EAS uses a different key
from the local debug build, so add the EAS key's SHA-1 to this same Firebase
Android app before testing a preview APK.

For the latest EAS preview APK built on October 26, 2026 (build ID
`96ad9995-a868-4445-8262-d06d7201b48b`, version code 3), the signing
certificate SHA-1 is:

```text
4C:59:FF:75:6D:0A:DE:26:2A:01:A0:6B:F5:C3:EE:37:02:6C:93:0A
```

Add this fingerprint to the `com.SmartStock` Android app in Firebase, then
download the refreshed `google-services.json` and replace the project-root
copy before making another EAS build. If EAS credentials are reset or changed,
inspect the new APK and register its new signing SHA-1 instead.

> **EAS generates its own keystore on the first build.** Build once, read the
> SHA-1 with `eas credentials`, add it in Firebase, then build again. Google
> Sign-In returns `DEVELOPER_ERROR` (code 10) until a matching SHA-1 is
> registered on the Firebase Android app whose package is `com.SmartStock`.

### 1.3 Enable the Google provider credential

The Google provider needs an OAuth client that belongs to the same project.
The Android OAuth client is tied to the exact package name and signing
certificate SHA-1 registered in Firebase. This app now uses the existing
Firebase Android registration for `com.SmartStock`. Add the signing SHA-1 of
each build type you test to that Firebase app.

The app also needs the **Web client ID** (OAuth client type 3 in
`google-services.json`), not the Android client ID (type 1):

**Authentication → Sign-in method → Google → Web SDK configuration**

Copy that value (`…apps.googleusercontent.com`) — it becomes
`EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID`.

If there is no Web app yet, click **Add app → Web**, register it (no hosting
needed) and copy the generated client ID from
**Authentication → Sign-in method → Google → Web SDK configuration**.

The current Web client ID from this Firebase project's
`google-services.json` is already configured as the default in `app.config.ts`.
You can override it with `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID`. The native
GoogleSignin SDK receives this Web client ID from
`GoogleSignin.configure({ webClientId })` in `src/lib/googleAuth.ts`.

---

## 2. What the app needs at build time

| Requirement | Value |
| --- | --- |
| Android package | `com.SmartStock` |
| Web OAuth client ID | Firebase **Web** client ID |
| Firebase project | `smartstock-20d14` (or your own) |

> `EXPO_PUBLIC_*` variables are **inlined at build time**. Changing them
> requires a new JS bundle (dev server restart or a new build); they are not
> read at runtime.

---

## 3. Environment variables

Create a `.env` file in the project root (already git-ignored) or export the
variables in your shell:

```bash
# Firebase web app config (Project settings → Your apps → Web app → Config)
EXPO_PUBLIC_FIREBASE_API_KEY=...
EXPO_PUBLIC_FIREBASE_AUTH_DOMAIN=smartstock-20d14.firebaseapp.com
EXPO_PUBLIC_FIREBASE_PROJECT_ID=smartstock-20d14
EXPO_PUBLIC_FIREBASE_STORAGE_BUCKET=smartstock-20d14.firebasestorage.app
EXPO_PUBLIC_FIREBASE_MESSAGING_SENDER_ID=...
EXPO_PUBLIC_FIREBASE_APP_ID=...

# OAuth 2.0 Web client id (required for Google Sign-In)
EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID=1234567890-xxxxxxxxxxxxxxxx.apps.googleusercontent.com
# Optional, iOS builds only
EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID=
```

The Firebase project values default to `smartstock-20d14`. Blank Firebase
values in `.env` fall back to those defaults, so copying `.env.example` without
filling those fields will not disconnect Firebase. To use a different Firebase
project, replace all six Firebase values with that project's Web app config.

Google Sign-In uses the configured Web client ID by default. If pointing the
app at another Firebase project, set `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID` to that
project's Web client ID. Email/password login and password reset do not require
this OAuth client ID, but the corresponding providers must be enabled in
Firebase Authentication.

For **EAS Build** the variables must exist in the build environment. Either add
them to the build profile in `eas.json`:

```json
"preview": {
  "distribution": "internal",
  "android": { "buildType": "apk" },
  "env": {
    "EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID": "…apps.googleusercontent.com"
  }
}
```

or create them with `npx eas-cli@latest env:create`.

---

## 4. Run the app locally

```bash
npm install
npx expo start
```

Then press `a` for an Android emulator/device. A **development build** is
required for Google Sign-In because it uses native code:

```bash
npx expo run:android
```

(Expo Go does not include the Google Sign-In native module.)

---

## 5. Build the APK

### Option A — EAS Build (no Android SDK needed)

Prerequisites: Node.js and a free <https://expo.dev> account. A Git repository is
recommended (EAS uses it for build metadata); if Git is not installed you can
skip it with `EAS_NO_VCS=1`.

```bash
git init            # skip if the project is already a repository (or no git)
git add .
git commit -m "Stockroom auth"
npx eas-cli@latest login
npx eas-cli@latest init          # links the project, writes extra.eas.projectId
npx eas-cli@latest build -p android --profile preview
```

Without Git (Windows):

```powershell
$env:EAS_NO_VCS = "1"
npx eas-cli@latest login
npx eas-cli@latest init
npx eas-cli@latest build -p android --profile preview
```

`eas.json` already defines the `preview` profile with
`android.buildType: "apk"`, `distribution: "internal"`, and automatic Android
version-code increments, so each new preview build can upgrade an existing
installation with the same signing key. EAS prints a download URL when it
finishes, and you can also download it from <https://expo.dev/builds>.

If Android still reports that the package is invalid, uninstall the previous
Stockroom app and install the newest APK from the latest EAS build (not one of
the older APKs in Downloads). Reinstalling is required when the app package or
signing key differs from the installed copy.

### Option B — local Gradle build (needs Android SDK + JDK 17)

```bash
npx expo prebuild -p android
cd android
./gradlew assembleRelease
```

Output: `android/app/build/outputs/apk/release/app-release.apk`.

On Windows use `gradlew.bat assembleRelease`. The generated project signs the
release build with the debug keystore unless you add your own signing config in
`android/app/build.gradle` (that file is generated — configure signing through
`app.config.ts` / EAS credentials instead of editing it by hand).

> `android/` and `ios/` are generated by Continuous Native Generation and are
> git-ignored. Never edit them by hand; change `app.config.ts` instead.

---

## 6. Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `DEVELOPER_ERROR` (code 10) when pressing the Google button | Confirm the build uses package `com.SmartStock`, then register that build's signing SHA-1 on its Firebase Android app and rebuild. |
| `auth/invalid-credential` or `auth/wrong-password` | Wrong email/password. The UI already shows "The email or password you entered is incorrect." |
| `auth/email-already-in-use` | The email is already registered — sign in instead, or use forgot password. |
| `auth/too-many-requests` | Firebase rate limit after repeated failures. Wait, then retry. |
| Reset email never arrives | Check the spam folder; the sender is `noreply@<project>.firebaseapp.com`. Add a custom SMTP server in **Authentication → Templates** for production. |
| `DUPLICATE_APP` / duplicate Firebase init | Keep the single entry point `src/lib/firebase.ts`. Do not call `initializeApp` anywhere else. |
| User is logged out after restart | AsyncStorage persistence only works in a real build/dev client, not in some web environments. Verify `initializeAuth` in `src/lib/firebase.ts` is unchanged. |
| Emails/passwords rejected by the server | Password must be at least 6 characters (Firebase minimum; the form enforces this locally). |

---

## 7. Manual test checklist

1. Register with a new email → lands on Inventory.
2. Kill and reopen the app → still signed in (session persistence).
3. Log out from **Account** → returns to Login; pressing back does not reopen Inventory.
4. Log in with wrong password → clear error, stays on Login.
5. Log in with the registered account → Inventory.
6. Forgot password → reset email received → new password works.
7. Google Sign-In → account is created/signed in and appears under
   **Authentication → Users** in the Firebase console.
8. Register with an existing email, empty fields, invalid email and mismatched
   passwords → inline validation messages, no network call.
