<?php

/**
 * What has moved through a wallet.
 *
 * **Nothing emits this today**, and of the eight shapes in that state
 * this is the cheapest to fix: the module that answers for Bitcoin
 * already computes the balance, the totals received and sent, and a
 * time and a value in three currencies per transaction, and then
 * prints them. Every field it holds has a relation waiting for it in
 * two templates that already exist.
 *
 * Four templates are claimed because the ecosystem splits the same
 * answer two ways: a wallet and its transactions as separate objects,
 * or one chain-agnostic address object carrying its own totals.
 */
class CryptoFlowRenderer extends ValueRendererBase
{
    public $id = 'crypto-flow';

    public $templates = array(
        'coin-address',
        'btc-wallet',
        'btc-transaction',
        'cryptocurrency-transaction',
    );

    public $compact = 'Values/Renderers/crypto_flow_compact';

    public $full = 'Values/Renderers/crypto_flow_full';

    public $producer = self::PRODUCER_CONVERSION;

    public function __construct()
    {
        $this->description = __('A wallet\'s balance and the'
            . ' transactions through it.');
        $this->producer_note = __('The module that answers this'
            . ' computes every field and then prints them as text.');
    }

    public function matches(array $objects, array $attributes)
    {
        foreach ($objects as $object) {
            if ($this->walletOf($object) !== null
                || $this->transactionOf($object) !== null
            ) {
                return true;
            }
        }
        return false;
    }

    public function prepare(array $objects, array $attributes)
    {
        $wallet = null;
        $transactions = array();
        foreach ($objects as $object) {
            $found = $this->walletOf($object);
            if ($found !== null
                && ($wallet === null
                    || ($found['ran_at'] ?? 0) > ($wallet['ran_at'] ?? 0))
            ) {
                $wallet = $found;
            }
            $transaction = $this->transactionOf($object);
            if ($transaction !== null) {
                $transactions[] = $transaction;
            }
        }
        usort($transactions, function ($a, $b) {
            return ($b['at'] ?? 0) - ($a['at'] ?? 0);
        });
        return array(
            'wallet' => $wallet,
            'transactions' => $transactions,
            'count' => count($transactions),
            /*
             * The last movement is the fact the compact form leads
             * with — a wallet with a balance and no movement in two
             * years is a different thing from one that moved this
             * morning — and it comes off the transactions where there
             * are any, because a wallet object's own `last-seen` is
             * when the service last looked.
             */
            'last_movement' => empty($transactions)
                ? ($wallet['last_seen'] ?? null)
                : $transactions[0]['at'],
            'sources' => $this->sources($objects),
        );
    }

    /**
     * @param array $object
     * @return array|null
     */
    private function walletOf(array $object)
    {
        $name = $object['name'] ?? null;
        if ($name === 'btc-wallet') {
            $balance = $this->number(
                $this->value($object, 'balance_BTC')
            );
            if ($balance === null) {
                return null;
            }
            return array(
                'address' => $this->value($object, 'wallet-address'),
                'symbol' => 'BTC',
                'balance' => $balance,
                'received' => $this->number(
                    $this->value($object, 'BTC_received')
                ),
                'sent' => $this->number(
                    $this->value($object, 'BTC_sent')
                ),
                'transactions' => null,
                'last_seen' => $this->stamp(
                    $this->value($object, 'time')
                ),
                'module' => $object['module'] ?? null,
                'ran_at' => $object['ran_at'] ?? null,
            );
        }
        if ($name !== 'coin-address') {
            return null;
        }
        $balance = $this->number(
            $this->value($object, 'current-balance')
        );
        $received = $this->number(
            $this->value($object, 'total-received')
        );
        if ($balance === null && $received === null) {
            return null;
        }
        return array(
            'address' => $this->firstValue($object, array(
                'address', 'address-crypto', 'address-xmr',
            )),
            'symbol' => $this->value($object, 'symbol'),
            'balance' => $balance,
            'received' => $received,
            'sent' => $this->number(
                $this->value($object, 'total-sent')
            ),
            'transactions' => $this->number(
                $this->value($object, 'total-transactions')
            ),
            'last_seen' => $this->stamp(
                $this->firstValue($object, array(
                    'last-updated', 'last-seen',
                ))
            ),
            'module' => $object['module'] ?? null,
            'ran_at' => $object['ran_at'] ?? null,
        );
    }

    /**
     * @param array $object
     * @return array|null
     */
    private function transactionOf(array $object)
    {
        $name = $object['name'] ?? null;
        if ($name !== 'btc-transaction'
            && $name !== 'cryptocurrency-transaction'
        ) {
            return null;
        }
        $number = $this->value($object, 'transaction-number');
        $at = $this->stamp($this->value($object, 'time'));
        if ($number === null && $at === null) {
            return null;
        }
        return array(
            'number' => $number,
            'at' => $at,
            'counterparty' => $this->firstValue($object, array(
                'btc-address', 'address',
            )),
            'symbol' => $this->value($object, 'symbol')
                ?? ($name === 'btc-transaction' ? 'BTC' : null),
            'value' => $this->number($this->firstValue($object, array(
                'value_BTC', 'value',
            ))),
            'value_eur' => $this->number(
                $this->value($object, 'value_EUR')
            ),
            'value_usd' => $this->number(
                $this->value($object, 'value_USD')
            ),
            'module' => $object['module'] ?? null,
        );
    }
}
