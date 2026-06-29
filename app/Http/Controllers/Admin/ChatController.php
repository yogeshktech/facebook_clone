<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = Conversation::query()
            ->with(['users', 'latestMessage.user'])
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate(25);

        return view('admin.chats.index', compact('conversations'));
    }

    public function show(Conversation $conversation): View
    {
        $conversation->load(['users']);

        $messages = $conversation->messages()
            ->with('user')
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->sortBy('created_at')
            ->values();

        return view('admin.chats.show', compact('conversation', 'messages'));
    }

    public function search(Request $request): View
    {
        $query = trim($request->input('q', ''));

        $messages = collect();

        if (strlen($query) >= 2) {
            $messages = Message::query()
                ->with(['user', 'conversation.users'])
                ->latest('created_at')
                ->limit(500)
                ->get()
                ->filter(fn (Message $m) => str_contains(
                    strtolower($m->body),
                    strtolower($query)
                ))
                ->take(50)
                ->values();
        }

        return view('admin.chats.search', compact('query', 'messages'));
    }
}
