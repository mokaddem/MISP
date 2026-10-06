<?php
$data = $galaxy['Galaxy'];
$railCards = $railCards ?? [];

$this->set('headerTitle', $data['name']);

if (!empty($isMatrixGalaxy)) {
    echo $this->element('genericElements/assetLoader', [
        'css' => ['misp-matrix'],
        'js' => ['misp-matrix'],
    ]);
}

echo $this->element('genericElementsBS5/Layout/view_layout', [
    'data' => $data,
    'tabs' => array_values(array_filter([
        [
            'id' => 'general',
            'title' => __('General'),
            'icon' => 'fas fa-info-circle',
            'left' => [
                'Galaxies/View/galaxies_general',
            ],
            'right' => array_merge([
                'Galaxies/View/galaxies_actions',
                'Galaxies/View/galaxies_analyst_data',
            ], $this->RailCard->rail($railCards, [
                'galaxy-composition', 'galaxy-usage',
            ], 'general')),
        ],
        [
            'id' => 'clusters',
            'title' => __('Clusters'),
            'icon' => 'fas fa-diagram-project',
            'count' => $clusterCount ?? 0,
            'left' => [
                [
                    'ajax' => $baseurl . '/galaxy_clusters/index/' . h($data['id']),
                ],
            ],
            'right' => $this->RailCard->rail($railCards, ['galaxy-composition'], 'clusters'),
        ],
        empty($isMatrixGalaxy) ? null : [
            'id' => 'matrix',
            'title' => __('Matrix'),
            'icon' => 'fas fa-table-cells',
            'left' => [
                [
                    'ajax' => $baseurl . '/galaxies/viewMatrix/' . h($data['id']),
                ],
            ],
        ],
    ])),
]);
