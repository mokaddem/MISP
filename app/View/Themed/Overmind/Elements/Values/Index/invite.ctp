<?php
/**
 * The invitation — what the region under the box says when it has
 * nothing else to say.
 *
 * It is an element rather than markup in `Values/index.ctp` because
 * the page needs it twice: once in the region, and once in a
 * `<template>` the script clones when the reader clears the page.
 * Two copies of a paragraph is two paragraphs to keep in step.
 */
?>
<div class="vi-invite">
    <h2><?= h(__('Open a value\'s profile.')) ?></h2>
    <p><?= h(__(
        'Type or paste one value and press Enter, or paste a list and'
        . ' get a row for each. Defanged input is refanged for you,'
        . ' because that is how values are stored. A value nothing'
        . ' here records is an answer rather than an error, and'
        . ' nothing you paste reaches the address bar.'
    )) ?></p>
</div>
