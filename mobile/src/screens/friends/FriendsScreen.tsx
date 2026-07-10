import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import * as socialApi from '../../api/social';
import { Avatar } from '../../components/Avatar';
import { COLORS } from '../../config/constants';
import type { Friendship, User } from '../../types';
import type { RootStackParamList } from '../../navigation/types';

export function FriendsScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [friends, setFriends] = useState<User[]>([]);
  const [pending, setPending] = useState<Friendship[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    const data = await socialApi.getFriends();
    setFriends(data.friends);
    setPending(data.pending);
  }, []);

  useEffect(() => {
    load().finally(() => setLoading(false));
  }, [load]);

  const accept = async (id: number) => {
    await socialApi.acceptFriendship(id);
    await load();
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={COLORS.primary} size="large" />
      </View>
    );
  }

  return (
    <FlatList
      style={styles.list}
      data={friends}
      keyExtractor={(item) => String(item.id)}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={async () => {
            setRefreshing(true);
            await load();
            setRefreshing(false);
          }}
        />
      }
      ListHeaderComponent={
        pending.length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Friend Requests</Text>
            {pending.map((req) => (
              <View key={req.id} style={styles.requestRow}>
                <Avatar uri={req.user?.avatar_url} name={req.user?.name ?? 'U'} />
                <Text style={styles.name}>{req.user?.name}</Text>
                <Pressable style={styles.acceptBtn} onPress={() => accept(req.id)}>
                  <Text style={styles.acceptText}>Confirm</Text>
                </Pressable>
              </View>
            ))}
          </View>
        ) : null
      }
      renderItem={({ item }) => (
        <Pressable
          style={styles.row}
          onPress={() => navigation.navigate('Profile', { userId: item.id })}
        >
          <Avatar uri={item.avatar_url} name={item.name} size={48} />
          <Text style={styles.name}>{item.name}</Text>
        </Pressable>
      )}
      ListEmptyComponent={<Text style={styles.empty}>No friends yet</Text>}
    />
  );
}

const styles = StyleSheet.create({
  list: { flex: 1, backgroundColor: COLORS.background },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  section: { backgroundColor: COLORS.card, marginBottom: 8, padding: 12 },
  sectionTitle: { fontWeight: '700', fontSize: 17, marginBottom: 12, color: COLORS.text },
  requestRow: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 12 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    backgroundColor: COLORS.card,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: COLORS.border,
  },
  name: { flex: 1, fontSize: 16, fontWeight: '600', color: COLORS.text },
  acceptBtn: { backgroundColor: COLORS.primary, paddingHorizontal: 14, paddingVertical: 8, borderRadius: 6 },
  acceptText: { color: COLORS.white, fontWeight: '700' },
  empty: { textAlign: 'center', color: COLORS.textSecondary, marginTop: 40 },
});
