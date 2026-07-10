export type RootStackParamList = {
  MainTabs: undefined;
  ChatList: undefined;
  Chat: { conversationId: number; title: string };
  Profile: { userId: number };
  Search: undefined;
};

export type MainTabParamList = {
  Home: undefined;
  Reels: undefined;
  Friends: undefined;
  Notifications: undefined;
  Menu: undefined;
};

export type AuthStackParamList = {
  Login: undefined;
  Register: undefined;
  VerifyOtp: { email: string };
};
