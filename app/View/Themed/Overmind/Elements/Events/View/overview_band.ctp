<?php
$event = $data['Event'] ?? [];
$orgc = $data['Orgc'] ?? [];
$org = $data['Org'] ?? [];
$sg = $data['SharingGroup'] ?? [];
$distribution = (int)($event['distribution'] ?? 0);
$markings = $overviewMarkings ?? ['declared' => [], 'present' => [], 'absent' => []];

$meta = [];
$meta[] = sprintf(
    '<span class="eo-id">#%s</span>'
    . '<span class="eo-uuid font-monospace" title="%s">%s'
    . '<button type="button" class="eo-copy" onclick="copyToClipboard(this, \'%s\')" title="%s" aria-label="%s">'
    . '<i class="fas fa-copy"></i></button></span>',
    h($event['id'] ?? ''),
    h($event['uuid'] ?? ''),
    h(substr($event['uuid'] ?? '', 0, 8)),
    h($event['uuid'] ?? ''),
    __('Copy UUID'),
    __('Copy UUID')
);
if (!empty($event['date'])) {
    $meta[] = '<span title="' . __('Event date') . '"><i class="fas fa-calendar-day me-1 opacity-50"></i>' . h($event['date']) . '</span>';
}
if (!empty($event['timestamp'])) {
    $meta[] = '<span title="' . __('Last modified') . '"><i class="fas fa-edit me-1 opacity-50"></i>' . $this->Time->time($event['timestamp']) . '</span>';
}
if (!empty($event['published']) && !empty($event['publish_timestamp'])) {
    $meta[] = '<span title="' . __('Last published') . '"><i class="fas fa-paper-plane me-1 opacity-50"></i>' . $this->Time->time($event['publish_timestamp']) . '</span>';
}
if (!empty($event['protected'])) {
    $meta[] = '<span class="eo-flag" title="' . __('Protected events can only be updated by signatories') . '"><i class="fas fa-shield-alt"></i>' . __('Protected') . '</span>';
}
if (!empty($event['disable_correlation'])) {
    $meta[] = '<span class="eo-flag"><i class="fas fa-unlink"></i>' . __('Correlation disabled') . '</span>';
}
if (!empty($event['Extends']) && is_array($event['Extends'])) {
    $meta[] = sprintf(
        '<a class="eo-flag eo-flag-link" href="%s/events/view2/%s"><i class="fas fa-code-branch"></i>%s</a>',
        h($baseurl),
        h($event['Extends']['id']),
        h(__('Extends: %s', $event['Extends']['info']))
    );
}
if (!empty($event['ExtendedBy'])) {
    $meta[] = '<span class="eo-flag"><i class="fas fa-code-branch"></i>'
        . h(__n('Extended by %s event', 'Extended by %s events', count($event['ExtendedBy']), count($event['ExtendedBy'])))
        . '</span>';
}
$this->set('headerDescription', '<span class="eo-meta">' . implode('', $meta) . '</span>');

$logo = $this->OrgImg->getOrgLogoV2($orgc, 28);
$ownerDiffers = !empty($org['id']) && (int)$org['id'] !== (int)($orgc['id'] ?? 0);
?>
<div class="card shadow-sm mb-3 eo-band" data-tour="event-general">
    <div class="eo-band-cell eo-band-org">
        <div class="eo-org-tile">
            <?= $logo !== '' ? $logo : '<i class="misp-icon misp-icon-organisation misp-simple"></i>' ?>
        </div>
        <div class="min-w-0">
            <a class="eo-org-name text-truncate" href="<?= h($baseurl . '/organisations/view/' . ($orgc['id'] ?? '')) ?>"><?= h($orgc['name'] ?? '') ?></a>
            <?php if ($ownerDiffers): ?>
                <div class="eo-org-held text-truncate">
                    <?= __(
                        'held here by %s',
                        sprintf('<a href="%s">%s</a>', h($baseurl . '/organisations/view/' . (int)$org['id']), h($org['name'] ?? ''))
                    ) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($data['User']['email'])): ?>
                <div class="eo-org-held text-truncate" title="<?= h(__('Event creator')) ?>">
                    <i class="misp-icon misp-icon-user1 misp-simple"></i><?= h($data['User']['email']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="eo-band-cell eo-band-dist">
        <div class="eo-band-label"><?= __('Distribution') ?></div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?= $this->element('genericElementsBS5/Badges/distribution', [
                'distribution' => $distribution,
                'full' => true,
            ]) ?>
            <?php if ($distribution === 4 && !empty($sg)): ?>
                <a class="eo-sg text-truncate" href="<?= h($baseurl . '/sharingGroups/view/' . ($sg['id'] ?? '')) ?>">
                    <span class="misp-icon misp-icon-sharing-group misp-simple"></span><?= h($sg['name'] ?? '') ?>
                </a>
                <?php if ($sharingGroupOrgCount !== null): ?>
                    <span class="eo-muted"><?= h(__n('%s org', '%s orgs', $sharingGroupOrgCount, $sharingGroupOrgCount)) ?></span>
                <?php endif; ?>
            <?php endif; ?>
            <a href="#" class="btn btn-sm btn-outline-secondary eo-narrower d-none" data-eo-narrower
               data-eo-label-one="<?= h(__('%s indicator shared more narrowly')) ?>"
               data-eo-label-many="<?= h(__('%s indicators shared more narrowly')) ?>"></a>
        </div>
    </div>

    <?php if (!empty($markings['declared'])): ?>
        <div class="eo-band-cell eo-band-marking">
            <div class="eo-band-label"><?= __('Marking') ?></div>
            <div class="d-flex align-items-center gap-1 flex-wrap">
                <?php foreach ($markings['present'] as $item): ?>
                    <?= $this->TagChip->chip($item['tag'], ['searchUrl' => '']) ?>
                <?php endforeach; ?>
                <?php foreach ($markings['absent'] as $missing): ?>
                    <span class="eo-absent" title="<?= h(__('Pinned by your analyst profile, not on this event')) ?>">
                        <i class="fas fa-thumbtack"></i><?= h($missing['key']) ?>
                    </span>
                <?php endforeach; ?>
                <?php if (empty($markings['present']) && empty($markings['absent'])): ?>
                    <span class="eo-muted"><?= __('None') ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
