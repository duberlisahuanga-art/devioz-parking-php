document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');

    if (sidebar && toggle) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('show'));
        document.addEventListener('click', (event) => {
            if (window.innerWidth >= 992) return;
            if (!sidebar.classList.contains('show')) return;
            if (sidebar.contains(event.target) || toggle.contains(event.target)) return;
            sidebar.classList.remove('show');
        });
    }

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const selector = button.getAttribute('data-toggle-password');
            const input = document.querySelector(selector);
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            const icon = button.querySelector('i');
            if (icon) icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    });
});
