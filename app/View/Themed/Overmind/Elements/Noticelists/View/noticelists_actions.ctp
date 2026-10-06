<?php
$noticelistId = h($data['Noticelist']['id']);
$enabled = $data['Noticelist']['enabled'];

$actions = [
    [
        'url' => "$baseurl/noticelists/preview_entries/$noticelistId",
        'icon' => 'fas fa-eye',
        'label' => __('Preview entries'),
    ],
];

if ($isSiteAdmin) {
    $actions[] = [
        'type' => 'post',
        'url' => "$baseurl/noticelists/enableNoticelist/$noticelistId" . ($enabled ? '' : '/1'),
        'id' => $noticelistId,
        'icon' => $enabled ? 'fas fa-stop' : 'fas fa-play',
        'label' => $enabled ? __('Disable Noticelist') : __('Enable Noticelist'),
        'class' => $enabled ? 'text-warning' : 'text-success',
    ];
    $actions[] = [
        'url' => "$baseurl/noticelists/delete/$noticelistId",
        'onclick' => "event.preventDefault(); openModal('$baseurl/noticelists/delete/$noticelistId', 'md');",
        'icon' => 'fas fa-trash',
        'label' => __('Delete Noticelist'),
        'danger' => true,
    ];
}

echo $this->element('genericElementsBS5/Cards/card_actions', [
    'actions' => $actions,
]);
