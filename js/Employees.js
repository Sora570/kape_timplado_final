// Employees Management JavaScript

let employees = [];
let selectedEmployeeIds = new Set();

// Tab switching for employee modal
function switchEmployeeTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    const targetTab = document.getElementById(tabName + 'InfoTab');
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    // Activate corresponding tab button
    const targetButton = document.querySelector(`[onclick="switchEmployeeTab('${tabName}')"]`);
    if (targetButton) {
        targetButton.classList.add('active');
    }
}

// Load employees list
async function loadEmployees() {
    try {
        const response = await fetch('db/employees_get.php');
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error('HTTP ' + response.status + ': ' + errorText);
        }
        
        const data = await response.json();
        
        // Check for array of employees
        if (Array.isArray(data)) {
            employees = data;
        } else if (data.employees) {
            employees = data.employees;
        } else if (data.error) {
            throw new Error(data.error);
        } else {
            employees = [];
        }
        
        selectedEmployeeIds.clear();
        renderEmployeesTable();
        updateEmployeeBulkActions();
    } catch (error) {
        console.error('Error loading employees:', error);
        showToast('Failed to load employees: ' + error.message, 'error');
        employees = [];
        selectedEmployeeIds.clear();
        renderEmployeesTable();
        updateEmployeeBulkActions();
    }
}

// Toast notifications helper
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast') || createToastElement();
    const toastMessage = toast.querySelector('#toast-message');
    
    if (toastMessage) {
        toastMessage.innerText = message;
        toast.className = 'toast show';
        
        if (type === 'error') {
            toast.classList.add('toast-error');
        } else if (type === 'success') {
            toast.classList.add('toast-success');
        }
        
        setTimeout(() => {
            toast.className = toast.className.replace('show', '');
        }, 4000);
    }
}

function createToastElement() {
    const toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'toast';
    toast.innerHTML = '<span id="toast-message"></span>';
    document.body.appendChild(toast);
    return toast;
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Render employees table
function renderEmployeesTable() {
    const tbody = document.getElementById('employeesTableBody');
    if (!tbody) return;
    
    if (employees.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; color: #6b7280; padding: 2rem;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                        <div style="font-size: 48px;">👥</div>
                        <div>No employees found</div>
                    </div>
                </td>
            </tr>
        `;
        const selectAll = document.getElementById('employeesSelectAll');
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        updateEmployeeBulkActions();
        return;
    }
    
    tbody.innerHTML = employees.map(emp => {
        const userId = Number(emp.userID);
        const isSelected = selectedEmployeeIds.has(userId);
        const role = emp.role || '';
        const status = emp.status || 'Not Started';
        const roleClass = role ? `role-${role}` : 'role-unknown';
        return `
        <tr class="employee-row ${isSelected ? 'selected' : ''}" data-user-id="${userId}">
            <td><strong>${escapeHtml(userId)}</strong></td>
            <td>${escapeHtml(emp.username || '')}</td>
            <td><code>${escapeHtml(emp.employee_id || 'N/A')}</code></td>
            <td>
                <span class="role-badge ${roleClass}">
                    ${escapeHtml(role.toUpperCase())}
                </span>
            </td>
            <td>${escapeHtml(formatDate(emp.createdAt || emp.created_at))}</td>
            <td>${escapeHtml(formatDate(emp.lastLogin))}</td>
            <td>
                <span class="status-badge ${escapeHtml(status)}">
                    ${escapeHtml(status)}
                </span>
            </td>
            <td class="employee-select">
                <input type="checkbox" class="employee-checkbox" data-user-id="${userId}" ${isSelected ? 'checked' : ''}>
            </td>
        </tr>
    `;
    }).join('');
    
    attachEmployeeRowHandlers();
    syncEmployeeSelectAllState();
    updateEmployeeBulkActions();
}

function attachEmployeeRowHandlers() {
    document.querySelectorAll('.employee-row').forEach(row => {
        row.addEventListener('click', event => {
            if (event.target.classList.contains('employee-checkbox')) return;
            const id = parseInt(row.dataset.userId, 10);
            if (Number.isFinite(id)) {
                editEmployee(id);
            }
        });
    });

    document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
        checkbox.addEventListener('click', event => {
            event.stopPropagation();
            const id = parseInt(event.currentTarget.dataset.userId, 10);
            if (!Number.isFinite(id)) return;

            if (event.currentTarget.checked) {
                selectedEmployeeIds.add(id);
                const rowElement = event.currentTarget.closest('tr');
                if (rowElement) {
                    rowElement.classList.add('selected');
                }
            } else {
                selectedEmployeeIds.delete(id);
                const rowElement = event.currentTarget.closest('tr');
                if (rowElement) {
                    rowElement.classList.remove('selected');
                }
            }

            syncEmployeeSelectAllState();
            updateEmployeeBulkActions();
        });
    });
}

function syncEmployeeSelectAllState() {
    const selectAll = document.getElementById('employeesSelectAll');
    if (!selectAll) return;

    const total = employees.length;
    const selected = selectedEmployeeIds.size;

    selectAll.checked = total > 0 && selected === total;
    selectAll.indeterminate = selected > 0 && selected < total;
}

function updateEmployeeBulkActions() {
    const bulkBar = document.getElementById('employeeBulkActions');
    const deleteBtn = document.getElementById('employeeBulkDeleteBtn');
    const countEl = document.getElementById('selectedEmployeesCount');

    if (!bulkBar || !deleteBtn) return;

    const count = selectedEmployeeIds.size;
    if (count > 0) {
        bulkBar.classList.add('visible');
        deleteBtn.disabled = false;
        if (countEl) countEl.textContent = count;
    } else {
        bulkBar.classList.remove('visible');
        deleteBtn.disabled = true;
        if (countEl) countEl.textContent = '0';
    }
}

// Format date display
function formatDate(dateString) {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function updateCredentialFieldVisibility() {
    const roleSelect = document.getElementById('employeeRole');
    const passwordGroup = document.getElementById('employeePasswordGroup');
    const pinGroup = document.getElementById('employeePinGroup');
    const passwordInput = document.getElementById('employeePassword');
    const pinInput = document.getElementById('employeePin');

    if (!roleSelect || !passwordGroup || !pinGroup || !passwordInput || !pinInput) {
        return;
    }

    const role = roleSelect.value;
    if (role === 'admin') {
        passwordGroup.style.display = '';
        pinGroup.style.display = 'none';
        passwordInput.required = true;
        pinInput.required = false;
        pinInput.value = '';
    } else {
        passwordGroup.style.display = 'none';
        pinGroup.style.display = '';
        passwordInput.required = false;
        passwordInput.value = '';
        pinInput.required = true;
    }
}

function updateEditCredentialVisibility(roleSelect, container) {
    if (!roleSelect || !container) {
        return;
    }
    const passwordGroup = container.querySelector('#editPasswordGroup');
    const pinGroup = container.querySelector('#editPinGroup');
    const passwordInput = container.querySelector('#editPassword');
    const pinInput = container.querySelector('#editPin');

    if (!passwordGroup || !pinGroup || !passwordInput || !pinInput) {
        return;
    }

    if (roleSelect.value === 'admin') {
        passwordGroup.style.display = '';
        pinGroup.style.display = 'none';
        passwordInput.required = true;
        pinInput.required = false;
        pinInput.value = '';
    } else {
        passwordGroup.style.display = 'none';
        pinGroup.style.display = '';
        passwordInput.required = false;
        passwordInput.value = '';
        pinInput.required = true;
    }
}

function setupEditCredentialHandlers(roleSelect, container) {
    if (!roleSelect || !container) {
        return;
    }
    const handler = () => updateEditCredentialVisibility(roleSelect, container);
    roleSelect.addEventListener('change', handler);
    // Store handler for potential cleanup if needed
    roleSelect.dataset.credentialHandler = 'true';
    updateEditCredentialVisibility(roleSelect, container);
}

// Show add employee modal
function showAddEmployeeModal() {
    const modal = document.getElementById('addEmployeeModal');
    if (modal) {
        modal.style.display = 'block';
        const form = document.getElementById('addEmployeeForm');
        if (form) {
            form.reset();
        }
        const roleSelect = document.getElementById('employeeRole');
        if (roleSelect) {
            roleSelect.value = 'cashier';
        }
        updateCredentialFieldVisibility();
    }
}

// Close add employee modal
function closeAddEmployeeModal() {
    const modal = document.getElementById('addEmployeeModal');
    if (modal) {
        modal.style.display = 'none';
    }
    const form = document.getElementById('addEmployeeForm');
    if (form) {
        form.reset();
    }
    const roleSelect = document.getElementById('employeeRole');
    if (roleSelect) {
        roleSelect.value = 'cashier';
    }
    updateCredentialFieldVisibility();
}

// Handle add employee form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addEmployeeForm');
    const roleSelect = document.getElementById('employeeRole');

    if (roleSelect) {
        roleSelect.addEventListener('change', updateCredentialFieldVisibility);
    }
    updateCredentialFieldVisibility();

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Basic Information
            const firstName = document.getElementById('employeeFirstName').value;
            const lastName = document.getElementById('employeeLastName').value;
            const email = document.getElementById('employeeEmail').value;
            const phone = document.getElementById('employeePhone').value;
            const address = document.getElementById('employeeAddress').value;
            
            // Login Information
            const username = document.getElementById('employeeUsername').value;
            let password = document.getElementById('employeePassword').value;
            const role = document.getElementById('employeeRole').value;
            const pin = document.getElementById('employeePin').value;
            const employeeId = document.getElementById('employeeId').value;

            if (!firstName || !lastName || !email || !phone || !address || !username || !employeeId) {
                showToast('Please fill in all required fields.', 'error');
                return;
            }

            if (!['admin', 'cashier'].includes(role)) {
                showToast('Invalid role selected.', 'error');
                return;
            }

            if (role === 'admin') {
                if (!password) {
                    showToast('Password is required for admin accounts.', 'error');
                    return;
                }
            } else {
                if (!pin) {
                    showToast('PIN is required for cashier accounts.', 'error');
                    return;
                }
                if (!/^[0-9]{4}$/.test(pin)) {
                    showToast('PIN must be exactly 4 digits.', 'error');
                    return;
                }
                if (!password) {
                    password = pin;
                }
            }

            const pinToSend = role === 'cashier' ? pin : '';
            const passwordToSend = password;
            
            try {
                const response = await fetch('db/employees_add.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `firstName=${encodeURIComponent(firstName)}&lastName=${encodeURIComponent(lastName)}&email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}&address=${encodeURIComponent(address)}&username=${encodeURIComponent(username)}&password=${encodeURIComponent(passwordToSend)}&role=${encodeURIComponent(role)}&pin=${encodeURIComponent(pinToSend)}&employeeId=${encodeURIComponent(employeeId)}`
                });
                
                if (response.ok) {
                    showToast('Employee added successfully!', 'success');
                    closeAddEmployeeModal();
                    loadEmployees();
                } else {
                    const error = await response.text();
                    showToast('Failed to add employee: ' + error, 'error');
                }
            } catch (error) {
                console.error('Error adding employee:', error);
                showToast('Error adding employee', 'error');
            }
        });
    }
});

function editEmployee(userID) {
    const employee = employees.find(emp => Number(emp.userID) === Number(userID));
    if (!employee) {
        showToast('Unable to locate employee record.', 'error');
        return;
    }

    openEditEmployeeModal(employee);
}

// Delete employee
async function deleteEmployee(userID) {
    if (!confirm('Are you sure you want to delete this employee?')) {
        return;
    }
    
    try {
        const response = await fetch('db/employees_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `userID=${userID}`
        });
        
        if (response.ok) {
            selectedEmployeeIds.delete(Number(userID));
            showToast('Employee deleted successfully!', 'success');
            await loadEmployees(); // Refresh list
            updateEmployeeBulkActions();
        } else {
            showToast('Failed to delete employee', 'error');
        }
    } catch (error) {
        console.error('Error deleting employee:', error);
        showToast('Error deleting employee', 'error');
    }
}

function openEditEmployeeModal(employee) {
    const modalId = 'employeeEditModal';
    const content = `
        <form id="editEmployeeForm" class="employee-edit-form">
            <input type="hidden" name="userID" value="${escapeHtml(employee.userID)}">
            <div class="form-grid">
                <div class="form-group">
                    <label for="editFirstName">First Name</label>
                    <input id="editFirstName" name="firstName" type="text" required value="${escapeHtml(employee.first_name || '')}">
                </div>
                <div class="form-group">
                    <label for="editLastName">Last Name</label>
                    <input id="editLastName" name="lastName" type="text" required value="${escapeHtml(employee.last_name || '')}">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input id="editEmail" name="email" type="email" required value="${escapeHtml(employee.email || '')}">
                </div>
                <div class="form-group">
                    <label for="editPhone">Phone</label>
                    <input id="editPhone" name="phone" type="text" required value="${escapeHtml(employee.phone || '')}">
                </div>
            </div>
            <div class="form-group">
                <label for="editAddress">Address</label>
                <textarea id="editAddress" name="address" rows="2" required>${escapeHtml(employee.address || '')}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="editRole">Role</label>
                    <select id="editRole" name="role" required>
                        <option value="cashier" ${employee.role === 'cashier' ? 'selected' : ''}>Cashier</option>
                        <option value="admin" ${employee.role === 'admin' ? 'selected' : ''}>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editEmployeeId">Employee ID</label>
                    <input id="editEmployeeId" name="employeeId" type="text" required value="${escapeHtml(employee.employee_id || '')}">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group" id="editPasswordGroup" style="display:${employee.role === 'admin' ? '' : 'none'};">
                    <label for="editPassword">New Password <span class="optional">(leave blank to keep current)</span></label>
                    <input id="editPassword" name="password" type="password" autocomplete="new-password" placeholder="********">
                </div>
                <div class="form-group" id="editPinGroup" style="display:${employee.role === 'cashier' ? '' : 'none'};">
                    <label for="editPin">New 4-digit PIN <span class="optional">(leave blank to keep current)</span></label>
                    <input id="editPin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="1234">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    `;

    ModalHelper.open({
        id: modalId,
        title: `Edit Employee - ${escapeHtml(employee.username)}`,
        content,
        width: '560px',
        onOpen: ({ body }) => {
            const form = body.querySelector('#editEmployeeForm');
            form.addEventListener('submit', handleEditEmployeeSubmit);
            const roleSelect = body.querySelector('#editRole');
            setupEditCredentialHandlers(roleSelect, body);
            const cancelBtn = body.querySelector('[data-modal-close]');
            cancelBtn?.addEventListener('click', () => ModalHelper.close(modalId));
        }
    });
}

async function handleEditEmployeeSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';

    try {
        const formData = new FormData(form);
        const payload = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            payload.append(key, value.trim());
        }

        const response = await fetch('db/employees_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(errorText || 'Failed to update employee.');
        }

        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message || 'Failed to update employee.');
        }

        showToast('Employee updated successfully!', 'success');
        ModalHelper.close('employeeEditModal');
        await loadEmployees();
        updateEmployeeBulkActions();
    } catch (error) {
        console.error('Error updating employee:', error);
        showToast(error.message || 'Failed to update employee.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

async function deleteSelectedEmployees() {
    const ids = Array.from(selectedEmployeeIds);
    if (ids.length === 0) {
        showToast('No employees selected', 'warning');
        return;
    }

    const confirmMessage = ids.length === 1
        ? 'Delete selected employee?'
        : `Delete ${ids.length} employees?`;
    if (!confirm(confirmMessage)) {
        return;
    }

    try {
        for (const id of ids) {
            const response = await fetch('db/employees_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `userID=${encodeURIComponent(id)}`
            });
            const resultText = await response.text();
            if (!response.ok || resultText.trim() !== 'success') {
                throw new Error(resultText || 'Failed to delete employee');
            }
        }

        showToast(`Deleted ${ids.length} employee${ids.length > 1 ? 's' : ''}`, 'success');
        selectedEmployeeIds.clear();
        await loadEmployees();
        updateEmployeeBulkActions();
    } catch (error) {
        console.error('Bulk delete error:', error);
        showToast(error.message || 'Failed to delete selected employees', 'error');
    }
}

// Employee filtering functions
function initEmployeeFilters() {
    const searchInput = document.getElementById('employeeSearch');
    const roleFilter = document.getElementById('employeeRoleFilter');
    
    if (searchInput) {
        searchInput.addEventListener('input', filterEmployees);
    }
    
    if (roleFilter) {
        roleFilter.addEventListener('change', filterEmployees);
    }
}

function filterEmployees() {
    const searchTerm = document.getElementById('employeeSearch')?.value.toLowerCase() || '';
    const roleFilter = document.getElementById('employeeRoleFilter')?.value || '';
    
    const filteredEmployees = employees.filter(emp => {
        const matchesSearch = emp.username.toLowerCase().includes(searchTerm) ||
                            String(emp.employee_id || '').toLowerCase().includes(searchTerm);
        const matchesRole = !roleFilter || emp.role === roleFilter;
        
        return matchesSearch && matchesRole;
    });
    
    // Temporarily store original and render filtered
    const originalEmployees = employees;
    employees = filteredEmployees;
    renderEmployeesTable();
    employees = originalEmployees; // Restore original data
}

// Initialize on document ready
document.addEventListener('DOMContentLoaded', function() {
    initEmployeeFilters();
    
    const selectAll = document.getElementById('employeesSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', event => {
            const checked = event.target.checked;
            selectedEmployeeIds.clear();
            if (checked) {
                document.querySelectorAll("#employeesTableBody .employee-row").forEach(row => {
                    const id = Number(row.dataset.userId);
                    if (Number.isFinite(id)) {
                        selectedEmployeeIds.add(id);
                    }
                });
            }
            renderEmployeesTable();
            updateEmployeeBulkActions();
        });
    }

    const bulkDeleteBtn = document.getElementById('employeeBulkDeleteBtn');
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', deleteSelectedEmployees);
    }
});

// Expose functions globally
window.showAddEmployeeModal = showAddEmployeeModal;
window.closeAddEmployeeModal = closeAddEmployeeModal;
window.loadEmployees = loadEmployees;
window.editEmployee = editEmployee;
window.deleteEmployee = deleteEmployee;
window.switchEmployeeTab = switchEmployeeTab;







