import { Ionicons } from '@expo/vector-icons';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { COLORS } from '../config/constants';
import { FeedScreen } from '../screens/home/FeedScreen';
import { ReelsScreen } from '../screens/reels/ReelsScreen';
import { FriendsScreen } from '../screens/friends/FriendsScreen';
import { NotificationsScreen } from '../screens/notifications/NotificationsScreen';
import { MenuScreen } from '../screens/menu/MenuScreen';
import type { MainTabParamList } from './types';

const Tab = createBottomTabNavigator<MainTabParamList>();

export function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: true,
        headerStyle: { backgroundColor: COLORS.card },
        headerTitleStyle: { fontWeight: '700', color: COLORS.text },
        tabBarActiveTintColor: COLORS.primary,
        tabBarInactiveTintColor: COLORS.tabInactive,
        tabBarStyle: { backgroundColor: COLORS.card, borderTopColor: COLORS.border },
        tabBarIcon: ({ color, size, focused }) => {
          const icons: Record<string, keyof typeof Ionicons.glyphMap> = {
            Home: focused ? 'home' : 'home-outline',
            Reels: focused ? 'play-circle' : 'play-circle-outline',
            Friends: focused ? 'people' : 'people-outline',
            Notifications: focused ? 'notifications' : 'notifications-outline',
            Menu: focused ? 'menu' : 'menu-outline',
          };
          return <Ionicons name={icons[route.name]} size={size} color={color} />;
        },
      })}
    >
      <Tab.Screen name="Home" component={FeedScreen} options={{ headerShown: false }} />
      <Tab.Screen name="Reels" component={ReelsScreen} options={{ headerTitle: 'Reels', headerShown: false }} />
      <Tab.Screen name="Friends" component={FriendsScreen} options={{ headerTitle: 'Friends' }} />
      <Tab.Screen name="Notifications" component={NotificationsScreen} options={{ headerTitle: 'Notifications' }} />
      <Tab.Screen name="Menu" component={MenuScreen} options={{ headerTitle: 'Menu' }} />
    </Tab.Navigator>
  );
}
