import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import * as authApi from '../api/auth';
import { getToken, clearToken } from '../api/client';
import type { User } from '../types';

type AuthContextValue = {
  user: User | null;
  loading: boolean;
  signIn: (login: string, password: string) => Promise<void>;
  signUpVerify: (email: string, otp: string) => Promise<void>;
  signOut: () => Promise<void>;
  refreshUser: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const refreshUser = useCallback(async () => {
    const me = await authApi.fetchMe();
    setUser(me);
  }, []);

  useEffect(() => {
    (async () => {
      try {
        const token = await getToken();
        if (token) {
          const me = await authApi.fetchMe();
          setUser(me);
        }
      } catch {
        try {
          await authApi.logout();
        } catch {
          await clearToken();
        }
        setUser(null);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const signIn = useCallback(async (login: string, password: string) => {
    const { user: u } = await authApi.login(login, password);
    setUser(u);
  }, []);

  const signUpVerify = useCallback(async (email: string, otp: string) => {
    const { user: u } = await authApi.verifyOtp(email, otp);
    setUser(u);
  }, []);

  const signOut = useCallback(async () => {
    await authApi.logout();
    setUser(null);
  }, []);

  const value = useMemo(
    () => ({ user, loading, signIn, signUpVerify, signOut, refreshUser }),
    [user, loading, signIn, signUpVerify, signOut, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
