<?php
    /*
    Expect:
    
    $dbSchemaDiagnostics = array(
        $table_name1 => array(
            'description' => $description1,
            'column_name' => $column_name1,
            'actual' => array(
                (int) 0 => 'object_relation',
                (int) 1 => 'varchar(128)',
                [...]
            ),
            'expected' => array(
                (int) 0 => 'object_relation',
                (int) 1 => 'varchar(255)',
                [...]
            )
        ),
        [...]
    );


    */



    function highlightAndSanitize($dirty, $toHighlight, $colorType = 'success')
    {
        if (is_array($dirty)) {
            $arraySane = array();
            foreach ($dirty as $i => $item) {
                if (in_array($item, $toHighlight)) {
                    $arraySane[] = sprintf('<span class="label label-%s">', $colorType) . h($item) . '</span>';
                } else {
                    $arraySane[] = h($item);
                }
            }
            return $arraySane;
        } else {
            $sane = h($dirty);
            $sane = str_replace($toHighlight, sprintf('<span class="label label-%s">', $colorType)  . h($toHighlight) . '</span>', $sane);
            return $sane;
        }
    }
?>

<?php
    if (count($dbSchemaDiagnostics) > 0) {
        echo sprintf('<span  style="margin-bottom: 5px;" class="label label-important" title="%s">%s<i style="font-size: larger;" class="fas fa-times"></i></span>',
            __('The current database schema does not match the expected format.'),
            __('Database schema diagnostic: ')
        );
        echo sprintf('<div class="alert alert-error"><strong>%s</strong> %s <br/>%s</div>',
            __('Critical warning!'),
            __('The MISP database seems to be in an inconsistent state. Immediate attention is required.'),
            __('⚠ This diagnostic tool is in experimental state - the highlighted issues may be benign. If you are unsure, please open an issue on with the issues identified over at https://github.com/MISP/MISP for clarification.')
        );
        $table = sprintf('%s%s%s', 
            '<table class="table table-bordered table-condensed">',
            sprintf('<thead><th>%s</th><th>%s</th><th>%s</th><th>%s</th></thead>', __('Table name'),  __('Description'), __('Expected schema'), __('Actual schema')),
            '<tbody>'
        );
        $rows = '';
        foreach ($dbSchemaDiagnostics as $tableName => $tableDiagnostic) {
            $rows .= '<tr>';
                $rows .= sprintf('<td rowspan="%s" colspan="0" class="bold">%s</td>', count($tableDiagnostic)+1, h($tableName));
            $rows .= '</tr>';

            foreach ($tableDiagnostic as $i => $columnDiagnostic) {
                $columnDiagnostic['expected'] = isset($columnDiagnostic['expected']) ? $columnDiagnostic['expected'] : array();
                $columnDiagnostic['actual'] = isset($columnDiagnostic['actual']) ? $columnDiagnostic['actual'] : array();
                $columnDiagnostic['description'] = isset($columnDiagnostic['expected']) ? $columnDiagnostic['description'] : '';
                $columnDiagnostic['column_name'] = isset($columnDiagnostic['column_name']) ? $columnDiagnostic['column_name'] : '';

                $intersect = array_intersect($columnDiagnostic['expected'], $columnDiagnostic['actual']);
                $diffExpected = array_diff($columnDiagnostic['expected'], $intersect);
                $diffActual = array_diff($columnDiagnostic['actual'], $intersect);

                $saneDescription = highlightAndSanitize($columnDiagnostic['description'], $columnDiagnostic['column_name'], '');
                $saneExpected = highlightAndSanitize($columnDiagnostic['expected'], $diffExpected);
                $saneActual = highlightAndSanitize($columnDiagnostic['actual'], $diffActual, 'important');
                $uniqueRow = empty($saneExpected) && empty($saneActual);

                $rows .= sprintf('<tr class="%s">', $columnDiagnostic['is_critical'] ? 'error' : '');
                    $rows .= sprintf('<td %s>%s</td>', $uniqueRow ? 'colspan=3' : '', $saneDescription);
                    if (!$uniqueRow) {
                        $rows .= sprintf('<td class="dbColumnDiagnosticRow" data-table="%s" data-index="%s">%s</td>', h($tableName), h($i), implode(' ', $saneExpected));
                        $rows .= sprintf('<td class="dbColumnDiagnosticRow" data-table="%s" data-index="%s">%s</td>', h($tableName), h($i), implode(' ', $saneActual));
                    }
                $rows .= '</tr>';
            }
        }
        $table .= $rows . '</tbody></table>';
        echo $table;
    } else {
        if (empty($error)) {
            echo sprintf('<span class="label label-success" title="%s">%s <i class="fas fa-check"></i></span>',
                __('The current database is correct'),
                __('Database schema diagnostic: ')
            );
        } else {
            echo sprintf('<span class="label label-important" style="margin-left: 5px;" >%s <i class="fas fa-times"></i></span>',
                h($error)
            );
        }
    }
    echo sprintf('<span class="label label-%s" style="margin-left: 5px;">%s</span>',
        is_numeric($expectedDbVersion) ? 'success' : 'important',
        __('Expected DB_version: ') . h($expectedDbVersion)
    );
    if ($expectedDbVersion == $actualDbVersion) {
        echo sprintf('<span class="label label-success" style="margin-left: 5px;" title="%s">%s <i class="fas fa-check"></i></span>',
            __('The current database version matches the expected one'),
            __('Actual DB_version: ') . h($actualDbVersion)
        );
    } else {
        echo sprintf('<span class="label label-important" style="margin-left: 5px;" title="%s">%s <i class="fas fa-times"></i></span>',
            __('The current database version does not match the expected one'),
            __('Actual DB_version: ') . h($actualDbVersion)
        );
    }
    echo '<br/>';
    $humanReadableTime = sprintf('%smin %ssec', floor($remainingLockTime / 60), $remainingLockTime % 60);
    echo sprintf('<span class="label label-%s" title="%s" style="margin-left: 5px;">%s <i class="fas fa-%s"></i></span>',
        $updateLocked ? 'important' : 'success',
        $updateLocked ? __('Updates are locked') : __('Updates are not locked'),
        $updateLocked ? ( 
            $updateFailNumberReached ? 
                __('Update are locked due to to many update fails') : sprintf(__('Update unlocked in %s'), h($humanReadableTime)))
            : __('Updates are not locked'),
        $updateLocked ? 'times' : 'check'
    )
?>
<script>
var dbSchemaDiagnostics = <?php echo json_encode($dbSchemaDiagnostics); ?>;
var dbSchemaDiagnosticsColumns = <?php echo json_encode($checkedTableColumn); ?>;

$(document).ready(function() {
    var popoverDiagnostic = $('td.dbColumnDiagnosticRow').popover({
        title: '<?php echo __('Column diagnostic'); ?>',
        content: function() {
            var $row = $(this);
            var tableName = $row.data('table');
            var columnId = $row.data('index');
            var expectedArray = [];
            var actualArray = [];
            dbSchemaDiagnosticsColumns.forEach(function(columnName) {
                expectedArray.push(dbSchemaDiagnostics[tableName][columnId].expected[columnName]);
                actualArray.push(dbSchemaDiagnostics[tableName][columnId].actual[columnName]);
            });
            var popoverHtml = arrayToNestedTable(
                dbSchemaDiagnosticsColumns,
                [
                    expectedArray,
                    actualArray,
                ]
            );
            return popoverHtml;
        },
        html: true,
        placement: function(context, src) {
            $(context).css('max-width', 'fit-content'); // make popover larger
            return 'bottom';
        },
        container: 'body',
        trigger: 'hover'
    });
});
</script>
