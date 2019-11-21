<?php if (!empty($diagnostics)): ?>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th><?php echo h($scope) ?></th>
                <th><?php echo __('Message') ?></th>
                <th><?php echo __('Diagnostic') ?></th>
            </tr>
        </thead>
        <?php foreach ($diagnostics as $key => $diagnostic): ?>
            <tr>
                <td><kbd><?php echo h($key) ?></kbd></td>
                <td>
                    <ul>
                        <?php foreach ($diagnostic['diagnosticMessage'] as $message): ?>
                            <li><?php echo h($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </td>
                <?php unset($diagnostic['diagnosticMessage']) ?>
                <td>
                    <div class="well toHighlight"><?php echo json_encode($diagnostic) ?></div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<script>
$('.toHighlight').each(function() {
    var $this = $(this);
    $this.html(syntaxHighlightJson($this.text(), 4));
})



// TO DEL
function syntaxHighlightJson(json, indent) {
    if (indent === undefined) {
        indent = 2;
    }
    if (typeof json == 'string') {
        json = JSON.parse(json);
    }
    json = JSON.stringify(json, undefined, indent);
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/(?:\r\n|\r|\n)/g, '<br>').replace(/ /g, '&nbsp;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
            var cls = 'json_number';
            if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                            cls = 'json_key';
                    } else {
                            cls = 'json_string';
                    }
            } else if (/true|false/.test(match)) {
                    cls = 'json_boolean';
            } else if (/null/.test(match)) {
                    cls = 'json_null';
            }
            return '<span class="' + cls + '">' + match + '</span>';
    });
}
</script>