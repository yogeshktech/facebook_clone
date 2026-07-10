import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import * as feedApi from '../../api/feed';
import { PostCard } from '../../components/PostCard';
import { StoryRow } from '../../components/StoryRow';
import { COLORS } from '../../config/constants';
import { useAuth } from '../../context/AuthContext';
import type { Post, Story } from '../../types';
import type { RootStackParamList } from '../../navigation/types';

export function FeedScreen() {
  const { user } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [posts, setPosts] = useState<Post[]>([]);
  const [stories, setStories] = useState<Record<string, Story[]>>({});
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [commentPostId, setCommentPostId] = useState<number | null>(null);
  const [commentText, setCommentText] = useState('');

  const load = useCallback(async () => {
    const [feed, storyData] = await Promise.all([feedApi.getFeed(), feedApi.getStories()]);
    setPosts(feed.data);
    setStories(storyData);
  }, []);

  useEffect(() => {
    load().finally(() => setLoading(false));
  }, [load]);

  const onRefresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const handleLike = async (post: Post) => {
    const res = await feedApi.likePost(post.id);
    setPosts((prev) =>
      prev.map((p) =>
        p.id === post.id
          ? {
              ...p,
              is_liked: res.liked,
              likes_count: (p.likes_count ?? 0) + (res.liked ? 1 : -1),
            }
          : p,
      ),
    );
  };

  const submitComment = async () => {
    if (!commentPostId || !commentText.trim()) return;
    await feedApi.commentPost(commentPostId, commentText.trim());
    setCommentText('');
    setCommentPostId(null);
    await load();
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.topBar}>
        <Text style={styles.logo}>newbook</Text>
        <View style={styles.topIcons}>
          <Pressable onPress={() => navigation.navigate('Search')} style={styles.iconBtn}>
            <Ionicons name="search" size={24} color={COLORS.text} />
          </Pressable>
          <Pressable onPress={() => navigation.navigate('ChatList')} style={styles.iconBtn}>
            <Ionicons name="chatbubble-ellipses" size={24} color={COLORS.text} />
          </Pressable>
        </View>
      </View>

      <FlatList
        data={posts}
        keyExtractor={(item) => String(item.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={COLORS.primary} />}
        ListHeaderComponent={<StoryRow currentUser={user} storyGroups={stories} />}
        renderItem={({ item }) => (
          <PostCard
            post={item}
            onLike={() => handleLike(item)}
            onComment={() => setCommentPostId(item.id)}
          />
        )}
        ListEmptyComponent={
          <Text style={styles.empty}>No posts yet. Follow friends to see their updates.</Text>
        }
      />

      {commentPostId ? (
        <View style={styles.commentBar}>
          <TextInput
            style={styles.commentInput}
            placeholder="Write a comment..."
            value={commentText}
            onChangeText={setCommentText}
            autoFocus
          />
          <Pressable onPress={submitComment}>
            <Ionicons name="send" size={22} color={COLORS.primary} />
          </Pressable>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.background },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 10,
    backgroundColor: COLORS.card,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: COLORS.border,
  },
  logo: { fontSize: 28, fontWeight: '800', color: COLORS.primary },
  topIcons: { flexDirection: 'row', gap: 8 },
  iconBtn: { padding: 6 },
  empty: { textAlign: 'center', color: COLORS.textSecondary, marginTop: 40, padding: 20 },
  commentBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    backgroundColor: COLORS.card,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: COLORS.border,
  },
  commentInput: { flex: 1, fontSize: 15, color: COLORS.text },
});
