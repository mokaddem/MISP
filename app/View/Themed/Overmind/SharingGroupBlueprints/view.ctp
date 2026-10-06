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
                    'SharingGroupBlueprints/View/sharingGroupBlueprints_general',
                ],
                'right' => array_merge([
                    'SharingGroupBlueprints/View/sharingGroupBlueprints_actions',
                ], $this->RailCard->rail($railCards, ['blueprint-pending', 'blueprint-result'], 'general')),
            ],
            [
                'id' => 'organisations',
                'title' => __('Organisations'),
                'icon' => 'fas fa-building-user',
                //'count' => $tag_count ?? 0,

                // Content
                'left' => [
                    [
                        'ajax' => sprintf('%s/SharingGroupBlueprints/viewOrgs/%s', $baseurl, h($data['SharingGroupBlueprint']['id']))
                    ]
                ],
                'right' => $this->RailCard->rail($railCards, ['blueprint-pending'], 'organisations'),
            ]
        ]
    ]);
?>

