<?php

$eventId = h($data['Event']['id']);
$eventUuid = h($data['Event']['uuid'] ?? '');
$isPublished = (bool)$data['Event']['published'];

$mayModify = $this->Acl->canModifyEvent($data);
$canPublish = $this->Acl->canPublishEvent($data);
$canEdit = $isSiteAdmin || $mayModify;

$modal = function ($url, $size = null) {
    return sprintf(
        "event.preventDefault(); openModal('%s'%s);",
        $url,
        $size === null ? '' : ", '" . $size . "'"
    );
};

$actions = [];

if ($canEdit) {
    $actions[] = ['divider' => true, 'label' => __('Content')];

    $actions[] = [
        'url' => "$baseurl/events/edit/$eventId",
        'onclick' => $modal("$baseurl/events/edit/$eventId"),
        'icon' => 'fas fa-pen',
        'label' => __('Edit Event'),
        'pinned' => true,
        'short' => __('Edit'),
        'entity' => 'event'
    ];

    $actions[] = [
        'url' => "$baseurl/events/delete/$eventId",
        'onclick' => $modal("$baseurl/events/delete/$eventId", 'md'),
        'icon' => 'fas fa-trash',
        'label' => __('Delete Event'),
        'danger' => true
    ];

    $actions[] = [
        'url' => "$baseurl/attributes/add/$eventId",
        'onclick' => $modal("$baseurl/attributes/add/$eventId"),
        'icon' => 'misp-icon misp-icon-attribute misp-simple',
        'tour' => 'action-add-attribute',
        'label' => __('Add Attribute'),
        'add' => true,
        'short' => __('Attribute'),
        'entity' => 'attribute'
    ];

    $actions[] = [
        'url' => "$baseurl/objects/add/$eventId",
        'onclick' => $modal("$baseurl/objects/add/$eventId"),
        'icon' => 'misp-icon misp-icon-object misp-simple',
        'label' => __('Add Object'),
        'pinned' => true,
        'add' => true,
        'short' => __('Object'),
        'entity' => 'object'
    ];

    $actions[] = [
        'url' => "$baseurl/attributes/add_attachment/$eventId",
        'onclick' => $modal("$baseurl/attributes/add_attachment/$eventId"),
        'icon' => 'fas fa-paperclip',
        'label' => __('Add Attachment'),
        'pinned' => true,
        'add' => true,
        'short' => __('Attachment'),
        'entity' => 'attribute'
    ];

    $actions[] = [
        'url' => "$baseurl/event_reports/add/$eventId",
        'onclick' => $modal("$baseurl/event_reports/add/$eventId"),
        'icon' => 'misp-icon misp-icon-report misp-simple',
        'label' => __('Add Event Report'),
        'pinned' => true,
        'add' => true,
        'short' => __('Report'),
        'entity' => 'report'
    ];

    $actions[] = ['divider' => true, 'label' => __('Import & enrichment')];

    $actions[] = [
        'url' => "$baseurl/events/populateFrom/$eventId",
        'onclick' => $modal("$baseurl/events/populateFrom/$eventId"),
        'icon' => 'fas fa-sign-in-alt',
        'tour' => 'action-populate-from',
        'label' => __('Populate from'),
        'short' => __('Populate')
    ];

    if (Configure::read('Plugin.AI_services_enable') && $this->Acl->canAccess('events', 'aiActions')) {
        $actions[] = [
            'url' => "$baseurl/events/aiActions/$eventId",
            'onclick' => $modal("$baseurl/events/aiActions/$eventId", 'md'),
            'icon' => 'fas fa-robot',
            'label' => __('AI actions'),
            'short' => __('AI'),
            'entity' => 'enrichment'
        ];
    }

    $actions[] = [
        'url' => "$baseurl/events/merge/$eventId",
        'onclick' => $modal("$baseurl/events/merge/$eventId", 'md'),
        'icon' => 'fas fa-layer-group',
        'label' => __('Merge attributes from'),
        'short' => __('Merge'),
        'entity' => 'attribute'
    ];

    if (Configure::read('Plugin.Enrichment_services_enable')) {
        $actions[] = [
            'url' => "$baseurl/events/enrichEvent/$eventId",
            'onclick' => $modal("$baseurl/events/enrichEvent/$eventId"),
            'icon' => 'fas fa-wand-magic-sparkles',
            'label' => __('Enrich Event'),
            'short' => __('Enrich'),
            'entity' => 'enrichment'
        ];
    }
}

$actions[] = ['divider' => true, 'label' => __('Share')];

if ($this->Acl->canAccess('analystGraphs', 'addNodes')) {
    $actions[] = [
        'url' => '#',
        'icon' => 'fas fa-circle-nodes',
        'label' => __('Add to graph'),
        'short' => __('Graph'),
        'entity' => 'event',
        'attributes' => ['data-intel-graph-add' => json_encode([[
            'type' => 'Event', 'uuid' => $data['Event']['uuid'], 'label' => $data['Event']['info'],
        ]])],
    ];
}

if (!$isPublished && ($isSiteAdmin || ($mayModify && $canPublish))) {
    $actions[] = [
        'url' => "",
        'onclick' => $modal("$baseurl/events/publish/$eventId", 'md'),
        'icon' => 'fas fa-upload',
        'tour' => 'action-publish',
        'label' => __('Publish Event'),
        'primary' => true,
        'short' => __('Publish'),
        'success' => true
    ];
} else if ($isPublished && ($isSiteAdmin || ($mayModify && $canPublish))) {
    $actions[] = [
        'url' => "",
        'onclick' => $modal("$baseurl/events/unpublish/$eventId", 'md'),
        'icon' => 'fas fa-eye-slash',
        'tour' => 'action-unpublish',
        'label' => __('Unpublish Event'),
        'primary' => true,
        'short' => __('Unpublish'),
        'warning' => true
    ];
}

if (!empty($data['Orgc']['local'])) {
    $actions[] = [
        'url' => "$baseurl/events/contact/$eventId",
        'onclick' => $modal("$baseurl/events/contact/$eventId", 'md'),
        'icon' => 'fas fa-envelope',
        'label' => __('Contact Reporter'),
        'short' => __('Contact')
    ];
}

$actions[] = [
    'url' => "$baseurl/events/exportChoice/$eventId",
    'onclick' => $modal("$baseurl/events/exportChoice/$eventId", 'md'),
    'icon' => 'fas fa-download',
    'label' => __('Download as'),
    'short' => __('Download')
];






if ($isPublished && !empty($me['Role']['perm_sighting'])) {
    $actions[] = [
        'url' => "",
        'onclick' => $modal("$baseurl/events/publishSightings/$eventId", 'md'),
        'icon' => 'fas fa-eye',
        'label' => __('Publish Sightings'),
        'short' => __('Sightings'),
        'entity' => 'sighting'
    ];
}


if (Configure::read('MISP.delegation')) {
    $pendingDelegation = empty($delegationRequest) ? null : $delegationRequest;

    if ($pendingDelegation === null) {
        $onlyMyOrg = (int)($data['Event']['distribution'] ?? -1) === 0;
        if ((Configure::read('MISP.unpublishedprivate') || $onlyMyOrg)
            && ($isSiteAdmin || !empty($isAclDelegate))
        ) {
            $actions[] = [
                'url' => "$baseurl/event_delegations/delegateEvent/$eventId",
                'onclick' => $modal("$baseurl/event_delegations/delegateEvent/$eventId"),
                'icon' => 'fas fa-handshake',
                'label' => __('Delegate Publishing'),
                'short' => __('Delegate')
            ];
        }
    } else {
        $delegationId = h($pendingDelegation['EventDelegation']['id']);
        $myOrg = $me['org_id'] ?? null;
        $isTarget = $myOrg !== null && $myOrg == $pendingDelegation['EventDelegation']['org_id'];
        $isRequester = $myOrg !== null && $myOrg == $pendingDelegation['EventDelegation']['requester_org_id'];

        if ($isSiteAdmin || (!empty($isAclPublish) && ($isTarget || $isRequester))) {
            if ($isSiteAdmin || (!empty($isAclPublish) && $isTarget)) {
                $actions[] = [
                    'url' => "$baseurl/event_delegations/acceptDelegation/$delegationId",
                    'onclick' => $modal("$baseurl/event_delegations/acceptDelegation/$delegationId", 'md'),
                    'icon' => 'fas fa-handshake',
                    'label' => __('Accept Delegation Request'),
                    'short' => __('Accept'),
                    'success' => true
                ];
            }
            $actions[] = [
                'url' => "$baseurl/event_delegations/deleteDelegation/$delegationId",
                'onclick' => $modal("$baseurl/event_delegations/deleteDelegation/$delegationId", 'md'),
                'icon' => 'fas fa-handshake-slash',
                'label' => __('Discard Delegation Request'),
                'short' => __('Discard'),
                'warning' => true
            ];
        }
    }
}

if ($isSiteAdmin) {
    $actions[] = ['divider' => true, 'label' => __('Maintenance')];

    if (Configure::read('Plugin.Workflow_enable')) {
        $actions[] = [
            'url' => "",
            'onclick' => $modal("$baseurl/events/runWorkflow/$eventId"),
            'icon' => 'fas fa-diagram-project',
            'label' => __('Run Ad-Hoc Workflow'),
            'short' => __('Workflow')
        ];
    }

    $actions[] = [
        'url' => "",
        'onclick' => $modal("$baseurl/events/recorrelateEvent/$eventId", 'md'),
        'icon' => 'fas fa-arrows-rotate',
        'label' => __('Recorrelate Event'),
        'short' => __('Recorrelate'),
        'entity' => 'correlation'
    ];
}

echo $this->element('genericElementsBS5/Cards/card_launch_bar', [
    'actions' => $actions,
    'status' => ['published' => $isPublished, 'readOnly' => !$canEdit],
    'maxTiles' => 4,
]);
