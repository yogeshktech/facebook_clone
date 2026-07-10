import { ActivityIndicator, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useState } from 'react';
import { COLORS } from '../config/constants';
import { useAuth } from '../context/AuthContext';
import { LoginScreen } from '../screens/auth/LoginScreen';
import { RegisterScreen } from '../screens/auth/RegisterScreen';
import { VerifyOtpScreen } from '../screens/auth/VerifyOtpScreen';
import { ChatListScreen } from '../screens/chat/ChatListScreen';
import { ChatScreen } from '../screens/chat/ChatScreen';
import { ProfileScreen } from '../screens/profile/ProfileScreen';
import { SearchScreen } from '../screens/search/SearchScreen';
import { MainTabs } from './MainTabs';
import type { AuthStackParamList, RootStackParamList } from './types';

const AuthStack = createNativeStackNavigator<AuthStackParamList>();
const RootStack = createNativeStackNavigator<RootStackParamList>();

function AuthNavigator() {
  const [registerEmail, setRegisterEmail] = useState('');

  return (
    <AuthStack.Navigator screenOptions={{ headerShown: false }}>
      <AuthStack.Screen name="Login">
        {({ navigation }) => (
          <LoginScreen onRegister={() => navigation.navigate('Register')} />
        )}
      </AuthStack.Screen>
      <AuthStack.Screen name="Register">
        {({ navigation }) => (
          <RegisterScreen
            onBack={() => navigation.goBack()}
            onOtpSent={(email) => {
              setRegisterEmail(email);
              navigation.navigate('VerifyOtp', { email });
            }}
          />
        )}
      </AuthStack.Screen>
      <AuthStack.Screen name="VerifyOtp">
        {({ navigation, route }) => (
          <VerifyOtpScreen email={route.params.email || registerEmail} onBack={() => navigation.goBack()} />
        )}
      </AuthStack.Screen>
    </AuthStack.Navigator>
  );
}

function AppNavigator() {
  return (
    <RootStack.Navigator>
      <RootStack.Screen name="MainTabs" component={MainTabs} options={{ headerShown: false }} />
      <RootStack.Screen name="ChatList" component={ChatListScreen} options={{ title: 'Messenger' }} />
      <RootStack.Screen name="Chat" component={ChatScreen} options={({ route }) => ({ title: route.params.title })} />
      <RootStack.Screen name="Profile" component={ProfileScreen} options={{ title: 'Profile' }} />
      <RootStack.Screen name="Search" component={SearchScreen} options={{ title: 'Search' }} />
    </RootStack.Navigator>
  );
}

export function RootNavigator() {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.background }}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  return (
    <NavigationContainer>
      {user ? <AppNavigator /> : <AuthNavigator />}
    </NavigationContainer>
  );
}
