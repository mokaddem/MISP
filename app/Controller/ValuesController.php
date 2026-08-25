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
        $profile = $this->profileFor($b64value);
        $this->set('valueProfile', $profile);
        // Re-encoded rather than passed through, so the panel URLs the page
        // builds are well-formed whichever alphabet the caller arrived with.
        $this->set('valueB64', self::encodeValue($profile['value']));
    }

    /**
     * The panels, one lazily-loaded fragment each.
     *
     * A panel per endpoint rather than one endpoint per page: each is a
     * different question of a different model, they answer at different
     * speeds, and a slow one should not hold up the rest. It also means
     * each panel's live implementation is one action and one element.
     *
     * @param string $b64value
     * @return void
     */
    public function viewOccurrences($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_occurrences');
    }

    public function viewContext($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_context');
    }

    public function viewAnalystPreview($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_analyst_preview');
    }

    public function viewVerdictCard($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_verdict_card');
    }

    public function viewSightings($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_sightings');
    }

    public function viewLifecycle($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_lifecycle');
    }

    public function viewExternal($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_external');
    }

    /**
     * The Occurrences tab: the facet rail and the table it counts.
     *
     * One endpoint rather than two, unlike the panels above. A facet
     * count and the rows it counts have to be computed from the same
     * fetch or they can disagree with each other, and two endpoints
     * against a moving attribute set is exactly how that happens.
     *
     * @param string $b64value
     * @return void
     */
    public function viewOccurrenceTable($b64value = null)
    {
        $this->renderPanel(
            $this->profileFor($b64value),
            'value_occurrence_table'
        );
    }

    /**
     * The Verdict tab body.
     *
     * A value whose signals contradict each other needs a different
     * layout, not a different colour: two opposed cases side by side
     * rather than one ledger. Which one is a property of the value, so
     * the disposition picks the template.
     *
     * @param string $b64value
     * @return void
     */
    public function viewVerdict($b64value = null)
    {
        $profile = $this->profileFor($b64value);
        $conflicted = ($profile['verdict']['disposition'] ?? null)
            === 'CONFLICTED';
        $this->renderPanel(
            $profile,
            $conflicted ? 'value_verdict_conflicted' : 'value_verdict'
        );
    }

    /**
     * The Verdict tab's right rail.
     *
     * One endpoint for the whole rail rather than one per card, unlike
     * the Overview rail: those cards are different questions of
     * different models, while every card here is a reading of the same
     * verdict computation. The element picks which cards apply.
     *
     * @param string $b64value
     * @return void
     */
    public function viewVerdictAside($b64value = null)
    {
        $this->renderPanel(
            $this->profileFor($b64value),
            'value_verdict_aside'
        );
    }

    /**
     * @param string $b64value
     * @return array
     */
    private function profileFor($b64value)
    {
        return ValueProfileFixture::forValue($this->decodeValue($b64value));
    }

    /**
     * Serve one panel: the fragment only, no layout and no chrome, since
     * it is injected into a page that already has both.
     *
     * @param array $profile
     * @param string $element Name under Elements/Values/View
     * @return void
     */
    private function renderPanel(array $profile, $element)
    {
        $this->set('valueProfile', $profile);
        $this->set('valueB64', self::encodeValue($profile['value']));
        $this->layout = false;
        $this->render('/Elements/Values/View/' . $element);
    }

    /**
     * @param string $value
     * @return string URL-safe base64, so a value containing `/` survives
     *                a path segment.
     */
    private static function encodeValue($value)
    {
        return strtr(base64_encode($value), '+/', '-_');
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
