<?php

$this->set('headerTitle', $sg['SharingGroup']['name']);
$railCards = $railCards ?? [];

echo $this->element('genericElementsBS5/Layout/view_layout',
    [
        'data' => $sg,
        'tabs' => [
            [
                'id' => 'general',
                'title' => __('General'),
                'icon' => 'fas fa-info-circle',

                // Content
                'left' => [
                    'SharingGroups/View/sharingGroups_general',
                ],
                'right' => array_merge([
                    'SharingGroups/View/sharingGroups_actions',
                    'SharingGroups/View/sharingGroups_analyst_data',
                ], $this->RailCard->rail($railCards, [
                    'sg-inventory', 'sg-reach', 'sg-blueprint',
                ], 'general')),
            ]
        ]
    ]);
?>

