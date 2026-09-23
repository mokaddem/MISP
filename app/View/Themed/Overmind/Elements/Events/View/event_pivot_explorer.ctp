<?php
    $eventId  = $data['Event']['id'] ?? '';
    // Editor chrome is only emitted when the viewer may modify the event
    // (same ACL the ObjectReferences add endpoint enforces server-side).
    $canEdit  = $this->Acl->canModifyEvent($data);

    // Behaviour lives in webroot/js/pivot-explorer.js, which reads its
    // config from the data-pe-* attributes on #pe-card below.
    echo $this->element('genericElements/assetLoader', [
        'js'  => ['pivotick.iife', 'pivot-explorer'],
        'css' => ['pivotick'],
    ]);
?>

<div class="card shadow-sm mb-3" id="pe-card"
     data-pe-event-id="<?= h($eventId) ?>"
     data-pe-baseurl="<?= h($baseurl ?? '') ?>"
     data-pe-can-edit="<?= $canEdit ? '1' : '0' ?>"
     data-pe-lib-missing="<?= h(__('Graph library failed to load.')) ?>"
     data-pe-load-failed="<?= h(__('Failed to load event graph.')) ?>">

    <!-- Resolution statement (D12): which levels the seed took and what it
         left out. Filled and revealed by pivot-explorer.js once the graph is
         built; hidden when there is nothing to say. -->
    <div class="card-header bg-transparent border-0 py-1 px-2 small text-muted"
         id="pe-resolution" style="display:none;"></div>

    <!-- BODY -->
    <div class="position-relative" id="pe-stage">

        <!-- Loader -->
        <div id="pivot-explorer-loader" class="text-center py-5 text-muted">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            <?= h(__('Building graph…')) ?>
        </div>

        <!-- Graph container (revealed after fetch) -->
        <div id="pivot-explorer-graph"
             style="width:100%;height:72vh;min-height:480px;display:none;"></div>
    </div>
</div>

<?php if ($canEdit): ?>
<style>
    /* The tray is rendered inside pivotick's sidebar (extraPanel); these style
       its contents. */
    #pe-card .pe-tray-body { display: flex; flex-direction: column; gap: .5rem; font-size: .82rem; }
    #pe-card .pe-count { font-size: .74rem; opacity: .75; }
    #pe-card .pe-count .pe-badge {
        margin-left: .25rem; padding: 0 .45rem; border-radius: 999px;
        background: #f39a1f; color: #1a1d21; font-size: .72rem; font-weight: 700;
    }
    #pe-card .pe-filter {
        width: 100%; padding: .35rem .5rem; border-radius: 6px;
        border: 1px solid rgba(128,128,128,.35);
        background: rgba(128,128,128,.08); color: inherit; font-size: .82rem;
    }
    #pe-card .pe-tray-list { overflow-y: auto; max-height: 46vh; }
    #pe-card .pe-group-label {
        font-size: .68rem; text-transform: uppercase; letter-spacing: .04em;
        opacity: .5; margin: .5rem 0 .25rem;
    }
    #pe-card .pe-chip {
        display: flex; flex-direction: column; gap: 1px;
        padding: .35rem .5rem; margin-bottom: .3rem; border-radius: 6px;
        background: rgba(243,154,31,.12); border: 1px solid rgba(243,154,31,.35);
        cursor: grab; font-size: .8rem;
    }
    #pe-card .pe-chip:hover { background: rgba(243,154,31,.22); }
    #pe-card .pe-chip:active { cursor: grabbing; }
    #pe-card .pe-chip.pe-chip-staged { opacity: .4; cursor: default; }
    #pe-card .pe-chip-val { font-weight: 600; word-break: break-all; }
    #pe-card .pe-chip-meta { font-size: .68rem; opacity: .6; }
    /* Objects are blue (matching their canvas node), attributes orange. */
    #pe-card .pe-chip-object {
        background: rgba(66,139,202,.14); border-color: rgba(66,139,202,.45);
    }
    #pe-card .pe-chip-object:hover { background: rgba(66,139,202,.24); }
    #pe-card .pe-empty { font-size: .78rem; opacity: .5; padding: .5rem 0; }

    #pe-stage.pe-drop-active #pivot-explorer-graph { outline: 2px dashed #428bca; outline-offset: -4px; }

    /* Drag ghost — follows the cursor (appended to <body>, so unscoped). */
    .pe-ghost {
        position: fixed; z-index: 9999; pointer-events: none;
        padding: .25rem .55rem; border-radius: 6px;
        font-size: .76rem; font-weight: 600; white-space: nowrap;
        box-shadow: 0 4px 14px rgba(0,0,0,.4);
    }
    .pe-ghost-attribute { background: #f39a1f; color: #1a1d21; }
    .pe-ghost-object    { background: #428bca; color: #fff; }
</style>
<?php endif; ?>
