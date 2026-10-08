<?php

/**
 * An index's filters as its URL carries them (CakePHP named segments), and
 * the URLs one change away from them.
 */
class IndexFilterState
{
    const PAGINATOR_KEYS = ['sort', 'direction', 'page', 'limit'];

    /** @var string */
    private $base;

    /** @var string prefix of every filter key, 'search' in event mode */
    private $prefix;

    /** @var array raw key => value, in URL order */
    private $named;

    /**
     * @param string $base index URL with no state on it
     * @param array $named the request's named params
     * @param string $prefix
     */
    public function __construct($base, array $named, $prefix = '')
    {
        $this->base = $base;
        $this->prefix = $prefix;
        $this->named = [];
        foreach ($named as $key => $value) {
            if (is_array($value)) {
                $value = implode('|', $value);
            }
            if ((string)$value !== '') {
                $this->named[(string)$key] = (string)$value;
            }
        }
    }

    /**
     * @return array filter name (unprefixed) => value, paginator keys left out
     */
    public function filters()
    {
        $out = [];
        foreach ($this->named as $key => $value) {
            if (in_array($key, self::PAGINATOR_KEYS, true)) {
                continue;
            }
            if ($this->prefix !== '') {
                if (strpos($key, $this->prefix) !== 0) {
                    continue;
                }
                $key = substr($key, strlen($this->prefix));
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /**
     * @param string $name unprefixed
     * @return string|null
     */
    public function get($name)
    {
        return $this->named[$this->prefix . $name] ?? null;
    }

    /**
     * @param string $name unprefixed
     * @return string the key the URL carries
     */
    public function key($name)
    {
        return $this->prefix . $name;
    }

    /**
     * A URL with these filters changed; null removes one. Paging restarts.
     *
     * @param array $changes unprefixed name => value|null
     * @return string
     */
    public function url(array $changes)
    {
        $named = $this->named;
        unset($named['page']);
        foreach ($changes as $name => $value) {
            $key = $this->prefix . $name;
            if ($value === null || (string)$value === '') {
                unset($named[$key]);
            } else {
                $named[$key] = (string)$value;
            }
        }
        return $this->format($named);
    }

    /**
     * Every filter dropped except the given ones; the sort is kept.
     *
     * @param array $keep unprefixed names
     * @return string
     */
    public function clearUrl(array $keep)
    {
        $named = [];
        foreach ($this->named as $key => $value) {
            $plain = $this->prefix !== '' && strpos($key, $this->prefix) === 0
                ? substr($key, strlen($this->prefix)) : $key;
            if (in_array($key, ['sort', 'direction', 'limit'], true) || in_array($plain, $keep, true)) {
                $named[$key] = $value;
            }
        }
        return $this->format($named);
    }

    /**
     * Split a picker value (`a|!b`) into its parts.
     *
     * @param string|null $value
     * @param string $separator
     * @return array [value, excluded][]
     */
    public static function pieces($value, $separator = '|')
    {
        $out = [];
        foreach (explode($separator, (string)$value) as $piece) {
            $piece = trim($piece);
            if ($piece === '' || $piece === '!') {
                continue;
            }
            $excluded = $piece[0] === '!';
            $out[] = [$excluded ? substr($piece, 1) : $piece, $excluded];
        }
        return $out;
    }

    /**
     * @param array $pieces [value, excluded][]
     * @param string $separator
     * @return string|null
     */
    public static function join(array $pieces, $separator = '|')
    {
        $parts = [];
        foreach ($pieces as [$value, $excluded]) {
            $parts[] = ($excluded ? '!' : '') . $value;
        }
        return $parts ? implode($separator, $parts) : null;
    }

    private function format(array $named)
    {
        $url = $this->base;
        foreach ($named as $key => $value) {
            $url .= '/' . $key . ':' . rawurlencode($value);
        }
        return $url;
    }
}
