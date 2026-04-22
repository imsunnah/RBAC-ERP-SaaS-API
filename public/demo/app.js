/* ═══════════════════════════════════════════════════════════════════════
   ERP SaaS — RBAC Demo UI JavaScript
   Complete state management, API integration, and dynamic UI rendering.
   ═══════════════════════════════════════════════════════════════════════ */
(() => {
    'use strict';

    const BASE = window.location.origin + '/api/v1';
    const $ = s => document.querySelector(s);
    const $$ = s => document.querySelectorAll(s);

    // ─── State ───────────────────────────────────────────────────────────
    const state = {
        token: null,
        user: null,
        orgId: null,
        orgUuid: null,
        orgName: null,
        organizations: [],
        modules: [],             // modules user has access to
        sidebarData: [],         // sidebar structure from API
        currentModule: null,     // currently selected module
        currentMenu: null,       // currently selected menu item
        isSuperAdmin: false,
    };

    // ─── Lucide icon map (slug → lucide class) ───────────────────────────
    const iconMap = {
        'shield-check': 'icon-shield-check',
        'shield': 'icon-shield',
        'users': 'icon-users',
        'trending-up': 'icon-trending-up',
        'package': 'icon-package',
        'dollar-sign': 'icon-dollar-sign',
        'shopping-cart': 'icon-shopping-cart',
        'phone-call': 'icon-phone',
        'briefcase': 'icon-briefcase',
        'settings': 'icon-settings',
        'book': 'icon-book-open',
        'clock': 'icon-clock',
        'file-text': 'icon-file-text',
        'layers': 'icon-layers',
    };
    function iconClass(slug) { return iconMap[slug] || 'icon-box'; }

    // Module color palette for cards
    const moduleColors = [
        '#4C9A2A', '#2980b9', '#8e44ad', '#e67e22', '#e74c3c',
        '#16a085', '#d35400', '#2c3e50', '#27ae60', '#c0392b',
        '#7f8c8d', '#1abc9c', '#3498db', '#9b59b6',
    ];

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    function headers() {
        const h = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (state.token) h['Authorization'] = `Bearer ${state.token}`;
        if (state.orgUuid) h['X-ORG-ID'] = state.orgUuid;
        return h;
    }

    async function api(method, endpoint, body = null) {
        const t0 = performance.now();
        const opts = { method, headers: headers() };
        if (body && method !== 'GET') opts.body = JSON.stringify(body);
        try {
            const res = await fetch(`${BASE}${endpoint}`, opts);
            const ms = Math.round(performance.now() - t0);
            let data;
            try { data = await res.json(); } catch { data = null; }
            showResponse(data, res.status, ms);
            return { ok: res.ok, status: res.status, data };
        } catch (err) {
            const ms = Math.round(performance.now() - t0);
            showResponse({ error: err.message }, 0, ms);
            toast('Network error: ' + err.message, 'error');
            return { ok: false, status: 0, data: null };
        }
    }

    // ─── JSON syntax highlight ───────────────────────────────────────────
    function highlight(obj) {
        if (!obj) return '';
        const raw = JSON.stringify(obj, null, 2);
        return raw
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"([^"]+)"(?=\s*:)/g, '<span class="json-key">"$1"</span>')
            .replace(/:\s*"([^"]*)"/g, ': <span class="json-string">"$1"</span>')
            .replace(/:\s*(\d+\.?\d*)/g, ': <span class="json-number">$1</span>')
            .replace(/:\s*(true|false)/g, ': <span class="json-bool">$1</span>')
            .replace(/:\s*(null)/g, ': <span class="json-null">$1</span>');
    }

    function showResponse(data, status, ms) {
        $('#drawerBody').innerHTML = highlight(data);
        const ds = $('#drawerStatus');
        ds.textContent = status || 'ERR';
        ds.className = 'drawer-status';
        if (status >= 200 && status < 300) ds.classList.add('s2xx');
        else if (status >= 400 && status < 500) ds.classList.add('s4xx');
        else ds.classList.add('s5xx');
        $('#drawerTime').textContent = `${ms}ms`;
    }

    // ─── Response Drawer Toggle ──────────────────────────────────────────
    $('#drawerToggle').addEventListener('click', () => {
        $('#responseDrawer').classList.toggle('open');
    });

    // ─── Toast ───────────────────────────────────────────────────────────
    function toast(msg, type = 'info') {
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        const icons = { success: '✓', error: '✕', info: 'ℹ' };
        el.innerHTML = `<span style="font-size:1.1em">${icons[type] || '•'}</span> ${msg}`;
        $('#toastContainer').appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0'; el.style.transform = 'translateX(30px)';
            setTimeout(() => el.remove(), 300);
        }, 3200);
    }

    // ═══════════════════════════════════════════════════════════════════
    // LOGIN
    // ═══════════════════════════════════════════════════════════════════

    // Quick-fill from demo cards
    $$('.cred-row').forEach(row => {
        row.addEventListener('click', () => {
            $('#loginEmail').value = row.dataset.email;
            $('#loginPassword').value = 'Password@123';
        });
    });

    $('#loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = $('#loginEmail').value.trim();
        const password = $('#loginPassword').value;
        if (!email || !password) return toast('Email & password required', 'error');

        const btn = $('#btnLogin');
        btn.classList.add('loading');
        btn.innerHTML = '<span class="spinner"></span> Signing in…';

        const { ok, data } = await api('POST', '/auth/login', { email, password });

        if (ok && data?.success) {
            state.token = data.data.access_token;
            state.user = data.data.user;
            state.isSuperAdmin = data.data.user?.is_super_admin || false;
            state.organizations = data.data.user?.organizations || [];

            // Auto-select first org
            if (state.organizations.length > 0) {
                selectOrg(state.organizations[0]);
            }

            toast('Login successful!', 'success');
            enterApp();
        } else {
            toast(data?.message || 'Login failed', 'error');
            btn.classList.remove('loading');
            btn.innerHTML = '<span>Sign In</span><i class="icon-arrow-right"></i>';
        }
    });

    function enterApp() {
        $('#loginScreen').style.display = 'none';
        $('#appShell').style.display = 'flex';
        updateTopbar();
        loadModuleGrid();
    }

    // ─── Logout ──────────────────────────────────────────────────────────
    $('#btnLogout').addEventListener('click', async () => {
        await api('POST', '/auth/logout');
        Object.keys(state).forEach(k => {
            if (Array.isArray(state[k])) state[k] = [];
            else state[k] = null;
        });
        state.isSuperAdmin = false;
        $('#appShell').style.display = 'none';
        $('#loginScreen').style.display = '';
        const btn = $('#btnLogin');
        btn.classList.remove('loading');
        btn.innerHTML = '<span>Sign In</span><i class="icon-arrow-right"></i>';
        toast('Logged out', 'info');
    });

    // ═══════════════════════════════════════════════════════════════════
    // ORGANIZATION
    // ═══════════════════════════════════════════════════════════════════

    function selectOrg(org) {
        state.orgId = org.id;
        state.orgUuid = org.uuid;
        state.orgName = org.name || org.slug;
        updateTopbar();
    }

    function renderOrgDropdown() {
        const menu = $('#orgDropdownMenu');
        menu.innerHTML = '';
        state.organizations.forEach(org => {
            const btn = document.createElement('button');
            btn.className = `dd-item ${org.uuid === state.orgUuid ? 'active' : ''}`;
            btn.innerHTML = `<i class="icon-building-2"></i> ${org.name || org.slug}`;
            btn.addEventListener('click', () => {
                selectOrg(org);
                menu.classList.remove('open');
                loadModuleGrid(); // reload modules for new org
            });
            menu.appendChild(btn);
        });
    }

    // Dropdown toggles
    $('#btnOrgSwitcher').addEventListener('click', (e) => {
        e.stopPropagation();
        renderOrgDropdown();
        $('#orgDropdownMenu').classList.toggle('open');
        $('#moduleDropdownMenu').classList.remove('open');
    });
    $('#btnModuleSwitcher').addEventListener('click', (e) => {
        e.stopPropagation();
        renderModuleDropdown();
        $('#moduleDropdownMenu').classList.toggle('open');
        $('#orgDropdownMenu').classList.remove('open');
    });
    document.addEventListener('click', () => {
        $$('.topbar-dropdown-menu').forEach(m => m.classList.remove('open'));
    });

    // ═══════════════════════════════════════════════════════════════════
    // TOPBAR
    // ═══════════════════════════════════════════════════════════════════

    function updateTopbar() {
        $('#currentOrgName').textContent = state.orgName || '—';
        const u = state.user;
        if (u) {
            $('#userName').textContent = u.name || u.email;
            $('#userRole').textContent = state.isSuperAdmin ? 'Super Admin' : 'User';
            const initials = (u.name || 'U').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
            $('#userAvatar').textContent = initials;
        }
    }

    // ─── Back to Module Grid ─────────────────────────────────────────────
    $('#btnBackToModules').addEventListener('click', () => {
        showModuleGrid();
    });

    function showModuleGrid() {
        state.currentModule = null;
        state.currentMenu = null;
        $('#sidebar').style.display = 'none';
        $('#btnHamburger').style.display = 'none';
        $('#moduleSwitcher').style.display = 'none';
        showView('viewModuleGrid');
        loadModuleGrid();
    }

    function showView(id) {
        $$('.view').forEach(v => v.classList.remove('active'));
        const el = $(`#${id}`);
        if (el) { el.classList.remove('active'); void el.offsetHeight; el.classList.add('active'); }
    }

    // ═══════════════════════════════════════════════════════════════════
    // MODULE GRID
    // ═══════════════════════════════════════════════════════════════════

    async function loadModuleGrid() {
        // Fetch modules from API
        const { ok, data } = await api('GET', '/modules');
        if (!ok || !data?.data) return;

        state.modules = data.data;
        renderModuleGrid();
    }

    function renderModuleGrid() {
        const grid = $('#moduleGrid');
        grid.innerHTML = '';

        // Add GAC card first for admin/super-admin
        const gacCard = createModuleCard({
            id: '__gac__',
            name: 'Global Access Control',
            slug: 'GAC',
            icon: 'shield-check',
            is_core: true,
        }, true);
        grid.appendChild(gacCard);

        // Render each module
        state.modules.forEach((mod, i) => {
            const card = createModuleCard(mod, false, i);
            grid.appendChild(card);
        });
    }

    function createModuleCard(mod, isGAC = false, index = 0) {
        const card = document.createElement('div');
        card.className = 'module-card';
        const color = isGAC ? '#e74c3c' : moduleColors[index % moduleColors.length];
        card.innerHTML = `
            <div class="module-card-badge${isGAC ? ' admin-badge' : ''}" style="background:${color}">
                ${mod.name}
            </div>
            <div class="module-card-body">
                <div class="module-card-icon"><i class="${iconClass(mod.icon || 'layers')}"></i></div>
                <div class="module-card-slug">⚡ ${mod.slug?.toUpperCase() || '—'}</div>
            </div>
        `;
        card.addEventListener('click', () => {
            if (isGAC) {
                enterGAC();
            } else {
                enterModule(mod);
            }
        });
        return card;
    }

    // ═══════════════════════════════════════════════════════════════════
    // GAC — Global Access Control (Admin Only)
    // ═══════════════════════════════════════════════════════════════════

    function enterGAC() {
        state.currentModule = {
            id: '__gac__',
            name: 'Global Access Control',
            slug: 'gac',
            icon: 'shield-check',
        };

        // Show sidebar with GAC menus
        const sidebarData = [
            { name: 'Users', slug: 'gac-users', icon: 'users', action: renderGACUsers },
            { name: 'Roles', slug: 'gac-roles', icon: 'shield', action: renderGACRoles },
            { name: 'Permissions', slug: 'gac-permissions', icon: 'settings', action: renderGACPermissions },
            { name: 'Modules', slug: 'gac-modules', icon: 'package', action: renderGACModules },
            { name: 'Menus', slug: 'gac-menus', icon: 'layers', action: renderGACMenus },
            { name: 'Submenus', slug: 'gac-submenus', icon: 'layers', action: renderGACSubmenus },
        ];

        renderSidebarManual('Global Access Control', 'GAC', 'shield-check', sidebarData);
        $('#moduleSwitcher').style.display = '';
        $('#btnHamburger').style.display = '';
        showView('viewModuleContent');

        // Auto-select first item
        sidebarData[0].action();
        activateSidebarItem(sidebarData[0].slug);
    }

    // ─── GAC: Users ──────────────────────────────────────────────────────
    async function renderGACUsers() {
        setContentHeader('User Management', 'Create, view, update, and delete users. Assign roles within the current organization.');
        const body = $('#contentBody');
        body.innerHTML = `
            <div class="card">
                <div class="card-title"><span class="method-badge get">GET</span> /api/v1/users</div>
                <div class="btn-group">
                    <button class="btn btn-secondary" id="gacFetchUsers"><i class="icon-refresh-cw"></i> Fetch Users</button>
                </div>
                <div id="gacUsersTable"></div>
            </div>
            <div class="card">
                <div class="card-title"><span class="method-badge post">POST</span> /api/v1/users — Create User</div>
                <form id="gacCreateUserForm" class="form-grid form-grid-3">
                    <div class="field"><label>First Name</label><input type="text" id="gcu_fn" placeholder="John"></div>
                    <div class="field"><label>Last Name</label><input type="text" id="gcu_ln" placeholder="Doe"></div>
                    <div class="field"><label>Email</label><input type="email" id="gcu_email" placeholder="john@example.com"></div>
                    <div class="field"><label>Password</label><input type="password" id="gcu_pw" placeholder="min 8 chars"></div>
                    <div class="field"><label>Confirm Password</label><input type="password" id="gcu_pwc" placeholder="confirm"></div>
                    <div class="field"><label>Phone</label><input type="text" id="gcu_phone" placeholder="+88017xxxxxxxx"></div>
                    <div class="span-full"><button type="submit" class="btn btn-primary"><i class="icon-plus"></i> Create User</button></div>
                </form>
            </div>
            <div class="card">
                <div class="card-title">Single User Operations</div>
                <div class="form-inline">
                    <div class="field"><label>User UUID</label><input type="text" id="gcu_uuid" placeholder="Paste UUID"></div>
                    <button class="btn btn-secondary" id="gacShowUser"><i class="icon-eye"></i> Show</button>
                    <button class="btn btn-danger" id="gacDeleteUser"><i class="icon-trash-2"></i> Delete</button>
                </div>
                <div class="mt-1">
                    <div class="card-title" style="font-size:.88rem">Sync Roles to User</div>
                    <div class="form-inline">
                        <div class="field" style="flex:2"><label>Role UUIDs (comma-separated)</label><input type="text" id="gcu_roles" placeholder="uuid1, uuid2"></div>
                        <button class="btn btn-primary" id="gacSyncUserRoles"><i class="icon-link"></i> Sync</button>
                    </div>
                </div>
            </div>
        `;

        // Bind events
        $('#gacFetchUsers').addEventListener('click', async () => {
            const { ok, data } = await api('GET', '/users');
            if (ok && data?.data) renderTable('#gacUsersTable', data.data,
                ['uuid', 'name', 'email', 'status'],
                { status: v => `<span class="badge ${v === 'active' ? 'badge-green' : 'badge-red'}">${v}</span>` }
            );
        });
        $('#gacCreateUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const { ok } = await api('POST', '/users', {
                first_name: $('#gcu_fn').value.trim(),
                last_name: $('#gcu_ln').value.trim(),
                email: $('#gcu_email').value.trim(),
                password: $('#gcu_pw').value,
                password_confirmation: $('#gcu_pwc').value,
                phone: $('#gcu_phone').value.trim() || undefined,
            });
            if (ok) { toast('User created!', 'success'); $('#gacCreateUserForm').reset(); }
        });
        $('#gacShowUser').addEventListener('click', async () => {
            const u = $('#gcu_uuid').value.trim();
            if (!u) return toast('Enter UUID', 'error');
            await api('GET', `/users/${u}`);
        });
        $('#gacDeleteUser').addEventListener('click', async () => {
            const u = $('#gcu_uuid').value.trim();
            if (!u) return toast('Enter UUID', 'error');
            if (!confirm('Remove this user from the organization?')) return;
            const { ok } = await api('DELETE', `/users/${u}`);
            if (ok) toast('User removed', 'success');
        });
        $('#gacSyncUserRoles').addEventListener('click', async () => {
            const u = $('#gcu_uuid').value.trim();
            if (!u) return toast('Enter UUID first', 'error');
            const roles = $('#gcu_roles').value.split(',').map(s => s.trim()).filter(Boolean);
            if (!roles.length) return toast('Enter role UUIDs', 'error');
            const { ok } = await api('PUT', `/users/${u}/roles`, { roles });
            if (ok) toast('Roles synced!', 'success');
        });

        // Auto-fetch
        $('#gacFetchUsers').click();
    }

    // ─── GAC: Roles ──────────────────────────────────────────────────────
    async function renderGACRoles() {
        setContentHeader('Role Management', 'Define organizational roles, assign modules, and sync permissions.');
        const body = $('#contentBody');
        body.innerHTML = `
            <div class="card">
                <div class="card-title"><span class="method-badge get">GET</span> /api/v1/roles</div>
                <div class="btn-group">
                    <button class="btn btn-secondary" id="gacFetchRoles"><i class="icon-refresh-cw"></i> Fetch Roles</button>
                </div>
                <div id="gacRolesTable"></div>
            </div>
            <div class="card">
                <div class="card-title"><span class="method-badge post">POST</span> /api/v1/roles — Create Role</div>
                <form id="gacCreateRoleForm" class="form-grid form-grid-3">
                    <div class="field"><label>Name</label><input type="text" id="gcr_name" placeholder="Financial Auditor"></div>
                    <div class="field"><label>Slug</label><input type="text" id="gcr_slug" placeholder="finance-auditor"></div>
                    <div class="field"><label>Description</label><input type="text" id="gcr_desc" placeholder="Read-only access"></div>
                    <div class="field"><label>Inheritable?</label><select id="gcr_inh"><option value="false">No</option><option value="true">Yes</option></select></div>
                    <div class="field"><label>Active?</label><select id="gcr_act"><option value="true">Yes</option><option value="false">No</option></select></div>
                    <div class="field"><label>Parent Role UUID</label><input type="text" id="gcr_parent" placeholder="(optional)"></div>
                    <div class="span-full"><button type="submit" class="btn btn-primary"><i class="icon-plus"></i> Create Role</button></div>
                </form>
            </div>
            <div class="card">
                <div class="card-title">Single Role Operations</div>
                <div class="form-inline">
                    <div class="field"><label>Role UUID</label><input type="text" id="gcr_uuid" placeholder="Paste UUID"></div>
                    <button class="btn btn-secondary" id="gacShowRole"><i class="icon-eye"></i> Show</button>
                    <button class="btn btn-danger" id="gacDeleteRole"><i class="icon-trash-2"></i> Delete</button>
                </div>
                <div class="mt-1">
                    <div class="card-title" style="font-size:.88rem">Sync Modules to Role</div>
                    <div class="form-inline">
                        <div class="field" style="flex:2"><label>Module UUIDs (comma-separated)</label><input type="text" id="gcr_modules" placeholder="uuid1, uuid2"></div>
                        <button class="btn btn-primary" id="gacSyncRoleModules"><i class="icon-link"></i> Sync Modules</button>
                    </div>
                </div>
                <div class="mt-1">
                    <div class="card-title" style="font-size:.88rem">Sync Permissions to Role</div>
                    <div class="card-desc">JSON array: <code>[{"id":"perm-uuid","is_granted":true}]</code></div>
                    <div class="form-inline">
                        <div class="field" style="flex:2"><label>Permissions JSON</label><textarea id="gcr_perms" rows="3" placeholder='[{"id":"uuid","is_granted":true}]'></textarea></div>
                        <button class="btn btn-primary" id="gacSyncRolePerms"><i class="icon-link"></i> Sync Perms</button>
                    </div>
                </div>
                <div class="mt-1">
                    <div class="card-title" style="font-size:.88rem">Sync Sidebar Menus to Role</div>
                    <div class="form-inline">
                        <div class="field" style="flex:1"><label>Menu UUIDs</label><input type="text" id="gcr_menus" placeholder="uuid1, uuid2"></div>
                        <div class="field" style="flex:1"><label>Submenu UUIDs</label><input type="text" id="gcr_submenus" placeholder="uuid1, uuid2"></div>
                        <button class="btn btn-primary" id="gacSyncRoleMenus"><i class="icon-link"></i> Sync Sidebar</button>
                    </div>
                </div>
            </div>
        `;

        $('#gacFetchRoles').addEventListener('click', async () => {
            const { ok, data } = await api('GET', '/roles');
            if (ok && data?.data) renderTable('#gacRolesTable', data.data,
                ['uuid', 'name', 'slug', 'is_active', 'is_inheritable'],
                {
                    is_active: v => `<span class="badge ${v ? 'badge-green' : 'badge-red'}">${v ? 'Yes' : 'No'}</span>`,
                    is_inheritable: v => `<span class="badge ${v ? 'badge-blue' : 'badge-red'}">${v ? 'Yes' : 'No'}</span>`,
                }
            );
        });
        $('#gacCreateRoleForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = {
                name: $('#gcr_name').value.trim(),
                slug: $('#gcr_slug').value.trim(),
                description: $('#gcr_desc').value.trim(),
                is_inheritable: $('#gcr_inh').value === 'true',
                is_active: $('#gcr_act').value === 'true',
            };
            const parent = $('#gcr_parent').value.trim();
            if (parent) body.parent_role_id = parent;
            const { ok } = await api('POST', '/roles', body);
            if (ok) { toast('Role created!', 'success'); $('#gacCreateRoleForm').reset(); }
        });
        $('#gacShowRole').addEventListener('click', async () => {
            const u = $('#gcr_uuid').value.trim();
            if (!u) return toast('Enter UUID', 'error');
            await api('GET', `/roles/${u}`);
        });
        $('#gacDeleteRole').addEventListener('click', async () => {
            const u = $('#gcr_uuid').value.trim();
            if (!u) return toast('Enter UUID', 'error');
            if (!confirm('Delete this role permanently?')) return;
            const { ok } = await api('DELETE', `/roles/${u}`);
            if (ok) toast('Role deleted', 'success');
        });
        $('#gacSyncRoleModules').addEventListener('click', async () => {
            const u = $('#gcr_uuid').value.trim();
            if (!u) return toast('Enter Role UUID first', 'error');
            const modules = $('#gcr_modules').value.split(',').map(s => s.trim()).filter(Boolean);
            if (!modules.length) return toast('Enter module UUIDs', 'error');
            const { ok } = await api('PUT', `/roles/${u}/modules`, { modules });
            if (ok) toast('Modules synced!', 'success');
        });
        $('#gacSyncRolePerms').addEventListener('click', async () => {
            const u = $('#gcr_uuid').value.trim();
            if (!u) return toast('Enter Role UUID first', 'error');
            let permissions;
            try { permissions = JSON.parse($('#gcr_perms').value); }
            catch { return toast('Invalid JSON', 'error'); }
            const { ok } = await api('PUT', `/roles/${u}/permissions`, { permissions });
            if (ok) toast('Permissions synced!', 'success');
        });
        $('#gacSyncRoleMenus').addEventListener('click', async () => {
            const u = $('#gcr_uuid').value.trim();
            if (!u) return toast('Enter Role UUID first', 'error');
            const menus = $('#gcr_menus').value.split(',').map(s => s.trim()).filter(Boolean);
            const submenus = $('#gcr_submenus').value.split(',').map(s => s.trim()).filter(Boolean);
            const { ok } = await api('PUT', `/roles/${u}/menus`, { menus, submenus });
            if (ok) toast('Sidebar menus synced!', 'success');
        });

        $('#gacFetchRoles').click();
    }

    // ─── GAC: Permissions ────────────────────────────────────────────────
    async function renderGACPermissions() {
        setContentHeader('Permissions', 'All available permissions grouped by module.');
        const body = $('#contentBody');
        body.innerHTML = `
            <div class="card">
                <div class="card-title"><span class="method-badge get">GET</span> /api/v1/permissions</div>
                <div class="btn-group">
                    <button class="btn btn-secondary" id="gacFetchPerms"><i class="icon-refresh-cw"></i> Fetch Permissions</button>
                </div>
                <div id="gacPermsWrap"></div>
            </div>
            <div class="card">
                <div class="card-title"><span class="method-badge post">POST</span> /api/v1/permissions — Create Permission</div>
                <form id="gacCreatePermissionForm" class="form-grid form-grid-3">
                    <div class="field"><label>Name</label><input type="text" id="gcp_name" placeholder="View Users"></div>
                    <div class="field"><label>Slug</label><input type="text" id="gcp_slug" placeholder="view-users"></div>
                    <div class="field"><label>Module UUID</label><input type="text" id="gcp_module" placeholder="module-uuid"></div>
                    <div class="field"><label>Description</label><input type="text" id="gcp_desc" placeholder="Allows viewing users"></div>
                    <div class="span-full"><button type="submit" class="btn btn-primary"><i class="icon-plus"></i> Create Permission</button></div>
                </form>
            </div>
            <div class="card">
                <div class="card-title">Single Permission Operations</div>
                <div class="form-inline">
                    <div class="field"><label>Permission UUID</label><input type="text" id="gcp_uuid" placeholder="Paste UUID"></div>
                    <button class="btn btn-secondary" id="gacShowPermission"><i class="icon-eye"></i> Show</button>
                    <button class="btn btn-danger" id="gacDeletePermission"><i class="icon-trash-2"></i> Delete</button>
                </div>
            </div>
        `;

        $('#gacFetchPerms').addEventListener('click', async () => {
            const { ok, data } = await api('GET', '/permissions');
            if (!ok || !data?.data) return;

            const wrap = $('#gacPermsWrap');
            const grouped = {};
            data.data.forEach(p => {
                const mod = p.module?.name || p.module_name || 'General';
                if (!grouped[mod]) grouped[mod] = [];
                grouped[mod].push(p);
            });

            let html = '';
            Object.entries(grouped).forEach(([mod, items]) => {
                html += `<div class="perm-group">
                    <div class="perm-group-title"><span class="badge badge-blue">${mod}</span> (${items.length} permissions)</div>`;
                items.forEach(p => {
                    html += `<div class="perm-item">
                        <span class="uuid-cell" onclick="navigator.clipboard.writeText('${p.uuid}')" title="Click to copy UUID">${p.uuid}</span>
                        <span>${p.name}</span>
                        <code>${p.slug}</code>
                    </div>`;
                });
                html += '</div>';
            });
            wrap.innerHTML = html || '<div class="empty-state"><i class="icon-shield-off"></i>No permissions found.</div>';
        });

        $('#gacCreatePermissionForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = {
                name: $('#gcp_name').value.trim(),
                slug: $('#gcp_slug').value.trim(),
                module_id: $('#gcp_module').value.trim(),
                description: $('#gcp_desc').value.trim(),
            };
            const { ok } = await api('POST', '/permissions', body);
            if (ok) { toast('Permission created!', 'success'); $('#gacCreatePermissionForm').reset(); $('#gacFetchPerms').click(); }
        });

        $('#gacShowPermission').addEventListener('click', async () => {
            const u = $('#gcp_uuid').value.trim();
            if (!u) return toast('Enter UUID', 'error');
            await api('GET', `/permissions/${u}`);
        });

        $('#gacDeletePermission').addEventListener('click', async () => {
            const u = $('#gcp_uuid').value.trim();
            if (!u) return toast('Enter UUID', 'error');
            if (!confirm('Delete this permission permanently?')) return;
            const { ok } = await api('DELETE', `/permissions/${u}`);
            if (ok) { toast('Permission deleted', 'success'); $('#gacFetchPerms').click(); }
        });

        $('#gacFetchPerms').click();
    }

    // ─── GAC: Modules ────────────────────────────────────────────────────
    async function renderGACModules() {
        setContentHeader('Modules', 'Active modules for this organization.');
        const body = $('#contentBody');
        body.innerHTML = `
            <div class="card">
                <div class="card-title"><span class="method-badge get">GET</span> /api/v1/modules</div>
                <div class="btn-group">
                    <button class="btn btn-secondary" id="gacFetchModules"><i class="icon-refresh-cw"></i> Fetch Modules</button>
                </div>
                <div id="gacModulesTable"></div>
            </div>
        `;

        $('#gacFetchModules').addEventListener('click', async () => {
            const { ok, data } = await api('GET', '/modules');
            if (ok && data?.data) renderTable('#gacModulesTable', data.data,
                ['id', 'name', 'slug', 'icon', 'is_core', 'sort_order'],
                {
                    id: v => `<span class="uuid-cell" onclick="navigator.clipboard.writeText('${v}')" title="Copy">${v}</span>`,
                    is_core: v => `<span class="badge ${v ? 'badge-yellow' : 'badge-blue'}">${v ? 'Core' : 'Add-on'}</span>`,
                }
            );
        });

        $('#gacFetchModules').click();
    }

    // ─── GAC: Menus ────────────────────────────────────────────────────
    async function renderGACMenus() {
        setContentHeader('Menus', 'Top-level sidebar menus across modules.');
        const body = $('#contentBody');
        body.innerHTML = `
            <div class="card">
                <div class="card-title"><span class="method-badge get">GET</span> /api/v1/menus</div>
                <div class="btn-group">
                    <button class="btn btn-secondary" id="gacFetchMenus"><i class="icon-refresh-cw"></i> Fetch Menus</button>
                </div>
                <div id="gacMenusTable"></div>
            </div>
        `;

        $('#gacFetchMenus').addEventListener('click', async () => {
            const { ok, data } = await api('GET', '/menus');
            if (ok && data?.data) renderTable('#gacMenusTable', data.data,
                ['id', 'module_name', 'name', 'slug', 'is_active'],
                {
                    id: v => `<span class="uuid-cell" onclick="navigator.clipboard.writeText('${v}')" title="Copy">${v}</span>`,
                    module_name: (_, item) => item.module?.name || '—',
                    is_active: v => `<span class="badge ${v ? 'badge-green' : 'badge-red'}">${v ? 'Yes' : 'No'}</span>`,
                }
            );
        });

        $('#gacFetchMenus').click();
    }

    // ─── GAC: Submenus ────────────────────────────────────────────────────
    async function renderGACSubmenus() {
        setContentHeader('Submenus', 'Nested sidebar submenus tied to specific permissions.');
        const body = $('#contentBody');
        body.innerHTML = `
            <div class="card">
                <div class="card-title"><span class="method-badge get">GET</span> /api/v1/submenus</div>
                <div class="btn-group">
                    <button class="btn btn-secondary" id="gacFetchSubmenus"><i class="icon-refresh-cw"></i> Fetch Submenus</button>
                </div>
                <div id="gacSubmenusTable"></div>
            </div>
        `;

        $('#gacFetchSubmenus').addEventListener('click', async () => {
            const { ok, data } = await api('GET', '/submenus');
            if (ok && data?.data) renderTable('#gacSubmenusTable', data.data,
                ['id', 'menu_name', 'name', 'slug', 'permission'],
                {
                    id: v => `<span class="uuid-cell" onclick="navigator.clipboard.writeText('${v}')" title="Copy">${v}</span>`,
                    menu_name: (_, item) => item.menu?.name || '—',
                    permission: (_, item) => item.permission ? `<code>${item.permission.slug}</code>` : '—',
                }
            );
        });

        $('#gacFetchSubmenus').click();
    }

    // ═══════════════════════════════════════════════════════════════════
    // ENTER MODULE (show sidebar from API)
    // ═══════════════════════════════════════════════════════════════════

    async function enterModule(mod) {
        state.currentModule = mod;

        // Fetch sidebar for this user/org
        const { ok, data } = await api('GET', '/sidebar');
        if (!ok || !data?.data) {
            toast('Failed to load sidebar', 'error');
            return;
        }

        state.sidebarData = data.data;

        // Find sidebar entry for this module
        const moduleSidebar = state.sidebarData.find(s =>
            s.module?.slug === mod.slug || s.module?.id === mod.id
        );

        if (!moduleSidebar || !moduleSidebar.menus?.length) {
            // No sidebar entries — show empty state
            renderSidebarManual(mod.name, mod.slug, mod.icon, []);
            $('#moduleSwitcher').style.display = '';
            $('#btnHamburger').style.display = '';
            showView('viewModuleContent');
            setContentHeader(mod.name, `No menu items available for this module.`);
            $('#contentBody').innerHTML = `<div class="empty-state"><i class="icon-layout-grid"></i>No sidebar items configured for this module, or you don't have permission to access any.</div>`;
            return;
        }

        // Build sidebar from API data
        renderSidebarFromAPI(moduleSidebar);
        $('#moduleSwitcher').style.display = '';
        $('#btnHamburger').style.display = '';
        showView('viewModuleContent');

        // Auto-select first menu
        const firstMenu = moduleSidebar.menus[0];
        if (firstMenu) {
            renderMenuContent(firstMenu, mod);
            activateSidebarItem(firstMenu.slug);
        }
    }

    function renderSidebarFromAPI(moduleSidebar) {
        const mod = moduleSidebar.module;
        $('#sidebarModuleName').textContent = mod.name;
        $('#sidebarModuleSlug').textContent = mod.slug;
        $('#sidebarModuleIcon').innerHTML = `<i class="${iconClass(mod.icon)}"></i>`;

        const nav = $('#sidebarNav');
        nav.innerHTML = '';

        moduleSidebar.menus.forEach(menu => {
            const group = document.createElement('div');
            group.className = 'sidebar-menu-group';

            const hasChildren = menu.submenus && menu.submenus.length > 0;

            const btn = document.createElement('button');
            btn.className = 'sidebar-menu-btn';
            btn.dataset.slug = menu.slug;
            btn.innerHTML = `
                <i class="${iconClass(menu.icon)}"></i>
                <span>${menu.name}</span>
                ${hasChildren ? '<i class="icon-chevron-right chevron"></i>' : ''}
            `;

            if (hasChildren) {
                const submenu = document.createElement('div');
                submenu.className = 'sidebar-submenu';
                menu.submenus.forEach(sub => {
                    const subBtn = document.createElement('button');
                    subBtn.className = 'sidebar-sub-btn';
                    subBtn.dataset.slug = sub.slug;
                    subBtn.textContent = sub.name;
                    subBtn.addEventListener('click', () => {
                        $$('.sidebar-sub-btn').forEach(b => b.classList.remove('active'));
                        subBtn.classList.add('active');
                        renderSubMenuContent(sub, menu, moduleSidebar.module);
                    });
                    submenu.appendChild(subBtn);
                });
                btn.addEventListener('click', () => {
                    btn.classList.toggle('expanded');
                    submenu.classList.toggle('open');
                    if (!hasChildren) {
                        renderMenuContent(menu, moduleSidebar.module);
                    }
                });
                group.appendChild(btn);
                group.appendChild(submenu);
            } else {
                btn.addEventListener('click', () => {
                    activateSidebarItem(menu.slug);
                    renderMenuContent(menu, moduleSidebar.module);
                });
                group.appendChild(btn);
            }

            nav.appendChild(group);
        });

        $('#sidebar').style.display = '';
    }

    function renderMenuContent(menu, mod) {
        setContentHeader(menu.name, `Module: ${mod.name} → ${menu.name}`);
        $('#contentBody').innerHTML = `
            <div class="card">
                <div class="card-title"><i class="${iconClass(menu.icon)}"></i> ${menu.name}</div>
                <div class="card-desc">
                    <strong>Route:</strong> <code>${menu.route || '—'}</code><br>
                    <strong>URL:</strong> <code>${menu.url || '—'}</code><br>
                    <strong>Module:</strong> ${mod.name} (<code>${mod.slug}</code>)
                </div>
                <p style="color:var(--text-dim);font-size:.84rem;margin-top:8px">
                    This menu item is visible because your role has the required module access and permissions.
                    In a real frontend application, clicking this would navigate to <code>${menu.url || menu.route}</code>.
                </p>
            </div>
        `;
    }

    function renderSubMenuContent(sub, menu, mod) {
        setContentHeader(sub.name, `${mod.name} → ${menu.name} → ${sub.name}`);
        $('#contentBody').innerHTML = `
            <div class="card">
                <div class="card-title"><i class="${iconClass(sub.icon || menu.icon)}"></i> ${sub.name}</div>
                <div class="card-desc">
                    <strong>Route:</strong> <code>${sub.route || '—'}</code><br>
                    <strong>URL:</strong> <code>${sub.url || '—'}</code><br>
                    <strong>Parent Menu:</strong> ${menu.name}<br>
                    <strong>Module:</strong> ${mod.name} (<code>${mod.slug}</code>)
                </div>
                <p style="color:var(--text-dim);font-size:.84rem;margin-top:8px">
                    This submenu item is permission-controlled. Your role has been granted access to this item.
                </p>
            </div>
        `;
    }

    // ─── Manual Sidebar (for GAC) ────────────────────────────────────────
    function renderSidebarManual(name, slug, icon, items) {
        $('#sidebarModuleName').textContent = name;
        $('#sidebarModuleSlug').textContent = slug;
        $('#sidebarModuleIcon').innerHTML = `<i class="${iconClass(icon)}"></i>`;

        const nav = $('#sidebarNav');
        nav.innerHTML = '';

        items.forEach(item => {
            const btn = document.createElement('button');
            btn.className = 'sidebar-menu-btn';
            btn.dataset.slug = item.slug;
            btn.innerHTML = `<i class="${iconClass(item.icon)}"></i><span>${item.name}</span>`;
            btn.addEventListener('click', () => {
                activateSidebarItem(item.slug);
                item.action();
            });
            nav.appendChild(btn);
        });

        $('#sidebar').style.display = '';
    }

    function activateSidebarItem(slug) {
        $$('.sidebar-menu-btn').forEach(b => b.classList.remove('active'));
        const active = $(`.sidebar-menu-btn[data-slug="${slug}"]`);
        if (active) active.classList.add('active');
    }

    // ─── Module Dropdown ─────────────────────────────────────────────────
    function renderModuleDropdown() {
        const menu = $('#moduleDropdownMenu');
        menu.innerHTML = '';

        // GAC entry
        const gacBtn = document.createElement('button');
        gacBtn.className = `dd-item ${state.currentModule?.id === '__gac__' ? 'active' : ''}`;
        gacBtn.innerHTML = `<i class="icon-shield-check"></i> GAC (Access Control)`;
        gacBtn.addEventListener('click', () => { menu.classList.remove('open'); enterGAC(); });
        menu.appendChild(gacBtn);

        // Modules
        state.modules.forEach(mod => {
            const btn = document.createElement('button');
            btn.className = `dd-item ${state.currentModule?.id === mod.id ? 'active' : ''}`;
            btn.innerHTML = `<i class="${iconClass(mod.icon)}"></i> ${mod.name}`;
            btn.addEventListener('click', () => { menu.classList.remove('open'); enterModule(mod); });
            menu.appendChild(btn);
        });
    }

    // ─── Hamburger (mobile) ──────────────────────────────────────────────
    $('#btnHamburger').addEventListener('click', () => {
        $('#sidebar').classList.toggle('hidden');
    });

    // ═══════════════════════════════════════════════════════════════════
    // UTILITY RENDERERS
    // ═══════════════════════════════════════════════════════════════════

    function setContentHeader(title, desc) {
        $('#contentTitle').textContent = title;
        $('#contentDesc').textContent = desc;
    }

    function renderTable(selector, items, columns, formatters = {}) {
        const wrap = $(selector);
        if (!items.length) {
            wrap.innerHTML = '<div class="empty-state"><i class="icon-inbox"></i>No records found.</div>';
            return;
        }
        let html = '<div class="table-wrap"><table class="data-table"><thead><tr>';
        columns.forEach(c => { html += `<th>${c.replace(/_/g, ' ')}</th>`; });
        html += '</tr></thead><tbody>';
        items.forEach(item => {
            html += '<tr>';
            columns.forEach(c => {
                let val = item[c] ?? '—';
                if (formatters[c]) val = formatters[c](val, item);
                else if (c === 'uuid' || c === 'id') val = `<span class="uuid-cell" onclick="navigator.clipboard.writeText('${val}')" title="Click to copy">${val}</span>`;
                html += `<td>${val}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        wrap.innerHTML = html;
    }

    // ═══════════════════════════════════════════════════════════════════
    // SPINNER CSS (inline)
    // ═══════════════════════════════════════════════════════════════════
    const style = document.createElement('style');
    style.textContent = `
        .spinner {
            display: inline-block; width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);

    // ─── Init ────────────────────────────────────────────────────────────
    console.log('%c🔒 ERP SaaS RBAC Demo Ready', 'color:#4C9A2A;font-weight:bold;font-size:14px');

})();
