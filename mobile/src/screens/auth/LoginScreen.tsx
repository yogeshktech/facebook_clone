import { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { COLORS } from '../../config/constants';
import { useAuth } from '../../context/AuthContext';
import { getErrorMessage } from '../../api/client';

type Props = {
  onRegister: () => void;
};

export function LoginScreen({ onRegister }: Props) {
  const { signIn } = useAuth();
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleLogin = async () => {
    setError('');

    const emailOrPhone = login.trim();
    if (!emailOrPhone) {
      setError('Please enter your email or mobile number.');
      return;
    }
    if (!password) {
      setError('Please enter your password.');
      return;
    }

    setLoading(true);
    try {
      await signIn(emailOrPhone, password);
    } catch (e) {
      setError(getErrorMessage(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'bottom']}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 8 : 0}
      >
        <ScrollView
          contentContainerStyle={styles.scroll}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.inner}>
            <Text style={styles.logo}>newbook</Text>
            <Text style={styles.tagline}>Connect with friends and the world around you.</Text>

            <TextInput
              style={styles.input}
              placeholder="Email or mobile number"
              placeholderTextColor={COLORS.textSecondary}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              textContentType="username"
              value={login}
              onChangeText={setLogin}
              editable={!loading}
            />

            <View style={styles.passwordWrap}>
              <TextInput
                style={styles.passwordInput}
                placeholder="Password"
                placeholderTextColor={COLORS.textSecondary}
                secureTextEntry={!showPassword}
                textContentType="password"
                value={password}
                onChangeText={setPassword}
                editable={!loading}
                onSubmitEditing={handleLogin}
                returnKeyType="go"
              />
              <Pressable
                style={styles.eyeBtn}
                onPress={() => setShowPassword((v) => !v)}
                hitSlop={8}
              >
                <Ionicons
                  name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                  size={22}
                  color={COLORS.textSecondary}
                />
              </Pressable>
            </View>

            {error ? <Text style={styles.error}>{error}</Text> : null}

            <Pressable
              style={[styles.btn, loading && styles.btnDisabled]}
              onPress={handleLogin}
              disabled={loading}
            >
              {loading ? (
                <ActivityIndicator color={COLORS.white} />
              ) : (
                <Text style={styles.btnText}>Log In</Text>
              )}
            </Pressable>

            <Pressable onPress={onRegister} style={styles.linkWrap} disabled={loading}>
              <Text style={styles.link}>Create new account</Text>
            </Pressable>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: COLORS.background },
  flex: { flex: 1 },
  scroll: { flexGrow: 1, justifyContent: 'center', padding: 24 },
  inner: { width: '100%', maxWidth: 400, alignSelf: 'center' },
  logo: {
    fontSize: 48,
    fontWeight: '800',
    color: COLORS.primary,
    textAlign: 'center',
    letterSpacing: -1,
  },
  tagline: {
    textAlign: 'center',
    color: COLORS.textSecondary,
    marginBottom: 28,
    marginTop: 8,
    fontSize: 15,
    lineHeight: 22,
  },
  input: {
    backgroundColor: COLORS.card,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 8,
    padding: 14,
    fontSize: 16,
    marginBottom: 12,
    color: COLORS.text,
  },
  passwordWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.card,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 8,
    marginBottom: 12,
  },
  passwordInput: {
    flex: 1,
    padding: 14,
    fontSize: 16,
    color: COLORS.text,
  },
  eyeBtn: { paddingHorizontal: 14, paddingVertical: 12 },
  btn: {
    backgroundColor: COLORS.primary,
    borderRadius: 8,
    padding: 14,
    alignItems: 'center',
    marginTop: 4,
    minHeight: 50,
    justifyContent: 'center',
  },
  btnDisabled: { opacity: 0.7 },
  btnText: { color: COLORS.white, fontWeight: '700', fontSize: 17 },
  error: {
    color: COLORS.danger,
    marginBottom: 10,
    textAlign: 'center',
    lineHeight: 20,
    fontSize: 14,
  },
  linkWrap: { marginTop: 24, alignItems: 'center', padding: 8 },
  link: { color: COLORS.primary, fontWeight: '600', fontSize: 15 },
});
