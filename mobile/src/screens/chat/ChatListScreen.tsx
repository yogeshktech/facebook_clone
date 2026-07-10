import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import * as chatApi from '../../api/chat';
import { Avatar } from '../../components/Avatar';
import { COLORS } from '../../config/constants';
import { useAuth } from '../../context/AuthContext';
import type { Conversation } from '../../types';
import type { RootStackParamList } from '../../navigation/types';

export function ChatListScreen() {
  const { user } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    const data = await chatApi.getConversations();
    setConversations(data);
  }, []);

  useEffect(() => {
    load().finally(() => setLoading(false));
  }, [load]);

  const getTitle = (c: Conversation) => {
    if (c.is_group) return c.name ?? 'Group';
    const other = c.users?.find((u) => u.id !== user?.id);
    return other?.name ?? 'Chat';
  };

  const getAvatar = (c: Conversation) => {
    const other = c.users?.find((u) => u.id !== user?.id);
    return other?.avatar_url;
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
      data={conversations}
      keyExtractor={(item) => String(item.id)}
      renderItem={({ item }) => (
        <Pressable
          style={styles.row}
          onPress={() => navigation.navigate('Chat', { conversationId: item.id, title: getTitle(item) })}
        >
          <Avatar uri={getAvatar(item)} name={getTitle(item)} size={52} />
          <View style={styles.body}>
            <Text style={styles.name}>{getTitle(item)}</Text>
            <Text style={styles.preview} numberOfLines={1}>
              {item.latest_message?.body ?? 'No messages yet'}
            </Text>
          </View>
        </Pressable>
      )}
      ListEmptyComponent={<Text style={styles.empty}>No conversations yet</Text>}
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
  body: { flex: 1 },
  name: { fontWeight: '700', fontSize: 16, color: COLORS.text },
  preview: { color: COLORS.textSecondary, marginTop: 4, fontSize: 14 },
  empty: { textAlign: 'center', color: COLORS.textSecondary, marginTop: 40 },
});
