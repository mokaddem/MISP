<?php
/**
 * The enrichment store, in one sentence.
 *
 * The second occupant of the conditions strip, beside the profile in
 * force and before the method note. It answers the question the run
 * store generates and nothing else on any page answers
 * (`value-index.md` §7.7, D25): why a value's Enrichment tab replied
 * instantly, and why pressing *run* on a fresh answer changes nothing.
 * Both have one cause — this organisation already holds an answer and
 * it is younger than the reuse window — and the two numbers here are
 * that cause stated.
 *
 * **A count and a window, and nothing else.** The survey asked for
 * *recently enriched values*; that is withdrawn, because a list of
 * what an organisation recently enriched is a list of what it is
 * currently investigating. No value, no module name and no timestamp
 * reaches this line by construction rather than by filtering — the
 * model hands over two integers.
 *
 * **Your organisation's, said in those words.** The store is
 * org-scoped, so a reader comparing this figure with a colleague's
 * finds the same one and a reader in another organisation a different
 * one. A sentence saying *this instance* would be both wrong and a
 * hint about somebody else's activity.
 *
 * **An empty store is a sentence, not a zero.** *0 stored enrichment
 * answers* reads as a fault on a page whose whole business is telling
 * an honest absence from a broken one. The window is still said,
 * because it is what will happen to the first answer.
 *
 * @var array{count: int, max_age_hours: int} $store
 */
$hours = (int)$store['max_age_hours'];
/*
 * One clause, shared by both sentences: what the window says is true
 * of an answer that exists and of one that does not exist yet.
 */
$window = sprintf(
    h(__n(
        'reused for %s hour before a module is asked again',
        'reused for %s hours before a module is asked again',
        $hours
    )),
    '<b>' . h(number_format($hours)) . '</b>'
);

if ($store['count'] === 0) {
    $line = sprintf(
        h(__(
            'Your organisation has no stored enrichment answers yet;'
            . ' one would be %s.'
        )),
        $window
    );
} else {
    $answers = '<b>' . h(number_format($store['count'])) . '</b> '
        . h(__n(
            'stored enrichment answer',
            'stored enrichment answers',
            $store['count']
        ));
    $line = sprintf(
        h(__('Your organisation has %s, %s.')),
        $answers,
        $window
    );
}
?>
<span class="vi-store"><?= $line ?></span>
