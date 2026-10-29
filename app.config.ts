import type { ConfigContext, ExpoConfig } from 'expo/config';

/**
 * Dynamic Expo configuration.
 *
 * Everything that differs per environment (Firebase project, Google OAuth
 * client ids) is read from environment variables so the same source tree can
 * be pointed at a different Firebase project without code changes.
 *
 *   EXPO_PUBLIC_FIREBASE_API_KEY
 *   EXPO_PUBLIC_FIREBASE_AUTH_DOMAIN
 *   EXPO_PUBLIC_FIREBASE_PROJECT_ID
 *   EXPO_PUBLIC_FIREBASE_STORAGE_BUCKET
 *   EXPO_PUBLIC_FIREBASE_MESSAGING_SENDER_ID
 *   EXPO_PUBLIC_FIREBASE_APP_ID
 *   EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID
 *   EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID
 *
 * NOTE: `config` already is the resolved `expo` object from app.json — it is
 * NOT wrapped in an `expo` key. Returning a nested `expo` key here would make
 * Expo discard the static config (plugins, scheme, icons, experiments, ...).
 */
const env = process.env;
const defaultGoogleWebClientId =
  '897783793219-ipemkt4l9s1l09jfimhhqr8u64eqmhou.apps.googleusercontent.com';

const firebase = {
  apiKey: env.EXPO_PUBLIC_FIREBASE_API_KEY?.trim() || 'AIzaSyDfb65Ukxf423Ea9yIHniKHqjaaCAtOHSE',
  authDomain:
    env.EXPO_PUBLIC_FIREBASE_AUTH_DOMAIN?.trim() || 'smartstock-20d14.firebaseapp.com',
  projectId: env.EXPO_PUBLIC_FIREBASE_PROJECT_ID?.trim() || 'smartstock-20d14',
  storageBucket:
    env.EXPO_PUBLIC_FIREBASE_STORAGE_BUCKET?.trim() || 'smartstock-20d14.firebasestorage.app',
  messagingSenderId: env.EXPO_PUBLIC_FIREBASE_MESSAGING_SENDER_ID?.trim() || '897783793219',
  appId:
    env.EXPO_PUBLIC_FIREBASE_APP_ID?.trim() || '1:897783793219:web:f3ac5e0b7688a0a89236f6',
};

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'Stockroom',
  // Keep the existing project slug; a missing slug would be derived from `name`.
  slug: config.slug ?? 'inventory_gap_solutionReactNative',
  android: {
    ...config.android,
    package: 'com.SmartStock',
    googleServicesFile: './google-services.json',
    versionCode: 1,
    permissions: ['INTERNET', 'ACCESS_NETWORK_STATE'],
  },
  ios: {
    ...config.ios,
    bundleIdentifier: 'com.stockroom.inventorygap',
  },
  extra: {
    ...config.extra,
    firebase,
    googleWebClientId: env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID?.trim() || defaultGoogleWebClientId,
    googleIosClientId: env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID ?? '',
  },
});
