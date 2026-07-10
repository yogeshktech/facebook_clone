export type User = {
  id: number;
  name: string;
  username?: string;
  email: string;
  phone?: string;
  avatar_url?: string;
  cover_photo_url?: string;
  bio?: string;
  location?: string;
  website?: string;
};

export type Post = {
  id: number;
  user_id: number;
  content?: string;
  type: string;
  media_url?: string;
  media_path?: string;
  likes_count?: number;
  comments_count?: number;
  is_liked?: boolean;
  shares_count?: number;
  created_at: string;
  user?: User;
  shared_post?: Post;
};

export type Comment = {
  id: number;
  content: string;
  user_id: number;
  post_id: number;
  parent_id?: number;
  created_at: string;
  user?: User;
};

export type Story = {
  id: number;
  user_id: number;
  media_url?: string;
  media_type?: string;
  caption?: string;
  expires_at: string;
  user?: User;
};

export type Friendship = {
  id: number;
  user_id: number;
  friend_id: number;
  status: string;
  user?: User;
  friend?: User;
};

export type Conversation = {
  id: number;
  name?: string;
  is_group: boolean;
  users?: User[];
  latest_message?: Message;
  updated_at: string;
};

export type Message = {
  id: number;
  conversation_id: number;
  user_id: number;
  body: string;
  media_url?: string;
  media_type?: string;
  created_at: string;
  user?: User;
  user_name?: string;
  is_sender?: boolean;
};

export type Notification = {
  id: number;
  type: string;
  message: string;
  is_read: boolean;
  created_at: string;
  sender?: User;
};

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  next_page_url?: string | null;
};
