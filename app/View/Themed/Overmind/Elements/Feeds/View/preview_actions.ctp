<?php

$ctx = $previewContext ?? [];
$fetchUrl = $ctx['fetchUrl'] ?? null;
if (empty($fetchUrl)) {
    return;
}

echo $this->element('genericElementsBS5/Cards/card_launch_bar', [
    'actions' => [
        [
            'url' => $fetchUrl,
            'icon' => 'fas fa-circle-arrow-down',
            'label' => __('Fetch this event'),
            'short' => __('Fetch'),
            'success' => true,
            'onclick' => sprintf(
                "event.preventDefault(); openModal('%s', 'md');",
                h($fetchUrl)
            ),
        ],
    ]
]);
