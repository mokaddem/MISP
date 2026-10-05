<?php

/**
 * How a galaxy matrix is laid out: the column order of merged tabs, and the
 * technique groups a column draws.
 */
class GalaxyMatrixLayout
{
    /**
     * One column order from several, keeping each one's relative order;
     * a column no sequence places is taken in first-appearance order.
     *
     * @param array $sequences lists of column names
     * @return array
     */
    public static function mergeColumnOrders(array $sequences)
    {
        $rank = [];
        $incoming = [];
        $next = [];
        foreach ($sequences as $columns) {
            $previous = null;
            foreach (array_values((array)$columns) as $column) {
                $column = (string)$column;
                if (!isset($rank[$column])) {
                    $rank[$column] = count($rank);
                    $incoming[$column] = 0;
                    $next[$column] = [];
                }
                if ($previous !== null && $previous !== $column && !isset($next[$previous][$column])) {
                    $next[$previous][$column] = true;
                    $incoming[$column]++;
                }
                $previous = $column;
            }
        }
        $order = [];
        while (!empty($incoming)) {
            $pick = null;
            foreach ($incoming as $column => $count) {
                $column = (string)$column;
                if ($count === 0 && ($pick === null || $rank[$column] < $rank[$pick])) {
                    $pick = $column;
                }
            }
            if ($pick === null) {
                // Sequences that disagree: take the earliest seen and carry on
                foreach (array_keys($incoming) as $column) {
                    $column = (string)$column;
                    if ($pick === null || $rank[$column] < $rank[$pick]) {
                        $pick = $column;
                    }
                }
            }
            $order[] = $pick;
            unset($incoming[$pick]);
            foreach (array_keys($next[$pick]) as $column) {
                if (isset($incoming[$column])) {
                    $incoming[$column]--;
                }
            }
        }
        return $order;
    }

    /**
     * ATT&CK's Enterprise tabs: every `attack-*` platform, PRE first so
     * reconnaissance leads the merged column order.
     *
     * @param array $killChain tab => columns
     * @return array tab names
     */
    public static function enterpriseTabs(array $killChain)
    {
        $tabs = array_values(array_filter(array_map('strval', array_keys($killChain)), function ($tab) {
            return strpos($tab, 'attack-') === 0 && $tab !== 'attack-enterprise';
        }));
        usort($tabs, function ($a, $b) {
            return ($b === 'attack-PRE') <=> ($a === 'attack-PRE');
        });
        return $tabs;
    }
}
