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
                    'ObjectTemplates/View/objectTemplate_general',
                ],
                'right' => array_merge([
                    'ObjectTemplates/View/objectTemplate_actions',
                ], $this->RailCard->rail($railCards, [
                    'template-usage', 'template-inventory', 'template-versions', 'template-required-by',
                ], 'general')),
            ],
            [
                'id' => 'elements',
                'title' => __('Elements'),
                'icon' => 'fas fa-cube',

                // Content
                'left' => [
                    [
                        'ajax' => sprintf('/objectTemplateElements/viewElements/%s/all', h($data['ObjectTemplate']['id']))
                    ]
                ],
                'right' => $this->RailCard->rail($railCards, ['template-inventory'], 'elements'),
            ]
        ]
    ]);
?>

