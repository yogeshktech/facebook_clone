<nav class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 md:hidden safe-area-bottom">
    <div class="flex items-center justify-around h-14 px-1">
        <a href="{{ route('feed.index') }}" class="mobile-nav-icon {{ request()->routeIs('feed.*') ? 'active' : '' }}" title="Home">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span>Home</span>
        </a>
        <a href="{{ route('friends.index') }}" class="mobile-nav-icon {{ request()->routeIs('friends.*') ? 'active' : '' }}" title="Friends">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            <span>Friends</span>
        </a>
        <a href="{{ route('groups.index') }}" class="mobile-nav-icon {{ request()->routeIs('groups.*') ? 'active' : '' }}" title="Groups">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
            <span>Groups</span>
        </a>
        <a href="{{ route('chat.index') }}" class="mobile-nav-icon {{ request()->routeIs('chat.*') ? 'active' : '' }}" title="Messenger">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
            <span>Chat</span>
        </a>
        <a href="{{ route('notifications.index') }}" class="mobile-nav-icon relative {{ request()->routeIs('notifications.*') ? 'active' : '' }}" title="Notifications">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
            <span id="mobile-notification-count" class="hidden absolute top-0 right-2 bg-red-500 text-white text-[10px] rounded-full min-w-[16px] h-4 px-1 flex items-center justify-center"></span>
            <span>Alerts</span>
        </a>
    </div>
</nav>
