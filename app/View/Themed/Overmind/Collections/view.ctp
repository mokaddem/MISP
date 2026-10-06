<?php
    $railCards = $railCards ?? [];
    echo $this->element('genericElementsBS5/Layout/view_layout',
    [
        'data' => $data,
        'tabs' => [
            [
                'id' => 'general',
                'title' => __('General'),
                'icon' => 'fas fa-info-circle',

                // Content
                'left' => [
                    'Collections/View/collection_general',
                ],
                'right' => array_merge([
                    'Collections/View/collection_actions',
                    'Collections/View/collection_analyst_data',
                    'Collections/View/collection_graphs',
                ], $this->RailCard->rail($railCards, [
                    'collection-inventory', 'collection-sources',
                ], 'general')),
            ],
            [
                'id' => 'elements',
                'title' => __('Elements'),
                'icon' => 'fas fa-file',

                // Content
                'left' => [
                    'Collections/View/collection_elements',
                ],
                'right' => $this->RailCard->rail($railCards, ['collection-inventory'], 'elements'),
            ]
        ]
    ]);
?>

