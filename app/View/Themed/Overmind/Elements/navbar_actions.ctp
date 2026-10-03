<?php // The navbar entries' actions, shared by navbar.ctp and navbar_rail.ctp. ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeButtons = document.querySelectorAll('.setTheme');

        themeButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const theme = String(this.dataset.theme || '');
                const safeTheme = encodeURIComponent(theme);

                fetch('<?php echo $baseurl; ?>/user_settings/setTheme/' + safeTheme, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': (window.csrfToken || '')
                    },
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (response.ok) {
                        location.reload();
                        throw new ShowToast('<?php echo __('Theme updated!'); ?>');
                    } else {
                        throw new Error('Server Error');
                    }
                })
                .catch(error => {
                    alert('<?php echo __('Failed to toggle Beta UI. Please try again.'); ?>');
                });
            });
        });

        document.querySelectorAll('.set-bootstrap-theme').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                if (this.getAttribute('aria-current') === 'true') {
                    return;
                }
                const theme = encodeURIComponent(String(this.dataset.theme || ''));
                fetch('<?= $baseurl ?>/user_settings/setBootstrapTheme/' + theme, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': (window.csrfToken || '')
                    },
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) throw new Error('Server Error');
                    location.reload();
                })
                .catch(() => {
                    showToast(<?= json_encode(__('Failed to change the theme. Please try again.')) ?>, 'danger');
                });
            });
        });

        document.querySelectorAll('.toggle-dark-mode').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleDarkMode();
            });
        });

        document.querySelectorAll('.set-homepage').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                fetch('<?php echo $baseurl; ?>/user_settings/setHomePage', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': (window.csrfToken || '')
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ path: window.location.pathname })
                })
                .then(response => {
                    if (!response.ok) throw new Error('Server Error');
                    showToast('<?php echo __('Homepage saved!'); ?>');
                })
                .catch(() => {
                    showToast('<?php echo __('Failed to set homepage. Please try again.'); ?>', 'danger');
                });
            });
        });
    });
</script>