@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="max-w-2xl mx-auto p-4">
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b flex justify-between items-center">
            <h1 class="text-2xl font-bold">Notifications</h1>
            <button type="button" id="mark-all-read-btn" class="text-fb-blue text-sm font-semibold hover:underline">Mark all as read</button>
        </div>
        <div class="divide-y" id="notifications-list">
            @forelse($notifications as $notification)
            <a href="{{ $notification->url ?? '#' }}"
               data-notification-id="{{ $notification->id }}"
               class="notification-item block p-4 flex gap-3 hover:bg-gray-50 transition {{ $notification->is_read ? '' : 'bg-blue-50' }}">
                @if($notification->sender)
                <img src="{{ $notification->sender->avatar_url }}" alt="" class="w-12 h-12 rounded-full object-cover flex-shrink-0">
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ $notification->title }}</p>
                    <p class="text-sm text-gray-600">{{ $notification->message }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
            </a>
            @empty
            <p class="p-8 text-center text-gray-500">No notifications yet.</p>
            @endforelse
        </div>
        {{ $notifications->links() }}
    </div>
</div>

<script>
document.getElementById('mark-all-read-btn')?.addEventListener('click', async () => {
    await fetch('{{ route('notifications.readAll') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            'Accept': 'application/json',
        },
    });
    document.querySelectorAll('.notification-item').forEach(el => el.classList.remove('bg-blue-50'));
});

document.querySelectorAll('.notification-item[data-notification-id]').forEach(el => {
    el.addEventListener('click', async () => {
        const id = el.dataset.notificationId;
        if (!id) return;
        await fetch(`/notifications/${id}/read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
        });
        el.classList.remove('bg-blue-50');
    });
});
</script>
@endsection
