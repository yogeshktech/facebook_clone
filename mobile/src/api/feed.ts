import { api } from './client';
import type { Paginated, Post, Story } from '../types';

export async function getFeed(page = 1): Promise<Paginated<Post>> {
  const { data } = await api.get<Paginated<Post>>('/feed', { params: { page } });
  return data;
}

export async function getStories(): Promise<Record<string, Story[]>> {
  const { data } = await api.get<Record<string, Story[]>>('/stories');
  return data;
}

export async function likePost(postId: number): Promise<{ liked: boolean }> {
  const { data } = await api.post(`/posts/${postId}/like`);
  return data;
}

export async function commentPost(postId: number, content: string) {
  const { data } = await api.post(`/posts/${postId}/comment`, { content });
  return data;
}

export async function getReels(cursor?: string) {
  const { data } = await api.get('/reels', { params: cursor ? { cursor } : {} });
  return data;
}

export async function likeReel(reelId: number) {
  const { data } = await api.post(`/reels/${reelId}/like`);
  return data;
}
