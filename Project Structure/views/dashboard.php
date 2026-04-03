<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employee Dashboard</title>
<link href="assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
<div class="navbar">
    <div class="navbar-logo">APPROVEX</div>
    <div class="profile-container">
        <div class="profile-icon" onclick="toggleProfileDropdown()">👤</div>
        <div class="profile-dropdown" id="profileDropdown">
            <a href="#">My Profile</a>
            <button onclick="openLoginModal()">Login</button>
            <button class="logout-option" onclick="logout()">Logout</button>
        </div>
    </div>
</div>

<div class="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-section-title">Dashboard</div>
        <a href="#" class="active">Employee Dashboard</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Management</div>
        <a href="#">Employee</a>
        <a href="#">Department</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Budget</div>
        <a href="#">Budget Monitor</a>
        <a href="#">Budget Category</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Workflows</div>
        <a href="#">Approval Workflows</a>
        <a href="#">Workflow Tracker</a>
        <a href="#">My Expenses</a>
    </div>
</div>

<div class="main">
    <div class="page-shell">
        <section class="hero">
            <h1>Employee Dashboard</h1>
            <p>Track request volumes, spending, and open decisions in one place.</p>
        </section>

        <section class="cards-grid">
            <article class="metric-card total-tickets">
                <div class="metric-label">Total Tickets</div>
                <div class="metric-value" id="totalTicketsValue">0</div>
            </article>
            <article class="metric-card accepted-tickets">
                <div class="metric-label">Accepted Tickets</div>
                <div class="metric-value" id="acceptedTicketsValue">0</div>
            </article>
            <article class="metric-card rejected-tickets">
                <div class="metric-label">Rejected Tickets</div>
                <div class="metric-value" id="rejectedTicketsValue">0</div>
            </article>
            <article class="metric-card department-budget">
                <div class="metric-label">Department Total Budget</div>
                <div class="metric-value" id="departmentBudgetValue">INR 0.00</div>
            </article>
            <article class="metric-card total-expense">
                <div class="metric-label">Total Expense</div>
                <div class="metric-value" id="totalExpenseValue">INR 0.00</div>
            </article>
            <article class="metric-card budget-remaining">
                <div class="metric-label">Budget Remaining</div>
                <div class="metric-value" id="budgetRemainingValue">INR 0.00</div>
            </article>
        </section>

        <section class="tickets-panel">
            <div class="tabs">
                <button class="tab-btn active" data-status="Pending" onclick="setActiveTab('Pending', event)">Pending Requests</button>
                <button class="tab-btn" data-status="Approved" onclick="setActiveTab('Approved', event)">Approved</button>
                <button class="tab-btn" data-status="Rejected" onclick="setActiveTab('Rejected', event)">Rejected</button>
            </div>
            <div class="ticket-list" id="ticketList"></div>
        </section>
    </div>
</div>

<div class="footer">
    <p>&copy; 2026 ApproveX. All rights reserved.</p>
</div>

<script>
const defaultTickets = [
    { id: 'REQ-2026-002', title: 'Laptop Purchase for New Joiner', requestType: 'Expense Request', requestDate: '15/03/2026', expenseDate: '14/03/2026', expenseType: 'Technology Procurement', employee: 'Aarav Shah', department: 'IT', amount: 78500, description: 'Request raised for a laptop and accessories for a newly onboarded software engineer.', receiptName: 'laptop-quote.pdf', status: 'Pending', currentStep: 'Dept Head Approval', actor: 'Vikram Rao' },
    { id: 'REQ-2026-004', title: 'Client Visit Travel Expense', requestType: 'Expense Request', requestDate: '21/03/2026', expenseDate: '20/03/2026', expenseType: 'Travel & Logistics', employee: 'Neha Iyer', department: 'Finance', amount: 26500, description: 'Travel reimbursement for client site visit including train, hotel, and local commute.', receiptName: 'travel-bills.pdf', status: 'Pending', currentStep: 'Reporting Manager Review', actor: 'Rahul Mehta' },
    { id: 'REQ-2026-001', title: 'Annual Training Workshop', requestType: 'Expense Request', requestDate: '04/03/2026', expenseDate: '02/03/2026', expenseType: 'Learning & Development', employee: 'Maya Thomas', department: 'HR', amount: 54000, description: 'Training workshop fees for annual skill development program.', receiptName: 'training-invoice.pdf', status: 'Approved', currentStep: 'Final Closure', actor: 'System' },
    { id: 'REQ-2026-003', title: 'Regional Project Logistics', requestType: 'Expense Request', requestDate: '18/03/2026', expenseDate: '17/03/2026', expenseType: 'Project Delivery', employee: 'Kiran Dev', department: 'Operations', amount: 112000, description: 'Regional site material transport and logistics support for active project delivery.', receiptName: 'logistics-receipt.pdf', status: 'Approved', currentStep: 'Final Closure', actor: 'System' },
    { id: 'REQ-2026-005', title: 'Office Furniture Replacement', requestType: 'Expense Request', requestDate: '26/03/2026', expenseDate: '25/03/2026', expenseType: 'Office Operations', employee: 'Ritu Jain', department: 'Finance', amount: 43000, description: 'Replacement of damaged workstations and chairs for finance operations team.', receiptName: 'furniture-vendor-quote.pdf', status: 'Rejected', currentStep: 'Finance Review', actor: 'Sonia Kapoor' }
];

const departmentTotalBudget = 3700000;
let activeStatus = 'Pending';

function formatCurrency(value) {
    return `INR ${value.toFixed(2)}`;
}

function getTickets() {
    const stored = localStorage.getItem('dashboardTickets');
    if (stored) {
        const storedTickets = JSON.parse(stored);
        return defaultTickets.map(defaultTicket => {
            const storedTicket = storedTickets.find(ticket => ticket.id === defaultTicket.id);
            return storedTicket ? { ...defaultTicket, ...storedTicket } : defaultTicket;
        });
    }
    localStorage.setItem('dashboardTickets', JSON.stringify(defaultTickets));
    return defaultTickets;
}

function saveTickets(tickets) {
    localStorage.setItem('dashboardTickets', JSON.stringify(tickets));
}

function updateCards(tickets) {
    const approved = tickets.filter(ticket => ticket.status === 'Approved');
    const rejected = tickets.filter(ticket => ticket.status === 'Rejected');
    const totalExpense = approved.reduce((sum, ticket) => sum + ticket.amount, 0);

    document.getElementById('totalTicketsValue').textContent = tickets.length;
    document.getElementById('acceptedTicketsValue').textContent = approved.length;
    document.getElementById('rejectedTicketsValue').textContent = rejected.length;
    document.getElementById('departmentBudgetValue').textContent = formatCurrency(departmentTotalBudget);
    document.getElementById('totalExpenseValue').textContent = formatCurrency(totalExpense);
    document.getElementById('budgetRemainingValue').textContent = formatCurrency(departmentTotalBudget - totalExpense);
}

function renderTickets() {
    const tickets = getTickets();
    updateCards(tickets);
    const filtered = tickets.filter(ticket => ticket.status === activeStatus);
    const ticketList = document.getElementById('ticketList');

    if (!filtered.length) {
        ticketList.innerHTML = `<div class="empty-state">No ${activeStatus.toLowerCase()} tickets available.</div>`;
        return;
    }

    ticketList.innerHTML = filtered.map(ticket => `
        <article class="ticket-card" onclick="openTicket('${ticket.id}')">
            <div class="ticket-top">
                <div>
                    <div class="ticket-id">${ticket.id}</div>
                    <div class="ticket-title">${ticket.title}</div>
                </div>
                <span class="status-badge status-${ticket.status.toLowerCase()}">${ticket.status}</span>
            </div>
            <div class="ticket-meta">
                <div>
                    <div class="meta-label">Employee</div>
                    <div class="meta-value">${ticket.employee}</div>
                </div>
                <div>
                    <div class="meta-label">Department</div>
                    <div class="meta-value">${ticket.department}</div>
                </div>
                <div>
                    <div class="meta-label">Amount</div>
                    <div class="meta-value">${formatCurrency(ticket.amount)}</div>
                </div>
            </div>
            <div class="ticket-footer">Open ticket for action</div>
        </article>
    `).join('');
}

function setActiveTab(status, event) {
    activeStatus = status;
    document.querySelectorAll('.tab-btn').forEach(button => button.classList.remove('active'));
    event.currentTarget.classList.add('active');
    renderTickets();
}

function openTicket(id) {
    window.location.href = `Ticket-Action.html?id=${encodeURIComponent(id)}`;
}

function toggleProfileDropdown() {
    document.getElementById('profileDropdown').classList.toggle('active');
}

document.addEventListener('click', function(event) {
    const profileContainer = document.querySelector('.profile-container');
    const dropdown = document.getElementById('profileDropdown');
    if (!profileContainer.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});

function openLoginModal() {
    alert('Opening Login Modal - To be implemented with actual login form');
    toggleProfileDropdown();
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = '?route=logout';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    renderTickets();
});
</script>
</body>
</html>
