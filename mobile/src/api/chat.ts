import { api } from './client';
import type { Conversation, Message } from '../types';

export async function getConversations(): Promise<Conversation[]> {
  const { data } = await api.get<Conversation[]>('/conversations');
  return data;
}

export async function getMessages(conversationId: number, afterId?: number) {
  const { data } = await api.get<{ messages: Message[] }>(
    `/conversations/${conversationId}/messages`,
    { params: afterId ? { after_id: afterId } : {} },
  );
  return data.messages;
}

export async function sendMessage(conversationId: number, body: string) {
  const { data } = await api.post<Message>(`/conversations/${conversationId}/messages`, { body });
  return data;
}

export async function startConversation(userId: number): Promise<Conversation> {
  const { data } = await api.post<Conversation>(`/conversations/start/${userId}`);
  return data;
}
