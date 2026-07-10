import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { Image } from 'expo-image';
import { COLORS } from '../config/constants';
import type { Story, User } from '../types';
import { Avatar } from './Avatar';

type Props = {
  currentUser?: User | null;
  storyGroups: Record<string, Story[]>;
};

export function StoryRow({ currentUser, storyGroups }: Props) {
  const groups = Object.values(storyGroups);

  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.row} contentContainerStyle={styles.content}>
      <View style={styles.storyItem}>
        <View style={styles.addRing}>
          <Avatar uri={currentUser?.avatar_url} name={currentUser?.name ?? 'You'} size={56} />
        </View>
        <Text style={styles.label} numberOfLines={1}>
          Your story
        </Text>
      </View>

      {groups.map((stories) => {
        const story = stories[0];
        const user = story?.user;
        if (!story || !user) return null;
        return (
          <View key={story.user_id} style={styles.storyItem}>
            <View style={styles.ring}>
              {story.media_url ? (
                <Image source={{ uri: story.media_url }} style={styles.storyImage} contentFit="cover" />
              ) : (
                <Avatar uri={user.avatar_url} name={user.name} size={56} />
              )}
            </View>
            <Text style={styles.label} numberOfLines={1}>
              {user.name.split(' ')[0]}
            </Text>
          </View>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: { backgroundColor: COLORS.card, marginBottom: 8 },
  content: { paddingHorizontal: 10, paddingVertical: 12, gap: 12 },
  storyItem: { width: 72, alignItems: 'center' },
  ring: {
    width: 64,
    height: 64,
    borderRadius: 32,
    borderWidth: 3,
    borderColor: COLORS.primary,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
  },
  addRing: {
    width: 64,
    height: 64,
    borderRadius: 32,
    borderWidth: 2,
    borderColor: COLORS.border,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
  },
  storyImage: { width: 58, height: 58, borderRadius: 29 },
  label: { fontSize: 11, marginTop: 6, color: COLORS.text, textAlign: 'center' },
});
