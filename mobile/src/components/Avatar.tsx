import { Image, StyleSheet, Text, View } from 'react-native';
import { COLORS } from '../config/constants';

type Props = {
  uri?: string;
  name: string;
  size?: number;
};

export function Avatar({ uri, name, size = 40 }: Props) {
  const source = uri ? { uri } : undefined;
  const initials = name.slice(0, 1).toUpperCase();

  return source ? (
    <Image source={source} style={[styles.image, { width: size, height: size, borderRadius: size / 2 }]} />
  ) : (
    <View style={[styles.fallback, { width: size, height: size, borderRadius: size / 2 }]}>
      <Text style={[styles.initials, { fontSize: size * 0.4 }]}>{initials}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  image: { backgroundColor: COLORS.border },
  fallback: {
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  initials: { color: COLORS.white, fontWeight: '700' },
});
