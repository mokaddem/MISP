<?php

/**
 * Where an identifier has an account.
 *
 * **`socialscan` emits this, free and unauthenticated.** It returns
 * one `user-account` per platform the identifier was found on, with
 * the platform as `account-type` and the identifier itself as the
 * handle — `username` where it was asked for a name, `email` and
 * `user-id` where it was asked for an address. A platform that
 * answered *available*, or that failed, produces no object, so the
 * objects are the hits and nothing else.
 *
 * No `link` is emitted: the module knows a platform holds the account
 * and not where it lives, and a URL guessed from a platform name is a
 * link that sometimes 404s. `accountOf()` reads one where a richer
 * producer sends it.
 *
 * **One object is one platform.** The set is the answer — *found on
 * six of the eleven checked* — so the count is the headline and the
 * platforms are the detail, which is the inverse of most shapes here.
 */
class AccountPresenceRenderer extends ValueRendererBase
{
    public $id = 'account-presence';

    public $templates = array('user-account');

    public $compact = 'Values/Renderers/account_presence_compact';

    public $full = 'Values/Renderers/account_presence_full';

    public function __construct()
    {
        $this->description = __('The platforms an identifier has an'
            . ' account on.');
    }

    public function matches(array $objects, array $attributes)
    {
        foreach ($objects as $object) {
            if ($this->accountOf($object) !== null) {
                return true;
            }
        }
        return false;
    }

    public function prepare(array $objects, array $attributes)
    {
        $accounts = array();
        foreach ($objects as $object) {
            $account = $this->accountOf($object);
            if ($account === null) {
                continue;
            }
            /*
             * Deduplicated on the platform and the handle together: an
             * identifier can legitimately have two accounts on one
             * platform, and two modules finding one account is not two
             * accounts.
             */
            $key = strtolower(
                ($account['platform'] ?? '') . "\0"
                . ($account['handle'] ?? '')
            );
            if (isset($accounts[$key])) {
                $held = $accounts[$key]['sources'];
                if (!in_array($account['module'], $held, true)) {
                    $accounts[$key]['sources'][] = $account['module'];
                }
                continue;
            }
            $accounts[$key] = $account;
        }
        $accounts = array_values($accounts);
        $platforms = array();
        foreach ($accounts as $account) {
            $platform = $account['platform'];
            if ($platform !== null
                && !in_array($platform, $platforms, true)
            ) {
                $platforms[] = $platform;
            }
        }
        return array(
            'accounts' => $accounts,
            'platforms' => $platforms,
            'count' => count($accounts),
            'sources' => $this->sources($objects),
        );
    }

    /**
     * @param array $object
     * @return array|null
     */
    private function accountOf(array $object)
    {
        $handle = $this->firstValue($object, array(
            'username', 'email', 'user-id', 'display-name',
        ));
        $platform = $this->value($object, 'account-type');
        if ($handle === null && $platform === null) {
            return null;
        }
        return array(
            'platform' => $platform,
            'handle' => $handle,
            'display' => $this->value($object, 'display-name'),
            'link' => $this->value($object, 'link'),
            'created' => $this->stamp($this->value($object, 'created')),
            'last_login' => $this->stamp(
                $this->value($object, 'last_login')
            ),
            'description' => $this->value($object, 'description'),
            'avatar' => $this->value($object, 'user-avatar'),
            'module' => $object['module'] ?? null,
            'ran_at' => $object['ran_at'] ?? null,
            'sources' => array($object['module'] ?? null),
        );
    }
}
