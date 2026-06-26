<nav class="fixed top-0 left-0 right-0 z-40 bg-white shadow-sm h-14 flex items-center px-3 sm:px-4">
    <div class="max-w-7xl mx-auto w-full flex items-center justify-between gap-2 sm:gap-4">
        {{-- Logo & Search --}}
        <div class="flex items-center gap-2 flex-1 min-w-0">
            <a href="{{ route('feed.index') }}" class="flex items-center gap-2 flex-shrink-0">
                <x-brand-logo size="sm" />
                <span class="hidden sm:block text-xl font-bold bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">Newbook</span>
            </a>
            <form action="{{ route('search') }}" method="GET" class="hidden md:block">
                <input type="search" name="q" value="{{ request('q') }}"
                    placeholder="Search Newbook"
                    class="bg-fb-gray rounded-full px-4 py-2 w-60 text-sm focus:outline-none focus:ring-2 focus:ring-fb-blue">
            </form>
            <a href="{{ route('search') }}" class="md:hidden nav-icon flex-shrink-0" title="Search">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C8.01 14 6 11.99 6 9.5S8.01 5 10.5 5 15 7.01 15 9.5 12.99 14 10.5 14z"/></svg>
            </a>
        </div>

        {{-- Nav Icons (tablet/desktop) --}}
        <div class="hidden md:flex items-center gap-1">
            <a href="{{ route('feed.index') }}" class="nav-icon {{ request()->routeIs('feed.*') ? 'active' : '' }}" title="Home">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            </a>
            <a href="{{ route('friends.index') }}" class="nav-icon {{ request()->routeIs('friends.*') ? 'active' : '' }}" title="Friends">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </a>
            <a href="{{ route('groups.index') }}" class="nav-icon {{ request()->routeIs('groups.*') ? 'active' : '' }}" title="Groups">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
            </a>
            <a href="{{ route('chat.index') }}" class="nav-icon {{ request()->routeIs('chat.*') ? 'active' : '' }}" title="Messenger">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
            </a>
        </div>

        {{-- Right side --}}
        <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
            <div class="relative hidden md:block" id="notification-bell">
                <a href="{{ route('notifications.index') }}" class="nav-icon relative" title="Notifications">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                    <span id="notification-count" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"></span>
                </a>
            </div>
            <a href="{{ route('profile.show', auth()->user()) }}" class="flex items-center gap-2 hover:bg-fb-gray rounded-full p-1 pr-2 sm:pr-3">
                <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover">
                <span class="hidden md:block text-sm font-medium max-w-[120px] truncate">{{ auth()->user()->name }}</span>
            </a>
            <form action="{{ route('logout') }}" method="POST" class="hidden sm:block">
                @csrf
                <button type="submit" class="nav-icon" title="Logout">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                </button>
            </form>
        </div>
    </div>
</nav>
