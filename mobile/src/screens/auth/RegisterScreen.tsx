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
import * as authApi from '../../api/auth';
import { getErrorMessage } from '../../api/client';
import { COLORS } from '../../config/constants';

type Props = {
  onBack: () => void;
  onOtpSent: (email: string) => void;
};

export function RegisterScreen({ onBack, onOtpSent }: Props) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleRegister = async () => {
    setError('');
    setLoading(true);
    try {
      await authApi.sendOtp({
        name: name.trim(),
        email: email.trim(),
        phone: phone.trim(),
        password,
        password_confirmation: confirm,
      });
      onOtpSent(email.trim());
    } catch (e) {
      setError(getErrorMessage(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        <Pressable onPress={onBack}>
          <Text style={styles.back}>← Back</Text>
        </Pressable>
        <Text style={styles.title}>Join Newbook</Text>

        <TextInput style={styles.input} placeholder="Full name" value={name} onChangeText={setName} />
        <TextInput
          style={styles.input}
          placeholder="Email"
          autoCapitalize="none"
          keyboardType="email-address"
          value={email}
          onChangeText={setEmail}
        />
        <TextInput
          style={styles.input}
          placeholder="Mobile (10 digits)"
          keyboardType="phone-pad"
          maxLength={10}
          value={phone}
          onChangeText={setPhone}
        />
        <TextInput style={styles.input} placeholder="Password" secureTextEntry value={password} onChangeText={setPassword} />
        <TextInput style={styles.input} placeholder="Confirm password" secureTextEntry value={confirm} onChangeText={setConfirm} />

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <Pressable style={styles.btn} onPress={handleRegister} disabled={loading}>
          {loading ? <ActivityIndicator color={COLORS.white} /> : <Text style={styles.btnText}>Send OTP</Text>}
        </Pressable>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.background },
  container: { padding: 24, paddingTop: 48 },
  back: { color: COLORS.primary, fontWeight: '600', marginBottom: 16 },
  title: { fontSize: 28, fontWeight: '800', color: COLORS.text, marginBottom: 20 },
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
  btn: {
    backgroundColor: COLORS.success,
    borderRadius: 8,
    padding: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  btnText: { color: COLORS.white, fontWeight: '700', fontSize: 17 },
  error: { color: COLORS.danger, marginBottom: 8 },
});
