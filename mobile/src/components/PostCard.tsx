import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { COLORS } from '../config/constants';
import type { Post } from '../types';
import { Avatar } from './Avatar';
import { Image } from 'expo-image';

type Props = {
  post: Post;
  onLike: () => void;
  onComment?: () => void;
};

export function PostCard({ post, onLike, onComment }: Props) {
  const user = post.user;
  const time = new Date(post.created_at).toLocaleDateString();

  return (
    <View style={styles.card}>
      <View style={styles.header}>
        <Avatar uri={user?.avatar_url} name={user?.name ?? 'U'} size={40} />
        <View style={styles.headerText}>
          <Text style={styles.name}>{user?.name ?? 'User'}</Text>
          <Text style={styles.time}>{time}</Text>
        </View>
      </View>

      {post.content ? <Text style={styles.content}>{post.content}</Text> : null}

      {post.media_url ? (
        <Image source={{ uri: post.media_url }} style={styles.media} contentFit="cover" />
      ) : null}

      <View style={styles.stats}>
        <Text style={styles.statText}>{post.likes_count ?? 0} likes</Text>
        <Text style={styles.statText}>{post.comments_count ?? 0} comments</Text>
      </View>

      <View style={styles.actions}>
        <Pressable style={styles.actionBtn} onPress={onLike}>
          <Ionicons
            name={post.is_liked ? 'heart' : 'heart-outline'}
            size={22}
            color={post.is_liked ? COLORS.danger : COLORS.textSecondary}
          />
          <Text style={[styles.actionText, post.is_liked && { color: COLORS.danger }]}>Like</Text>
        </Pressable>
        <Pressable style={styles.actionBtn} onPress={onComment}>
          <Ionicons name="chatbubble-outline" size={20} color={COLORS.textSecondary} />
          <Text style={styles.actionText}>Comment</Text>
        </Pressable>
        <Pressable style={styles.actionBtn}>
          <Ionicons name="share-social-outline" size={20} color={COLORS.textSecondary} />
          <Text style={styles.actionText}>Share</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: COLORS.card,
    marginBottom: 8,
    paddingBottom: 4,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    gap: 10,
  },
  headerText: { flex: 1 },
  name: { fontWeight: '700', fontSize: 15, color: COLORS.text },
  time: { fontSize: 12, color: COLORS.textSecondary, marginTop: 2 },
  content: {
    paddingHorizontal: 12,
    paddingBottom: 10,
    fontSize: 15,
    color: COLORS.text,
    lineHeight: 20,
  },
  media: { width: '100%', height: 280, backgroundColor: COLORS.background },
  stats: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: COLORS.border,
  },
  statText: { fontSize: 13, color: COLORS.textSecondary },
  actions: {
    flexDirection: 'row',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: COLORS.border,
    paddingVertical: 4,
  },
  actionBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 8,
  },
  actionText: { fontSize: 13, fontWeight: '600', color: COLORS.textSecondary },
});
