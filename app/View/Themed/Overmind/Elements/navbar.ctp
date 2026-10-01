<nav class="rc-nav" aria-label="<?= __('Main') ?>">
    <div class="rc-bar">
        <a class="rc-brand" href="<?= empty($homepage['path']) ? $baseurl .'/' : $baseurl . h($homepage['path']) ?>">
            <?= $this->Html->image('misp-logo-main-cmyk-icon coul.png', ['alt' => __('MISP Logo')]) ?>
        </a>

        <button type="button" class="rc-toggler" aria-expanded="false" aria-controls="rc-collapse" aria-label="<?= __('Menu') ?>">
            <i class="fas fa-bars rc-burger" aria-hidden="true"></i>
            <i class="fas fa-xmark rc-x" aria-hidden="true"></i>
        </button>

        <div class="rc-collapse" id="rc-collapse">
            <ul class="rc-groups rc-groups-left">
                <?php foreach ($menus['left'] as $item): ?>
                    <?= $this->element('navbar_nav', ['item' => $item]) ?>
                <?php endforeach; ?>
            </ul>

            <ul class="rc-groups rc-groups-right">
                <?php foreach ($menus['right'] as $item): ?>
                    <?= $this->element('navbar_nav', ['item' => $item, 'alignEnd' => true]) ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="rc-scrim" aria-hidden="true"></div>
</nav>
 

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