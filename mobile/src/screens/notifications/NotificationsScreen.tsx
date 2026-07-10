import { useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import * as socialApi from '../../api/social';
import { Avatar } from '../../components/Avatar';
import { COLORS } from '../../config/constants';
import type { Notification } from '../../types';

export function NotificationsScreen() {
  const [items, setItems] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    socialApi.getNotifications().then((res) => {
      setItems(res.data);
      setLoading(false);
    });
  }, []);

  const markRead = async (id: number) => {
    await socialApi.markNotificationRead(id);
    setItems((prev) => prev.map((n) => (n.id === id ? { ...n, is_read: true } : n)));
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
      data={items}
      keyExtractor={(item) => String(item.id)}
      renderItem={({ item }) => (
        <Pressable
          style={[styles.row, !item.is_read && styles.unread]}
          onPress={() => markRead(item.id)}
        >
          <Avatar uri={item.sender?.avatar_url} name={item.sender?.name ?? 'N'} />
          <View style={styles.body}>
            <Text style={styles.message}>{item.message}</Text>
            <Text style={styles.time}>{new Date(item.created_at).toLocaleString()}</Text>
          </View>
        </Pressable>
      )}
      ListEmptyComponent={<Text style={styles.empty}>No notifications</Text>}
    />
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  row: {
    flexDirection: 'row',
    gap: 12,
    padding: 14,
    backgroundColor: COLORS.card,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: COLORS.border,
  },
  unread: { backgroundColor: '#E7F3FF' },
  body: { flex: 1 },
  message: { fontSize: 14, color: COLORS.text, lineHeight: 20 },
  time: { fontSize: 12, color: COLORS.textSecondary, marginTop: 4 },
  empty: { textAlign: 'center', color: COLORS.textSecondary, marginTop: 40 },
});
