import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Avatar } from '../../components/Avatar';
import { COLORS } from '../../config/constants';
import { useAuth } from '../../context/AuthContext';
import type { RootStackParamList } from '../../navigation/types';

export function MenuScreen() {
  const { user, signOut } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  return (
    <View style={styles.container}>
      <Pressable
        style={styles.profileCard}
        onPress={() => user && navigation.navigate('Profile', { userId: user.id })}
      >
        <Avatar uri={user?.avatar_url} name={user?.name ?? 'U'} size={56} />
        <View>
          <Text style={styles.name}>{user?.name}</Text>
          <Text style={styles.sub}>See your profile</Text>
        </View>
      </Pressable>

      <MenuItem icon="chatbubbles-outline" label="Messenger" onPress={() => navigation.navigate('ChatList')} />
      <MenuItem icon="people-outline" label="Friends" onPress={() => navigation.navigate('MainTabs', { screen: 'Friends' } as never)} />
      <MenuItem icon="videocam-outline" label="Videos" onPress={() => {}} />
      <MenuItem icon="flag-outline" label="Pages" onPress={() => {}} />
      <MenuItem icon="people-circle-outline" label="Groups" onPress={() => {}} />

      <View style={styles.divider} />

      <MenuItem icon="log-out-outline" label="Log Out" onPress={signOut} danger />
    </View>
  );
}

function MenuItem({
  icon,
  label,
  onPress,
  danger,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  onPress: () => void;
  danger?: boolean;
}) {
  return (
    <Pressable style={styles.menuItem} onPress={onPress}>
      <Ionicons name={icon} size={24} color={danger ? COLORS.danger : COLORS.text} />
      <Text style={[styles.menuLabel, danger && { color: COLORS.danger }]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background, paddingTop: 8 },
  profileCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    backgroundColor: COLORS.card,
    padding: 16,
    marginBottom: 8,
  },
  name: { fontSize: 18, fontWeight: '700', color: COLORS.text },
  sub: { fontSize: 14, color: COLORS.textSecondary, marginTop: 2 },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    backgroundColor: COLORS.card,
    padding: 16,
    marginBottom: 1,
  },
  menuLabel: { fontSize: 16, fontWeight: '600', color: COLORS.text },
  divider: { height: 8 },
});
