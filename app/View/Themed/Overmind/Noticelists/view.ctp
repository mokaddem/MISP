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
                    'Noticelists/View/noticelists_general',
                    'Noticelists/View/noticelists_values',
                ],
                'right' => array_merge([
                    'Noticelists/View/noticelists_actions',
                ], $this->RailCard->rail($railCards, ['noticelist-fires'], 'general')),
            ],
        ]
    ]);
?>

