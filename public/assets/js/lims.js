// PlexiQ LIMS - Custom JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Confirm dialogs for workflow actions
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });

    // Responsive tables: generate data-label attributes from thead for card-style mobile display
    document.querySelectorAll('.table-responsive-cards table').forEach(function(table) {
        var headers = [];
        var thead = table.querySelector('thead');
        if (!thead) return;
        thead.querySelectorAll('th').forEach(function(th) {
            headers.push(th.textContent.trim());
        });
        table.querySelectorAll('tbody tr').forEach(function(tr) {
            tr.querySelectorAll('td').forEach(function(td, index) {
                if (headers[index] && !td.hasAttribute('data-label')) {
                    td.setAttribute('data-label', headers[index]);
                }
            });
        });
    });

    // Sidebar: auto-collapse on mobile when clicking outside
    document.addEventListener('click', function(e) {
        var sidebar = document.getElementById('sidebar');
        var mobileToggle = document.getElementById('mobileToggle');
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('mobile-open')) {
            if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                closeMobileSidebar();
            }
        }
    });

    // Keyboard shortcut: ESC to close mobile sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth <= 768) {
            closeMobileSidebar();
        }
    });
});

// Mobile sidebar functions (called from layout)
function toggleMobileSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('show');
    document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
}

function closeMobileSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
}
