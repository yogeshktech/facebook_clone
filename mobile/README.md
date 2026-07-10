# Newbook — React Native App

Facebook-style mobile app for [newbook.workarya.com](https://newbook.workarya.com), built with **Expo** + **TypeScript**.

## Features (v1)

- Login / Register (OTP) via live API
- Home feed with stories + posts (like, comment)
- Reels (vertical scroll, like)
- Friends list + friend requests
- Notifications
- Messenger (chat list + real-time polling)
- Profile + Search
- Facebook-like bottom tabs + blue theme

## API

Live base URL: `https://newbook.workarya.com/api`

Auth uses Laravel Sanctum Bearer token (stored in SecureStore).

## Run locally

**Node.js:** `20.18+` or `22+` recommended. Check with `node -v`.

```bash
cd mobile
npm install
npm start
```

Then:

- Press `a` for Android emulator
- Scan QR with **Expo Go** on your phone (same Wi‑Fi)

### Android APK (production)

```bash
npx expo prebuild
cd android && ./gradlew assembleRelease
```

Or use EAS Build:

```bash
npm install -g eas-cli
eas build -p android
```

## Project structure

```
mobile/
├── App.tsx                 # Entry
├── src/
│   ├── api/                # API client + endpoints
│   ├── components/         # PostCard, Avatar, StoryRow
│   ├── config/constants.ts # API URL, colors
│   ├── context/            # AuthContext
│   ├── navigation/         # Tabs + stacks
│   ├── screens/            # All screens
│   └── types/              # TypeScript types
```

## Change API URL

Edit `src/config/constants.ts`:

```ts
export const API_BASE_URL = 'https://newbook.workarya.com/api';
```

## Next steps (roadmap)

- [ ] Push notifications (FCM + `/api/notifications/device-token`)
- [ ] Create post / story from app (image picker)
- [ ] WebRTC calls (`/api/calls/*`)
- [ ] Groups & Pages screens
- [ ] Offline cache
