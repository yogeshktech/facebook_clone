import { useEffect, useState } from 'react';
import { ActivityIndicator, Dimensions, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import * as feedApi from '../../api/feed';
import { Avatar } from '../../components/Avatar';
import { COLORS } from '../../config/constants';
import type { Post } from '../../types';

const { height } = Dimensions.get('window');

export function ReelsScreen() {
  const [reels, setReels] = useState<Post[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    feedApi.getReels().then((res) => {
      setReels(res.data ?? []);
      setLoading(false);
    });
  }, []);

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={COLORS.white} size="large" />
      </View>
    );
  }

  return (
    <FlatList
      data={reels}
      keyExtractor={(item) => String(item.id)}
      pagingEnabled
      showsVerticalScrollIndicator={false}
      snapToInterval={height}
      decelerationRate="fast"
      renderItem={({ item }) => <ReelItem reel={item} />}
      ListEmptyComponent={<Text style={styles.empty}>No reels yet</Text>}
    />
  );
}

function ReelItem({ reel }: { reel: Post }) {
  const [liked, setLiked] = useState(reel.is_liked ?? false);
  const [likes, setLikes] = useState(reel.likes_count ?? 0);

  const toggleLike = async () => {
    const res = await feedApi.likeReel(reel.id);
    setLiked(res.liked);
    setLikes(res.likes_count ?? likes);
  };

  return (
    <View style={[styles.reel, { height }]}>
      {reel.media_url ? (
        <Image source={{ uri: reel.media_url }} style={StyleSheet.absoluteFill} contentFit="cover" />
      ) : (
        <View style={[StyleSheet.absoluteFill, styles.placeholder]} />
      )}

      <View style={styles.overlay}>
        <View style={styles.userRow}>
          <Avatar uri={reel.user?.avatar_url} name={reel.user?.name ?? 'U'} size={36} />
          <Text style={styles.userName}>{reel.user?.name}</Text>
        </View>
        {reel.content ? <Text style={styles.caption}>{reel.content}</Text> : null}
      </View>

      <View style={styles.actions}>
        <Pressable style={styles.action} onPress={toggleLike}>
          <Ionicons name={liked ? 'heart' : 'heart-outline'} size={32} color={liked ? COLORS.danger : COLORS.white} />
          <Text style={styles.actionLabel}>{likes}</Text>
        </Pressable>
        <Pressable style={styles.action}>
          <Ionicons name="chatbubble-outline" size={30} color={COLORS.white} />
          <Text style={styles.actionLabel}>{reel.comments_count ?? 0}</Text>
        </Pressable>
        <Pressable style={styles.action}>
          <Ionicons name="paper-plane-outline" size={28} color={COLORS.white} />
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, backgroundColor: COLORS.black, alignItems: 'center', justifyContent: 'center' },
  reel: { backgroundColor: COLORS.black, justifyContent: 'flex-end' },
  placeholder: { backgroundColor: '#222' },
  overlay: { padding: 16, paddingRight: 80 },
  userRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 8 },
  userName: { color: COLORS.white, fontWeight: '700', fontSize: 15 },
  caption: { color: COLORS.white, fontSize: 14, lineHeight: 20 },
  actions: { position: 'absolute', right: 12, bottom: 100, gap: 20, alignItems: 'center' },
  action: { alignItems: 'center' },
  actionLabel: { color: COLORS.white, fontSize: 12, marginTop: 4, fontWeight: '600' },
  empty: { color: COLORS.white, textAlign: 'center', marginTop: 100 },
});
