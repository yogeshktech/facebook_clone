import { useCallback, useEffect, useRef, useState } from 'react';
import {
  FlatList,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import type { RouteProp } from '@react-navigation/native';
import { useRoute } from '@react-navigation/native';
import * as chatApi from '../../api/chat';
import { COLORS } from '../../config/constants';
import { useAuth } from '../../context/AuthContext';
import type { Message } from '../../types';
import type { RootStackParamList } from '../../navigation/types';

export function ChatScreen() {
  const { user } = useAuth();
  const route = useRoute<RouteProp<RootStackParamList, 'Chat'>>();
  const { conversationId } = route.params;
  const [messages, setMessages] = useState<Message[]>([]);
  const [text, setText] = useState('');
  const listRef = useRef<FlatList>(null);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const load = useCallback(async () => {
    const data = await chatApi.getMessages(conversationId);
    setMessages(data);
  }, [conversationId]);

  useEffect(() => {
    load();
    pollRef.current = setInterval(load, 4000);
    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
    };
  }, [load]);

  const send = async () => {
    if (!text.trim()) return;
    const body = text.trim();
    setText('');
    await chatApi.sendMessage(conversationId, body);
    await load();
    listRef.current?.scrollToEnd({ animated: true });
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={90}
    >
      <FlatList
        ref={listRef}
        data={messages}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.list}
        onContentSizeChange={() => listRef.current?.scrollToEnd({ animated: false })}
        renderItem={({ item }) => {
          const mine = item.user_id === user?.id || item.is_sender;
          return (
            <View style={[styles.bubbleWrap, mine && styles.mineWrap]}>
              <View style={[styles.bubble, mine ? styles.mineBubble : styles.theirBubble]}>
                <Text style={[styles.bubbleText, mine && styles.mineText]}>{item.body}</Text>
              </View>
            </View>
          );
        }}
      />

      <View style={styles.inputBar}>
        <TextInput
          style={styles.input}
          placeholder="Aa"
          placeholderTextColor={COLORS.textSecondary}
          value={text}
          onChangeText={setText}
          multiline
        />
        <Pressable onPress={send} style={styles.sendBtn}>
          <Ionicons name="send" size={22} color={COLORS.primary} />
        </Pressable>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: 12, paddingBottom: 8 },
  bubbleWrap: { marginBottom: 8, alignItems: 'flex-start' },
  mineWrap: { alignItems: 'flex-end' },
  bubble: { maxWidth: '78%', borderRadius: 18, paddingHorizontal: 14, paddingVertical: 10 },
  theirBubble: { backgroundColor: COLORS.card },
  mineBubble: { backgroundColor: COLORS.primary },
  bubbleText: { fontSize: 15, color: COLORS.text, lineHeight: 20 },
  mineText: { color: COLORS.white },
  inputBar: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 8,
    padding: 10,
    backgroundColor: COLORS.card,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: COLORS.border,
  },
  input: { flex: 1, maxHeight: 100, fontSize: 16, color: COLORS.text, padding: 8 },
  sendBtn: { padding: 8 },
});
