<?php

/**
 * Scratch shell: seed analyst relationships for Value Profile phase 24
 * verification.
 *
 * The instance holds 120 `relationships`, of which **112 are
 * Object→Object and none is anchored to an attribute as its source** —
 * so the Relationships tab's third section had nothing to render for
 * any value on it. Two rows do point *at* an attribute, and both point
 * at a UUID that no longer exists, which is a useful case in its own
 * right and is left alone.
 *
 * This writes claims on `8.8.8.8`'s occurrences, shaped to exercise the
 * section rather than to look plausible: both directions, four target
 * kinds including one MISP cannot resolve, three organisations, and a
 * distribution spread that puts one claim outside a non-admin reader.
 *
 * Everything goes through `Relationship::save`, so the rows are written
 * the way MISP writes them — uuid, validation, `afterSave`.
 *
 * **Not part of the application.** It lives beside the phase document
 * that explains it rather than under `app/Console/Command/`, because
 * §14.8 of the contract declines to build a seeder into MISP and this
 * is verification scaffolding, not a feature. To run it, copy it into
 * `app/Console/Command/` for the duration:
 *
 *   cp prd/value-profile-live/24-relationships-seed.php \
 *      app/Console/Command/ValueRelationSeedShell.php
 *   app/Console/cake ValueRelationSeed run 8.8.8.8
 *   app/Console/cake ValueRelationSeed wipe
 *   rm app/Console/Command/ValueRelationSeedShell.php
 *
 * `wipe` removes only what `run` wrote, by the authors marker below.
 */
class ValueRelationSeedShell extends AppShell
{
    public $uses = array('Relationship', 'User', 'MispAttribute',
        'MispObject', 'Event', 'Organisation');

    /** So `wipe` can find exactly these rows and nothing else. */
    const MARKER = 'vp-phase-24-seed';

    /**
     * cake ValueRelationSeed run <value>
     */
    public function run()
    {
        $value = isset($this->args[0]) ? $this->args[0] : '8.8.8.8';
        $admin = $this->User->getAuthUser(1);
        Configure::write('CurrentUserId', $admin['id']);

        $occurrences = $this->MispAttribute->find('all', array(
            'recursive' => -1,
            'conditions' => array(
                'OR' => array(
                    'Attribute.value1' => $value,
                    'Attribute.value2' => $value,
                ),
                'Attribute.deleted' => 0,
            ),
            'fields' => array('Attribute.id', 'Attribute.uuid',
                'Attribute.event_id', 'Attribute.type'),
            'order' => array('Attribute.timestamp DESC'),
            'limit' => 6,
        ));
        if (count($occurrences) < 3) {
            $this->err(sprintf('Not enough occurrences of %s', $value));
            return;
        }

        // Targets that exist, so most claims resolve to a real label.
        $event = $this->Event->find('first', array(
            'recursive' => -1,
            'fields' => array('Event.id', 'Event.uuid', 'Event.info'),
            'conditions' => array(
                'Event.id' => $occurrences[0]['Attribute']['event_id'],
            ),
        ));
        $object = $this->MispObject->find('first', array(
            'recursive' => -1,
            'fields' => array('Object.id', 'Object.uuid', 'Object.name'),
            'conditions' => array('Object.deleted' => 0),
        ));
        $other = $this->MispAttribute->find('first', array(
            'recursive' => -1,
            'fields' => array('Attribute.uuid', 'Attribute.type',
                'Attribute.value1'),
            'conditions' => array(
                'Attribute.type' => 'domain',
                'Attribute.deleted' => 0,
            ),
        ));

        $claims = array(
            // Outbound to an event: the ordinary case.
            array($occurrences[0]['Attribute']['uuid'], 'Attribute',
                'Event', $event['Event']['uuid'], 'related-to', 1, 1),
            // Outbound to an object.
            array($occurrences[1]['Attribute']['uuid'], 'Attribute',
                'Object', $object['Object']['uuid'], 'derived-from',
                2, 9),
            // Outbound to an attribute.
            array($occurrences[2]['Attribute']['uuid'], 'Attribute',
                'Attribute', $other['Attribute']['uuid'], 'connects-to',
                3, 1),
            /*
             * Outbound to a galaxy cluster —
             * `Relationship::getRelatedElement` has no branch for one,
             * so this is the claim whose label cannot resolve and which
             * has to fall back to the UUID.
             */
            array($occurrences[0]['Attribute']['uuid'], 'Attribute',
                'GalaxyCluster', CakeText::uuid(), 'similar-to', 1, 9),
            /*
             * Inbound: something else claims a relationship *to* this
             * value. The near end is `related_object_uuid`, which
             * `afterFind` does not resolve for us.
             */
            array($event['Event']['uuid'], 'Event', 'Attribute',
                $occurrences[1]['Attribute']['uuid'], 'blocks', 1, 1),
            // Distribution 0 — invisible to anybody outside org 1.
            array($occurrences[3]['Attribute']['uuid'], 'Attribute',
                'Event', $event['Event']['uuid'], 'similar-to', 0, 1),
        );

        $written = 0;
        foreach ($claims as $claim) {
            list($sourceUuid, $sourceType, $targetType, $targetUuid,
                $type, $distribution, $orgId) = $claim;
            if ($sourceUuid === $targetUuid) {
                continue;
            }
            $org = $this->Organisation->find('first', array(
                'recursive' => -1,
                'fields' => array('Organisation.uuid',
                    'Organisation.name'),
                'conditions' => array('Organisation.id' => $orgId),
            ));
            $this->Relationship->create();
            $this->Relationship->current_user = $admin;
            $saved = $this->Relationship->save(array(
                'Relationship' => array(
                    'uuid' => CakeText::uuid(),
                    'object_uuid' => $sourceUuid,
                    'object_type' => $sourceType,
                    'related_object_uuid' => $targetUuid,
                    'related_object_type' => $targetType,
                    'relationship_type' => $type,
                    'distribution' => $distribution,
                    'sharing_group_id' => 0,
                    'authors' => self::MARKER,
                    'org_uuid' => $org['Organisation']['uuid'],
                    'orgc_uuid' => $org['Organisation']['uuid'],
                    'locked' => 0,
                ),
            ), array('validate' => false, 'callbacks' => false));
            if ($saved) {
                $written++;
            } else {
                $this->err('save failed: '
                    . json_encode($this->Relationship->validationErrors));
            }
        }
        $this->out(sprintf('%d claims written on %s', $written, $value));
    }

    /**
     * cake ValueRelationSeed wipe
     */
    public function wipe()
    {
        $count = $this->Relationship->deleteAll(
            array('Relationship.authors' => self::MARKER),
            false,
            false
        );
        $this->out($count ? 'seed removed' : 'nothing to remove');
    }
}
