<?php

/**
 * What a URL looked like when somebody fetched it.
 *
 * **Nothing emits this today**, and this is the one conversion in its
 * group that needs an upstream template before it needs a module
 * change: one module has the page bytes as an attachment and nowhere
 * to put them, and another has a screenshot URL, a title and a server
 * banner and emits none of it.
 *
 * **A capture with no image is still a capture.** An archived copy and
 * its extracted text are what a disinformation reader came for as much
 * as the picture is, so the widget draws a thumbnail where there is
 * one and the URL and its date where there is not.
 */
class PageCaptureRenderer extends ValueRendererBase
{
    public $id = 'page-capture';

    public $templates = array('image', 'domain-crawled');

    public $compact = 'Values/Renderers/page_capture_compact';

    public $full = 'Values/Renderers/page_capture_full';

    public $producer = self::PRODUCER_CONVERSION;

    public function __construct()
    {
        $this->description = __('A captured copy of a page, with what'
            . ' it looked like.');
        $this->producer_note = __('One module holds the page bytes and'
            . ' another a screenshot URL; neither emits an object, and'
            . ' a page-capture template does not exist yet.');
    }

    public function matches(array $objects, array $attributes)
    {
        foreach ($objects as $object) {
            if ($this->captureOf($object) !== null) {
                return true;
            }
        }
        return false;
    }

    public function prepare(array $objects, array $attributes)
    {
        $captures = array();
        foreach ($objects as $object) {
            $capture = $this->captureOf($object);
            if ($capture !== null) {
                $captures[] = $capture;
            }
        }
        usort($captures, function ($a, $b) {
            return ($b['captured'] ?? $b['ran_at'] ?? 0)
                - ($a['captured'] ?? $a['ran_at'] ?? 0);
        });
        return array(
            'captures' => $captures,
            'newest' => empty($captures) ? null : $captures[0],
            'count' => count($captures),
            'sources' => $this->sources($objects),
        );
    }

    /**
     * @param array $object
     * @return array|null
     */
    private function captureOf(array $object)
    {
        $url = $this->firstValue($object, array('url', 'link'));
        $attachment = $this->value($object, 'attachment');
        $archive = $this->value($object, 'archive');
        $text = $this->firstValue($object, array('image-text', 'text'));
        if ($url === null && $attachment === null && $archive === null
            && $text === null
        ) {
            return null;
        }
        return array(
            'url' => $url,
            'domain' => $this->value($object, 'domain'),
            'filename' => $this->value($object, 'filename'),
            /*
             * The attachment relation carries a filename rather than
             * the bytes: what arrives over the wire from a module is a
             * name and a payload the pane already knows how to offer,
             * and a widget that tried to inline it would be drawing
             * something it has not got.
             */
            'attachment' => $attachment,
            'archive' => $archive,
            'text' => $text,
            'captured' => null,
            'module' => $object['module'] ?? null,
            'ran_at' => $object['ran_at'] ?? null,
        );
    }
}
