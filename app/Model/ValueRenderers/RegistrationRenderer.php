<?php

/**
 * When a domain was registered, and by whom through whom.
 *
 * **Nothing emits this today, and the reason is worth stating.** The
 * module that fetches registration data queries a whois server over a
 * socket and sends back the raw text it got — so the data is fetched,
 * and then flattened into prose that no widget can read. Filling in
 * `text` alone would be trivial and would give no age, which is the
 * one fact every profile that ranks this shape ranks it for; the
 * dates, the registrar and the nameservers mean either parsing free
 * text or asking RDAP, which returns them as JSON.
 *
 * Three other modules do emit this template as a keyed side result, so
 * an instance with those keys sees the widget today.
 *
 * **A `whois` object carrying only `text` is declined.** It would draw
 * a widget whose one fact is *a registration record exists*, and the
 * chip it replaces says that already.
 */
class RegistrationRenderer extends ValueRendererBase
{
    public $id = 'registration';

    public $templates = array('whois');

    public $compact = 'Values/Renderers/registration_compact';

    public $full = 'Values/Renderers/registration_full';

    public $producer = self::PRODUCER_CONVERSION;

    const REGISTRANT = array(
        'registrant-name' => 'name',
        'registrant-org' => 'organisation',
        'registrant-email' => 'email',
        'registrant-phone' => 'phone',
    );

    public function __construct()
    {
        $this->description = __('When a domain was registered, its'
            . ' registrar and its nameservers.');
        $this->producer_note = __('The module that answers this sends'
            . ' the raw record as text; it emits no object yet.');
    }

    public function matches(array $objects, array $attributes)
    {
        foreach ($objects as $object) {
            if ($this->recordOf($object) !== null) {
                return true;
            }
        }
        return false;
    }

    public function prepare(array $objects, array $attributes)
    {
        $records = array();
        foreach ($objects as $object) {
            $record = $this->recordOf($object);
            if ($record !== null) {
                $records[] = $record;
            }
        }
        usort($records, function ($a, $b) {
            return ($b['ran_at'] ?? 0) - ($a['ran_at'] ?? 0);
        });
        return array(
            'records' => $records,
            'headline' => empty($records) ? null : $records[0],
            'sources' => $this->sources($objects),
        );
    }

    /**
     * @param array $object
     * @return array|null
     */
    private function recordOf(array $object)
    {
        $created = $this->stamp($this->value($object, 'creation-date'));
        $registrar = $this->value($object, 'registrar');
        $nameservers = $this->values($object, 'nameserver');
        if ($created === null && $registrar === null
            && empty($nameservers)
        ) {
            return null;
        }
        $registrant = array();
        foreach (self::REGISTRANT as $relation => $field) {
            $value = $this->value($object, $relation);
            if ($value !== null) {
                $registrant[$field] = $value;
            }
        }
        $expires = $this->stamp(
            $this->value($object, 'expiration-date')
        );
        return array(
            'domain' => $this->value($object, 'domain'),
            'created' => $created,
            /*
             * The age in days, computed here so the compact widget and
             * the full rendering cannot round it differently — and
             * because the full rendering states it against the
             * profile's own threshold, which needs a number rather
             * than a date.
             */
            'age_days' => $created === null
                ? null
                : (int)floor((time() - $created) / 86400),
            'expires' => $expires,
            'expires_in_days' => $expires === null
                ? null
                : (int)floor(($expires - time()) / 86400),
            'modified' => $this->stamp(
                $this->value($object, 'modification-date')
            ),
            'registrar' => $registrar,
            'nameservers' => $nameservers,
            'registrant' => $registrant,
            'text' => $this->value($object, 'text'),
            'module' => $object['module'] ?? null,
            'ran_at' => $object['ran_at'] ?? null,
        );
    }
}
