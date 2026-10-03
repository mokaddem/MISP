<?php
App::uses('AppHelper', 'View/Helper');
App::uses('TagChipTool', 'Tools');

/**
 * Renders tags as chips: one line while the name fits, the leaf dropping to a
 * second row only when it doesn't. Three or more tags of a collection sharing
 * a namespace and predicate chain fuse into a block that prints the prefix
 * once. Colour means taxonomy: a taxonomy whose declared palette tells its
 * values apart (TLP, PAP) keeps its colours, every other one gets a hue
 * derived from its namespace. Styles live in css/tag-chips.css.
 */
class TagChipHelper extends AppHelper
{
    /** @var string[]|null */
    private $semantic = null;

    /**
     * @param array $tags rows shaped as rich_tag accepts them
     * @param array $options
     *   scope, id          what the tags hang off, for the edit affordances
     *   searchUrl          link prefix, '' for no link
     *   href               callable(tag row): ?string, overrides searchUrl
     *   prefix             callable(tag row): string, HTML ahead of the chip
     *   canModifyAll       may remove any tag
     *   canModifyLocal     may remove local tags
     *   display            'full' (default), 'leaf' or 'swatch'
     *   group              fuse shared prefixes into blocks (default true)
     *   minGroup           members before a chain earns a block (2)
     *   wideAt             members before a block takes its own line (8)
     *   track              column track width in px for a wide block
     *   budget             one-line width budget in px (300)
     *   class              extra class on the root
     * @return string
     */
    public function collection(array $tags, array $options = [])
    {
        $options += [
            'display' => 'full',
            'group' => true,
            'minGroup' => 2,
            'wideAt' => 8,
            'track' => null,
            'class' => '',
        ];
        $rows = $this->normalise($tags);
        if (empty($rows)) {
            return '';
        }
        $grouping = $options['group'] && $options['display'] === 'full';

        $groups = [];
        foreach ($rows as $row) {
            $p = $row['parsed'];
            $key = $p['namespace'] === null || !$grouping
                ? "\0" . count($groups)
                : mb_strtolower($p['namespace']) . "\1" . implode("\1", $p['above']);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'namespace' => $p['namespace'],
                    'above' => $p['above'],
                    'rows' => [],
                ];
            }
            $groups[$key]['rows'][] = $row;
        }

        $body = '';
        foreach ($groups as $group) {
            if ($grouping && $group['namespace'] !== null && count($group['rows']) >= $options['minGroup']) {
                $body .= $this->renderGroup($group, $options);
            } else {
                foreach ($group['rows'] as $row) {
                    $body .= $this->renderChip($row, 'flow', $options);
                }
            }
        }
        return $this->wrap($body, $options);
    }

    /**
     * A single tag, never grouped.
     *
     * @param array $tag
     * @param array $options as collection()
     * @return string
     */
    public function chip(array $tag, array $options = [])
    {
        $options += ['display' => 'full', 'class' => ''];
        $rows = $this->normalise([$tag]);
        if (empty($rows)) {
            return '';
        }
        return $this->wrap($this->renderChip($rows[0], 'flow', $options), $options);
    }

    /**
     * Maps the legacy tag_display_style (0 colour only, 1 full name, 2 value
     * only) onto a display mode.
     *
     * @param mixed $style
     * @return string
     */
    public function displayFromStyle($style)
    {
        if ($style === 0 || $style === '0') {
            return 'swatch';
        }
        if ((int)$style === 2) {
            return 'leaf';
        }
        return 'full';
    }

    /**
     * @return string[] lowercased namespaces whose declared colours are kept
     */
    public function semanticNamespaces()
    {
        if ($this->semantic === null) {
            try {
                $this->semantic = ClassRegistry::init('Taxonomy')->semanticPaletteNamespaces();
            } catch (Exception $e) {
                $this->semantic = [];
            }
        }
        return $this->semantic;
    }

    /**
     * Hue and saturation for a tag, as CSS custom property values, and the
     * declared colour when the tag's taxonomy keeps it.
     *
     * @param array $parsed
     * @param string $colour
     * @return array{0: int, 1: string, 2: ?string}
     */
    private function hueOf(array $parsed, $colour)
    {
        if ($parsed['namespace'] === null) {
            return [0, '0%', null];
        }
        if (in_array(mb_strtolower($parsed['namespace']), $this->semanticNamespaces(), true)) {
            // tlp:clear is #ffffff: no hue to keep, so the chip stays neutral
            $declared = TagChipTool::hueSat($colour);
            if ($declared['s'] < 15) {
                return [$declared['h'], '0%', null];
            }
            return [$declared['h'], '62%', strtolower($colour)];
        }
        return [TagChipTool::hue($parsed['namespace']), '62%', null];
    }

    private function normalise(array $tags)
    {
        $rows = [];
        foreach ($tags as $tag) {
            if (empty($tag['Tag'])) {
                $tag['Tag'] = $tag;
            }
            if (!isset($tag['Tag']['name']) || trim($tag['Tag']['name']) === '') {
                continue;
            }
            if (empty($tag['Tag']['colour'])) {
                $tag['Tag']['colour'] = '#0088cc';
            }
            $rows[] = [
                'tag' => $tag,
                'parsed' => TagChipTool::parse($tag['Tag']['name']),
            ];
        }
        return $rows;
    }

    private function wrap($body, array $options)
    {
        $class = 'hinge-tags';
        if (!empty($options['class'])) {
            $class .= ' ' . $options['class'];
        }
        return sprintf('<span class="%s">%s</span>', h($class), $body);
    }

    /**
     * @param array $row
     * @param string $mode 'flow' hinges on content; 'member' sits in a group
     *                     block whose header already carries the ancestry
     * @param array $options
     * @return string
     */
    private function renderChip(array $row, $mode, array $options)
    {
        $tag = $row['tag'];
        $p = $row['parsed'];
        $display = $options['display'] ?? 'full';
        $isLocal = !empty($tag['local']);
        $rel = isset($tag['relationship_type']) && $tag['relationship_type'] !== ''
            ? $tag['relationship_type']
            : null;
        $hidePath = $mode === 'member' || $display !== 'full';

        $nv = $tag['Tag']['numerical_value'] ?? null;
        $hasNv = $nv !== null && $nv !== '' && is_numeric($nv) && $display !== 'swatch';
        $nv = $hasNv ? $nv + 0 : null;
        $over = $hasNv && ($nv > 100 || $nv < 0);

        list($hue, $sat, $declared) = $this->hueOf($p, $tag['Tag']['colour']);
        $inline = $mode === 'flow' && $display === 'full'
            && TagChipTool::inlineWidth($p) <= ($options['budget'] ?? 300);

        $classes = ['hg-chip'];
        if ($isLocal) {
            $classes[] = 'is-local';
        }
        if ($display === 'swatch') {
            $classes[] = 'is-swatch';
        } elseif ($hidePath && !$rel) {
            $classes[] = 'is-tight';
        } elseif ($inline) {
            $classes[] = 'is-inline';
        }
        if ($hasNv) {
            $classes[] = 'has-meter';
        }
        if ($declared !== null) {
            $classes[] = 'has-colour';
        }
        $tight = in_array('is-tight', $classes, true);

        $inner = '';
        if ($display !== 'swatch') {
            // A free-text tag has no ancestry, so it gets no rail at all
            $showPath = !$hidePath && $p['namespace'] !== null;
            if (!$tight && ($showPath || $rel || (!$inline && $hasNv))) {
                $rail = '';
                if ($rel) {
                    $rail .= sprintf(
                        '<span class="hg-rel" title="%s">%s</span>',
                        h(__('Relationship: %s', $rel)),
                        h($rel)
                    );
                }
                if ($showPath) {
                    $path = sprintf('<b class="hg-ns">%s</b>', h($p['namespace']));
                    foreach ($p['above'] as $seg) {
                        $path .= '<i class="hg-sep">&rsaquo;</i>' . h($seg);
                    }
                    $rail .= sprintf('<span class="hg-path">%s</span>', $path);
                }
                if ($hasNv && !$inline) {
                    $rail .= $this->numeral($nv, $over);
                }
                $inner .= sprintf('<span class="hg-rail">%s</span>', $rail);
            }

            $tail = sprintf('<span class="hg-leaf">%s</span>', h($p['leaf']));
            if ($hasNv && ($inline || $tight)) {
                $tail .= $this->numeral($nv, $over);
            }
            if ($isLocal) {
                $tail .= sprintf(
                    '<span class="hg-flag" title="%s">%s</span>',
                    __('Local tag'),
                    __('local')
                );
            }
            $inner .= sprintf('<span class="hg-tail">%s</span>', $tail);
            if ($hasNv) {
                $inner .= sprintf(
                    '<span class="hg-meter%s" aria-hidden="true"><i style="width:%d%%"></i></span>',
                    $over ? ' is-over' : '',
                    $over ? 100 : max(0, min(100, (int)round($nv)))
                );
            }
        }

        $title = $p['raw'];
        if ($rel) {
            $title = $rel . ': ' . $title;
        }
        if ($isLocal) {
            $title .= ' (' . __('local') . ')';
        }
        $attrs = sprintf(
            'class="%s" style="--hg-h:%d;--hg-s:%s%s" title="%s"',
            implode(' ', $classes),
            $hue,
            $sat,
            $declared === null ? '' : ';--hg-c:' . $declared,
            h($title)
        );
        if ($display === 'swatch') {
            $attrs .= sprintf(' aria-label="%s"', h($title));
        }
        $tagId = isset($tag['Tag']['id']) ? (int)$tag['Tag']['id'] : 0;
        $searchUrl = $options['searchUrl'] ?? '/events/index/searchtag:';
        $href = null;
        if (isset($options['href']) && is_callable($options['href'])) {
            $href = $options['href']($tag);
        } elseif ($tagId && $searchUrl !== '' && $searchUrl !== false) {
            $href = $this->baseurl() . $searchUrl . $tagId;
        }
        if ($tagId) {
            $attrs .= sprintf(' data-tag-id="%d"', $tagId);
        }
        if ($href !== null && $href !== '') {
            $out = sprintf('<a href="%s" %s>%s</a>', h($href), $attrs, $inner);
        } else {
            $out = sprintf('<span %s>%s</span>', $attrs, $inner);
        }

        if (!empty($options['canModifyAll']) || (!empty($options['canModifyLocal']) && $isLocal)) {
            $scope = $options['scope'] ?? 'event';
            if (!empty($tag['id'])) {
                $out .= sprintf(
                    '<a class="hg-act noPrint modal-open" href="%s" title="%s" aria-label="%s" role="button" tabindex="0">'
                        . '<i class="fas fa-project-diagram"></i></a>',
                    h(sprintf('%s/tags/modifyTagRelationship/%s/%s', $this->baseurl(), $scope, $tag['id'])),
                    __('Modify Tag Relationship'),
                    __('Modify relationship for tag %s', h($p['raw']))
                );
            }
            $out .= sprintf(
                '<button type="button" class="hg-act noPrint" title="%s" aria-label="%s" onclick="%s">&times;</button>',
                __('Remove tag'),
                __('Remove tag %s', h($p['raw'])),
                h(sprintf(
                    "removeObjectTagPopup(this, '%s', %s, %s)",
                    $scope,
                    (int)($options['id'] ?? 0),
                    $tagId
                ))
            );
        }
        if (isset($options['prefix']) && is_callable($options['prefix'])) {
            $out = $options['prefix']($tag) . $out;
        }
        return sprintf(
            '<span class="hg-unit" data-tag-item data-tag-name="%s">%s</span>',
            h(mb_strtolower($p['raw'])),
            $out
        );
    }

    private function renderGroup(array $group, array $options)
    {
        $count = count($group['rows']);
        $classes = ['hg-group'];
        if ($count >= $options['wideAt']) {
            $classes[] = 'is-wide';
        }
        // A hand-picked palette keeps a bar on every member, because those
        // bars differ; a derived one puts its single hue on the block.
        if (in_array(mb_strtolower($group['namespace']), $this->semanticNamespaces(), true)) {
            $classes[] = 'is-varied';
        }

        $head = sprintf('<b class="hg-hns">%s</b>', h($group['namespace']));
        if (!empty($group['above'])) {
            $head .= sprintf(
                '<span class="hg-hpath">&rsaquo; %s</span>',
                h(implode(' › ', $group['above']))
            );
        }
        $head .= sprintf(
            '<span class="hg-count" title="%s">%d</span>',
            __('%s tags share this prefix', $count),
            $count
        );

        $members = '';
        foreach ($group['rows'] as $row) {
            $members .= $this->renderChip($row, 'member', $options);
        }

        return sprintf(
            '<span class="%s" style="--hg-h:%d;--hg-s:62%%%s" title="%s">'
                . '<span class="hg-head">%s</span><span class="hg-members">%s</span></span>',
            implode(' ', $classes),
            TagChipTool::hue($group['namespace']),
            empty($options['track']) ? '' : sprintf(';--hg-colw:%dpx', $options['track']),
            h(implode(':', array_merge([$group['namespace']], $group['above']))),
            $head,
            $members
        );
    }

    private function numeral($nv, $over)
    {
        return sprintf(
            '<span class="hg-n%s" title="%s">%s</span>',
            $over ? ' is-over' : '',
            __('Numerical value: %s', h($nv)),
            h($over ? $nv . '↑' : $nv)
        );
    }

    private function baseurl()
    {
        return $this->_View->viewVars['baseurl'] ?? (string)Configure::read('MISP.baseurl');
    }
}
