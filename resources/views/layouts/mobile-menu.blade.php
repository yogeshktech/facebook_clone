{{-- Mobile offcanvas menu (slides from right, 50% width) --}}
<div id="mobile-offcanvas-backdrop" class="fixed inset-0 bg-black/50 z-50 hidden md:hidden" onclick="closeMobileMenu()"></div>
<aside id="mobile-offcanvas" class="fixed top-0 right-0 h-full w-1/2 min-w-[200px] max-w-xs bg-white z-50 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out md:hidden flex flex-col">
    <div class="flex items-center justify-between p-4 border-b">
        <span class="font-bold text-lg bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">Menu</span>
        <button type="button" onclick="closeMobileMenu()" class="text-gray-500 hover:text-gray-800 text-2xl leading-none" aria-label="Close menu">&times;</button>
    </div>

    <div class="flex-1 overflow-y-auto p-3 space-y-1">
        <a href="{{ route('profile.show', auth()->user()) }}" class="sidebar-link" onclick="closeMobileMenu()">
            <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-9 h-9 rounded-full object-cover">
            <span>{{ auth()->user()->name }}</span>
        </a>
        <a href="{{ route('feed.index') }}" class="sidebar-link {{ request()->routeIs('feed.*') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span>Home</span>
        </a>
        <a href="{{ route('reels.index') }}" class="sidebar-link {{ request()->routeIs('reels.*') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4h-4z"/></svg>
            <span>Reels</span>
        </a>
        <a href="{{ route('friends.index') }}" class="sidebar-link {{ request()->routeIs('friends.*') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            <span>Friends</span>
        </a>
        <a href="{{ route('groups.index') }}" class="sidebar-link {{ request()->routeIs('groups.*') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
            <span>Groups</span>
        </a>
        <a href="{{ route('pages.index') }}" class="sidebar-link {{ request()->routeIs('pages.*') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11z"/><circle cx="17.5" cy="17.5" r="4.5"/><circle cx="6.5" cy="17.5" r="4.5"/></svg>
            <span>Pages</span>
        </a>
        <a href="{{ route('chat.index') }}" class="sidebar-link {{ request()->routeIs('chat.*') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
            <span>Messenger</span>
        </a>
        <a href="{{ route('stories.index') }}" class="sidebar-link {{ request()->routeIs('stories.*') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            <span>Stories</span>
        </a>
        <a href="{{ route('search') }}" class="sidebar-link {{ request()->routeIs('search') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C8.01 14 6 11.99 6 9.5S8.01 5 10.5 5 15 7.01 15 9.5 12.99 14 10.5 14z"/></svg>
            <span>Search</span>
        </a>
        <a href="{{ route('notifications.index') }}" class="sidebar-link {{ request()->routeIs('notifications.*') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
            <span>Notifications</span>
        </a>
        <a href="{{ route('profile.edit') }}" class="sidebar-link {{ request()->routeIs('profile.edit') ? 'text-fb-blue' : '' }}" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            <span>Edit Profile</span>
        </a>
        <a href="{{ auth()->user()->isAdmin() ? route('admin.ads.index') : route('ads.index') }}" class="sidebar-link" onclick="closeMobileMenu()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z"/></svg>
            <span>{{ auth()->user()->isAdmin() ? 'Admin Ads' : 'Ads Manager' }}</span>
        </a>

        <hr class="my-2 border-gray-200">

        <button type="button" id="pwa-install-btn" class="hidden sidebar-link w-full text-left text-fb-blue" onclick="installPwa()">
            <svg class="w-9 h-9 text-fb-blue" fill="currentColor" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
            <span>Install App</span>
        </button>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="sidebar-link w-full text-left text-red-600 hover:bg-red-50">
                <svg class="w-9 h-9 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
