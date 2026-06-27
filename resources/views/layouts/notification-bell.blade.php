            <div class="relative hidden md:block" id="notification-bell">
                <button type="button" class="nav-icon relative" title="Notifications" aria-label="Notifications">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                    <span id="notification-count" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"></span>
                </button>

                <div id="notification-dropdown" class="hidden absolute right-0 top-full mt-2 w-96 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                        <h3 class="font-bold text-gray-900">Notifications</h3>
                        <span id="dropdown-notification-count" class="hidden bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"></span>
                    </div>
                    <div id="notification-dropdown-list" class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                        <p class="p-4 text-center text-sm text-gray-500">Loading...</p>
                    </div>
                    <div class="border-t border-gray-100 p-2 flex gap-2">
                        <button type="button" id="mark-all-read-dropdown" class="flex-1 text-center text-xs font-semibold text-fb-blue hover:bg-gray-50 py-2 rounded-lg">Mark all read</button>
                        <a href="{{ route('notifications.index') }}" class="flex-1 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50 py-2 rounded-lg">See all</a>
                    </div>
                </div>
            </div>
