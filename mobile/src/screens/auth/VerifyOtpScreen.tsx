import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { COLORS } from '../../config/constants';
import { useAuth } from '../../context/AuthContext';
import { getErrorMessage } from '../../api/client';

type Props = {
  email: string;
  onBack: () => void;
};

export function VerifyOtpScreen({ email, onBack }: Props) {
  const { signUpVerify } = useAuth();
  const [otp, setOtp] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleVerify = async () => {
    setError('');
    setLoading(true);
    try {
      await signUpVerify(email, otp.trim());
    } catch (e) {
      setError(getErrorMessage(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <Pressable onPress={onBack}>
        <Text style={styles.back}>← Back</Text>
      </Pressable>
      <Text style={styles.title}>Verify OTP</Text>
      <Text style={styles.sub}>Enter the 6-digit code sent to {email}</Text>

      <TextInput
        style={styles.input}
        placeholder="000000"
        keyboardType="number-pad"
        maxLength={6}
        value={otp}
        onChangeText={setOtp}
      />

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <Pressable style={styles.btn} onPress={handleVerify} disabled={loading}>
        {loading ? <ActivityIndicator color={COLORS.white} /> : <Text style={styles.btnText}>Verify & Join</Text>}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background, padding: 24, paddingTop: 48 },
  back: { color: COLORS.primary, fontWeight: '600', marginBottom: 16 },
  title: { fontSize: 28, fontWeight: '800', color: COLORS.text },
  sub: { color: COLORS.textSecondary, marginBottom: 20, marginTop: 8 },
  input: {
    backgroundColor: COLORS.card,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 8,
    padding: 14,
    fontSize: 24,
    letterSpacing: 8,
    textAlign: 'center',
    color: COLORS.text,
  },
  btn: {
    backgroundColor: COLORS.primary,
    borderRadius: 8,
    padding: 14,
    alignItems: 'center',
    marginTop: 20,
  },
  btnText: { color: COLORS.white, fontWeight: '700', fontSize: 17 },
  error: { color: COLORS.danger, marginTop: 12 },
});
