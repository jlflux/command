// Athletics Command Center — base page behavior (vanilla JS, no dependencies).

document.addEventListener('DOMContentLoaded', () => {
    // Mobile nav toggle
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('mainnav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    }

    // Confirmation prompts: <form data-confirm="Are you sure?">
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});

// CSRF helper for future fetch() calls against app/api endpoints.
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}
