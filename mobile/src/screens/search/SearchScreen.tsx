import { useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import * as socialApi from '../../api/social';
import { Avatar } from '../../components/Avatar';
import { COLORS } from '../../config/constants';
import type { User } from '../../types';
import type { RootStackParamList } from '../../navigation/types';

export function SearchScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [query, setQuery] = useState('');
  const [users, setUsers] = useState<User[]>([]);

  const search = async (q: string) => {
    setQuery(q);
    if (q.length < 2) {
      setUsers([]);
      return;
    }
    const res = await socialApi.searchUsers(q);
    setUsers(res.users);
  };

  return (
    <View style={styles.container}>
      <TextInput
        style={styles.input}
        placeholder="Search Newbook"
        placeholderTextColor={COLORS.textSecondary}
        value={query}
        onChangeText={search}
        autoFocus
      />
      <FlatList
        data={users}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <Pressable
            style={styles.row}
            onPress={() => navigation.navigate('Profile', { userId: item.id })}
          >
            <Avatar uri={item.avatar_url} name={item.name} />
            <Text style={styles.name}>{item.name}</Text>
          </Pressable>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  input: {
    margin: 12,
    backgroundColor: COLORS.card,
    borderRadius: 20,
    paddingHorizontal: 16,
    paddingVertical: 10,
    fontSize: 16,
    color: COLORS.text,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    backgroundColor: COLORS.card,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: COLORS.border,
  },
  name: { fontSize: 16, fontWeight: '600', color: COLORS.text },
});
