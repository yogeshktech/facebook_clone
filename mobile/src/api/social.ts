import { api } from './client';
import type { Friendship, Notification, Paginated, User } from '../types';

export async function getFriends() {
  const { data } = await api.get<{
    friends: User[];
    pending: Friendship[];
    sent: Friendship[];
  }>('/friends');
  return data;
}

export async function sendFriendRequest(userId: number) {
  const { data } = await api.post(`/friends/${userId}`);
  return data;
}

export async function acceptFriendship(friendshipId: number) {
  const { data } = await api.post(`/friendships/${friendshipId}/accept`);
  return data;
}

export async function rejectFriendship(friendshipId: number) {
  const { data } = await api.post(`/friendships/${friendshipId}/reject`);
  return data;
}

export async function searchUsers(q: string) {
  const { data } = await api.get<{ users: User[]; groups: unknown[]; pages: unknown[] }>('/search', {
    params: { q },
  });
  return data;
}

export async function getNotifications(page = 1): Promise<Paginated<Notification>> {
  const { data } = await api.get<Paginated<Notification>>('/notifications', { params: { page } });
  return data;
}

export async function getNotificationCount(): Promise<number> {
  const { data } = await api.get<{ count: number }>('/notifications/count');
  return data.count;
}

export async function markNotificationRead(id: number) {
  await api.post(`/notifications/${id}/read`);
}

export async function getProfile(userId: number) {
  const { data } = await api.get(`/users/${userId}`);
  return data;
}
