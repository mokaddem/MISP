<?php
$m = $modelSelection ?? 'Note';
$record = $data[$m] ?? [];
$recordId = h($record['id'] ?? '');

$actions = [];
if (!empty($mayModify)) {
    $actions[] = [
        'url' => "$baseurl/analystData/edit/$m/$recordId",
        'onclick' => "event.preventDefault(); openModal('$baseurl/analystData/edit/$m/$recordId');",
        'icon' => 'fas fa-pen',
        'label' => __('Edit %s', $m),
    ];
    $actions[] = [
        'url' => "$baseurl/analystData/delete/$m/$recordId",
        'onclick' => "event.preventDefault(); openModal('$baseurl/analystData/delete/$m/$recordId', 'md');",
        'icon' => 'fas fa-trash',
        'label' => __('Delete %s', $m),
        'danger' => true,
    ];
}
if (!empty($actions)) {
    echo $this->element('genericElementsBS5/Cards/card_actions', [
        'actions' => $actions,
    ]);
}

echo $this->element('AnalystData/add_controls', [
    'objectType' => $m,
    'objectUuid' => $record['uuid'] ?? '',
    'showView' => false,
]);
