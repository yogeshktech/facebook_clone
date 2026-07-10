import { api, saveToken, clearToken } from './client';
import type { User } from '../types';

export type LoginResponse = { user: User; token: string };

export async function login(login: string, password: string): Promise<LoginResponse> {
  const { data } = await api.post<LoginResponse>('/login', { login, password });
  await saveToken(data.token);
  return data;
}

export async function sendOtp(payload: {
  name: string;
  email: string;
  phone: string;
  password: string;
  password_confirmation: string;
}) {
  const { data } = await api.post('/register/send-otp', payload);
  return data;
}

export async function verifyOtp(email: string, otp: string): Promise<LoginResponse> {
  const { data } = await api.post<LoginResponse>('/register/verify-otp', { email, otp });
  await saveToken(data.token);
  return data;
}

export async function fetchMe(): Promise<User> {
  const { data } = await api.get<User>('/user');
  return data;
}

export async function logout(): Promise<void> {
  try {
    await api.post('/logout');
  } finally {
    await clearToken();
  }
}
