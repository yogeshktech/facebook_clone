import { useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, StyleSheet, Text, View } from 'react-native';
import type { RouteProp } from '@react-navigation/native';
import { useRoute } from '@react-navigation/native';
import { Image } from 'expo-image';
import * as socialApi from '../../api/social';
import { Avatar } from '../../components/Avatar';
import { PostCard } from '../../components/PostCard';
import { COLORS } from '../../config/constants';
import type { Post, User } from '../../types';
import type { RootStackParamList } from '../../navigation/types';
import * as feedApi from '../../api/feed';

export function ProfileScreen() {
  const route = useRoute<RouteProp<RootStackParamList, 'Profile'>>();
  const { userId } = route.params;
  const [profile, setProfile] = useState<User | null>(null);
  const [posts, setPosts] = useState<Post[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    socialApi.getProfile(userId).then((data) => {
      setProfile(data.user);
      setPosts(data.posts?.data ?? []);
      setLoading(false);
    });
  }, [userId]);

  if (loading || !profile) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={COLORS.primary} size="large" />
      </View>
    );
  }

  return (
    <FlatList
      data={posts}
      keyExtractor={(item) => String(item.id)}
      ListHeaderComponent={
        <View>
          {profile.cover_photo_url ? (
            <Image source={{ uri: profile.cover_photo_url }} style={styles.cover} contentFit="cover" />
          ) : (
            <View style={[styles.cover, styles.coverPlaceholder]} />
          )}
          <View style={styles.profileHeader}>
            <Avatar uri={profile.avatar_url} name={profile.name} size={88} />
            <Text style={styles.name}>{profile.name}</Text>
            {profile.bio ? <Text style={styles.bio}>{profile.bio}</Text> : null}
          </View>
        </View>
      }
      renderItem={({ item }) => (
        <PostCard post={item} onLike={() => feedApi.likePost(item.id)} />
      )}
    />
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  cover: { width: '100%', height: 160 },
  coverPlaceholder: { backgroundColor: COLORS.border },
  profileHeader: { alignItems: 'center', padding: 16, marginTop: -44, backgroundColor: COLORS.card, marginBottom: 8 },
  name: { fontSize: 22, fontWeight: '800', color: COLORS.text, marginTop: 8 },
  bio: { color: COLORS.textSecondary, marginTop: 6, textAlign: 'center' },
});
