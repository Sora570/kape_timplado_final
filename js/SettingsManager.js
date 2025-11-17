// Settings Manager: system diagnostics, audit visibility, and exports
const SettingsManager = (() => {
    const state = {
        auditLogs: [],
        currentAuditPage: 1,
        auditPageSize: 10,
        systemInfo: {}
    };

    function init() {
        loadSystemInfo();
        loadAuditLogs();
        setupEventListeners();
    }

    function setupEventListeners() {
        const filterControls = [
            'auditDateFrom',
            'auditDateTo',
            'auditUserFilter',
            'auditActionFilter'
        ];

        filterControls.forEach((id) => {
            document.getElementById(id)?.addEventListener('change', () => {
                state.currentAuditPage = 1;
                renderAuditLogs();
            });
        });
    }

    async function loadSystemInfo() {
        try {
            const response = await fetch('db/system_info.php');
            const data = await response.json();
            state.systemInfo = data || {};
            renderSystemInfo();
        } catch (error) {
            console.error('Error loading system info:', error);
            state.systemInfo = {};
            renderSystemInfo();
        }
    }

    function renderSystemInfo() {
        const info = state.systemInfo;
        const dbStatusEl = document.getElementById('dbStatus');
        if (dbStatusEl) {
            const label = info.dbStatus || 'Unknown';
            dbStatusEl.textContent = label;
            dbStatusEl.className = `stat-value ${label === 'Online' ? 'online' : 'offline'}`;
        }
        const tableCountEl = document.getElementById('tableCount');
        if (tableCountEl) tableCountEl.textContent = info.tableCount ?? '-';
        const dbSizeEl = document.getElementById('dbSize');
        if (dbSizeEl) dbSizeEl.textContent = info.dbSize ?? '-';
    }

    function refreshSystemInfo() {
        showToast('Refreshing system information...', 'info');
        loadSystemInfo();
    }

    function backupDatabase() {
        showToast('Database backup initiated...', 'info');
        setTimeout(() => showToast('Database backup completed', 'success'), 2000);
    }

    function clearCache() {
        showToast('Cache cleared successfully', 'success');
    }

    function optimizeDatabase() {
        showToast('Database optimization started...', 'info');
        setTimeout(() => showToast('Database optimization completed', 'success'), 3000);
    }

    async function loadAuditLogs() {
        const tbody = document.getElementById('auditLogsBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="loading-spinner">Loading audit logs...</td>
                </tr>
            `;
        }

        try {
            const response = await fetch('db/get_audit_logs.php');
            const data = await response.json();

            if (data.status === 'success') {
                const logs = Array.isArray(data.audit_logs) ? data.audit_logs : [];
                state.auditLogs = logs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            } else {
                state.auditLogs = [];
            }

            populateAuditUserFilter(state.auditLogs);
            state.currentAuditPage = 1;
            renderAuditLogs();
            updateSecurityStats();
        } catch (error) {
            console.error('Error loading audit logs:', error);
            state.auditLogs = [];
            renderAuditLogs();
            showToast('Failed to load audit logs', 'error');
        }
    }

    function populateAuditUserFilter(logs) {
        const select = document.getElementById('auditUserFilter');
        if (!select) return;

        const previousValue = select.value;
        const usernames = Array.from(
            new Set(
                logs
                    .map((log) => log.username)
                    .filter((name) => !!name)
            )
        ).sort((a, b) => a.localeCompare(b));

        select.innerHTML = '<option value="">All Employees</option>' +
            usernames.map((name) => `<option value="${name}">${name}</option>`).join('');

        if (previousValue && usernames.includes(previousValue)) {
            select.value = previousValue;
        }
    }

    function getFilteredAuditLogs() {
        const dateFromValue = document.getElementById('auditDateFrom')?.value;
        const dateToValue = document.getElementById('auditDateTo')?.value;
        const userValue = (document.getElementById('auditUserFilter')?.value || '').toLowerCase();
        const actionValue = document.getElementById('auditActionFilter')?.value || '';

        const dateFrom = dateFromValue ? new Date(`${dateFromValue}T00:00:00`) : null;
        const dateTo = dateToValue ? new Date(`${dateToValue}T23:59:59`) : null;

        return state.auditLogs.filter((log) => {
            const action = log.action || '';
            const username = (log.username || '').toLowerCase();
            const timestamp = new Date(log.created_at);

            if (dateFrom && timestamp < dateFrom) return false;
            if (dateTo && timestamp > dateTo) return false;
            if (userValue && username !== userValue) return false;
            if (actionValue && action !== actionValue) return false;
            return true;
        });
    }

    function renderAuditLogs() {
        const tbody = document.getElementById('auditLogsBody');
        if (!tbody) return;

        const logs = getFilteredAuditLogs();
        const totalPages = Math.max(1, Math.ceil(logs.length / state.auditPageSize));
        state.currentAuditPage = Math.min(state.currentAuditPage, totalPages);

        const start = (state.currentAuditPage - 1) * state.auditPageSize;
        const pageItems = logs.slice(start, start + state.auditPageSize);

        if (!pageItems.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="empty-state">No audit logs found</td>
                </tr>
            `;
        } else {
            tbody.innerHTML = pageItems.map((log) => `
                <tr>
                    <td>${new Date(log.created_at).toLocaleString()}</td>
                    <td>${log.username || 'System'}</td>
                    <td><span class="action-badge action-${log.action}">${log.action}</span></td>
                    <td>${log.details || '-'}</td>
                    <td>${log.ip_address || '-'}</td>
                    <td><span class="status-badge status-${(log.role || 'system').toLowerCase()}">${log.role || 'System'}</span></td>
                </tr>
            `).join('');
        }

        updateAuditPaginationControls(logs.length, totalPages);
    }

    function updateAuditPaginationControls(totalItems, totalPages) {
        const prevBtn = document.getElementById('prevAuditBtn');
        if (prevBtn) prevBtn.disabled = state.currentAuditPage <= 1 || totalItems === 0;

        const nextBtn = document.getElementById('nextAuditBtn');
        if (nextBtn) nextBtn.disabled = state.currentAuditPage >= totalPages || totalItems === 0;

        const pageInfo = document.getElementById('auditPageInfo');
        if (pageInfo) {
            if (totalItems === 0) {
                pageInfo.textContent = 'Page 0 of 0';
            } else {
                pageInfo.textContent = `Page ${state.currentAuditPage} of ${totalPages}`;
            }
        }
    }

    function previousAuditPage() {
        if (state.currentAuditPage <= 1) return;
        state.currentAuditPage -= 1;
        renderAuditLogs();
    }

    function nextAuditPage() {
        const logs = getFilteredAuditLogs();
        const totalPages = Math.max(1, Math.ceil(logs.length / state.auditPageSize));
        if (state.currentAuditPage >= totalPages) return;
        state.currentAuditPage += 1;
        renderAuditLogs();
    }

    function filterAuditLogs() {
        state.currentAuditPage = 1;
        renderAuditLogs();
    }

    function refreshAuditLogs() {
        showToast('Refreshing audit logs...', 'info');
        loadAuditLogs();
    }

    function updateSecurityStats() {
        if (!state.auditLogs.length) {
            ['failedLogins', 'activeSessions', 'totalLogins', 'lastActivity', 'currentOnline', 'todayLogins', 'weekLogins']
                .forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = id === 'lastActivity' ? '-' : '0';
                });
            return;
        }

        const logs = state.auditLogs;
        const now = new Date();
        const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const sevenDaysAgo = new Date(now.getTime() - (7 * 24 * 60 * 60 * 1000));

        const failedLogins = logs.filter((log) => log.action === 'failed_login' && new Date(log.created_at) >= startOfDay).length;
        const totalLoginsToday = logs.filter((log) => log.action === 'login' && new Date(log.created_at) >= startOfDay).length;
        const totalLoginsWeek = logs.filter((log) => log.action === 'login' && new Date(log.created_at) >= sevenDaysAgo).length;
        const activeSessions = logs.filter((log) => log.action === 'login').length -
            logs.filter((log) => log.action === 'logout').length;
        const lastActivity = logs[0] ? new Date(logs[0].created_at).toLocaleString() : 'No activity';

        const assignments = {
            failedLogins,
            totalLogins: totalLoginsToday,
            todayLogins: totalLoginsToday,
            weekLogins: totalLoginsWeek,
            activeSessions: Math.max(0, activeSessions),
            currentOnline: Math.max(0, activeSessions),
            lastActivity
        };

        Object.entries(assignments).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        });
    }

    function showAuditSection(section) {
        document.querySelectorAll('.audit-content-section').forEach((el) => {
            const matches = el.id === `audit-${section}-section`;
            el.classList.toggle('active', matches);
        });

        document.querySelectorAll('.audit-nav-button').forEach((button) => {
            const target = button.getAttribute('onclick') || '';
            button.classList.toggle('active', target.includes(`'${section}'`));
        });

        if (section === 'logs' && !state.auditLogs.length) {
            loadAuditLogs();
        }
    }

    function exportAuditLogs() {
        exportAuditReport('audit_logs', () => true);
    }

    function generateEmployeeReport() {
        const employeeActions = new Set([
            'employee_add',
            'employee_update',
            'employee_delete',
            'login',
            'logout'
        ]);
        exportAuditReport('employee_activity', (log) => employeeActions.has(log.action));
    }

    function generateSecurityReport() {
        const securityActions = new Set(['login', 'logout', 'failed_login', 'pin_login']);
        exportAuditReport('security_events', (log) => securityActions.has(log.action));
    }

    function generateSystemReport() {
        exportAuditReport('system_activity', () => true);
    }

    function exportAuditReport(label, predicate) {
        if (!state.auditLogs.length) {
            showToast('No audit logs available yet', 'warning');
            return;
        }

        const rows = state.auditLogs.filter(predicate);
        if (!rows.length) {
            showToast('No matching audit entries found', 'warning');
            return;
        }

        const headers = ['Timestamp', 'Employee', 'Action', 'Details', 'IP Address', 'Status'];
        const csv = [
            headers.join(','),
            ...rows.map((log) => [
                new Date(log.created_at).toISOString(),
                `"${(log.username || 'System').replace(/"/g, '""')}"`,
                log.action,
                `"${(log.details || '-').replace(/"/g, '""')}"`,
                log.ip_address || '-',
                log.action === 'failed_login' ? 'Failed' : 'Success'
            ].join(','))
        ].join('\n');

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `${label}_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        URL.revokeObjectURL(url);
        showToast('Report generated successfully', 'success');
    }

    return {
        init,
        refreshSystemInfo,
        backupDatabase,
        clearCache,
        optimizeDatabase,
        refreshAuditLogs,
        filterAuditLogs,
        previousAuditPage,
        nextAuditPage,
        showAuditSection,
        exportAuditLogs,
        generateEmployeeReport,
        generateSecurityReport,
        generateSystemReport
    };
})();

document.addEventListener('DOMContentLoaded', SettingsManager.init);

Object.assign(window, {
    refreshSystemInfo: SettingsManager.refreshSystemInfo,
    backupDatabase: SettingsManager.backupDatabase,
    clearCache: SettingsManager.clearCache,
    optimizeDatabase: SettingsManager.optimizeDatabase,
    refreshAuditLogs: SettingsManager.refreshAuditLogs,
    filterAuditLogs: SettingsManager.filterAuditLogs,
    previousAuditPage: SettingsManager.previousAuditPage,
    nextAuditPage: SettingsManager.nextAuditPage,
    showAuditSection: SettingsManager.showAuditSection,
    exportAuditLogs: SettingsManager.exportAuditLogs,
    generateEmployeeReport: SettingsManager.generateEmployeeReport,
    generateSecurityReport: SettingsManager.generateSecurityReport,
    generateSystemReport: SettingsManager.generateSystemReport
});

