</main>
</div>

<script>
    // Full-Screen Mobile Menu Workspace Toggle (Zero-lag switching)
    const mobileMenuWorkspace = document.getElementById('mobileMenuWorkspace');
    const mainPageContent = document.getElementById('mainPageContent');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenuBtnPill = document.getElementById('mobileMenuBtnPill');
    const mobileMenuBtnIcon = document.getElementById('mobileMenuBtnIcon');

    function toggleMobileSidebar() {
        if (!mobileMenuWorkspace || !mainPageContent) return;
        const isOpening = mobileMenuWorkspace.classList.contains('hidden');
        if (isOpening) {
            mobileMenuWorkspace.classList.remove('hidden');
            mainPageContent.classList.add('hidden');
            if (mobileMenuBtn) mobileMenuBtn.classList.add('text-blue-900', 'font-bold');
            if (mobileMenuBtnPill) mobileMenuBtnPill.classList.add('bg-blue-100');
            if (mobileMenuBtnIcon) mobileMenuBtnIcon.classList.replace('fa-th-large', 'fa-times');
            window.scrollTo(0, 0);
        } else {
            closeSidebar();
        }
    }

    function closeSidebar() {
        if (!mobileMenuWorkspace || !mainPageContent) return;
        mobileMenuWorkspace.classList.add('hidden');
        mainPageContent.classList.remove('hidden');
        if (mobileMenuBtnIcon) mobileMenuBtnIcon.classList.replace('fa-times', 'fa-th-large');
    }

    // Notifications Toggle
    function toggleNotifications(e) {
        if (e) e.stopPropagation();
        const dd = document.getElementById('notifDropdown');
        if (!dd) return;
        dd.classList.toggle('show');
        if (dd.classList.contains('show')) {
            loadNotifications();
        }
        // Close user dropdown when opening notifications
        const ud = document.getElementById('userDropdown');
        if (ud) ud.classList.add('hidden');
    }

    // User Menu Toggle
    function toggleUserMenu() {
        const ud = document.getElementById('userDropdown');
        if (ud) ud.classList.toggle('hidden');
        // Close notifications when opening user menu
        const dd = document.getElementById('notifDropdown');
        if (dd) dd.classList.remove('show');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        const dd = document.getElementById('notifDropdown');
        if (dd && !e.target.closest('#notifDropdown') && !e.target.closest('#mobileNotifWrapper') && !e.target.closest('#desktopNotifWrapper')) {
            dd.classList.remove('show');
        }
        const ud = document.getElementById('userDropdown');
        if (ud && !e.target.closest('#userMenuWrapper')) {
            ud.classList.add('hidden');
        }
    });

    // Register PWA Service Worker & Install Prompt
    let deferredInstallPrompt = null;
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('service-worker.js').catch(() => {});
        });
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstallPrompt = e;
        const installBtn = document.getElementById('pwaInstallBtn');
        if (installBtn) {
            installBtn.classList.remove('hidden');
        }
    });

    function installPWA() {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        deferredInstallPrompt.userChoice.then(() => {
            deferredInstallPrompt = null;
            const installBtn = document.getElementById('pwaInstallBtn');
            if (installBtn) installBtn.classList.add('hidden');
        });
    }

    // Load notifications via AJAX
    function loadNotifications() {
        fetch('api/notifications.php?action=list')
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('notifList');
                if (data.length === 0) {
                    list.innerHTML = '<div class="p-6 text-center text-gray-400 text-sm"><i class="fas fa-check-circle text-2xl mb-2 text-green-400"></i><br>ไม่มีการแจ้งเตือนใหม่</div>';
                    return;
                }
                let html = '';
                data.forEach(n => {
                    const typeColors = { info: 'blue', success: 'green', warning: 'yellow', danger: 'red' };
                    const color = typeColors[n.type] || 'blue';
                    html += `
                    <a href="${n.link || '#'}" class="block px-4 py-3 border-b border-gray-50 hover:bg-blue-50/50 transition-colors ${n.is_read ? 'opacity-60' : ''}">
                        <div class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-${color}-500 mt-2 flex-shrink-0 ${!n.is_read ? 'pulse-dot' : ''}"></div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">${n.title}</p>
                                <p class="text-xs text-gray-500 mt-0.5">${n.message}</p>
                                <p class="text-xs text-gray-400 mt-1">${n.created_at}</p>
                            </div>
                        </div>
                    </a>
                `;
                });
                list.innerHTML = html;
            })
            .catch(() => {
                document.getElementById('notifList').innerHTML = '<div class="p-4 text-center text-red-400 text-sm">โหลดไม่สำเร็จ</div>';
            });
    }

    // Global SweetAlert theme (used across many pages)
    const SwalThemed = Swal.mixin({
        confirmButtonColor: '#1e3a8a',
        cancelButtonColor: '#6b7280',
        customClass: {
            confirmButton: 'rounded-lg px-6 py-2 font-semibold',
            cancelButton: 'rounded-lg px-6 py-2 font-semibold',
        }
    });

    // Utility: Show loading
    function showLoading(msg = 'กำลังดำเนินการ...') {
        Swal.fire({ title: msg, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    }

    // Confirm Logout — Premium Themed
    function confirmLogout(e) {
        if (e) e.preventDefault();

        Swal.fire({
            html: `<div class="relative overflow-hidden">
                        <!-- Header banner -->
                        <div class="bg-gradient-to-br from-blue-900 via-blue-800 to-blue-900 -mx-6 -mt-6 pt-8 pb-10 px-6 relative">
                            <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;%23fff&quot; fill-opacity=&quot;0.15&quot;%3E%3Cpath d=&quot;M20 20h20v20H20z&quot;/%3E%3Cpath d=&quot;M0 0h20v20H0z&quot;/%3E%3C/g%3E%3C/svg%3E');"></div>
                            <div class="relative w-20 h-20 mx-auto bg-white/15 backdrop-blur rounded-2xl flex items-center justify-center border border-white/20 shadow-lg">
                                <i class="fas fa-right-from-bracket text-yellow-400 text-3xl drop-shadow"></i>
                            </div>
                        </div>
                        
                        <!-- Golden accent line -->
                        <div class="h-1 bg-gradient-to-r from-transparent via-yellow-500 to-transparent -mx-6"></div>
                        
                        <!-- Content -->
                        <div class="pt-5 pb-1 px-2">
                            <h3 class="text-2xl font-extrabold text-gray-800 mb-1">ออกจากระบบ</h3>
                            <p class="text-xs font-bold text-blue-900/40 tracking-widest uppercase mb-4">Sign Out Confirmation</p>
                            
                            <div class="bg-blue-50/60 rounded-xl px-4 py-3 mx-1 border border-blue-100 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-blue-900/10 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-question text-blue-900/60 text-sm"></i>
                                </div>
                                <p class="text-gray-600 text-sm leading-relaxed text-left">
                                    คุณต้องการออกจากระบบใช่หรือไม่?
                                </p>
                            </div>
                        </div>
                    </div>`,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-right-from-bracket mr-1"></i> ออกจากระบบ',
            cancelButtonText: '<i class="fas fa-times mr-1"></i> ยกเลิก',
            buttonsStyling: false,
            customClass: {
                popup: 'rounded-2xl p-6 border-0 shadow-2xl pt-0 overflow-hidden',
                actions: 'mt-4 gap-3 w-full px-4',
                confirmButton: 'bg-gradient-to-r from-blue-900 to-blue-800 text-white px-7 py-3 rounded-xl font-bold hover:from-blue-800 hover:to-blue-700 shadow-lg shadow-blue-900/25 transition-all duration-200 transform hover:-translate-y-0.5 active:translate-y-0 text-sm tracking-wide',
                cancelButton: 'bg-white text-gray-500 px-7 py-3 rounded-xl font-semibold hover:bg-gray-50 border-2 border-gray-200 transition-all duration-200 hover:text-gray-700 hover:border-gray-300 text-sm'
            },
            showCloseButton: true,
            reverseButtons: true,
            focusCancel: true,
            width: '400px'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    html: `
                        <div class="py-6">
                            <div class="relative mx-auto w-16 h-16 mb-5">
                                <div class="absolute inset-0 rounded-full border-4 border-blue-100"></div>
                                <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-blue-900 animate-spin"></div>
                                <div class="absolute inset-2 rounded-full bg-blue-50 flex items-center justify-center">
                                    <i class="fas fa-right-from-bracket text-blue-900 text-lg"></i>
                                </div>
                            </div>
                            <p class="text-blue-900 font-bold text-lg">กำลังออกจากระบบ...</p>
                            <p class="text-gray-400 text-xs mt-1">กรุณารอสักครู่</p>
                        </div>
                    `,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    customClass: {
                        popup: 'rounded-2xl border-0 shadow-2xl'
                    },
                    didOpen: () => {
                        window.location.href = 'logout.php?confirm=true';
                    }
                });
            }
        });
    }
</script>
</body>

</html>