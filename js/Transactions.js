(function () {
    const state = {
        transactions: [],
        summary: {
            totalRevenue: 0,
            count: 0
        },
        filters: {
            status: '',
            date: '',
            search: '',
            payment: ''
        }
    };

    const selectors = {
        tableBody: 'transactionsTableBody',
        statusFilter: 'transactionStatusFilter',
        paymentFilter: 'transactionPaymentFilter',
        dateFilter: 'transactionDateFilter',
        searchInput: 'transactionSearch',
        todaysRevenue: 'todaysRevenue',
        transactionCount: 'transactionCount',
        averageTransaction: 'averageTransaction'
    };

    document.addEventListener('DOMContentLoaded', () => {
        const tableBody = document.getElementById(selectors.tableBody);
        if (!tableBody) return;
        TransactionsManager.init();
    });

    const TransactionsManager = {
        init() {
            this.cacheDom();
            this.bindEvents();
            this.loadTransactions();
        },

        cacheDom() {
            this.tableBody = document.getElementById(selectors.tableBody);
            this.statusFilter = document.getElementById(selectors.statusFilter);
            this.paymentFilter = document.getElementById(selectors.paymentFilter);
            this.dateFilter = document.getElementById(selectors.dateFilter);
            this.searchInput = document.getElementById(selectors.searchInput);
            this.todaysRevenueEl = document.getElementById(selectors.todaysRevenue);
            this.transactionCountEl = document.getElementById(selectors.transactionCount);
            this.averageTransactionEl = document.getElementById(selectors.averageTransaction);
        },

        bindEvents() {
            if (this.statusFilter) {
                this.statusFilter.addEventListener('change', () => {
                    state.filters.status = this.statusFilter.value;
                    this.loadTransactions();
                });
            }

            if (this.paymentFilter) {
                this.paymentFilter.addEventListener('change', () => {
                    state.filters.payment = this.paymentFilter.value;
                    this.render();
                });
            }

            if (this.dateFilter) {
                this.dateFilter.addEventListener('change', () => {
                    state.filters.date = this.dateFilter.value;
                    this.loadTransactions();
                });
            }

            if (this.searchInput) {
                this.searchInput.addEventListener('input', () => {
                    state.filters.search = this.searchInput.value.trim().toLowerCase();
                    this.render();
                });
            }
        },

        async loadTransactions() {
            if (!this.tableBody) return;

            this.renderLoading();

            const params = new URLSearchParams();
            params.set('limit', '200');

            if (state.filters.status && state.filters.status !== 'all') {
                params.set('type', state.filters.status);
            }

            if (state.filters.date) {
                params.set('date', state.filters.date);
            }

            try {
                const response = await fetch(`db/transactions_get.php?${params.toString()}`);
                if (!response.ok) {
                    throw new Error(`Request failed with status ${response.status}`);
                }

                const data = await response.json();
                if (data.status !== 'ok') {
                    throw new Error(data.message || 'Failed to load transactions');
                }

                state.transactions = Array.isArray(data.transactions) ? data.transactions : [];
                state.summary.totalRevenue = Number(data.total_revenue || 0);
                state.summary.count = Number(data.count || 0);

                this.render();
                this.updateSummary();
            } catch (error) {
                console.error('Error loading transactions:', error);
                this.renderError(error.message || 'Unable to load transactions');
                if (typeof showToast === 'function') {
                    showToast('Failed to load transactions', 'error');
                }
            }
        },

        render() {
            if (!this.tableBody) return;

            const filtered = this.getFilteredTransactions();
            if (filtered.length === 0) {
                this.tableBody.innerHTML = `
                    <tr>
                        <td colspan="9" style="text-align:center; padding: 24px; color: #6b7280;">
                            No transactions found
                        </td>
                    </tr>
                `;
                return;
            }

            const rowsHtml = filtered.map(tx => {
                const itemsHtml = renderItemsHtml(tx);
                return `
                    <tr data-transaction-id="${tx.orderID}">
                        <td>${formatTransactionId(tx.orderID)}</td>
                        <td>${formatReference(tx.referenceNumber, tx.orderID)}</td>
                        <td>${formatDateTime(tx.order_date)}</td>
                        <td>${escapeHtml(tx.cashier_id || '-')}</td>
                        <td>${itemsHtml}</td>
                        <td>${formatCurrency(tx.totalAmount)}</td>
                        <td>${formatPayment(tx.payment_method)}</td>
                        <td>
                            <span class="status-badge ${statusClass(tx.status)}">
                                ${escapeHtml(tx.status || 'Unknown')}
                            </span>
                        </td>
                        <td>
                            <button class="btn-small btn-primary transaction-view-btn" data-transaction="${tx.orderID}">
                                View
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            this.tableBody.innerHTML = rowsHtml;

            this.bindRowActions(filtered);
        },

        bindRowActions(transactions) {
            const mapById = new Map(transactions.map(item => [String(item.orderID), item]));
            const buttons = this.tableBody.querySelectorAll('.transaction-view-btn');
            buttons.forEach(button => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const id = button.getAttribute('data-transaction');
                    const transaction = mapById.get(String(id));
                    if (transaction) {
                        this.showTransactionDetails(transaction);
                    }
                });
            });
        },

        showTransactionDetails(transaction) {
            const referenceDisplay = getReferenceValue(transaction);
            const itemsDetailHtml = renderItemsDetailHtml(transaction);
            const content = `
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div><strong>Transaction ID:</strong> ${formatTransactionId(transaction.orderID)}</div>
                    <div><strong>Reference:</strong> ${formatReference(transaction.referenceNumber, transaction.orderID)}</div>
                    <div><strong>Date:</strong> ${formatDateTime(transaction.order_date)}</div>
                    <div><strong>Cashier ID:</strong> ${escapeHtml(transaction.cashier_id || '-')}</div>
                    <div><strong>Status:</strong> ${escapeHtml(transaction.status || 'Unknown')}</div>
                    <div><strong>Payment Method:</strong> ${formatPayment(transaction.payment_method)}</div>
                    <div><strong>Total:</strong> ${formatCurrency(transaction.totalAmount)}</div>
                    <div>
                        <strong>Items:</strong>
                        <div style="margin-top:8px;">${itemsDetailHtml}</div>
                    </div>
                </div>
            `;

            if (window.ModalHelper && typeof ModalHelper.open === 'function') {
                ModalHelper.open({
                    id: 'transactionDetailModal',
                    title: `Transaction #${transaction.orderID}`,
                    content,
                    width: '480px'
                });
            } else {
                const itemLines = getItemLines(transaction);
                const plainItems = itemLines.length ? itemLines.join(', ') : (typeof transaction.items === 'string' ? transaction.items : 'No items');
                alert(
                    `Transaction #${transaction.orderID}\n` +
                    `Reference: ${referenceDisplay}\n` +
                    `Date: ${formatDateTime(transaction.order_date)}\n` +
                    `Cashier: ${transaction.cashier_id || '-'}\n` +
                    `Payment: ${formatPayment(transaction.payment_method)}\n` +
                    `Status: ${transaction.status || 'Unknown'}\n` +
                    `Total: ${formatCurrency(transaction.totalAmount)}\n` +
                    `Items: ${plainItems}`
                );
            }
        },

        updateSummary() {
            if (this.todaysRevenueEl) {
                this.todaysRevenueEl.textContent = formatCurrency(state.summary.totalRevenue);
            }
            if (this.transactionCountEl) {
                this.transactionCountEl.textContent = state.summary.count.toString();
            }
            if (this.averageTransactionEl) {
                const avg = state.summary.count > 0
                    ? state.summary.totalRevenue / state.summary.count
                    : 0;
                this.averageTransactionEl.textContent = formatCurrency(avg);
            }
        },

        getFilteredTransactions() {
            const search = state.filters.search;
            const paymentFilter = state.filters.payment;

            return state.transactions.filter(tx => {
                const haystack = [
                    tx.referenceNumber,
                    tx.orderID,
                    getItemsSearchText(tx),
                    tx.cashier_id,
                    tx.payment_method,
                    tx.status
                ].map(value => (value || '').toString().toLowerCase());

                const matchesSearch = !search || haystack.some(value => value.includes(search));
                const matchesPayment = !paymentFilter ||
                    (tx.payment_method || '').toLowerCase() === paymentFilter.toLowerCase();
                return matchesSearch && matchesPayment;
            });
        },

        renderLoading() {
            if (!this.tableBody) return;
            this.tableBody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align:center; padding: 24px; color: #6b7280;">
                        Loading transactions...
                    </td>
                </tr>
            `;
        },

        renderError(message) {
            if (!this.tableBody) return;
            this.tableBody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align:center; padding: 24px; color: #dc2626;">
                        ${escapeHtml(message)}
                    </td>
                </tr>
            `;
        }
    };

    function formatTransactionId(id) {
        if (id === null || id === undefined || id === '') return '-';
        const numeric = Number(id);
        const value = Number.isFinite(numeric) ? numeric.toString() : id.toString();
        return escapeHtml(value);
    }

    function formatReference(reference, orderId) {
        const fallback = orderId ? `ORD${String(orderId).padStart(6, '0')}` : '-';
        const value = reference && reference.toString().trim() !== '' ? reference : fallback;
        return escapeHtml(value);
    }

    function getReferenceValue(transaction) {
        if (!transaction) return '-';
        const fallback = transaction.orderID ? `ORD${String(transaction.orderID).padStart(6, '0')}` : '-';
        const reference = transaction.referenceNumber;
        if (reference === null || reference === undefined) return fallback;
        const text = reference.toString().trim();
        return text !== '' ? text : fallback;
    }

    function getItemLines(tx) {
        if (!tx) return [];
        if (Array.isArray(tx.items_list)) {
            return tx.items_list
                .map(line => (line === null || line === undefined) ? '' : String(line).trim())
                .filter(line => line !== '');
        }
        if (Array.isArray(tx.items)) {
            return tx.items
                .map(line => (line === null || line === undefined) ? '' : String(line).trim())
                .filter(line => line !== '');
        }
        if (typeof tx.items === 'string') {
            const trimmed = tx.items.trim();
            if (trimmed === '') return [];
            const separators = trimmed.includes("\n") ? /\r?\n/ : /,\s*/;
            return trimmed.split(separators).map(part => part.trim()).filter(part => part !== '');
        }
        return [];
    }

    function renderItemsHtml(tx) {
        const lines = getItemLines(tx);
        if (lines.length === 0) {
            const fallback = typeof tx.items === 'string' && tx.items.trim() !== '' ? tx.items : 'No items';
            return escapeHtml(fallback);
        }
        if (lines.length === 1) {
            const single = String(lines[0]);
            if (single.toLowerCase() === 'no items') {
                return escapeHtml(single);
            }
        }
        return lines.map(line => escapeHtml(String(line))).join('<br>');
    }

    function renderItemsDetailHtml(tx) {
        const lines = getItemLines(tx);
        if (lines.length === 0) {
            const fallback = typeof tx.items === 'string' && tx.items.trim() !== '' ? tx.items : 'No items';
            return escapeHtml(fallback);
        }
        if (lines.length === 1) {
            const single = String(lines[0]);
            if (single.toLowerCase() === 'no items') {
                return escapeHtml(single);
            }
        }
        const items = lines.map(line => `<li>${escapeHtml(String(line))}</li>`).join('');
        return `<ul style="margin:0; padding-left:18px;">${items}</ul>`;
    }

    function getItemsSearchText(tx) {
        const lines = getItemLines(tx);
        if (lines.length > 0) {
            return lines.join(' ');
        }
        if (typeof tx.items === 'string') {
            return tx.items;
        }
        return '';
    }

    function formatDateTime(value) {
        if (!value) return '-';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleString();
    }

    function formatCurrency(amount) {
        const number = Number(amount) || 0;
        try {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(number);
        } catch (error) {
            return `PHP ${number.toFixed(2)}`;
        }
    }

    function formatPayment(method) {
        if (!method) return '-';
        const normalized = method.toString().toLowerCase();
        if (normalized === 'gcash') return 'GCash';
        return normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    function statusClass(status) {
        if (!status) return 'status-unknown';
        const normalized = status.toLowerCase();
        if (normalized === 'completed' || normalized === 'complete') return 'status-completed';
        if (normalized === 'pending') return 'status-pending';
        if (normalized === 'cancelled' || normalized === 'canceled') return 'status-cancelled';
        return `status-${normalized}`;
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return value.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    window.TransactionsManager = TransactionsManager;
})();
