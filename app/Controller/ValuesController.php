<?php
App::uses('AppController', 'Controller');
App::uses('ValueProfileFixture', 'Tools');

/**
 * Value Profile controller, mounted at /values/* via CakePHP's default
 * routing.
 *
 * The subject of these pages is a value string — `185.234.219.24`, a hash,
 * a domain — not a single attribute row. The same value exists as many
 * attribute rows across many events, and this controller aggregates them.
 *
 * Read-only: nothing here writes, and every number is fixture data until
 * the per-panel model queries land.
 */
class ValuesController extends AppController
{
    public $components = array('Session', 'RequestHandler');

    // The subject is a value, not a row of one table, so there is no
    // default model to bind. Panels load their own models as they land.
    public $uses = array();

    /**
     * The full profile page for one value.
     *
     * @param string $b64value
     * @return void
     */
    public function view($b64value = null)
    {
        $value = $this->decodeValue($b64value);
        $this->set('valueProfile', ValueProfileFixture::forValue($value));
        $this->set('valueB64', $b64value);
    }

    /**
     * Values reach this controller base64-encoded because they are
     * arbitrary strings in a URL segment. Both the standard and the
     * URL-safe alphabet are accepted — a raw `/` cannot survive a path
     * segment, so callers legitimately encode with `-_`.
     *
     * @param string $b64value
     * @return string
     * @throws NotFoundException
     */
    private function decodeValue($b64value)
    {
        if ($b64value === null || $b64value === '') {
            throw new NotFoundException(__('No value supplied.'));
        }
        $normalised = strtr($b64value, '-_', '+/');
        $value = base64_decode($normalised, true);
        if ($value === false || $value === '') {
            throw new NotFoundException(__('Invalid base64 encoding.'));
        }
        return $value;
    }
}
