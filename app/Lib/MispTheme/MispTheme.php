<?php
class MispTheme
{
    public $name;
    public $label;
    public $description;
    public $active;
    public $hideFromUsers;

    public function __construct($name, $label = '', $description = '', $active = false, $hideFromUsers = false)
    {
        $this->name = $name;
        $this->label = !empty($label) ? $label : $name . ' UI';
        $this->description = $description;
        $this->active = $active;
        $this->hideFromUsers = $hideFromUsers;
    }

    /**
     * Whether a theme carries an implementation of one controller's
     * views.
     *
     * A page whose templates exist under exactly one theme is a fact
     * about where its files are, not a preference: rendering it under
     * a theme that does not have it throws `MissingViewException` and
     * the reader gets a 500 rather than a page. A controller with one
     * implementation asks this before leaving the reader's own theme
     * in place.
     *
     * @param string|null $theme
     * @param string $controller The view directory, e.g. `Values`
     * @return bool
     */
    public static function carries($theme, $controller)
    {
        if (empty($theme) || $theme === 'Default') {
            return false;
        }
        return is_dir(APP . 'View' . DS . 'Themed' . DS . $theme . DS
            . $controller);
    }

    /**
     * Get all available themes as MispTheme objects
     *
     * @param string $currentActiveTheme The name of the currently active theme
     * @param bool $showHiddenThemes Whether to include hidden themes (e.g. for developers)
     * @return MispTheme[]
     */
    public static function getAvailableThemes($currentActiveTheme = 'Default', $showHiddenThemes = false)
    {
        $userSetting = ClassRegistry::init('UserSetting');
        $themeNames = $userSetting::VALID_SETTINGS['ui_theme']['options'];

        $themes = [];
        foreach ($themeNames as $name) {
            $label = '';
            $description = '';
            $hideFromUsers = false;

            if ($name === 'Default') {
                $label = __('Default UI');
                $description = __('The classic MISP interface.');
            } else {
                $themeFile = APP . 'View' . DS . 'Themed' . DS . $name . DS . 'theme.php';
                if (file_exists($themeFile)) {
                    $themeConfig = include $themeFile;
                    $label = !empty($themeConfig['label']) ? $themeConfig['label'] : '';
                    $description = !empty($themeConfig['description']) ? $themeConfig['description'] : '';
                    $hideFromUsers = !empty($themeConfig['hide_from_users']) ? (bool)$themeConfig['hide_from_users'] : false;
                }
            }

            if ($hideFromUsers && !$showHiddenThemes && $name !== $currentActiveTheme) {
                continue;
            }
            $themes[] = new MispTheme($name, $label, $description, $name === $currentActiveTheme, $hideFromUsers);
        }
        return $themes;
    }
}
