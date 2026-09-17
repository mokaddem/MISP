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
    <h2><?= h(__('Look up a value')) ?></h2>
    <p><?= h(__(
        'Enter a single value to open its Value Profile, or paste a'
        . ' list to get one row per value. Defanged input such as'
        . ' hxxp:// or 1.2.3[.]4 is refanged automatically. What you'
        . ' paste is never added to the URL, so it stays out of your'
        . ' browser history.'
    )) ?></p>
</div>
