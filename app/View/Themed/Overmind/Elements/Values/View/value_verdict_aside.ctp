<?php
/**
 * The Verdict tab's right rail.
 *
 * The split between this and the main column is by what each section
 * needs, not by importance: the evidence — the ledger grid, the
 * occurrence tables, the two cases side by side — needs horizontal
 * room and stays wide. What sits here is either a self-contained
 * summary or a reference fact, and it is more useful beside the
 * evidence than below it.
 *
 * Which cards appear is a property of the value, the same way the main
 * column's layout is. A conflicted value has no score to compose, and
 * an agreeing one has no warninglist hit to explain.
 *
 * Lazily loaded into `.ajax-card` from
 * ValuesController::viewVerdictAside.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$verdict = $valueProfile['verdict'];
$conflicted = ($verdict['disposition'] ?? null) === 'CONFLICTED';

$uid = 'vp' . substr(md5($valueProfile['value'] . '-aside'), 0, 8);

/*
 * An UNKNOWN value has none of these, so the rail renders nothing
 * rather than a column of empty states — the main column already says
 * that no signal was found, and saying it four more times narrower
 * would not make it truer.
 */
/*
 * Resolve it leads the conflicted rail. It is the only card on the page
 * that asks the reader to do something, and the argument beside it
 * exists to inform that choice — so it sits where the eye lands first,
 * not below three cards of evidence.
 */
$cards = $conflicted
    ? array(
        array('element' => 'value_verdict_resolve'),
        array('element' => 'value_verdict_opinions'),
        array(
            'element' => 'value_verdict_curves',
            'chart' => 'curves',
            'title' => __('The two cases over time'),
        ),
        array('element' => 'value_verdict_not_counted'),
    )
    : array(
        array('element' => 'value_verdict_composition'),
        array(
            'element' => 'value_verdict_curves',
            'chart' => 'curves',
            'title' => __('Verdict over time'),
        ),
        array('element' => 'value_verdict_not_counted'),
        array('element' => 'value_verdict_changers'),
    );

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

foreach ($cards as $card) {
    $params = array(
        'valueProfile' => $valueProfile,
        'noWrites' => $noWrites,
    );
    if (!empty($card['chart'])) {
        $params['chartId'] = $uid . '-' . $card['chart'];
    }
    if (!empty($card['title'])) {
        $params['cardTitle'] = $card['title'];
    }
    echo $this->element(
        'Values/View/' . $card['element'],
        $params
    );
}
