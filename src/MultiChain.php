<?php

namespace Beycan\MultiChain;

use Web3\Validators\AddressValidator;
use Web3p\EthereumTx\Transaction as PendingTransaction;

final class MultiChain
{
    /**
     * The class in which we execute transactions on the connected blockchain network
     * @var Provider
     */
    private static $provider;

    /**
     * The Transaction signed with private key
     * @var PendingTransaction|null
     */
    private $pendingTransaction = null;

    /**
     * Start time for 15 second timeout
     * @var int
     */
    private $time;

    /**
     * Exception codes
     * @var array
     */
    public static $codes = [
        10000 => 'Insufficient balance!',
        11000 => 'There was a problem retrieving the balance',
        12000 => 'There was a problem retrieving the decimals value',
        13000 => 'There was a problem retrieving the transaction id',
        14000 => 'There was a problem retrieving the chain id',
        14001 => 'There was a problem retrieving the total supply',
        15000 => 'Before you can use the signing process, you must create a pending transaction.',
        16000 => 'Transaction time out!',
        18000 => 'It should contain native currency, symbol and decimals values',
        20000 => 'The amount cannot be zero or less than zero!',
        21000 => 'Invalid sender address!',
        22000 => 'Invalid receiver address!',
        23000 => 'Invalid token address!',
        24000 => 'Invalid transaction id!',
        25000 => 'Invalid transaction data!',
        26000 => 'Transaction failed!'
    ];

    /**
     * @param string $rpcUrl
     * @param array $currency
     * @param integer $timeOut
     */
    public function __construct(string $rpcUrl, array $currency, int $timeOut = 5)
    {
        self::$provider = new Provider($rpcUrl, $currency, $timeOut);
        $this->time = time();
    }

    /**
     * @return Provider
     */
    public static function getProvider() : Provider
    {
        return self::$provider;
    }

    /**
     * Start transfer process
     * @param string $from
     * @param string $to
     * @param float $amount
     * @param string|null $tokenAddress
     * @return MultiChain
     * @throws Exception
     */
    public function transfer(string $from, string $to, float $amount, ?string $tokenAddress = null) : MultiChain
    {
        if (is_null($tokenAddress)) {
            return $this->coinTransfer($from, $to, $amount);
        } else {
            return $this->tokenTransfer($from, $to, $amount, $tokenAddress);
        }
    }

    /**
     * Start token transfer process
     * @param string $from
     * @param string $to
     * @param float $amount
     * @param string $tokenAddress
     * @return MultiChain
     * @throws Exception
     */
    public function tokenTransfer(string $from, string $to, float $amount, string $tokenAddress) : MultiChain
    {
        $this->validate($from, $to, $amount, $tokenAddress);

        $transferData = (new Token($tokenAddress))->transferData($from, $to, $amount);

        $this->pendingTransaction = new PendingTransaction($transferData);
    
        return $this;
    }

    /**
     * Start coin transfer process
     * @param string $from
     * @param string $to
     * @param float $amount
     * @return MultiChain
     * @throws Exception
     */
    public function coinTransfer(string $from, string $to, float $amount) : MultiChain
    {
        $this->validate($from, $to, $amount);

        $transferData = (new Coin())->transferData($from, $to, $amount);

        $this->pendingTransaction = new PendingTransaction($transferData);
    
        return $this;
    }

    /**
     * Signs the transaction waiting to be signed and initiates the transfer
     * @param string $privateKey
     * @return Transaction
     * @throws Exception
     */
    public function sign(string $privateKey) : Transaction
    {
        if ($this->pendingTransaction instanceof PendingTransaction) {
            $signedTransaction = $this->pendingTransaction->sign($privateKey);
            try {
                $transactionId = self::$provider->sendSignedTransaction($signedTransaction);
            } catch (\Exception $e) {
                if ((time() - $this->time) >= 15) {
                    throw new \Exception("Transaction time out!", 16000);
                } else {
                    if ($e->getCode() == -32000 && $e->getMessage() != 'invalid sender') {
                        return $this->sign($privateKey);
                    }
                }
            }
            return $this->createTransaction($transactionId);
        } else {
            throw new \Exception("Before you can use the signing process, you must create a pending transaction.", 15000);
        }
    }

    /**
     * @param string $transactionId
     * @return Transaction
     * @throws Exception
     */
    private function createTransaction(string $transactionId) : Transaction
    {
        try {
            return new Transaction($transactionId);
        } catch (\Exception $e) {
            if ((time() - $this->time) >= 15) {
                throw new \Exception("Transaction failed.", 26000);
            } else {
                if ($e->getCode() == 0) {
                    return $this->createTransaction($transactionId);
                }
            }
        }
    }
    
    /**
     * Validate parameters
     *
     * @param string $from
     * @param string $to
     * @param float $amount
     * @param string|null $tokenAddress
     * @return void
     * @throws Exception
     */
    private function validate(string $from, string $to, float $amount, ?string $tokenAddress = null) : void
    {
        if ($amount <= 0) {
            throw new \Exception("The amount cannot be zero or less than zero!", 20000);
        } 

        if (AddressValidator::validate($from) === false) {
            throw new \Exception('Invalid sender address!', 21000);
        }

        if (AddressValidator::validate($to) === false) {
            throw new \Exception('Invalid receiver address!', 22000);
        }

        if (!is_null($tokenAddress) && AddressValidator::validate($tokenAddress) === false) {
            throw new \Exception('Invalid token address!', 23000);
        }
    }

}