<?php
    $eventId  = $data['Event']['id'] ?? '';
    // Drawing references is only offered when the viewer may modify the
    // event (same ACL the ObjectReferences add endpoint enforces).
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

        <!-- Empty canvas (D11): why nothing is drawn, and where the event's
             contents are. Filled by pivot-explorer.js; shown only while the
             canvas holds no node. -->
        <div id="pe-empty" style="display:none;"></div>
    </div>
</div>

<style>
    #pe-empty {
        position: absolute; inset: 0; z-index: 5; pointer-events: none;
        display: flex; align-items: center; justify-content: center;
    }
    #pe-empty .pe-empty-box {
        pointer-events: auto; max-width: 28rem; padding: 1rem 1.25rem;
        border-radius: 8px; text-align: center; color: #e6e8ea;
        background: rgba(28, 33, 40, .92); border: 1px solid rgba(255, 255, 255, .14);
    }
</style>

