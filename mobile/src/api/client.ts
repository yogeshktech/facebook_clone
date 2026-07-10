import AsyncStorage from '@react-native-async-storage/async-storage';
import axios, { AxiosError } from 'axios';
import * as SecureStore from 'expo-secure-store';
import { API_BASE_URL } from '../config/constants';

const TOKEN_KEY = 'newbook_token';

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 30000,
});

api.interceptors.request.use(async (config) => {
  const token = await getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

async function writeToken(token: string): Promise<void> {
  try {
    await SecureStore.setItemAsync(TOKEN_KEY, token);
  } catch {
    await AsyncStorage.setItem(TOKEN_KEY, token);
  }
}

async function readToken(): Promise<string | null> {
  try {
    const secure = await SecureStore.getItemAsync(TOKEN_KEY);
    if (secure) return secure;
  } catch {
    // fall through to AsyncStorage
  }
  return AsyncStorage.getItem(TOKEN_KEY);
}

async function removeToken(): Promise<void> {
  try {
    await SecureStore.deleteItemAsync(TOKEN_KEY);
  } catch {
    // ignore
  }
  await AsyncStorage.removeItem(TOKEN_KEY);
}

export async function saveToken(token: string): Promise<void> {
  await writeToken(token);
}

export async function getToken(): Promise<string | null> {
  return readToken();
}

export async function clearToken(): Promise<void> {
  await removeToken();
}

export function getErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const axErr = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>;

    if (!axErr.response) {
      if (axErr.code === 'ECONNABORTED') {
        return 'Request timed out. Check your internet connection.';
      }
      return 'Cannot reach server. Check internet or try again later.';
    }

    const data = axErr.response.data;
    if (data?.errors) {
      const first = Object.values(data.errors)[0];
      if (first?.[0]) return first[0];
    }
    if (data?.message) return data.message;
    if (axErr.message) return axErr.message;
  }
  return 'Something went wrong. Please try again.';
}
